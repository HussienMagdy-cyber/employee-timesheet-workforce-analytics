(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	let trendChart = null;

	function chartColors() {
		const root = document.documentElement;
		const style = getComputedStyle(root);
		return {
			primary: style.getPropertyValue('--etwa-primary').trim() || '#4f46e5',
			amber: style.getPropertyValue('--etwa-warning').trim() || '#f59e0b',
			muted: style.getPropertyValue('--etwa-muted').trim() || '#94a3b8',
			border: style.getPropertyValue('--etwa-border').trim() || '#e2e8f0',
		};
	}

	function buildTrendChart(trend) {
		const canvas = document.getElementById('etwa-ca-trend-chart');
		const wrap = document.querySelector('[data-chart="client-trend"]');
		if (!canvas || !window.Chart || !trend?.length) {
			if (wrap) {
				wrap.classList.remove('is-loading');
				if (!trend?.length) {
					wrap.innerHTML = `<p class="etwa-insight-empty">${cfg.i18n?.noTrendData || 'No trend data for this period.'}</p>`;
				}
			}
			return;
		}

		if (trendChart) {
			trendChart.destroy();
		}

		const colors = chartColors();
		const labels = trend.map((t) => t.period);
		const regular = trend.map((t) => t.regular_hours ?? 0);
		const hours = trend.map((t) => t.total_hours);
		const ot = trend.map((t) => t.overtime_hours);

		trendChart = new Chart(canvas, {
			type: 'line',
			data: {
				labels,
				datasets: [
					{
						label: cfg.i18n?.regularHours || 'Regular Hours',
						data: regular,
						borderColor: colors.muted,
						backgroundColor: colors.muted + '22',
						fill: true,
						tension: 0.35,
					},
					{
						label: cfg.i18n?.totalHours || 'Combined Hours',
						data: hours,
						borderColor: colors.primary,
						backgroundColor: colors.primary + '33',
						fill: true,
						tension: 0.35,
					},
					{
						label: cfg.i18n?.overtimeHours || 'Overtime',
						data: ot,
						borderColor: colors.amber,
						backgroundColor: colors.amber + '22',
						fill: true,
						tension: 0.35,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
				scales: {
					y: { beginAtZero: true, grid: { color: colors.border } },
					x: { grid: { display: false } },
				},
			},
		});

		if (wrap) wrap.classList.remove('is-loading');
	}

	function queryParams() {
		const params = new URLSearchParams(window.location.search);
		return {
			preset: params.get('preset') || 'this_month',
			date_from: params.get('date_from') || '',
			date_to: params.get('date_to') || '',
		};
	}

	function exportClient(format) {
		const app = document.getElementById('etwa-client-analytics-app');
		const clientId = app?.dataset.clientId;
		if (!clientId || !cfg.canExport) return;

		const q = queryParams();
		const $btn = format === 'csv' ? $('#etwa-ca-export-csv') : $('#etwa-ca-export-excel');
		ui.setButtonLoading($btn[0], true, cfg.i18n?.exporting || 'Exporting…');

		const body = JSON.stringify({
			preset: q.preset,
			date_from: q.date_from,
			date_to: q.date_to,
		});

		api(`client-analytics/${clientId}/export/${format}`, { method: 'POST', body })
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				if (res.data?.file_url) {
					window.location.href = res.data.file_url;
					ui.success(cfg.i18n?.exportReady || 'Export ready.');
				}
			})
			.catch((err) => ui.error(err.message))
			.finally(() => ui.setButtonLoading($btn[0], false));
	}

	function exportReport(reportType, button) {
		const app = document.getElementById('etwa-client-analytics-app');
		const clientId = parseInt(app?.dataset.clientId || '0', 10);
		if (!clientId || !cfg.canExport || !window.etwaDateRange) return;

		const q = queryParams();
		const defaults = window.etwaDateRange.defaultRange();

		window.etwaDateRange.loadFilters().then((filters) => {
			window.etwaDateRange
				.promptDateRangeExport({
					title:
						reportType === 'client_labor_cost'
							? cfg.i18n?.exportLaborCostReport || 'Export Labor Cost Report'
							: cfg.i18n?.exportClientReport || 'Export Client Report',
					date_from: q.date_from || defaults.date_from,
					date_to: q.date_to || defaults.date_to,
					client_id: clientId,
					client_name: app?.dataset.clientName || '',
					fields: {
						client: { required: true, readonly: true },
					},
					filters,
				})
				.then((params) => {
					if (!params) return;
					params.client_id = clientId;
					return window.etwaDateRange.exportReport(reportType, params, button);
				})
				.catch((err) => ui.error(err.message));
		});
	}

	function bindListSearch() {
		let timer;
		$('#etwa-ca-search').on('input', function () {
			clearTimeout(timer);
			const q = $(this).val().trim();
			timer = setTimeout(() => {
				const url = new URL(window.location.href);
				if (q) url.searchParams.set('search', q);
				else url.searchParams.delete('search');
				url.searchParams.delete('ca_page');
				window.location.href = url.toString();
			}, 400);
		});
	}

	function initDetail() {
		const raw = document.getElementById('etwa-ca-detail-data')?.textContent;
		if (!raw) return;
		try {
			const data = JSON.parse(raw);
			buildTrendChart(data.trend || []);
		} catch (e) {
			buildTrendChart([]);
		}

		$('#etwa-ca-export-excel').on('click', () => exportClient('excel'));
		$('#etwa-ca-export-csv').on('click', () => exportClient('csv'));
		$('#etwa-ca-export-client-report').on('click', function () {
			exportReport('client', this);
		});
		$('#etwa-ca-export-labor-cost').on('click', function () {
			exportReport('client_labor_cost', this);
		});
	}

	$(function () {
		if (!$('#etwa-client-analytics-app').length) return;

		const mode = $('#etwa-client-analytics-app').data('mode');
		if (mode === 'detail') {
			initDetail();
		} else {
			bindListSearch();
		}

		$('.etwa-date-preset-row .etwa-tab').on('click', function () {
			$('.etwa-custom-range-fields').removeClass('is-visible');
		});
		$('button[name="preset"][value="custom"]').on('click', () => {
			$('.etwa-custom-range-fields').addClass('is-visible');
		});
	});
})(jQuery);
