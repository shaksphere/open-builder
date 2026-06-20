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
	});

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
				fields[name] = input.value;
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

			fetch(CFG.restUrl + '/form', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					form_id: formId,
					post_id: CFG.postId,
					_nonce: nonce,
					ob_hp: form.querySelector('[name="ob_hp"]') ? form.querySelector('[name="ob_hp"]').value : '',
					fields: fields
				})
			}).then(function (r) {
				return r.json().then(function (j) { return { ok: r.ok, data: j }; });
			}).then(function (res) {
				form.classList.remove('is-submitting');
				if (res.ok && res.data.success) {
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
