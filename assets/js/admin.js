(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};

	$('#etwa-settings-form').on('submit', function (e) {
		e.preventDefault();
		const data = $(this).serializeArray();
		const payload = { action: 'etwa_save_settings', nonce: cfg.ajaxNonce };
		data.forEach(({ name, value }) => (payload[name] = value));
		$.post(cfg.ajaxUrl, payload)
			.done(() => alert('Settings saved.'))
			.fail(() => alert(cfg.i18n?.error || 'Error saving settings.'));
	});
})(jQuery);
