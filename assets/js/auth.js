(function ($) {
	'use strict';

	const cfg = window.etwaAuth || {};
	const ui = window.etwaUI || {};

	function api(path, options = {}) {
		options.credentials = 'same-origin';
		options.headers = Object.assign(
			{
				'X-ETWA-Nonce': cfg.nonce,
				'Content-Type': 'application/json',
			},
			options.headers || {}
		);
		return fetch(cfg.root + path, options).then(async (response) => {
			const data = await response.json().catch(() => ({}));
			if (!response.ok && !data.message) {
				data.message = cfg.i18n?.error || 'Something went wrong.';
				data.success = false;
			}
			return data;
		});
	}

	function setLoading($btn, loading, label) {
		if (ui.setButtonLoading) {
			ui.setButtonLoading($btn[0], loading, label);
			return;
		}
		$btn.prop('disabled', loading);
	}

	function syncPasswordToggle($btn, visible) {
		const showLabel = $btn.attr('data-label-show') || cfg.i18n?.showPassword || 'Show password';
		const hideLabel = $btn.attr('data-label-hide') || cfg.i18n?.hidePassword || 'Hide password';
		$btn
			.attr('aria-pressed', visible ? 'true' : 'false')
			.attr('aria-label', visible ? hideLabel : showLabel)
			.text(visible ? cfg.i18n?.hide || 'Hide' : cfg.i18n?.show || 'Show');
	}

	$(function () {
		if (!$('#etwa-auth-app').length) {
			return;
		}

		$('#etwa-auth-app').on('click', '.etwa-auth-password-toggle', function () {
			const $btn = $(this);
			const inputId = $btn.attr('aria-controls');
			const $input = inputId ? $('#' + inputId) : $btn.siblings('input').first();
			if (!$input.length) {
				return;
			}
			const visible = $input.attr('type') === 'password';
			$input.attr('type', visible ? 'text' : 'password');
			syncPasswordToggle($btn, visible);
		});

		$('.etwa-auth-password-toggle').each(function () {
			syncPasswordToggle($(this), false);
		});

		$('#etwa-login-form').on('submit', function (e) {
			e.preventDefault();
			const $btn = $(this).find('button[type="submit"]');
			const payload = {
				email: $('#etwa-login-email').val().trim(),
				password: $('#etwa-login-password').val(),
				remember: $('#etwa-login-remember').is(':checked'),
			};
			const redirectTo = new URLSearchParams(window.location.search).get('redirect_to');
			if (redirectTo) {
				payload.redirect_to = redirectTo;
			}

			setLoading($btn, true, cfg.i18n?.loggingIn || 'Logging in…');
			api('auth/login', { method: 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					window.location.href = res.data?.redirect || cfg.portalUrl || '/';
				})
				.catch((err) => {
					if (ui.error) ui.error(err.message);
					else alert(err.message);
				})
				.finally(() => setLoading($btn, false));
		});

		$('#etwa-forgot-password-form').on('submit', function (e) {
			e.preventDefault();
			const $btn = $(this).find('button[type="submit"]');
			const payload = {
				email: $('#etwa-forgot-email').val().trim(),
			};

			setLoading($btn, true, cfg.i18n?.sending || 'Sending…');
			api('auth/forgot-password', { method: 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					if (ui.success) {
						ui.success(cfg.i18n?.resetEmailSent || 'If an account exists for that email, a reset link has been sent.');
					}
					$('#etwa-forgot-email').val('');
				})
				.catch((err) => {
					if (ui.error) ui.error(err.message);
					else alert(err.message);
				})
				.finally(() => setLoading($btn, false));
		});

		$('#etwa-reset-password-form').on('submit', function (e) {
			e.preventDefault();
			const $btn = $(this).find('button[type="submit"]');
			const payload = {
				key: $('#etwa-reset-key').val(),
				login: $('#etwa-reset-login').val(),
				password: $('#etwa-reset-password').val(),
				password_confirm: $('#etwa-reset-password-confirm').val(),
			};

			setLoading($btn, true, cfg.i18n?.saving || 'Saving…');
			api('auth/reset-password', { method: 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					window.location.href = res.data?.redirect || cfg.loginUrl || '/';
				})
				.catch((err) => {
					if (ui.error) ui.error(err.message);
					else alert(err.message);
				})
				.finally(() => setLoading($btn, false));
		});
	});
})(jQuery);
