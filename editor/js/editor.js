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

	/* Inline SVG glyphs for the visual style controls (16px, stroke=currentColor). */
	function _svg(inner) {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
	}
	var STYLE_ICON = {
		alignLeft: _svg('<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>'),
		alignCenter: _svg('<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>'),
		alignRight: _svg('<path d="M4 6h16M10 10h10M4 14h16M10 18h10"/>'),
		alignJustify: _svg('<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>'),
		dirRow: _svg('<rect x="3" y="6" width="5" height="12" rx="1"/><rect x="10" y="6" width="5" height="12" rx="1"/><rect x="17" y="6" width="4" height="12" rx="1"/>'),
		dirColumn: _svg('<rect x="6" y="3" width="12" height="5" rx="1"/><rect x="6" y="10" width="12" height="5" rx="1"/><rect x="6" y="17" width="12" height="4" rx="1"/>'),
		justStart: _svg('<path d="M3 4v16"/><rect x="6" y="8" width="5" height="8" rx="1"/><rect x="13" y="8" width="5" height="8" rx="1"/>'),
		justCenter: _svg('<path d="M12 4v16" stroke-dasharray="2 2"/><rect x="3" y="8" width="6" height="8" rx="1"/><rect x="15" y="8" width="6" height="8" rx="1"/>'),
		justEnd: _svg('<path d="M21 4v16"/><rect x="6" y="8" width="5" height="8" rx="1"/><rect x="13" y="8" width="5" height="8" rx="1"/>'),
		justBetween: _svg('<rect x="3" y="8" width="5" height="8" rx="1"/><rect x="16" y="8" width="5" height="8" rx="1"/>'),
		alignStart: _svg('<path d="M4 4h16"/><rect x="7" y="7" width="4" height="10" rx="1"/><rect x="13" y="7" width="4" height="7" rx="1"/>'),
		alignMiddle: _svg('<path d="M4 12h16" stroke-dasharray="2 2"/><rect x="7" y="7" width="4" height="10" rx="1"/><rect x="13" y="9" width="4" height="6" rx="1"/>'),
		alignBottom: _svg('<path d="M4 20h16"/><rect x="7" y="7" width="4" height="10" rx="1"/><rect x="13" y="13" width="4" height="4" rx="1"/>'),
		alignStretch: _svg('<path d="M4 4h16M4 20h16"/><rect x="7" y="7" width="4" height="10" rx="1"/><rect x="13" y="7" width="4" height="10" rx="1"/>'),
		link: _svg('<path d="M9 12h6M10 8H8a4 4 0 000 8h2M14 8h2a4 4 0 010 8h-2"/>'),
		borderSolid: _svg('<path d="M3 12h18"/>'),
		borderDashed: _svg('<path d="M3 12h4M10 12h4M17 12h4"/>'),
		borderDotted: _svg('<path d="M4 12h.01M8 12h.01M12 12h.01M16 12h.01M20 12h.01"/>'),
		bgColor: _svg('<rect x="3" y="3" width="18" height="18" rx="2"/>'),
		bgImage: _svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.6"/><path d="M21 16l-5-4L5 21"/>'),
		bgGradient: _svg('<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 17l18-9" opacity=".5"/>')
	};

	/* Widgets that support inline (double-click) text editing on the canvas.
	   sel = element inside the node wrapper to edit; field = content key;
	   html = whether to store innerHTML (rich) or plain text. */
	var INLINE_EDIT = {
		heading:  { sel: '.ob-heading__text', field: 'text', html: false },
		text:     { sel: '.ob-text__content', field: 'text', html: true },
		button:   { sel: '.ob-button', field: 'text', html: false },
		icon_box: { sel: '.ob-iconbox__title', field: 'title', html: false }
	};

	/* ----------------------------------------------------------------------- *
	 * State
	 * ----------------------------------------------------------------------- */
	var state = {
		tree: normalizeTree(Array.isArray(BOOT.tree) ? BOOT.tree : []),
		globals: BOOT.globals || { colors: [], fonts: [], sizes: [] },
		globalCss: typeof BOOT.globalCss === 'string' ? BOOT.globalCss : '',
		pageSettings: BOOT.pageSettings && typeof BOOT.pageSettings === 'object' ? BOOT.pageSettings : {},
		title: BOOT.postTitle || '',
		clipboard: loadClipboard(),
		selectedId: null,
		collapsed: {}, // layer ids collapsed in the tree
		device: 'desktop',
		showChrome: loadShowChrome(), // header/footer chrome visible in canvas
		dirty: false,
		history: [],
		future: [],
		drag: null // { mode:'new'|'move', type|id }
	};

	// PHP encodes empty maps as JSON arrays ([]). Setting a string key on a JS
	// Array (e.g. background['desktop'] = …) is dropped by JSON.stringify, so we
	// coerce settings.style / background / content / advanced to plain objects on
	// load. Without this, newly-set backgrounds silently never save.
	function normalizeTree(nodes) {
		(nodes || []).forEach(function (n) {
			n.settings = (n.settings && !Array.isArray(n.settings)) ? n.settings : {};
			['content', 'style', 'background', 'advanced', 'dynamic'].forEach(function (k) {
				if (Array.isArray(n.settings[k]) || n.settings[k] == null) n.settings[k] = {};
			});
			['style', 'background'].forEach(function (k) {
				Object.keys(n.settings[k]).forEach(function (bp) {
					if (Array.isArray(n.settings[k][bp])) n.settings[k][bp] = {};
				});
			});
			if (n.children && n.children.length) normalizeTree(n.children);
		});
		return nodes;
	}

	// Clipboard persists across pages via localStorage (best-effort).
	function loadClipboard() {
		try { return JSON.parse(localStorage.getItem('openb_clipboard') || 'null'); } catch (e) { return null; }
	}
	function saveClipboard(node) {
		state.clipboard = node;
		try { localStorage.setItem('openb_clipboard', JSON.stringify(node)); } catch (e) {}
	}
	// Whether the theme header/footer chrome is shown in the canvas (persisted).
	function loadShowChrome() {
		try { return localStorage.getItem('openb_show_chrome') !== '0'; } catch (e) { return true; }
	}

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
		var node = { id: uid(), type: type, settings: { content: content, style: defaultStyle(type), advanced: { css_id: '', css_classes: '', custom_css: '' } }, children: [] };
		// Seed nested defaults for the columns layout: two columns.
		if (type === 'columns') {
			node.children = [makeColumn(), makeColumn()];
		}
		return node;
	}
	// Standard starting sizes so new containers have presence (like Elementor/
	// Breakdance) instead of collapsing to 0px.
	function defaultStyle(type) {
		if (type === 'section') {
			return { desktop: { 'padding-top': '60px', 'padding-bottom': '60px', 'padding-left': '20px', 'padding-right': '20px', 'min-height': '100px' } };
		}
		if (type === 'columns') {
			return { desktop: { 'padding-top': '10px', 'padding-bottom': '10px', 'min-height': '80px' } };
		}
		if (type === 'column') {
			return { desktop: { 'padding-top': '10px', 'padding-bottom': '10px', 'padding-left': '10px', 'padding-right': '10px', 'min-height': '60px' } };
		}
		return {};
	}
	function makeColumn() {
		return { id: uid(), type: 'column', settings: { content: {}, style: defaultStyle('column'), advanced: { css_id: '', css_classes: '', custom_css: '' } }, children: [] };
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
		decorateRegions(doc);
		// Only decorate nodes inside the editable canvas. Nodes inside the theme
		// header/footer regions live outside #openb-canvas and are not selectable
		// in the page context — they are edited by opening their template.
		var nodes = doc.querySelectorAll('#openb-canvas [data-ob-id]');
		Array.prototype.forEach.call(nodes, function (n) {
			n.classList.add('openb-editable');
			if (n.getAttribute('data-ob-id') === state.selectedId) n.classList.add('openb-selected');
			n.addEventListener('click', onCanvasClick);
			n.addEventListener('dragover', onCanvasDragOver);
			n.addEventListener('drop', onCanvasDrop);
			n.addEventListener('dragleave', onCanvasDragLeave);
			n.addEventListener('contextmenu', onCanvasContextMenu);
			if (INLINE_EDIT[n.getAttribute('data-ob-type')]) {
				n.classList.add('openb-inline-editable');
				n.addEventListener('dblclick', onCanvasInlineEdit);
			}
		});
		// Root-level drop when empty or dropping at the end. The #openb-canvas
		// container persists across re-renders, so attach its listeners once.
		var container = doc.getElementById('openb-canvas');
		if (container && !container.dataset.obBound) {
			container.dataset.obBound = '1';
			container.addEventListener('dragover', onRootDragOver);
			container.addEventListener('drop', onRootDrop);
			// Links must never navigate inside the editor canvas.
			doc.addEventListener('click', function (e) {
				var a = e.target.closest && e.target.closest('a');
				if (a) e.preventDefault();
			}, true);
		}
	}

	/* ----------------------------------------------------------------------- *
	 * Theme regions: header/footer chrome shown for context. Bound once (they
	 * render server-side and persist across page re-renders).
	 * ----------------------------------------------------------------------- */
	function decorateRegions(doc) {
		applyChromeVisibility(doc);
		if (doc.body.dataset.obRegionsBound) return;
		doc.body.dataset.obRegionsBound = '1';

		// "Edit Header/Footer": open that template in the builder.
		Array.prototype.forEach.call(doc.querySelectorAll('[data-ob-edit-region]'), function (btn) {
			var region = btn.closest('.openb-region');
			var tplId = region && region.getAttribute('data-ob-template');
			btn.addEventListener('click', function (e) {
				e.preventDefault(); e.stopPropagation();
				if (tplId) goToEditor(tplId);
			});
		});
		// Clicking anywhere in a region (not just the badge) also edits it.
		Array.prototype.forEach.call(doc.querySelectorAll('.openb-region'), function (region) {
			var tplId = region.getAttribute('data-ob-template');
			region.addEventListener('click', function (e) {
				e.preventDefault(); e.stopPropagation();
				if (tplId) goToEditor(tplId);
			});
		});
		// "+ Add Header/Footer": create the template, then open it.
		Array.prototype.forEach.call(doc.querySelectorAll('[data-ob-add]'), function (add) {
			add.addEventListener('click', function (e) {
				e.preventDefault(); e.stopPropagation();
				createTemplate(add.getAttribute('data-ob-add'));
			});
		});
	}

	function applyChromeVisibility(doc) {
		doc = doc || canvasDoc();
		if (doc && doc.body) doc.body.classList.toggle('openb-hide-chrome', !state.showChrome);
	}

	// Navigate the editor to another post/template, warning about unsaved work.
	function goToEditor(targetId) {
		if (state.dirty && !window.confirm('You have unsaved changes. Leave and discard them?')) return;
		state.dirty = false;
		var u = new URL(BOOT.homeUrl || window.location.origin, window.location.origin);
		u.searchParams.set(BOOT.editQv || 'openb_editor', targetId);
		window.location.href = u.toString();
	}

	function createTemplate(type) {
		if (!BOOT.canManage) { toast('You need the manage-options capability to add templates.', true); return; }
		toast('Creating ' + type + '…');
		api('/create-template', { type: type }).then(function (res) {
			if (res.ok && res.data && res.data.edit_url) {
				state.dirty = false;
				window.location.href = res.data.edit_url;
			} else {
				toast((res.data && res.data.message) || 'Could not create template', true);
			}
		}).catch(function () { toast('Could not create template', true); });
	}

	function toggleChrome() {
		state.showChrome = !state.showChrome;
		try { localStorage.setItem('openb_show_chrome', state.showChrome ? '1' : '0'); } catch (e) {}
		applyChromeVisibility();
		var btn = document.getElementById('openb-chrome-toggle');
		if (btn) btn.classList.toggle('is-active', state.showChrome);
	}

	function onCanvasClick(e) {
		// While inline-editing this node, let clicks position the caret normally.
		if (inlineEditing && this.getAttribute('data-ob-id') === inlineEditing.id) return;
		e.stopPropagation();
		e.preventDefault();
		var id = this.getAttribute('data-ob-id');
		selectNode(id);
	}

	function onCanvasContextMenu(e) {
		e.preventDefault();
		e.stopPropagation();
		var id = this.getAttribute('data-ob-id');
		selectNode(id);
		// Translate iframe-relative coords into the parent document.
		var frame = document.getElementById('openb-canvas-frame');
		var rect = frame ? frame.getBoundingClientRect() : { left: 0, top: 0 };
		showContextMenu(rect.left + e.clientX, rect.top + e.clientY, nodeMenuItems(id));
	}

	var inlineEditing = null; // { id, target, cfg }
	function onCanvasInlineEdit(e) {
		e.preventDefault();
		e.stopPropagation();
		var id = this.getAttribute('data-ob-id');
		var type = this.getAttribute('data-ob-type');
		var cfg = INLINE_EDIT[type];
		if (!cfg) return;
		var target = this.querySelector(cfg.sel) || this;
		startInlineEdit(id, target, cfg);
	}

	function startInlineEdit(id, target, cfg) {
		finishInlineEdit(); // commit any previous
		selectNode(id);
		target.setAttribute('contenteditable', 'true');
		target.classList.add('openb-editing');
		target.focus();
		// Select all text for quick replace.
		var doc = canvasDoc();
		try {
			var range = doc.createRange();
			range.selectNodeContents(target);
			var sel = doc.defaultView.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
		} catch (err) {}
		inlineEditing = { id: id, target: target, cfg: cfg };

		target.addEventListener('blur', finishInlineEdit);
		target.addEventListener('keydown', inlineKeydown);
	}

	function inlineKeydown(e) {
		// Enter commits for single-line (plain text) fields; Shift+Enter allows
		// newlines in rich text. Esc cancels.
		if (e.key === 'Enter' && inlineEditing && !inlineEditing.cfg.html && !e.shiftKey) {
			e.preventDefault();
			finishInlineEdit();
		} else if (e.key === 'Escape') {
			e.preventDefault();
			if (inlineEditing) inlineEditing.cancelled = true;
			finishInlineEdit();
		}
	}

	function finishInlineEdit() {
		if (!inlineEditing) return;
		var ie = inlineEditing;
		inlineEditing = null;
		var target = ie.target;
		target.removeEventListener('blur', finishInlineEdit);
		target.removeEventListener('keydown', inlineKeydown);
		target.removeAttribute('contenteditable');
		target.classList.remove('openb-editing');

		if (!ie.cancelled) {
			var hit = findNode(ie.id);
			if (hit) {
				var value = ie.cfg.html ? target.innerHTML : (target.innerText || '').replace(/\s+$/, '');
				hit.node.settings.content = hit.node.settings.content || {};
				if (hit.node.settings.content[ie.cfg.field] !== value) {
					hit.node.settings.content[ie.cfg.field] = value;
					markDirty();
					renderInspector();
				}
			}
		}
		// Re-render so the canvas reflects the committed (and sanitized) value.
		renderCanvas();
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
			var sel = doc.querySelector('#openb-canvas [data-ob-id="' + id + '"]');
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

		// Toggle the theme header/footer chrome in the canvas. Hidden when editing
		// a template itself (there's no chrome to show around it).
		var chromeBtn = BOOT.isTemplate ? null : el('button', {
			class: 'openb-btn' + (state.showChrome ? ' is-active' : ''),
			id: 'openb-chrome-toggle', title: 'Show/hide theme header & footer', onclick: toggleChrome
		}, [svg('M2 4h16v4H2zM2 14h16v4H2z'), ' Chrome']);

		return el('div', { class: 'openb-topbar' }, [
			el('div', { class: 'openb-topbar__left' }, [
				el('span', { class: 'openb-logo', text: 'Open Builder' }),
				el('span', { class: 'openb-title', text: BOOT.postTitle || '' })
			]),
			el('div', { class: 'openb-topbar__center' }, deviceBtns),
			el('div', { class: 'openb-topbar__right' }, [
				chromeBtn,
				el('button', { class: 'openb-btn', title: 'Undo', onclick: undo }, [svg('M7 7L3 11l4 4M3 11h10a4 4 0 010 8h-2')]),
				el('button', { class: 'openb-btn', title: 'Redo', onclick: redo }, [svg('M13 7l4 4-4 4M17 11H7a4 4 0 000 8h2')]),
				el('button', { class: 'openb-btn', title: 'Page Settings', onclick: openPageSettings }, [svg('M10 3l1.5 2.6 3-.5-.5 3L16.5 11l-2.5 1.4.5 3-3-.5L10 17l-1.5-2.6-3 .5.5-3L3.5 9l2.5-1.4-.5-3 3 .5z'), ' Page']),
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

	// Rebuild the inspector but keep the scroll position (used when a control
	// change needs to re-render the panel, e.g. switching background Type).
	function refreshInspectorKeepScroll() {
		var body = document.querySelector('.openb-inspector__body');
		var top = body ? body.scrollTop : 0;
		renderInspector();
		var nb = document.querySelector('.openb-inspector__body');
		if (nb) nb.scrollTop = top;
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
			if (isBindable(ctrl.type)) {
				wrap.appendChild(dynamicField(node, key, ctrl, value));
			} else {
				wrap.appendChild(controlField(ctrl, value, function (v) {
					node.settings.content[key] = v;
					markDirty(); rerender();
				}));
			}
		});
		return wrap;
	}

	/* ----------------------------------------------------------------------- *
	 * Dynamic data binding: bind a text/url/image field to a post/site source.
	 * ----------------------------------------------------------------------- */
	var DYNAMIC_SOURCES = Array.isArray(BOOT.dynamicSources) ? BOOT.dynamicSources : [];
	function isBindable(type) {
		return DYNAMIC_SOURCES.length && ['text', 'url', 'textarea', 'html', 'richtext', 'image'].indexOf(type) !== -1;
	}
	function sourcesForKind(isImage) {
		return DYNAMIC_SOURCES.filter(function (s) { return isImage ? s.image : s.text; });
	}
	function dynamicField(node, key, ctrl, value) {
		node.settings.dynamic = (node.settings.dynamic && !Array.isArray(node.settings.dynamic)) ? node.settings.dynamic : {};
		var isImage = ctrl.type === 'image';
		var dyn = node.settings.dynamic[key];
		var wrap = el('div', { class: 'openb-dynfield' });

		// Header: label + a toggle that switches this field to dynamic.
		var toggle = el('button', {
			class: 'openb-dynbtn' + (dyn ? ' is-active' : ''),
			title: dyn ? 'Using dynamic data — click to use a static value' : 'Bind to dynamic data',
			type: 'button',
			onclick: function () {
				if (node.settings.dynamic[key]) {
					delete node.settings.dynamic[key];
				} else {
					var opts = sourcesForKind(isImage);
					node.settings.dynamic[key] = { source: (opts[0] && opts[0].value) || 'post_title', key: '', fallback: '' };
				}
				markDirty(); refreshInspectorKeepScroll(); rerender();
			}
		}, [svgRaw('<path d="M4 7c0 1.7 3.6 3 8 3s8-1.3 8-3-3.6-3-8-3-8 1.3-8 3z"/><path d="M4 7v10c0 1.7 3.6 3 8 3s8-1.3 8-3V7"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>'), ' Dynamic']);

		if (ctrl.label) {
			wrap.appendChild(el('div', { class: 'openb-dynfield__head' }, [
				el('label', { class: 'openb-field__label', text: ctrl.label }),
				toggle
			]));
		} else {
			wrap.appendChild(el('div', { class: 'openb-dynfield__head' }, [toggle]));
		}

		if (!dyn) {
			// Static: the normal control (no duplicate label).
			wrap.appendChild(controlField({ type: ctrl.type, choices: ctrl.choices, hint: ctrl.hint }, value, function (v) {
				node.settings.content[key] = v; markDirty(); rerender();
			}));
			return wrap;
		}

		// Dynamic: source select (+ key when needed) + fallback.
		var opts = sourcesForKind(isImage);
		var choices = {};
		opts.forEach(function (s) { choices[s.value] = s.label; });
		wrap.appendChild(controlField({ type: 'select', label: 'Source', choices: choices }, dyn.source, function (v) {
			dyn.source = v; markDirty(); refreshInspectorKeepScroll(); rerender();
		}));
		var sel = opts.filter(function (s) { return s.value === dyn.source; })[0];
		if (sel && sel.needsKey) {
			wrap.appendChild(controlField({ type: 'text', label: 'Field / meta key' }, dyn.key || '', function (v) {
				dyn.key = v; markDirty(); rerender();
			}));
		}
		if (!isImage) {
			wrap.appendChild(controlField({ type: 'text', label: 'Fallback (if empty)' }, dyn.fallback || '', function (v) {
				dyn.fallback = v; markDirty(); rerender();
			}));
		}
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
		if (ctrl.hint) field.appendChild(el('p', { class: 'openb-hint', text: ctrl.hint }));
		return field;
	}

	function colorControl(value, onChange) {
		var wrap = el('div', { class: 'openb-colorctrl' });
		var swatchVal = value || '';
		var text = el('input', { class: 'openb-input', type: 'text', placeholder: '#000000 or var(--ob-color-primary)' });
		text.value = swatchVal;
		var hint = el('p', { class: 'openb-contrast-hint' });
		function update() { onChange(text.value); paintContrast(hint, text.value); }
		text.addEventListener('input', update);
		// Brand color swatches.
		var swatches = el('div', { class: 'openb-swatches' });
		(state.globals.colors || []).forEach(function (c) {
			swatches.appendChild(el('button', {
				class: 'openb-swatch', title: c.name, style: 'background:' + c.value,
				onclick: function () { text.value = 'var(--ob-color-' + c.id + ')'; update(); }
			}));
		});
		wrap.appendChild(text);
		wrap.appendChild(swatches);
		wrap.appendChild(hint);
		paintContrast(hint, swatchVal);
		return wrap;
	}

	/* Color-contrast hint (WCAG). Resolves #hex and brand var() values, then
	   shows the contrast ratio against white and black so authors can sanity-check
	   legibility. AA for normal text is 4.5:1. */
	function resolveColorHex(value) {
		value = (value || '').trim();
		if (!value) return null;
		var m = value.match(/^var\(\s*--ob-color-([a-z0-9_-]+)\s*\)$/i);
		if (m) {
			var found = (state.globals.colors || []).filter(function (c) { return c.id === m[1]; })[0];
			value = found ? found.value : '';
		}
		return parseHex(value);
	}
	function parseHex(v) {
		v = (v || '').trim();
		var m = v.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
		if (!m) return null;
		var h = m[1];
		if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
		return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
	}
	function relLum(rgb) {
		var a = rgb.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
		return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
	}
	function contrastRatio(rgb1, rgb2) {
		var l1 = relLum(rgb1), l2 = relLum(rgb2);
		var hi = Math.max(l1, l2), lo = Math.min(l1, l2);
		return (hi + 0.05) / (lo + 0.05);
	}
	function paintContrast(node, value) {
		var rgb = resolveColorHex(value);
		if (!rgb) { node.textContent = ''; return; }
		var white = contrastRatio(rgb, [255, 255, 255]);
		var black = contrastRatio(rgb, [0, 0, 0]);
		function tag(label, r) { return label + ' ' + r.toFixed(1) + ':1 ' + (r >= 4.5 ? '✓' : '✗'); }
		node.textContent = 'Contrast — ' + tag('on white', white) + ' · ' + tag('on black', black);
		node.className = 'openb-contrast-hint' + ((white >= 4.5 || black >= 4.5) ? '' : ' is-fail');
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
				html: widgetIcon(name), onclick: function () { onChange(name); refreshInspectorKeepScroll(); }
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
		// Guard against PHP's empty-array-as-[] encoding (see normalizeTree).
		if (Array.isArray(node.settings.style) || !node.settings.style) node.settings.style = {};
		if (Array.isArray(node.settings.background) || !node.settings.background) node.settings.background = {};
		if (Array.isArray(node.settings.style[bp]) || !node.settings.style[bp]) node.settings.style[bp] = {};
		if (Array.isArray(node.settings.background[bp]) || !node.settings.background[bp]) node.settings.background[bp] = { type: 'none' };
		var styleMap = node.settings.style[bp];

		function setProp(prop, val) {
			if (val === '' || val == null) delete styleMap[prop];
			else styleMap[prop] = val;
			markDirty(); rerender();
		}
		function get(prop) { return styleMap[prop] != null ? styleMap[prop] : ''; }

		var isContainer = WIDGETS[node.type] && WIDGETS[node.type].isContainer;
		var wrap = el('div', {});
		wrap.appendChild(el('div', { class: 'openb-bpnote', text: 'Editing ' + bp + ' — switch device to target tablet/mobile' }));

		/* Alignment (horizontal text align for everything; flex both-axis for containers) */
		var alignRows = [
			iconGroupRow('Text Align', get('text-align'), [
				{ val: 'left', title: 'Left', svg: STYLE_ICON.alignLeft },
				{ val: 'center', title: 'Center', svg: STYLE_ICON.alignCenter },
				{ val: 'right', title: 'Right', svg: STYLE_ICON.alignRight },
				{ val: 'justify', title: 'Justify', svg: STYLE_ICON.alignJustify }
			], function (v) { setProp('text-align', v); })
		];
		if (isContainer) {
			alignRows.push(iconGroupRow('Direction', get('flex-direction'), [
				{ val: 'row', title: 'Row (horizontal)', svg: STYLE_ICON.dirRow },
				{ val: 'column', title: 'Column (vertical)', svg: STYLE_ICON.dirColumn }
			], function (v) { if (v) setProp('display', 'flex'); setProp('flex-direction', v); }));
			alignRows.push(iconGroupRow('Justify (main axis)', get('justify-content'), [
				{ val: 'flex-start', title: 'Start', svg: STYLE_ICON.justStart },
				{ val: 'center', title: 'Center', svg: STYLE_ICON.justCenter },
				{ val: 'flex-end', title: 'End', svg: STYLE_ICON.justEnd },
				{ val: 'space-between', title: 'Space between', svg: STYLE_ICON.justBetween }
			], function (v) { if (v) setProp('display', 'flex'); setProp('justify-content', v); }));
			alignRows.push(iconGroupRow('Align (cross axis)', get('align-items'), [
				{ val: 'flex-start', title: 'Start', svg: STYLE_ICON.alignStart },
				{ val: 'center', title: 'Center', svg: STYLE_ICON.alignMiddle },
				{ val: 'flex-end', title: 'End', svg: STYLE_ICON.alignBottom },
				{ val: 'stretch', title: 'Stretch', svg: STYLE_ICON.alignStretch }
			], function (v) { if (v) setProp('display', 'flex'); setProp('align-items', v); }));
			alignRows.push(unitSliderRow('Gap', get('gap'), { min: 0, max: 100, units: ['px', 'em', 'rem'] }, function (v) { setProp('gap', v); }));
		}
		wrap.appendChild(styleGroup('Alignment', alignRows));

		/* Typography */
		wrap.appendChild(styleGroup('Typography', [
			colorPopoverRow('Text Color', get('color'), function (v) { setProp('color', v); }),
			unitSliderRow('Font Size', get('font-size'), { min: 8, max: 120, units: ['px', 'em', 'rem', '%'] }, function (v) { setProp('font-size', v); }),
			iconGroupRow('Weight', get('font-weight'), [
				{ val: '300', title: 'Light', text: 'L' }, { val: '400', title: 'Normal', text: 'N' },
				{ val: '600', title: 'Semibold', text: 'S' }, { val: '700', title: 'Bold', text: 'B' }
			], function (v) { setProp('font-weight', v); }),
			unitSliderRow('Line Height', get('line-height'), { min: 0.8, max: 3, step: 0.05, units: ['', 'px', 'em'] }, function (v) { setProp('line-height', v); }),
			unitSliderRow('Letter Spacing', get('letter-spacing'), { min: -5, max: 20, step: 0.5, units: ['px', 'em'] }, function (v) { setProp('letter-spacing', v); }),
			iconGroupRow('Transform', get('text-transform'), [
				{ val: 'none', title: 'None', text: 'Aa' }, { val: 'uppercase', title: 'Uppercase', text: 'AA' },
				{ val: 'lowercase', title: 'Lowercase', text: 'aa' }, { val: 'capitalize', title: 'Capitalize', text: 'Ab' }
			], function (v) { setProp('text-transform', v); })
		]));

		/* Background */
		wrap.appendChild(styleGroup('Background', [backgroundBuilder(node, bp)]));

		/* Spacing — linked box controls */
		wrap.appendChild(styleGroup('Spacing', [
			linkedBoxRow('Padding', styleMap, 'padding', setProp),
			linkedBoxRow('Margin', styleMap, 'margin', setProp)
		]));

		/* Size */
		wrap.appendChild(styleGroup('Size', [
			unitSliderRow('Width', get('width'), { min: 0, max: 1600, units: ['px', '%', 'vw', 'auto'] }, function (v) { setProp('width', v); }),
			unitSliderRow('Max Width', get('max-width'), { min: 0, max: 1600, units: ['px', '%', 'vw'] }, function (v) { setProp('max-width', v); }),
			unitSliderRow('Height', get('height'), { min: 0, max: 1200, units: ['px', '%', 'vh', 'auto'] }, function (v) { setProp('height', v); }),
			unitSliderRow('Min Height', get('min-height'), { min: 0, max: 1200, units: ['px', 'vh'] }, function (v) { setProp('min-height', v); })
		]));

		/* Border + radius */
		wrap.appendChild(styleGroup('Border', [borderBuilder(styleMap, setProp, get)]));

		/* Box shadow */
		wrap.appendChild(styleGroup('Shadow', [shadowBuilder(get('box-shadow'), function (v) { setProp('box-shadow', v); })]));

		/* Effects */
		wrap.appendChild(styleGroup('Effects', [
			unitSliderRow('Opacity', get('opacity'), { min: 0, max: 1, step: 0.05, units: [''], plain: true }, function (v) { setProp('opacity', v); })
		]));

		return wrap;
	}

	function styleGroup(title, rows) {
		return el('div', { class: 'openb-stylegroup' }, [el('div', { class: 'openb-stylegroup__title', text: title })].concat(rows));
	}

	/* ---- Visual control primitives ---- */

	function iconGroupRow(label, value, options, onChange) {
		var row = el('div', { class: 'openb-field openb-field--row' });
		row.appendChild(el('label', { class: 'openb-field__label', text: label }));
		var group = el('div', { class: 'openb-icongroup' });
		options.forEach(function (opt) {
			var btn = el('button', {
				class: 'openb-icongroup__btn' + (String(value) === String(opt.val) ? ' is-active' : ''),
				title: opt.title || opt.val,
				html: opt.svg || '',
				onclick: function () {
					var next = (String(value) === String(opt.val)) ? '' : opt.val; // toggle off
					value = next;
					Array.prototype.forEach.call(group.children, function (c) { c.classList.remove('is-active'); });
					if (next !== '') btn.classList.add('is-active');
					onChange(next);
				}
			});
			if (opt.text) btn.textContent = opt.text;
			group.appendChild(btn);
		});
		row.appendChild(group);
		return row;
	}

	function parseUnit(value, fallbackUnit) {
		value = (value == null) ? '' : String(value).trim();
		if (value === '') return { num: '', unit: fallbackUnit || 'px' };
		if (value === 'auto') return { num: '', unit: 'auto' };
		var m = value.match(/^(-?\d*\.?\d+)\s*([a-z%]*)$/i);
		if (!m) return { num: value, unit: fallbackUnit || 'px' };
		return { num: m[1], unit: m[2] || (fallbackUnit || '') };
	}

	function unitSliderRow(label, value, opts, onChange) {
		opts = opts || {};
		var units = opts.units || ['px'];
		var parsed = parseUnit(value, units[0]);
		var row = el('div', { class: 'openb-field' });
		row.appendChild(el('label', { class: 'openb-field__label', text: label }));
		var line = el('div', { class: 'openb-unitline' });

		var slider = el('input', { type: 'range', class: 'openb-slider', min: (opts.min != null ? opts.min : 0), max: (opts.max != null ? opts.max : 100), step: (opts.step || 1) });
		var num = el('input', { type: 'number', class: 'openb-input openb-input--num', step: (opts.step || 1) });
		var unitSel = null;

		function unit() { return unitSel ? unitSel.value : (parsed.unit || ''); }
		function emit() {
			var n = num.value;
			if (n === '' && unit() !== 'auto') { onChange(''); return; }
			if (unit() === 'auto') { onChange('auto'); return; }
			onChange(opts.plain ? String(n) : (n + unit()));
		}
		slider.value = (parsed.num === '' ? (opts.min || 0) : parsed.num);
		num.value = parsed.num;
		slider.addEventListener('input', function () { num.value = slider.value; emit(); });
		num.addEventListener('input', function () { if (num.value !== '') slider.value = num.value; emit(); });
		line.appendChild(slider);
		line.appendChild(num);

		if (!opts.plain && units.length > 1) {
			unitSel = el('select', { class: 'openb-unitsel' });
			units.forEach(function (u) {
				var o = el('option', { value: u, text: u === '' ? '—' : u });
				if (u === parsed.unit) o.selected = true;
				unitSel.appendChild(o);
			});
			unitSel.addEventListener('change', function () {
				num.disabled = (unitSel.value === 'auto');
				emit();
			});
			num.disabled = (parsed.unit === 'auto');
			line.appendChild(unitSel);
		}
		row.appendChild(line);
		return row;
	}

	function linkedBoxRow(label, styleMap, prefix, setProp) {
		var sides = ['top', 'right', 'bottom', 'left'];
		var cur = sides.map(function (s) { return parseUnit(styleMap[prefix + '-' + s] || '', 'px'); });
		var unit = (cur.find(function (c) { return c.unit && c.unit !== 'px'; }) || cur[0]).unit || 'px';
		var linked = (cur[0].num !== '' && cur.every(function (c) { return c.num === cur[0].num; }));

		var row = el('div', { class: 'openb-field' });
		var head = el('div', { class: 'openb-boxhead' }, [
			el('label', { class: 'openb-field__label', text: label }),
			el('button', { class: 'openb-link' + (linked ? ' is-on' : ''), title: 'Link sides', html: STYLE_ICON.link })
		]);
		var linkBtn = head.children[1];
		row.appendChild(head);

		var grid = el('div', { class: 'openb-boxgrid' });
		var inputs = {};
		sides.forEach(function (s, i) {
			var input = el('input', { type: 'number', class: 'openb-input openb-input--box', placeholder: s[0].toUpperCase() });
			input.value = cur[i].num;
			input.title = s;
			input.addEventListener('input', function () {
				if (linked) {
					sides.forEach(function (s2) { inputs[s2].value = input.value; setProp(prefix + '-' + s2, input.value === '' ? '' : input.value + unitSel.value); });
				} else {
					setProp(prefix + '-' + s, input.value === '' ? '' : input.value + unitSel.value);
				}
			});
			inputs[s] = input;
			grid.appendChild(input);
		});
		var unitSel = el('select', { class: 'openb-unitsel openb-unitsel--box' });
		['px', '%', 'em', 'rem'].forEach(function (u) { var o = el('option', { value: u, text: u }); if (u === unit) o.selected = true; unitSel.appendChild(o); });
		unitSel.addEventListener('change', function () {
			sides.forEach(function (s) { if (inputs[s].value !== '') setProp(prefix + '-' + s, inputs[s].value + unitSel.value); });
		});
		grid.appendChild(unitSel);
		row.appendChild(grid);

		linkBtn.addEventListener('click', function () {
			linked = !linked;
			linkBtn.classList.toggle('is-on', linked);
			if (linked) {
				var v = inputs.top.value;
				sides.forEach(function (s) { inputs[s].value = v; setProp(prefix + '-' + s, v === '' ? '' : v + unitSel.value); });
			}
		});
		return row;
	}

	function colorPopoverRow(label, value, onChange) {
		// Stacked (label above the control) so the inline panel can use full width
		// and is never clipped by the inspector's scroll container.
		var row = el('div', { class: 'openb-field' });
		if (label) row.appendChild(el('label', { class: 'openb-field__label', text: label }));
		row.appendChild(colorPopover(value, onChange));
		return row;
	}

	function colorPopover(value, onChange) {
		var wrap = el('div', { class: 'openb-colorpop' });

		// Full-width swatch bar showing the current value; click to expand.
		var swatch = el('button', { class: 'openb-colorpop__swatch', title: 'Choose color' });
		var label = el('span', { class: 'openb-colorpop__value' });
		function paint() {
			swatch.style.background = value || '';
			swatch.classList.toggle('is-empty', !value);
			label.textContent = value || 'No color';
		}
		swatch.appendChild(label);

		// Panel expands INLINE (extends scroll height) instead of overlaying.
		var pop = el('div', { class: 'openb-colorpop__panel', style: 'display:none' });
		var native = el('input', { type: 'color', class: 'openb-colorpop__native' });
		native.value = /^#([0-9a-f]{6})$/i.test(value || '') ? value : '#000000';
		native.addEventListener('input', function () { value = native.value; paint(); onChange(value); });
		var text = el('input', { class: 'openb-input', type: 'text', placeholder: '#hex / rgba / var(--ob-color-…)' });
		text.value = value || '';
		text.addEventListener('input', function () { value = text.value; paint(); onChange(value); });
		var swatches = el('div', { class: 'openb-swatches' });
		(state.globals.colors || []).forEach(function (c) {
			swatches.appendChild(el('button', { class: 'openb-swatch', title: c.name, style: 'background:' + c.value, onclick: function () { value = 'var(--ob-color-' + c.id + ')'; text.value = value; if (/^#([0-9a-f]{6})$/i.test(c.value)) native.value = c.value; paint(); onChange(value); } }));
		});
		var actions = el('div', { class: 'openb-colorpop__actions' }, [
			el('button', { class: 'openb-btn', text: 'Clear', onclick: function () { value = ''; text.value = ''; paint(); onChange(''); } }),
			el('button', { class: 'openb-btn openb-btn--primary', text: 'Done', onclick: function () { pop.style.display = 'none'; } })
		]);
		pop.appendChild(native); pop.appendChild(text); pop.appendChild(swatches); pop.appendChild(actions);

		swatch.addEventListener('click', function () { pop.style.display = pop.style.display === 'none' ? 'flex' : 'none'; });
		paint();
		wrap.appendChild(swatch); wrap.appendChild(pop);
		return wrap;
	}

	function borderBuilder(styleMap, setProp, get) {
		var wrap = el('div', {});
		wrap.appendChild(iconGroupRow('Style', get('border-style'), [
			{ val: 'none', title: 'None', text: '∅' },
			{ val: 'solid', title: 'Solid', svg: STYLE_ICON.borderSolid },
			{ val: 'dashed', title: 'Dashed', svg: STYLE_ICON.borderDashed },
			{ val: 'dotted', title: 'Dotted', svg: STYLE_ICON.borderDotted }
		], function (v) { setProp('border-style', v); }));
		wrap.appendChild(unitSliderRow('Width', get('border-width'), { min: 0, max: 30, units: ['px'] }, function (v) { setProp('border-width', v); }));
		wrap.appendChild(colorPopoverRow('Color', get('border-color'), function (v) { setProp('border-color', v); }));
		wrap.appendChild(unitSliderRow('Radius', get('border-radius'), { min: 0, max: 200, units: ['px', '%'] }, function (v) { setProp('border-radius', v); }));
		return wrap;
	}

	function shadowBuilder(value, onChange) {
		// Parse "x y blur spread color [inset]"
		var inset = /inset/.test(value || '');
		var rest = (value || '').replace('inset', '').trim();
		var colorMatch = rest.match(/(#[0-9a-f]+|rgba?\([^)]+\)|var\([^)]+\))/i);
		var color = colorMatch ? colorMatch[0] : 'rgba(0,0,0,0.15)';
		var nums = rest.replace(color, '').trim().split(/\s+/).map(function (n) { return parseInt(n, 10) || 0; });
		var st = { x: nums[0] || 0, y: nums[1] || 0, blur: nums[2] || 0, spread: nums[3] || 0, color: color, inset: inset };

		function emit() {
			if (st.x === 0 && st.y === 0 && st.blur === 0 && st.spread === 0) { onChange(''); return; }
			onChange((st.inset ? 'inset ' : '') + st.x + 'px ' + st.y + 'px ' + st.blur + 'px ' + st.spread + 'px ' + st.color);
		}
		var wrap = el('div', {});
		function numRow(lbl, key, min, max) {
			return unitSliderRow(lbl, st[key] + 'px', { min: min, max: max, units: ['px'] }, function (v) { st[key] = parseInt(v, 10) || 0; emit(); });
		}
		wrap.appendChild(numRow('Offset X', 'x', -50, 50));
		wrap.appendChild(numRow('Offset Y', 'y', -50, 50));
		wrap.appendChild(numRow('Blur', 'blur', 0, 100));
		wrap.appendChild(numRow('Spread', 'spread', -50, 50));
		wrap.appendChild(colorPopoverRow('Color', st.color, function (v) { st.color = v || 'rgba(0,0,0,0.15)'; emit(); }));
		var insetRow = el('div', { class: 'openb-field openb-field--row' }, [el('label', { class: 'openb-field__label', text: 'Inset' })]);
		var sw = el('label', { class: 'openb-switch' });
		var cb = el('input', { type: 'checkbox' }); cb.checked = st.inset;
		cb.addEventListener('change', function () { st.inset = cb.checked; emit(); });
		sw.appendChild(cb); sw.appendChild(el('span', { class: 'openb-switch__slider' }));
		insetRow.appendChild(sw);
		wrap.appendChild(insetRow);
		return wrap;
	}

	function backgroundBuilder(node, bp) {
		var bg = node.settings.background[bp];
		function commit() { markDirty(); rerender(); }
		var wrap = el('div', {});
		wrap.appendChild(iconGroupRow('Type', bg.type || 'none', [
			{ val: 'none', title: 'None', text: '∅' },
			{ val: 'color', title: 'Color', svg: STYLE_ICON.bgColor },
			{ val: 'image', title: 'Image', svg: STYLE_ICON.bgImage },
			{ val: 'gradient', title: 'Gradient', svg: STYLE_ICON.bgGradient }
		], function (v) { bg.type = v || 'none'; commit(); refreshInspectorKeepScroll(); }));

		if (bg.type === 'color') {
			wrap.appendChild(colorPopoverRow('Color', bg.color || '', function (v) { bg.color = v; commit(); }));
		} else if (bg.type === 'image') {
			var img = bg.image || { id: 0, url: '' };
			var prev = el('div', { class: 'openb-imagectrl__preview' });
			function paint() { prev.innerHTML = img.url ? '<img src="' + escapeAttr(img.url) + '" alt="">' : '<span>No image</span>'; }
			paint();
			wrap.appendChild(prev);
			wrap.appendChild(el('button', { class: 'openb-btn openb-btn--block', text: 'Select Image', onclick: function () { openMedia(function (a) { img = { id: a.id, url: a.url }; bg.image = img; paint(); commit(); }); } }));
			wrap.appendChild(selectField('Size', bg.size || 'cover', { cover: 'Cover', contain: 'Contain', auto: 'Auto' }, function (v) { bg.size = v; commit(); }));
			wrap.appendChild(selectField('Position', bg.position || 'center center', {
				'center center': 'Center', 'top left': 'Top Left', 'top center': 'Top', 'top right': 'Top Right',
				'center left': 'Left', 'center right': 'Right', 'bottom left': 'Bottom Left', 'bottom center': 'Bottom', 'bottom right': 'Bottom Right'
			}, function (v) { bg.position = v; commit(); }));
			wrap.appendChild(selectField('Repeat', bg.repeat || 'no-repeat', { 'no-repeat': 'No repeat', 'repeat': 'Repeat', 'repeat-x': 'Repeat X', 'repeat-y': 'Repeat Y' }, function (v) { bg.repeat = v; commit(); }));
			wrap.appendChild(colorPopoverRow('Overlay/BG Color', bg.color || '', function (v) { bg.color = v; commit(); }));
		} else if (bg.type === 'gradient') {
			wrap.appendChild(colorPopoverRow('From', bg.from || '#2563eb', function (v) { bg.from = v; commit(); }));
			wrap.appendChild(colorPopoverRow('To', bg.to || '#7c3aed', function (v) { bg.to = v; commit(); }));
			wrap.appendChild(unitSliderRow('Angle', (bg.angle != null ? bg.angle : 135) + '', { min: 0, max: 360, units: [''], plain: true }, function (v) { bg.angle = parseInt(v, 10) || 0; commit(); }));
		}
		return wrap;
	}

	function selectField(label, value, choices, onChange) {
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
		pane.appendChild(buildHeadingAudit());
		pane.appendChild(buildLayerList(state.tree, 0));
	}

	/* Accessibility: heading-order linter. Walks headings in document order and
	   flags a missing/duplicate H1 and skipped levels (e.g. H2 → H4). */
	function collectHeadings(nodes, out) {
		(nodes || []).forEach(function (n) {
			if (n.type === 'heading') {
				var tag = (n.settings && n.settings.content && n.settings.content.tag) || 'h2';
				var lvl = parseInt(String(tag).replace(/[^0-9]/g, ''), 10);
				if (lvl >= 1 && lvl <= 6) out.push({ id: n.id, level: lvl });
			}
			if (n.children && n.children.length) collectHeadings(n.children, out);
		});
		return out;
	}
	function auditHeadings() {
		var hs = collectHeadings(state.tree, []);
		var issues = [];
		if (hs.length) {
			var h1s = hs.filter(function (h) { return h.level === 1; }).length;
			if (h1s === 0) issues.push('No H1 on the page — add one top-level heading.');
			if (h1s > 1) issues.push('Multiple H1s (' + h1s + ') — use a single H1 per page.');
			var prev = 0;
			hs.forEach(function (h) {
				if (prev && h.level > prev + 1) issues.push('Heading jumps from H' + prev + ' to H' + h.level + ' — don’t skip levels.');
				prev = h.level;
			});
		}
		return { count: hs.length, issues: issues };
	}
	function buildHeadingAudit() {
		var a = auditHeadings();
		if (!a.count) return el('span');
		if (!a.issues.length) {
			return el('div', { class: 'openb-audit is-ok' }, [el('span', { text: '✓ Heading order looks good (' + a.count + ').' })]);
		}
		var box = el('div', { class: 'openb-audit is-warn' }, [el('div', { class: 'openb-audit__title', text: '⚠ Heading order' })]);
		a.issues.forEach(function (msg) { box.appendChild(el('div', { class: 'openb-audit__item', text: msg })); });
		return box;
	}
	function buildLayerList(nodes, depth) {
		var ul = el('div', { class: 'openb-layers' });
		nodes.forEach(function (n) {
			var def = WIDGETS[n.type] || { title: n.type, icon: '' };
			var isContainer = WIDGETS[n.type] && WIDGETS[n.type].isContainer;
			var hasKids = n.children && n.children.length;
			var collapsed = !!state.collapsed[n.id];

			var caret = el('span', { class: 'openb-layer__caret' + (hasKids ? '' : ' is-empty') });
			if (hasKids) {
				caret.innerHTML = collapsed ? '▸' : '▾';
				caret.addEventListener('click', function (e) {
					e.stopPropagation();
					if (state.collapsed[n.id]) delete state.collapsed[n.id]; else state.collapsed[n.id] = true;
					renderLayers();
				});
			}

			var row = el('div', {
				class: 'openb-layer' + (n.id === state.selectedId ? ' is-selected' : ''),
				draggable: 'true',
				'data-layer-id': n.id,
				style: 'padding-left:' + (6 + depth * 14) + 'px',
				onclick: function (e) { e.stopPropagation(); selectNode(n.id); },
				oncontextmenu: function (e) { e.preventDefault(); e.stopPropagation(); selectNode(n.id); showContextMenu(e.clientX, e.clientY, nodeMenuItems(n.id)); }
			}, [
				caret,
				el('span', { class: 'openb-layer__icon', html: widgetIcon(def.icon) }),
				el('span', { class: 'openb-layer__name', text: def.title }),
				el('span', { class: 'openb-layer__acts' }, [
					el('button', { class: 'openb-iconbtn', title: 'Duplicate', text: '⎘', onclick: function (e) { e.stopPropagation(); duplicateNode(n.id); } }),
					el('button', { class: 'openb-iconbtn', title: 'Delete', text: '×', onclick: function (e) { e.stopPropagation(); deleteNode(n.id); } })
				])
			]);

			// Drag-to-reorder / nest within the tree.
			row.addEventListener('dragstart', function (e) {
				state.drag = { mode: 'move', id: n.id };
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', n.id);
				e.stopPropagation();
			});
			row.addEventListener('dragover', function (e) {
				if (!state.drag || state.drag.mode !== 'move') return;
				e.preventDefault();
				e.stopPropagation();
				row.classList.remove('layer-before', 'layer-after', 'layer-inside');
				var r = row.getBoundingClientRect();
				var ratio = (e.clientY - r.top) / r.height;
				if (isContainer && ratio > 0.3 && ratio < 0.7) row.classList.add('layer-inside');
				else if (ratio < 0.5) row.classList.add('layer-before');
				else row.classList.add('layer-after');
			});
			row.addEventListener('dragleave', function () { row.classList.remove('layer-before', 'layer-after', 'layer-inside'); });
			row.addEventListener('drop', function (e) {
				if (!state.drag || state.drag.mode !== 'move') return;
				e.preventDefault();
				e.stopPropagation();
				var pos = row.classList.contains('layer-inside') ? 'inside' : (row.classList.contains('layer-before') ? 'before' : 'after');
				row.classList.remove('layer-before', 'layer-after', 'layer-inside');
				var dragId = state.drag.id;
				state.drag = null;
				if (dragId === n.id) return;
				pushHistory();
				moveNode(dragId, n.id, pos);
				if (pos === 'inside') delete state.collapsed[n.id];
				markDirty(); renderCanvas(); renderLayers();
			});

			ul.appendChild(row);
			if (hasKids && !collapsed) ul.appendChild(buildLayerList(n.children, depth + 1));
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

		// Site-wide custom CSS.
		pane.appendChild(el('div', { class: 'openb-widgetcat', text: 'Custom CSS (site-wide)' }));
		var cssArea = el('textarea', { class: 'openb-input', rows: 8, spellcheck: 'false', placeholder: '/* Applies to every page on the site */' });
		cssArea.value = state.globalCss || '';
		cssArea.addEventListener('input', function () { state.globalCss = cssArea.value; });
		pane.appendChild(el('div', { class: 'openb-field' }, [cssArea]));
		pane.appendChild(el('p', { class: 'openb-hint', text: 'Printed on every front-end page. Sanitized on save (no @import, url() or behaviour hacks).' }));

		pane.appendChild(el('button', { class: 'openb-btn openb-btn--primary openb-btn--block', text: 'Save Brand Settings', onclick: saveGlobals }));
	}
	function saveGlobals() {
		api('/global-styles', { styles: state.globals, custom_css: state.globalCss }).then(function (res) {
			if (res.ok) {
				toast('Brand settings saved');
				if (res.data && typeof res.data.custom_css === 'string') state.globalCss = res.data.custom_css;
				// Reflect site-wide CSS live in the canvas.
				var doc = canvasDoc();
				if (doc) {
					var node = doc.getElementById('openb-global-custom-css');
					if (node) node.textContent = state.globalCss;
				}
				renderCanvas();
			}
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
	function copyNode(id) {
		var hit = findNode(id);
		if (!hit) return;
		saveClipboard(deepClone(hit.node));
		toast('Copied');
	}
	function cutNode(id) {
		copyNode(id);
		deleteNode(id);
	}
	// Paste after the target node (or into it if it's an empty container).
	function pasteNode(targetId) {
		if (!state.clipboard) { toast('Clipboard is empty', true); return; }
		pushHistory();
		var copy = deepClone(state.clipboard);
		reId(copy);
		var hit = targetId ? findNode(targetId) : null;
		if (hit && WIDGETS[hit.node.type] && WIDGETS[hit.node.type].isContainer) {
			hit.node.children = hit.node.children || [];
			hit.node.children.push(copy);
		} else if (hit) {
			hit.list.splice(hit.index + 1, 0, copy);
		} else {
			state.tree.push(copy);
		}
		markDirty(); renderCanvas(); renderLayers(); selectNode(copy.id, true);
	}
	function moveUp(id) {
		var hit = findNode(id);
		if (!hit || hit.index === 0) return;
		pushHistory();
		var n = hit.list.splice(hit.index, 1)[0];
		hit.list.splice(hit.index - 1, 0, n);
		markDirty(); renderCanvas(); renderLayers();
	}
	function moveDown(id) {
		var hit = findNode(id);
		if (!hit || hit.index >= hit.list.length - 1) return;
		pushHistory();
		var n = hit.list.splice(hit.index, 1)[0];
		hit.list.splice(hit.index + 1, 0, n);
		markDirty(); renderCanvas(); renderLayers();
	}

	/* ----------------------------------------------------------------------- *
	 * Context menu (right-click on canvas blocks and layer rows)
	 * ----------------------------------------------------------------------- */
	function nodeMenuItems(id) {
		var hit = findNode(id);
		var isContainer = hit && WIDGETS[hit.node.type] && WIDGETS[hit.node.type].isContainer;
		return [
			{ label: 'Edit', icon: '✎', fn: function () { selectNode(id); } },
			{ label: 'Copy', icon: '⧉', fn: function () { copyNode(id); } },
			{ label: 'Cut', icon: '✂', fn: function () { cutNode(id); } },
			{ label: state.clipboard ? (isContainer ? 'Paste inside' : 'Paste after') : 'Paste', icon: '📋', disabled: !state.clipboard, fn: function () { pasteNode(id); } },
			{ label: 'Duplicate', icon: '⎘', fn: function () { duplicateNode(id); } },
			{ sep: true },
			{ label: 'Move Up', icon: '↑', fn: function () { moveUp(id); } },
			{ label: 'Move Down', icon: '↓', fn: function () { moveDown(id); } },
			{ sep: true },
			{ label: 'Delete', icon: '🗑', danger: true, fn: function () { deleteNode(id); } }
		];
	}

	function showContextMenu(x, y, items) {
		closeContextMenu();
		var menu = el('div', { class: 'openb-ctxmenu', id: 'openb-ctxmenu' });
		items.forEach(function (it) {
			if (it.sep) { menu.appendChild(el('div', { class: 'openb-ctxmenu__sep' })); return; }
			var item = el('button', {
				class: 'openb-ctxmenu__item' + (it.danger ? ' is-danger' : '') + (it.disabled ? ' is-disabled' : ''),
				onclick: function () { if (it.disabled) return; closeContextMenu(); it.fn(); }
			}, [
				el('span', { class: 'openb-ctxmenu__icon', text: it.icon || '' }),
				el('span', { text: it.label })
			]);
			menu.appendChild(item);
		});
		document.body.appendChild(menu);
		// Keep within viewport.
		var w = menu.offsetWidth, h = menu.offsetHeight;
		var vw = window.innerWidth, vh = window.innerHeight;
		menu.style.left = Math.min(x, vw - w - 8) + 'px';
		menu.style.top = Math.min(y, vh - h - 8) + 'px';
		setTimeout(function () {
			document.addEventListener('click', closeContextMenu, { once: true });
			document.addEventListener('keydown', escClose);
			var cdoc = canvasDoc();
			if (cdoc) cdoc.addEventListener('click', closeContextMenu, { once: true });
		}, 0);
	}
	function escClose(e) { if (e.key === 'Escape') closeContextMenu(); }
	function closeContextMenu() {
		var m = document.getElementById('openb-ctxmenu');
		if (m) m.parentNode.removeChild(m);
		document.removeEventListener('keydown', escClose);
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
		api('/save', {
			post_id: BOOT.postId,
			tree: state.tree,
			page_settings: state.pageSettings,
			title: state.title
		}).then(function (res) {
			if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
			if (res.ok) { state.dirty = false; toast('Saved'); }
			else toast('Save failed', true);
		}).catch(function () {
			if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
			toast('Save failed', true);
		});
	}

	/* ----------------------------------------------------------------------- *
	 * Page settings modal
	 * ----------------------------------------------------------------------- */
	function openPageSettings() {
		var ps = state.pageSettings;
		var overlay = el('div', { class: 'openb-modal-overlay', onclick: function (e) { if (e.target === overlay) closeModal(); } });
		var body = el('div', { class: 'openb-modal__body' });

		// Title
		var titleField = controlField({ type: 'text', label: 'Page Title' }, state.title, function (v) { state.title = v; markDirty(); });
		body.appendChild(titleField);

		body.appendChild(controlField({ type: 'select', label: 'Layout', choices: { 'default': 'Theme Default', full: 'Full Width', boxed: 'Boxed' } }, ps.layout || 'default', function (v) { ps.layout = v; markDirty(); }));
		body.appendChild(controlField({ type: 'toggle', label: 'Hide page title' }, !!ps.hide_title, function (v) { ps.hide_title = v; markDirty(); }));
		body.appendChild(controlField({ type: 'text', label: 'Content Max Width (e.g. 1140px)' }, ps.content_width || '', function (v) { ps.content_width = v; markDirty(); }));
		body.appendChild(colorPopoverRow('Page Background', ps.background || '', function (v) { ps.background = v; markDirty(); }));
		body.appendChild(controlField({ type: 'text', label: 'Body CSS Classes' }, ps.body_classes || '', function (v) { ps.body_classes = v; markDirty(); }));

		body.appendChild(el('div', { class: 'openb-stylegroup__title', text: 'Custom CSS (page)' }));
		body.appendChild(controlField({ type: 'textarea', label: '' }, ps.custom_css || '', function (v) { ps.custom_css = v; markDirty(); }));
		body.appendChild(el('p', { class: 'openb-hint', text: 'Applies to this page only. Use normal CSS selectors.' }));

		// SEO — meta title/description + OG image. Defers to a real SEO plugin.
		body.appendChild(el('div', { class: 'openb-stylegroup__title', text: 'SEO' }));
		if (BOOT.seoActive) {
			body.appendChild(el('p', { class: 'openb-hint', text: 'An SEO plugin (Yoast / Rank Math / AIOSEO / SEOPress) is active, so Open Builder defers to it — set meta there. These fields are ignored while it is active.' }));
		}
		body.appendChild(controlField({ type: 'text', label: 'Meta Title' }, ps.seo_title || '', function (v) { ps.seo_title = v; markDirty(); }));
		body.appendChild(controlField({ type: 'textarea', label: 'Meta Description' }, ps.seo_description || '', function (v) { ps.seo_description = v; markDirty(); }));
		body.appendChild(el('p', { class: 'openb-hint', text: 'Aim for ~50–60 characters for the title and ~150–160 for the description.' }));
		body.appendChild(el('label', { class: 'openb-field__label', text: 'Social Share Image (OG)' }));
		body.appendChild(controlField({ type: 'image', label: '' }, ps.seo_og_image ? { id: 0, url: ps.seo_og_image } : { id: 0, url: '' }, function (v) { ps.seo_og_image = (v && v.url) ? v.url : ''; markDirty(); }));

		if (BOOT.canCustomJs) {
			body.appendChild(el('div', { class: 'openb-stylegroup__title', text: 'Custom JS (page)' }));
			body.appendChild(controlField({ type: 'textarea', label: '' }, ps.custom_js || '', function (v) { ps.custom_js = v; markDirty(); }));
			body.appendChild(el('p', { class: 'openb-hint', text: 'Runs in the page footer. No <script> tags needed.' }));
		} else {
			body.appendChild(el('p', { class: 'openb-hint', text: 'Custom JS requires the unfiltered_html capability (administrators on single-site).' }));
		}

		var modal = el('div', { class: 'openb-modal' }, [
			el('div', { class: 'openb-modal__head' }, [
				el('span', { class: 'openb-modal__title', text: 'Page Settings' }),
				el('button', { class: 'openb-iconbtn', text: '×', title: 'Close', onclick: closeModal })
			]),
			body,
			el('div', { class: 'openb-modal__foot' }, [
				el('button', { class: 'openb-btn', onclick: closeModal }, ['Close']),
				el('button', { class: 'openb-btn openb-btn--primary', onclick: function () { closeModal(); save(); } }, ['Save'])
			])
		]);
		overlay.appendChild(modal);
		document.body.appendChild(overlay);
	}
	function closeModal() {
		var m = document.querySelector('.openb-modal-overlay');
		if (m) m.parentNode.removeChild(m);
	}

	function markDirty() { state.dirty = true; }

	window.addEventListener('beforeunload', function (e) {
		if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
	});
	document.addEventListener('keydown', function (e) {
		var mod = e.metaKey || e.ctrlKey;
		if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
		if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); return; }

		// Element shortcuts only when not typing in a field.
		var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
		if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
		if (!state.selectedId) return;

		if (mod && e.key.toLowerCase() === 'c') { e.preventDefault(); copyNode(state.selectedId); }
		else if (mod && e.key.toLowerCase() === 'x') { e.preventDefault(); cutNode(state.selectedId); }
		else if (mod && e.key.toLowerCase() === 'v') { e.preventDefault(); pasteNode(state.selectedId); }
		else if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); duplicateNode(state.selectedId); }
		else if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); deleteNode(state.selectedId); }
	});

	/* ----------------------------------------------------------------------- *
	 * UI atoms
	 * ----------------------------------------------------------------------- */
	function svg(path) {
		return el('span', { class: 'openb-svg', html: '<svg viewBox="0 0 20 22" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"/></svg>' });
	}
	// Like svg() but accepts raw inner SVG markup (multiple paths) on a 24×24 grid.
	function svgRaw(inner) {
		return el('span', { class: 'openb-svg', html: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>' });
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
