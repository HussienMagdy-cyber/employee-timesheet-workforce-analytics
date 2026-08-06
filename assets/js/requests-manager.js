(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const app = $('#etwa-requests-center-app');
	if (!app.length || (!cfg.canManageVacation && !cfg.canApproveDept)) return;

	const canExport = app.data('can-export') === 1;

	let employees = [];
	let vacPage = 1;
	let wfhPage = 1;
	let permPage = 1;
	let holidayPage = 1;

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	function updateBadge(selector, count) {
		const $badge = $(selector);
		if (count > 0) {
			$badge.text(String(count)).removeAttr('hidden');
		} else {
			$badge.attr('hidden', 'hidden').text('');
		}
	}

	function loadTabBadges() {
		return Promise.all([
			api('requests/pending-counts'),
			api('vacations/requests?status=pending&per_page=1'),
		])
			.then(([countsRes, vacPendingRes]) => {
				if (countsRes.success) {
					const c = countsRes.data || {};
					updateBadge('#etwa-wfh-tab-badge', c.wfh || 0);
					updateBadge('#etwa-permission-tab-badge', c.permission || 0);
					updateBadge('#etwa-holiday-work-tab-badge', c.holiday_work || 0);
				}
				if (vacPendingRes.success) {
					updateBadge('#etwa-vacation-tab-badge', vacPendingRes.data?.total || 0);
				}
			})
			.catch(() => {});
	}

	function refreshNavBadges() {
		if (!cfg.root || !window.etwaAPI) return;
		window.etwaAPI.api('portal/nav-badges')
			.then((payload) => {
				if (!payload || !payload.success) return;
				const badges = payload.data || {};
				document.querySelectorAll('[data-nav-badge]').forEach((el) => {
					const key = el.getAttribute('data-nav-badge');
					const count = badges[key] || 0;
					if (count > 0) {
						el.textContent = String(count);
						el.removeAttribute('hidden');
					} else {
						el.setAttribute('hidden', 'hidden');
						el.textContent = '';
					}
				});
			})
			.catch(() => {});
	}

	function refreshAllBadges() {
		loadTabBadges();
		refreshNavBadges();
	}

	function agingBadge(r) {
		if (r.status !== 'pending' || !r.is_aging) return '';
		return `<span class="etwa-aging-badge" title="${cfg.i18n?.agingHint || 'Pending over 48 hours'}">${cfg.i18n?.aging || '>48h'}</span>`;
	}

	function rowCheckbox(r, source) {
		if (r.status !== 'pending') return '<td></td>';
		return `<td><input type="checkbox" class="etwa-bulk-select" data-id="${r.id}" data-source="${source}" /></td>`;
	}

	function timelineHtml(r) {
		return ui.timelineHtml(r.approval_timeline || r.approval_history || []);
	}

	function getActiveTab() {
		return app.data('initial-tab') || app.find('[data-request-tab].is-active').data('request-tab') || 'vacation';
	}

	function getSelectedItems() {
		const tab = getActiveTab();
		const source = tab === 'vacation' ? 'vacation' : 'generic';
		return app
			.find(`[data-request-panel="${tab}"] .etwa-bulk-select:checked`)
			.map(function () {
				return { id: $(this).data('id'), source };
			})
			.get();
	}

	function updateBulkToolbar() {
		const selected = getSelectedItems();
		const $toolbar = $('#etwa-requests-bulk-toolbar');
		if (!selected.length) {
			$toolbar.attr('hidden', 'hidden');
			return;
		}
		$toolbar.removeAttr('hidden');
		$('#etwa-bulk-selected-count').text(
			(cfg.i18n?.selectedCount || '%d selected').replace('%d', String(selected.length))
		);
	}

	function submitReview(id, status, source, slot, notes, days) {
		const path = source === 'vacation' ? `vacations/requests/${id}/review` : `requests/${id}/review`;
		const body = { status, notes: notes || '' };
		if (slot) body.approval_slot = slot;
		if (days != null && days > 0) body.days = days;
		return api(path, { method: 'PUT', body: JSON.stringify(body) });
	}

	function vacationHrNeedsDays(source, slot) {
		return source === 'vacation' && (slot === 'hr' || (!slot && cfg.canManageVacation));
	}

	function reloadActiveTab() {
		const tab = getActiveTab();
		if (tab === 'vacation') loadVacationRequests();
		if (tab === 'wfh') loadWfhRequests();
		if (tab === 'permission') loadPermissionRequests();
		if (tab === 'holiday_work') loadHolidayWorkRequests();
	}

	function bulkReview(status) {
		const items = getSelectedItems();
		if (!items.length) return;

		const run = (notes) => {
			const tab = getActiveTab();
			const path = tab === 'vacation' ? 'vacations/requests/bulk-review' : 'requests/bulk-review';
			const slot =
				status === 'approved' && cfg.canApproveDept && !cfg.canManageVacation ? 'department' : cfg.canManageVacation ? 'hr' : '';

			api(path, {
				method: 'POST',
				body: JSON.stringify({ items, status, notes: notes || '', approval_slot: slot }),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					const data = res.data || {};
					if (data.failed) {
						ui.error((data.errors || []).join('\n') || cfg.i18n?.error || 'Some requests failed.');
					} else {
						ui.success(
							status === 'approved'
								? cfg.i18n?.requestApproved || 'Request approved.'
								: cfg.i18n?.requestRejected || 'Request rejected.'
						);
					}
					reloadActiveTab();
					refreshAllBadges();
					updateBulkToolbar();
				})
				.catch((err) => ui.error(err.message));
		};

		if (status === 'rejected') {
			ui.confirm(cfg.i18n?.confirmRejectRequest || 'Reject selected requests?', cfg.i18n?.reject || 'Reject').then((ok) => {
				if (!ok) return;
				ui.promptReason(cfg.i18n?.rejectReasonOptional || 'Rejection reason (optional)', { optional: true }).then((notes) => {
					if (notes === null) return;
					run(String(notes).trim());
				});
			});
			return;
		}

		const tab = getActiveTab();
		if (tab === 'vacation' && cfg.canManageVacation) {
			ui.error(
				cfg.i18n?.vacationBulkHrDays ||
					'Approve vacation requests individually so you can enter the days to deduct.'
			);
			return;
		}

		ui.confirm(cfg.i18n?.confirmApproveRequest || 'Approve selected requests?', cfg.i18n?.approve || 'Approve').then((ok) => {
			if (ok) run('');
		});
	}

	function requestStatusHtml(r) {
		const label = r.status_label || r.status;
		let html = `<span class="etwa-status etwa-status-${r.status}">${label}</span>${agingBadge(r)}`;
		if (r.status === 'pending') {
			html += `<div class="etwa-approval-badges">
				<span class="etwa-approval-badge${r.dept_approved ? ' is-done' : ''}">${cfg.i18n?.deptApproval || 'Department'}</span>
				<span class="etwa-approval-badge${r.hr_approved ? ' is-done' : ''}">${cfg.i18n?.hrApproval || 'HR'}</span>
			</div>`;
		}
		return html;
	}

	function reviewActions(r, source) {
		const buttons = [];
		if (r.status !== 'pending') {
			return `<button type="button" class="etwa-link etwa-view-request-detail" data-id="${r.id}" data-source="${source}">${cfg.i18n?.view || 'View'}</button>`;
		}
		if (r.can_approve_as_department) {
			buttons.push(`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-source="${source}" data-slot="department">${cfg.i18n?.approveAsDepartment || 'Approve (Department)'}</button>`);
		}
		if (r.can_approve_as_hr) {
			const dayAttrs =
				source === 'vacation'
					? ` data-needs-days="1" data-start="${r.start_date || ''}" data-end="${r.end_date || ''}"`
					: '';
			buttons.push(
				`<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm etwa-approve-request" data-id="${r.id}" data-source="${source}" data-slot="hr"${dayAttrs}>${cfg.i18n?.approveAsHr || 'Approve (HR)'}</button>`
			);
		}
		if (r.can_reject) {
			buttons.push(`<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-sm etwa-reject-request" data-id="${r.id}" data-source="${source}">${cfg.i18n?.reject || 'Reject'}</button>`);
		}
		buttons.push(`<button type="button" class="etwa-link etwa-view-request-detail" data-id="${r.id}" data-source="${source}">${cfg.i18n?.view || 'View'}</button>`);
		return buttons.join(' ');
	}

	function populateEmployees() {
		const selects = '#etwa-vac-req-employee, #etwa-wfh-req-employee, #etwa-perm-req-employee, #etwa-holiday-req-employee';
		$(selects).each(function () {
			const current = $(this).val();
			let html = `<option value="">${cfg.i18n?.allEmployees || 'All employees'}</option>`;
			employees.forEach((e) => {
				html += `<option value="${e.id}">${e.display_name}</option>`;
			});
			$(this).html(html);
			if (current) $(this).val(String(current));
		});
	}

	function buildParams(page, prefix) {
		const params = new URLSearchParams({ page: String(page), per_page: '25' });
		const userId = $(`#${prefix}-employee`).val();
		const status = $(`#${prefix}-status`).val();
		const from = $(`#${prefix}-from`).val();
		const to = $(`#${prefix}-to`).val();
		const search = $(`#${prefix}-search`).val()?.trim();
		if (userId) params.set('user_id', userId);
		if (status) params.set('status', status);
		if (from) params.set('date_from', from);
		if (to) params.set('date_to', to);
		if (search) params.set('search', search);
		return params;
	}

	function renderPagination(selector, total, current, setter, loader) {
		const pages = Math.max(1, Math.ceil((total || 0) / 25));
		let html = '';
		for (let i = 1; i <= pages; i += 1) {
			html += `<button type="button" class="etwa-btn etwa-btn-secondary${i === current ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
		}
		$(selector).html(html).off('click').on('click', 'button[data-page]', function () {
			setter(parseInt($(this).data('page'), 10) || 1);
			loader();
		});
	}

	function loadVacationRequests() {
		const params = buildParams(vacPage, 'etwa-vac-req');
		$('#etwa-vac-req-body').html('<tr><td colspan="11"><div class="etwa-skeleton" style="height:40px"></div></td></tr>');

		api(`vacations/requests?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];

				if (!items.length) {
					$('#etwa-vac-req-body').html(
						`<tr><td colspan="11" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$('#etwa-vac-req-pagination').empty();
					return;
				}

				$('#etwa-vac-req-body').html(
					items
						.map(
							(r) => `<tr>
						${rowCheckbox(r, 'vacation')}
						<td>${r.employee_name || ''}</td>
						<td>${r.department_name || '—'}</td>
						<td>${r.leave_type_name || ''}</td>
						<td>${r.start_date || ''}</td>
						<td>${r.end_date || ''}</td>
						<td>${r.days || ''}</td>
						<td>${r.dept_status_label || '—'}</td>
						<td>${r.hr_status_label || '—'}</td>
						<td>${requestStatusHtml(r)}</td>
						<td class="etwa-actions-cell">${reviewActions(r, 'vacation')}</td>
					</tr>`
						)
						.join('')
				);
				updateBulkToolbar();
				renderPagination('#etwa-vac-req-pagination', data.total, vacPage, (p) => {
					vacPage = p;
				}, loadVacationRequests);
			})
			.catch((err) => ui.error(err.message));
	}

	function loadGenericRequests(type, page, prefix, bodySelector, paginationSelector, pageSetter, loader) {
		const colSpan = type === 'wfh' ? 7 : 8;
		const params = buildParams(page, prefix);
		params.set('request_type', type);
		$(bodySelector).html(`<tr><td colspan="${colSpan}"><div class="etwa-skeleton" style="height:40px"></div></td></tr>`);

		api(`requests?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const items = data.items || [];

				if (!items.length) {
					$(bodySelector).html(
						`<tr><td colspan="${colSpan}" class="etwa-empty-cell">${cfg.i18n?.noRequests || 'No requests found.'}</td></tr>`
					);
					$(paginationSelector).empty();
					return;
				}

				if (type === 'wfh') {
					$(bodySelector).html(
						items
							.map(
								(r) => `<tr>
							${rowCheckbox(r, 'generic')}
							<td>${r.employee_name || ''}</td>
							<td>${r.work_date || ''}</td>
							<td>${r.time_range || ''}</td>
							<td>${r.reason || ''}</td>
							<td>${requestStatusHtml(r)}</td>
							<td class="etwa-actions-cell">${reviewActions(r, 'generic')}</td>
						</tr>`
							)
							.join('')
					);
				} else if (type === 'holiday_work') {
					$(bodySelector).html(
						items
							.map(
								(r) => `<tr>
							${rowCheckbox(r, 'generic')}
							<td>${r.employee_name || ''}</td>
							<td>${r.work_date || ''}</td>
							<td>${r.time_range || ''}</td>
							<td>${r.holiday_work_type_label || ''}</td>
							<td>${r.client_name || ''}</td>
							<td>${requestStatusHtml(r)}</td>
							<td class="etwa-actions-cell">${reviewActions(r, 'generic')}</td>
						</tr>`
							)
							.join('')
					);
				} else {
					$(bodySelector).html(
						items
							.map(
								(r) => `<tr>
							${rowCheckbox(r, 'generic')}
							<td>${r.employee_name || ''}</td>
							<td>${r.work_date || r.request_date || ''}</td>
							<td>${r.time_range || r.duration_label || ''}</td>
							<td>${r.permission_type_label || ''}</td>
							<td>${r.reason_label || r.reason || ''}</td>
							<td>${requestStatusHtml(r)}</td>
							<td class="etwa-actions-cell">${reviewActions(r, 'generic')}</td>
						</tr>`
							)
							.join('')
					);
				}
				updateBulkToolbar();

				renderPagination(paginationSelector, data.total, page, pageSetter, loader);
			})
			.catch((err) => ui.error(err.message));
	}

	function loadWfhRequests() {
		loadGenericRequests(
			'wfh',
			wfhPage,
			'etwa-wfh-req',
			'#etwa-wfh-req-body',
			'#etwa-wfh-req-pagination',
			(p) => {
				wfhPage = p;
			},
			loadWfhRequests
		);
	}

	function loadPermissionRequests() {
		loadGenericRequests(
			'permission',
			permPage,
			'etwa-perm-req',
			'#etwa-perm-req-body',
			'#etwa-perm-req-pagination',
			(p) => {
				permPage = p;
			},
			loadPermissionRequests
		);
	}

	function loadHolidayWorkRequests() {
		loadGenericRequests(
			'holiday_work',
			holidayPage,
			'etwa-holiday-req',
			'#etwa-holiday-req-body',
			'#etwa-holiday-req-pagination',
			(p) => {
				holidayPage = p;
			},
			loadHolidayWorkRequests
		);
	}

	function reviewRequest(id, status, source, slot, meta = {}) {
		let msg = '';
		if (status === 'approved') {
			if (slot === 'hr') msg = cfg.i18n?.confirmApproveHr || 'Record your HR approval?';
			else if (slot === 'owner') msg = cfg.i18n?.confirmApproveOwner || 'Record your Owner approval?';
			else if (slot === 'department') msg = cfg.i18n?.confirmApproveDepartment || 'Record your department approval?';
			else msg = cfg.i18n?.confirmApproveRequest || 'Approve this request?';
		} else {
			msg = cfg.i18n?.confirmRejectRequest || 'Reject this request?';
		}

		const needsDays =
			meta.needsDays != null ? !!meta.needsDays : vacationHrNeedsDays(source, slot);

		const perform = (notes, days) => {
			submitReview(id, status, source, slot, notes, days)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					const data = res.data || {};
					if (data.status === 'pending' && status === 'approved') {
						ui.success(cfg.i18n?.requestPartiallyApproved || 'Approval recorded. Waiting for the other reviewer.');
					} else {
						ui.success(
							status === 'approved'
								? cfg.i18n?.requestApproved || 'Request approved.'
								: cfg.i18n?.requestRejected || 'Request rejected.'
						);
					}
					reloadActiveTab();
					refreshAllBadges();
					$('#etwa-request-detail-modal').attr('hidden', 'hidden');
					updateBulkToolbar();
				})
				.catch((err) => ui.error(err.message));
		};

		if (status === 'rejected') {
			ui.confirm(msg, cfg.i18n?.reject || 'Reject').then((ok) => {
				if (!ok) return;
				ui.promptReason(cfg.i18n?.rejectReasonOptional || 'Rejection reason (optional)', { optional: true }).then((notes) => {
					if (notes === null) return;
					perform(String(notes).trim());
				});
			});
			return;
		}

		if (needsDays) {
			ui.promptDaysToDeduct({
				startDate: meta.startDate || '',
				endDate: meta.endDate || '',
				api,
			}).then((days) => {
				if (days == null) return;
				perform('', days);
			});
			return;
		}

		ui.confirm(msg, cfg.i18n?.approve || 'Approve').then((ok) => {
			if (ok) perform('');
		});
	}

	function showDetail(id, source) {
		const path = source === 'vacation' ? `vacations/requests/${id}` : `requests/${id}`;

		api(path)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const r = res.data || {};
				const history = (r.approval_history || [])
					.map((h) => `<li><strong>${h.label}</strong>: ${h.by} — ${h.at}</li>`)
					.join('');

				let details = `
					<dl class="etwa-detail-list">
						<dt>${cfg.i18n?.employee || 'Employee'}</dt><dd>${r.employee_name || ''}</dd>
						<dt>${cfg.i18n?.submittedDate || 'Submitted Date'}</dt><dd>${r.created_at_label || ui.formatDateTime12(r.created_at) || ''}${r.is_aging ? ` <span class="etwa-aging-badge">${cfg.i18n?.aging || '>48h'}</span>` : ''}</dd>
						<dt>${cfg.i18n?.status || 'Status'}</dt><dd>${r.status_label || r.status || ''}</dd>
						${ui.requestReviewDetailRows(r)}
				`;

				if (source === 'vacation') {
					details += `
						<dt>${cfg.i18n?.leaveType || 'Leave Type'}</dt><dd>${r.leave_type_name || ''}</dd>
						<dt>${cfg.i18n?.startDate || 'From'}</dt><dd>${r.start_date || ''}</dd>
						<dt>${cfg.i18n?.endDate || 'To'}</dt><dd>${r.end_date || ''}</dd>
						<dt>${cfg.i18n?.days || 'Days'}</dt><dd>${r.days || ''}</dd>
					`;
				} else if (r.request_type === 'wfh') {
					details += `
						<dt>${cfg.i18n?.workDate || 'Work Date'}</dt><dd>${r.work_date || ''}</dd>
						<dt>${cfg.i18n?.time || 'Time'}</dt><dd>${r.time_range || ''}</dd>
					`;
				} else if (r.request_type === 'holiday_work') {
					details += `
						<dt>${cfg.i18n?.workDate || 'Work Date'}</dt><dd>${r.work_date || ''}</dd>
						<dt>${cfg.i18n?.time || 'Time'}</dt><dd>${r.time_range || ''}</dd>
						<dt>${cfg.i18n?.workType || 'Work Type'}</dt><dd>${r.holiday_work_type_label || ''}</dd>
						<dt>${cfg.i18n?.client || 'Client'}</dt><dd>${r.client_name || ''}</dd>
						<dt>${cfg.i18n?.task || 'Task'}</dt><dd>${r.task || ''}</dd>
					`;
				} else if (r.request_type === 'permission') {
					details += `
						<dt>${cfg.i18n?.date || 'Date'}</dt><dd>${r.work_date || r.request_date || ''}</dd>
						<dt>${cfg.i18n?.time || 'Time'}</dt><dd>${r.time_range || r.duration_label || ''}</dd>
						<dt>${cfg.i18n?.permissionType || 'Permission Type'}</dt><dd>${r.permission_type_label || ''}</dd>
						<dt>${cfg.i18n?.reason || 'Reason'}</dt><dd>${r.reason_label || r.reason || ''}</dd>
					`;
				}

				if (r.request_type !== 'permission' && source !== 'vacation') {
					details += `<dt>${cfg.i18n?.reason || 'Reason'}</dt><dd>${r.reason || ''}</dd>`;
				}

				details += '</dl>';
				details += timelineHtml(r);
				if (history && !(r.approval_timeline || []).length) {
					details += `<h4>${cfg.i18n?.approvalHistory || 'Approval History'}</h4><ul class="etwa-detail-history">${history}</ul>`;
				}
				details += `<p class="etwa-muted">${cfg.i18n?.attachedFilesFuture || 'Attached files — coming soon.'}</p>`;

				$('#etwa-request-detail-body').html(details);
				$('#etwa-request-detail-actions').html(
					r.status === 'pending' ? reviewActions(r, source) : ''
				);
				$('#etwa-request-detail-modal').removeAttr('hidden').attr('aria-hidden', 'false');
			})
			.catch((err) => ui.error(err.message));
	}

	function exportRequests(type) {
		if (!canExport) return;
		const prefixMap = {
			vacation: 'etwa-vac-req',
			wfh: 'etwa-wfh-req',
			permission: 'etwa-perm-req',
			holiday_work: 'etwa-holiday-req',
		};
		const prefix = prefixMap[type] || 'etwa-perm-req';
		const params = buildParams(1, prefix);
		if (type !== 'vacation') params.set('request_type', type);

		const path = type === 'vacation' ? 'vacations/requests/export' : 'requests/export';
		ui.info(cfg.i18n?.exporting || 'Exporting…');

		api(`${path}?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				const blob = Uint8Array.from(atob(data.content || ''), (c) => c.charCodeAt(0));
				const url = URL.createObjectURL(new Blob([blob], { type: data.mime }));
				const a = document.createElement('a');
				a.href = url;
				a.download = data.filename || 'export.xlsx';
				a.click();
				URL.revokeObjectURL(url);
				ui.success(cfg.i18n?.exportReady || 'Export ready.');
			})
			.catch((err) => ui.error(err.message));
	}

	function switchTab(tab) {
		app.find('[data-request-tab]').removeClass('is-active');
		app.find(`[data-request-tab="${tab}"]`).addClass('is-active');
		app.find('[data-request-panel]').attr('hidden', 'hidden').removeClass('is-active');
		app.find(`[data-request-panel="${tab}"]`).removeAttr('hidden').addClass('is-active');
		if (tab === 'vacation') loadVacationRequests();
		if (tab === 'wfh') loadWfhRequests();
		if (tab === 'permission') loadPermissionRequests();
		if (tab === 'holiday_work') loadHolidayWorkRequests();
	}

	function debounce(fn, wait) {
		let timer;
		return function (...args) {
			clearTimeout(timer);
			timer = setTimeout(() => fn.apply(this, args), wait);
		};
	}

	$(function () {
		refreshAllBadges();

		api('vacations/employees')
			.then((empRes) => {
				if (empRes.success) {
					employees = empRes.data || [];
					populateEmployees();
				}
			})
			.catch(() => {});

		const initialTab = app.data('initial-tab') || 'vacation';
		switchTab(initialTab);

		app.on('click', '[data-request-tab]', function () {
			switchTab($(this).data('request-tab'));
		});

		$('#etwa-vac-req-employee, #etwa-vac-req-status, #etwa-vac-req-from, #etwa-vac-req-to').on('change', () => {
			vacPage = 1;
			loadVacationRequests();
		});
		$('#etwa-vac-req-search').on('input', debounce(() => {
			vacPage = 1;
			loadVacationRequests();
		}, 300));

		$('#etwa-wfh-req-employee, #etwa-wfh-req-status, #etwa-wfh-req-from, #etwa-wfh-req-to').on('change', () => {
			wfhPage = 1;
			loadWfhRequests();
		});
		$('#etwa-wfh-req-search').on('input', debounce(() => {
			wfhPage = 1;
			loadWfhRequests();
		}, 300));

		$('#etwa-perm-req-employee, #etwa-perm-req-status, #etwa-perm-req-from, #etwa-perm-req-to').on('change', () => {
			permPage = 1;
			loadPermissionRequests();
		});
		$('#etwa-perm-req-search').on('input', debounce(() => {
			permPage = 1;
			loadPermissionRequests();
		}, 300));

		$('#etwa-holiday-req-employee, #etwa-holiday-req-status, #etwa-holiday-req-from, #etwa-holiday-req-to').on('change', () => {
			holidayPage = 1;
			loadHolidayWorkRequests();
		});
		$('#etwa-holiday-req-search').on('input', debounce(() => {
			holidayPage = 1;
			loadHolidayWorkRequests();
		}, 300));

		$('#etwa-vac-req-export').on('click', () => exportRequests('vacation'));
		$('#etwa-wfh-req-export').on('click', () => exportRequests('wfh'));
		$('#etwa-perm-req-export').on('click', () => exportRequests('permission'));
		$('#etwa-holiday-req-export').on('click', () => exportRequests('holiday_work'));

		app.on('click', '.etwa-approve-request', function () {
			const $btn = $(this);
			reviewRequest($btn.data('id'), 'approved', $btn.data('source'), $btn.data('slot') || '', {
				needsDays: $btn.attr('data-needs-days') === '1',
				startDate: $btn.attr('data-start') || '',
				endDate: $btn.attr('data-end') || '',
			});
		});

		app.on('click', '.etwa-reject-request', function () {
			reviewRequest($(this).data('id'), 'rejected', $(this).data('source'));
		});

		app.on('click', '.etwa-view-request-detail', function () {
			showDetail($(this).data('id'), $(this).data('source'));
		});

		$('#etwa-request-detail-modal').on('click', '.etwa-modal-close', function () {
			$('#etwa-request-detail-modal').attr('hidden', 'hidden').attr('aria-hidden', 'true');
		});

		app.on('change', '.etwa-bulk-select', updateBulkToolbar);
		$('#etwa-bulk-approve').on('click', () => bulkReview('approved'));
	});
})(jQuery);
