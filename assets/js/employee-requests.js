(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const app = $('#etwa-requests-app');
	if (!app.length || !cfg.canViewOwnVacation) return;

	let wfhPage = 1;
	let permissionPage = 1;
	let holidayPage = 1;
	let permissionTypes = {};

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

	function todayIso() {
		return new Date().toISOString().slice(0, 10);
	}

	function setDefaultDates() {
		app.find('[name="request_date"]').each(function () {
			if (!this.value) this.value = todayIso();
		});
	}

	function switchTab(tab) {
		app.find('[data-request-tab]').removeClass('is-active');
		app.find(`[data-request-tab="${tab}"]`).addClass('is-active');
		app.find('[data-request-panel]').attr('hidden', 'hidden').removeClass('is-active');
		app.find(`[data-request-panel="${tab}"]`).removeAttr('hidden').addClass('is-active');
		if (tab === 'wfh') loadWfhRequests();
		if (tab === 'permission') loadPermissionRequests();
		if (tab === 'holiday_work') loadHolidayWorkRequests();
	}

	function renderNotification(n, target) {
		if (n.type === 'request_rejected') {
			return `<div class="etwa-alert etwa-alert-warning etwa-vacation-notice">
				<strong>${n.request_type_label || ''} ${cfg.i18n?.rejected || 'Rejected'}</strong><br />
				${n.work_date || ''} ${n.time_range || ''}
				${ui.rejectionReasonNotice(n.rejection_reason || n.notes)}
			</div>`;
		}
		return `<div class="etwa-alert etwa-alert-info etwa-vacation-notice">
			<strong>${n.request_type_label || ''} ${cfg.i18n?.approved || 'Approved'}</strong><br />
			${n.work_date || ''} ${n.time_range || ''}<br />
			${cfg.i18n?.approvedBy || 'Approved by'}: ${n.approved_by || ''}
		</div>`;
	}

	function loadGenericNotifications() {
		api('requests/notifications')
			.then((res) => {
				if (!res.success) return;
				const items = res.data?.items || [];
				if (!items.length) return;

				const wfhItems = items.filter((n) => n.request_type === 'wfh');
				const permItems = items.filter((n) => n.request_type === 'permission');
				const holidayItems = items.filter((n) => n.request_type === 'holiday_work');

				if (wfhItems.length) {
					$('#etwa-wfh-notifications').html(wfhItems.map((n) => renderNotification(n)).join(''));
				}
				if (permItems.length) {
					$('#etwa-permission-notifications').html(permItems.map((n) => renderNotification(n)).join(''));
				}
				if (holidayItems.length) {
					$('#etwa-holiday-work-notifications').html(holidayItems.map((n) => renderNotification(n)).join(''));
				}

				api('requests/notifications', { method: 'PUT', body: '{}' });
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

	function statusCell(r) {
		return `<span class="etwa-status etwa-status-${r.status}">${r.status_label || r.status}</span>`;
	}

	function loadWfhRequests() {
		api(`requests?request_type=wfh&page=${wfhPage}&per_page=25`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-wfh-requests-body').html(
						`<tr><td colspan="6" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$('#etwa-wfh-requests-pagination').empty();
					return;
				}
				$('#etwa-wfh-requests-body').html(
					items
						.map((r) => {
							const cancel =
								r.status === 'pending'
									? `<button type="button" class="etwa-link etwa-cancel-generic-request" data-id="${r.id}">${cfg.i18n?.cancel || 'Cancel'}</button>`
									: '';
							const approved = r.status === 'approved' ? r.approved_by || '—' : '—';
							return `<tr class="etwa-request-row" data-id="${r.id}" data-source="generic">
						<td>${r.work_date || ''}</td>
						<td>${r.time_range || ''}</td>
						<td>${reasonCell(r)}</td>
						<td>${statusCell(r)}</td>
						<td>${approved}</td>
						<td>${cancel}</td>
					</tr>`;
						})
						.join('')
				);
				renderPagination('#etwa-wfh-requests-pagination', data.total, wfhPage, (p) => {
					wfhPage = p;
					loadWfhRequests();
				});
			})
			.catch((err) => ui.error(err.message));
	}

	function loadPermissionRequests() {
		api(`requests?request_type=permission&page=${permissionPage}&per_page=25`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-permission-requests-body').html(
						`<tr><td colspan="7" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$('#etwa-permission-requests-pagination').empty();
					return;
				}
				$('#etwa-permission-requests-body').html(
					items
						.map((r) => {
							const cancel =
								r.status === 'pending'
									? `<button type="button" class="etwa-link etwa-cancel-generic-request" data-id="${r.id}">${cfg.i18n?.cancel || 'Cancel'}</button>`
									: '';
							const approved = r.status === 'approved' ? r.approved_by || '—' : '—';
							return `<tr class="etwa-request-row" data-id="${r.id}" data-source="generic">
						<td>${r.work_date || r.request_date || ''}</td>
						<td>${r.time_range || ''}</td>
						<td>${r.permission_type_label || ''}</td>
						<td>${reasonCell(r)}</td>
						<td>${statusCell(r)}</td>
						<td>${approved}</td>
						<td>${cancel}</td>
					</tr>`;
						})
						.join('')
				);
				renderPagination('#etwa-permission-requests-pagination', data.total, permissionPage, (p) => {
					permissionPage = p;
					loadPermissionRequests();
				});
			})
			.catch((err) => ui.error(err.message));
	}

	function loadHolidayWorkRequests() {
		api(`requests?request_type=holiday_work&page=${holidayPage}&per_page=25`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-holiday-work-requests-body').html(
						`<tr><td colspan="7" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$('#etwa-holiday-work-requests-pagination').empty();
					return;
				}
				$('#etwa-holiday-work-requests-body').html(
					items
						.map((r) => {
							const cancel =
								r.status === 'pending'
									? `<button type="button" class="etwa-link etwa-cancel-generic-request" data-id="${r.id}">${cfg.i18n?.cancel || 'Cancel'}</button>`
									: '';
							const approved = r.status === 'approved' ? r.approved_by || '—' : '—';
							return `<tr class="etwa-request-row" data-id="${r.id}" data-source="generic">
						<td>${r.work_date || ''}</td>
						<td>${r.time_range || ''}</td>
						<td>${r.holiday_work_type_label || ''}</td>
						<td>${r.client_name || ''}</td>
						<td>${statusCell(r)}</td>
						<td>${approved}</td>
						<td>${cancel}</td>
					</tr>`;
						})
						.join('')
				);
				renderPagination('#etwa-holiday-work-requests-pagination', data.total, holidayPage, (p) => {
					holidayPage = p;
					loadHolidayWorkRequests();
				});
			})
			.catch((err) => ui.error(err.message));
	}

	function renderPagination(selector, total, current, onPage) {
		const pages = Math.max(1, Math.ceil((total || 0) / 25));
		let html = '';
		for (let i = 1; i <= pages; i += 1) {
			html += `<button type="button" class="etwa-btn etwa-btn-secondary${i === current ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
		}
		$(selector).html(html).off('click').on('click', 'button[data-page]', function () {
			onPage(parseInt($(this).data('page'), 10) || 1);
		});
	}

	function submitGenericForm(form) {
		const $form = $(form);
		const type = $form.data('request-type');
		const data = Object.fromEntries(new FormData(form).entries());
		data.request_type = type;
		if (type === 'permission') {
			data.work_date = data.request_date;
		}

		const $btn = $form.find('[type="submit"]');
		ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

		api('requests', { method: 'POST', body: JSON.stringify(data) })
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				form.reset();
				setDefaultDates();
				ui.success(cfg.i18n?.requestSubmitted || 'Request submitted for HR review.');
				if (type === 'wfh') loadWfhRequests();
				if (type === 'permission') loadPermissionRequests();
				if (type === 'holiday_work') loadHolidayWorkRequests();
			})
			.catch((err) => ui.error(err.message))
			.finally(() => ui.setButtonLoading($btn[0], false));
	}

	function showDetail(id) {
		api(`requests/${id}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const r = res.data || {};
				const history = (r.approval_history || [])
					.map((h) => {
						let line = `<li><strong>${h.label}</strong>: ${h.by} — ${h.at}`;
						if (h.reason) {
							line += `<br /><strong>${cfg.i18n?.rejectionReason || 'Rejection reason'}:</strong> ${h.reason}`;
						}
						return `${line}</li>`;
					})
					.join('');
				const timeline = ui.timelineHtml(r.approval_timeline || []);
				let extraDetails = '';
				if (r.request_type === 'holiday_work') {
					extraDetails = `
						<dt>${cfg.i18n?.workDate || 'Work Date'}</dt><dd>${r.work_date || ''}</dd>
						<dt>${cfg.i18n?.time || 'Time'}</dt><dd>${r.time_range || ''}</dd>
						<dt>${cfg.i18n?.workType || 'Work Type'}</dt><dd>${r.holiday_work_type_label || ''}</dd>
						<dt>${cfg.i18n?.client || 'Client'}</dt><dd>${r.client_name || ''}</dd>
						<dt>${cfg.i18n?.task || 'Task'}</dt><dd>${r.task || ''}</dd>
					`;
				}
				const reasonRow = `<dt>${cfg.i18n?.reason || 'Reason'}</dt><dd>${r.reason || ''}</dd>`;
				$('#etwa-request-detail-body').html(`
					<dl class="etwa-detail-list">
						<dt>${cfg.i18n?.requestType || 'Request Type'}</dt><dd>${r.request_type_label || ''}</dd>
						<dt>${cfg.i18n?.submittedDate || 'Submitted Date'}</dt><dd>${r.created_at_label || ui.formatDateTime12(r.created_at) || ''}</dd>
						<dt>${cfg.i18n?.status || 'Status'}</dt><dd>${r.status_label || r.status || ''}</dd>
						${extraDetails}
						${reasonRow}
						${ui.requestReviewDetailRows(r)}
						${r.approved_by ? `<dt>${cfg.i18n?.approvedBy || 'Approved by'}</dt><dd>${r.approved_by}</dd>` : ''}
						${r.approved_at ? `<dt>${cfg.i18n?.approvedDate || 'Approved Date'}</dt><dd>${r.approved_at}</dd>` : ''}
					</dl>
					${timeline}
					${history && !(r.approval_timeline || []).length ? `<h4>${cfg.i18n?.approvalHistory || 'Approval History'}</h4><ul class="etwa-detail-history">${history}</ul>` : ''}
					<p class="etwa-muted">${cfg.i18n?.attachedFilesFuture || 'Attached files — coming soon.'}</p>
				`);
				$('#etwa-request-detail-modal').removeAttr('hidden').attr('aria-hidden', 'false');
			})
			.catch((err) => ui.error(err.message));
	}

	$(function () {
		setDefaultDates();
		loadGenericNotifications();

		api('requests/permission-types')
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				permissionTypes = res.data?.items || {};
				$('#etwa-permission-type').html(
					Object.entries(permissionTypes)
						.map(([slug, label]) => `<option value="${slug}">${label}</option>`)
						.join('')
				);
			})
			.catch((err) => ui.error(err.message));

		Promise.all([
			api('requests/holiday-work-types'),
			api('clients'),
		])
			.then(([holidayRes, clientsRes]) => {
				if (holidayRes.success) {
					const types = holidayRes.data?.items || {};
					$('#etwa-holiday-work-type').html(
						Object.entries(types)
							.map(([slug, label]) => `<option value="${slug}">${label}</option>`)
							.join('')
					);
				}
				if (clientsRes.success) {
					const list = Array.isArray(clientsRes.data) ? clientsRes.data : clientsRes.data?.items || [];
					const clientOptions = list
						.filter((c) => String(c.code || '').toUpperCase() !== 'BREAK')
						.map((c) => `<option value="${c.id}">${c.name}</option>`)
						.join('');
					$('#etwa-holiday-work-client').html(clientOptions);
				}
			})
			.catch(() => {});

		const initialTab = app.data('initial-tab') || 'vacation';
		if (initialTab !== 'vacation') switchTab(initialTab);

		app.on('click', '[data-request-tab]', function () {
			switchTab($(this).data('request-tab'));
		});

		$('#etwa-wfh-request-form, #etwa-permission-request-form, #etwa-holiday-work-request-form').on('submit', function (e) {
			e.preventDefault();
			submitGenericForm(this);
		});

		app.on('click', '.etwa-cancel-generic-request', function (e) {
			e.stopPropagation();
			const id = $(this).data('id');
			ui.confirm(cfg.i18n?.confirmCancelRequest || 'Cancel this request?', cfg.i18n?.cancel || 'Cancel').then((ok) => {
				if (!ok) return;
				api(`requests/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.requestCancelled || 'Request cancelled.');
						loadWfhRequests();
						loadPermissionRequests();
						loadHolidayWorkRequests();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		app.on('click', '.etwa-request-row[data-source="generic"]', function (e) {
			if ($(e.target).closest('button').length) return;
			showDetail($(this).data('id'));
		});

		$('#etwa-request-detail-modal').on('click', '.etwa-modal-close', function () {
			$('#etwa-request-detail-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
		});
	});
})(jQuery);
