(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	if (!cfg.canManageEmployees && !cfg.canViewLaborCost) {
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

	let page = 1;
	let searchTimer = null;
	let currentSearch = '';
	let departments = [];
	let profileController = null;
	let editingEmployeeId = null;

	function debounce(fn, ms) {
		return function (...args) {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(() => fn.apply(this, args), ms);
		};
	}

	function getEmployeeIdFromUrl() {
		const params = new URLSearchParams(window.location.search);
		const id = parseInt(params.get('employee_id') || '0', 10);
		return id > 0 ? id : null;
	}

	function setEmployeeIdInUrl(id) {
		const url = new URL(window.location.href);
		if (id) {
			url.searchParams.set('employee_id', String(id));
		} else {
			url.searchParams.delete('employee_id');
		}
		window.history.replaceState({}, '', url.toString());
	}

	function showList() {
		$('#etwa-employees-list-wrap').removeAttr('hidden');
		$('#etwa-add-employee').removeAttr('hidden');
		if (profileController) profileController.hide();
		setEmployeeIdInUrl(null);
	}

	function showProfile(employeeId) {
		$('#etwa-employees-list-wrap').attr('hidden', 'hidden');
		$('#etwa-add-employee').attr('hidden', 'hidden');
		setEmployeeIdInUrl(employeeId);
		if (!profileController) {
			profileController = window.etwaEmployeeProfile.init({
				mode: 'embedded',
				userId: employeeId,
				showBack: true,
				onBack: showList,
			});
		}
		profileController.loadProfile(employeeId);
		profileController.show();
	}

	function departmentOptions(selected) {
		let html = `<option value="">${cfg.i18n?.selectDepartment || '— No department —'}</option>`;
		departments.forEach((d) => {
			if (!d || !d.id || !d.name) return;
			html += `<option value="${d.id}"${String(selected) === String(d.id) ? ' selected' : ''}>${d.name}</option>`;
		});
		return html;
	}

	function syncCreateModalDepartments() {
		const $select = $('#etwa-employee-department');
		if (!$select.length || !departments.length) return;
		const selected = $select.val() || '';
		$select.html(departmentOptions(selected));
	}

	function syncEditModalDepartments(selected) {
		const $select = $('#etwa-employee-edit-department');
		if (!$select.length) return;
		$select.html(departmentOptions(selected || ''));
	}

	function renderActionLinks(emp) {
		const editLabel = cfg.i18n?.edit || 'Edit';
		const links = [
			`<button type="button" class="etwa-link etwa-employee-edit-link" data-id="${emp.id}">${editLabel}</button>`,
		];

		if (cfg.canManageEmployees && emp.id !== cfg.userId) {
			if (emp.is_active) {
				links.push(
					`<button type="button" class="etwa-link etwa-employee-deactivate-link" data-id="${emp.id}">${cfg.i18n?.deactivate || 'Deactivate'}</button>`
				);
			} else {
				links.push(
					`<button type="button" class="etwa-link etwa-employee-reactivate-link" data-id="${emp.id}">${cfg.i18n?.reactivate || 'Reactivate'}</button>`
				);
			}
		}

		return links.join(' · ');
	}

	function renderRows(items) {
		if (!items.length) {
			return `<tr><td colspan="5" class="etwa-empty-cell">${cfg.i18n?.noEmployees || 'No employees found.'}</td></tr>`;
		}

		const notSet = cfg.i18n?.notSet || 'Not set';

		return items
			.map((emp) => {
				const statusBadge = emp.is_active
					? `<span class="etwa-status etwa-status-approved">${cfg.i18n?.active || 'Active'}</span>`
					: `<span class="etwa-status etwa-status-rejected">${cfg.i18n?.inactive || 'Inactive'}</span>`;
				return `
			<tr data-id="${emp.id}" data-role="${emp.role || ''}" data-department-id="${emp.department_id || ''}" data-hourly-cost="${emp.hourly_cost_egp ?? ''}">
				<td>
					<button type="button" class="etwa-link etwa-employee-profile-link" data-id="${emp.id}">
						<strong>${emp.display_name || ''}</strong>
					</button>
				</td>
				<td>${statusBadge}</td>
				<td>${emp.department_name || notSet}</td>
				<td>${emp.email || ''}</td>
				<td class="etwa-actions-cell">${renderActionLinks(emp)}</td>
			</tr>`;
			})
			.join('');
	}

	function renderPagination(total, perPage) {
		const pages = Math.max(1, Math.ceil(total / perPage));
		if (pages <= 1) {
			return '';
		}

		let html = '';
		for (let i = 1; i <= pages; i += 1) {
			html += `<button type="button" class="etwa-btn etwa-btn-secondary${i === page ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
		}
		return html;
	}

	function setEmploymentStatus(employeeId, status) {
		const isDeactivate = status === 'inactive';
		const msg = isDeactivate
			? cfg.i18n?.confirmDeactivate || 'Deactivate this employee? They will lose portal access.'
			: cfg.i18n?.confirmReactivate || 'Reactivate this employee?';
		const title = isDeactivate ? cfg.i18n?.deactivate || 'Deactivate' : cfg.i18n?.reactivate || 'Reactivate';

		return ui
			.confirm(msg, title)
			.then((ok) => {
				if (!ok) return;
				return api(`employees/${employeeId}/status`, {
					method: 'PUT',
					body: JSON.stringify({ status }),
				}).then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(
						isDeactivate
							? cfg.i18n?.employeeDeactivated || 'Employee deactivated.'
							: cfg.i18n?.employeeReactivated || 'Employee reactivated.'
					);
					loadEmployees();
				});
			});
	}

	function bindRowEvents() {
		$('.etwa-employee-profile-link')
			.off('click')
			.on('click', function () {
				const id = parseInt($(this).data('id'), 10);
				if (id) showProfile(id);
			});

		$('.etwa-employee-edit-link')
			.off('click')
			.on('click', function () {
				const id = parseInt($(this).data('id'), 10);
				const $row = $(this).closest('tr');
				if (!id) return;
				openEditModal({
					id,
					display_name: $row.find('.etwa-employee-profile-link strong').text().trim(),
					role: $row.data('role') || 'etwa_employee',
					department_id: $row.data('departmentId') || '',
					hourly_cost_egp: $row.data('hourlyCost'),
				});
			});

		$('.etwa-employee-deactivate-link')
			.off('click')
			.on('click', function () {
				const id = parseInt($(this).data('id'), 10);
				if (!id) return;
				setEmploymentStatus(id, 'inactive').catch((err) => ui.error(err.message));
			});

		$('.etwa-employee-reactivate-link')
			.off('click')
			.on('click', function () {
				const id = parseInt($(this).data('id'), 10);
				if (!id) return;
				setEmploymentStatus(id, 'active').catch((err) => ui.error(err.message));
			});
	}

	function loadDepartments() {
		return api('departments').then((res) => {
			if (!res.success) return;
			const items = res.data?.items;
			departments = Array.isArray(items) ? items : [];
			syncCreateModalDepartments();
		});
	}

	function loadEmployees() {
		const $body = $('#etwa-employees-body');
		const params = new URLSearchParams({
			page: String(page),
			per_page: '25',
		});
		if (currentSearch) {
			params.set('search', currentSearch);
		}
		if ($('#etwa-employees-show-inactive').is(':checked')) {
			params.set('include_inactive', '1');
		}

		$body.html(
			`<tr class="etwa-skeleton-row"><td colspan="5"><div class="etwa-skeleton" style="height:40px"></div></td></tr>`
		);

		api(`employees?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				$body.html(renderRows(data.items || []));
				$('#etwa-employees-count').text(
					(cfg.i18n?.employeesCount || '%1$d employees · page %2$d of %3$d')
						.replace('%1$d', String(data.total || 0))
						.replace('%2$d', String(data.page || 1))
						.replace(
							'%3$d',
							String(Math.max(1, Math.ceil((data.total || 0) / (data.per_page || 25))))
						)
				);
				$('#etwa-employees-pagination').html(
					renderPagination(data.total || 0, data.per_page || 25)
				);
				bindRowEvents();
			})
			.catch((err) => {
				$body.html(
					`<tr><td colspan="5" class="etwa-empty-cell">${err.message}</td></tr>`
				);
			});
	}

	const $modal = $('#etwa-employee-modal');
	const $editModal = $('#etwa-employee-edit-modal');

	function openCreateModal() {
		$('#etwa-employee-form')[0].reset();
		$('#etwa-employee-role').val('etwa_employee');
		syncCreateModalDepartments();
		$('#etwa-employee-department').val('');
		$('#etwa-employee-join-date').val(new Date().toISOString().slice(0, 10));
		$modal.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
		document.body.appendChild($modal[0]);
	}

	function closeCreateModal() {
		$modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	function openEditModal(employee) {
		editingEmployeeId = employee.id;
		$('#etwa-employee-edit-name').text(employee.display_name || '');
		$('#etwa-employee-edit-role').val(employee.role || 'etwa_employee');
		syncEditModalDepartments(employee.department_id || '');
		const $hourlyCost = $('#etwa-employee-edit-hourly-cost');
		if ($hourlyCost.length) {
			const cost = employee.hourly_cost_egp;
			$hourlyCost.val(cost === null || cost === undefined || cost === '' ? '0' : cost);
		}
		$editModal.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
		document.body.appendChild($editModal[0]);
	}

	function closeEditModal() {
		editingEmployeeId = null;
		$editModal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	$(function () {
		if (!$('#etwa-employees-app').length) return;

		loadDepartments().finally(() => {
			const profileId = getEmployeeIdFromUrl();
			if (profileId) {
				showProfile(profileId);
			} else {
				loadEmployees();
			}
		});

		$('#etwa-add-employee').on('click', openCreateModal);
		$modal.on('click', '.etwa-modal-close', closeCreateModal);
		$modal.on('click', function (e) {
			if (e.target === this) closeCreateModal();
		});

		$editModal.on('click', '.etwa-modal-close', closeEditModal);
		$editModal.on('click', function (e) {
			if (e.target === this) closeEditModal();
		});

		$('#etwa-employee-edit-form').on('submit', function (e) {
			e.preventDefault();
			if (!editingEmployeeId) return;

			const $btn = $(this).find('button[type="submit"]');
			const payload = {
				role: $('#etwa-employee-edit-role').val(),
				department_id: $('#etwa-employee-edit-department').val() || null,
			};

			const $hourlyCost = $('#etwa-employee-edit-hourly-cost');
			if ($hourlyCost.length) {
				payload.hourly_cost_egp = $hourlyCost.val();
			}

			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving || 'Saving…');
			api(`employees/${editingEmployeeId}/assignment`, {
				method: 'PUT',
				body: JSON.stringify(payload),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeEditModal();
					ui.success(cfg.i18n?.employeeSaved || 'Employee updated.');
					loadEmployees();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-employee-form').on('submit', function (e) {
			e.preventDefault();
			const $btn = $(this).find('button[type="submit"]');
			const payload = {
				display_name: $('#etwa-employee-name').val().trim(),
				email: $('#etwa-employee-email').val().trim(),
				role: $('#etwa-employee-role').val(),
				department_id: $('#etwa-employee-department').val() || null,
				job_title: $('#etwa-employee-job-title').val().trim(),
				join_date: $('#etwa-employee-join-date').val(),
				birthday: $('#etwa-employee-birthday').val() || null,
			};

			ui.setButtonLoading($btn[0], true, cfg.i18n?.creating || 'Creating…');
			api('employees', { method: 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeCreateModal();
					if (res.data?.password_email_sent === false) {
						ui.error(cfg.i18n?.passwordEmailFailed || 'Employee created, but the password email could not be sent.');
					} else {
						ui.success(cfg.i18n?.employeeCreated || 'Employee created. Password setup email sent.');
					}
					loadEmployees();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-employees-search').on(
			'input',
			debounce(function () {
				currentSearch = $(this).val().trim();
				page = 1;
				loadEmployees();
			}, 350)
		);

		$('#etwa-employees-show-inactive').on('change', function () {
			page = 1;
			loadEmployees();
		});

		$('#etwa-employees-pagination').on('click', 'button[data-page]', function () {
			page = parseInt($(this).data('page'), 10) || 1;
			loadEmployees();
		});
	});
})(jQuery);
