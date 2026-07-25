/**
 * Open Builder — front-end runtime. Currently handles AJAX form submission
 * against the nonce-protected REST endpoint. Kept tiny and dependency-free.
 */
(function () {
	'use strict';

	var CFG = window.OPENB_FRONT || {};

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	ready(function () {
		var forms = document.querySelectorAll('form.ob-form');
		Array.prototype.forEach.call(forms, bindForm);

		initAccordions();
		initTabs();
		initVideoFacades();
		initCounters();
		initProgress();
		initAnimations();
		initLightbox();
	});

	/* ----- Lightbox (galleries with data-ob-lightbox) -----
	   Progressive enhancement over real <a href> links: intercept clicks, show
	   the full image in an overlay with prev/next/close + keyboard control. */
	function initLightbox() {
		var galleries = document.querySelectorAll('.ob-gallery[data-ob-lightbox]');
		if (!galleries.length) return;

		var overlay, imgEl, countEl, items = [], index = 0;

		function build() {
			overlay = document.createElement('div');
			overlay.className = 'ob-lightbox';
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');
			overlay.setAttribute('aria-label', 'Image viewer');
			overlay.hidden = true;
			imgEl = document.createElement('img');
			imgEl.className = 'ob-lightbox__img';
			imgEl.alt = '';
			countEl = document.createElement('div');
			countEl.className = 'ob-lightbox__count';
			var close = btn('ob-lightbox__close', '×', 'Close', hide);
			var prev = btn('ob-lightbox__prev', '‹', 'Previous', function () { show(index - 1); });
			var next = btn('ob-lightbox__next', '›', 'Next', function () { show(index + 1); });
			overlay.appendChild(imgEl);
			overlay.appendChild(close);
			overlay.appendChild(prev);
			overlay.appendChild(next);
			overlay.appendChild(countEl);
			overlay.addEventListener('click', function (e) { if (e.target === overlay) hide(); });
			document.body.appendChild(overlay);
		}
		function btn(cls, label, aria, fn) {
			var b = document.createElement('button');
			b.className = 'ob-lightbox__btn ' + cls;
			b.type = 'button';
			b.textContent = label;
			b.setAttribute('aria-label', aria);
			b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
			return b;
		}
		function show(i) {
			if (!items.length) return;
			index = (i + items.length) % items.length;
			imgEl.src = items[index];
			countEl.textContent = (index + 1) + ' / ' + items.length;
			overlay.hidden = false;
			document.documentElement.classList.add('openb-popup-open');
		}
		function hide() {
			overlay.hidden = true;
			imgEl.src = '';
			document.documentElement.classList.remove('openb-popup-open');
		}

		galleries.forEach(function (gal) {
			var links = Array.prototype.slice.call(gal.querySelectorAll('.ob-gallery__item[href]'));
			gal.addEventListener('click', function (e) {
				var a = e.target.closest('.ob-gallery__item[href]');
				if (!a || !gal.contains(a)) return;
				e.preventDefault();
				if (!overlay) build();
				items = links.map(function (l) { return l.getAttribute('href'); });
				show(links.indexOf(a));
			});
		});

		document.addEventListener('keydown', function (e) {
			if (!overlay || overlay.hidden) return;
			if (e.key === 'Escape') hide();
			else if (e.key === 'ArrowRight') show(index + 1);
			else if (e.key === 'ArrowLeft') show(index - 1);
		});
	}

	/* ----- Entrance animations (reveal on scroll) -----
	   Progressive enhancement: the server emits only data-ob-anim on elements;
	   the initial hidden state is applied here in JS, so a no-JS visitor always
	   sees fully-visible content. Honors prefers-reduced-motion. */
	function initAnimations() {
		var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-ob-anim]'));
		if (!nodes.length) return;

		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce) return; // leave everything in its natural, visible state

		nodes.forEach(function (el) {
			var type = el.getAttribute('data-ob-anim') || 'fade';
			var dur = parseInt(el.getAttribute('data-ob-anim-dur'), 10);
			var delay = parseInt(el.getAttribute('data-ob-anim-delay'), 10);
			el.classList.add('ob-anim', 'ob-anim--' + type);
			if (dur) el.style.setProperty('--ob-anim-dur', dur + 'ms');
			if (delay) el.style.setProperty('--ob-anim-delay', delay + 'ms');
		});

		// Use a threshold of ~0 so elements taller than the viewport (e.g. a full
		// hero section) still trigger the moment any part scrolls into view —
		// onVisible's 0.4 ratio would never fire for those. A small negative
		// rootMargin holds the reveal until the element is a touch inside.
		function reveal(el) { el.classList.add('is-in'); }
		if (!('IntersectionObserver' in window)) {
			nodes.forEach(reveal);
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { reveal(entry.target); io.unobserve(entry.target); }
			});
		}, { threshold: 0.01, rootMargin: '0px 0px -8% 0px' });
		nodes.forEach(function (el) { io.observe(el); });
	}

	/* ----- Accordion ----- */
	function initAccordions() {
		document.querySelectorAll('[data-ob-accordion]').forEach(function (acc) {
			acc.addEventListener('click', function (e) {
				var header = e.target.closest('.ob-accordion__header');
				if (!header || !acc.contains(header)) return;
				var item = header.closest('.ob-accordion__item');
				var panel = item.querySelector('.ob-accordion__panel');
				var open = item.classList.toggle('is-open');
				header.setAttribute('aria-expanded', open ? 'true' : 'false');
				if (open) { panel.removeAttribute('hidden'); } else { panel.setAttribute('hidden', ''); }
			});
		});
	}

	/* ----- Tabs ----- */
	function initTabs() {
		document.querySelectorAll('[data-ob-tabs]').forEach(function (tabs) {
			var tabBtns = tabs.querySelectorAll('.ob-tabs__tab');
			var panels = tabs.querySelectorAll('.ob-tabs__panel');
			function activate(idx) {
				tabBtns.forEach(function (b, i) {
					var on = i === idx;
					b.classList.toggle('is-active', on);
					b.setAttribute('aria-selected', on ? 'true' : 'false');
					b.tabIndex = on ? 0 : -1;
				});
				panels.forEach(function (p, i) {
					var on = i === idx;
					p.classList.toggle('is-active', on);
					if (on) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
				});
			}
			tabBtns.forEach(function (b, i) {
				b.addEventListener('click', function () { activate(i); });
				b.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
						e.preventDefault();
						var next = e.key === 'ArrowRight' ? (i + 1) % tabBtns.length : (i - 1 + tabBtns.length) % tabBtns.length;
						tabBtns[next].focus();
						activate(next);
					}
				});
			});
		});
	}

	/* ----- Video facade (click to load embed) ----- */
	function initVideoFacades() {
		document.querySelectorAll('.ob-video__facade').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var src = btn.getAttribute('data-ob-embed');
				if (!src) return;
				var iframe = document.createElement('iframe');
				iframe.className = 'ob-video__player';
				iframe.setAttribute('src', src + (src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1');
				iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
				iframe.setAttribute('allowfullscreen', '');
				iframe.setAttribute('frameborder', '0');
				btn.parentNode.replaceChild(iframe, btn);
			});
		});
	}

	/* ----- Counter + progress (animate when scrolled into view) ----- */
	function onVisible(elements, cb) {
		if (!('IntersectionObserver' in window)) {
			elements.forEach(cb);
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) { cb(entry.target); io.unobserve(entry.target); }
			});
		}, { threshold: 0.4 });
		elements.forEach(function (el) { io.observe(el); });
	}

	function initCounters() {
		onVisible(Array.prototype.slice.call(document.querySelectorAll('[data-ob-counter]')), function (el) {
			var start = parseFloat(el.getAttribute('data-start')) || 0;
			var end = parseFloat(el.getAttribute('data-end')) || 0;
			var duration = parseInt(el.getAttribute('data-duration'), 10) || 2000;
			var decimals = (end % 1 !== 0) ? (String(end).split('.')[1] || '').length : 0;
			var startTime = null;
			function step(ts) {
				if (startTime === null) startTime = ts;
				var p = Math.min((ts - startTime) / duration, 1);
				var eased = 1 - Math.pow(1 - p, 3);
				var current = start + (end - start) * eased;
				el.textContent = current.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
				if (p < 1) requestAnimationFrame(step);
			}
			requestAnimationFrame(step);
		});
	}

	function initProgress() {
		onVisible(Array.prototype.slice.call(document.querySelectorAll('[data-ob-progress]')), function (el) {
			var pct = parseFloat(el.getAttribute('data-percent')) || 0;
			requestAnimationFrame(function () { el.style.width = Math.max(0, Math.min(100, pct)) + '%'; });
		});
	}

	function bindForm(form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (form.classList.contains('is-submitting')) return;

			var formId = form.getAttribute('data-ob-form');
			var nonce = form.getAttribute('data-nonce');
			var messageEl = form.querySelector('.ob-form__message');

			var fields = {};
			Array.prototype.forEach.call(form.querySelectorAll('input, textarea, select'), function (input) {
				var name = input.name;
				if (!name || name === 'ob_hp') return;
				if (input.type === 'checkbox') {
					if (!input.checked) return;
					// Grouped checkboxes use name="key[]"; collect into an array under "key".
					if (name.slice(-2) === '[]') {
						var key = name.slice(0, -2);
						(fields[key] = fields[key] || []).push(input.value);
					} else {
						fields[name] = input.value || '1';
					}
					return;
				}
				if (input.type === 'radio') {
					if (input.checked) fields[name] = input.value;
					return;
				}
				if (input.type === 'file') return; // handled separately as multipart
				fields[name] = input.value;
			});

			// Collect any chosen files; their presence switches us to multipart.
			var fileInputs = [];
			Array.prototype.forEach.call(form.querySelectorAll('input[type=file]'), function (fi) {
				if (fi.name && fi.files && fi.files.length) fileInputs.push(fi);
			});

			// Client-side required check for radio/checkbox groups (HTML "required"
			// can't span a group reliably).
			var groupMiss = [];
			Array.prototype.forEach.call(form.querySelectorAll('[data-ob-required="1"]'), function (group) {
				if (!group.querySelector('input:checked')) {
					groupMiss.push(group.getAttribute('aria-label') || 'a required field');
				}
			});
			if (groupMiss.length) {
				setMessage(messageEl, 'Please complete: ' + groupMiss.join(', '), 'error');
				return;
			}

			form.classList.add('is-submitting');
			setMessage(messageEl, '', '');

			var hp = form.querySelector('[name="ob_hp"]') ? form.querySelector('[name="ob_hp"]').value : '';
			var opts;
			if (fileInputs.length) {
				// Multipart: carries scalar fields plus the file blobs.
				var fd = new FormData();
				fd.append('form_id', formId);
				fd.append('post_id', CFG.postId);
				fd.append('_nonce', nonce);
				fd.append('ob_hp', hp);
				Object.keys(fields).forEach(function (k) {
					var v = fields[k];
					if (Array.isArray(v)) v.forEach(function (item) { fd.append('fields[' + k + '][]', item); });
					else fd.append('fields[' + k + ']', v);
				});
				fileInputs.forEach(function (fi) { fd.append(fi.name, fi.files[0]); });
				opts = { method: 'POST', credentials: 'same-origin', body: fd }; // browser sets multipart boundary
			} else {
				opts = {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						form_id: formId,
						post_id: CFG.postId,
						_nonce: nonce,
						ob_hp: hp,
						fields: fields
					})
				};
			}

			fetch(CFG.restUrl + '/form', opts).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok, data: j }; });
			}).then(function (res) {
				form.classList.remove('is-submitting');
				if (res.ok && res.data.success) {
					var redirect = form.getAttribute('data-redirect');
					if (redirect) { window.location.assign(redirect); return; }
					setMessage(messageEl, res.data.message, 'success');
					form.reset();
				} else {
					setMessage(messageEl, res.data.message || 'Something went wrong.', 'error');
				}
			}).catch(function () {
				form.classList.remove('is-submitting');
				setMessage(messageEl, 'Network error. Please try again.', 'error');
			});
		});

		// Add a honeypot field bots tend to fill.
		var hp = document.createElement('input');
		hp.type = 'text';
		hp.name = 'ob_hp';
		hp.tabIndex = -1;
		hp.autocomplete = 'off';
		hp.setAttribute('aria-hidden', 'true');
		hp.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0;';
		form.appendChild(hp);
	}

	function setMessage(elm, text, type) {
		if (!elm) return;
		elm.textContent = text || '';
		elm.className = 'ob-form__message' + (type ? ' is-' + type : '');
	}
})();
