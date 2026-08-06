(function ($, window) {
	'use strict';

	const cfg = () => window.etwaApp || {};
	const ui = () => window.etwaUI || {};

	function api(path, options = {}) {
		return window.etwaAPI.api(path, options);
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
			[i18n.phone || 'Phone number', profile.phone || notSetLabel()],
			[i18n.department || 'Department', profile.department_name || notSetLabel()],
			[i18n.joinDate || 'Join date', profile.join_date_formatted || notSetLabel()],
			[i18n.birthday || 'Birthday', profile.birthday_formatted || notSetLabel()],
		];

		return rows
			.map(
				([label, value]) =>
					`<dt>${label}</dt><dd>${value}</dd>`
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
		$('#etwa-profile-edit-phone').val(profile.phone || '');
		$('#etwa-profile-edit-join-date').val(profile.join_date || '');
		$('#etwa-profile-edit-birthday').val(profile.birthday || '');
	}

	function applyEditMode(profile) {
		const fullEdit = !!profile.can_edit_full;
		$('.etwa-profile-field-hr').toggle(fullEdit);
		$('#etwa-profile-reset-btn, #etwa-profile-deactivate-btn, #etwa-profile-reactivate-btn').toggle(fullEdit);
		$('#etwa-profile-edit-title').text(
			fullEdit ? (cfg().i18n?.editEmployee || 'Edit Employee') : (cfg().i18n?.editProfile || 'Edit Profile')
		);
		$('#etwa-profile-edit-role, #etwa-profile-edit-job-title, #etwa-profile-edit-join-date').prop('required', fullEdit);
	}

	function profileUpdatePath(profile) {
		if (profile.can_edit_full) {
			return `employees/${profile.id}/profile`;
		}
		return 'employees/me/profile';
	}

	function buildUpdatePayload(profile) {
		const payload = {
			display_name: $('#etwa-profile-edit-name').val().trim(),
			email: $('#etwa-profile-edit-email').val().trim(),
			phone: $('#etwa-profile-edit-phone').val().trim(),
			birthday: $('#etwa-profile-edit-birthday').val() || null,
		};

		if (profile.can_edit_full) {
			payload.role = $('#etwa-profile-edit-role').val();
			payload.job_title = $('#etwa-profile-edit-job-title').val().trim();
			payload.join_date = $('#etwa-profile-edit-join-date').val();
		}

		return payload;
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
					applyEditMode(profile);
					if (profile.can_edit_full && profile.is_active) {
						$('#etwa-profile-deactivate-btn').removeAttr('hidden');
						$('#etwa-profile-reactivate-btn').attr('hidden', 'hidden');
					} else if (profile.can_edit_full) {
						$('#etwa-profile-deactivate-btn').attr('hidden', 'hidden');
						$('#etwa-profile-reactivate-btn').removeAttr('hidden');
					} else {
						$('#etwa-profile-deactivate-btn').attr('hidden', 'hidden');
						$('#etwa-profile-reactivate-btn').attr('hidden', 'hidden');
					}
				} else {
					$actions.attr('hidden', 'hidden');
				}

				showViewPanel();
			}

			function loadProfile(id) {
				$app.removeAttr('hidden');
				$('#etwa-profile-details').html(
					'<dd class="etwa-profile-loading"><div class="etwa-skeleton" style="height:120px"></div></dd>'
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
					applyEditMode(currentProfile);
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

			function setEmploymentStatus(status) {
				if (!resolvedUserId) return Promise.resolve();
				const isDeactivate = status === 'inactive';
				const msg = isDeactivate
					? cfg().i18n?.confirmDeactivate || 'Deactivate this employee? They will lose portal access.'
					: cfg().i18n?.confirmReactivate || 'Reactivate this employee?';
				return ui()
					.confirm(msg, isDeactivate ? cfg().i18n?.deactivate || 'Deactivate' : cfg().i18n?.reactivate || 'Reactivate')
					.then((ok) => {
						if (!ok) return;
						return api(`employees/${resolvedUserId}/status`, {
							method: 'PUT',
							body: JSON.stringify({ status }),
						}).then((res) => {
							if (!res.success) throw new Error(res.message);
							renderProfile(res.data || {});
							ui().success(
								isDeactivate
									? cfg().i18n?.employeeDeactivated || 'Employee deactivated.'
									: cfg().i18n?.employeeReactivated || 'Employee reactivated.'
							);
						});
					});
			}

			$('#etwa-profile-deactivate-btn')
				.off('click')
				.on('click', function () {
					setEmploymentStatus('inactive').catch((err) => ui().error(err.message));
				});

			$('#etwa-profile-reactivate-btn')
				.off('click')
				.on('click', function () {
					setEmploymentStatus('active').catch((err) => ui().error(err.message));
				});

			$('#etwa-profile-edit-form')
				.off('submit')
				.on('submit', function (e) {
					e.preventDefault();
					if (!currentProfile) return;

					const $btn = $(this).find('button[type="submit"]');
					const payload = buildUpdatePayload(currentProfile);

					ui().setButtonLoading($btn[0], true, cfg().i18n?.saving);
					api(profileUpdatePath(currentProfile), {
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
