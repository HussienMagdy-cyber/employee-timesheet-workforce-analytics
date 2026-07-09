(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	if (!cfg.canManageHolidays) return;

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

	let page = 1;
	let yearFilter = '';

	function renderRows(items) {
		if (!items.length) {
			return `<tr><td colspan="4" class="etwa-empty-cell">${cfg.i18n?.noHolidays || 'No holidays found.'}</td></tr>`;
		}
		return items
			.map(
				(h) => `
			<tr data-id="${h.id}">
				<td><strong>${h.name}</strong></td>
				<td>${h.holiday_date}</td>
				<td>${h.is_recurring ? (cfg.i18n?.yes || 'Yes') : (cfg.i18n?.no || 'No')}</td>
				<td><button type="button" class="etwa-link etwa-delete-holiday">${cfg.i18n?.delete || 'Delete'}</button></td>
			</tr>`
			)
			.join('');
	}

	function renderPagination(total, perPage) {
		const pages = Math.max(1, Math.ceil(total / perPage));
		if (pages <= 1) return '';
		let html = '';
		for (let i = 1; i <= pages; i += 1) {
			html += `<button type="button" class="etwa-btn etwa-btn-secondary${i === page ? ' is-active' : ''}" data-page="${i}">${i}</button>`;
		}
		return html;
	}

	function loadHolidays() {
		const params = new URLSearchParams({ page: String(page), per_page: '25' });
		if (yearFilter) params.set('year', yearFilter);

		$('#etwa-holidays-body').html(
			'<tr class="etwa-skeleton-row"><td colspan="4"><div class="etwa-skeleton" style="height:40px"></div></td></tr>'
		);

		api(`holidays?${params.toString()}`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				const data = res.data || {};
				$('#etwa-holidays-body').html(renderRows(data.items || []));
				$('#etwa-holidays-pagination').html(renderPagination(data.total || 0, data.per_page || 25));
			})
			.catch((err) => {
				$('#etwa-holidays-body').html(`<tr><td colspan="4">${err.message}</td></tr>`);
			});
	}

	$(function () {
		if (!$('#etwa-holidays-app').length) return;

		loadHolidays();

		$('#etwa-holiday-year').on('change', function () {
			yearFilter = $(this).val();
			page = 1;
			loadHolidays();
		});

		$('#etwa-holidays-pagination').on('click', 'button[data-page]', function () {
			page = parseInt($(this).data('page'), 10) || 1;
			loadHolidays();
		});

		$('#etwa-holiday-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api('holidays', {
				method: 'POST',
				body: JSON.stringify({
					name: data.name,
					holiday_date: data.holiday_date,
					is_recurring: !!data.is_recurring,
				}),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					ui.success(cfg.i18n?.holidaySaved || 'Holiday saved.');
					loadHolidays();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-calendar-form').on('submit', function (e) {
			e.preventDefault();
			const weekdays = {};
			$(this)
				.find('select[name^="weekdays"]')
				.each(function () {
					const match = this.name.match(/\[(\d+)\]/);
					if (match) weekdays[match[1]] = $(this).val();
				});
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api('calendar/weekdays', { method: 'PUT', body: JSON.stringify({ weekdays }) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.calendarSaved || 'Calendar saved.');
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-holidays-body').on('click', '.etwa-delete-holiday', function () {
			const id = $(this).closest('tr').data('id');
			ui.confirm(cfg.i18n?.confirmDeleteHoliday || 'Delete this holiday?', cfg.i18n?.delete || 'Delete').then((ok) => {
				if (!ok) return;
				api(`holidays/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.holidayDeleted || 'Holiday deleted.');
						loadHolidays();
					})
					.catch((err) => ui.error(err.message));
			});
		});
	});
})(jQuery);
