(function ($, wp) {
	'use strict';

	function debounce(fn, ms) {
		let timer;
		return function (...args) {
			clearTimeout(timer);
			timer = setTimeout(() => fn.apply(this, args), ms);
		};
	}

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	const isHrEdit = () => String($('#etwa-employee-app').data('hrEdit')) === '1';
	const reviewUserId = () => $('#etwa-employee-app').data('reviewUserId');

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

	let timeSlots = [];
	let timeSlotOptions = [];
	let breakClientId = null;
	let allClientsClientId = null;

	function loadTimeSlots() {
		const path = isHrEdit() ? 'hr/time-slots' : 'time-slots';
		return api(path).then((res) => {
			if (res.success) {
				timeSlots = res.data.slots || [];
				timeSlotOptions = res.data.slot_options || timeSlots.map((slot) => ({ value: slot, label: ui.formatTime12(slot) }));
				populateSelect('#etwa-start-time', timeSlotOptions);
				populateSelect('#etwa-end-time', timeSlotOptions);
			}
		});
	}

	function populateSelect(selector, options) {
		const $el = $(selector);
		$el.empty();
		options.forEach((option) => {
			const value = typeof option === 'string' ? option : option.value;
			const label = typeof option === 'string' ? ui.formatTime12(option) : option.label;
			$el.append(`<option value="${value}">${label}</option>`);
		});
	}

	function detectBreakClientId() {
		$('#etwa-client-id optgroup option, #etwa-client-id > option').each(function () {
			const text = $(this).text().trim().toLowerCase();
			if (text === 'break') {
				breakClientId = $(this).val();
			}
			if (text === 'all clients') {
				allClientsClientId = $(this).val();
			}
		});
		if (!breakClientId && cfg.breakClientId) {
			breakClientId = String(cfg.breakClientId);
		}
		if (!allClientsClientId && cfg.allClientsClientId) {
			allClientsClientId = String(cfg.allClientsClientId);
		}
		if (!allClientsClientId) {
			allClientsClientId = String($('#etwa-employee-app').data('allClientsClientId') || '');
		}
	}

	function openModal(title, date, entry) {
		$('#etwa-modal-title').text(title);
		$('#etwa-entry-modal').prop('hidden', false).attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
		$('#etwa-entry-date').val(date || '');

		if (entry) {
			$('#etwa-entry-id').val(entry.id);
			$('#etwa-start-time').val(entry.start);
			$('#etwa-end-time').val(entry.end);
			$('#etwa-client-id').val(entry.clientId);
			$('#etwa-task').val(entry.task);
			$('#etwa-notes').val(entry.notes);
		} else {
			$('#etwa-entry-id').val('');
			$('#etwa-task').val('');
			$('#etwa-notes').val('');
			if (isHrEdit()) {
				$('#etwa-edit-reason').val('');
			}
			if (timeSlots.length > 1) {
				$('#etwa-start-time').val(timeSlots[0]);
				$('#etwa-end-time').val(timeSlots[1]);
			}
		}

		toggleTaskRequired();
		updateDurationPreview();
		setTimeout(() => $('#etwa-client-id').trigger('focus'), 50);
	}

	function closeModal() {
		$('#etwa-entry-modal').prop('hidden', true).attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
		$('#etwa-entry-form')[0].reset();
		$('#etwa-entry-id').val('');
		$('#etwa-duration-preview').text('');
	}

	function toggleTaskRequired() {
		const isBreak = String($('#etwa-client-id').val()) === String(breakClientId);
		const isAllClients = String($('#etwa-client-id').val()) === String(allClientsClientId);
		$('#etwa-task').prop('required', !isBreak);
		$('#etwa-notes').prop('required', isAllClients);
		$('#etwa-all-clients-hint').prop('hidden', !isAllClients);
		if (isBreak && !$('#etwa-task').val()) {
			$('#etwa-task').attr('placeholder', 'Break');
		} else {
			$('#etwa-task').attr('placeholder', cfg.i18n?.taskPlaceholder || 'e.g. Website');
		}
		if (isAllClients) {
			$('#etwa-notes').attr(
				'placeholder',
				cfg.i18n?.allClientsNotesPlaceholder || 'Describe the work performed in detail (required for all-clients entries)…'
			);
		} else {
			$('#etwa-notes').attr('placeholder', cfg.i18n?.notesPlaceholder || 'Optional details…');
		}
	}

	function updateDurationPreview() {
		const start = $('#etwa-start-time').val();
		const end = $('#etwa-end-time').val();
		const $preview = $('#etwa-duration-preview');

		if (!start || !end) {
			$preview.text('');
			return;
		}

		const startParts = start.split(':').map(Number);
		const endParts = end.split(':').map(Number);
		const startMin = startParts[0] * 60 + startParts[1];
		const endMin = endParts[0] * 60 + endParts[1];
		const diff = endMin - startMin;

		if (diff <= 0) {
			$preview.text(cfg.i18n?.invalidDuration || 'End time must be after start time.').addClass('is-error');
			return;
		}

		const hours = (diff / 60).toFixed(1);
		$preview.removeClass('is-error').text(
			(cfg.i18n?.durationPreview || 'Duration: %s hours').replace('%s', hours)
		);
	}

	function autoAdvanceEndTime() {
		const start = $('#etwa-start-time').val();
		const end = $('#etwa-end-time').val();
		if (!start || !timeSlots.length) return;

		const startIdx = timeSlots.indexOf(start);
		if (startIdx >= 0 && startIdx < timeSlots.length - 1) {
			const nextSlot = timeSlots[startIdx + 1];
			if (!end || timeSlots.indexOf(end) <= startIdx) {
				$('#etwa-end-time').val(nextSlot);
			}
		}
		updateDurationPreview();
	}

	function entryFromChip($chip) {
		return {
			id: $chip.data('entry-id'),
			date: $chip.data('entry-date'),
			start: $chip.data('start-time'),
			end: $chip.data('end-time'),
			clientId: $chip.data('client-id'),
			task: $chip.data('task') || '',
			notes: $chip.data('notes') || '',
		};
	}

	function formatHours(value) {
		return Number(value || 0).toFixed(1);
	}

	function formatHoursFull(value) {
		return Number(value || 0).toFixed(2);
	}

	function renderEntryChip(entry) {
		const isBreak = String(entry.client_id) === String(breakClientId);
		const total = Number(entry.regular_hours || 0) + Number(entry.overtime_hours || 0);
		const hoursHtml = isBreak
			? `<span class="etwa-break-tag">${formatHours(entry.duration_minutes / 60)}h ${cfg.i18n?.breakLabel || 'Break'}</span>`
			: `${formatHours(total)}h`;

		return `
			<article
				class="etwa-entry-chip${isBreak ? ' is-break' : ''} is-clickable"
				data-entry-id="${entry.id}"
				data-entry-date="${entry.entry_date}"
				data-start-time="${entry.start_time}"
				data-end-time="${entry.end_time}"
				data-client-id="${entry.client_id}"
				data-task="${$('<div>').text(entry.task || '').html()}"
				data-notes="${$('<div>').text(entry.notes || '').html()}"
				tabindex="0"
				role="button"
			>
				<div class="etwa-entry-time">${entry.time_range_label || `${ui.formatTime12(entry.start_time)} – ${ui.formatTime12(entry.end_time)}`}</div>
				<div class="etwa-entry-meta">
					<strong>${entry.client_name || ''}</strong>
					<span>${entry.task || ''}</span>
				</div>
				<div class="etwa-entry-hours">${hoursHtml}</div>
				<div class="etwa-entry-actions">
					<button type="button" class="etwa-link etwa-edit-entry">${cfg.i18n?.edit || 'Edit'}</button>
					<button type="button" class="etwa-link etwa-delete-entry">${cfg.i18n?.delete || 'Delete'}</button>
				</div>
			</article>`;
	}

	function groupEntriesByDay(entries, workDates) {
		const map = {};
		workDates.forEach((d) => { map[d] = []; });
		entries.forEach((entry) => {
			if (map[entry.entry_date]) {
				map[entry.entry_date].push(entry);
			}
		});
		return map;
	}

	function sumDayHours(entries) {
		return entries.reduce((sum, e) => {
			if (String(e.client_id) === String(breakClientId)) return sum;
			return sum + Number(e.regular_hours || 0) + Number(e.overtime_hours || 0);
		}, 0);
	}

	function updateDashboard(data) {
		const ts = data.timesheet;
		const entries = data.entries || [];
		const workDates = data.work_dates || [];
		const byDay = groupEntriesByDay(entries, workDates);

		$('.etwa-kpi-card').eq(0).find('.etwa-kpi-value').text(formatHoursFull(Number(ts.total_regular_hours) + Number(ts.total_overtime_hours)));
		$('.etwa-kpi-card').eq(1).find('.etwa-status')
			.attr('class', `etwa-status etwa-status-${ts.status}`)
			.text(ts.status.charAt(0).toUpperCase() + ts.status.slice(1));

		workDates.forEach((date) => {
			const $card = $(`.etwa-day-card[data-date="${date}"]`);
			const status = $card.data('dayStatus') || 'working';
			const dayEntries = byDay[date] || [];

			if (status === 'working') {
				$card.find('.etwa-day-hours').text(`${formatHours(sumDayHours(dayEntries))}h`);
			}

			const $container = $card.find('.etwa-day-entries');
			if (!dayEntries.length) {
				if (status === 'holiday') return;
				if (status === 'day_off') return;
				$container.html(`<p class="etwa-day-empty">${cfg.i18n?.noEntries || 'No entries'}</p>`);
			} else {
				$container.html(dayEntries.map(renderEntryChip).join(''));
			}
		});

		bindEntryActions();
	}

	function refreshTimesheet() {
		const weekStart = $('#etwa-employee-app').data('weekStart');
		if (!weekStart) {
			window.location.reload();
			return Promise.resolve();
		}

		const path = isHrEdit()
			? `timesheets/user/${reviewUserId()}?date=${encodeURIComponent(weekStart)}`
			: `timesheets/me?date=${encodeURIComponent(weekStart)}`;

		return api(path).then((res) => {
			if (!res.success) throw new Error(res.message);
			updateDashboard(res.data);
		});
	}

	function bindEntryActions() {
		$('.etwa-entry-chip.is-clickable').off('click keydown').on('click keydown', function (e) {
			if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
			if ($(e.target).closest('.etwa-delete-entry, .etwa-edit-entry').length) return;
			e.preventDefault();
			const entry = entryFromChip($(this));
			openModal(cfg.i18n?.editEntry || 'Edit Time Entry', entry.date, entry);
		});

		$('.etwa-edit-entry').off('click').on('click', function (e) {
			e.stopPropagation();
			const entry = entryFromChip($(this).closest('.etwa-entry-chip'));
			openModal(cfg.i18n?.editEntry || 'Edit Time Entry', entry.date, entry);
		});

		$('.etwa-delete-entry').off('click').on('click', function (e) {
			e.stopPropagation();
			handleDelete($(this).closest('.etwa-entry-chip'));
		});
	}

	function handleDelete($chip) {
		const doDelete = (reason) => {
			if (isHrEdit() && !reason) return;
			const id = $chip.data('entry-id');
			const $btn = $chip.find('.etwa-delete-entry');
			ui.setButtonLoading($btn[0], true);

			const options = isHrEdit()
				? { method: 'DELETE', body: JSON.stringify({ edit_reason: reason }) }
				: { method: 'DELETE' };
			const path = isHrEdit() ? `hr/entries/${id}` : `entries/${id}`;

			api(path, options)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.entryDeleted || 'Entry deleted.');
					return refreshTimesheet();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		};

		ui.confirm(cfg.i18n?.confirmDelete, cfg.i18n?.deleteEntry || 'Delete entry').then((ok) => {
			if (!ok) return;
			if (isHrEdit()) {
				ui.promptReason(cfg.i18n?.deleteEntry || 'Delete entry').then(doDelete);
			} else {
				doDelete(true);
			}
		});
	}

	function loadVacationNotifications() {
		if (!cfg.canViewOwnVacation || isHrEdit()) return;
		fetch(cfg.root + 'vacations/notifications', {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce },
		})
			.then((r) => r.json())
			.then((res) => {
				if (!res.success || !(res.data?.items?.length)) return;
				const html = res.data.items
					.map(
						(n) =>
							`<div class="etwa-alert etwa-alert-info etwa-vacation-notice"><strong>${n.leave_type_name} ${cfg.i18n?.approved || 'Approved'}</strong><br />${n.start_date} – ${n.end_date} · ${n.days} ${cfg.i18n?.days || 'days'}<br />${cfg.i18n?.remainingBalance || 'Remaining balance'}: ${n.remaining}</div>`
					)
					.join('');
				$('#etwa-employee-app').prepend(`<div class="etwa-vacation-notifications">${html}</div>`);
				fetch(cfg.root + 'vacations/notifications', {
					method: 'PUT',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
					body: '{}',
				});
			})
			.catch(() => {});
	}

	function bindEmployeeDashboard() {
		if (!$('#etwa-employee-app').length) return;

		breakClientId = String($('#etwa-employee-app').data('breakClientId') || '');

		loadVacationNotifications();

		loadTimeSlots().then(detectBreakClientId);
		bindEntryActions();

		$('#etwa-client-id').on('change', toggleTaskRequired);
		$('#etwa-start-time, #etwa-end-time').on('change', updateDurationPreview);
		$('#etwa-start-time').on('change', autoAdvanceEndTime);

		$('.etwa-add-day-entry').on('click', function () {
			openModal(cfg.i18n?.addEntry || 'Add Time Entry', $(this).data('date'));
		});

		$('.etwa-modal-close').on('click', closeModal);
		$('#etwa-entry-modal').on('click', function (e) {
			if ($(e.target).is('#etwa-entry-modal')) closeModal();
		});

		$(document).on('keydown.etwaModal', (e) => {
			if (e.key === 'Escape' && !$('#etwa-entry-modal').prop('hidden')) {
				closeModal();
			}
		});

		$('#etwa-entry-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const entryId = data.entry_id || null;
			if (String(data.client_id) === String(breakClientId) && !data.task) {
				data.task = 'Break';
			}
			if (String(data.client_id) === String(allClientsClientId) && !String(data.notes || '').trim()) {
				ui.error(
					cfg.i18n?.allClientsNotesRequired ||
						'Please describe the work performed in detail in Notes because this entry applies to all clients.'
				);
				return;
			}

			const saveEntry = (reason) => {
				if (isHrEdit()) {
					if (!reason || String(reason).trim().length < 3) {
						ui.error(cfg.i18n?.reasonRequired || 'An audit reason is required (minimum 3 characters).');
						return;
					}
					data.edit_reason = reason;
				}

				const $submit = $(this).find('[type="submit"]');
				ui.setButtonLoading($submit[0], true, cfg.i18n?.saving);

				const path = isHrEdit()
					? (entryId ? `hr/entries/${entryId}` : 'hr/entries')
					: (entryId ? `entries/${entryId}` : 'entries');

				api(path, {
					method: entryId ? 'PUT' : 'POST',
					body: JSON.stringify(data),
				})
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						closeModal();
						ui.success(cfg.i18n?.entrySaved || 'Entry saved.');
						return refreshTimesheet();
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($submit[0], false));
			};

			if (isHrEdit()) {
				const reason = String(data.edit_reason || '').trim();
				saveEntry.call(this, reason);
			} else {
				saveEntry.call(this, true);
			}
		});

		$('#etwa-submit-timesheet').on('click', function () {
			ui.confirm(cfg.i18n?.confirmSubmit, cfg.i18n?.submitReport || 'Submit report').then((ok) => {
				if (!ok) return;
				const $btn = $(this);
				ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

				api(`timesheets/${$btn.data('timesheet-id')}/submit`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.submitted);
						setTimeout(() => window.location.reload(), 900);
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn[0], false));
			});
		});

		$('#etwa-copy-week').on('click', () => {
			ui.confirm(cfg.i18n?.confirmCopyWeek, cfg.i18n?.copyWeek || 'Copy previous week').then((ok) => {
				if (!ok) return;
				const $btn = $('#etwa-copy-week');
				const weekStart = $('#etwa-employee-app').data('weekStart');
				ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

				api('timesheets/copy-previous', {
					method: 'POST',
					body: JSON.stringify({ date: weekStart || null }),
				})
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.copiedWeek || 'Previous week copied.');
						return refreshTimesheet();
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn[0], false));
			});
		});

	}

	function bindPortalAndHistory() {
		$('#etwa-history-search').on(
			'input',
			debounce(function () {
				const q = $(this).val().trim().toLowerCase();
				$('.etwa-history-row, .etwa-history-card').each(function () {
					const match = !q || String($(this).data('search') || '').includes(q);
					$(this).toggle(match);
				});
			}, 300)
		);
	}

	function bindStandaloneThemeToggle() {
		if ($('#etwa-portal-root').length) return;

		$('#etwa-dark-toggle').on('click', function (e) {
			e.preventDefault();
			const next = !$('.etwa-app, .etwa-portal-layout').first().hasClass('etwa-dark');
			$('.etwa-app, .etwa-portal-layout').toggleClass('etwa-dark', next);
			$(this).attr('aria-pressed', next ? 'true' : 'false');
			if (!cfg.ajaxUrl || !cfg.ajaxNonce) return;
			$.post(cfg.ajaxUrl, { action: 'etwa_toggle_dark_mode', nonce: cfg.ajaxNonce }).fail(() => {
				$('.etwa-app, .etwa-portal-layout').toggleClass('etwa-dark', !next);
				$('#etwa-dark-toggle').attr('aria-pressed', !next ? 'true' : 'false');
			});
		});
	}

	$(function () {
		bindEmployeeDashboard();
		bindPortalAndHistory();
		bindStandaloneThemeToggle();
	});
})(jQuery, window.wp);
