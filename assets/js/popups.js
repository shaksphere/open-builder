/**
 * Open Builder — popup loader (front end).
 *
 * Vanilla JS, no dependencies. For each popup rendered in the footer it reads
 * the trigger/frequency config from data-attributes, respects the frequency cap,
 * wires the chosen trigger, and handles accessible open/close: ARIA dialog,
 * focus trap, ESC, overlay click, and focus restore.
 */
(function () {
	'use strict';

	var DAY = 86400000;

	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}

	/* --- Frequency capping (per popup id) --------------------------------- */
	function seenKey(id) { return 'openb_popup_seen_' + id; }

	function isCapped(id, mode, days) {
		try {
			if (mode === 'always') return false;
			if (mode === 'session') return sessionStorage.getItem(seenKey(id)) === '1';
			if (mode === 'days') {
				var ts = parseInt(localStorage.getItem(seenKey(id)) || '0', 10);
				return ts > 0 && (Date.now() - ts) < Math.max(1, days) * DAY;
			}
		} catch (e) {}
		return false;
	}

	function markSeen(id, mode, days) {
		try {
			if (mode === 'session') sessionStorage.setItem(seenKey(id), '1');
			else if (mode === 'days') localStorage.setItem(seenKey(id), String(Date.now()));
		} catch (e) {}
	}

	/* --- Open / close with focus management ------------------------------- */
	var openPopup = null;
	var lastFocused = null;

	function focusable(container) {
		return Array.prototype.slice.call(container.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		)).filter(function (el) { return el.offsetWidth || el.offsetHeight || el.getClientRects().length; });
	}

	function open(el, cfg) {
		if (openPopup) return; // one at a time
		lastFocused = document.activeElement;
		el.hidden = false;
		el.setAttribute('aria-hidden', 'false');
		document.documentElement.classList.add('openb-popup-open');
		openPopup = el;
		markSeen(cfg.id, cfg.freq, cfg.days);

		var dialog = el.querySelector('.openb-popup__dialog');
		var f = focusable(dialog);
		(f[0] || dialog).focus();

		el.addEventListener('keydown', onKeydown);
	}

	function close(el) {
		if (!el) return;
		el.setAttribute('aria-hidden', 'true');
		el.hidden = true;
		el.removeEventListener('keydown', onKeydown);
		document.documentElement.classList.remove('openb-popup-open');
		openPopup = null;
		if (lastFocused && lastFocused.focus) lastFocused.focus();
		lastFocused = null;
	}

	function onKeydown(e) {
		if (e.key === 'Escape') { e.preventDefault(); close(openPopup); return; }
		if (e.key !== 'Tab' || !openPopup) return;
		var dialog = openPopup.querySelector('.openb-popup__dialog');
		var f = focusable(dialog);
		if (!f.length) { e.preventDefault(); return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	}

	/* --- Triggers --------------------------------------------------------- */
	function wireTrigger(el, cfg, fire) {
		switch (cfg.trigger) {
			case 'load':
				setTimeout(fire, Math.max(0, cfg.delay) * 1000);
				break;
			case 'exit':
				var onLeave = function (e) {
					if (e.clientY <= 0) { document.removeEventListener('mouseout', onLeave); fire(); }
				};
				document.addEventListener('mouseout', onLeave);
				break;
			case 'scroll':
				var onScroll = function () {
					var h = document.documentElement;
					var max = (h.scrollHeight - h.clientHeight) || 1;
					var pct = (h.scrollTop || window.pageYOffset) / max * 100;
					if (pct >= cfg.scroll) { window.removeEventListener('scroll', onScroll); fire(); }
				};
				window.addEventListener('scroll', onScroll, { passive: true });
				break;
			case 'click':
				if (!cfg.selector) return;
				document.addEventListener('click', function (e) {
					var t = e.target.closest && e.target.closest(cfg.selector);
					if (t) { e.preventDefault(); fire(); }
				});
				break;
			case 'idle':
				var timer;
				var reset = function () { clearTimeout(timer); timer = setTimeout(fire, Math.max(1, cfg.idle) * 1000); };
				['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function (ev) {
					document.addEventListener(ev, reset, { passive: true });
				});
				reset();
				break;
		}
	}

	ready(function () {
		var nodes = document.querySelectorAll('.openb-popup[data-openb-popup]');
		Array.prototype.forEach.call(nodes, function (el) {
			var cfg = {
				id: el.getAttribute('data-openb-popup'),
				trigger: el.getAttribute('data-trigger') || 'load',
				delay: parseInt(el.getAttribute('data-delay') || '3', 10),
				scroll: parseInt(el.getAttribute('data-scroll') || '50', 10),
				selector: el.getAttribute('data-selector') || '',
				idle: parseInt(el.getAttribute('data-idle') || '20', 10),
				freq: el.getAttribute('data-freq') || 'session',
				days: parseInt(el.getAttribute('data-days') || '7', 10),
				overlayClose: el.getAttribute('data-overlay-close') === '1'
			};

			// Close handlers (always wired; harmless while hidden).
			Array.prototype.forEach.call(el.querySelectorAll('[data-openb-close]'), function (btn) {
				if (btn.classList.contains('openb-popup__overlay') && !cfg.overlayClose) return;
				btn.addEventListener('click', function (e) { e.preventDefault(); close(el); });
			});

			if (isCapped(cfg.id, cfg.freq, cfg.days)) return;

			var fired = false;
			var fire = function () {
				if (fired) return;
				fired = true;
				if (!isCapped(cfg.id, cfg.freq, cfg.days)) open(el, cfg);
			};
			wireTrigger(el, cfg, fire);
		});
	});
})();
