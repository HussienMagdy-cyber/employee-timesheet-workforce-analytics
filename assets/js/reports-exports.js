(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const dateRange = window.etwaDateRange || {};
	const api = window.etwaAPI?.api;

	if (!cfg.canExport || !$('#etwa-reports-app').length) {
		return;
	}

	const reportTitles = {
		employee_timesheet: cfg.i18n?.employeeTimesheetReport || 'Employee Timesheet Report',
		client: cfg.i18n?.clientReport || 'Client Report',
		client_labor_cost: cfg.i18n?.clientLaborCostReport || 'Client Labor Cost Report',
		employee_hours_by_client: cfg.i18n?.employeeHoursByClientReport || 'Employee Hours by Client',
		all_clients_comparison: cfg.i18n?.allClientsComparisonReport || 'All Clients Comparison',
	};

	const reportFields = {
		employee_timesheet: {
			employee: { required: true },
			client: { required: false },
		},
		client: {
			client: { required: true },
		},
		client_labor_cost: {
			client: { required: true },
		},
		employee_hours_by_client: {
			employee: { required: false },
			client: { required: false },
		},
	};

	function openExport(reportType, button) {
		const fields = reportFields[reportType] || {};
		dateRange.loadFilters().then((filters) => {
			dateRange
				.promptDateRangeExport({
					title: reportTitles[reportType] || 'Export Report',
					fields,
					filters,
				})
				.then((params) => {
					if (!params) return;
					return dateRange.exportReport(reportType, params, button);
				})
				.catch((err) => ui.error(err.message));
		});
	}

	$('.etwa-report-export-btn').on('click', function () {
		const reportType = $(this).closest('[data-report]').data('report');
		openExport(reportType, this);
	});

	/* ——— All Clients Comparison ——— */

	if (!cfg.canViewAnalytics || !$('#etwa-all-clients-comparison').length || !api) {
		return;
	}

	const strings = cfg.i18n || {};
	const showLaborCost = !!cfg.canViewLaborCost;
	let comparisonData = null;
	let sortKey = 'total_hours';
	let sortDir = 'desc';
	const chartInstances = [];

	function chartColors() {
		const style = getComputedStyle(document.documentElement);
		return {
			primary: style.getPropertyValue('--etwa-primary').trim() || '#4f46e5',
			amber: style.getPropertyValue('--etwa-warning').trim() || '#f59e0b',
			muted: style.getPropertyValue('--etwa-muted').trim() || '#94a3b8',
			palette: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#64748b'],
		};
	}

	function destroyCharts() {
		while (chartInstances.length) {
			const chart = chartInstances.pop();
			if (chart) chart.destroy();
		}
	}

	function defaultComparisonRange() {
		const range = dateRange.defaultRange?.() || {};
		$('#etwa-comparison-from').val(range.date_from || '');
		$('#etwa-comparison-to').val(range.date_to || '');
	}

	function populateComparisonFilters() {
		return dateRange.loadFilters().then((filters) => {
			const deptSelect = document.getElementById('etwa-comparison-department');
			const empSelect = document.getElementById('etwa-comparison-employee');

			if (deptSelect) {
				deptSelect.innerHTML = `<option value="">${strings.allDepartments || 'All Departments'}</option>`;
				(filters.departments || []).forEach((dept) => {
					const opt = document.createElement('option');
					opt.value = String(dept.id);
					opt.textContent = dept.name;
					deptSelect.appendChild(opt);
				});
			}

			if (empSelect) {
				empSelect.innerHTML = `<option value="">${strings.allEmployees || 'All Employees'}</option>`;
				(filters.employees || []).forEach((emp) => {
					const opt = document.createElement('option');
					opt.value = String(emp.id);
					opt.textContent = emp.display_name;
					empSelect.appendChild(opt);
				});
			}
		});
	}

	function currentComparisonParams() {
		const params = {
			date_from: $('#etwa-comparison-from').val() || '',
			date_to: $('#etwa-comparison-to').val() || '',
			preset: 'custom',
		};
		const deptId = parseInt($('#etwa-comparison-department').val() || '0', 10);
		const userId = parseInt($('#etwa-comparison-employee').val() || '0', 10);
		if (deptId) params.department_id = deptId;
		if (userId) params.user_id = userId;
		return params;
	}

	function formatHours(value) {
		return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 1 });
	}

	function formatCost(value) {
		return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function formatPct(value) {
		return `${Number(value || 0).toFixed(1)}%`;
	}

	function sortedRows() {
		if (!comparisonData?.rows) return [];
		const rows = [...comparisonData.rows];
		rows.sort((a, b) => {
			const av = a[sortKey] ?? 0;
			const bv = b[sortKey] ?? 0;
			if (av === bv) return 0;
			return sortDir === 'asc' ? (av > bv ? 1 : -1) : av < bv ? 1 : -1;
		});
		return rows;
	}

	function updateSortHeaders() {
		$('#etwa-comparison-table th.etwa-sortable').each(function () {
			const key = $(this).data('sort');
			$(this).removeClass('is-sorted is-asc is-desc');
			if (key === sortKey) {
				$(this).addClass('is-sorted').addClass(sortDir === 'asc' ? 'is-asc' : 'is-desc');
			}
		});
	}

	function renderComparisonTable() {
		const rows = sortedRows();
		const $body = $('#etwa-comparison-table-body');
		const $foot = $('#etwa-comparison-table-foot');
		$body.empty();
		$foot.empty();

		if (!rows.length) {
			const colSpan = showLaborCost ? 8 : 6;
			$body.append(
				`<tr><td colspan="${colSpan}" class="etwa-empty-cell">${strings.noComparisonData || 'No client activity for the selected filters.'}</td></tr>`
			);
			return;
		}

		rows.forEach((row) => {
			const cells = [
				`<td><strong>${escapeHtml(row.client_name)}</strong></td>`,
				`<td>${row.employee_count}</td>`,
				`<td>${formatHours(row.regular_hours)}</td>`,
				`<td>${formatHours(row.overtime_hours)}</td>`,
				`<td>${formatHours(row.total_hours)}</td>`,
			];
			if (showLaborCost) {
				cells.push(`<td>${formatCost(row.labor_cost_egp)}</td>`);
			}
			cells.push(`<td>${formatPct(row.pct_total_hours)}</td>`);
			if (showLaborCost) {
				cells.push(`<td>${formatPct(row.pct_labor_cost)}</td>`);
			}
			$body.append(`<tr>${cells.join('')}</tr>`);
		});

		const summary = comparisonData.summary || {};
		const footCells = [
			`<td>${strings.grandTotal || 'Grand Total'}</td>`,
			`<td>${summary.total_employees ?? 0}</td>`,
			`<td>${formatHours(summary.total_regular_hours)}</td>`,
			`<td>${formatHours(summary.total_overtime_hours)}</td>`,
			`<td>${formatHours(summary.total_hours)}</td>`,
		];
		if (showLaborCost) {
			footCells.push(`<td>${formatCost(summary.total_labor_cost_egp)}</td>`);
		}
		footCells.push('<td>100%</td>');
		if (showLaborCost) {
			footCells.push('<td>100%</td>');
		}
		$foot.append(`<tr>${footCells.join('')}</tr>`);
		updateSortHeaders();
	}

	function escapeHtml(text) {
		return String(text || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function renderAllocations() {
		const $wrap = $('#etwa-comparison-allocations-body');
		$wrap.empty();

		const blocks = comparisonData?.employee_allocations || [];
		if (!blocks.length) {
			$wrap.append(`<p class="etwa-muted">${strings.noComparisonData || 'No client activity for the selected filters.'}</p>`);
			return;
		}

		blocks.forEach((block, index) => {
			const panelId = `etwa-allocation-panel-${block.client_id}`;
			const employees = block.employees || [];
			const headHours = employees.reduce((sum, emp) => sum + (emp.total_hours || 0), 0);
			const headCost = showLaborCost
				? employees.reduce((sum, emp) => sum + (emp.labor_cost_egp || 0), 0)
				: 0;

			let tableHead = `
				<tr>
					<th>${strings.employee || 'Employee'}</th>
					<th>${strings.regularHours || 'Regular Hours'}</th>
					<th>${strings.overtimeHours || 'Overtime Hours'}</th>
					<th>${strings.totalHours || 'Total Hours'}</th>`;
			if (showLaborCost) {
				tableHead += `<th>${strings.laborCost || 'Labor Cost'}</th>`;
			}
			tableHead += '</tr>';

			let tableRows = '';
			employees.forEach((emp) => {
				tableRows += `
					<tr>
						<td>${escapeHtml(emp.employee_name)}</td>
						<td>${formatHours(emp.regular_hours)}</td>
						<td>${formatHours(emp.overtime_hours)}</td>
						<td>${formatHours(emp.total_hours)}</td>`;
				if (showLaborCost) {
					tableRows += `<td>${formatCost(emp.labor_cost_egp)}</td>`;
				}
				tableRows += '</tr>';
			});

			const meta = showLaborCost
				? `${formatHours(headHours)} · ${formatCost(headCost)}`
				: formatHours(headHours);

			$wrap.append(`
				<div class="etwa-comparison-accordion">
					<button type="button" class="etwa-comparison-accordion-toggle" aria-expanded="false" aria-controls="${panelId}">
						<span><strong>${escapeHtml(block.client_name)}</strong> <span class="etwa-muted">(${employees.length} ${strings.employees || 'Employees'})</span></span>
						<span class="etwa-muted">${meta}</span>
					</button>
					<div class="etwa-comparison-accordion-panel" id="${panelId}" hidden>
						<div class="etwa-table-wrap">
							<table class="etwa-table">
								<thead>${tableHead}</thead>
								<tbody>${tableRows}</tbody>
							</table>
						</div>
					</div>
				</div>`);
		});
	}

	function buildComparisonCharts() {
		if (typeof Chart === 'undefined' || !comparisonData?.rows?.length) {
			destroyCharts();
			return;
		}

		destroyCharts();
		const colors = chartColors();
		const rows = sortedRows();
		const labels = rows.map((r) => r.client_name);
		const barDefaults = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: {
				x: { ticks: { maxRotation: 45, minRotation: 0 } },
				y: { beginAtZero: true },
			},
		};

		const hoursEl = document.getElementById('etwa-comparison-chart-hours');
		if (hoursEl) {
			chartInstances.push(
				new Chart(hoursEl, {
					type: 'bar',
					data: {
						labels,
						datasets: [
							{
								label: strings.totalHours || 'Total Hours',
								data: rows.map((r) => r.total_hours),
								backgroundColor: colors.primary + 'cc',
								borderRadius: 4,
							},
						],
					},
					options: barDefaults,
				})
			);
		}

		if (showLaborCost) {
			const costEl = document.getElementById('etwa-comparison-chart-cost');
			if (costEl) {
				chartInstances.push(
					new Chart(costEl, {
						type: 'bar',
						data: {
							labels,
							datasets: [
								{
									label: strings.laborCost || 'Labor Cost',
									data: rows.map((r) => r.labor_cost_egp || 0),
									backgroundColor: colors.amber + 'cc',
									borderRadius: 4,
								},
							],
						},
						options: barDefaults,
					})
				);
			}
		}

		const empEl = document.getElementById('etwa-comparison-chart-employees');
		if (empEl) {
			chartInstances.push(
				new Chart(empEl, {
					type: 'bar',
					data: {
						labels,
						datasets: [
							{
								label: strings.employees || 'Employees',
								data: rows.map((r) => r.employee_count),
								backgroundColor: colors.palette,
								borderRadius: 4,
							},
						],
					},
					options: barDefaults,
				})
			);
		}
	}

	function renderComparison() {
		renderComparisonTable();
		renderAllocations();
		buildComparisonCharts();
		$('#etwa-comparison-results').prop('hidden', false);
		$('#etwa-comparison-export').prop('hidden', !comparisonData?.rows?.length);
	}

	function generateComparison(button) {
		const params = currentComparisonParams();
		if (!params.date_from || !params.date_to) {
			ui.error(strings.invalidDateRange || 'Please select a valid date range.');
			return Promise.resolve();
		}
		if (params.date_from > params.date_to) {
			ui.error(strings.invalidDateRange || 'Start date must be on or before end date.');
			return Promise.resolve();
		}

		ui.setButtonLoading(button, true, strings.generating || 'Generating…');

		const query = new URLSearchParams(params).toString();
		return api(`reports/all-clients-comparison?${query}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				comparisonData = res.data;
				sortKey = 'total_hours';
				sortDir = 'desc';
				renderComparison();
			})
			.catch((err) => ui.error(err.message))
			.finally(() => ui.setButtonLoading(button, false));
	}

	$('#etwa-comparison-generate').on('click', function () {
		generateComparison(this);
	});

	$('#etwa-comparison-export').on('click', function () {
		const params = currentComparisonParams();
		dateRange.exportReport('all_clients_comparison', params, this).catch((err) => ui.error(err.message));
	});

	$('#etwa-comparison-table').on('click', 'th.etwa-sortable', function () {
		const key = $(this).data('sort');
		if (!key || !comparisonData) return;
		if (sortKey === key) {
			sortDir = sortDir === 'asc' ? 'desc' : 'asc';
		} else {
			sortKey = key;
			sortDir = 'desc';
		}
		renderComparisonTable();
		buildComparisonCharts();
	});

	$('#etwa-comparison-allocations-body').on('click', '.etwa-comparison-accordion-toggle', function () {
		const expanded = this.getAttribute('aria-expanded') === 'true';
		const panelId = this.getAttribute('aria-controls');
		const panel = panelId ? document.getElementById(panelId) : null;
		this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		if (panel) panel.hidden = expanded;
	});

	defaultComparisonRange();
	populateComparisonFilters();

	if (window.location.hash === '#etwa-all-clients-comparison') {
		const section = document.getElementById('etwa-all-clients-comparison');
		if (section) {
			section.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}
})(jQuery);
