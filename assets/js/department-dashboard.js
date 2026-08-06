(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	if (!$('#etwa-dept-dashboard-app').length || !cfg.canViewDeptDashboard) return;

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	function reviewBaseUrl() {
		const baseUrl = new URL(window.location.href);
		baseUrl.searchParams.delete('review_user');
		baseUrl.searchParams.delete('etwa_week');
		return baseUrl;
	}

	function employeeWeeksUrl(userId) {
		const url = reviewBaseUrl();
		url.searchParams.set('review_user', String(userId));
		return url.toString();
	}

	function weekReportUrl(userId, periodStart) {
		const url = reviewBaseUrl();
		url.searchParams.set('review_user', String(userId));
		url.searchParams.set('etwa_week', periodStart);
		return url.toString();
	}

	function statusClass(status) {
		return `etwa-status etwa-status-${status === 'missing' ? 'draft' : status}`;
	}

	function renderLeaveCalendar(entries) {
		if (!entries.length) {
			return `<p class="etwa-muted">${cfg.i18n?.noUpcoming || 'No upcoming vacations.'}</p>`;
		}

		const byDate = {};
		entries.forEach((entry) => {
			if (!byDate[entry.date]) byDate[entry.date] = [];
			byDate[entry.date].push(entry);
		});

		const dates = Object.keys(byDate).sort();
		return dates
			.map((date) => {
				const items = byDate[date]
					.map((l) => `<li><strong>${l.employee_name}</strong> — ${l.leave_type_name}</li>`)
					.join('');
				return `<div class="etwa-dept-calendar-day"><strong>${date}</strong><ul class="etwa-dept-calendar-list">${items}</ul></div>`;
			})
			.join('');
	}

	function loadDashboard() {
		api('departments/dashboard').then((res) => {
			if (!res.success) return;
			const data = res.data || {};
			const stats = data.statistics || {};

			$('#etwa-dept-action-pending').text(String(data.pending_dept_action ?? 0));
			$('#etwa-dept-pending').text(String(data.pending_count ?? stats.pending_requests ?? 0));
			$('#etwa-dept-aging').text(String(data.aging_count ?? 0));
			$('#etwa-dept-approved').text(String(stats.approved_month ?? data.approved_count ?? 0));
			$('#etwa-dept-employees').text(String(stats.employee_count ?? (data.employees || []).length));

			const employees = data.employees || [];
			$('#etwa-dept-employees-body').html(
				employees.length
					? employees
							.map(
								(e) => `<tr>
						<td><strong>${e.display_name}</strong></td>
						<td>${e.email || ''}</td>
						<td class="etwa-hr-row-actions">
							<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="${employeeWeeksUrl(e.id)}">${cfg.i18n?.viewWeeks || 'View'}</a>
						</td>
					</tr>`
							)
							.join('')
					: `<tr><td colspan="4" class="etwa-empty-cell">${cfg.i18n?.noEmployees || 'No employees.'}</td></tr>`
			);

			const timesheets = data.team_timesheet_status || [];
			$('#etwa-dept-timesheet-body').html(
				timesheets.length
					? timesheets
							.map(
								(row) => `<tr>
						<td><strong>${row.display_name}</strong></td>
						<td><span class="${statusClass(row.status)}">${row.status_label}</span></td>
						<td>${row.total_hours ?? 0}</td>
						<td class="etwa-hr-row-actions">
							<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="${employeeWeeksUrl(row.user_id)}">${cfg.i18n?.viewWeeks || 'View'}</a>
							<a class="etwa-btn etwa-btn-secondary etwa-btn-small" href="${weekReportUrl(row.user_id, row.period_start)}">${cfg.i18n?.weekReport || 'Week report'}</a>
						</td>
					</tr>`
							)
							.join('')
					: `<tr><td colspan="4" class="etwa-empty-cell">${cfg.i18n?.noEmployees || 'No employees.'}</td></tr>`
			);

			$('#etwa-dept-leave-calendar').html(renderLeaveCalendar(data.leave_calendar || []));

			const leave = data.upcoming_leave || [];
			$('#etwa-dept-upcoming-leave').html(
				leave.length
					? leave
							.map(
								(l) =>
									`<div class="etwa-list-item"><strong>${l.employee_name}</strong><span>${l.start_date} – ${l.end_date}</span><span class="etwa-muted">${l.leave_type_name}</span></div>`
							)
							.join('')
					: `<p class="etwa-muted">${cfg.i18n?.noUpcoming || 'No upcoming vacations.'}</p>`
			);
		});
	}

	$(loadDashboard);
})(jQuery);
