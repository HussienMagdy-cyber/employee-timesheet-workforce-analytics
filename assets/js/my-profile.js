(function ($) {
	'use strict';

	$(function () {
		if (!$('#etwa-my-profile-page').length || !window.etwaEmployeeProfile) return;
		window.etwaEmployeeProfile.init({ mode: 'standalone' });
	});
})(jQuery);
