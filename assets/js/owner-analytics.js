(function () {
	'use strict';

	const cfg = window.etwaApp || {};
	const owner = window.etwaOwnerData || {};
	const i18n = owner.i18n || {};

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

	const api = (path) =>
		fetch(`${cfg.root}${path}`, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
		}).then((r) => r.json());

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
					ticks: { color: CHART_COLORS.text, font: { size: 11 } },
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
		const wrap = document.querySelector(`.etwa-chart-canvas-wrap[data-chart="${key}"]`);
		if (wrap) wrap.classList.remove('is-loading');
	}

	function renderInsightList(containerId, items, options) {
		const el = document.getElementById(containerId);
		if (!el) return;

		if (!items || !items.length) {
			el.innerHTML = `<div class="etwa-insight-empty">${i18n.noData || 'No data for this period'}</div>`;
			return;
		}

		el.innerHTML = items
			.map((item) => {
				const pct = Math.min(100, Math.max(0, options.pct(item)));
				return `
					<article class="etwa-insight-item ${options.variant || ''}">
						<div class="etwa-insight-item-head">
							<span class="etwa-insight-name" title="${options.label(item)}">${options.label(item)}</span>
							<span class="etwa-insight-value">${options.value(item)}</span>
						</div>
						<div class="etwa-insight-bar" role="presentation">
							<span style="width:${pct}%"></span>
						</div>
					</article>`;
			})
			.join('');
	}

	function renderInsights(insights) {
		renderInsightList('etwa-insight-top-clients', insights.top_clients, {
			label: (c) => c.name,
			value: (c) => `${c.total_hours}h · ${c.consumption_pct}%`,
			pct: (c) => c.consumption_pct,
			variant: '',
		});

		renderInsightList('etwa-insight-overloaded', insights.overloaded_employees, {
			label: (e) => e.display_name,
			value: (e) => `${e.utilization_pct}%`,
			pct: (e) => e.utilization_pct,
			variant: 'etwa-insight-item-warning',
		});

		renderInsightList('etwa-insight-underutilized', insights.underutilized, {
			label: (e) => e.display_name,
			value: (e) => `${e.utilization_pct}%`,
			pct: (e) => e.utilization_pct,
			variant: 'etwa-insight-item-info',
		});

		renderInsightList('etwa-insight-client-ot', insights.client_overtime, {
			label: (c) => c.name,
			value: (c) => `${c.overtime_ratio_pct}% ${i18n.otRatio || 'OT ratio'}`,
			pct: (c) => c.overtime_ratio_pct,
			variant: 'etwa-insight-item-ot',
		});
	}

	function updateExtendedMetrics(metrics) {
		if (!metrics) return;
		const grid = document.getElementById('etwa-extended-metrics');
		if (!grid) return;
		grid.hidden = false;

		const hourKeys = ['weekend_work_hours', 'holiday_work_hours', 'meeting_hours', 'event_hours', 'work_on_site_hours'];
		hourKeys.forEach((key) => {
			const el = document.querySelector(`[data-ext-kpi="${key}"]`);
			if (el && metrics[key] !== undefined) {
				el.textContent = Number(metrics[key]).toFixed(1);
			}
		});
	}

	function updateKpis(kpis) {
		if (!kpis) return;
		const map = {
			total_hours: (v) => Number(v).toFixed(1),
			regular_hours: (v) => Number(v).toFixed(1),
			overtime_hours: (v) => Number(v).toFixed(1),
			utilization_pct: (v) => `${Number(v).toFixed(1)}%`,
		};
		Object.keys(map).forEach((key) => {
			const el = document.querySelector(`[data-kpi="${key}"]`);
			if (el && kpis[key] !== undefined) {
				el.textContent = map[key](kpis[key]);
			}
		});
	}

	function buildCharts(data) {
		if (typeof Chart === 'undefined') return;

		destroyCharts();

		const trends = data.monthly_trends || [];
		const trendsEl = document.getElementById('etwa-chart-trends');
		if (trendsEl && trends.length) {
			chartInstances.push(
				new Chart(trendsEl, {
					type: 'line',
					data: {
						labels: trends.map((t) => t.month),
						datasets: [
							{
								label: 'Regular',
								data: trends.map((t) => t.regular_hours),
								borderColor: CHART_COLORS.primary,
								backgroundColor: CHART_COLORS.primaryLight,
								fill: true,
								tension: 0.35,
								borderWidth: 2,
								pointRadius: 3,
								pointHoverRadius: 5,
							},
							{
								label: 'Overtime',
								data: trends.map((t) => t.overtime_hours),
								borderColor: CHART_COLORS.warning,
								backgroundColor: 'rgba(217, 119, 6, 0.08)',
								fill: true,
								tension: 0.35,
								borderWidth: 2,
								pointRadius: 3,
								pointHoverRadius: 5,
							},
						],
					},
					options: chartDefaults(),
				})
			);
		}
		markChartLoaded('trends');

		const clients = data.top_clients || [];
		const clientsEl = document.getElementById('etwa-chart-clients');
		if (clientsEl && clients.length) {
			chartInstances.push(
				new Chart(clientsEl, {
					type: 'doughnut',
					data: {
						labels: clients.map((c) => c.name),
						datasets: [
							{
								data: clients.map((c) => c.total_hours),
								backgroundColor: CHART_COLORS.palette,
								borderWidth: 0,
								hoverOffset: 6,
							},
						],
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						cutout: '62%',
						plugins: {
							legend: {
								position: 'bottom',
								labels: {
									boxWidth: 10,
									padding: 12,
									font: { size: 11 },
									color: CHART_COLORS.text,
								},
							},
						},
					},
				})
			);
		}
		markChartLoaded('clients');

		const employees = (data.employee_utilization || []).slice(0, 12);
		const utilEl = document.getElementById('etwa-chart-utilization');
		if (utilEl && employees.length) {
			const isHorizontal = employees.length > 6;
			const utilWrap = utilEl.closest('.etwa-chart-canvas-wrap');
			if (isHorizontal && utilWrap) {
				const chartHeight = Math.max(260, employees.length * 28);
				utilWrap.style.minHeight = `${chartHeight}px`;
				utilEl.style.maxHeight = `${chartHeight}px`;
			}

			chartInstances.push(
				new Chart(utilEl, {
					type: 'bar',
					data: {
						labels: employees.map((e) => e.display_name),
						datasets: [
							{
								label: 'Utilization %',
								data: employees.map((e) => e.utilization_pct),
								backgroundColor: employees.map((e) =>
									e.utilization_pct > 100 ? CHART_COLORS.warning : CHART_COLORS.primary
								),
								borderRadius: 6,
								borderSkipped: false,
							},
						],
					},
					options: {
						...chartDefaults(),
						indexAxis: isHorizontal ? 'y' : 'x',
						scales: isHorizontal
							? {
									x: {
										grid: { color: CHART_COLORS.grid },
										ticks: { color: CHART_COLORS.text, font: { size: 10 } },
										beginAtZero: true,
									},
									y: {
										grid: { display: false },
										ticks: {
											autoSkip: false,
											color: CHART_COLORS.text,
											font: { size: 10 },
										},
									},
								}
							: {
									x: {
										grid: { display: true, color: CHART_COLORS.grid },
										ticks: { color: CHART_COLORS.text, font: { size: 10 }, maxRotation: 45 },
									},
									y: {
										grid: { color: CHART_COLORS.grid },
										ticks: { color: CHART_COLORS.text },
										beginAtZero: true,
									},
								},
					},
				})
			);
		}
		markChartLoaded('utilization');

		const departments = data.department_performance || [];
		const deptEl = document.getElementById('etwa-chart-departments');
		if (deptEl && departments.length) {
			chartInstances.push(
				new Chart(deptEl, {
					type: 'bar',
					data: {
						labels: departments.map((d) => d.department),
						datasets: [
							{
								label: 'Hours',
								data: departments.map((d) => d.total_hours),
								backgroundColor: CHART_COLORS.success,
								borderRadius: 6,
								borderSkipped: false,
							},
						],
					},
					options: chartDefaults(),
				})
			);
		}
		markChartLoaded('departments');
	}

	document.addEventListener('DOMContentLoaded', () => {
		if (!document.getElementById('etwa-owner-app')) return;

		renderInsights(owner.insights || {});

		api(`analytics/advanced?date_from=${encodeURIComponent(owner.dateFrom)}&date_to=${encodeURIComponent(owner.dateTo)}`)
			.then((res) => {
				if (!res.success || !res.data) return;
				updateKpis(res.data.kpis);
				updateExtendedMetrics(res.data.extended_metrics);
				if (res.data.insights) {
					renderInsights(res.data.insights);
				}
				buildCharts(res.data);
			})
			.catch(() => {
				document.querySelectorAll('.etwa-chart-canvas-wrap.is-loading').forEach((el) => {
					el.classList.remove('is-loading');
				});
			});
	});
})();
