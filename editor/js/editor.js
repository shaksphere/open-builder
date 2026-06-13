/**
 * Open Builder — editor application.
 *
 * Vanilla JS (no build step) so the plugin runs the moment it's dropped into
 * wp-content/plugins. The canvas is a same-origin iframe rendered server-side
 * by the PHP Renderer (single source of truth → true WYSIWYG). The editor owns
 * the node tree, mutates it locally, and asks the server to re-render (debounced)
 * and to persist on save.
 */
(function () {
	'use strict';

	var BOOT = window.OPENB_BOOT || {};
	var WIDGETS = BOOT.widgets || {};

	/* ----------------------------------------------------------------------- *
	 * State
	 * ----------------------------------------------------------------------- */
	var state = {
		tree: Array.isArray(BOOT.tree) ? BOOT.tree : [],
		globals: BOOT.globals || { colors: [], fonts: [], sizes: [] },
		selectedId: null,
		device: 'desktop',
		dirty: false,
		history: [],
		future: [],
		drag: null // { mode:'new'|'move', type|id }
	};

	/* ----------------------------------------------------------------------- *
	 * Small helpers
	 * ----------------------------------------------------------------------- */
	function uid() {
		return 'ob' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-2);
	}
	function deepClone(v) { return JSON.parse(JSON.stringify(v)); }
	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		attrs = attrs || {};
		Object.keys(attrs).forEach(function (k) {
			if (k === 'class') node.className = attrs[k];
			else if (k === 'html') node.innerHTML = attrs[k];
			else if (k === 'text') node.textContent = attrs[k];
			else if (k.slice(0, 2) === 'on' && typeof attrs[k] === 'function') node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
			else if (attrs[k] != null) node.setAttribute(k, attrs[k]);
		});
		(children || []).forEach(function (c) {
			if (c == null) return;
			node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
		});
		return node;
	}
	function debounce(fn, ms) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(ctx, args); }, ms);
		};
	}

	/* ----------------------------------------------------------------------- *
	 * Tree operations
	 * ----------------------------------------------------------------------- */
	function findNode(id, nodes, parent) {
		nodes = nodes || state.tree;
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].id === id) return { node: nodes[i], list: nodes, index: i, parent: parent || null };
			if (nodes[i].children && nodes[i].children.length) {
				var hit = findNode(id, nodes[i].children, nodes[i]);
				if (hit) return hit;
			}
		}
		return null;
	}

	function newNode(type) {
		var def = WIDGETS[type];
		var content = def && def.defaults ? deepClone(def.defaults) : {};
		var node = { id: uid(), type: type, settings: { content: content, style: {}, advanced: { css_id: '', css_classes: '', custom_css: '' } }, children: [] };
		// Seed nested defaults for the columns layout: two columns.
		if (type === 'columns') {
			node.children = [makeColumn(), makeColumn()];
		}
		return node;
	}
	function makeColumn() {
		return { id: uid(), type: 'column', settings: { content: {}, style: {}, advanced: { css_id: '', css_classes: '', custom_css: '' } }, children: [] };
	}

	function pushHistory() {
		state.history.push(deepClone(state.tree));
		if (state.history.length > 50) state.history.shift();
		state.future = [];
	}

	function insertNode(node, targetId, position) {
		// position: 'before' | 'after' | 'inside'
		if (!targetId) { state.tree.push(node); return; }
		var hit = findNode(targetId);
		if (!hit) { state.tree.push(node); return; }
		if (position === 'inside') {
			hit.node.children = hit.node.children || [];
			hit.node.children.push(node);
		} else {
			var idx = position === 'after' ? hit.index + 1 : hit.index;
			hit.list.splice(idx, 0, node);
		}
	}

	function removeNode(id) {
		var hit = findNode(id);
		if (hit) hit.list.splice(hit.index, 1);
	}

	function moveNode(id, targetId, position) {
		var hit = findNode(id);
		if (!hit) return;
		// Guard: don't drop a node into its own descendant.
		if (targetId && isDescendant(id, targetId)) return;
		var node = hit.list.splice(hit.index, 1)[0];
		insertNode(node, targetId, position);
	}
	function isDescendant(ancestorId, maybeChildId) {
		var hit = findNode(ancestorId);
		if (!hit) return false;
		return !!findNode(maybeChildId, hit.node.children || []);
	}

	/* ----------------------------------------------------------------------- *
	 * API
	 * ----------------------------------------------------------------------- */
	function api(path, body) {
		return fetch(BOOT.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BOOT.restNonce },
			body: JSON.stringify(body || {})
		}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, data: j }; }); });
	}

	var rerender = debounce(function () { renderCanvas(); }, 250);

	function renderCanvas() {
		api('/render', { post_id: BOOT.postId, tree: state.tree }).then(function (res) {
			if (!res.ok) return;
			var doc = canvasDoc();
			if (!doc) return;
			var dyn = doc.getElementById('openb-dynamic-css');
			if (dyn) dyn.textContent = res.data.css || '';
			var canvas = doc.getElementById('openb-canvas');
			if (canvas) {
				canvas.innerHTML = res.data.html || '';
				if (!state.tree.length) {
					canvas.innerHTML = '<div class="openb-canvas-empty">' +
						'Drag a widget here, or click one in the left panel to start building.</div>';
				}
			}
			decorateCanvas();
		});
	}

	function canvasDoc() {
		var iframe = document.getElementById('openb-canvas-frame');
		return iframe && iframe.contentDocument ? iframe.contentDocument : null;
	}

	/* ----------------------------------------------------------------------- *
	 * Canvas decoration: selection, hover toolbars, drop zones
	 * ----------------------------------------------------------------------- */
	function decorateCanvas() {
		var doc = canvasDoc();
		if (!doc) return;
		var nodes = doc.querySelectorAll('[data-ob-id]');
		Array.prototype.forEach.call(nodes, function (n) {
			n.classList.add('openb-editable');
			if (n.getAttribute('data-ob-id') === state.selectedId) n.classList.add('openb-selected');
			n.addEventListener('click', onCanvasClick);
			n.addEventListener('dragover', onCanvasDragOver);
			n.addEventListener('drop', onCanvasDrop);
			n.addEventListener('dragleave', onCanvasDragLeave);
		});
		// Root-level drop when empty or dropping at the end. The #openb-canvas
		// container persists across re-renders, so attach its listeners once.
		var container = doc.getElementById('openb-canvas');
		if (container && !container.dataset.obBound) {
			container.dataset.obBound = '1';
			container.addEventListener('dragover', onRootDragOver);
			container.addEventListener('drop', onRootDrop);
		}
	}

	function onCanvasClick(e) {
		e.stopPropagation();
		e.preventDefault();
		var id = this.getAttribute('data-ob-id');
		selectNode(id);
	}

	function onCanvasDragOver(e) {
		if (!state.drag) return;
		e.preventDefault();
		e.stopPropagation();
		var rect = this.getBoundingClientRect();
		var type = this.getAttribute('data-ob-type');
		var isContainer = WIDGETS[type] && WIDGETS[type].isContainer;
		this.classList.remove('openb-drop-before', 'openb-drop-after', 'openb-drop-inside');
		var ratio = (e.clientY - rect.top) / rect.height;
		if (isContainer && ratio > 0.25 && ratio < 0.75) {
			this.classList.add('openb-drop-inside');
		} else if (ratio < 0.5) {
			this.classList.add('openb-drop-before');
		} else {
			this.classList.add('openb-drop-after');
		}
	}
	function onCanvasDragLeave() {
		this.classList.remove('openb-drop-before', 'openb-drop-after', 'openb-drop-inside');
	}
	function onCanvasDrop(e) {
		if (!state.drag) return;
		e.preventDefault();
		e.stopPropagation();
		var targetId = this.getAttribute('data-ob-id');
		var position = this.classList.contains('openb-drop-inside') ? 'inside'
			: this.classList.contains('openb-drop-before') ? 'before' : 'after';
		this.classList.remove('openb-drop-before', 'openb-drop-after', 'openb-drop-inside');
		applyDrop(targetId, position);
	}
	function onRootDragOver(e) {
		if (!state.drag) return;
		e.preventDefault();
	}
	function onRootDrop(e) {
		if (!state.drag) return;
		// Only handle if the drop wasn't already consumed by a node.
		if (e.target.closest && e.target.closest('[data-ob-id]')) return;
		e.preventDefault();
		applyDrop(null, 'inside');
	}

	function applyDrop(targetId, position) {
		var drag = state.drag;
		state.drag = null;
		if (!drag) return;
		pushHistory();
		if (drag.mode === 'new') {
			var node = newNode(drag.type);
			// Sections only drop at root level; if dropped onto a leaf, place after it.
			insertNode(node, targetId, position);
			selectNode(node.id, true);
		} else if (drag.mode === 'move') {
			moveNode(drag.id, targetId, position);
		}
		markDirty();
		renderCanvas();
		renderLayers();
	}

	/* ----------------------------------------------------------------------- *
	 * Selection + right panel
	 * ----------------------------------------------------------------------- */
	function selectNode(id, skipRerender) {
		state.selectedId = id;
		var doc = canvasDoc();
		if (doc) {
			Array.prototype.forEach.call(doc.querySelectorAll('.openb-selected'), function (n) { n.classList.remove('openb-selected'); });
			var sel = doc.querySelector('[data-ob-id="' + id + '"]');
			if (sel) sel.classList.add('openb-selected');
		}
		renderInspector();
		renderLayers();
		if (!skipRerender) { /* selection only; no canvas rerender needed */ }
	}

	/* ----------------------------------------------------------------------- *
	 * Layout shell
	 * ----------------------------------------------------------------------- */
	function buildShell() {
		var app = document.getElementById('openb-app');
		app.innerHTML = '';

		app.appendChild(buildTopbar());

		var main = el('div', { class: 'openb-main' });
		main.appendChild(buildLeftPanel());
		main.appendChild(buildCanvasArea());
		main.appendChild(buildRightPanel());
		app.appendChild(main);

		// Global drag end cleanup.
		document.addEventListener('dragend', function () { state.drag = null; });
	}

	function buildTopbar() {
		var devices = [
			['desktop', 'Desktop', 'M2 4h16v10H2zM7 17h6'],
			['tablet', 'Tablet', 'M5 3h10v14H5z'],
			['mobile', 'Mobile', 'M6 2h8v16H6z']
		];
		var deviceBtns = devices.map(function (d) {
			return el('button', {
				class: 'openb-device' + (state.device === d[0] ? ' is-active' : ''),
				title: d[1], 'data-device': d[0],
				onclick: function () { setDevice(d[0]); }
			}, [svg(d[2])]);
		});

		var saveBtn = el('button', { class: 'openb-btn openb-btn--primary', id: 'openb-save', onclick: save }, ['Save']);

		return el('div', { class: 'openb-topbar' }, [
			el('div', { class: 'openb-topbar__left' }, [
				el('span', { class: 'openb-logo', text: 'Open Builder' }),
				el('span', { class: 'openb-title', text: BOOT.postTitle || '' })
			]),
			el('div', { class: 'openb-topbar__center' }, deviceBtns),
			el('div', { class: 'openb-topbar__right' }, [
				el('button', { class: 'openb-btn', title: 'Undo', onclick: undo }, [svg('M7 7L3 11l4 4M3 11h10a4 4 0 010 8h-2')]),
				el('button', { class: 'openb-btn', title: 'Redo', onclick: redo }, [svg('M13 7l4 4-4 4M17 11H7a4 4 0 000 8h2')]),
				el('a', { class: 'openb-btn', href: BOOT.viewUrl, target: '_blank', rel: 'noopener' }, ['View']),
				el('a', { class: 'openb-btn', href: BOOT.exitUrl }, ['Exit']),
				saveBtn
			])
		]);
	}

	function buildLeftPanel() {
		var panel = el('div', { class: 'openb-left' });
		var tabs = el('div', { class: 'openb-tabs' }, [
			tabBtn('widgets', 'Widgets', true),
			tabBtn('layers', 'Layers'),
			BOOT.canManage ? tabBtn('globals', 'Globals') : null
		].filter(Boolean));
		panel.appendChild(tabs);

		panel.appendChild(el('div', { class: 'openb-tabpane', id: 'pane-widgets' }, [buildWidgetList()]));
		panel.appendChild(el('div', { class: 'openb-tabpane', id: 'pane-layers', style: 'display:none' }));
		if (BOOT.canManage) panel.appendChild(el('div', { class: 'openb-tabpane', id: 'pane-globals', style: 'display:none' }));

		tabs.addEventListener('click', function (e) {
			var b = e.target.closest('[data-tab]');
			if (!b) return;
			var name = b.getAttribute('data-tab');
			Array.prototype.forEach.call(tabs.children, function (c) { c.classList.toggle('is-active', c === b); });
			['widgets', 'layers', 'globals'].forEach(function (p) {
				var pane = document.getElementById('pane-' + p);
				if (pane) pane.style.display = p === name ? '' : 'none';
			});
			if (name === 'layers') renderLayers();
			if (name === 'globals') renderGlobals();
		});

		return panel;
	}
	function tabBtn(name, label, active) {
		return el('button', { class: 'openb-tab' + (active ? ' is-active' : ''), 'data-tab': name, text: label });
	}

	function buildWidgetList() {
		var groups = {};
		Object.keys(WIDGETS).forEach(function (type) {
			var w = WIDGETS[type];
			if (type === 'column') return; // columns are created implicitly
			(groups[w.category] = groups[w.category] || []).push(w);
		});
		var wrap = el('div', { class: 'openb-widgetlist' });
		Object.keys(groups).forEach(function (cat) {
			wrap.appendChild(el('div', { class: 'openb-widgetcat', text: cat }));
			var grid = el('div', { class: 'openb-widgetgrid' });
			groups[cat].forEach(function (w) {
				var item = el('div', {
					class: 'openb-widget', draggable: 'true', title: w.title,
					onclick: function () { quickAdd(w.type); }
				}, [
					el('span', { class: 'openb-widget__icon', html: widgetIcon(w.icon) }),
					el('span', { class: 'openb-widget__label', text: w.title })
				]);
				item.addEventListener('dragstart', function (e) {
					state.drag = { mode: 'new', type: w.type };
					e.dataTransfer.effectAllowed = 'copy';
					e.dataTransfer.setData('text/plain', w.type);
				});
				grid.appendChild(item);
			});
			wrap.appendChild(grid);
		});
		return wrap;
	}

	function quickAdd(type) {
		pushHistory();
		var node = newNode(type);
		// Append into the selected container if it accepts, else at root.
		var targetId = null, position = 'inside';
		if (state.selectedId) {
			var hit = findNode(state.selectedId);
			if (hit) {
				var selType = hit.node.type;
				if (WIDGETS[selType] && WIDGETS[selType].isContainer) {
					targetId = state.selectedId;
				} else {
					targetId = state.selectedId; position = 'after';
				}
			}
		}
		insertNode(node, targetId, position);
		markDirty();
		renderCanvas();
		renderLayers();
		selectNode(node.id, true);
	}

	function buildCanvasArea() {
		var area = el('div', { class: 'openb-canvasarea' });
		var frame = el('iframe', { class: 'openb-canvas-frame', id: 'openb-canvas-frame', src: BOOT.previewUrl });
		frame.addEventListener('load', function () { decorateCanvas(); if (!state.tree.length) renderCanvas(); });
		area.appendChild(el('div', { class: 'openb-canvas-scroll' }, [frame]));
		return area;
	}

	function buildRightPanel() {
		return el('div', { class: 'openb-right', id: 'openb-inspector' }, [
			el('div', { class: 'openb-empty', text: 'Select an element to edit its settings.' })
		]);
	}

	/* ----------------------------------------------------------------------- *
	 * Inspector (Content / Style / Advanced)
	 * ----------------------------------------------------------------------- */
	var inspectorTab = 'content';

	function renderInspector() {
		var panel = document.getElementById('openb-inspector');
		if (!panel) return;
		panel.innerHTML = '';
		if (!state.selectedId) {
			panel.appendChild(el('div', { class: 'openb-empty', text: 'Select an element to edit its settings.' }));
			return;
		}
		var hit = findNode(state.selectedId);
		if (!hit) { state.selectedId = null; return renderInspector(); }
		var node = hit.node;
		var def = WIDGETS[node.type] || { title: node.type, controls: {} };

		// Header with element actions.
		panel.appendChild(el('div', { class: 'openb-inspector__head' }, [
			el('span', { class: 'openb-inspector__title', text: def.title }),
			el('div', { class: 'openb-inspector__acts' }, [
				el('button', { class: 'openb-iconbtn', title: 'Duplicate', onclick: function () { duplicateNode(node.id); } }, [svg('M6 6h9v9H6zM3 3h9v2H5v7H3z')]),
				el('button', { class: 'openb-iconbtn', title: 'Delete', onclick: function () { deleteNode(node.id); } }, [svg('M4 5h12M8 5V3h4v2M6 5l1 11h6l1-11')])
			])
		]));

		var tabs = el('div', { class: 'openb-subtabs' }, [
			subTab('content', 'Content'),
			subTab('style', 'Style'),
			subTab('advanced', 'Advanced')
		]);
		panel.appendChild(tabs);
		tabs.addEventListener('click', function (e) {
			var b = e.target.closest('[data-subtab]');
			if (!b) return;
			inspectorTab = b.getAttribute('data-subtab');
			renderInspector();
		});

		var body = el('div', { class: 'openb-inspector__body' });
		if (inspectorTab === 'content') body.appendChild(buildContentControls(node, def));
		else if (inspectorTab === 'style') body.appendChild(buildStyleControls(node));
		else body.appendChild(buildAdvancedControls(node));
		panel.appendChild(body);
	}
	function subTab(name, label) {
		return el('button', { class: 'openb-subtab' + (inspectorTab === name ? ' is-active' : ''), 'data-subtab': name, text: label });
	}

	function buildContentControls(node, def) {
		var wrap = el('div', {});
		var controls = def.controls || {};
		var keys = Object.keys(controls);
		if (!keys.length) {
			wrap.appendChild(el('p', { class: 'openb-hint', text: 'This element has no content options. Use the Style tab.' }));
		}
		keys.forEach(function (key) {
			var ctrl = controls[key];
			var value = node.settings.content[key];
			if (value === undefined) value = ctrl.default;
			wrap.appendChild(controlField(ctrl, value, function (v) {
				node.settings.content[key] = v;
				markDirty(); rerender();
			}));
		});
		return wrap;
	}

	function controlField(ctrl, value, onChange) {
		var field = el('div', { class: 'openb-field' });
		if (ctrl.label) field.appendChild(el('label', { class: 'openb-field__label', text: ctrl.label }));
		var input;
		switch (ctrl.type) {
			case 'textarea':
			case 'html':
				input = el('textarea', { class: 'openb-input', rows: ctrl.type === 'html' ? 6 : 3 });
				input.value = value || '';
				input.addEventListener('input', function () { onChange(input.value); });
				break;
			case 'richtext':
				input = el('textarea', { class: 'openb-input', rows: 4 });
				input.value = value || '';
				input.addEventListener('input', function () { onChange(input.value); });
				field.appendChild(el('p', { class: 'openb-hint', text: 'Basic HTML allowed (p, strong, em, a, lists).' }));
				break;
			case 'select':
				input = el('select', { class: 'openb-input' });
				Object.keys(ctrl.choices || {}).forEach(function (k) {
					var opt = el('option', { value: k, text: ctrl.choices[k] });
					if (String(value) === k) opt.selected = true;
					input.appendChild(opt);
				});
				input.addEventListener('change', function () { onChange(input.value); });
				break;
			case 'toggle':
				input = el('label', { class: 'openb-switch' }, []);
				var cb = el('input', { type: 'checkbox' });
				cb.checked = !!value;
				cb.addEventListener('change', function () { onChange(cb.checked); });
				input.appendChild(cb);
				input.appendChild(el('span', { class: 'openb-switch__slider' }));
				break;
			case 'color':
				input = colorControl(value, onChange);
				break;
			case 'url':
				input = el('input', { class: 'openb-input', type: 'text', placeholder: 'https://' });
				input.value = value || '';
				input.addEventListener('input', function () { onChange(input.value); });
				break;
			case 'number':
				input = el('input', { class: 'openb-input', type: 'number' });
				input.value = value != null ? value : '';
				input.addEventListener('input', function () { onChange(input.value); });
				break;
			case 'image':
				input = imageControl(value, onChange);
				break;
			case 'gallery':
				input = galleryControl(value, onChange);
				break;
			case 'icon':
				input = iconControl(ctrl, value, onChange);
				break;
			case 'repeater':
				input = repeaterControl(ctrl, value, onChange);
				break;
			default:
				input = el('input', { class: 'openb-input', type: 'text' });
				input.value = value || '';
				input.addEventListener('input', function () { onChange(input.value); });
		}
		field.appendChild(input);
		return field;
	}

	function colorControl(value, onChange) {
		var wrap = el('div', { class: 'openb-colorctrl' });
		var swatchVal = value || '';
		var text = el('input', { class: 'openb-input', type: 'text', placeholder: '#000000 or var(--ob-color-primary)' });
		text.value = swatchVal;
		text.addEventListener('input', function () { onChange(text.value); });
		// Brand color swatches.
		var swatches = el('div', { class: 'openb-swatches' });
		(state.globals.colors || []).forEach(function (c) {
			swatches.appendChild(el('button', {
				class: 'openb-swatch', title: c.name, style: 'background:' + c.value,
				onclick: function () { text.value = 'var(--ob-color-' + c.id + ')'; onChange(text.value); }
			}));
		});
		wrap.appendChild(text);
		wrap.appendChild(swatches);
		return wrap;
	}

	function imageControl(value, onChange) {
		value = value || { id: 0, url: '' };
		var wrap = el('div', { class: 'openb-imagectrl' });
		var preview = el('div', { class: 'openb-imagectrl__preview' });
		function paint() {
			preview.innerHTML = value.url ? '<img src="' + escapeAttr(value.url) + '" alt="">' : '<span>No image</span>';
		}
		paint();
		var pick = el('button', { class: 'openb-btn openb-btn--block', text: 'Select Image', onclick: function () {
			openMedia(function (att) { value = { id: att.id, url: att.url }; paint(); onChange(value); });
		} });
		var clear = el('button', { class: 'openb-btn openb-btn--block', text: 'Remove', onclick: function () { value = { id: 0, url: '' }; paint(); onChange(value); } });
		var urlInput = el('input', { class: 'openb-input', type: 'text', placeholder: 'or paste image URL' });
		urlInput.value = value.url || '';
		urlInput.addEventListener('input', function () { value = { id: 0, url: urlInput.value }; onChange(value); });
		wrap.appendChild(preview);
		wrap.appendChild(pick);
		wrap.appendChild(urlInput);
		wrap.appendChild(clear);
		return wrap;
	}

	function galleryControl(value, onChange) {
		value = Array.isArray(value) ? value.slice() : [];
		var wrap = el('div', { class: 'openb-galleryctrl' });
		var grid = el('div', { class: 'openb-galleryctrl__grid' });
		function paint() {
			grid.innerHTML = '';
			value.forEach(function (img, i) {
				var cell = el('div', { class: 'openb-galleryctrl__cell' }, [
					el('img', { src: img.url || '' }),
					el('button', { class: 'openb-galleryctrl__rm', text: '×', title: 'Remove', onclick: function () { value.splice(i, 1); onChange(value.slice()); paint(); } })
				]);
				grid.appendChild(cell);
			});
		}
		paint();
		var add = el('button', { class: 'openb-btn openb-btn--block', text: 'Add Images', onclick: function () {
			openMedia(function (imgs) {
				(Array.isArray(imgs) ? imgs : [imgs]).forEach(function (im) { value.push({ id: im.id, url: im.url }); });
				onChange(value.slice()); paint();
			}, true);
		} });
		wrap.appendChild(grid);
		wrap.appendChild(add);
		return wrap;
	}

	function openMedia(cb, multiple) {
		var media = (window.wp && window.wp.media) ? window.wp.media : null;
		if (media) { return runMedia(media, cb, !!multiple); }
		var url = window.prompt('Image URL');
		if (url) cb(multiple ? [{ id: 0, url: url }] : { id: 0, url: url });
	}
	function runMedia(media, cb, multiple) {
		var frame = media({ title: 'Select Image', multiple: !!multiple, library: { type: 'image' } });
		frame.on('select', function () {
			var items = frame.state().get('selection').toJSON().map(function (att) {
				return { id: att.id, url: (att.sizes && att.sizes.large ? att.sizes.large.url : att.url) };
			});
			cb(multiple ? items : items[0]);
		});
		frame.open();
	}

	function iconControl(ctrl, value, onChange) {
		var wrap = el('div', { class: 'openb-iconpicker' });
		(ctrl.choices || Object.keys(BOOT.icons || {})).forEach(function (name) {
			var b = el('button', {
				class: 'openb-iconpick' + (value === name ? ' is-active' : ''), title: name,
				html: widgetIcon(name), onclick: function () { onChange(name); renderInspector(); }
			});
			wrap.appendChild(b);
		});
		return wrap;
	}

	function repeaterControl(ctrl, value, onChange) {
		value = Array.isArray(value) ? value : [];
		var wrap = el('div', { class: 'openb-repeater' });
		function redraw() {
			wrap.innerHTML = '';
			value.forEach(function (row, i) {
				var card = el('div', { class: 'openb-repeater__row' });
				card.appendChild(el('div', { class: 'openb-repeater__rowhead' }, [
					el('span', { text: (row.label || ('Item ' + (i + 1))) }),
					el('button', { class: 'openb-iconbtn', title: 'Remove', text: '×', onclick: function () { value.splice(i, 1); onChange(value); redraw(); } })
				]));
				Object.keys(ctrl.fields || {}).forEach(function (fk) {
					var sub = ctrl.fields[fk];
					card.appendChild(controlField(sub, row[fk], function (v) { row[fk] = v; onChange(value); }));
				});
				wrap.appendChild(card);
			});
			wrap.appendChild(el('button', { class: 'openb-btn openb-btn--block', text: '+ Add Item', onclick: function () {
				var blank = {};
				Object.keys(ctrl.fields || {}).forEach(function (fk) { blank[fk] = ctrl.fields[fk].default != null ? ctrl.fields[fk].default : ''; });
				value.push(blank); onChange(value); redraw();
			} }));
		}
		redraw();
		return wrap;
	}

	/* ----- Style tab: per-breakpoint visual controls ----- */
	function buildStyleControls(node) {
		var bp = state.device;
		node.settings.style = node.settings.style || {};
		node.settings.style[bp] = node.settings.style[bp] || {};
		var styleMap = node.settings.style[bp];

		function setProp(prop, val) {
			if (val === '' || val == null) delete styleMap[prop];
			else styleMap[prop] = val;
			markDirty(); rerender();
		}
		function get(prop) { return styleMap[prop] != null ? styleMap[prop] : ''; }

		var wrap = el('div', {});
		wrap.appendChild(el('div', { class: 'openb-bpnote', text: 'Editing: ' + bp + ' (use the device switcher to target tablet/mobile)' }));

		wrap.appendChild(styleGroup('Typography', [
			textRow('Text color', get('color'), function (v) { setProp('color', v); }, true),
			textRow('Font size', get('font-size'), function (v) { setProp('font-size', v); }),
			selectRow('Text align', get('text-align'), { '': 'Default', left: 'Left', center: 'Center', right: 'Right', justify: 'Justify' }, function (v) { setProp('text-align', v); }),
			textRow('Font weight', get('font-weight'), function (v) { setProp('font-weight', v); }),
			textRow('Line height', get('line-height'), function (v) { setProp('line-height', v); })
		]));

		wrap.appendChild(styleGroup('Background', [
			textRow('Background', get('background-color'), function (v) { setProp('background-color', v); }, true)
		]));

		wrap.appendChild(styleGroup('Spacing', [
			textRow('Padding', get('padding'), function (v) { setProp('padding', v); }),
			textRow('Margin', get('margin'), function (v) { setProp('margin', v); })
		]));

		wrap.appendChild(styleGroup('Size', [
			textRow('Width', get('width'), function (v) { setProp('width', v); }),
			textRow('Max width', get('max-width'), function (v) { setProp('max-width', v); }),
			textRow('Height', get('height'), function (v) { setProp('height', v); })
		]));

		wrap.appendChild(styleGroup('Border', [
			textRow('Border width', get('border-width'), function (v) { setProp('border-width', v); }),
			selectRow('Border style', get('border-style'), { '': 'None', solid: 'Solid', dashed: 'Dashed', dotted: 'Dotted' }, function (v) { setProp('border-style', v); }),
			textRow('Border color', get('border-color'), function (v) { setProp('border-color', v); }, true),
			textRow('Border radius', get('border-radius'), function (v) { setProp('border-radius', v); })
		]));

		return wrap;
	}
	function styleGroup(title, rows) {
		return el('div', { class: 'openb-stylegroup' }, [el('div', { class: 'openb-stylegroup__title', text: title })].concat(rows));
	}
	function textRow(label, value, onChange, isColor) {
		if (isColor) return controlField({ type: 'color', label: label }, value, onChange);
		var f = el('div', { class: 'openb-field openb-field--inline' });
		f.appendChild(el('label', { class: 'openb-field__label', text: label }));
		var i = el('input', { class: 'openb-input', type: 'text' });
		i.value = value || '';
		i.addEventListener('input', function () { onChange(i.value); });
		f.appendChild(i);
		return f;
	}
	function selectRow(label, value, choices, onChange) {
		return controlField({ type: 'select', label: label, choices: choices }, value, onChange);
	}

	function buildAdvancedControls(node) {
		node.settings.advanced = node.settings.advanced || { css_id: '', css_classes: '', custom_css: '' };
		var adv = node.settings.advanced;
		var wrap = el('div', {});
		wrap.appendChild(controlField({ type: 'text', label: 'CSS ID' }, adv.css_id, function (v) { adv.css_id = v; markDirty(); rerender(); }));
		wrap.appendChild(controlField({ type: 'text', label: 'CSS Classes' }, adv.css_classes, function (v) { adv.css_classes = v; markDirty(); rerender(); }));
		var cssField = controlField({ type: 'textarea', label: 'Custom CSS (use "selector" for this element)' }, adv.custom_css, function (v) { adv.custom_css = v; markDirty(); rerender(); });
		wrap.appendChild(cssField);
		wrap.appendChild(el('p', { class: 'openb-hint', text: 'Example: selector { box-shadow: 0 4px 20px rgba(0,0,0,.1) }' }));
		return wrap;
	}

	/* ----------------------------------------------------------------------- *
	 * Layers panel
	 * ----------------------------------------------------------------------- */
	function renderLayers() {
		var pane = document.getElementById('pane-layers');
		if (!pane || pane.style.display === 'none') return;
		pane.innerHTML = '';
		if (!state.tree.length) {
			pane.appendChild(el('p', { class: 'openb-hint', text: 'No elements yet.' }));
			return;
		}
		pane.appendChild(buildLayerList(state.tree, 0));
	}
	function buildLayerList(nodes, depth) {
		var ul = el('div', { class: 'openb-layers' });
		nodes.forEach(function (n) {
			var def = WIDGETS[n.type] || { title: n.type, icon: '' };
			var row = el('div', {
				class: 'openb-layer' + (n.id === state.selectedId ? ' is-selected' : ''),
				style: 'padding-left:' + (8 + depth * 14) + 'px',
				onclick: function (e) { e.stopPropagation(); selectNode(n.id); }
			}, [
				el('span', { class: 'openb-layer__icon', html: widgetIcon(def.icon) }),
				el('span', { class: 'openb-layer__name', text: def.title }),
				el('span', { class: 'openb-layer__acts' }, [
					el('button', { class: 'openb-iconbtn', title: 'Duplicate', text: '⎘', onclick: function (e) { e.stopPropagation(); duplicateNode(n.id); } }),
					el('button', { class: 'openb-iconbtn', title: 'Delete', text: '×', onclick: function (e) { e.stopPropagation(); deleteNode(n.id); } })
				])
			]);
			ul.appendChild(row);
			if (n.children && n.children.length) ul.appendChild(buildLayerList(n.children, depth + 1));
		});
		return ul;
	}

	/* ----------------------------------------------------------------------- *
	 * Globals panel
	 * ----------------------------------------------------------------------- */
	function renderGlobals() {
		var pane = document.getElementById('pane-globals');
		if (!pane) return;
		pane.innerHTML = '';
		pane.appendChild(el('p', { class: 'openb-hint', text: 'Brand settings apply across every page via CSS variables.' }));

		['colors', 'fonts', 'sizes'].forEach(function (group) {
			pane.appendChild(el('div', { class: 'openb-widgetcat', text: group.charAt(0).toUpperCase() + group.slice(1) }));
			(state.globals[group] || []).forEach(function (item) {
				var row = el('div', { class: 'openb-field openb-field--inline' });
				row.appendChild(el('label', { class: 'openb-field__label', text: item.name }));
				var i = el('input', { class: 'openb-input', type: 'text' });
				i.value = item.value;
				i.addEventListener('input', function () { item.value = i.value; });
				row.appendChild(i);
				pane.appendChild(row);
			});
		});

		pane.appendChild(el('button', { class: 'openb-btn openb-btn--primary openb-btn--block', text: 'Save Brand Settings', onclick: saveGlobals }));
	}
	function saveGlobals() {
		api('/global-styles', { styles: state.globals }).then(function (res) {
			if (res.ok) { toast('Brand settings saved'); renderCanvas(); }
			else toast('Could not save brand settings', true);
		});
	}

	/* ----------------------------------------------------------------------- *
	 * Node actions
	 * ----------------------------------------------------------------------- */
	function duplicateNode(id) {
		var hit = findNode(id);
		if (!hit) return;
		pushHistory();
		var copy = deepClone(hit.node);
		reId(copy);
		hit.list.splice(hit.index + 1, 0, copy);
		markDirty(); renderCanvas(); renderLayers(); selectNode(copy.id, true);
	}
	function reId(node) {
		node.id = uid();
		(node.children || []).forEach(reId);
	}
	function deleteNode(id) {
		pushHistory();
		removeNode(id);
		if (state.selectedId === id) state.selectedId = null;
		markDirty(); renderCanvas(); renderLayers(); renderInspector();
	}

	/* ----------------------------------------------------------------------- *
	 * History / device / save
	 * ----------------------------------------------------------------------- */
	function undo() {
		if (!state.history.length) return;
		state.future.push(deepClone(state.tree));
		state.tree = state.history.pop();
		state.selectedId = null;
		renderCanvas(); renderLayers(); renderInspector();
	}
	function redo() {
		if (!state.future.length) return;
		state.history.push(deepClone(state.tree));
		state.tree = state.future.pop();
		renderCanvas(); renderLayers(); renderInspector();
	}

	function setDevice(device) {
		state.device = device;
		Array.prototype.forEach.call(document.querySelectorAll('.openb-device'), function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-device') === device);
		});
		var area = document.querySelector('.openb-canvas-frame');
		if (area) {
			area.classList.remove('is-tablet', 'is-mobile');
			if (device === 'tablet') area.classList.add('is-tablet');
			if (device === 'mobile') area.classList.add('is-mobile');
		}
		if (inspectorTab === 'style') renderInspector();
	}

	function save() {
		var btn = document.getElementById('openb-save');
		if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
		api('/save', { post_id: BOOT.postId, tree: state.tree }).then(function (res) {
			if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
			if (res.ok) { state.dirty = false; toast('Saved'); }
			else toast('Save failed', true);
		}).catch(function () {
			if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
			toast('Save failed', true);
		});
	}

	function markDirty() { state.dirty = true; }

	window.addEventListener('beforeunload', function (e) {
		if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
	});
	document.addEventListener('keydown', function (e) {
		if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
		if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
	});

	/* ----------------------------------------------------------------------- *
	 * UI atoms
	 * ----------------------------------------------------------------------- */
	function svg(path) {
		return el('span', { class: 'openb-svg', html: '<svg viewBox="0 0 20 22" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"/></svg>' });
	}
	function widgetIcon(name) {
		var icons = {
			layout: '<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9h20"/>',
			columns: '<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M9 3v18M15 3v18"/>',
			column: '<rect x="7" y="3" width="10" height="18" rx="2"/>',
			heading: '<path d="M6 4v16M18 4v16M6 12h12"/>',
			text: '<path d="M4 6h16M4 12h16M4 18h10"/>',
			button: '<rect x="3" y="8" width="18" height="8" rx="4"/>',
			image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
			spacer: '<path d="M12 4v16M8 7l4-3 4 3M8 17l4 3 4-3"/>',
			divider: '<path d="M3 12h18"/>',
			star: '<path d="M12 2l3 6 6 1-4.5 4.5L18 20l-6-3-6 3 1.5-6.5L3 9l6-1z"/>',
			code: '<path d="M8 6l-5 6 5 6M16 6l5 6-5 6"/>',
			form: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/>',
			check: '<path d="M5 12l4 4L19 6"/>', arrow: '<path d="M4 12h16M14 6l6 6-6 6"/>',
			heart: '<path d="M12 21s-8-5-8-11a4 4 0 018-2 4 4 0 018 2c0 6-8 11-8 11z"/>',
			bolt: '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>',
			mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			phone: '<path d="M5 4h4l2 5-3 2a14 14 0 006 6l2-3 5 2v4a2 2 0 01-2 2A18 18 0 013 6a2 2 0 012-2z"/>',
			globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18 14 14 0 010-18z"/>',
			play: '<circle cx="12" cy="12" r="9"/><path d="M10 8l6 4-6 4z" fill="currentColor" stroke="none"/>',
			quote: '<path d="M7 7H4v6h3l-1 4h2l2-4V7zM18 7h-3v6h3l-1 4h2l2-4V7z"/>',
			chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			accordion: '<rect x="3" y="4" width="18" height="5" rx="1"/><rect x="3" y="12" width="18" height="8" rx="1"/><path d="M17 6h2M17 15h2"/>',
			tabs: '<path d="M3 8h6V4H3zM10 8h11V4H10zM3 20h18V10H3z"/>',
			share: '<circle cx="6" cy="12" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="17" cy="18" r="2.5"/><path d="M8 11l7-4M8 13l7 4"/>',
			map: '<path d="M9 4L3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14"/>'
		};
		var inner = icons[name] || icons.text;
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
	}
	function escapeAttr(s) { return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

	var toastTimer;
	function toast(msg, isError) {
		var t = document.getElementById('openb-toast');
		if (!t) { t = el('div', { id: 'openb-toast', class: 'openb-toast' }); document.body.appendChild(t); }
		t.textContent = msg;
		t.className = 'openb-toast is-visible' + (isError ? ' is-error' : '');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { t.className = 'openb-toast'; }, 2500);
	}

	/* ----------------------------------------------------------------------- *
	 * Boot
	 * ----------------------------------------------------------------------- */
	function init() {
		if (!BOOT.restUrl) {
			document.getElementById('openb-app').textContent = 'Open Builder failed to load configuration.';
			return;
		}
		// Load the WP media library into the parent window so the image control
		// can use it. It's already authenticated.
		buildShell();
		renderInspector();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
