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

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	let timeSlots = [];
	let timeSlotOptions = [];
	let defaultStartTime = '';
	let defaultEndTime = '';
	let breakClientId = null;
	let allClientsClientId = null;
	let timeSlotsReady = Promise.resolve();

	const SELECTABLE_ENTRY_TYPES = ['overtime', 'compensation'];

	function loadTimeSlots() {
		const path = isHrEdit() ? 'hr/time-slots' : 'time-slots';
		return api(path).then((res) => {
			if (res.success) {
				timeSlots = res.data.slots || [];
				timeSlotOptions = res.data.slot_options || timeSlots.map((slot) => ({ value: slot, label: ui.formatTime12(slot) }));
				defaultStartTime = res.data.default_start || timeSlots[0] || '';
				defaultEndTime = res.data.default_end || timeSlots[1] || '';
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

	const WORK_DAY_END = '18:00:00';

	function getWorkDayEnd() {
		return cfg.workDayEnd || WORK_DAY_END;
	}

	function normalizeSlotTime(time) {
		if (!time) return '';
		const parts = String(time).trim().split(':');
		if (parts.length < 2) return String(time);
		return `${parts[0].padStart(2, '0')}:${parts[1].padStart(2, '0')}`;
	}

	function normalizeEntryDate(date) {
		if (!date) return '';
		if (date instanceof Date && !Number.isNaN(date.getTime())) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		}
		const match = String(date).trim().match(/^(\d{4}-\d{2}-\d{2})/);
		return match ? match[1] : String(date).trim();
	}

	function setTimeSelect(selector, time) {
		const normalized = normalizeSlotTime(time);
		const $el = $(selector);
		if (!normalized) return;

		if ($el.find(`option[value="${normalized}"]`).length) {
			$el.val(normalized);
			return;
		}

		if (timeSlots.length) {
			const match = timeSlots.find((slot) => slot >= normalized);
			if (match) {
				$el.val(match);
				return;
			}
		}

		$el.val(normalized);
	}

	function isAfterWorkHours(startTime) {
		if (!startTime) return false;
		const normalized = startTime.length === 5 ? `${startTime}:00` : startTime;
		return normalized >= getWorkDayEnd();
	}

	function toggleEntryTypeField() {
		const afterHours = isAfterWorkHours($('#etwa-start-time').val());
		const $wrap = $('#etwa-entry-type-wrap');
		const $field = $('#etwa-entry-type');
		$wrap.prop('hidden', !afterHours);
		$field.prop('required', afterHours);
		if (!afterHours) {
			$field.val('');
		}
	}

	function resolveEntryTypeForForm(entry) {
		if (!entry) return '';
		let type = String(entry.entryType || '').toLowerCase();
		if (SELECTABLE_ENTRY_TYPES.includes(type)) {
			return type;
		}
		if (Number(entry.overtimeHours || 0) > 0) {
			return 'overtime';
		}
		if (isAfterWorkHours(entry.start) && Number(entry.durationMinutes || 0) > 0) {
			return 'overtime';
		}
		return '';
	}

	function applyEntryTypeField(entry) {
		toggleEntryTypeField();
		if (!isAfterWorkHours($('#etwa-start-time').val())) {
			return;
		}
		const type = resolveEntryTypeForForm(entry);
		if (SELECTABLE_ENTRY_TYPES.includes(type)) {
			$('#etwa-entry-type').val(type);
		} else {
			$('#etwa-entry-type').val('');
		}
	}

	function getLastEntryEndTimeForDay(date) {
		const day = normalizeEntryDate(date);
		const $chips = $(`.etwa-day-card[data-date="${day}"] .etwa-entry-chip`);
		if (!$chips.length) return '';

		let latest = '';
		$chips.each(function () {
			const end = normalizeSlotTime($(this).attr('data-end-time') || '');
			if (end && (!latest || end > latest)) {
				latest = end;
			}
		});
		return latest;
	}

	function setEndTimeAfterStart() {
		const start = $('#etwa-start-time').val();
		if (!start || !timeSlots.length) return;

		const startIdx = timeSlots.indexOf(start);
		if (startIdx >= 0 && startIdx < timeSlots.length - 1) {
			$('#etwa-end-time').val(timeSlots[startIdx + 1]);
			return;
		}

		$('#etwa-end-time').val('');
	}

	function fillEntryForm(date, entry) {
		date = normalizeEntryDate(entry?.date || date);
		$('#etwa-entry-date').val(date || '');

		if (entry) {
			$('#etwa-entry-id').val(entry.id);
			setTimeSelect('#etwa-start-time', entry.start);
			setTimeSelect('#etwa-end-time', entry.end);
			if (!entry.clientId && entry.prospectId) {
				ensureProspectOption(entry.prospectId, entry.prospectName || '');
				$('#etwa-client-id').val(`pr:${entry.prospectId}`);
				loadProjectsForClient(`pr:${entry.prospectId}`, null);
			} else if (!entry.clientId && entry.projectId) {
				getStandaloneProjectTree()
					.then((tree) => {
						const node = findStandaloneNode(tree, entry.projectId);
						if (node && (node.parent.children || []).length) {
							$('#etwa-client-id').val(`p:${node.parent.id}`);
							fillStandaloneProjectSelect(tree, node.parent.id, entry.projectId);
						} else {
							$('#etwa-client-id').val(`p:${entry.projectId}`);
							fillStandaloneProjectSelect(tree, entry.projectId, entry.projectId);
						}
					})
					.catch(() => {
						$('#etwa-client-id').val(`p:${entry.projectId}`);
						loadProjectsForClient(`p:${entry.projectId}`, entry.projectId);
					});
			} else {
				$('#etwa-client-id').val(entry.clientId);
				loadProjectsForClient(entry.clientId, entry.projectId).then(() => {
					if (entry.projectId) {
						$('#etwa-project-id').val(String(entry.projectId));
					}
				});
			}
			$('#etwa-task').val(entry.task);
			$('#etwa-notes').val(entry.notes);
			toggleEntryTypeField();
			applyEntryTypeField(entry);
		} else {
			$('#etwa-entry-id').val('');
			$('#etwa-task').val('');
			$('#etwa-notes').val('');
			if (isHrEdit()) {
				$('#etwa-edit-reason').val('');
			}
			const lastEnd = getLastEntryEndTimeForDay(date);
			if (lastEnd) {
				setTimeSelect('#etwa-start-time', lastEnd);
				setEndTimeAfterStart();
			} else if (defaultStartTime && defaultEndTime) {
				$('#etwa-start-time').val(defaultStartTime);
				$('#etwa-end-time').val(defaultEndTime);
			} else if (timeSlots.length > 1) {
				$('#etwa-start-time').val(timeSlots[0]);
				$('#etwa-end-time').val(timeSlots[1]);
			}
			toggleEntryTypeField();
		}

		toggleTaskRequired();
		updateDurationPreview();
	}

	function openModal(title, date, entry) {
		date = normalizeEntryDate(entry?.date || date);
		if (isEmployeeLockedDay(date)) {
			ui.error(cfg.i18n?.vacationLocked || 'This day is locked and cannot be edited.');
			return;
		}

		$('#etwa-modal-title').text(title);
		$('#etwa-entry-modal').prop('hidden', false).attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');

		timeSlotsReady.then(() => {
			fillEntryForm(date, entry);
			setTimeout(() => $('#etwa-client-id').trigger('focus'), 50);
		});
	}

	function entryTypeChipHtml(entry) {
		const type = entry.entry_type || '';
		if (!type || type === 'regular') {
			if (Number(entry.overtime_hours || 0) > 0) {
				return ` <span class="etwa-ot-tag">${cfg.i18n?.overtimeShort || 'OT'}</span>`;
			}
			return '';
		}
		if (type === 'overtime') {
			return ` <span class="etwa-ot-tag">${cfg.i18n?.overtimeShort || 'OT'}</span>`;
		}
		if (type === 'compensation') {
			return ` <span class="etwa-comp-tag">${cfg.i18n?.compensationShort || 'Comp'}</span>`;
		}
		return entry.entry_type_label
			? ` <span class="etwa-comp-tag">${entry.entry_type_label}</span>`
			: '';
	}

	function entryDisplayHours(entry) {
		if (String(entry.client_id) === String(breakClientId)) {
			return Number(entry.duration_minutes || 0) / 60;
		}
		return Number(entry.regular_hours || 0) + Number(entry.overtime_hours || 0);
	}

	function dayStatusForDate(date) {
		const $card = $(`.etwa-day-card[data-date="${date}"]`);
		return $card.data('dayStatus') || 'working';
	}

	function isEmployeeLockedDay(date) {
		if (isHrEdit()) return false;
		return dayStatusForDate(date) !== 'working';
	}

	function canShowEntryActions(date) {
		if (isHrEdit()) return true;
		return dayStatusForDate(date) === 'working';
	}

	function detectBreakClientId() {
		$('#etwa-client-id optgroup option, #etwa-client-id > option').each(function () {
			const text = $(this).text().trim().toLowerCase();
			if (text === 'break') {
				breakClientId = $(this).val();
			}
			if (text === 'moderation' || text === 'all clients') {
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

	function closeModal() {
		$('#etwa-entry-modal').prop('hidden', true).attr('aria-hidden', 'true');
		$('body').removeClass('etwa-modal-open');
		$('#etwa-entry-form')[0].reset();
		$('#etwa-entry-id').val('');
		$('#etwa-duration-preview').text('');
		$('#etwa-entry-type-wrap').prop('hidden', true);
		$('#etwa-entry-type').prop('required', false).val('');
	}

	function isStandaloneProjectValue(val) {
		const s = String(val || '');
		return s.startsWith('p:') && !s.startsWith('pr:');
	}

	function isProspectValue(val) {
		return String(val || '').startsWith('pr:');
	}

	function prospectIdFromValue(val) {
		return String(val || '').slice(3);
	}

	function ensureProspectOption(id, name) {
		const value = `pr:${id}`;
		if ($(`#etwa-client-id option[value="${value}"]`).length) {
			return;
		}
		let $group = $('#etwa-client-id optgroup').filter(function () {
			return $(this).attr('label') === (cfg.i18n?.prospectsGroup || 'Prospects');
		});
		if (!$group.length) {
			$group = $('<optgroup>').attr('label', cfg.i18n?.prospectsGroup || 'Prospects');
			$('#etwa-client-id').append($group);
		}
		$group.append($('<option>').attr('value', value).prop('disabled', true).text(name || `Prospect #${id}`));
	}

	function standaloneProjectId(val) {
		return String(val || '').slice(2);
	}

	function findStandaloneNode(tree, id) {
		const numId = parseInt(id, 10);
		if (!numId) {
			return null;
		}
		for (const parent of tree || []) {
			if (parseInt(parent.id, 10) === numId) {
				return { kind: 'parent', parent };
			}
			const child = (parent.children || []).find((c) => parseInt(c.id, 10) === numId);
			if (child) {
				return { kind: 'child', parent, child };
			}
		}
		return null;
	}

	let standaloneProjectTreeCache = null;

	function readEmbeddedStandaloneTree() {
		const $json = $('#etwa-standalone-projects-json');
		if (!$json.length) {
			return null;
		}
		try {
			return JSON.parse($json.text() || '[]');
		} catch (err) {
			return null;
		}
	}

	function getStandaloneProjectTree() {
		if (standaloneProjectTreeCache) {
			return Promise.resolve(standaloneProjectTreeCache);
		}
		const embedded = readEmbeddedStandaloneTree();
		if (embedded) {
			standaloneProjectTreeCache = embedded;
			return Promise.resolve(embedded);
		}
		return api('projects').then((res) => {
			if (!res.success) throw new Error(res.message);
			standaloneProjectTreeCache = res.data || [];
			return standaloneProjectTreeCache;
		});
	}

	function fillStandaloneProjectSelect(tree, workspaceId, selectedProjectId) {
		const $wrap = $('#etwa-project-wrap');
		const $select = $('#etwa-project-id');
		const node = findStandaloneNode(tree, workspaceId);
		if (!node) {
			$wrap.prop('hidden', true);
			return;
		}
		const children = (node.parent.children || []).filter((p) => p.status === 'active');
		if (!children.length) {
			$wrap.prop('hidden', true);
			$select.prop('required', false).html('<option value=""></option>');
			return;
		}
		const directLabel = cfg.i18n?.directParentProject || 'Direct on parent project';
		let html = `<option value="">${directLabel}</option>`;
		children.forEach((p) => {
			html += `<option value="${p.id}">${p.name}</option>`;
		});
		$select.html(html).prop('required', false);
		$wrap.prop('hidden', false);
		const sel = selectedProjectId ? String(selectedProjectId) : '';
		if (sel && children.some((p) => String(p.id) === sel)) {
			$select.val(sel);
		} else if (node.kind === 'child') {
			$select.val(String(node.child.id));
		}
	}

	function loadProjectsForClient(clientId, selectedProjectId) {
		const $wrap = $('#etwa-project-wrap');
		const $select = $('#etwa-project-id');
		if (
			!clientId ||
			String(clientId) === String(breakClientId) ||
			String(clientId) === String(allClientsClientId) ||
			isProspectValue(clientId)
		) {
			$wrap.attr('hidden', 'hidden');
			$select.prop('required', false).html(
				`<option value="">${cfg.i18n?.directClientWork || 'Social Media'}</option>`
			);
			return Promise.resolve();
		}

		if (isStandaloneProjectValue(clientId)) {
			const workspaceId = standaloneProjectId(clientId);
			return getStandaloneProjectTree()
				.then((tree) => {
					fillStandaloneProjectSelect(tree, workspaceId, selectedProjectId);
				})
				.catch(() => {
					$wrap.prop('hidden', true);
					$select.prop('required', false);
				});
		}

		return api(`clients/${clientId}/projects`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const projects = res.data || [];
				let html = `<option value="">${cfg.i18n?.directClientWork || 'Social Media'}</option>`;
				projects.forEach((p) => {
					html += `<option value="${p.id}">${p.name}</option>`;
				});
				$select.html(html).prop('required', false);
				if (projects.length === 0) {
					$wrap.attr('hidden', 'hidden');
				} else {
					$wrap.removeAttr('hidden');
				}
				if (selectedProjectId) {
					$select.val(String(selectedProjectId));
				}
			})
			.catch(() => {
				$wrap.removeAttr('hidden');
				$select.prop('required', false);
			});
	}

	function onClientChange() {
		toggleTaskRequired();
		loadProjectsForClient($('#etwa-client-id').val(), null);
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
			id: $chip.attr('data-entry-id'),
			date: $chip.attr('data-entry-date'),
			start: normalizeSlotTime($chip.attr('data-start-time') || ''),
			end: normalizeSlotTime($chip.attr('data-end-time') || ''),
			clientId: $chip.attr('data-client-id'),
			projectId: $chip.attr('data-project-id') || '',
			prospectId: $chip.attr('data-prospect-id') || '',
			prospectName: $chip.find('.etwa-entry-meta strong').first().text() || '',
			task: $chip.attr('data-task') || '',
			notes: $chip.attr('data-notes') || '',
			entryType: $chip.attr('data-entry-type') || '',
			overtimeHours: $chip.attr('data-overtime-hours') || '0',
			durationMinutes: $chip.attr('data-duration-minutes') || '0',
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
		const isAllClients = String(entry.client_id) === String(allClientsClientId);
		const total = entryDisplayHours(entry);
		const hoursHtml = isBreak
			? `<span class="etwa-break-tag">${formatHours(total)}h ${cfg.i18n?.breakLabel || 'Break'}</span>`
			: `${formatHours(total)}h${entryTypeChipHtml(entry)}`;
		const showActions = canShowEntryActions(entry.entry_date);
		const actionsHtml = showActions
			? `<div class="etwa-entry-actions">
					<button type="button" class="etwa-link etwa-edit-entry">${cfg.i18n?.edit || 'Edit'}</button>
					<button type="button" class="etwa-link etwa-delete-entry">${cfg.i18n?.delete || 'Delete'}</button>
				</div>`
			: '';

		return `
			<article
				class="etwa-entry-chip${isBreak ? ' is-break' : ''}${showActions ? ' is-clickable' : ''}"
				data-entry-id="${entry.id}"
				data-entry-date="${entry.entry_date}"
				data-start-time="${entry.start_time}"
				data-end-time="${entry.end_time}"
				data-client-id="${entry.client_id || ''}"
				data-project-id="${entry.project_id || ''}"
				data-prospect-id="${entry.prospect_id || ''}"
				data-task="${$('<div>').text(entry.task || '').html()}"
				data-notes="${$('<div>').text(entry.notes || '').html()}"
				data-entry-type="${entry.entry_type || 'regular'}"
				data-overtime-hours="${Number(entry.overtime_hours || 0)}"
				data-duration-minutes="${Number(entry.duration_minutes || 0)}"
				${showActions ? 'tabindex="0" role="button"' : ''}
			>
				<div class="etwa-entry-time">${entry.time_range_label || `${ui.formatTime12(entry.start_time)} – ${ui.formatTime12(entry.end_time)}`}</div>
				<div class="etwa-entry-meta">
					<strong>${entry.client_name || entry.prospect_name || entry.project_name || ''}</strong>
					${entry.project_name && entry.client_id ? `<span class="etwa-entry-project">${entry.project_name}</span>` : !isBreak && !isAllClients && entry.client_id && !entry.project_id ? `<span class="etwa-entry-project">${cfg.i18n?.directClientWork || 'Social Media'}</span>` : ''}
					<span>${entry.task || ''}</span>
				</div>
				<div class="etwa-entry-hours">${hoursHtml}</div>
				${actionsHtml}
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
			return sum + entryDisplayHours(e);
		}, 0);
	}

	function applyDayMeta(dayMeta, workDates) {
		if (!dayMeta) return;

		workDates.forEach((date) => {
			const meta = dayMeta[date] || { status: 'working' };
			const status = meta.status || 'working';
			const canLog = status === 'working';
			const $card = $(`.etwa-day-card[data-date="${date}"]`);
			if (!$card.length) return;

			$card
				.attr('data-day-status', status)
				.data('dayStatus', status)
				.removeClass('etwa-day-working etwa-day-holiday etwa-day-day_off etwa-day-vacation is-non-working')
				.addClass(`etwa-day-${status}`)
				.toggleClass('is-non-working', !canLog);

			if (!canLog) {
				$card.find('.etwa-add-day-entry').remove();
			}
		});
	}

	function updateDashboard(data) {
		const ts = data.timesheet;
		const entries = data.entries || [];
		const workDates = data.work_dates || [];
		const byDay = groupEntriesByDay(entries, workDates);

		applyDayMeta(data.day_meta, workDates);

		$('.etwa-kpi-card').eq(0).find('.etwa-kpi-value').text(formatHoursFull(Number(ts.total_regular_hours) + Number(ts.total_overtime_hours)));
		$('.etwa-kpi-card').eq(1).find('.etwa-status')
			.attr('class', `etwa-status etwa-status-${ts.status}`)
			.find('.etwa-status-label')
			.text(ts.status.charAt(0).toUpperCase() + ts.status.slice(1));

		const $draftSync = $('#etwa-draft-sync-status');
		if ($draftSync.length) {
			const syncState = ts.draft_sync_state || (ts.has_unsaved_changes ? 'unsaved' : 'saved');
			const syncLabel = ts.draft_sync_label
				|| (syncState === 'saved'
					? (cfg.i18n?.draftSaved || 'Draft Saved')
					: syncState === 'never_saved'
						? (cfg.i18n?.notSavedForReview || 'Not saved for review yet')
						: (cfg.i18n?.unsavedChanges || 'Unsaved changes — not visible to HR yet'));
			$draftSync
				.attr('class', `etwa-draft-sync-status is-${syncState}`)
				.text(syncLabel);

			const $draftNotice = $('#etwa-draft-sync-notice');
			if ($draftNotice.length) {
				if (syncState === 'saved' || syncState === 'submitted') {
					$draftNotice.attr('hidden', 'hidden');
				} else {
					$draftNotice.removeAttr('hidden');
				}
			}
		}

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
				if (status === 'vacation') return;
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
		const entryDate = $chip.data('entryDate');
		if (isEmployeeLockedDay(entryDate)) {
			ui.error(cfg.i18n?.vacationLocked || 'This day is locked and cannot be edited.');
			return;
		}

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
		api('vacations/notifications')
			.then((res) => {
				if (!res.success || !(res.data?.items?.length)) return;
				const html = res.data.items
					.map(
						(n) =>
							`<div class="etwa-alert etwa-alert-info etwa-vacation-notice"><strong>${n.leave_type_name} ${cfg.i18n?.approved || 'Approved'}</strong><br />${n.start_date} – ${n.end_date} · ${n.days} ${cfg.i18n?.days || 'days'}<br />${cfg.i18n?.remainingBalance || 'Remaining balance'}: ${n.remaining}</div>`
					)
					.join('');
				$('#etwa-employee-app').prepend(`<div class="etwa-vacation-notifications">${html}</div>`);
				return api('vacations/notifications', { method: 'PUT', body: '{}' });
			})
			.catch(() => {});
	}

	function bindEmployeeDashboard() {
		if (!$('#etwa-employee-app').length) return;

		breakClientId = String($('#etwa-employee-app').data('breakClientId') || '');

		loadVacationNotifications();

		timeSlotsReady = loadTimeSlots().then(detectBreakClientId);
		bindEntryActions();

		$('#etwa-client-id').on('change', onClientChange);
		$('#etwa-start-time, #etwa-end-time').on('change', updateDurationPreview);
		$('#etwa-start-time').on('change', autoAdvanceEndTime);
		$('#etwa-start-time').on('change', toggleEntryTypeField);

		$('.etwa-add-day-entry').on('click', function () {
			openModal(cfg.i18n?.addEntry || 'Add Time Entry', $(this).attr('data-date'));
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
			if (isEmployeeLockedDay(data.entry_date)) {
				ui.error(cfg.i18n?.vacationLocked || 'This day is locked and cannot be edited.');
				return;
			}
			if (String(data.client_id) === String(breakClientId) && !data.task) {
				data.task = 'Break';
			}
			if (String(data.client_id) === String(allClientsClientId) && !String(data.notes || '').trim()) {
				ui.error(
					cfg.i18n?.allClientsNotesRequired ||
						'Please describe the work performed in detail in Notes because this entry applies to Moderation.'
				);
				return;
			}
			if (isAfterWorkHours(data.start_time) && !SELECTABLE_ENTRY_TYPES.includes(String(data.entry_type || ''))) {
				ui.error(cfg.i18n?.entryTypeRequired || 'Please select an entry type for entries after 6:00 PM.');
				return;
			}
			if (!isAfterWorkHours(data.start_time)) {
				delete data.entry_type;
			}
			if (isProspectValue(data.client_id)) {
				data.prospect_id = prospectIdFromValue(data.client_id);
				delete data.client_id;
				delete data.project_id;
			} else if (isStandaloneProjectValue(data.client_id)) {
				const fallback = standaloneProjectId(data.client_id);
				data.project_id = data.project_id || fallback;
				delete data.client_id;
			}
			if (!data.project_id) {
				delete data.project_id;
			}
			if (!data.prospect_id) {
				delete data.prospect_id;
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

		$('#etwa-save-draft').on('click', function () {
			const $btn = $(this);
			const timesheetId = $('#etwa-submit-timesheet').data('timesheet-id');
			if (!timesheetId) return;

			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api(`timesheets/${timesheetId}/save-draft`, { method: 'POST' })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.draftSavedSuccess || 'Draft saved for review.');
					return refreshTimesheet();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
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
