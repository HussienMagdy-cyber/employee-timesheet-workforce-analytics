(function () {
	'use strict';

	const i18n = () => (window.etwaApp && window.etwaApp.i18n) || {};

	function ensureToastContainer() {
		let el = document.getElementById('etwa-toast-stack');
		if (!el) {
			el = document.createElement('div');
			el.id = 'etwa-toast-stack';
			el.className = 'etwa-toast-stack';
			el.setAttribute('aria-live', 'polite');
			el.setAttribute('aria-atomic', 'true');
			document.body.appendChild(el);
		}
		return el;
	}

	function toast(message, type = 'info', duration = 4200) {
		const stack = ensureToastContainer();
		const item = document.createElement('div');
		item.className = `etwa-toast etwa-toast-${type}`;
		item.setAttribute('role', 'status');
		item.innerHTML = `<span class="etwa-toast-text"></span><button type="button" class="etwa-toast-close" aria-label="Close">&times;</button>`;
		item.querySelector('.etwa-toast-text').textContent = message;
		stack.appendChild(item);

		requestAnimationFrame(() => item.classList.add('is-visible'));

		const remove = () => {
			item.classList.remove('is-visible');
			setTimeout(() => item.remove(), 220);
		};

		item.querySelector('.etwa-toast-close').addEventListener('click', remove);
		if (duration > 0) {
			setTimeout(remove, duration);
		}
		return remove;
	}

	function confirmDialog(message, title) {
		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.id = 'etwa-confirm-overlay';
			overlay.className = 'etwa-confirm-overlay etwa-app';
			overlay.innerHTML = `
				<div class="etwa-confirm-dialog" role="alertdialog" aria-modal="true">
					<h3 class="etwa-confirm-title"></h3>
					<p class="etwa-confirm-message"></p>
					<div class="etwa-confirm-actions">
						<button type="button" class="etwa-btn etwa-btn-secondary etwa-confirm-cancel"></button>
						<button type="button" class="etwa-btn etwa-btn-primary etwa-confirm-ok"></button>
					</div>
				</div>`;

			const strings = i18n();
			overlay.querySelector('.etwa-confirm-title').textContent = title || strings.confirmTitle || 'Confirm';
			overlay.querySelector('.etwa-confirm-message').textContent = message;
			overlay.querySelector('.etwa-confirm-cancel').textContent = strings.cancel || 'Cancel';
			overlay.querySelector('.etwa-confirm-ok').textContent = strings.confirm || 'Confirm';

			const close = (result) => {
				overlay.classList.remove('is-visible');
				setTimeout(() => overlay.remove(), 180);
				resolve(result);
			};

			const portalRoot = document.getElementById('etwa-portal-root');
			if (portalRoot && portalRoot.classList.contains('etwa-dark')) {
				overlay.classList.add('etwa-dark');
			}

			overlay.querySelector('.etwa-confirm-cancel').addEventListener('click', () => close(false));
			overlay.querySelector('.etwa-confirm-ok').addEventListener('click', () => close(true));
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) close(false);
			});

			document.body.appendChild(overlay);
			requestAnimationFrame(() => overlay.classList.add('is-visible'));
			overlay.querySelector('.etwa-confirm-ok').focus();
		});
	}

	function promptReason(title, options = {}) {
		const optional = !!options.optional;
		const inline = document.getElementById('etwa-edit-reason');
		if (inline) {
			const reason = String(inline.value || '').trim();
			if (reason.length < 3) {
				toast(i18n().reasonRequired || 'An audit reason is required (minimum 3 characters).', 'error', 6000);
				return Promise.resolve(null);
			}
			return Promise.resolve(reason);
		}

		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.id = 'etwa-confirm-overlay';
			overlay.className = 'etwa-confirm-overlay etwa-app';
			overlay.innerHTML = `
				<div class="etwa-confirm-dialog etwa-hr-reason-dialog" role="alertdialog" aria-modal="true">
					<h3 class="etwa-confirm-title"></h3>
					<label class="etwa-hr-reason-field">
						<span class="etwa-hr-reason-label"></span>
						<textarea class="etwa-hr-reason-input" rows="3"${optional ? '' : ' minlength="3" required'}></textarea>
					</label>
					<div class="etwa-confirm-actions">
						<button type="button" class="etwa-btn etwa-btn-secondary etwa-reason-cancel"></button>
						<button type="button" class="etwa-btn etwa-btn-primary etwa-reason-ok"></button>
					</div>
				</div>`;

			const strings = i18n();
			overlay.querySelector('.etwa-confirm-title').textContent = title || strings.auditReasonTitle || 'Audit reason required';
			overlay.querySelector('.etwa-hr-reason-label').textContent =
				optional
					? strings.rejectReasonOptional || 'Rejection reason (optional)'
					: strings.auditReasonLabel || 'Why are you making this change?';
			overlay.querySelector('.etwa-reason-cancel').textContent = strings.cancel || 'Cancel';
			overlay.querySelector('.etwa-reason-ok').textContent = optional
				? strings.reject || 'Reject'
				: strings.confirm || 'Confirm';

			const close = (result) => {
				overlay.classList.remove('is-visible');
				setTimeout(() => overlay.remove(), 180);
				resolve(result);
			};

			const portalRoot = document.getElementById('etwa-portal-root');
			if (portalRoot && portalRoot.classList.contains('etwa-dark')) {
				overlay.classList.add('etwa-dark');
			}

			overlay.querySelector('.etwa-reason-cancel').addEventListener('click', () => close(null));
			overlay.querySelector('.etwa-reason-ok').addEventListener('click', () => {
				const reason = overlay.querySelector('.etwa-hr-reason-input').value.trim();
				if (!optional && reason.length < 3) {
					toast(strings.reasonRequired || 'An audit reason is required (minimum 3 characters).', 'error', 6000);
					return;
				}
				close(reason);
			});
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) close(null);
			});

			document.body.appendChild(overlay);
			requestAnimationFrame(() => overlay.classList.add('is-visible'));
			overlay.querySelector('.etwa-hr-reason-input').focus();
		});
	}

	function promptDaysToDeduct(options = {}) {
		const {
			startDate = '',
			endDate = '',
			api = null,
			defaultDays = null,
			title = null,
			help = null,
			label = null,
			skipDaysBetween = false,
		} = options;
		const strings = i18n();

		return new Promise((resolve) => {
			const overlay = document.createElement('div');
			overlay.id = 'etwa-confirm-overlay';
			overlay.className = 'etwa-confirm-overlay etwa-app';
			overlay.innerHTML = `
				<div class="etwa-confirm-dialog etwa-hr-reason-dialog" role="alertdialog" aria-modal="true">
					<h3 class="etwa-confirm-title"></h3>
					<p class="etwa-confirm-message"></p>
					<label class="etwa-hr-reason-field">
						<span class="etwa-hr-reason-label"></span>
						<input type="number" class="etwa-input etwa-days-deduct-input" min="0.25" step="0.25" required />
					</label>
					<div class="etwa-confirm-actions">
						<button type="button" class="etwa-btn etwa-btn-secondary etwa-days-cancel"></button>
						<button type="button" class="etwa-btn etwa-btn-primary etwa-days-ok"></button>
					</div>
				</div>`;

			overlay.querySelector('.etwa-confirm-title').textContent =
				title || strings.daysToDeductTitle || 'Days to deduct';
			overlay.querySelector('.etwa-confirm-message').textContent =
				help ||
				(startDate && endDate
					? `${strings.requestDates || 'Request dates'}: ${startDate} – ${endDate}`
					: strings.daysToDeductHelp || 'Enter how many days to deduct from the employee balance.');
			overlay.querySelector('.etwa-hr-reason-label').textContent =
				label || strings.daysToDeduct || 'Days to deduct';
			overlay.querySelector('.etwa-days-cancel').textContent = strings.cancel || 'Cancel';
			overlay.querySelector('.etwa-days-ok').textContent = strings.approve || 'Approve';

			const input = overlay.querySelector('.etwa-days-deduct-input');
			if (defaultDays != null && defaultDays > 0) {
				input.value = String(defaultDays);
			}
			const close = (result) => {
				overlay.classList.remove('is-visible');
				setTimeout(() => overlay.remove(), 180);
				resolve(result);
			};

			const portalRoot = document.getElementById('etwa-portal-root');
			if (portalRoot && portalRoot.classList.contains('etwa-dark')) {
				overlay.classList.add('etwa-dark');
			}

			overlay.querySelector('.etwa-days-cancel').addEventListener('click', () => close(null));
			overlay.querySelector('.etwa-days-ok').addEventListener('click', () => {
				const days = parseFloat(input.value);
				if (!days || days <= 0) {
					toast(strings.invalidDays || 'Enter a valid number of days.', 'error', 6000);
					return;
				}
				close(days);
			});
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) close(null);
			});

			document.body.appendChild(overlay);
			requestAnimationFrame(() => overlay.classList.add('is-visible'));

			if (api && startDate && endDate && !skipDaysBetween) {
				api(`vacations/days-between?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`)
					.then((res) => {
						if (res.success && res.data && res.data.days) {
							input.value = res.data.days;
						}
						input.focus();
						input.select();
					})
					.catch(() => input.focus());
			} else {
				input.focus();
			}
		});
	}

	function setButtonLoading(button, loading, loadingText) {
		if (!button) return;
		const $btn = window.jQuery ? window.jQuery(button) : null;
		if (!$btn || !$btn.length) return;

		if (loading) {
			if (!$btn.data('etwa-label')) {
				$btn.data('etwa-label', $btn.text());
			}
			$btn.prop('disabled', true).addClass('is-loading');
			if (loadingText) {
				$btn.text(loadingText);
			}
		} else {
			$btn.prop('disabled', false).removeClass('is-loading');
			const label = $btn.data('etwa-label');
			if (label) {
				$btn.text(label);
			}
		}
	}

	function formatTime12(value) {
		if (!value) return '';
		const match = String(value).trim().substring(0, 5).match(/^(\d{2}):(\d{2})$/);
		if (!match) return String(value);
		let hour = parseInt(match[1], 10);
		const minute = match[2];
		const period = hour >= 12 ? 'PM' : 'AM';
		hour = hour % 12 || 12;
		return `${hour}:${minute} ${period}`;
	}

	function formatDateTime12(value) {
		if (!value) return '';
		const normalized = String(value).includes('T') ? String(value) : String(value).replace(' ', 'T');
		const date = new Date(normalized);
		if (Number.isNaN(date.getTime())) return String(value);
		return date.toLocaleString(undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			hour12: true,
		});
	}

	function rejectionReasonNotice(value) {
		const reason = String(value || '').trim();
		if (!reason) return '';
		const label = i18n().rejectionReason || 'Rejection reason';
		return `<br /><strong>${label}:</strong> ${reason}`;
	}

	function requestReviewDetailRows(r) {
		const strings = i18n();
		let html = '';
		if (r.status === 'rejected') {
			const reason = r.rejection_reason || r.notes || '';
			html += `<dt>${strings.rejectionReason || 'Rejection reason'}</dt><dd>${reason || '—'}</dd>`;
			if (r.reviewed_by_name) {
				html += `<dt>${strings.rejectedBy || 'Rejected by'}</dt><dd>${r.reviewed_by_name}</dd>`;
			}
		} else if (r.notes) {
			html += `<dt>${strings.notes || 'Notes'}</dt><dd>${r.notes}</dd>`;
		}
		return html;
	}

	function timelineStepMeta(step) {
		let meta = `${step.by || ''}${step.at ? ` · ${step.at}` : ''}`;
		if (step.reason) {
			const label = i18n().rejectionReason || 'Rejection reason';
			meta += `<br /><span class="etwa-timeline-reason"><strong>${label}:</strong> ${step.reason}</span>`;
		}
		return meta;
	}

	function timelineHtml(timeline, title) {
		if (!timeline.length) return '';
		const items = timeline
			.map(
				(step) =>
					`<li class="etwa-timeline-item etwa-timeline-${step.action || 'step'}"><span class="etwa-timeline-label">${step.label || step.action || ''}</span><span class="etwa-timeline-meta">${timelineStepMeta(step)}</span></li>`
			)
			.join('');
		const heading = title || i18n().approvalTimeline || 'Approval Timeline';
		return `<div class="etwa-approval-timeline"><h4>${heading}</h4><ol class="etwa-timeline-list">${items}</ol></div>`;
	}

	window.etwaUI = {
		toast,
		confirm: confirmDialog,
		promptReason,
		promptDaysToDeduct,
		setButtonLoading,
		formatTime12,
		formatDateTime12,
		rejectionReasonNotice,
		requestReviewDetailRows,
		timelineStepMeta,
		timelineHtml,
		success: (msg) => toast(msg, 'success'),
		error: (msg) => toast(msg, 'error', 6000),
		info: (msg) => toast(msg, 'info'),
	};
})();
