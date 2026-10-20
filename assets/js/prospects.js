(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	if (!cfg.canManage) return;

	const api = (path, options = {}) => window.etwaAPI.api(path, options);
	const i18n = cfg.i18n || {};
	const $app = () => $('#etwa-prospects-app');

	let statusFilter = '';

	function esc(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function statusLabel(status) {
		const map = {
			open: i18n.statusOpen || 'Open',
			won: i18n.statusWon || 'Won',
			lost: i18n.statusLost || 'Lost',
		};
		return map[status] || status;
	}

	function sourceLabel(source) {
		const map = cfg.sourceLabels || {
			referrals: i18n.sourceReferrals || 'Referrals',
			social_media: i18n.sourceSocialMedia || 'Social Media',
			direct_contact: i18n.sourceDirectContact || 'Direct Contact',
			campaigns: i18n.sourceCampaigns || 'Campaigns',
			events: i18n.sourceEvents || 'Events',
		};
		return source ? map[source] || source : '—';
	}

	function todayDate() {
		return cfg.today || new Date().toISOString().slice(0, 10);
	}

	function formatDate(value) {
		if (!value) return '—';
		const day = String(value).slice(0, 10);
		if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return esc(value);
		return esc(day);
	}

	function detailUrl(id) {
		const url = new URL(window.location.href);
		url.searchParams.set($app().closest('.etwa-in-portal').length ? 'etwa_view' : 'page', url.searchParams.get('etwa_view') || url.searchParams.get('page') || 'etwa-prospects');
		if ($app().closest('.etwa-in-portal').length) {
			url.searchParams.set('etwa_view', 'prospects');
		} else {
			url.searchParams.set('page', 'etwa-prospects');
		}
		url.searchParams.set('prospect_id', String(id));
		return url.toString();
	}

	function listUrl() {
		const url = new URL(window.location.href);
		url.searchParams.delete('prospect_id');
		if ($app().closest('.etwa-in-portal').length) {
			url.searchParams.set('etwa_view', 'prospects');
		} else {
			url.searchParams.set('page', 'etwa-prospects');
		}
		return url.toString();
	}

	function clientUrl(clientId) {
		const workspace = cfg.workspaceUrl || '';
		if (workspace) return workspace;
		const base = cfg.clientAnalyticsUrl || '';
		if (!base || !clientId) return '';
		const join = base.indexOf('?') === -1 ? '?' : '&';
		return `${base}${join}client_id=${encodeURIComponent(clientId)}`;
	}

	function suggestCode(name) {
		return String(name || '')
			.toUpperCase()
			.replace(/[^A-Z0-9]+/g, '_')
			.replace(/^_|_$/g, '')
			.slice(0, 20);
	}

	function openModal(id) {
		$(id).prop('hidden', false).attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModals() {
		$('.etwa-modal').prop('hidden', true).attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	function actionsHtml(p) {
		const parts = [`<a class="etwa-link" href="${esc(detailUrl(p.id))}">${i18n.view || 'View'}</a>`];
		if (p.status === 'open') {
			parts.push(`<button type="button" class="etwa-link etwa-prospect-edit" data-id="${p.id}">${i18n.edit || 'Edit'}</button>`);
			parts.push(`<button type="button" class="etwa-link etwa-prospect-won" data-id="${p.id}">${i18n.markWon || 'Mark Won'}</button>`);
			parts.push(`<button type="button" class="etwa-link etwa-prospect-lost" data-id="${p.id}" data-name="${esc(p.name)}">${i18n.markLost || 'Mark Lost'}</button>`);
		} else if (p.status === 'won') {
			if (!p.is_converted && !p.converted_client_id) {
				parts.push(`<button type="button" class="etwa-link etwa-prospect-convert" data-id="${p.id}" data-name="${esc(p.name)}">${i18n.convertToClient || 'Convert to Client'}</button>`);
			} else if (p.converted_client_id) {
				const href = clientUrl(p.converted_client_id);
				if (href) {
					parts.push(`<a class="etwa-link" href="${esc(href)}">${i18n.viewConvertedClient || 'View converted Client'}</a>`);
				}
			}
		} else if (p.status === 'lost') {
			parts.push(`<button type="button" class="etwa-link etwa-prospect-edit" data-id="${p.id}">${i18n.edit || 'Edit'}</button>`);
			parts.push(`<button type="button" class="etwa-link etwa-prospect-view-reason" data-id="${p.id}">${i18n.viewLostReason || 'View Lost Reason'}</button>`);
		}
		return parts.join(' ');
	}

	function renderRows(items) {
		if (!items.length) {
			return `<tr><td colspan="7" class="etwa-empty-cell">${i18n.noProspects || 'No prospects found.'}</td></tr>`;
		}
		return items
			.map(
				(p) => `
			<tr data-id="${p.id}" data-name="${esc(p.name)}" data-status="${esc(p.status)}" data-lost-reason="${esc(p.lost_reason || '')}" data-converted="${p.converted_client_id || ''}">
				<td><strong>${esc(p.name)}</strong></td>
				<td><span class="etwa-status etwa-status-${esc(p.status)}">${esc(statusLabel(p.status))}</span></td>
				<td>${esc(sourceLabel(p.source))}</td>
				<td>${formatDate(p.prospect_start_date)}</td>
				<td>${formatDate(p.created_at)}</td>
				<td>${formatDate(p.won_lost_at)}</td>
				<td class="etwa-actions-cell">${actionsHtml(p)}</td>
			</tr>`
			)
			.join('');
	}

	function loadList() {
		const params = new URLSearchParams();
		if (statusFilter) params.set('status', statusFilter);
		const qs = params.toString();
		$('#etwa-prospects-body').html(
			'<tr class="etwa-skeleton-row"><td colspan="7"><div class="etwa-skeleton" style="height:40px"></div></td></tr>'
		);
		api(`prospects/all${qs ? `?${qs}` : ''}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				$('#etwa-prospects-body').html(renderRows(res.data || []));
			})
			.catch((err) => {
				$('#etwa-prospects-body').html(`<tr><td colspan="7">${esc(err.message)}</td></tr>`);
			});
	}

	function hoursLabel(value) {
		return `${Number(value || 0).toFixed(2)}h`;
	}

	function renderDetail(p) {
		const converted = p.converted_client;
		const convertedHtml = converted
			? `<a class="etwa-link" href="${esc(clientUrl(converted.id))}">${esc(converted.name)} (${esc(converted.code)})</a>`
			: p.status === 'won'
				? esc(i18n.notConvertedYet || 'Not converted yet')
				: '—';

		const entries = p.entries || [];
		const entryRows = entries.length
			? entries
					.map(
						(e) => `<tr>
							<td>${esc(e.entry_date)}</td>
							<td>${esc(e.employee_name || '')}</td>
							<td>${esc(e.time_range_label || `${e.start_time} – ${e.end_time}`)}</td>
							<td>${esc(e.task || '')}</td>
							<td>${hoursLabel(Number(e.regular_hours || 0) + Number(e.overtime_hours || 0))}</td>
						</tr>`
					)
					.join('')
			: `<tr><td colspan="5" class="etwa-empty-cell">${i18n.noProspectEntries || 'No working hours recorded against this prospect.'}</td></tr>`;

		const lostBlock =
			p.status === 'lost'
				? `<dt>${i18n.lostReason || 'Lost reason'}</dt><dd>${esc(p.lost_reason || '—')}</dd>`
				: '';

		return `
			<div class="etwa-toolbar">
				<h2>${esc(p.name)}</h2>
				<span class="etwa-status etwa-status-${esc(p.status)}">${esc(statusLabel(p.status))}</span>
			</div>
			<dl class="etwa-definition-list">
				<dt>${i18n.status || 'Status'}</dt><dd>${esc(statusLabel(p.status))}</dd>
				<dt>${i18n.source || 'Source'}</dt><dd>${esc(sourceLabel(p.source))}</dd>
				<dt>${i18n.startDate || 'Start Date'}</dt><dd>${formatDate(p.prospect_start_date)}</dd>
				<dt>${i18n.createdDate || 'Created date'}</dt><dd>${formatDate(p.created_at)}</dd>
				<dt>${i18n.wonLostDate || 'Won/Lost date'}</dt><dd>${formatDate(p.won_lost_at)}</dd>
				${lostBlock}
				<dt>${i18n.conversionStatus || 'Conversion status'}</dt><dd>${p.is_converted ? esc(i18n.converted || 'Converted') : esc(i18n.notConverted || 'Not converted')}</dd>
				<dt>${i18n.convertedClient || 'Converted client'}</dt><dd>${convertedHtml}</dd>
				<dt>${i18n.totalHours || 'Total working hours'}</dt><dd><strong>${hoursLabel(p.total_hours)}</strong> (${Number(p.entry_count || 0)} ${i18n.entries || 'entries'})</dd>
			</dl>
			<div class="etwa-toolbar" style="margin-top:16px">
				${p.status === 'open' ? `<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-won" data-id="${p.id}">${i18n.markWon || 'Mark Won'}</button>
					<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-lost" data-id="${p.id}" data-name="${esc(p.name)}">${i18n.markLost || 'Mark Lost'}</button>
					<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-edit" data-id="${p.id}">${i18n.edit || 'Edit'}</button>` : ''}
				${p.status === 'won' && !p.is_converted ? `<button type="button" class="etwa-btn etwa-btn-primary etwa-prospect-convert" data-id="${p.id}" data-name="${esc(p.name)}">${i18n.convertToClient || 'Convert to Client'}</button>` : ''}
				${p.status === 'lost' ? `<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-edit" data-id="${p.id}">${i18n.edit || 'Edit'}</button>` : ''}
			</div>
			<h3>${i18n.relatedEntries || 'Related entries / work'}</h3>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th>${i18n.date || 'Date'}</th>
							<th>${i18n.employee || 'Employee'}</th>
							<th>${i18n.time || 'Time'}</th>
							<th>${i18n.task || 'Task'}</th>
							<th>${i18n.hours || 'Hours'}</th>
						</tr>
					</thead>
					<tbody>${entryRows}</tbody>
				</table>
			</div>`;
	}

	function loadDetail(id) {
		$('#etwa-prospect-detail-body').html('<div class="etwa-skeleton" style="height:120px"></div>');
		api(`prospects/${id}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				$('#etwa-prospect-detail-body').html(renderDetail(res.data || {}));
			})
			.catch((err) => {
				$('#etwa-prospect-detail-body').html(`<p>${esc(err.message)}</p>`);
			});
	}

	function showList() {
		$app().attr('data-mode', 'list').attr('data-prospect-id', '');
		$('#etwa-prospects-detail').prop('hidden', true);
		$('#etwa-prospects-list').prop('hidden', false);
		if (window.history && window.history.replaceState) {
			window.history.replaceState({}, '', listUrl());
		}
		loadList();
	}

	function showDetail(id) {
		$app().attr('data-mode', 'detail').attr('data-prospect-id', String(id));
		$('#etwa-prospects-list').prop('hidden', true);
		$('#etwa-prospects-detail').prop('hidden', false);
		if (window.history && window.history.replaceState) {
			window.history.replaceState({}, '', detailUrl(id));
		}
		loadDetail(id);
	}

	function currentProspectFromRow(id) {
		const $row = $(`#etwa-prospects-body tr[data-id="${id}"]`);
		if ($row.length) {
			return {
				id,
				name: $row.attr('data-name') || '',
				status: $row.attr('data-status') || '',
				lost_reason: $row.attr('data-lost-reason') || '',
			};
		}
		return { id, name: '', status: $app().attr('data-mode') === 'detail' ? '' : 'open', lost_reason: '' };
	}

	function afterMutation(id) {
		if ($app().attr('data-mode') === 'detail') {
			loadDetail(id || $app().attr('data-prospect-id'));
		} else {
			loadList();
		}
	}

	$(function () {
		if (!$app().length) return;

		if ($app().attr('data-mode') === 'detail') {
			loadDetail($app().attr('data-prospect-id'));
		} else {
			loadList();
		}

		$('#etwa-prospect-status').on('change', function () {
			statusFilter = $(this).val();
			loadList();
		});

		$('#etwa-prospect-back').on('click', function (e) {
			e.preventDefault();
			showList();
		});

		$('#etwa-prospect-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, i18n.saving);
			api('prospects', {
				method: 'POST',
				body: JSON.stringify({
					name: data.name,
					source: data.source,
					prospect_start_date: data.prospect_start_date,
				}),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					$(this).find('[name="prospect_start_date"]').val(todayDate());
					ui.success(i18n.prospectSaved || 'Prospect saved.');
					loadList();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$(document).on('click', '.etwa-prospect-edit', function () {
			const id = $(this).data('id');
			api(`prospects/${id}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					return res.data;
				})
				.then((p) => {
					$('#etwa-prospect-edit-id').val(p.id);
					$('#etwa-prospect-edit-name').val(p.name || '');
					$('#etwa-prospect-edit-source').val(p.source || '');
					$('#etwa-prospect-edit-start-date').val(p.prospect_start_date || '');
					const isLost = p.status === 'lost';
					$('#etwa-prospect-edit-reason-wrap').prop('hidden', !isLost);
					$('#etwa-prospect-edit-reason').prop('required', isLost).val(p.lost_reason || '');
					openModal('#etwa-prospect-edit-modal');
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-prospect-edit-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const payload = {
				name: data.name,
				source: data.source,
				prospect_start_date: data.prospect_start_date,
			};
			if (!$('#etwa-prospect-edit-reason-wrap').prop('hidden')) {
				payload.lost_reason = data.lost_reason;
			}
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, i18n.saving);
			api(`prospects/${data.id}`, { method: 'PUT', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeModals();
					ui.success(i18n.prospectUpdated || 'Prospect updated.');
					afterMutation(data.id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$(document).on('click', '.etwa-prospect-won', function () {
			const id = $(this).data('id');
			const confirmFn = ui.confirmDialog || ui.confirm;
			Promise.resolve(confirmFn(i18n.confirmMarkWon || 'Mark this prospect as won?', i18n.markWon || 'Mark Won')).then(
				(ok) => {
					if (!ok) return;
					api(`prospects/${id}/won`, { method: 'POST', body: '{}' })
						.then((res) => {
							if (!res.success) throw new Error(res.message);
							ui.success(i18n.prospectWon || 'Prospect marked as won.');
							afterMutation(id);
						})
						.catch((err) => ui.error(err.message));
				}
			);
		});

		$(document).on('click', '.etwa-prospect-lost', function () {
			$('#etwa-prospect-lost-id').val($(this).data('id'));
			$('#etwa-prospect-lost-name').text($(this).data('name') || '');
			$('#etwa-prospect-lost-reason').val('');
			openModal('#etwa-prospect-lost-modal');
		});

		$('#etwa-prospect-lost-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, i18n.saving);
			api(`prospects/${data.id}/lost`, { method: 'POST', body: JSON.stringify({ lost_reason: data.lost_reason }) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeModals();
					ui.success(i18n.prospectLost || 'Prospect marked as lost.');
					afterMutation(data.id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$(document).on('click', '.etwa-prospect-view-reason', function () {
			const $row = $(this).closest('tr');
			if ($row.length) {
				$('#etwa-prospect-reason-name').text($row.attr('data-name') || '');
				$('#etwa-prospect-reason-text').text($row.attr('data-lost-reason') || '—');
				openModal('#etwa-prospect-reason-modal');
				return;
			}
			api(`prospects/${$(this).data('id')}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					$('#etwa-prospect-reason-name').text(res.data.name || '');
					$('#etwa-prospect-reason-text').text(res.data.lost_reason || '—');
					openModal('#etwa-prospect-reason-modal');
				})
				.catch((err) => ui.error(err.message));
		});

		$(document).on('click', '.etwa-prospect-convert', function () {
			const id = $(this).data('id');
			const name = $(this).data('name') || '';
			$('#etwa-prospect-convert-id').val(id);
			$('#etwa-prospect-convert-name').val(name);
			$('#etwa-prospect-convert-code').val(suggestCode(name));
			$('#etwa-prospect-convert-department').val('General');
			openModal('#etwa-prospect-convert-modal');
		});

		$('#etwa-prospect-convert-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, i18n.saving);
			api(`prospects/${data.id}/convert`, {
				method: 'POST',
				body: JSON.stringify({
					name: String(data.name || '').trim(),
					code: String(data.code || '').trim(),
					department: String(data.department || 'General').trim() || 'General',
				}),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeModals();
					ui.success(i18n.prospectConverted || 'Prospect converted to client.');
					afterMutation(data.id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('.etwa-prospect-modal-close').on('click', closeModals);
		$('.etwa-modal').on('click', function (e) {
			if ($(e.target).is('.etwa-modal')) closeModals();
		});
	});
})(jQuery);
