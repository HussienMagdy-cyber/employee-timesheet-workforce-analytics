(function ($, window) {
	'use strict';

	const cfg = () => window.etwaApp || {};
	const ui = () => window.etwaUI || {};

	function api(path, options = {}) {
		options.credentials = 'same-origin';
		options.headers = Object.assign(
			{ 'X-WP-Nonce': cfg().nonce, 'Content-Type': 'application/json' },
			options.headers || {}
		);
		return fetch(cfg().root + path, options).then(async (response) => {
			const data = await response.json().catch(() => ({}));
			if (!response.ok && !data.message) {
				data.message = cfg().i18n?.error || 'Something went wrong.';
				data.success = false;
			}
			return data;
		});
	}

	function roleOptions(selected) {
		const roles = cfg().creatableRoles || [
			{ slug: 'etwa_owner', label: 'Owner' },
			{ slug: 'etwa_hr_manager', label: 'HR Manager' },
			{ slug: 'etwa_department_head', label: 'Department Head' },
			{ slug: 'etwa_employee', label: 'Employee' },
		];
		let html = roles
			.map(
				(r) =>
					`<option value="${r.slug}"${String(selected) === String(r.slug) ? ' selected' : ''}>${r.label}</option>`
			)
			.join('');

		if (selected && !roles.some((r) => String(r.slug) === String(selected))) {
			html += `<option value="${selected}" selected>${selected}</option>`;
		}

		return html;
	}

	function ensureSelectOptions($select, html, value) {
		if (!$select.find('option').length) {
			$select.html(html);
		}
		$select.val(value ? String(value) : '');
	}

	function notSetLabel() {
		return cfg().i18n?.notSet || 'Not set';
	}

	function renderDetails(profile) {
		const i18n = cfg().i18n || {};
		const rows = [
			[i18n.email || 'Email', profile.email || ''],
			[i18n.jobTitle || 'Job title', profile.job_title || notSetLabel()],
			[i18n.department || 'Department', profile.department_name || notSetLabel()],
			[i18n.joinDate || 'Join date', profile.join_date_formatted || notSetLabel()],
			[i18n.birthday || 'Birthday', profile.birthday_formatted || notSetLabel()],
		];

		return rows
			.map(
				([label, value]) =>
					`<div class="etwa-profile-detail-row"><dt>${label}</dt><dd>${value}</dd></div>`
			)
			.join('');
	}

	function fillEditForm(profile) {
		$('#etwa-profile-edit-name').val(profile.display_name || '');
		$('#etwa-profile-edit-email').val(profile.email || '');
		ensureSelectOptions(
			$('#etwa-profile-edit-role'),
			roleOptions(profile.role),
			profile.role || 'etwa_employee'
		);
		$('#etwa-profile-edit-job-title').val(profile.job_title || '');
		$('#etwa-profile-edit-join-date').val(profile.join_date || '');
		$('#etwa-profile-edit-birthday').val(profile.birthday || '');
	}

	function showViewPanel() {
		$('#etwa-profile-view').removeAttr('hidden');
		$('#etwa-profile-edit-panel').attr('hidden', 'hidden');
	}

	function showEditPanel() {
		$('#etwa-profile-view').attr('hidden', 'hidden');
		$('#etwa-profile-edit-panel').removeAttr('hidden');
	}

	window.etwaEmployeeProfile = {
		init(options = {}) {
			const $app = $('#etwa-employee-profile-app');
			if (!$app.length) return;

			const userId = options.userId || null;
			const mode = options.mode || 'standalone';
			const showBack = !!options.showBack;
			let currentProfile = null;
			let resolvedUserId = userId;

			if (showBack) {
				$('#etwa-profile-back').removeAttr('hidden');
			}

			function profilePath(id) {
				if (!id || id === cfg().userId) {
					return 'employees/me/profile';
				}
				return `employees/${id}/profile`;
			}

			function renderProfile(profile) {
				currentProfile = profile;
				resolvedUserId = profile.id;

				$app.removeAttr('hidden');
				$('#etwa-profile-avatar').text(profile.initials || '?');
				$('#etwa-profile-name').text(profile.display_name || '');
				$('#etwa-profile-job-title').text(profile.job_title || '');
				$('#etwa-profile-role').text(profile.role_label || '');
				$('#etwa-profile-details').html(renderDetails(profile));

				const $actions = $('#etwa-profile-actions');
				if (profile.can_edit) {
					$actions.removeAttr('hidden');
					fillEditForm(profile);
				} else {
					$actions.attr('hidden', 'hidden');
				}

				showViewPanel();
			}

			function loadProfile(id) {
				$app.removeAttr('hidden');
				$('#etwa-profile-details').html(
					`<div class="etwa-skeleton" style="height:120px"></div>`
				);

				return api(profilePath(id))
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						renderProfile(res.data || {});
					})
					.catch((err) => {
						ui().error(err.message);
						if (typeof options.onBack === 'function') options.onBack();
					});
			}

			$('#etwa-profile-back').off('click').on('click', function () {
				if (typeof options.onBack === 'function') options.onBack();
			});

			$('#etwa-profile-edit-btn')
				.off('click')
				.on('click', function () {
					if (!currentProfile) return;
					fillEditForm(currentProfile);
					showEditPanel();
				});

			$('#etwa-profile-edit-cancel')
				.off('click')
				.on('click', function () {
					showViewPanel();
				});

			$('#etwa-profile-reset-btn')
				.off('click')
				.on('click', function () {
					if (!resolvedUserId) return;
					const $btn = $(this);
					ui().setButtonLoading($btn[0], true);
					api(`employees/${resolvedUserId}/send-password-reset`, { method: 'POST' })
						.then((res) => {
							if (!res.success) throw new Error(res.message);
							ui().success(cfg().i18n?.passwordResetSent || 'Password reset email sent.');
						})
						.catch((err) => ui().error(err.message))
						.finally(() => ui().setButtonLoading($btn[0], false));
				});

			$('#etwa-profile-edit-form')
				.off('submit')
				.on('submit', function (e) {
					e.preventDefault();
					if (!resolvedUserId) return;

					const $btn = $(this).find('button[type="submit"]');
					const payload = {
						display_name: $('#etwa-profile-edit-name').val().trim(),
						email: $('#etwa-profile-edit-email').val().trim(),
						role: $('#etwa-profile-edit-role').val(),
						job_title: $('#etwa-profile-edit-job-title').val().trim(),
						join_date: $('#etwa-profile-edit-join-date').val(),
						birthday: $('#etwa-profile-edit-birthday').val() || null,
					};

					ui().setButtonLoading($btn[0], true, cfg().i18n?.saving);
					api(`employees/${resolvedUserId}/profile`, {
						method: 'PUT',
						body: JSON.stringify(payload),
					})
						.then((res) => {
							if (!res.success) throw new Error(res.message);
							renderProfile(res.data || {});
							ui().success(cfg().i18n?.profileSaved || 'Profile saved.');
						})
						.catch((err) => ui().error(err.message))
						.finally(() => ui().setButtonLoading($btn[0], false));
				});

			if (mode === 'standalone') {
				loadProfile(resolvedUserId || cfg().userId);
			}

			return {
				loadProfile,
				hide() {
					$app.attr('hidden', 'hidden');
				},
				show() {
					$app.removeAttr('hidden');
				},
			};
		},
	};
})(jQuery, window);
