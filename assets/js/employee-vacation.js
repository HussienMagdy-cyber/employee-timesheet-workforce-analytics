(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	if (!$('#etwa-my-vacation-app').length || !cfg.canViewOwnVacation) return;

	let historyPage = 1;
	let requestsPage = 1;
	let leaveTypes = [];
	let myVacationCalendar = null;

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

	function renderPrimaryBalance(balances, pool) {
		const primary = balances.find((b) => b.is_primary_pool) || null;
		const allocated = primary?.allocated ?? pool?.allocated ?? 0;
		const used = primary?.used ?? pool?.used ?? 0;
		const remaining = primary?.remaining ?? pool?.remaining ?? 0;
		const name = primary?.leave_type_name || cfg.i18n?.annualLeave || 'Annual Leave';

		$('#etwa-my-vacation-cards').html(`
			<div class="etwa-vacation-card etwa-vacation-card-primary">
				<h4>${name}</h4>
				<p class="etwa-muted etwa-card-note">${cfg.i18n?.annualPoolNote || 'All leave types (except unpaid) deduct from this balance.'}</p>
				<div class="etwa-vacation-stat"><span>${cfg.i18n?.allocated || 'Allocated'}</span><strong>${allocated}</strong></div>
				<div class="etwa-vacation-stat"><span>${cfg.i18n?.used || 'Used'}</span><strong>${used}</strong></div>
				<div class="etwa-vacation-stat etwa-vacation-remaining"><span>${cfg.i18n?.remaining || 'Remaining'}</span><strong>${remaining}</strong></div>
			</div>
		`);

		const breakdown = balances.filter((b) => b.usage_only && (b.used || 0) > 0);
		if (breakdown.length) {
			$('#etwa-my-vacation-cards').append(
				`<div class="etwa-vacation-breakdown-wrap"><p class="etwa-muted">${cfg.i18n?.usageBreakdown || 'Usage by type:'}</p><div class="etwa-vacation-cards etwa-vacation-usage-cards">${breakdown
					.map(
						(b) => `<div class="etwa-vacation-card etwa-vacation-card-sm"><h4>${b.leave_type_name}</h4><div class="etwa-vacation-stat"><span>${cfg.i18n?.used || 'Used'}</span><strong>${b.used}</strong></div></div>`
					)
					.join('')}</div></div>`
			);
		}
	}

	function renderNotification(n) {
		if (n.type === 'vacation_rejected') {
			return `<div class="etwa-alert etwa-alert-warning etwa-vacation-notice">
				<strong>${n.leave_type_name} ${cfg.i18n?.rejected || 'Rejected'}</strong><br />
				${n.start_date} – ${n.end_date}
				${ui.rejectionReasonNotice(n.rejection_reason || n.notes)}
			</div>`;
		}
		return `<div class="etwa-alert etwa-alert-info etwa-vacation-notice">
			<strong>${n.leave_type_name} ${cfg.i18n?.approved || 'Approved'}</strong><br />
			${n.start_date} – ${n.end_date} · ${n.days} ${cfg.i18n?.days || 'days'}<br />
			${cfg.i18n?.remainingBalance || 'Remaining annual balance'}: ${n.remaining} ${cfg.i18n?.days || 'days'}
		</div>`;
	}

	function loadNotifications() {
		api('vacations/notifications')
			.then((res) => {
				if (!res.success) return;
				const items = res.data?.items || [];
				if (!items.length) return;
				$('#etwa-vacation-notifications').html(items.map(renderNotification).join(''));
				api('vacations/notifications', { method: 'PUT', body: '{}' });
			})
			.catch(() => {});
	}

	function reasonCell(r) {
		if (r.status === 'rejected' && (r.rejection_reason || r.notes)) {
			const label = cfg.i18n?.rejectionReason || 'Rejection reason';
			return `${r.reason || ''}${r.reason ? '<br />' : ''}<strong>${label}:</strong> ${r.rejection_reason || r.notes}`;
		}
		return r.reason || '';
	}

	function showVacationDetail(id) {
		api(`vacations/requests/${id}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const r = res.data || {};
				const timeline = ui.timelineHtml(r.approval_timeline || r.approval_history || []);
				$('#etwa-vacation-detail-body').html(`
					<dl class="etwa-detail-list">
						<dt>${cfg.i18n?.leaveType || 'Leave Type'}</dt><dd>${r.leave_type_name || ''}</dd>
						<dt>${cfg.i18n?.startDate || 'Start Date'}</dt><dd>${r.start_date || ''}</dd>
						<dt>${cfg.i18n?.endDate || 'End Date'}</dt><dd>${r.end_date || ''}</dd>
						<dt>${cfg.i18n?.days || 'Days'}</dt><dd>${formatRequestDays(r) || '—'}</dd>
						<dt>${cfg.i18n?.submittedDate || 'Submitted Date'}</dt><dd>${r.created_at_label || ui.formatDateTime12(r.created_at) || ''}</dd>
						<dt>${cfg.i18n?.status || 'Status'}</dt><dd>${r.status_label || r.status || ''}</dd>
						<dt>${cfg.i18n?.reason || 'Reason'}</dt><dd>${r.reason || ''}</dd>
						${ui.requestReviewDetailRows(r)}
					</dl>
					${timeline}
				`);
				$('#etwa-vacation-detail-modal').removeAttr('hidden').attr('aria-hidden', 'false');
			})
			.catch((err) => ui.error(err.message));
	}

	function loadRequests() {
		api(`vacations/requests?page=${requestsPage}&per_page=25`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-my-vacation-requests').html(
						`<tr><td colspan="6" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests yet.'}</td></tr>`
					);
					$('#etwa-my-requests-pagination').empty();
					return;
				}
				$('#etwa-my-vacation-requests').html(
					items
						.map((r) => {
							const cancel =
								r.status === 'pending'
									? `<button type="button" class="etwa-link etwa-cancel-request" data-id="${r.id}">${cfg.i18n?.cancel || 'Cancel'}</button>`
									: '';
							const view =
								r.status !== 'pending'
									? `<button type="button" class="etwa-link etwa-view-vacation-request" data-id="${r.id}">${cfg.i18n?.view || 'View'}</button>`
									: '';
							const actions = [view, cancel].filter(Boolean).join(' ');
							return `<tr class="etwa-vacation-request-row" data-id="${r.id}">
						<td>${r.leave_type_name}</td>
						<td>${r.start_date} – ${r.end_date}</td>
						<td>${formatRequestDays(r)}</td>
						<td><span class="etwa-status etwa-status-${r.status}">${r.status_label || r.status}</span></td>
						<td>${reasonCell(r)}</td>
						<td>${actions}</td>
					</tr>`;
						})
						.join('')
				);
				const pages = Math.max(1, Math.ceil((data.total || 0) / 25));
				let pag = '';
				for (let i = 1; i <= pages; i += 1) {
					pag += `<button type="button" class="etwa-btn etwa-btn-secondary${i === requestsPage ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
				}
				$('#etwa-my-requests-pagination').html(pag);
			})
			.catch((err) => ui.error(err.message));
	}

	function loadHistory() {
		api(`vacations/history?page=${historyPage}&per_page=25`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-my-vacation-history').html(
						`<tr><td colspan="6" class="etwa-empty-cell">${cfg.i18n?.noHistory || 'No history yet.'}</td></tr>`
					);
					return;
				}
				$('#etwa-my-vacation-history').html(
					items
						.map(
							(row) => `<tr>
						<td>${row.event_date}</td>
						<td>${row.leave_type_name}</td>
						<td>${row.transaction_label}</td>
						<td>${Math.abs(row.days)}</td>
						<td>${row.status || '—'}</td>
						<td>${row.reason || ''}</td>
					</tr>`
						)
						.join('')
				);
				const pages = Math.max(1, Math.ceil((data.total || 0) / 25));
				let pag = '';
				for (let i = 1; i <= pages; i += 1) {
					pag += `<button type="button" class="etwa-btn etwa-btn-secondary${i === historyPage ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
				}
				$('#etwa-my-vacation-pagination').html(pag);
			})
			.catch((err) => ui.error(err.message));
	}

	function formatRequestDays(r) {
		if (r.days_display) {
			return r.days_display;
		}
		if (r.status === 'pending' && (!r.days || Number(r.days) <= 0)) {
			return cfg.i18n?.pendingReview || 'Pending review';
		}
		return r.days ?? '—';
	}

	$(function () {
		loadNotifications();

		api('vacations/leave-types')
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				leaveTypes = res.data || [];
				$('#etwa-request-leave-type').html(
					leaveTypes.map((t) => `<option value="${t.id}">${t.name}</option>`).join('')
				);
			})
			.catch((err) => ui.error(err.message));

		api('vacations/my')
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				$('#etwa-my-vacation-name').text(data.employee?.display_name || '');
				renderPrimaryBalance(data.balances || [], data.annual_pool || null);
			})
			.catch((err) => ui.error(err.message));

		loadRequests();
		loadHistory();

		myVacationCalendar = window.etwaVacationCalendar?.init({
			prefix: 'etwa-my-vacation',
			api,
			cfg,
			ui,
			highlightUserId: cfg.userId || null,
		});

		$('#etwa-my-vacation-app .etwa-vacation-tab').on('click', function () {
			const tab = $(this).data('tab');
			$('#etwa-my-vacation-app .etwa-vacation-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$('#etwa-my-vacation-app .etwa-vacation-panel').attr('hidden', 'hidden').removeClass('is-active');
			$(`#etwa-my-vacation-app .etwa-vacation-panel[data-panel="${tab}"]`).removeAttr('hidden').addClass('is-active');
			if (tab === 'calendar' && myVacationCalendar) myVacationCalendar.load();
			if (tab === 'requests') loadRequests();
			if (tab === 'history') loadHistory();
		});

		$('#etwa-vacation-request-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			delete data.days;
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api('vacations/requests', { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					ui.success(cfg.i18n?.requestSubmitted || 'Request submitted for HR review.');
					loadRequests();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-my-vacation-requests').on('click', '.etwa-cancel-request', function (e) {
			e.stopPropagation();
			const id = $(this).data('id');
			ui.confirm(cfg.i18n?.confirmCancelRequest || 'Cancel this request?', cfg.i18n?.cancel || 'Cancel').then((ok) => {
				if (!ok) return;
				api(`vacations/requests/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.requestCancelled || 'Request cancelled.');
						loadRequests();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-my-vacation-requests').on('click', '.etwa-vacation-request-row', function (e) {
			if ($(e.target).closest('button').length) return;
			showVacationDetail($(this).data('id'));
		});

		$('#etwa-my-vacation-requests').on('click', '.etwa-view-vacation-request', function (e) {
			e.stopPropagation();
			showVacationDetail($(this).data('id'));
		});

		$('#etwa-vacation-detail-modal').on('click', '.etwa-modal-close', function () {
			$('#etwa-vacation-detail-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
		});

		$('#etwa-my-requests-pagination').on('click', 'button[data-page]', function () {
			requestsPage = parseInt($(this).data('page'), 10) || 1;
			loadRequests();
		});

		$('#etwa-my-vacation-pagination').on('click', 'button[data-page]', function () {
			historyPage = parseInt($(this).data('page'), 10) || 1;
			loadHistory();
		});
	});
})(jQuery);
