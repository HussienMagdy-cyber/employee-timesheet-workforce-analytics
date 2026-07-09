(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	if (!cfg.canManage) {
		return;
	}

	const api = (path, options = {}) => {
		options.credentials = 'same-origin';
		options.headers = Object.assign(
			{ 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
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
	};

	const $modal = $('#etwa-client-edit-modal');

	function openModal() {
		$modal.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModal() {
		$modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	function rowData($row) {
		return {
			id: $row.data('id'),
			name: String($row.data('name') || ''),
			code: String($row.data('code') || ''),
			department: String($row.data('department') || 'General'),
			is_active: String($row.data('active')) === '1',
		};
	}

	$(function () {
		if (!$('#etwa-clients-app').length) return;

		$('#etwa-client-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api('clients', {
				method: 'POST',
				body: JSON.stringify(data),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.clientSaved || 'Client saved.');
					location.reload();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-clients-body').on('click', '.etwa-edit-client', function () {
			const data = rowData($(this).closest('tr'));
			$('#etwa-client-edit-id').val(data.id);
			$('#etwa-client-edit-name').val(data.name);
			$('#etwa-client-edit-code').val(data.code);
			$('#etwa-client-edit-department').val(data.department);
			$('#etwa-client-edit-active').prop('checked', data.is_active);
			openModal();
		});

		$('#etwa-client-edit-form').on('submit', function (e) {
			e.preventDefault();
			const id = $('#etwa-client-edit-id').val();
			const $btn = $(this).find('[type="submit"]');
			const payload = {
				name: $('#etwa-client-edit-name').val().trim(),
				code: $('#etwa-client-edit-code').val().trim(),
				department: $('#etwa-client-edit-department').val().trim() || 'General',
				is_active: $('#etwa-client-edit-active').is(':checked'),
			};

			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api(`clients/${id}`, {
				method: 'PUT',
				body: JSON.stringify(payload),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.clientUpdated || 'Client updated.');
					location.reload();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-clients-body').on('click', '.etwa-deactivate-client', function () {
			const id = $(this).closest('tr').data('id');
			ui.confirm(
				cfg.i18n?.confirmDeactivateClient || 'Deactivate this client? It will be hidden from the timesheet dropdown.',
				cfg.i18n?.deactivate || 'Deactivate'
			).then((ok) => {
				if (!ok) return;
				api(`clients/${id}/deactivate`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.clientDeactivated || 'Client deactivated.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-clients-body').on('click', '.etwa-delete-client', function () {
			const id = $(this).closest('tr').data('id');
			ui.confirm(
				cfg.i18n?.confirmDeleteClient || 'Permanently delete this client? This cannot be undone.',
				cfg.i18n?.delete || 'Delete'
			).then((ok) => {
				if (!ok) return;
				api(`clients/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.clientDeleted || 'Client deleted.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$modal.on('click', '.etwa-modal-close', closeModal);
		$modal.on('click', function (e) {
			if (e.target === this) closeModal();
		});
	});
})(jQuery);
