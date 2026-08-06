(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const app = $('#etwa-vacation-app');
	if (!app.length || !cfg.canManageVacation) return;

	const canDelete = app.data('can-delete') === 1;
	const canExport = app.data('can-export') === 1;

	let leaveTypes = [];
	let employees = [];
	let historyPage = 1;
	let requestsPage = 1;
	let calYear = new Date().getFullYear();
	let calMonth = new Date().getMonth() + 1;
	let vacationCalendar = null;

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	function renderBalanceCards(balances, target) {
		const primary = balances.filter((b) => b.is_primary_pool);
		if (!primary.length) {
			target.html(`<p class="etwa-muted">${cfg.i18n?.noData || 'No data.'}</p>`);
			return;
		}
		target.html(
			primary
				.map(
					(b) => `<div class="etwa-vacation-card etwa-vacation-card-primary">
					<h4>${b.leave_type_name}</h4>
					<p class="etwa-muted etwa-card-note">${b.pool_note || cfg.i18n?.annualPoolNote || ''}</p>
					<div class="etwa-vacation-stat"><span>${cfg.i18n?.allocated || 'Allocated'}</span><strong>${b.allocated}</strong></div>
					<div class="etwa-vacation-stat"><span>${cfg.i18n?.used || 'Used'}</span><strong>${b.used}</strong></div>
					<div class="etwa-vacation-stat etwa-vacation-remaining"><span>${cfg.i18n?.remaining || 'Remaining'}</span><strong>${b.remaining}</strong></div>
				</div>`
				)
				.join('')
		);
	}

	function renderUsageBreakdown(balances) {
		const items = balances.filter((b) => b.usage_only && (b.used || 0) > 0);
		if (!items.length) {
			$('#etwa-vacation-usage-breakdown').html(`<p class="etwa-muted">${cfg.i18n?.noUsageYet || 'No leave used yet.'}</p>`);
			return;
		}
		$('#etwa-vacation-usage-breakdown').html(
			items
				.map(
					(b) => `<div class="etwa-vacation-card etwa-vacation-card-sm">
					<h4>${b.leave_type_name}</h4>
					<div class="etwa-vacation-stat"><span>${cfg.i18n?.used || 'Used'}</span><strong>${b.used}</strong></div>
				</div>`
				)
				.join('')
		);
	}

	function annualLeaveTypeId() {
		const annual = leaveTypes.find((t) => t.slug === 'annual');
		return annual ? annual.id : '';
	}

	function syncProfileEmployeeId() {
		const userId = $('#etwa-vacation-employee').val() || '';
		$('#etwa-set-annual-user').val(userId);
		$('#etwa-adjust-user').val(userId);
		return userId;
	}

	function prepareAllocationData(form, data) {
		if (form.id === 'etwa-set-annual-form') {
			data.user_id = syncProfileEmployeeId() || data.user_id || '';
		}
		data.leave_type_id = annualLeaveTypeId();
		if (!data.user_id) {
			throw new Error(cfg.i18n?.selectEmployee || 'Please select an employee.');
		}
		if (!data.leave_type_id) {
			throw new Error(cfg.i18n?.annualTypeMissing || 'Annual leave type is not configured.');
		}
		return data;
	}

	function todayIso() {
		return new Date().toISOString().slice(0, 10);
	}

	function setDefaultEffectiveDates() {
		const today = todayIso();
		$('#etwa-set-annual-form [name="effective_date"], #etwa-vacation-allocate-form [name="effective_date"], #etwa-vacation-adjust-form [name="effective_date"]').each(function () {
			if (!this.value) this.value = today;
		});
	}

	function updateAdjustDaysField() {
		const mode = $('#etwa-adjust-mode').val();
		const $days = $('#etwa-adjust-days');
		const $label = $('#etwa-adjust-days-label');

		if (mode === 'set_remaining') {
			$label.text(cfg.i18n?.remainingTarget || 'Remaining days');
			$days.attr('min', '0');
			$days.attr('step', '0.25');
		} else {
			$label.text(cfg.i18n?.adjustmentDays || cfg.i18n?.days || 'Days');
			$days.attr('min', '0.25');
			$days.attr('step', '0.25');
		}
	}

	function loadProfile(userId) {
		if (!userId) return;
		$('#etwa-set-annual-user').val(userId);
		$('#etwa-adjust-user').val(userId);
		api(`vacations/profile/${userId}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				$('#etwa-vacation-profile-name').text(data.employee?.display_name || '');
				renderBalanceCards(data.balances || [], $('#etwa-vacation-balance-cards'));
				renderUsageBreakdown(data.balances || []);
			})
			.catch((err) => ui.error(err.message));
	}

	function populateEmployees() {
		const selects = $('.etwa-vacation-employee-field, #etwa-vacation-employee, #etwa-vacation-filter-employee');
		selects.each(function () {
			const isFilter = this.id === 'etwa-vacation-filter-employee';
			const current = $(this).val();
			let html = isFilter ? `<option value="">${cfg.i18n?.allEmployees || 'All employees'}</option>` : '';
			employees.forEach((e) => {
				html += `<option value="${e.id}">${e.display_name}</option>`;
			});
			$(this).html(html);
			if (current) $(this).val(String(current));
		});
		if ($('#etwa-vacation-employee option').length && !$('#etwa-vacation-employee').val()) {
			$('#etwa-vacation-employee').prop('selectedIndex', 0);
		}
		syncProfileEmployeeId();
		if ($('#etwa-vacation-employee').val()) {
			loadProfile($('#etwa-vacation-employee').val());
		}
	}

	function populateLeaveTypes() {
		const annualId = annualLeaveTypeId();
		$('.etwa-annual-leave-type-id').val(annualId);
		const all = $('.etwa-vacation-type-all, #etwa-vacation-filter-type');
		all.each(function () {
			const isFilter = this.id === 'etwa-vacation-filter-type';
			let html = isFilter ? `<option value="">${cfg.i18n?.allLeaveTypes || 'All leave types'}</option>` : '';
			html += leaveTypes.map((t) => `<option value="${t.id}">${t.name}</option>`).join('');
			$(this).html(html);
		});
	}

	function historyFilters() {
		const monthVal = $('#etwa-vacation-filter-month').val();
		let month = '';
		let year = '';
		if (monthVal) {
			const parts = monthVal.split('-');
			year = parts[0];
			month = parts[1];
		}
		return {
			user_id: $('#etwa-vacation-filter-employee').val() || undefined,
			leave_type_id: $('#etwa-vacation-filter-type').val() || undefined,
			status: $('#etwa-vacation-filter-status').val() || undefined,
			month: month || undefined,
			year: year || undefined,
			date_from: $('#etwa-vacation-filter-from').val() || undefined,
			date_to: $('#etwa-vacation-filter-to').val() || undefined,
			keyword: $('#etwa-vacation-filter-keyword').val().trim() || undefined,
		};
	}

	function loadHistory() {
		const params = new URLSearchParams({ page: String(historyPage), per_page: '25' });
		const filters = historyFilters();
		Object.entries(filters).forEach(([k, v]) => {
			if (v) params.set(k, String(v));
		});

		$('#etwa-vacation-history-body').html(
			'<tr><td colspan="9"><div class="etwa-skeleton" style="height:40px"></div></td></tr>'
		);

		api(`vacations/history?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];
				if (!items.length) {
					$('#etwa-vacation-history-body').html(
						`<tr><td colspan="9" class="etwa-empty-cell">${cfg.i18n?.noHistory || 'No records found.'}</td></tr>`
					);
					$('#etwa-vacation-history-pagination').empty();
					return;
				}
				$('#etwa-vacation-history-body').html(
					items
						.map((row) => {
							const actions = [];
							if (row.source === 'transaction' && row.transaction_type === 'allocation') {
								actions.push(`<button type="button" class="etwa-link etwa-vacation-edit" data-source="allocation" data-id="${row.id}">${cfg.i18n?.edit || 'Edit'}</button>`);
								if (canDelete) actions.push(`<button type="button" class="etwa-link etwa-vacation-delete" data-source="allocation" data-id="${row.id}">${cfg.i18n?.delete || 'Delete'}</button>`);
							} else if (row.source === 'transaction' && row.transaction_type === 'adjustment') {
								actions.push(`<button type="button" class="etwa-link etwa-vacation-edit" data-source="adjustment" data-id="${row.id}">${cfg.i18n?.edit || 'Edit'}</button>`);
								if (canDelete) actions.push(`<button type="button" class="etwa-link etwa-vacation-delete" data-source="adjustment" data-id="${row.id}">${cfg.i18n?.delete || 'Delete'}</button>`);
							} else if (row.source === 'record' || row.transaction_type === 'vacation' || row.transaction_type === 'usage') {
								actions.push(`<button type="button" class="etwa-link etwa-vacation-view" data-id="${row.record_id || row.id}">${cfg.i18n?.view || 'View'}</button>`);
								actions.push(`<button type="button" class="etwa-link etwa-vacation-edit" data-source="record" data-id="${row.record_id || row.id}">${cfg.i18n?.edit || 'Edit'}</button>`);
								if (canDelete) actions.push(`<button type="button" class="etwa-link etwa-vacation-delete" data-source="record" data-id="${row.record_id || row.id}">${cfg.i18n?.delete || 'Delete'}</button>`);
							}
							const daysDisplay =
								row.transaction_type === 'adjustment' ? row.days : Math.abs(row.days);
							return `<tr>
							<td>${row.event_date}</td>
							<td>${row.leave_type_name}</td>
							<td>${row.transaction_label}</td>
							<td>${daysDisplay}</td>
							<td>${row.status ? row.status : '—'}</td>
							<td>${row.reason || ''}</td>
							<td>${row.created_by_name || ''}</td>
							<td>${row.created_at_label || ui.formatDateTime12(row.created_at)}</td>
							<td class="etwa-actions-cell">${actions.join(' ')}</td>
						</tr>`;
						})
						.join('')
				);
				const pages = Math.max(1, Math.ceil((data.total || 0) / (data.per_page || 25)));
				let pag = '';
				for (let i = 1; i <= pages; i += 1) {
					pag += `<button type="button" class="etwa-btn etwa-btn-secondary${i === historyPage ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
				}
				$('#etwa-vacation-history-pagination').html(pag);
			})
			.catch((err) => ui.error(err.message));
	}

	function updatePendingBadge(count) {
		const $badge = $('#etwa-vacation-pending-badge');
		if (count > 0) {
			$badge.text(String(count)).removeAttr('hidden');
		} else {
			$badge.attr('hidden', 'hidden').text('');
		}
	}

	function requestStatusHtml(r) {
		const label = r.status_label || r.status;
		let html = `<span class="etwa-status etwa-status-${r.status}">${label}</span>`;
		if (r.dual_approval_enabled && r.status === 'pending') {
			html += `<div class="etwa-vacation-approval-badges">
				<span class="etwa-vacation-approval-badge${r.hr_approved ? ' is-done' : ''}">${cfg.i18n?.hrApproval || 'HR'}${r.hr_approved_by_name ? `: ${r.hr_approved_by_name}` : ''}</span>
				<span class="etwa-vacation-approval-badge${r.owner_approved ? ' is-done' : ''}">${cfg.i18n?.ownerApproval || 'Owner'}${r.owner_approved_by_name ? `: ${r.owner_approved_by_name}` : ''}</span>
			</div>`;
		}
		return html;
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

	function requestActions(r) {
		if (r.status !== 'pending') return '—';
		const buttons = [];
		const dayAttrs = `data-start="${r.start_date || ''}" data-end="${r.end_date || ''}"`;
		if (r.dual_approval_enabled) {
			if (r.can_approve_as_hr) {
				buttons.push(`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-slot="hr" data-needs-days="1" ${dayAttrs}>${cfg.i18n?.approveAsHr || 'Approve (HR)'}</button>`);
			}
			if (r.can_approve_as_owner) {
				buttons.push(`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-slot="owner">${cfg.i18n?.approveAsOwner || 'Approve (Owner)'}</button>`);
			}
		} else if (r.can_approve_as_hr) {
			buttons.push(`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-needs-days="1" ${dayAttrs}>${cfg.i18n?.approve || 'Approve'}</button>`);
		} else if (r.can_approve_as_department) {
			buttons.push(`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-slot="department" data-needs-days="0">${cfg.i18n?.approve || 'Approve'}</button>`);
		}
		if (r.can_reject) {
			buttons.push(`<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-sm etwa-reject-request" data-id="${r.id}">${cfg.i18n?.reject || 'Reject'}</button>`);
		}
		return buttons.length ? buttons.join(' ') : '—';
	}

	function loadRequests() {
		const status = $('#etwa-vacation-request-status').val() || 'pending';
		const params = new URLSearchParams({ page: String(requestsPage), per_page: '25' });
		if (status) params.set('status', status);

		$('#etwa-vacation-requests-body').html(
			'<tr><td colspan="8"><div class="etwa-skeleton" style="height:40px"></div></td></tr>'
		);

		api(`vacations/requests?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];

				if (status === 'pending') {
					updatePendingBadge(data.total || 0);
				}

				if (!items.length) {
					$('#etwa-vacation-requests-body').html(
						`<tr><td colspan="8" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$('#etwa-vacation-requests-pagination').empty();
					return;
				}

				$('#etwa-vacation-requests-body').html(
					items
						.map((r) => `<tr>
							<td><strong>${r.employee_name}</strong></td>
							<td>${r.leave_type_name}</td>
							<td>${r.start_date} – ${r.end_date}</td>
							<td>${formatRequestDays(r)}</td>
							<td>${r.reason || ''}</td>
							<td>${requestStatusHtml(r)}</td>
							<td>${r.created_at_label || ui.formatDateTime12(r.created_at)}</td>
							<td class="etwa-actions-cell">${requestActions(r)}</td>
						</tr>`)
						.join('')
				);

				const pages = Math.max(1, Math.ceil((data.total || 0) / 25));
				let pag = '';
				for (let i = 1; i <= pages; i += 1) {
					pag += `<button type="button" class="etwa-btn etwa-btn-secondary${i === requestsPage ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
				}
				$('#etwa-vacation-requests-pagination').html(pag);
			})
			.catch((err) => ui.error(err.message));
	}

	function submitReview(id, status, slot, days, notes) {
		const body = {
			status,
			approval_slot: slot || '',
		};
		if (days != null && days > 0) {
			body.days = days;
		}
		if (notes != null && String(notes).trim()) {
			body.notes = String(notes).trim();
		}
		return api(`vacations/requests/${id}/review`, {
			method: 'PUT',
			body: JSON.stringify(body),
		})
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				if (status === 'approved' && data.status === 'pending') {
					ui.success(cfg.i18n?.requestPartiallyApproved || 'Approval recorded. Waiting for the other reviewer.');
				} else {
					ui.success(
						status === 'approved'
							? cfg.i18n?.requestApproved || 'Request approved.'
							: cfg.i18n?.requestRejected || 'Request rejected.'
					);
				}
				loadRequests();
				if ($('#etwa-vacation-employee').val()) loadProfile($('#etwa-vacation-employee').val());
			})
			.catch((err) => ui.error(err.message));
	}

	function reviewRequest(id, status, slot, meta = {}) {
		let msg;
		let title;
		if (status === 'approved') {
			if (slot === 'hr') {
				msg = cfg.i18n?.confirmApproveHr || 'Record your HR approval for this request?';
				title = cfg.i18n?.approveAsHr || 'Approve (HR)';
			} else if (slot === 'owner') {
				msg = cfg.i18n?.confirmApproveOwner || 'Record your Owner approval for this request?';
				title = cfg.i18n?.approveAsOwner || 'Approve (Owner)';
			} else {
				msg = cfg.i18n?.confirmApproveRequest || 'Approve this vacation request?';
				title = cfg.i18n?.approve || 'Approve';
			}
		} else {
			msg = cfg.i18n?.confirmRejectRequest || 'Reject this vacation request?';
			title = cfg.i18n?.reject || 'Reject';
		}

		const needsDays = !!meta.needsDays;

		const proceed = () => {
			if (status === 'approved' && needsDays) {
				return ui
					.promptDaysToDeduct({
						startDate: meta.startDate,
						endDate: meta.endDate,
						api,
					})
					.then((days) => {
						if (days == null) return;
						return submitReview(id, status, slot, days);
					});
			}
			return ui.confirm(msg, title).then((ok) => {
				if (!ok) return;
				return submitReview(id, status, slot);
			});
		};

		if (status === 'approved' && needsDays) {
			proceed();
			return;
		}

		if (status === 'rejected') {
			ui.confirm(msg, title).then((ok) => {
				if (!ok) return;
				ui.promptReason(cfg.i18n?.rejectReasonOptional || 'Rejection reason (optional)', { optional: true }).then((notes) => {
					if (notes === null) return;
					submitReview(id, status, slot, null, notes);
				});
			});
			return;
		}

		ui.confirm(msg, title).then((ok) => {
			if (!ok) return;
			submitReview(id, status, slot);
		});
	}

	function loadCalendar() {
		if (vacationCalendar) {
			vacationCalendar.load();
		}
	}

	function calcDays() {
		const start = $('#etwa-vacation-start').val();
		const end = $('#etwa-vacation-end').val();
		if (!start || !end) return;
		api(`vacations/days-between?start_date=${start}&end_date=${end}`)
			.then((res) => {
				if (res.success && res.data) $('#etwa-vacation-days').val(res.data.days);
			})
			.catch(() => {});
	}

	function openEditModal(source, id) {
		const endpoint =
			source === 'allocation'
				? `vacations/allocations/${id}`
				: source === 'adjustment'
					? `vacations/adjustments/${id}`
					: `vacations/records/${id}`;
		api(endpoint).then((res) => {
			if (!res.success) return ui.error(res.message);
			const row = res.data;
			const title =
				source === 'allocation'
					? cfg.i18n?.editAllocation || 'Edit Allocation'
					: source === 'adjustment'
						? cfg.i18n?.editAdjustment || 'Edit Balance Adjustment'
						: cfg.i18n?.editRecord || 'Edit Leave Record';
			$('#etwa-vacation-edit-title').text(title);
			let formHtml = `<input type="hidden" name="source" value="${source}" /><input type="hidden" name="id" value="${id}" />`;
			if (source === 'allocation') {
				formHtml += `
				<label>${cfg.i18n?.leaveType || 'Leave Type'}<select name="leave_type_id" class="etwa-input">${leaveTypes.filter(t=>t.tracks_allocation).map(t=>`<option value="${t.id}"${t.id==row.leave_type_id?' selected':''}>${t.name}</option>`).join('')}</select></label>
				<label>${cfg.i18n?.days || 'Days'}<input type="number" name="days" class="etwa-input" value="${row.days}" min="0.25" step="0.25" required /></label>
				<label>${cfg.i18n?.effectiveDate || 'Effective Date'}<input type="date" name="effective_date" class="etwa-input" value="${row.effective_date}" required /></label>
				<label class="etwa-span-2">${cfg.i18n?.notes || 'Notes'}<textarea name="notes" class="etwa-input">${row.notes || ''}</textarea></label>`;
			} else if (source === 'adjustment') {
				formHtml += `
				<label>${cfg.i18n?.days || 'Days'}<input type="number" name="days" class="etwa-input" value="${row.days}" step="0.25" required /></label>
				<p class="etwa-span-2 etwa-muted">${cfg.i18n?.adjustmentEditHint || 'Use positive values to add days and negative values to subtract.'}</p>
				<label>${cfg.i18n?.effectiveDate || 'Effective Date'}<input type="date" name="effective_date" class="etwa-input" value="${row.effective_date}" required /></label>
				<label class="etwa-span-2">${cfg.i18n?.notes || 'Notes'}<textarea name="notes" class="etwa-input">${row.notes || ''}</textarea></label>`;
			} else {
				formHtml += `
				<label>${cfg.i18n?.leaveType || 'Leave Type'}<select name="leave_type_id" class="etwa-input">${leaveTypes.map(t=>`<option value="${t.id}"${t.id==row.leave_type_id?' selected':''}>${t.name}</option>`).join('')}</select></label>
				<label>${cfg.i18n?.startDate || 'Start'}<input type="date" name="start_date" class="etwa-input" value="${row.start_date}" required /></label>
				<label>${cfg.i18n?.endDate || 'End'}<input type="date" name="end_date" class="etwa-input" value="${row.end_date}" required /></label>
				<label>${cfg.i18n?.days || 'Days'}<input type="number" name="days" class="etwa-input" value="${row.days}" min="0.25" step="0.25" required /></label>
				<label>${cfg.i18n?.status || 'Status'}<select name="status" class="etwa-input">
					<option value="approved"${row.status==='approved'?' selected':''}>${cfg.i18n?.approved || 'Approved'}</option>
					<option value="pending"${row.status==='pending'?' selected':''}>${cfg.i18n?.pending || 'Pending'}</option>
					<option value="rejected"${row.status==='rejected'?' selected':''}>${cfg.i18n?.rejected || 'Rejected'}</option>
				</select></label>
				<label class="etwa-span-2">${cfg.i18n?.reason || 'Reason'}<textarea name="reason" class="etwa-input">${row.reason || ''}</textarea></label>
				<label class="etwa-span-2">${cfg.i18n?.notes || 'Notes'}<textarea name="notes" class="etwa-input">${row.notes || ''}</textarea></label>`;
			}
			formHtml += `<div class="etwa-modal-actions etwa-span-2">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close">${cfg.i18n?.cancel || 'Cancel'}</button>
				<button type="submit" class="etwa-btn etwa-btn-primary">${cfg.i18n?.save || 'Save'}</button>
			</div>`;
			$('#etwa-vacation-edit-form').html(formHtml);
			$('#etwa-vacation-edit-modal').removeAttr('hidden').attr('aria-hidden', 'false');
			$('body').addClass('etwa-modal-open');
		});
	}

	function closeModal() {
		$('#etwa-vacation-edit-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
	}

	$(function () {
		Promise.all([api('vacations/leave-types'), api('vacations/employees?per_page=100')])
			.then(([typesRes, empRes]) => {
				if (!typesRes.success || !empRes.success) throw new Error(typesRes.message || empRes.message);
				leaveTypes = typesRes.data || [];
				employees = (empRes.data?.items) || [];
				populateLeaveTypes();
				populateEmployees();
				setDefaultEffectiveDates();
				updateAdjustDaysField();
			})
			.catch((err) => ui.error(err.message));

		loadRequests();

		vacationCalendar = window.etwaVacationCalendar?.init({
			prefix: 'etwa-vacation',
			api,
			cfg,
			ui,
		});

		$('.etwa-vacation-tab').on('click', function () {
			const tab = $(this).data('tab');
			$('.etwa-vacation-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$('.etwa-vacation-panel').attr('hidden', 'hidden').removeClass('is-active');
			$(`.etwa-vacation-panel[data-panel="${tab}"]`).removeAttr('hidden').addClass('is-active');
			if (tab === 'history') loadHistory();
			if (tab === 'requests') loadRequests();
			if (tab === 'calendar') loadCalendar();
		});

		$('#etwa-vacation-request-status').on('change', function () {
			requestsPage = 1;
			loadRequests();
		});

		$('#etwa-vacation-requests-pagination').on('click', 'button[data-page]', function () {
			requestsPage = parseInt($(this).data('page'), 10) || 1;
			loadRequests();
		});

		$('#etwa-vacation-requests-body').on('click', '.etwa-approve-request', function () {
			const $btn = $(this);
			reviewRequest($btn.data('id'), 'approved', $btn.data('slot') || '', {
				needsDays: $btn.attr('data-needs-days') === '1',
				startDate: $btn.attr('data-start') || '',
				endDate: $btn.attr('data-end') || '',
			});
		});

		$('#etwa-vacation-requests-body').on('click', '.etwa-reject-request', function () {
			reviewRequest($(this).data('id'), 'rejected');
		});

		$('#etwa-vacation-employee').on('change', function () {
			syncProfileEmployeeId();
			loadProfile($(this).val());
		});

		$('#etwa-adjust-mode').on('change', updateAdjustDaysField);

		$('#etwa-vacation-adjust-form').on('submit', function (e) {
			e.preventDefault();
			syncProfileEmployeeId();
			const data = Object.fromEntries(new FormData(this).entries());
			if (!data.user_id) {
				ui.error(cfg.i18n?.selectEmployee || 'Please select an employee.');
				return;
			}
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			api('vacations/adjustments', { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					$('#etwa-adjust-days').val('');
					ui.success(cfg.i18n?.balanceAdjusted || 'Vacation balance updated.');
					loadProfile(data.user_id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-vacation-allocate-form').on('submit', function (e) {
			e.preventDefault();
			let data;
			try {
				data = prepareAllocationData(this, Object.fromEntries(new FormData(this).entries()));
			} catch (err) {
				ui.error(err.message);
				return;
			}
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			api('vacations/allocations', { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					setDefaultEffectiveDates();
					ui.success(cfg.i18n?.annualBalanceSaved || 'Annual leave balance saved.');
					loadProfile(data.user_id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-set-annual-form').on('submit', function (e) {
			e.preventDefault();
			let data;
			try {
				data = prepareAllocationData(this, Object.fromEntries(new FormData(this).entries()));
			} catch (err) {
				ui.error(err.message);
				return;
			}
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			api('vacations/allocations', { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.annualBalanceSaved || 'Annual leave balance saved.');
					loadProfile(data.user_id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-vacation-record-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			if (!data.user_id) {
				ui.error(cfg.i18n?.selectEmployee || 'Please select an employee.');
				return;
			}
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			api('vacations/records', { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					ui.success(cfg.i18n?.recordSaved || 'Leave record saved.');
					loadProfile(data.user_id);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-vacation-start, #etwa-vacation-end').on('change', calcDays);

		$('#etwa-vacation-history-pagination').on('click', 'button[data-page]', function () {
			historyPage = parseInt($(this).data('page'), 10) || 1;
			loadHistory();
		});

		$('.etwa-vacation-filters select, .etwa-vacation-filters input').on('change input', function () {
			historyPage = 1;
			loadHistory();
		});

		$('#etwa-vacation-history-body').on('click', '.etwa-vacation-edit', function () {
			openEditModal($(this).data('source'), $(this).data('id'));
		});

		$('#etwa-vacation-history-body').on('click', '.etwa-vacation-view', function () {
			api(`vacations/records/${$(this).data('id')}`).then((res) => {
				if (!res.success) return ui.error(res.message);
				const r = res.data;
				ui.info(`${r.leave_type_name}: ${r.start_date} – ${r.end_date}, ${r.days} days (${r.status})\n${r.reason || ''}`);
			});
		});

		$('#etwa-vacation-history-body').on('click', '.etwa-vacation-delete', function () {
			const source = $(this).data('source');
			const id = $(this).data('id');
			ui.confirm(cfg.i18n?.confirmDeleteVacation || 'Delete this record?', cfg.i18n?.delete || 'Delete').then((ok) => {
				if (!ok) return;
				const path =
					source === 'allocation'
						? `vacations/allocations/${id}`
						: source === 'adjustment'
							? `vacations/adjustments/${id}`
							: `vacations/records/${id}`;
				api(path, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.deleted || 'Deleted.');
						loadHistory();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-vacation-edit-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const source = data.source;
			const id = data.id;
			delete data.source;
			delete data.id;
			const path =
				source === 'allocation'
					? `vacations/allocations/${id}`
					: source === 'adjustment'
						? `vacations/adjustments/${id}`
						: `vacations/records/${id}`;
			api(path, { method: 'PUT', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					closeModal();
					ui.success(cfg.i18n?.saved || 'Saved.');
					loadHistory();
					if ($('#etwa-vacation-employee').val()) loadProfile($('#etwa-vacation-employee').val());
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-vacation-edit-modal').on('click', '.etwa-modal-close', closeModal);

		if (canExport) {
			$('#etwa-vacation-export').on('click', function () {
				const $btn = $(this);
				ui.setButtonLoading($btn[0], true, cfg.i18n?.exporting);
				api('vacations/export', { method: 'POST', body: JSON.stringify(historyFilters()) })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						if (res.data?.url) window.open(res.data.url, '_blank');
						ui.success(cfg.i18n?.exportReady || 'Export ready.');
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn[0], false));
			});
		}
	});
})(jQuery);
