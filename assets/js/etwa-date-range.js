(function () {
	'use strict';

	const cfg = () => window.etwaApp || {};
	const ui = () => window.etwaUI || {};

	function defaultRange() {
		const today = new Date();
		const from = new Date(today.getFullYear(), today.getMonth(), 1);
		return {
			date_from: from.toISOString().slice(0, 10),
			date_to: today.toISOString().slice(0, 10),
		};
	}

	function buildFields(options) {
		const strings = cfg().i18n || {};
		const defaults = defaultRange();
		const fields = options.fields || {};
		let html = '';

		if (fields.employee) {
			const required = fields.employee.required ? ' required' : '';
			html += `
				<label class="etwa-analytics-field">
					<span>${strings.employee || 'Employee'}</span>
					<select class="etwa-input etwa-date-range-employee"${required}>
						<option value="">${strings.selectEmployee || 'Please select an employee.'}</option>
					</select>
				</label>`;
		}

		if (fields.client) {
			const required = fields.client.required ? ' required' : '';
			const readonly = fields.client.readonly ? ' disabled' : '';
			html += `
				<label class="etwa-analytics-field">
					<span>${strings.client || 'Client'}</span>
					<select class="etwa-input etwa-date-range-client"${required}${readonly}>
						<option value="">${strings.allClients || 'All Clients'}</option>
					</select>
				</label>`;
		}

		if (fields.parent_project) {
			const required = fields.parent_project.required ? ' required' : '';
			html += `
				<label class="etwa-analytics-field">
					<span>${strings.parentProject || 'Parent Project'}</span>
					<select class="etwa-input etwa-date-range-parent-project"${required}>
						<option value="">${strings.selectParentProject || 'Please select a parent project.'}</option>
					</select>
				</label>`;
		}

		if (fields.prospect) {
			const required = fields.prospect.required ? ' required' : '';
			html += `
				<label class="etwa-analytics-field">
					<span>${strings.prospect || 'Prospect'}</span>
					<select class="etwa-input etwa-date-range-prospect"${required}>
						<option value="">${strings.selectProspect || 'Please select a prospect.'}</option>
					</select>
				</label>`;
		}

		html += `
			<label class="etwa-analytics-field">
				<span>${strings.startDate || 'Start Date'}</span>
				<input type="date" class="etwa-input etwa-date-range-from" value="${options.date_from || defaults.date_from}" required />
			</label>
			<label class="etwa-analytics-field">
				<span>${strings.endDate || 'End Date'}</span>
				<input type="date" class="etwa-input etwa-date-range-to" value="${options.date_to || defaults.date_to}" required />
			</label>`;

		return html;
	}

	function populateSelect(select, items, selectedId, placeholderText) {
		if (!select) return;

		select.innerHTML = '';

		const placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.textContent =
			placeholderText || cfg().i18n?.selectEmployee || 'Please select…';
		select.appendChild(placeholder);

		(items || []).forEach((item) => {
			const opt = document.createElement('option');
			opt.value = String(item.id);
			opt.textContent = item.display_name || item.name || item.code || item.id;
			if (selectedId && String(selectedId) === String(item.id)) {
				opt.selected = true;
			}
			select.appendChild(opt);
		});
	}

	/**
	 * Unified date-range export modal.
	 *
	 * @param {object} options
	 * @param {string} options.title
	 * @param {string} [options.description]
	 * @param {string} [options.date_from]
	 * @param {string} [options.date_to]
	 * @param {number|string} [options.employee_id]
	 * @param {number|string} [options.client_id]
	 * @param {object} [options.fields] employee/client field config
	 * @param {object} [options.filters] preloaded employees/clients arrays
	 * @returns {Promise<object|null>}
	 */
	function promptDateRangeExport(options = {}) {
		const strings = cfg().i18n || {};

		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.className = 'etwa-confirm-overlay etwa-app etwa-date-range-overlay';
			overlay.innerHTML = `
				<div class="etwa-confirm-dialog etwa-date-range-dialog" role="dialog" aria-modal="true">
					<h3 class="etwa-confirm-title"></h3>
					<p class="etwa-confirm-message etwa-date-range-description"></p>
					<div class="etwa-analytics-filter-fields etwa-date-range-fields"></div>
					<div class="etwa-confirm-actions">
						<button type="button" class="etwa-btn etwa-btn-secondary etwa-date-range-cancel"></button>
						<button type="button" class="etwa-btn etwa-btn-primary etwa-date-range-export"></button>
					</div>
				</div>`;

			overlay.querySelector('.etwa-confirm-title').textContent = options.title || strings.exportReport || 'Export Report';
			const desc = overlay.querySelector('.etwa-date-range-description');
			if (options.description) {
				desc.textContent = options.description;
			} else {
				desc.hidden = true;
			}

			overlay.querySelector('.etwa-date-range-fields').innerHTML = buildFields(options);
			overlay.querySelector('.etwa-date-range-cancel').textContent = strings.cancel || 'Cancel';
			overlay.querySelector('.etwa-date-range-export').textContent = strings.exportToExcel || 'Export to Excel';

			const portalRoot = document.getElementById('etwa-portal-root');
			if (portalRoot && portalRoot.classList.contains('etwa-dark')) {
				overlay.classList.add('etwa-dark');
			}

			const employeeSelect = overlay.querySelector('.etwa-date-range-employee');
			const clientSelect = overlay.querySelector('.etwa-date-range-client');
			const parentProjectSelect = overlay.querySelector('.etwa-date-range-parent-project');
			const prospectSelect = overlay.querySelector('.etwa-date-range-prospect');
			const filters = options.filters || {};

			if (employeeSelect) {
				populateSelect(
					employeeSelect,
					filters.employees,
					options.employee_id,
					strings.selectEmployee || 'Please select an employee.'
				);
			}
			if (clientSelect) {
				let clients = filters.clients || [];
				if (options.client_id && options.client_name) {
					const exists = clients.some((c) => String(c.id) === String(options.client_id));
					if (!exists) {
						clients = [{ id: options.client_id, name: options.client_name }, ...clients];
					}
				}
				populateSelect(
					clientSelect,
					clients,
					options.client_id,
					strings.allClients || 'All Clients'
				);
				if (options.fields?.client?.readonly && options.client_id) {
					clientSelect.value = String(options.client_id);
				}
			}
			if (parentProjectSelect) {
				populateSelect(
					parentProjectSelect,
					filters.parent_projects,
					options.parent_project_id,
					strings.selectParentProject || 'Please select a parent project.'
				);
			}
			if (prospectSelect) {
				populateSelect(
					prospectSelect,
					filters.prospects,
					options.prospect_id,
					strings.selectProspect || 'Please select a prospect.'
				);
			}

			const close = (result) => {
				overlay.classList.remove('is-visible');
				setTimeout(() => overlay.remove(), 180);
				resolve(result);
			};

			overlay.querySelector('.etwa-date-range-cancel').addEventListener('click', () => close(null));
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) close(null);
			});

			overlay.querySelector('.etwa-date-range-export').addEventListener('click', () => {
				const from = overlay.querySelector('.etwa-date-range-from')?.value || '';
				const to = overlay.querySelector('.etwa-date-range-to')?.value || '';

				if (!from || !to) {
					ui().error(strings.invalidDateRange || 'Please select a valid date range.');
					return;
				}
				if (from > to) {
					ui().error(strings.invalidDateRange || 'Start date must be on or before end date.');
					return;
				}

				const payload = {
					date_from: from,
					date_to: to,
					preset: 'custom',
				};

				if (employeeSelect) {
					const userId = parseInt(employeeSelect.value || '0', 10);
					if (options.fields?.employee?.required && !userId) {
						ui().error(strings.selectEmployee || 'Please select an employee.');
						return;
					}
					if (userId) payload.user_id = userId;
				}

				if (clientSelect && !clientSelect.disabled) {
					const clientId = parseInt(clientSelect.value || '0', 10);
					if (options.fields?.client?.required && !clientId) {
						ui().error(strings.selectClient || 'Please select a client.');
						return;
					}
					if (clientId) payload.client_id = clientId;
				} else if (options.client_id) {
					payload.client_id = parseInt(String(options.client_id), 10);
				}

				if (parentProjectSelect) {
					const parentProjectId = parseInt(parentProjectSelect.value || '0', 10);
					if (options.fields?.parent_project?.required && !parentProjectId) {
						ui().error(strings.selectParentProject || 'Please select a parent project.');
						return;
					}
					if (parentProjectId) payload.parent_project_id = parentProjectId;
				}

				if (prospectSelect) {
					const prospectId = parseInt(prospectSelect.value || '0', 10);
					if (options.fields?.prospect?.required && !prospectId) {
						ui().error(strings.selectProspect || 'Please select a prospect.');
						return;
					}
					if (prospectId) payload.prospect_id = prospectId;
				}

				close(payload);
			});

			document.body.appendChild(overlay);
			requestAnimationFrame(() => overlay.classList.add('is-visible'));
			overlay.querySelector('.etwa-date-range-from')?.focus();
		});
	}

	let filtersPromise = null;

	function mapEmployeesResponse(res) {
		if (!res?.success) return [];
		const items = res.data?.items || res.data?.employees || [];
		return items.map((emp) => ({
			id: emp.id,
			display_name: emp.display_name || emp.name || `#${emp.id}`,
		}));
	}

	function mapClientsResponse(res) {
		if (!res?.success) return [];
		const items = Array.isArray(res.data) ? res.data : res.data?.clients || res.data?.items || [];
		return items
			.filter((client) => String(client.code || '').toUpperCase() !== 'BREAK')
			.map((client) => ({
				id: client.id,
				name: client.name,
				code: client.code,
			}));
	}

	function loadFilters(force = false) {
		if (filtersPromise && !force) return filtersPromise;

		const api = window.etwaAPI?.api;
		if (!api) {
			return Promise.resolve({ employees: [], clients: [], departments: [], parent_projects: [], prospects: [] });
		}

		filtersPromise = api('reports/filters')
			.then((res) => {
				if (res.success && res.data) {
					const employees = res.data.employees || [];
					const clients = res.data.clients || [];
					const departments = res.data.departments || [];
					const parent_projects = res.data.parent_projects || [];
					const prospects = res.data.prospects || [];
					if (employees.length || clients.length || departments.length || parent_projects.length || prospects.length) {
						return { employees, clients, departments, parent_projects, prospects };
					}
				}
				return Promise.all([
					api('employees?per_page=100&page=1'),
					api('clients'),
				]).then(([empRes, clientRes]) => ({
					employees: mapEmployeesResponse(empRes),
					clients: mapClientsResponse(clientRes),
					departments: [],
					parent_projects: [],
					prospects: [],
				}));
			})
			.catch(() =>
				Promise.all([api('employees?per_page=100&page=1'), api('clients')]).then(
					([empRes, clientRes]) => ({
						employees: mapEmployeesResponse(empRes),
						clients: mapClientsResponse(clientRes),
						departments: [],
						parent_projects: [],
						prospects: [],
					})
				)
			)
			.catch(() => {
				filtersPromise = null;
				return { employees: [], clients: [], departments: [], parent_projects: [], prospects: [] };
			});

		return filtersPromise;
	}

	function exportReport(reportType, params, button) {
		const api = window.etwaAPI?.api;
		const strings = cfg().i18n || {};
		if (!api) return Promise.reject(new Error(strings.error || 'Something went wrong.'));

		ui().setButtonLoading(button, true, strings.exporting || 'Exporting…');

		return api(`reports/export/${reportType}`, {
			method: 'POST',
			body: JSON.stringify(params),
		})
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				if (res.data?.file_url) {
					window.location.href = res.data.file_url;
					ui().success(strings.exportReady || 'Export ready.');
				}
				return res;
			})
			.finally(() => ui().setButtonLoading(button, false));
	}

	window.etwaDateRange = {
		promptDateRangeExport,
		loadFilters,
		exportReport,
		defaultRange,
	};
})();
