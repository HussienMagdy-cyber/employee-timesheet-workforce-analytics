(function () {
	'use strict';

	const CHART_COLORS = {
		referrals: '#4f46e5',
		social_media: '#0891b2',
		direct_contact: '#059669',
		campaigns: '#d97706',
		events: '#db2777',
		bar: '#4f46e5',
		grid: 'rgba(115, 115, 115, 0.2)',
		text: '#737373',
	};

	function chartDefaults(withScales) {
		const options = {
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
		};
		if (withScales) {
			options.scales = {
				x: {
					grid: { color: CHART_COLORS.grid, drawBorder: false },
					ticks: { color: CHART_COLORS.text, font: { size: 11 } },
				},
				y: {
					grid: { color: CHART_COLORS.grid, drawBorder: false },
					ticks: { color: CHART_COLORS.text, font: { size: 11 }, precision: 0 },
					beginAtZero: true,
				},
			};
		}
		return options;
	}

	function markLoaded(key) {
		const wrap = document.querySelector(`.etwa-chart-canvas-wrap[data-chart="${key}"]`);
		if (wrap) wrap.classList.remove('is-loading');
	}

	function emptyChart(key, message) {
		const wrap = document.querySelector(`.etwa-chart-canvas-wrap[data-chart="${key}"]`);
		if (!wrap) return;
		wrap.classList.remove('is-loading');
		wrap.innerHTML = `<p class="etwa-insight-empty">${message}</p>`;
	}

	function safeNumber(value) {
		const n = Number(value);
		return Number.isFinite(n) ? n : 0;
	}

	function sourceColor(source, index) {
		return CHART_COLORS[source] || ['#4f46e5', '#0891b2', '#059669', '#d97706', '#db2777'][index % 5];
	}

	function buildCharts(payload) {
		const i18n = (payload && payload.i18n) || {};
		const noData = i18n.noData || 'No data for this period.';
		if (!window.Chart || !payload) {
			emptyChart('acquisition', noData);
			emptyChart('source', i18n.noSource || noData);
			return;
		}

		const acquisition = Array.isArray(payload.acquisition) ? payload.acquisition : [];
		const acquisitionTotal = acquisition.reduce((sum, row) => sum + safeNumber(row.count), 0);
		const acquisitionCanvas = document.getElementById('etwa-pa-acquisition-chart');
		if (!acquisitionCanvas || acquisitionTotal <= 0) {
			emptyChart('acquisition', i18n.noAcquisition || noData);
		} else {
			new window.Chart(acquisitionCanvas, {
				type: 'bar',
				data: {
					labels: acquisition.map((row) => row.label || row.period),
					datasets: [
						{
							label: i18n.prospects || 'Prospects',
							data: acquisition.map((row) => safeNumber(row.count)),
							backgroundColor: CHART_COLORS.bar,
							borderWidth: 0,
							borderRadius: 6,
						},
					],
				},
				options: {
					...chartDefaults(true),
					plugins: {
						...chartDefaults(true).plugins,
						legend: { display: false },
					},
				},
			});
			markLoaded('acquisition');
		}

		const sources = Array.isArray(payload.sources) ? payload.sources : [];
		const sourceValues = sources.map((item) => safeNumber(item.total));
		const sourceTotal = sourceValues.reduce((sum, n) => sum + n, 0);
		const sourceCanvas = document.getElementById('etwa-pa-source-chart');
		if (!sourceCanvas || sourceTotal <= 0) {
			emptyChart('source', i18n.noSource || noData);
		} else {
			new window.Chart(sourceCanvas, {
				type: 'doughnut',
				data: {
					labels: sources.map((item) => item.label),
					datasets: [
						{
							data: sourceValues,
							backgroundColor: sources.map((item, index) => sourceColor(item.source, index)),
							borderWidth: 0,
						},
					],
				},
				options: chartDefaults(false),
			});
			markLoaded('source');
		}
	}

	function initSourceSort() {
		const table = document.getElementById('etwa-pa-source-table');
		if (!table) return;
		const body = table.querySelector('tbody');
		if (!body) return;

		table.querySelectorAll('th.etwa-sortable').forEach((th) => {
			th.addEventListener('click', () => {
				const key = th.getAttribute('data-sort');
				if (!key) return;
				const wasDesc = th.classList.contains('is-sorted') && th.classList.contains('is-desc');
				const nextDir = wasDesc ? 'asc' : 'desc';
				table.querySelectorAll('th.etwa-sortable').forEach((item) => {
					item.classList.remove('is-sorted', 'is-asc', 'is-desc');
				});
				th.classList.add('is-sorted', nextDir === 'asc' ? 'is-asc' : 'is-desc');

				const rows = Array.from(body.querySelectorAll('tr[data-total]'));
				rows.sort((a, b) => {
					const av = Number(a.getAttribute(`data-${key}`));
					const bv = Number(b.getAttribute(`data-${key}`));
					const aVal = Number.isFinite(av) ? av : 0;
					const bVal = Number.isFinite(bv) ? bv : 0;
					return nextDir === 'asc' ? aVal - bVal : bVal - aVal;
				});
				rows.forEach((row) => body.appendChild(row));
			});
		});
	}

	function initDateFilter() {
		const form = document.getElementById('etwa-date-range-form');
		if (!form) return;
		form.querySelectorAll('.etwa-date-preset-row [name="preset"]').forEach((btn) => {
			btn.addEventListener('click', () => {
				const fields = form.querySelector('.etwa-custom-range-fields');
				if (fields) {
					fields.classList.toggle('is-visible', btn.value === 'custom');
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		if (!document.getElementById('etwa-prospect-analytics-app')) return;
		initDateFilter();
		initSourceSort();
		const node = document.getElementById('etwa-pa-chart-data');
		if (!node) return;
		try {
			buildCharts(JSON.parse(node.textContent || '{}'));
		} catch (err) {
			emptyChart('acquisition', 'No data for this period.');
			emptyChart('source', 'No prospects with a Source in this range.');
		}
	});
})();
