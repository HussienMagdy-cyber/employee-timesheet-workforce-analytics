(function ($, window) {
	'use strict';

	function escapeAttr(value) {
		return String(value).replace(/"/g, '&quot;');
	}

	function renderMonth(data, state) {
		const cfg = state.cfg || {};
		const label = new Date(state.year, state.month - 1, 1).toLocaleDateString(undefined, {
			month: 'long',
			year: 'numeric',
		});
		$(`#${state.prefix}-cal-label`).text(label);

		const first = new Date(state.year, state.month - 1, 1);
		const lastDay = new Date(state.year, state.month, 0).getDate();
		const startDow = first.getDay();
		let html = '<div class="etwa-vacation-cal-grid">';
		for (let i = 0; i < startDow; i += 1) {
			html += '<div class="etwa-vacation-cal-day is-empty"></div>';
		}
		for (let d = 1; d <= lastDay; d += 1) {
			const dateStr = `${state.year}-${String(state.month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
			const events = (data.events || []).filter((ev) => ev.start_date <= dateStr && ev.end_date >= dateStr);
			const holiday = (data.holidays || {})[dateStr];
			const dayClasses = ['etwa-vacation-cal-day'];
			if (holiday) dayClasses.push('has-holiday');
			if (events.length) dayClasses.push('has-event');
			const tips = [
				holiday ? `${cfg.i18n?.companyHoliday || 'Company holiday'}: ${holiday.name}` : '',
				...events.map(
					(ev) =>
						`${ev.employee_name}: ${ev.leave_type_name}\n${ev.start_date} – ${ev.end_date}\n${ev.days} days`
				),
			]
				.filter(Boolean)
				.join('\n\n');
			html += `<div class="${dayClasses.join(' ')}" title="${escapeAttr(tips)}">
				<span class="etwa-vacation-cal-num">${d}</span>
				${holiday ? `<span class="etwa-vacation-cal-holiday">${holiday.name}</span>` : ''}
				${events
					.map((ev) => {
						const isMine = state.highlightUserId && ev.user_id === state.highlightUserId;
						return `<span class="etwa-vacation-cal-event${isMine ? ' is-mine' : ''}">${ev.employee_name}</span>`;
					})
					.join('')}
			</div>`;
		}
		html += '</div>';
		$(`#${state.prefix}-calendar`).html(html);
	}

	window.etwaVacationCalendar = {
		init(options) {
			const state = {
				prefix: options.prefix || 'etwa-vacation',
				api: options.api,
				cfg: options.cfg || window.etwaApp || {},
				ui: options.ui || window.etwaUI || {},
				highlightUserId: options.highlightUserId || null,
				year: new Date().getFullYear(),
				month: new Date().getMonth() + 1,
			};

			if (!$(`#${state.prefix}-calendar`).length) {
				return null;
			}

			const load = () =>
				state
					.api(`vacations/calendar?year=${state.year}&month=${state.month}`)
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						renderMonth(res.data || {}, state);
					})
					.catch((err) => state.ui.error(err.message));

			$(`#${state.prefix}-cal-prev`).on('click', function () {
				state.month -= 1;
				if (state.month < 1) {
					state.month = 12;
					state.year -= 1;
				}
				load();
			});

			$(`#${state.prefix}-cal-next`).on('click', function () {
				state.month += 1;
				if (state.month > 12) {
					state.month = 1;
					state.year += 1;
				}
				load();
			});

			return { load };
		},
	};
})(jQuery, window);
