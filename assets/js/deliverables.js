(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	const $app = $('#etwa-deliverables-app');
	if (!$app.length || typeof window.etwaAPI === 'undefined') {
		return;
	}

	const canManage = Number($app.attr('data-can-manage')) === 1 || !!cfg.canManageDeliverableScopes;
	const canAllocate = Number($app.attr('data-can-allocate')) === 1 || !!cfg.canAllocateDeliverables;
	const canManageTypes =
		Number($app.attr('data-can-manage-types')) === 1 || canManage || canAllocate || !!cfg.canManageDeliverableTypes;

	const state = {
		mode: $app.attr('data-mode') || 'dashboard',
		scopeId: parseInt($app.attr('data-scope-id'), 10) || 0,
		types: [],
		managedTypes: [],
		clients: [],
		scopes: [],
		scopeProjects: [],
		detail: null,
		allocations: [],
		currentAllocation: null,
		dashboard: null,
		trend: 'month',
		overTime: 'month',
		scopePerfTab: 'top',
		clientPerfTab: 'highest_completion',
	};

	const CHART_COLORS = {
		primary: '#4f46e5',
		primaryLight: 'rgba(79, 70, 229, 0.12)',
		success: '#059669',
		warning: '#d97706',
		danger: '#dc2626',
		palette: ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626', '#64748b'],
		grid: 'rgba(115, 115, 115, 0.2)',
		text: '#737373',
	};

	let chartInstances = [];

	function esc(str) {
		return $('<div>').text(str == null ? '' : String(str)).html();
	}

	function num(n) {
		const v = Number(n);
		return Number.isFinite(v) ? v : 0;
	}

	function pctBar(pct) {
		return Math.max(0, Math.min(100, num(pct)));
	}

	function today() {
		const d = new Date();
		const m = String(d.getMonth() + 1).padStart(2, '0');
		const day = String(d.getDate()).padStart(2, '0');
		return `${d.getFullYear()}-${m}-${day}`;
	}

	function openModal($modal) {
		if (!$modal.length) {
			return;
		}
		$modal.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModal($modal) {
		if (!$modal || !$modal.length) {
			$('.etwa-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
		} else {
			$modal.attr('hidden', 'hidden').attr('aria-hidden', 'true');
		}
		if (!$('.etwa-modal:not([hidden])').length) {
			$('body').removeClass('etwa-modal-open');
		}
	}

	function setLoading(on) {
		$app.toggleClass('is-loading', !!on);
	}

	function fail(resOrErr, fallback) {
		const message =
			(resOrErr && resOrErr.message) ||
			(resOrErr && resOrErr.message) ||
			(typeof resOrErr === 'string' ? resOrErr : '') ||
			fallback ||
			'Something went wrong.';
		if (ui.error) {
			ui.error(message);
		}
	}

	function ok(message) {
		if (ui.success) {
			ui.success(message);
		}
	}

	function btnLoading($btn, on, text) {
		if (ui.setButtonLoading) {
			ui.setButtonLoading($btn[0] || $btn, on, text);
		}
	}

	function setKpis($root, metrics, prefix) {
		if (!metrics || !$root.length) {
			return;
		}
		const valueAttr = prefix === 'scope' ? 'data-scope-kpi' : 'data-kpi';
		const barAttr = prefix === 'scope' ? 'data-scope-bar' : 'data-kpi-bar';
		$root.find(`[${valueAttr}]`).each(function () {
			const key = this.getAttribute(valueAttr);
			const val = metrics[key];
			if (key === 'completion_pct') {
				$(this).text(`${num(val).toFixed(1)}%`);
			} else if (['total_scopes', 'active_scopes', 'completed_scopes', 'scopes_with_remaining', 'overdue_scopes'].includes(key)) {
				$(this).text(String(Math.round(num(val))));
			} else {
				$(this).text(num(val).toFixed(2));
			}
		});
		$root.find(`[${barAttr}]`).each(function () {
			const key = this.getAttribute(barAttr);
			$(this).css('width', `${pctBar(metrics[key])}%`);
		});
	}

	function typeName(id) {
		const t = state.types.find((x) => Number(x.id) === Number(id));
		return t ? t.name : String(id || '—');
	}

	function loadTypes() {
		return api('deliverable-types?active=1')
			.then((res) => {
				if (!res.success) {
					fail(res, 'Could not load deliverable types.');
					state.types = [];
					return state.types;
				}
				state.types = res.data || [];
				const $typeFilter = $('#etwa-dlv-type-filter');
				$typeFilter.find('option:not(:first)').remove();
				state.types.forEach((t) => {
					$typeFilter.append(`<option value="${esc(t.id)}">${esc(t.name)}</option>`);
				});
				return state.types;
			})
			.catch((err) => {
				fail(err, 'Could not load deliverable types.');
				state.types = [];
				return state.types;
			});
	}

	function loadClients() {
		return api('clients')
			.then((res) => {
				if (!res.success) {
					fail(res, 'Could not load clients.');
					return [];
				}
				state.clients = res.data || [];
				const $filter = $('#etwa-dlv-client-filter');
				const $scope = $('#etwa-dlv-scope-client');
				$filter.find('option:not(:first)').remove();
				$scope.find('option:not(:first)').remove();
				state.clients.forEach((c) => {
					const opt = `<option value="${esc(c.id)}">${esc(c.name)}</option>`;
					$filter.append(opt);
					$scope.append(opt);
				});
				return state.clients;
			})
			.catch((err) => {
				fail(err, 'Could not load clients.');
				return [];
			});
	}

	function typeOptionsHtml(selected) {
		if (!state.types.length) {
			return '<option value="">No active types</option>';
		}
		return state.types
			.map((t) => {
				const sel = Number(selected) === Number(t.id) ? ' selected' : '';
				return `<option value="${esc(t.id)}"${sel}>${esc(t.name)}</option>`;
			})
			.join('');
	}

	function formatQty(n) {
		const v = num(n);
		return Number.isInteger(v) ? String(v) : v.toFixed(2).replace(/\.?0+$/, '');
	}

	function allocationSummaryText(a) {
		if (a.summary) {
			return a.summary;
		}
		const items = a.items || [];
		return items
			.map((item) => `${formatQty(item.quantity)} ${typeName(item.deliverable_type_id)}`)
			.join(', ');
	}

	function taskLabel(a) {
		return (a && a.task_name) || 'Untitled allocation';
	}

	function impactLabel(impact) {
		if (impact === 'replacement') {
			return 'Replacement';
		}
		return 'Normal';
	}

	function allocItemToward($row) {
		const q = num($row.find('[data-field="quantity"]').val());
		const impact = $row.find('[data-field="scope_impact"]').val();
		if (impact === 'replacement') {
			return q + num($row.find('[data-field="replacement_qty"]').val());
		}
		return q;
	}

	function itemsToward(items) {
		let toward = 0;
		(items || []).forEach((item) => {
			const q = num(item.quantity);
			if (item.scope_impact === 'replacement') {
				toward += q + num(item.replacement_qty);
			} else {
				toward += q;
			}
		});
		return toward;
	}

	function allocCapacityRemaining() {
		const metrics = state.detail && state.detail.metrics;
		if (!metrics) {
			return 0;
		}
		let capacity = num(metrics.remaining_qty);
		const editId = parseInt($('#etwa-dlv-alloc-edit-id').val(), 10) || 0;
		if (editId && state.currentAllocation && Number(state.currentAllocation.id) === editId) {
			capacity += itemsToward(state.currentAllocation.items);
		}
		return Math.max(0, capacity);
	}

	function allocItemRowHtml(item) {
		const data = item || {};
		let impact = data.scope_impact || 'normal';
		if (impact === 'extra') {
			impact = 'normal';
		}
		return `
			<div class="etwa-dlv-line-row etwa-dlv-alloc-item" data-alloc-item>
				<div class="etwa-dlv-alloc-item-main">
					<label>
						<span>Deliverable Type</span>
						<select class="etwa-input" data-field="deliverable_type_id" required>${typeOptionsHtml(data.deliverable_type_id)}</select>
					</label>
					<label>
						<span>Quantity</span>
						<input type="number" class="etwa-input" data-field="quantity" min="0.01" step="0.01" required value="${esc(data.quantity != null ? data.quantity : '')}" />
					</label>
					<label>
						<span>Scope Impact</span>
						<select class="etwa-input" data-field="scope_impact">
							<option value="normal"${impact === 'normal' ? ' selected' : ''}>Normal</option>
							<option value="replacement"${impact === 'replacement' ? ' selected' : ''}>Replacement</option>
						</select>
					</label>
					<button type="button" class="etwa-btn etwa-btn-ghost etwa-dlv-remove-item" data-remove-item>Remove</button>
				</div>
				<div class="etwa-dlv-item-replacement" data-replacement-fields hidden>
					<label>
						<span>Replace Deliverable Type</span>
						<select class="etwa-input" data-field="replaced_type_id">${typeOptionsHtml(data.replaced_type_id)}</select>
					</label>
					<label>
						<span>Replacement Quantity</span>
						<input type="number" class="etwa-input" data-field="replacement_qty" min="0.01" step="0.01" value="${esc(data.replacement_qty != null ? data.replacement_qty : '')}" />
					</label>
				</div>
			</div>
		`;
	}

	function addAllocItemRow(item) {
		const $wrap = $('#etwa-dlv-alloc-items');
		if (!$wrap.length) {
			return;
		}
		$wrap.append(allocItemRowHtml(item));
		updateAllocItemUi($wrap.find('[data-alloc-item]').last());
		updateAllocSummary();
	}

	function resetAllocItems(items) {
		const $wrap = $('#etwa-dlv-alloc-items');
		$wrap.empty();
		const list = items && items.length ? items : [null];
		list.forEach((item) => addAllocItemRow(item));
	}

	function updateAllocItemUi($row) {
		const impact = $row.find('[data-field="scope_impact"]').val();
		const $rep = $row.find('[data-replacement-fields]');
		const $repQty = $row.find('[data-field="replacement_qty"]');
		const $repType = $row.find('[data-field="replaced_type_id"]');
		if (impact === 'replacement') {
			$rep.removeAttr('hidden');
			$repQty.prop('required', true);
			$repType.prop('required', true);
		} else {
			$rep.attr('hidden', 'hidden');
			$repQty.prop('required', false).val('');
			$repType.prop('required', false);
		}
	}

	function updateAllocSummary() {
		let items = 0;
		let qty = 0;
		let normal = 0;
		let replacement = 0;
		let toward = 0;
		$('#etwa-dlv-alloc-items [data-alloc-item]').each(function () {
			const $row = $(this);
			items += 1;
			const q = num($row.find('[data-field="quantity"]').val());
			const impact = $row.find('[data-field="scope_impact"]').val();
			qty += q;
			toward += allocItemToward($row);
			if (impact === 'replacement') {
				replacement += q;
			} else {
				normal += q;
			}
		});
		const capacity = allocCapacityRemaining();
		const extra = Math.max(0, toward - capacity);
		const $sum = $('#etwa-dlv-alloc-summary');
		$sum.find('[data-sum="items"] strong').text(String(items));
		$sum.find('[data-sum="qty"] strong').text(formatQty(qty));
		$sum.find('[data-sum="normal"] strong').text(formatQty(normal));
		$sum.find('[data-sum="replacement"] strong').text(formatQty(replacement));
		$sum.find('[data-sum="extra"] strong').text(formatQty(extra));
	}

	function collectAllocItems() {
		const items = [];
		let error = '';
		$('#etwa-dlv-alloc-items [data-alloc-item]').each(function () {
			if (error) {
				return;
			}
			const $row = $(this);
			const typeId = parseInt($row.find('[data-field="deliverable_type_id"]').val(), 10);
			const quantity = parseFloat($row.find('[data-field="quantity"]').val());
			let impact = $row.find('[data-field="scope_impact"]').val();
			if (impact === 'extra') {
				impact = 'normal';
			}
			if (!typeId || !(quantity > 0)) {
				error = 'Each deliverable needs a type and quantity greater than zero.';
				return;
			}
			const item = {
				deliverable_type_id: typeId,
				quantity,
				scope_impact: impact === 'replacement' ? 'replacement' : 'normal',
			};
			if (impact === 'replacement') {
				item.replaced_type_id = parseInt($row.find('[data-field="replaced_type_id"]').val(), 10);
				item.replacement_qty = parseFloat($row.find('[data-field="replacement_qty"]').val());
				if (!item.replaced_type_id || !(item.replacement_qty > 0)) {
					error = 'Replacement requires a replace type and quantity.';
					return;
				}
				if (item.replaced_type_id === item.deliverable_type_id) {
					error = 'Replaced type must differ from the allocated type.';
					return;
				}
			}
			items.push(item);
		});
		return { items, error };
	}

	function fillTypeSelects() {
		$('#etwa-dlv-alloc-items [data-field="deliverable_type_id"], #etwa-dlv-alloc-items [data-field="replaced_type_id"]').each(function () {
			const $s = $(this);
			const cur = $s.val();
			$s.html(typeOptionsHtml(cur));
			if (cur) {
				$s.val(cur);
			}
		});
	}

	function showDashboard() {
		state.mode = 'dashboard';
		state.scopeId = 0;
		state.detail = null;
		$('#etwa-deliverables-dashboard').removeClass('etwa-hidden');
		$('#etwa-deliverables-detail').addClass('etwa-hidden');
		try {
			const url = new URL(window.location.href);
			url.searchParams.delete('scope_id');
			url.searchParams.delete('view');
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
		refreshDashboard();
	}

	function showDetail(scopeId) {
		state.mode = 'detail';
		state.scopeId = scopeId;
		$('#etwa-deliverables-dashboard').addClass('etwa-hidden');
		$('#etwa-deliverables-detail').removeClass('etwa-hidden');
		try {
			const url = new URL(window.location.href);
			url.searchParams.set('scope_id', String(scopeId));
			url.searchParams.delete('view');
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
		refreshDetail();
	}

	function loadScopeOptions(clientId) {
		const params = new URLSearchParams();
		if (clientId) {
			params.set('client_id', clientId);
		}
		return api(`deliverables/scopes?${params.toString()}`)
			.then((res) => {
				const items = (res.success && res.data && res.data.items) || [];
				state.scopes = items.map((item) => item.scope || item);
				const $scope = $('#etwa-dlv-scope-filter');
				const cur = $scope.val();
				$scope.find('option:not(:first)').remove();
				state.scopes.forEach((s) => {
					$scope.append(`<option value="${esc(s.id)}">${esc(s.name)}</option>`);
				});
				if (cur && $scope.find(`option[value="${cur}"]`).length) {
					$scope.val(cur);
				}
				return state.scopes;
			})
			.catch(() => {
				state.scopes = [];
				return state.scopes;
			});
	}

	function chartDefaults() {
		return {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					position: 'bottom',
					labels: {
						boxWidth: 10,
						boxHeight: 10,
						padding: 16,
						font: { size: 12, family: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' },
						color: CHART_COLORS.text,
					},
				},
				tooltip: {
					backgroundColor: '#0f172a',
					titleFont: { size: 13 },
					bodyFont: { size: 12 },
					padding: 10,
					cornerRadius: 8,
				},
			},
			scales: {
				x: {
					grid: { color: CHART_COLORS.grid, drawBorder: false },
					ticks: { color: CHART_COLORS.text, font: { size: 11 }, maxRotation: 45, minRotation: 0 },
				},
				y: {
					grid: { color: CHART_COLORS.grid, drawBorder: false },
					ticks: { color: CHART_COLORS.text, font: { size: 11 } },
					beginAtZero: true,
				},
			},
		};
	}

	function destroyCharts() {
		chartInstances.forEach((c) => c.destroy());
		chartInstances = [];
	}

	function markChartLoaded(key) {
		const wrap = document.querySelector(`.etwa-deliverables-page .etwa-chart-canvas-wrap[data-chart="${key}"]`);
		if (wrap) {
			wrap.classList.remove('is-loading');
		}
	}

	function dashboardQueryParams() {
		const params = new URLSearchParams();
		const clientId = $('#etwa-dlv-client-filter').val();
		const scopeId = $('#etwa-dlv-scope-filter').val();
		const status = $('#etwa-dlv-status-filter').val();
		const typeId = $('#etwa-dlv-type-filter').val();
		const dateFrom = $('#etwa-dlv-date-from').val();
		const dateTo = $('#etwa-dlv-date-to').val();
		if (clientId) params.set('client_id', clientId);
		if (scopeId) params.set('scope_id', scopeId);
		if (status) params.set('status', status);
		if (typeId) params.set('deliverable_type_id', typeId);
		if (dateFrom) params.set('date_from', dateFrom);
		if (dateTo) params.set('date_to', dateTo);
		params.set('trend', state.trend);
		params.set('over_time', state.overTime);
		return params;
	}

	function renderEffortStrip(effort) {
		const e = effort || {};
		$('[data-effort="planned"]').text(num(e.planned).toFixed(2));
		$('[data-effort="completed"]').text(num(e.completed).toFixed(2));
		$('[data-effort="pct"]').text(`${num(e.pct).toFixed(1)}%`);
	}

	function renderScopePerformance(data) {
		const perf = (data && data.scope_performance) || {};
		const rows = perf[state.scopePerfTab] || [];
		const $body = $('#etwa-dlv-scope-perf-body').empty();
		if (!rows.length) {
			$body.append('<tr><td colspan="5" class="etwa-empty-cell">No data for this view</td></tr>');
			return;
		}
		rows.forEach((row) => {
			$body.append(`
				<tr class="etwa-dlv-perf-row" data-scope-id="${esc(row.scope_id)}" role="button" tabindex="0">
					<td>${esc(row.scope_name)}</td>
					<td>${esc(row.client_name)}</td>
					<td>${esc(row.project_name || '—')}</td>
					<td>${num(row.completion_pct).toFixed(1)}%</td>
					<td>${num(row.remaining_qty).toFixed(2)}</td>
				</tr>
			`);
		});
	}

	function renderClientPerformance(data) {
		const perf = (data && data.client_performance) || {};
		const rows = perf[state.clientPerfTab] || [];
		const $body = $('#etwa-dlv-client-perf-body').empty();
		if (!rows.length) {
			$body.append('<tr><td colspan="3" class="etwa-empty-cell">No data for this view</td></tr>');
			return;
		}
		rows.forEach((row) => {
			let value = '';
			if (state.clientPerfTab === 'highest_completion') {
				value = `${num(row.completion_pct).toFixed(1)}%`;
			} else if (state.clientPerfTab === 'highest_effort') {
				value = num(row.effort_score).toFixed(2);
			} else {
				value = num(row.extra_qty).toFixed(2);
			}
			$body.append(`
				<tr>
					<td>${esc(row.name)}</td>
					<td>${esc(row.scope_count)}</td>
					<td>${value}</td>
				</tr>
			`);
		});
	}

	function renderInsights(insights) {
		const $wrap = $('#etwa-dlv-insights').empty();
		const items = insights || [];
		if (!items.length) {
			$wrap.append('<p class="etwa-muted">No insights for the current filters.</p>');
			return;
		}
		items.forEach((insight) => {
			const chips = (insight.items || [])
				.slice(0, 5)
				.map((item) => {
					const label = item.scope_name || item.name || item.client_name || '—';
					return `<span class="etwa-dlv-insight-chip">${esc(label)}</span>`;
				})
				.join('');
			$wrap.append(`
				<div class="etwa-dlv-insight-block">
					<h4>${esc(insight.label)}</h4>
					<div class="etwa-dlv-insight-chips">${chips || '<span class="etwa-muted">—</span>'}</div>
				</div>
			`);
		});
	}

	function renderRecentActivity(rows) {
		const $body = $('#etwa-dlv-activity-body').empty();
		if (!rows || !rows.length) {
			$body.append('<tr><td colspan="7" class="etwa-empty-cell">No recent allocations</td></tr>');
			return;
		}
		rows.forEach((row) => {
			$body.append(`
				<tr>
					<td>${esc(row.date)}</td>
					<td>${esc(row.client)}</td>
					<td>${esc(row.scope)}</td>
					<td>${esc(row.task_name || 'Untitled allocation')}</td>
					<td>${esc(row.summary || '—')}</td>
					<td>${formatQty(row.quantity)}</td>
					<td>${esc(row.user)}</td>
				</tr>
			`);
		});
	}

	function buildDashboardCharts(data) {
		if (typeof Chart === 'undefined') {
			return;
		}

		destroyCharts();
		document.querySelectorAll('.etwa-deliverables-page .etwa-chart-canvas-wrap').forEach((el) => {
			el.classList.add('is-loading');
		});

		const byType = data.by_type || [];
		const byTypeEl = document.getElementById('etwa-dlv-chart-by-type');
		if (byTypeEl && byType.length) {
			chartInstances.push(
				new Chart(byTypeEl, {
					type: 'doughnut',
					data: {
						labels: byType.map((t) => t.name),
						datasets: [
							{
								data: byType.map((t) => num(t.completed_qty) + num(t.extra_qty)),
								backgroundColor: CHART_COLORS.palette,
								borderWidth: 0,
								hoverOffset: 6,
							},
						],
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						cutout: '58%',
						plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 }, color: CHART_COLORS.text } } },
					},
				})
			);
		}
		markChartLoaded('by-type');

		const completion = data.completion_trend || [];
		const completionEl = document.getElementById('etwa-dlv-chart-completion');
		if (completionEl && completion.length) {
			const isScope = state.trend === 'scope';
			chartInstances.push(
				new Chart(completionEl, {
					type: isScope ? 'bar' : 'line',
					data: {
						labels: completion.map((t) => t.period),
						datasets: [
							{
								label: 'Completed',
								data: completion.map((t) => t.completed_qty),
								borderColor: CHART_COLORS.primary,
								backgroundColor: isScope ? CHART_COLORS.primary : CHART_COLORS.primaryLight,
								fill: !isScope,
								tension: 0.35,
								borderWidth: 2,
							},
							...(isScope
								? []
								: [
										{
											label: 'Planned (total)',
											data: completion.map((t) => t.planned_qty),
											borderColor: CHART_COLORS.text,
											borderDash: [4, 4],
											fill: false,
											tension: 0.2,
											borderWidth: 1.5,
										},
								  ]),
						],
					},
					options: chartDefaults(),
				})
			);
		}
		markChartLoaded('completion');

		const overTime = data.deliverables_over_time || [];
		const overTimeEl = document.getElementById('etwa-dlv-chart-over-time');
		if (overTimeEl && overTime.length) {
			chartInstances.push(
				new Chart(overTimeEl, {
					type: 'bar',
					data: {
						labels: overTime.map((t) => t.period),
						datasets: [
							{
								label: 'Completed qty',
								data: overTime.map((t) => t.completed_qty),
								backgroundColor: CHART_COLORS.success,
								borderRadius: 4,
							},
						],
					},
					options: chartDefaults(),
				})
			);
		}
		markChartLoaded('over-time');

		const effortTrend = data.effort_trend || [];
		const effortEl = document.getElementById('etwa-dlv-chart-effort');
		if (effortEl && effortTrend.length) {
			chartInstances.push(
				new Chart(effortEl, {
					type: 'line',
					data: {
						labels: effortTrend.map((t) => t.period),
						datasets: [
							{
								label: 'Completed hours',
								data: effortTrend.map((t) => t.completed_effort),
								borderColor: CHART_COLORS.warning,
								backgroundColor: 'rgba(217, 119, 6, 0.08)',
								fill: true,
								tension: 0.35,
								borderWidth: 2,
							},
						],
					},
					options: chartDefaults(),
				})
			);
		}
		markChartLoaded('effort');
	}

	function applyDashboardData(data) {
		state.dashboard = data;
		setKpis($('#etwa-dlv-dashboard-kpis'), (data && data.kpis) || {}, 'kpi');
		renderEffortStrip(data && data.effort);
		renderScopePerformance(data);
		renderClientPerformance(data);
		renderInsights((data && data.insights) || []);
		renderRecentActivity((data && data.recent_activity) || []);
		buildDashboardCharts(data);
	}

	function refreshDashboard() {
		setLoading(true);
		$('#etwa-dlv-scope-perf-body').html('<tr><td colspan="5" class="etwa-empty-cell">Loading…</td></tr>');
		$('#etwa-dlv-client-perf-body').html('<tr><td colspan="3" class="etwa-empty-cell">Loading…</td></tr>');
		$('#etwa-dlv-activity-body').html('<tr><td colspan="7" class="etwa-empty-cell">Loading…</td></tr>');

		api(`deliverables/dashboard/analytics?${dashboardQueryParams().toString()}`)
			.then((res) => {
				if (!res.success) {
					fail(res, 'Failed to load dashboard analytics.');
					return;
				}
				applyDashboardData(res.data || {});
			})
			.catch((err) => fail(err, 'Failed to load deliverables dashboard.'))
			.finally(() => setLoading(false));
	}

	function applyMetricsToDetail(metrics) {
		if (!metrics) {
			return;
		}
		setKpis($('#etwa-dlv-scope-kpis'), metrics, 'scope');
		renderContractPanel(metrics);
	}

	function renderContractPanel(metrics) {
		const $summary = $('#etwa-dlv-contract-summary');
		const contract = num(metrics.contract_deliverables);
		const planned = num(metrics.planned_qty);
		const notes =
			state.detail && state.detail.scope && state.detail.scope.description
				? state.detail.scope.description
				: '';
		$summary.html(`
			<p><strong>Expected total:</strong> ${contract.toFixed(2)}</p>
			<p><strong>Delivered total:</strong> ${num(metrics.completed_qty).toFixed(2)}</p>
			${notes ? `<p class="etwa-muted">${esc(notes)}</p>` : ''}
		`);

		const byType = (metrics && metrics.by_type) || [];
		const $body = $('#etwa-dlv-contract-body').empty();
		if (!byType.length) {
			$body.append('<tr><td colspan="5" class="etwa-empty-cell">No allocations yet — type breakdown appears after allocate</td></tr>');
			return;
		}
		byType.forEach((row) => {
			$body.append(`
				<tr>
					<td>${esc(row.deliverable_type)}</td>
					<td>${num(row.completed_qty).toFixed(2)}</td>
					<td>${num(row.normal_qty).toFixed(2)}</td>
					<td>${num(row.replacement_qty).toFixed(2)}</td>
					<td>${num(row.extra_qty).toFixed(2)}</td>
				</tr>
			`);
		});
	}

	function allocColspan() {
		return canAllocate ? 5 : 4;
	}

	function renderAllocations(items) {
		state.allocations = items || [];
		const $body = $('#etwa-dlv-alloc-body').empty();
		const cols = allocColspan();
		if (!items.length) {
			$body.append(`<tr><td colspan="${cols}" class="etwa-empty-cell">No allocations yet</td></tr>`);
			return;
		}
		items.forEach((a) => {
			const total = a.total_quantity != null ? a.total_quantity : (a.items || []).reduce((s, i) => s + num(i.quantity), 0);
			const actions = canAllocate
				? `<td class="etwa-dlv-alloc-actions">
					<button type="button" class="etwa-btn etwa-btn-ghost etwa-btn-small" data-alloc-edit="${esc(a.id)}">Edit</button>
					<button type="button" class="etwa-btn etwa-btn-ghost etwa-btn-small" data-alloc-delete="${esc(a.id)}">Delete</button>
				</td>`
				: '';
			$body.append(`
				<tr class="etwa-dlv-alloc-row" data-alloc-id="${esc(a.id)}" tabindex="0" role="button">
					<td><strong>${esc(taskLabel(a))}</strong></td>
					<td>${esc(allocationSummaryText(a) || '—')}</td>
					<td>${formatQty(total)}</td>
					<td>${esc(a.completed_date)}</td>
					${actions}
				</tr>
			`);
		});
	}

	function renderAllocationDetail(a) {
		state.currentAllocation = a;
		const items = a.items || [];
		const total = a.total_quantity != null ? a.total_quantity : items.reduce((s, i) => s + num(i.quantity), 0);
		const scopeName = (state.detail && state.detail.scope && state.detail.scope.name) || `#${a.scope_id}`;
		const createdBy = a.allocated_by_name || (a.allocated_by ? `User #${a.allocated_by}` : '—');
		const itemRows = items
			.map((item) => {
				let extra = '';
				if (item.scope_impact === 'replacement') {
					extra = `<p class="etwa-muted">Replaces ${esc(typeName(item.replaced_type_id))} × ${esc(formatQty(item.replacement_qty))}</p>`;
				}
				return `
					<article class="etwa-dlv-detail-item">
						<h4>${esc(typeName(item.deliverable_type_id))} × ${esc(formatQty(item.quantity))}</h4>
						<span class="etwa-status">${esc(impactLabel(item.scope_impact))}</span>
						${extra}
					</article>
				`;
			})
			.join('');

		$('#etwa-dlv-alloc-detail-body').html(`
			<header class="etwa-dlv-alloc-detail-head">
				<h2>${esc(taskLabel(a))}</h2>
				<p class="etwa-muted"><strong>Scope</strong> ${esc(scopeName)}</p>
				<p class="etwa-muted"><strong>Completed Date</strong> ${esc(a.completed_date)}</p>
				${a.notes ? `<p class="etwa-muted"><strong>Notes</strong> ${esc(a.notes)}</p>` : ''}
			</header>
			<section>
				<h3>Deliverables</h3>
				<div class="etwa-dlv-detail-items">${itemRows || '<p class="etwa-muted">No items</p>'}</div>
			</section>
			<p class="etwa-dlv-detail-total"><strong>Total Delivered:</strong> ${esc(formatQty(total))}</p>
			<p class="etwa-muted"><strong>Created By</strong> ${esc(createdBy)}</p>
			<p class="etwa-muted"><strong>Created Date</strong> ${esc((a.created_at || '').slice(0, 10) || '—')}</p>
		`);
	}

	function openAllocationDetail(id) {
		const cached = (state.allocations || []).find((a) => Number(a.id) === Number(id));
		if (cached) {
			renderAllocationDetail(cached);
			openModal($('#etwa-dlv-alloc-detail-modal'));
		}
		api(`deliverables/allocations/${id}`)
			.then((res) => {
				if (!res.success || !res.data || !res.data.allocation) {
					if (!cached) {
						fail(res, 'Could not load allocation.');
					}
					return;
				}
				renderAllocationDetail(res.data.allocation);
				openModal($('#etwa-dlv-alloc-detail-modal'));
			})
			.catch((err) => {
				if (!cached) {
					fail(err, 'Could not load allocation.');
				}
			});
	}

	function openAllocateEditor(allocation) {
		if (!canAllocate) {
			return;
		}
		if (!state.types.length) {
			fail({ message: 'Create at least one active deliverable type before allocating.' });
			return;
		}
		const $form = $('#etwa-dlv-allocate-form');
		$form[0].reset();
		if (allocation && allocation.id) {
			state.currentAllocation = allocation;
			$('#etwa-dlv-allocate-modal-title').text('Edit Allocation');
			$('#etwa-dlv-alloc-edit-id').val(String(allocation.id));
			$('#etwa-dlv-alloc-task').val(allocation.task_name || '');
			$('#etwa-dlv-alloc-date').val(allocation.completed_date || today());
			$form.find('[name="notes"]').val(allocation.notes || '');
			resetAllocItems(allocation.items || []);
		} else {
			$('#etwa-dlv-allocate-modal-title').text('Allocate Deliverables');
			$('#etwa-dlv-alloc-edit-id').val('');
			$('#etwa-dlv-alloc-date').val(today());
			resetAllocItems();
		}
		openModal($('#etwa-dlv-allocate-modal'));
	}

	function confirmDeleteAllocation(id) {
		if (!canAllocate || !id) {
			return;
		}
		const msg =
			(cfg.i18n && cfg.i18n.confirmDeleteAllocation) ||
			'Delete this allocation and all of its deliverable items? Scope metrics will recalculate. This cannot be undone.';
		const title = (cfg.i18n && cfg.i18n.delete) || 'Delete';
		const run = () => {
			api(`deliverables/allocations/${id}`, { method: 'DELETE' })
				.then((res) => {
					if (!res.success) {
						fail(res, 'Could not delete allocation.');
						return;
					}
					ok('Allocation deleted');
					closeModal($('#etwa-dlv-alloc-detail-modal'));
					if (state.currentAllocation && Number(state.currentAllocation.id) === Number(id)) {
						state.currentAllocation = null;
					}
					syncAfterAllocation(res.data && res.data.metrics);
				})
				.catch((err) => fail(err, 'Could not delete allocation.'));
		};
		if (ui.confirm) {
			ui.confirm(msg, title).then((okConfirm) => {
				if (okConfirm) {
					run();
				}
			});
		} else if (window.confirm(msg)) {
			run();
		}
	}

	function refreshDetail() {
		if (!state.scopeId) {
			return;
		}
		setLoading(true);
		$('#etwa-dlv-contract-body').html('<tr><td colspan="5" class="etwa-empty-cell">Loading…</td></tr>');
		$('#etwa-dlv-alloc-body').html(`<tr><td colspan="${allocColspan()}" class="etwa-empty-cell">Loading…</td></tr>`);

		Promise.all([
			api(`deliverables/scopes/${state.scopeId}`),
			api(`deliverables/allocations?scope_id=${state.scopeId}`),
		])
			.then(([detailRes, allocRes]) => {
				if (!detailRes.success) {
					fail(detailRes, 'Failed to load scope.');
					return;
				}
				state.detail = detailRes.data;
				const scope = detailRes.data.scope || {};
				const client = detailRes.data.client || {};
				const project = detailRes.data.project || {};
				$('#etwa-dlv-detail-title').text(scope.name || 'Scope');
				$('#etwa-dlv-detail-client').text(client.name ? `Client: ${client.name}` : '');
				$('#etwa-dlv-detail-project').text(project.name ? `Project: ${project.name}` : '');
				$('#etwa-dlv-detail-status')
					.attr('class', `etwa-status etwa-status-${scope.status || 'active'}`)
					.text(scope.status || '');
				applyMetricsToDetail(detailRes.data.metrics);
				if (allocRes.success) {
					renderAllocations((allocRes.data && allocRes.data.items) || []);
				} else {
					fail(allocRes, 'Failed to load allocations.');
					renderAllocations([]);
				}
			})
			.catch((err) => fail(err, 'Failed to load scope details.'))
			.finally(() => setLoading(false));
	}

	function resetTypeForm() {
		const $form = $('#etwa-dlv-type-form');
		if (!$form.length) {
			return;
		}
		$form[0].reset();
		$('#etwa-dlv-type-id').val('');
		$('#etwa-dlv-type-submit').text('Add Type');
		$('#etwa-dlv-type-cancel-edit').addClass('etwa-hidden');
	}

	function startEditType(type) {
		if (!type || !type.id) {
			return;
		}
		$('#etwa-dlv-type-id').val(String(type.id));
		$('#etwa-dlv-type-form').find('[name="name"]').val(type.name || '');
		$('#etwa-dlv-type-form').find('[name="slug"]').val(type.slug || '');
		$('#etwa-dlv-type-form').find('[name="effort_score"]').val(type.effort_score != null ? type.effort_score : 1);
		$('#etwa-dlv-type-submit').text('Update Type');
		$('#etwa-dlv-type-cancel-edit').removeClass('etwa-hidden');
	}

	function refreshTypesTable() {
		api('deliverable-types')
			.then((res) => {
				if (!res.success) {
					fail(res, 'Could not load types.');
					return;
				}
				const $body = $('#etwa-dlv-types-body').empty();
				const rows = res.data || [];
				state.managedTypes = rows;
				if (!rows.length) {
					$body.append('<tr><td colspan="4" class="etwa-empty-cell">No types yet</td></tr>');
					return;
				}
				rows.forEach((t) => {
					const active = t.is_active === true || t.is_active === 1 || t.is_active === '1';
					const statusAction = active
						? `<button type="button" class="etwa-btn etwa-btn-ghost etwa-btn-small" data-type-deactivate="${esc(t.id)}">Delete</button>`
						: `<button type="button" class="etwa-btn etwa-btn-ghost etwa-btn-small" data-type-reactivate="${esc(t.id)}">Reactivate</button>`;
					$body.append(`
						<tr data-type-id="${esc(t.id)}">
							<td>${esc(t.name)}</td>
							<td>${num(t.effort_score).toFixed(2)}</td>
							<td>${active ? 'Yes' : 'No'}</td>
							<td class="etwa-dlv-type-actions">
								<button type="button" class="etwa-btn etwa-btn-ghost etwa-btn-small" data-type-edit="${esc(t.id)}">Edit</button>
								${statusAction}
							</td>
						</tr>
					`);
				});
			})
			.catch((err) => fail(err, 'Could not load types.'));
	}

	function refreshTypesAfterChange() {
		return loadTypes().then(() => {
			fillTypeSelects();
			refreshTypesTable();
		});
	}

	function syncAfterAllocation(metrics) {
		if (metrics) {
			if (state.detail) {
				state.detail.metrics = metrics;
			}
			applyMetricsToDetail(metrics);
		}
		api(`deliverables/allocations?scope_id=${state.scopeId}`)
			.then((aRes) => {
				if (aRes.success) {
					renderAllocations((aRes.data && aRes.data.items) || []);
				}
			})
			.catch(() => {});
	}

	function loadScopeProjects(clientId, selectedProjectId) {
		const $sel = $('#etwa-dlv-scope-project');
		if (!$sel.length) {
			return Promise.resolve([]);
		}
		if (!clientId) {
			state.scopeProjects = [];
			$sel.find('option:not(:first)').remove();
			return Promise.resolve([]);
		}
		return api(`clients/${clientId}/projects`)
			.then((res) => {
				state.scopeProjects = res.success ? res.data || [] : [];
				const cur = selectedProjectId != null ? String(selectedProjectId) : $sel.val();
				$sel.find('option:not(:first)').remove();
				state.scopeProjects.forEach((p) => {
					$sel.append(`<option value="${esc(p.id)}">${esc(p.name)}</option>`);
				});
				if (cur && $sel.find(`option[value="${cur}"]`).length) {
					$sel.val(cur);
				} else {
					$sel.val('');
				}
				return state.scopeProjects;
			})
			.catch(() => {
				state.scopeProjects = [];
				$sel.find('option:not(:first)').remove();
				return [];
			});
	}

	function resetScopeFormUi() {
		$('#etwa-dlv-scope-project').find('option:not(:first)').remove().end().val('');
		$('#etwa-dlv-scope-contract').prop('readonly', false).val('0');
	}

	// Events
	$('#etwa-deliverables-filters').on('submit', function (e) {
		e.preventDefault();
		refreshDashboard();
	});

	$('#etwa-dlv-client-filter').on('change', function () {
		loadScopeOptions($(this).val()).then(() => {
			$('#etwa-dlv-scope-filter').val('');
		});
	});

	$(document).on('click', '[data-dlv-trend]', function () {
		state.trend = $(this).data('dlv-trend');
		$('[data-dlv-trend]').removeClass('is-active');
		$(this).addClass('is-active');
		refreshDashboard();
	});

	$(document).on('click', '[data-dlv-over-time]', function () {
		state.overTime = $(this).data('dlv-over-time');
		$('[data-dlv-over-time]').removeClass('is-active');
		$(this).addClass('is-active');
		refreshDashboard();
	});

	$(document).on('click', '[data-dlv-perf]', function () {
		state.scopePerfTab = $(this).data('dlv-perf');
		$('[data-dlv-perf]').removeClass('is-active');
		$(this).addClass('is-active');
		if (state.dashboard) {
			renderScopePerformance(state.dashboard);
		}
	});

	$(document).on('click', '[data-dlv-client-perf]', function () {
		state.clientPerfTab = $(this).data('dlv-client-perf');
		$('[data-dlv-client-perf]').removeClass('is-active');
		$(this).addClass('is-active');
		if (state.dashboard) {
			renderClientPerformance(state.dashboard);
		}
	});

	$(document).on('click', '.etwa-dlv-perf-row', function () {
		showDetail(parseInt($(this).data('scope-id'), 10));
	});

	$(document).on('keydown', '.etwa-dlv-perf-row', function (e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			showDetail(parseInt($(this).data('scope-id'), 10));
		}
	});

	$('#etwa-dlv-back').on('click', function (e) {
		e.preventDefault();
		showDashboard();
	});

	$('[data-close-modal]').on('click', function () {
		closeModal($(this).closest('.etwa-modal'));
	});

	$(document).on('click', '.etwa-modal', function (e) {
		if (e.target === this) {
			closeModal($(this));
		}
	});

	$('#etwa-dlv-add-item').on('click', function () {
		addAllocItemRow();
	});

	$(document).on('click', '[data-remove-item]', function () {
		const $rows = $('#etwa-dlv-alloc-items [data-alloc-item]');
		if ($rows.length <= 1) {
			fail({ message: 'At least one deliverable item is required.' });
			return;
		}
		$(this).closest('[data-alloc-item]').remove();
		updateAllocSummary();
	});

	$(document).on('change', '#etwa-dlv-alloc-items [data-field="scope_impact"]', function () {
		updateAllocItemUi($(this).closest('[data-alloc-item]'));
		updateAllocSummary();
	});

	$(document).on(
		'input change',
		'#etwa-dlv-alloc-items [data-field="quantity"], #etwa-dlv-alloc-items [data-field="replacement_qty"]',
		updateAllocSummary
	);

	$(document).on('click', '.etwa-dlv-alloc-row', function () {
		openAllocationDetail($(this).data('alloc-id'));
	});

	$(document).on('keydown', '.etwa-dlv-alloc-row', function (e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			openAllocationDetail($(this).data('alloc-id'));
		}
	});

	$('#etwa-dlv-allocate-btn').on('click', function () {
		openAllocateEditor(null);
	});

	$('#etwa-dlv-alloc-edit-btn').on('click', function () {
		if (!state.currentAllocation) {
			return;
		}
		closeModal($('#etwa-dlv-alloc-detail-modal'));
		openAllocateEditor(state.currentAllocation);
	});

	$('#etwa-dlv-alloc-delete-btn').on('click', function () {
		if (!state.currentAllocation || !state.currentAllocation.id) {
			return;
		}
		confirmDeleteAllocation(state.currentAllocation.id);
	});

	$(document).on('click', '[data-alloc-edit]', function (e) {
		e.preventDefault();
		e.stopPropagation();
		const id = $(this).data('alloc-edit');
		const allocation = (state.allocations || []).find((a) => Number(a.id) === Number(id));
		if (allocation) {
			openAllocateEditor(allocation);
		} else {
			api(`deliverables/allocations/${id}`)
				.then((res) => {
					if (!res.success || !res.data || !res.data.allocation) {
						fail(res, 'Could not load allocation.');
						return;
					}
					openAllocateEditor(res.data.allocation);
				})
				.catch((err) => fail(err, 'Could not load allocation.'));
		}
	});

	$(document).on('click', '[data-alloc-delete]', function (e) {
		e.preventDefault();
		e.stopPropagation();
		confirmDeleteAllocation($(this).data('alloc-delete'));
	});

	$('#etwa-dlv-allocate-form').on('submit', function (e) {
		e.preventDefault();
		if (!canAllocate || !state.scopeId) {
			return;
		}
		const taskName = ($('#etwa-dlv-alloc-task').val() || '').trim();
		if (!taskName) {
			fail({ message: 'Task name is required.' });
			return;
		}
		const collected = collectAllocItems();
		if (collected.error) {
			fail({ message: collected.error });
			return;
		}
		if (!collected.items.length) {
			fail({ message: 'At least one deliverable item is required.' });
			return;
		}
		const editId = parseInt($('#etwa-dlv-alloc-edit-id').val(), 10) || 0;
		const $btn = $(this).find('[type="submit"]');
		const payload = {
			scope_id: state.scopeId,
			task_name: taskName,
			completed_date: $('#etwa-dlv-alloc-date').val(),
			notes: $(this).find('[name="notes"]').val() || '',
			items: collected.items,
		};
		btnLoading($btn, true, 'Saving…');
		const req = editId
			? api(`deliverables/allocations/${editId}`, { method: 'PUT', body: JSON.stringify(payload) })
			: api('deliverables/allocations', { method: 'POST', body: JSON.stringify(payload) });
		req
			.then((res) => {
				if (!res.success) {
					fail(res, 'Allocation failed.');
					return;
				}
				ok(editId ? 'Allocation updated' : 'Allocation saved');
				closeModal($('#etwa-dlv-allocate-modal'));
				state.currentAllocation = null;
				syncAfterAllocation(res.data && res.data.metrics);
			})
			.catch((err) => fail(err, 'Allocation failed.'))
			.finally(() => btnLoading($btn, false));
	});

	if (canManage) {
		$('#etwa-dlv-create-scope').on('click', function () {
			$('#etwa-dlv-scope-modal-title').text('Create Scope');
			$('#etwa-dlv-scope-form')[0].reset();
			$('#etwa-dlv-scope-id').val('');
			$('#etwa-dlv-scope-code').val('').attr('placeholder', 'Auto-generated');
			resetScopeFormUi();
			openModal($('#etwa-dlv-scope-modal'));
		});

		$('#etwa-dlv-scope-client').on('change', function () {
			loadScopeProjects(parseInt($(this).val(), 10) || 0);
		});

		$('#etwa-dlv-edit-scope').on('click', function () {
			if (!state.detail) {
				return;
			}
			const s = state.detail.scope;
			$('#etwa-dlv-scope-modal-title').text('Edit Scope');
			$('#etwa-dlv-scope-id').val(s.id);
			$('#etwa-dlv-scope-client').val(String(s.client_id));
			$('#etwa-dlv-scope-name').val(s.name);
			$('#etwa-dlv-scope-code').val(s.code || '');
			$('#etwa-dlv-scope-starts').val(s.starts_on || '');
			$('#etwa-dlv-scope-ends').val(s.ends_on || '');
			$('#etwa-dlv-scope-status').val(s.status);
			$('#etwa-dlv-scope-desc').val(s.description || '');
			$('#etwa-dlv-scope-contract')
				.prop('readonly', false)
				.val(s.contract_deliverables != null ? s.contract_deliverables : 0);
			loadScopeProjects(s.client_id, s.project_id || '').then(() => {
				openModal($('#etwa-dlv-scope-modal'));
			});
		});

		$('#etwa-dlv-scope-form').on('submit', function (e) {
			e.preventDefault();
			const id = $('#etwa-dlv-scope-id').val();
			const projectRaw = $('#etwa-dlv-scope-project').val();
			const projectId = projectRaw ? parseInt(projectRaw, 10) : null;
			const payload = {
				client_id: parseInt($('#etwa-dlv-scope-client').val(), 10),
				project_id: projectId && projectId > 0 ? projectId : null,
				name: $('#etwa-dlv-scope-name').val(),
				status: $('#etwa-dlv-scope-status').val(),
				starts_on: $('#etwa-dlv-scope-starts').val() || null,
				ends_on: $('#etwa-dlv-scope-ends').val() || null,
				contract_deliverables: parseFloat($('#etwa-dlv-scope-contract').val() || '0'),
				description: $('#etwa-dlv-scope-desc').val(),
			};
			if (!payload.client_id || !payload.name) {
				fail({ message: 'Client and name are required.' });
				return;
			}
			const $btn = $(this).find('[type="submit"]');
			btnLoading($btn, true, 'Saving…');
			const req = id
				? api(`deliverables/scopes/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
				: api('deliverables/scopes', { method: 'POST', body: JSON.stringify(payload) });

			req
				.then((res) => {
					if (!res.success) {
						fail(res, 'Save failed.');
						return;
					}
					ok('Scope saved');
					closeModal($('#etwa-dlv-scope-modal'));
					const newId = (res.data && res.data.scope && res.data.scope.id) || id;
					if (newId) {
						showDetail(parseInt(newId, 10));
					} else {
						refreshDashboard();
					}
				})
				.catch((err) => fail(err, 'Save failed.'))
				.finally(() => btnLoading($btn, false));
		});
	}

	if (canManageTypes) {
		$(document).on('click', '[data-dlv-manage-types]', function () {
			resetTypeForm();
			refreshTypesTable();
			openModal($('#etwa-dlv-types-modal'));
		});

		$('#etwa-dlv-type-cancel-edit').on('click', function () {
			resetTypeForm();
		});

		$(document).on('click', '[data-type-edit]', function () {
			const id = Number($(this).data('type-edit'));
			const type = (state.managedTypes || []).find((t) => Number(t.id) === id);
			if (type) {
				startEditType(type);
			}
		});

		$(document).on('click', '[data-type-deactivate]', function () {
			const id = Number($(this).data('type-deactivate'));
			if (!id) {
				return;
			}
			const msg =
				(cfg.i18n && cfg.i18n.confirmDeactivateDeliverableType) ||
				'Delete this deliverable type? It will be deactivated and no longer appear when allocating. Existing allocations keep their history.';
			const title = (cfg.i18n && cfg.i18n.delete) || (cfg.i18n && cfg.i18n.confirmTitle) || 'Delete';
			const run = () => {
				api(`deliverable-types/${id}/deactivate`, { method: 'POST' })
					.then((res) => {
						if (!res.success) {
							fail(res, 'Could not delete type.');
							return;
						}
						ok('Type deleted');
						if (Number($('#etwa-dlv-type-id').val()) === id) {
							resetTypeForm();
						}
						return refreshTypesAfterChange();
					})
					.catch((err) => fail(err, 'Could not delete type.'));
			};
			if (ui.confirm) {
				ui.confirm(msg, title).then((okConfirm) => {
					if (okConfirm) {
						run();
					}
				});
			} else if (window.confirm(msg)) {
				run();
			}
		});

		$(document).on('click', '[data-type-reactivate]', function () {
			const id = Number($(this).data('type-reactivate'));
			if (!id) {
				return;
			}
			api(`deliverable-types/${id}`, { method: 'PUT', body: JSON.stringify({ is_active: 1 }) })
				.then((res) => {
					if (!res.success) {
						fail(res, 'Could not reactivate type.');
						return;
					}
					ok('Type reactivated');
					return refreshTypesAfterChange();
				})
				.catch((err) => fail(err, 'Could not reactivate type.'));
		});

		$('#etwa-dlv-type-form').on('submit', function (e) {
			e.preventDefault();
			const editId = parseInt($('#etwa-dlv-type-id').val(), 10) || 0;
			const payload = {
				name: $(this).find('[name="name"]').val(),
				slug: $(this).find('[name="slug"]').val(),
				effort_score: parseFloat($(this).find('[name="effort_score"]').val() || '1'),
			};
			const $btn = $(this).find('[type="submit"]');
			btnLoading($btn, true, 'Saving…');
			const req = editId
				? api(`deliverable-types/${editId}`, { method: 'PUT', body: JSON.stringify(payload) })
				: api('deliverable-types', { method: 'POST', body: JSON.stringify(payload) });
			req
				.then((res) => {
					if (!res.success) {
						fail(res, editId ? 'Could not update type.' : 'Could not create type.');
						return;
					}
					ok(editId ? 'Type updated' : 'Type created');
					resetTypeForm();
					return refreshTypesAfterChange();
				})
				.catch((err) => fail(err, editId ? 'Could not update type.' : 'Could not create type.'))
				.finally(() => btnLoading($btn, false));
		});
	}

	// Boot
	Promise.all([loadTypes(), loadClients()])
		.then(() => loadScopeOptions($('#etwa-dlv-client-filter').val()))
		.then(() => {
			fillTypeSelects();
			if (state.mode === 'detail' && state.scopeId) {
				showDetail(state.scopeId);
			} else {
				refreshDashboard();
			}
		})
		.catch((err) => fail(err, 'Failed to initialize Deliverables.'));
})(jQuery);
