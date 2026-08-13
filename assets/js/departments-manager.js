(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	if (!$('#etwa-departments-app').length || !cfg.canManageDepartments) return;

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	let departments = [];
	let users = [];

	function loadUsers() {
		return api('vacations/employees?per_page=100').then((res) => {
			if (res.success) users = res.data?.items || [];
		});
	}

	function headOptions(selected) {
		let html = `<option value="">${cfg.i18n?.noDepartmentHead || 'No Department Head'}</option>`;
		users.forEach((u) => {
			html += `<option value="${u.id}"${String(selected) === String(u.id) ? ' selected' : ''}>${u.display_name}</option>`;
		});
		return html;
	}

	function renderRows() {
		if (!departments.length) {
			return `<tr><td colspan="7" class="etwa-empty-cell">${cfg.i18n?.noDepartments || 'No departments yet.'}</td></tr>`;
		}
		return departments
			.map(
				(d) => `<tr data-id="${d.id}">
			<td><strong>${d.name}</strong>${d.description ? `<div class="etwa-muted">${d.description}</div>` : ''}</td>
			<td>${d.head_name || `<span class="etwa-muted">${cfg.i18n?.noDepartmentHead || 'No Department Head'}</span>`}</td>
			<td>${d.employee_count || 0}</td>
			<td>${d.approval_enabled ? cfg.i18n?.yes || 'Yes' : cfg.i18n?.no || 'No'}</td>
			<td>${d.can_allocate_deliverables ? cfg.i18n?.yes || 'Yes' : cfg.i18n?.no || 'No'}</td>
			<td>${d.status_label || d.status}</td>
			<td class="etwa-actions-cell">
				<button type="button" class="etwa-link etwa-edit-dept">${cfg.i18n?.edit || 'Edit'}</button>
				<button type="button" class="etwa-link etwa-delete-dept">${cfg.i18n?.delete || 'Delete'}</button>
			</td>
		</tr>`
			)
			.join('');
	}

	const $modal = $('#etwa-department-modal');

	function openModal(dept) {
		$('#etwa-dept-id').val(dept?.id || '');
		$('#etwa-dept-name').val(dept?.name || '');
		$('#etwa-dept-description').val(dept?.description || '');
		$('#etwa-dept-head').html(headOptions(dept?.head_user_id || ''));
		$('#etwa-dept-approval').prop('checked', dept ? !!dept.approval_enabled : true);
		$('#etwa-dept-deliverables').prop('checked', dept ? !!dept.can_allocate_deliverables : false);
		$('#etwa-dept-status').val(dept?.status || 'active');
		$('#etwa-department-modal-title').text(dept ? cfg.i18n?.editDepartment || 'Edit Department' : cfg.i18n?.addDepartment || 'Add Department');
		$modal.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModal() {
		$modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	function loadDepartments() {
		$('#etwa-departments-body').html('<tr><td colspan="6"><div class="etwa-skeleton" style="height:40px"></div></td></tr>');
		api('departments')
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				departments = res.data?.items || [];
				$('#etwa-departments-body').html(renderRows());
			})
			.catch((err) => {
				$('#etwa-departments-body').html(`<tr><td colspan="6" class="etwa-empty-cell">${err.message}</td></tr>`);
			});
	}

	$(function () {
		loadUsers().finally(loadDepartments);

		$('#etwa-add-department').on('click', () => openModal(null));
		$modal.on('click', '.etwa-modal-close', closeModal);
		$modal.on('click', function (e) {
			if (e.target === this) closeModal();
		});

		$('#etwa-departments-body').on('click', '.etwa-edit-dept', function () {
			const id = $(this).closest('tr').data('id');
			const dept = departments.find((d) => d.id === id);
			if (dept) openModal(dept);
		});

		$('#etwa-departments-body').on('click', '.etwa-delete-dept', function () {
			const id = $(this).closest('tr').data('id');
			const dept = departments.find((d) => d.id === id);
			if (!dept || !window.confirm((cfg.i18n?.confirmDeleteDepartment || 'Delete department "%s"?').replace('%s', dept.name))) return;
			api(`departments/${id}`, { method: 'DELETE' })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.departmentDeleted || 'Department deleted.');
					loadDepartments();
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-department-form').on('submit', function (e) {
			e.preventDefault();
			const id = $('#etwa-dept-id').val();
			const payload = {
				name: $('#etwa-dept-name').val().trim(),
				description: $('#etwa-dept-description').val().trim(),
				head_user_id: $('#etwa-dept-head').val() || null,
				approval_enabled: $('#etwa-dept-approval').is(':checked') ? 1 : 0,
				can_allocate_deliverables: $('#etwa-dept-deliverables').is(':checked') ? 1 : 0,
				status: $('#etwa-dept-status').val(),
			};
			const method = id ? 'PUT' : 'POST';
			const path = id ? `departments/${id}` : 'departments';
			api(path, { method, body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.departmentSaved || 'Department saved.');
					closeModal();
					loadDepartments();
				})
				.catch((err) => ui.error(err.message));
		});
	});
})(jQuery);
