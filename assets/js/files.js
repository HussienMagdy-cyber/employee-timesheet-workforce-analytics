/**
 * Client Files — list and client file detail.
 */
(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	const $app = $('#etwa-files-app');
	if (!$app.length || typeof window.etwaAPI === 'undefined') {
		return;
	}

	const canAnalytics = Number($app.attr('data-can-analytics')) === 1 || !!cfg.canViewAnalytics;
	const canAllocate = Number($app.attr('data-can-allocate')) === 1 || !!cfg.canAllocateDeliverables;
	const filesUrl = $app.attr('data-files-url') || '';
	const deliverablesUrl = $app.attr('data-deliverables-url') || '';
	const clientAnalyticsUrl = $app.attr('data-client-analytics-url') || '';

	const state = {
		mode: $app.attr('data-mode') || 'list',
		clientId: parseInt($app.attr('data-client-id'), 10) || 0,
		items: [],
		file: null,
		searchTimer: null,
	};

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

	function fail(resOrErr, fallback) {
		const message =
			(resOrErr && resOrErr.message) ||
			(typeof resOrErr === 'string' ? resOrErr : '') ||
			fallback ||
			'Something went wrong.';
		if (ui.error) {
			ui.error(message);
		}
	}

	function withQuery(base, params) {
		try {
			const url = new URL(base, window.location.origin);
			Object.keys(params || {}).forEach((k) => {
				if (params[k] === null || params[k] === undefined || params[k] === '') {
					url.searchParams.delete(k);
				} else {
					url.searchParams.set(k, String(params[k]));
				}
			});
			return url.pathname + url.search + url.hash;
		} catch (e) {
			const qs = Object.keys(params || {})
				.filter((k) => params[k] !== null && params[k] !== undefined && params[k] !== '')
				.map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`)
				.join('&');
			return qs ? `${base}${base.indexOf('?') >= 0 ? '&' : '?'}${qs}` : base;
		}
	}

	function fileUrl(clientId) {
		return withQuery(filesUrl, { client_id: clientId });
	}

	function scopeUrl(scopeId) {
		return withQuery(deliverablesUrl, { scope_id: scopeId });
	}

	function analyticsUrl(clientId) {
		return withQuery(clientAnalyticsUrl, { client_id: clientId });
	}

	function progressHtml(delivered, planned, completion, extra) {
		const d = num(delivered);
		const p = num(planned);
		const pct = num(completion);
		const ex = num(extra);
		const exceeded = pct > 100 || ex > 0;
		return `
			<div class="etwa-files-progress ${exceeded ? 'is-exceeded' : ''}">
				<div class="etwa-files-progress-meta">
					<span>${d.toFixed(0)} / ${p.toFixed(0)}</span>
					<strong>${pct.toFixed(1)}%</strong>
				</div>
				<div class="etwa-files-progress-track"><span style="width:${pctBar(pct)}%"></span></div>
				${
					exceeded
						? `<p class="etwa-files-extra">+${ex > 0 ? ex.toFixed(0) : Math.max(0, d - p).toFixed(0)} Extra Deliverables</p>`
						: ''
				}
			</div>
		`;
	}

	function statusClass(status, exceeded) {
		if (exceeded) {
			return 'etwa-status etwa-status-exceeded';
		}
		const s = String(status || 'active').toLowerCase();
		return `etwa-status etwa-status-${esc(s)}`;
	}

	function periodLabel(scope) {
		const a = scope.starts_on || '';
		const b = scope.ends_on || '';
		if (a && b) {
			return `${a} → ${b}`;
		}
		if (a) {
			return `From ${a}`;
		}
		if (b) {
			return `Until ${b}`;
		}
		return 'No period set';
	}

	function renderFileCard(file) {
		const client = file.client || {};
		const t = file.totals || {};
		const exceeded = !!t.exceeded || num(t.completion_pct) > 100;
		const analyticsBtn = canAnalytics
			? `<a class="etwa-btn etwa-btn-ghost etwa-btn-small" href="${esc(analyticsUrl(client.id))}" data-stop>View Client</a>`
			: '';
		return `
			<article class="etwa-file-card" data-client-id="${esc(client.id)}" role="button" tabindex="0">
				<header class="etwa-file-card-header">
					<div>
						<p class="etwa-muted etwa-file-eyebrow">Client File</p>
						<h3>${esc(client.name || 'Client')}</h3>
					</div>
					<span class="${statusClass('active', exceeded)}">${exceeded ? 'Exceeded' : 'Active'}</span>
				</header>
				<div class="etwa-file-card-body">
					<div class="etwa-file-metrics">
						<div><span class="etwa-kpi-label">Scopes</span><strong>${num(t.scope_count)}</strong></div>
						<div><span class="etwa-kpi-label">Active</span><strong>${num(t.active_scope_count)}</strong></div>
						<div><span class="etwa-kpi-label">Contract</span><strong>${num(t.contract_deliverables).toFixed(0)}</strong></div>
						<div><span class="etwa-kpi-label">Delivered</span><strong>${num(t.delivered_qty).toFixed(0)}</strong></div>
					</div>
					${progressHtml(t.delivered_qty, t.planned_qty || t.contract_deliverables, t.completion_pct, t.extra_qty)}
				</div>
				<footer class="etwa-file-card-footer">
					<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="${esc(fileUrl(client.id))}" data-stop>Open File</a>
					${analyticsBtn}
				</footer>
			</article>
		`;
	}

	function renderScopeCard(row) {
		const scope = row.scope || {};
		const m = row.metrics || {};
		const exceeded = !!row.exceeded || num(m.completion_pct) > 100;
		const plannedEffort = num(row.planned_effort != null ? row.planned_effort : m.planned_qty);
		const completedEffort = num(row.completed_effort != null ? row.completed_effort : m.effort_score);
		const manageLabel = canAllocate ? 'Manage Deliverables' : 'Open Scope';
		return `
			<article class="etwa-scope-file-card">
				<header class="etwa-file-card-header">
					<div>
						<p class="etwa-muted etwa-file-eyebrow">${esc(scope.code || 'Scope')}</p>
						<h3>${esc(scope.name || '')}</h3>
						<p class="etwa-muted">${esc(periodLabel(scope))}</p>
					</div>
					<span class="${statusClass(scope.status, exceeded)}">${exceeded ? 'Exceeded' : esc(scope.status || 'active')}</span>
				</header>
				<div class="etwa-file-card-body">
					<div class="etwa-file-metrics etwa-file-metrics-dense">
						<div><span class="etwa-kpi-label">Contract</span><strong>${num(scope.contract_deliverables).toFixed(0)}</strong></div>
						<div><span class="etwa-kpi-label">Delivered</span><strong>${num(m.completed_qty).toFixed(0)}</strong></div>
						<div><span class="etwa-kpi-label">Remaining</span><strong>${num(m.remaining_qty).toFixed(0)}</strong></div>
						<div><span class="etwa-kpi-label">Extra</span><strong>${num(m.extra_qty).toFixed(0)}</strong></div>
						<div><span class="etwa-kpi-label">Planned Hours</span><strong>${plannedEffort.toFixed(1)}</strong></div>
						<div><span class="etwa-kpi-label">Completed Hours</span><strong>${completedEffort.toFixed(1)}</strong></div>
						<div><span class="etwa-kpi-label">Hours</span><strong>${num(m.effort_score).toFixed(1)}</strong></div>
						<div><span class="etwa-kpi-label">Completion</span><strong>${num(m.completion_pct).toFixed(1)}%</strong></div>
					</div>
					${progressHtml(m.completed_qty, m.planned_qty, m.completion_pct, m.extra_qty)}
				</div>
				<footer class="etwa-file-card-footer">
					<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="${esc(scopeUrl(scope.id))}">Open Scope</a>
					<a class="etwa-btn etwa-btn-secondary etwa-btn-small" href="${esc(scopeUrl(scope.id))}">${esc(manageLabel)}</a>
				</footer>
			</article>
		`;
	}

	function setListKpis() {
		/* no-op — count only */
	}

	function renderList(items) {
		state.items = items || [];
		const $grid = $('#etwa-files-grid').empty();
		const $empty = $('#etwa-files-empty');
		$('#etwa-files-count').text(`${state.items.length} file(s)`);
		if (!state.items.length) {
			$empty.removeAttr('hidden');
			return;
		}
		$empty.attr('hidden', 'hidden');
		state.items.forEach((file) => {
			$grid.append(renderFileCard(file));
		});
	}

	function setFileKpis(totals) {
		const t = totals || {};
		$('[data-file-kpi="scope_count"]').text(num(t.scope_count));
		$('[data-file-kpi="active_scope_count"]').text(num(t.active_scope_count));
		$('[data-file-kpi="contract_deliverables"]').text(num(t.contract_deliverables).toFixed(0));
		$('[data-file-kpi="delivered_qty"]').text(num(t.delivered_qty).toFixed(0));
		$('[data-file-kpi="completion_pct"]').text(`${num(t.completion_pct).toFixed(1)}%`);
		$('[data-file-kpi="effort_score"]').text(num(t.effort_score).toFixed(1));
	}

	function renderDetail(file) {
		state.file = file;
		const client = file.client || {};
		const totals = file.totals || {};
		const scopes = file.scopes || [];

		$('#etwa-file-client-name').text(client.name || 'Client File');
		$('#etwa-file-client-meta').text(
			`${num(totals.scope_count)} scope(s) · ${num(totals.active_scope_count)} active · ${num(totals.completion_pct).toFixed(1)}% complete`
		);
		$('#etwa-files-title').text(client.name || 'Client File');
		$('#etwa-files-subtitle').text('Scopes for this client file');

		const $actions = $('#etwa-file-actions').empty();
		if (canAnalytics) {
			$actions.append(
				`<a class="etwa-btn etwa-btn-secondary" href="${esc(analyticsUrl(client.id))}">View Client</a>`
			);
		}

		setFileKpis(totals);

		const $grid = $('#etwa-file-scopes-grid').empty();
		const $empty = $('#etwa-file-scopes-empty');
		if (!scopes.length) {
			$empty.removeAttr('hidden');
			return;
		}
		$empty.attr('hidden', 'hidden');
		scopes.forEach((row) => $grid.append(renderScopeCard(row)));
	}

	function showList() {
		state.mode = 'list';
		state.clientId = 0;
		$app.attr('data-mode', 'list').attr('data-client-id', '0');
		$('#etwa-files-list').removeClass('etwa-hidden');
		$('#etwa-files-detail').addClass('etwa-hidden');
		$('#etwa-files-title').text('Files');
		$('#etwa-files-subtitle').text('Client files and scopes at a glance');
		try {
			const url = new URL(window.location.href);
			url.searchParams.delete('client_id');
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
		loadList();
	}

	function showFile(clientId) {
		state.mode = 'file';
		state.clientId = clientId;
		$app.attr('data-mode', 'file').attr('data-client-id', String(clientId));
		$('#etwa-files-list').addClass('etwa-hidden');
		$('#etwa-files-detail').removeClass('etwa-hidden');
		try {
			const url = new URL(window.location.href);
			url.searchParams.set('client_id', String(clientId));
			window.history.replaceState({}, '', url.toString());
		} catch (e) {
			/* ignore */
		}
		loadFile(clientId);
	}

	function loadList(search) {
		$app.addClass('is-loading');
		$('#etwa-files-grid').html('<p class="etwa-muted">Loading files…</p>');
		$('#etwa-files-empty').attr('hidden', 'hidden');
		const params = new URLSearchParams();
		if (search) {
			params.set('search', search);
		}
		api(`deliverables/files?${params.toString()}`)
			.then((res) => {
				if (!res.success) {
					fail(res, 'Failed to load files.');
					$('#etwa-files-grid').empty();
					$('#etwa-files-empty').removeAttr('hidden');
					return;
				}
				renderList((res.data && res.data.items) || []);
				setListKpis();
			})
			.catch((err) => {
				fail(err, 'Failed to load files.');
				$('#etwa-files-grid').empty();
				$('#etwa-files-empty').removeAttr('hidden');
			})
			.finally(() => $app.removeClass('is-loading'));
	}

	function loadFile(clientId) {
		$app.addClass('is-loading');
		$('#etwa-file-scopes-grid').html('<p class="etwa-muted">Loading scopes…</p>');
		api(`deliverables/files?client_id=${clientId}`)
			.then((res) => {
				if (!res.success) {
					fail(res, 'Failed to load client file.');
					showList();
					return;
				}
				renderDetail(res.data.file || res.data);
			})
			.catch((err) => {
				fail(err, 'Failed to load client file.');
				showList();
			})
			.finally(() => $app.removeClass('is-loading'));
	}

	// Events
	$('#etwa-files-search').on('input', function () {
		const q = ($(this).val() || '').trim();
		clearTimeout(state.searchTimer);
		state.searchTimer = setTimeout(() => loadList(q), 280);
	});

	$('#etwa-files-back').on('click', function (e) {
		e.preventDefault();
		showList();
	});

	$(document).on('click', '.etwa-file-card', function (e) {
		if ($(e.target).closest('[data-stop], a, button').length) {
			return;
		}
		const id = parseInt($(this).data('client-id'), 10);
		if (id) {
			showFile(id);
		}
	});

	$(document).on('keydown', '.etwa-file-card', function (e) {
		if (e.key === 'Enter' || e.key === ' ') {
			e.preventDefault();
			const id = parseInt($(this).data('client-id'), 10);
			if (id) {
				showFile(id);
			}
		}
	});

	// Boot
	if (state.mode === 'file' && state.clientId) {
		showFile(state.clientId);
	} else {
		loadList();
	}
})(jQuery);
