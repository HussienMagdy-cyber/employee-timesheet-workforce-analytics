(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {
		error: (m) => window.alert(m),
		success: (m) => window.alert(m),
		confirm: (m) => Promise.resolve(window.confirm(m)),
	};

	const loadedTabs = {};

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	function debounce(fn, ms) {
		let timer;
		return function (...args) {
			clearTimeout(timer);
			timer = setTimeout(() => fn.apply(this, args), ms);
		};
	}

	function filterParams(overrides = {}) {
		const params = {
			status: $('#etwa-hr-status-filter').val() || '',
			date_from: $('#etwa-hr-date-from').val() || '',
			date_to: $('#etwa-hr-date-to').val() || '',
			search: $('#etwa-hr-search').val() || '',
		};
		const deptId = $('#etwa-hr-dept-filter').val();
		if (deptId) params.department_id = deptId;
		return new URLSearchParams(Object.assign(params, overrides));
	}

	function loadDepartments() {
		return api('departments').then((res) => {
			if (!res.success) return;
			const items = res.data?.items || [];
			let html = `<option value="">${cfg.i18n?.allDepartments || 'All departments'}</option>`;
			items.forEach((d) => {
				html += `<option value="${d.id}">${d.name}</option>`;
			});
			$('#etwa-hr-dept-filter').html(html);
		});
	}

	function timesheetRowHtml(ts) {
		const weeksUrl = new URL(window.location.href);
		weeksUrl.searchParams.set('review_user', String(ts.user_id));
		weeksUrl.searchParams.delete('etwa_week');

		const reportUrl = new URL(window.location.href);
		reportUrl.searchParams.set('review_user', String(ts.user_id));
		reportUrl.searchParams.set('etwa_week', ts.period_start);

		const totalHours = Number(ts.total_regular_hours) + Number(ts.total_overtime_hours);

		const approveBtns =
			ts.status === 'submitted'
				? `<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-small etwa-approve-ts" data-id="${ts.id}">Approve</button>
				   <button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-small etwa-reject-ts" data-id="${ts.id}">Reject</button>`
				: '';

		return `
			<tr data-id="${ts.id}">
				<td><a class="etwa-table-link" href="${weeksUrl.toString()}">${ts.employee_name || ts.user_id}</a></td>
				<td>${ts.period_start} → ${ts.period_end}</td>
				<td>${totalHours.toFixed(2)}</td>
				<td><span class="etwa-status etwa-status-${ts.status}">${ts.status}</span></td>
				<td class="etwa-hr-row-actions">
					<a class="etwa-btn etwa-btn-secondary etwa-btn-small" href="${reportUrl.toString()}">Week report</a>
					${approveBtns}
				</td>
			</tr>
		`;
	}

	function renderTimesheetRows($body, items, emptyMsg) {
		$body.empty();
		if (!items.length) {
			$body.append(`<tr><td colspan="5">${emptyMsg}</td></tr>`);
			return;
		}
		items.forEach((ts) => $body.append(timesheetRowHtml(ts)));
	}

	function loadTimesheetsInto($body, overrides, emptyMsg) {
		$body.html(`<tr class="etwa-hr-loading-row"><td colspan="5">${cfg.i18n?.loading || 'Loading…'}</td></tr>`);
		return api(`timesheets?${filterParams(overrides).toString()}`).then((res) => {
			if (!res.success) {
				$body.html(`<tr><td colspan="5">${res.message || cfg.i18n?.error || 'Error'}</td></tr>`);
				return;
			}
			renderTimesheetRows($body, res.data.items || [], emptyMsg);
		});
	}

	function loadPendingTab() {
		return loadTimesheetsInto(
			$('#etwa-hr-pending-body'),
			{ status: 'submitted' },
			cfg.i18n?.noPending || 'No pending timesheets.'
		);
	}

	function loadAllReportsTab() {
		return loadTimesheetsInto(
			$('#etwa-hr-all-body'),
			{},
			cfg.i18n?.noReports || 'No reports found.'
		);
	}

	function ensureTabLoaded(tab) {
		if (loadedTabs[tab]) {
			return Promise.resolve();
		}
		let promise;
		if (tab === 'pending') {
			promise = loadPendingTab();
		} else if (tab === 'all-reports') {
			promise = loadAllReportsTab();
		} else {
			return Promise.resolve();
		}
		return promise.then(() => {
			loadedTabs[tab] = true;
		});
	}

	function activateTab(tab) {
		if (!tab) return;
		$('.etwa-hr-tabs .etwa-tab').removeClass('active');
		$(`.etwa-hr-tabs .etwa-tab[data-tab="${tab}"]`).addClass('active');
		$('.etwa-tab-panel').removeClass('active');
		$(`#etwa-tab-${tab}`).addClass('active');
		ensureTabLoaded(tab);
	}

	function bindTabs() {
		$('.etwa-hr-tabs .etwa-tab').on('click', function () {
			activateTab($(this).data('tab'));
		});

		$('#etwa-hr-app').on('click', 'a.etwa-kpi-card[data-hr-tab]', function (e) {
			const tab = $(this).data('hr-tab');
			const status = $(this).data('hr-status');
			if (status) {
				$('#etwa-hr-status-filter').val(String(status));
				loadedTabs['all-reports'] = false;
			}
			activateTab(tab);
			const panel = document.getElementById(`etwa-tab-${tab}`);
			if (panel) {
				e.preventDefault();
				panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
				if (this.hash) {
					history.replaceState(null, '', this.hash);
				}
			}
		});
	}

	function loadFilteredTimesheets() {
		const status = $('#etwa-hr-status-filter').val() || '';
		const $pending = $('#etwa-hr-pending-body');
		const $all = $('#etwa-hr-all-body');

		if (status === 'submitted' || !status) {
			loadTimesheetsInto($pending, { status: status || 'submitted' }, cfg.i18n?.noPending || 'No pending timesheets.');
			loadedTabs.pending = true;
		}
		loadTimesheetsInto($all, { status }, cfg.i18n?.noReports || 'No reports found.');
		loadedTabs['all-reports'] = true;

		$('.etwa-hr-tabs .etwa-tab[data-tab="all-reports"]').trigger('click');
	}

	function reviewTimesheet(id, status) {
		const confirmMsg = 'approved' === status ? cfg.i18n?.confirmApprove : cfg.i18n?.confirmReject;
		ui.confirm(confirmMsg, 'approved' === status ? cfg.i18n?.approved : cfg.i18n?.rejected).then((ok) => {
			if (!ok) return;

			api(`approvals/timesheets/${id}`, {
				method: 'POST',
				body: JSON.stringify({ status, notes: '' }),
			})
				.then((res) => {
					if (!res.success) {
						ui.error(res.message || cfg.i18n?.error || 'Error');
						return;
					}
					ui.success('approved' === status ? cfg.i18n?.approved : cfg.i18n?.rejected);
					loadedTabs.pending = false;
					loadedTabs['all-reports'] = false;
					ensureTabLoaded('pending');
					if ($('#etwa-tab-all-reports').hasClass('active')) {
						loadedTabs['all-reports'] = false;
						ensureTabLoaded('all-reports');
					}
				})
				.catch((err) => ui.error(err.message || cfg.i18n?.error || 'Error'));
		});
	}

	$(function () {
		if (!$('#etwa-hr-app').length && !$('.etwa-hr-review').length) return;

		loadDepartments();
		bindTabs();

		const hash = window.location.hash || '';
		if (hash.startsWith('#etwa-tab-')) {
			const tab = hash.replace('#etwa-tab-', '');
			if (['employees', 'pending', 'all-reports', 'export'].includes(tab)) {
				activateTab(tab);
			}
		}

		$('#etwa-hr-filter').on('click', loadFilteredTimesheets);
		$('#etwa-hr-dept-filter').on('change', loadFilteredTimesheets);
		$('#etwa-hr-search').on(
			'input',
			debounce(function () {
				if ($('#etwa-tab-all-reports').hasClass('active')) {
					loadFilteredTimesheets();
				}
			}, 300)
		);

		$(document).on('click', '.etwa-approve-ts', function () {
			reviewTimesheet($(this).data('id'), 'approved');
		});
		$(document).on('click', '.etwa-reject-ts', function () {
			reviewTimesheet($(this).data('id'), 'rejected');
		});

		$('#etwa-export-excel').on('click', () => {
			const body = {
				date_from: $('#etwa-export-from').val(),
				date_to: $('#etwa-export-to').val(),
			};
			api('export/excel', { method: 'POST', body: JSON.stringify(body) }).then((res) => {
				if (!res.success) return alert(res.message);
				$('#etwa-export-result').html(`<a href="${res.data.file_url}" target="_blank">Download ${res.data.filename}</a>`);
			});
		});

		$('#etwa-email-report').on('click', () => {
			api('export/email', {
				method: 'POST',
				body: JSON.stringify({
					date_from: $('#etwa-export-from').val(),
					date_to: $('#etwa-export-to').val(),
				}),
			}).then((res) => {
				alert(res.success ? 'Report emailed to HR (Owner CC).' : res.message);
			});
		});

		$('#etwa-hr-status-save').on('click', function () {
			const timesheetId = $(this).data('id');
			const status = $('#etwa-hr-status-select').val();
			ui.promptReason(cfg.i18n?.updateStatus || 'Update status').then((reason) => {
				if (!reason) return;
				const $btn = $(this);
				ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

				api(`hr/timesheets/${timesheetId}`, {
					method: 'PUT',
					body: JSON.stringify({ status, edit_reason: reason }),
				})
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.statusUpdated || 'Status updated.');
						setTimeout(() => window.location.reload(), 900);
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn[0], false));
			});
		});
	});
})(jQuery);
