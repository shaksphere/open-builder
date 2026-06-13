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
	});

	function bindForm(form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (form.classList.contains('is-submitting')) return;

			var formId = form.getAttribute('data-ob-form');
			var nonce = form.getAttribute('data-nonce');
			var messageEl = form.querySelector('.ob-form__message');

			var fields = {};
			Array.prototype.forEach.call(form.querySelectorAll('input, textarea, select'), function (input) {
				if (!input.name) return;
				fields[input.name] = input.value;
			});

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
