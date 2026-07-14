(function ($, window) {
	'use strict';

	const cfg = () => window.etwaApp || {};
	let previewTimer = null;
	let mediaFrame = null;
	let signatureRows = [];

	const SOURCE_OPTIONS = [
		{ value: 'custom', label: 'Custom text / placeholders' },
		{ value: 'dedication', label: 'Dedication line' },
		{ value: 'sender_name', label: 'Sender name' },
		{ value: 'sender_role', label: 'Sender role' },
	];

	function readSignatureRowsFromHidden() {
		const raw = $('#etwa-signature-rows-json').val();
		try {
			const parsed = JSON.parse(raw || '[]');
			signatureRows = Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			signatureRows = [];
		}
	}

	function writeSignatureRowsToHidden() {
		$('#etwa-signature-rows-json').val(JSON.stringify(signatureRows)).trigger('change');
	}

	function rowTemplate(row, index) {
		const sourceOptions = SOURCE_OPTIONS.map(
			(opt) =>
				`<option value="${opt.value}" ${row.source === opt.value ? 'selected' : ''}>${opt.label}</option>`
		).join('');

		return `
			<div class="etwa-signature-row" data-index="${index}">
				<div class="etwa-signature-row-grid">
					<label>
						<span>Text / template</span>
						<input type="text" class="etwa-signature-field" data-field="text" value="${escapeAttr(
							row.text || ''
						)}" placeholder="M: {sender_phone} | E: {sender_email}" />
					</label>
					<label>
						<span>Source</span>
						<select class="etwa-signature-field" data-field="source">${sourceOptions}</select>
					</label>
					<label>
						<span>Size (px)</span>
						<input type="number" min="11" max="24" class="etwa-signature-field" data-field="size" value="${Number(
							row.size || 14
						)}" />
					</label>
					<label>
						<span>Weight</span>
						<select class="etwa-signature-field" data-field="weight">
							<option value="normal" ${row.weight === 'normal' ? 'selected' : ''}>Normal</option>
							<option value="600" ${row.weight === '600' ? 'selected' : ''}>Semi-bold</option>
							<option value="bold" ${row.weight === 'bold' ? 'selected' : ''}>Bold</option>
						</select>
					</label>
					<label>
						<span>Color</span>
						<select class="etwa-signature-field" data-field="color">
							<option value="default" ${row.color === 'default' ? 'selected' : ''}>Default</option>
							<option value="muted" ${row.color === 'muted' ? 'selected' : ''}>Muted</option>
							<option value="dark" ${row.color === 'dark' ? 'selected' : ''}>Dark</option>
						</select>
					</label>
					<label>
						<span>Spacing below (px)</span>
						<input type="number" min="0" max="32" class="etwa-signature-field" data-field="spacing" value="${Number(
							row.spacing ?? 4
						)}" />
					</label>
					<label class="etwa-signature-row-check">
						<input type="checkbox" class="etwa-signature-field" data-field="link_email" ${
							row.link_email ? 'checked' : ''
						} />
						<span>Link email address</span>
					</label>
				</div>
				<div class="etwa-signature-row-actions">
					<button type="button" class="button-link-delete etwa-signature-remove-row">Remove row</button>
				</div>
			</div>
		`;
	}

	function escapeAttr(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;');
	}

	function renderSignatureRows() {
		const $list = $('#etwa-signature-rows-list');
		if (!$list.length) return;
		$list.html(signatureRows.map((row, index) => rowTemplate(row, index)).join(''));
	}

	function syncRowFromDom($row) {
		const index = Number($row.data('index'));
		if (Number.isNaN(index) || !signatureRows[index]) return;

		const next = { ...signatureRows[index] };
		$row.find('.etwa-signature-field').each(function () {
			const field = $(this).data('field');
			if (field === 'link_email') {
				next.link_email = $(this).is(':checked');
			} else if (field === 'size' || field === 'spacing') {
				next[field] = Number($(this).val()) || 0;
			} else {
				next[field] = $(this).val();
			}
		});
		signatureRows[index] = next;
		writeSignatureRowsToHidden();
	}

	function initSignatureBuilder() {
		readSignatureRowsFromHidden();
		if (!signatureRows.length) {
			signatureRows = [
				{
					text: '',
					source: 'dedication',
					size: 15,
					weight: 'normal',
					color: 'default',
					spacing: 4,
					link_email: false,
				},
			];
			writeSignatureRowsToHidden();
		}
		renderSignatureRows();

		$('#etwa-signature-add-row').on('click', function (e) {
			e.preventDefault();
			signatureRows.push({
				text: '',
				source: 'custom',
				size: 14,
				weight: 'normal',
				color: 'default',
				spacing: 4,
				link_email: false,
			});
			writeSignatureRowsToHidden();
			renderSignatureRows();
			updatePreview();
		});

		$('#etwa-signature-rows-list')
			.on('input change', '.etwa-signature-field', function () {
				syncRowFromDom($(this).closest('.etwa-signature-row'));
				updatePreview();
			})
			.on('click', '.etwa-signature-remove-row', function (e) {
				e.preventDefault();
				const index = Number($(this).closest('.etwa-signature-row').data('index'));
				signatureRows.splice(index, 1);
				writeSignatureRowsToHidden();
				renderSignatureRows();
				updatePreview();
			});
	}

	function collectSettings() {
		const settings = {};
		$('#etwa-email-settings-form')
			.find('[name^="etwa_email_settings["]')
			.each(function () {
				const name = this.name.match(/\[([^\]]+)\]/);
				if (!name) return;
				settings[name[1]] = $(this).val();
			});
		settings.logo_id = parseInt($('#etwa-email-logo-id').val(), 10) || 0;

		try {
			settings.signature_rows = JSON.parse($('#etwa-signature-rows-json').val() || '[]');
		} catch (e) {
			settings.signature_rows = [];
		}
		delete settings.signature_rows_json;

		return settings;
	}

	function updatePreview() {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(function () {
			$.post(cfg().ajaxUrl, {
				action: 'etwa_email_preview',
				nonce: cfg().ajaxNonce,
				settings: JSON.stringify(collectSettings()),
			})
				.done(function (response) {
					if (!response.success || !response.data?.html) return;
					const frame = document.getElementById('etwa-email-preview-frame');
					if (!frame) return;
					const doc = frame.contentDocument || frame.contentWindow.document;
					doc.open();
					doc.write(response.data.html);
					doc.close();
				})
				.fail(function () {
					// Preview failures are non-blocking.
				});
		}, 250);
	}

	function showNotice(message, type) {
		const $notice = $('#etwa-test-email-notice');
		$notice
			.removeClass('is-success is-error')
			.addClass(type === 'success' ? 'is-success' : 'is-error')
			.text(message)
			.removeAttr('hidden');
	}

	function initMediaUploader() {
		$('#etwa-email-logo-upload').on('click', function (e) {
			e.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: cfg().i18n?.selectLogo || 'Select Logo',
				button: { text: cfg().i18n?.useLogo || 'Use this logo' },
				library: { type: 'image' },
				multiple: false,
			});

			mediaFrame.on('select', function () {
				const attachment = mediaFrame.state().get('selection').first().toJSON();
				$('#etwa-email-logo-id').val(attachment.id).trigger('change');
				$('#etwa-email-logo-preview').html(`<img src="${attachment.url}" alt="" />`);
				$('#etwa-email-logo-remove').removeAttr('hidden');
				updatePreview();
			});

			mediaFrame.open();
		});

		$('#etwa-email-logo-remove').on('click', function (e) {
			e.preventDefault();
			$('#etwa-email-logo-id').val('0').trigger('change');
			$('#etwa-email-logo-preview').html(
				`<span class="etwa-muted">${cfg().i18n?.noLogo || 'No logo selected'}</span>`
			);
			$(this).attr('hidden', 'hidden');
			updatePreview();
		});
	}

	function initTestEmail() {
		$('#etwa-send-test-email').on('click', function () {
			const $btn = $(this);
			const recipient = $('#etwa-test-email-recipient').val().trim();

			if (!recipient) {
				showNotice(cfg().i18n?.invalidRecipient || 'Enter a valid recipient email address.', 'error');
				return;
			}

			$btn.prop('disabled', true).text(cfg().i18n?.sending || 'Sending…');

			$.post(cfg().ajaxUrl, {
				action: 'etwa_send_test_email',
				nonce: cfg().ajaxNonce,
				recipient_email: recipient,
				settings: JSON.stringify(collectSettings()),
			})
				.done(function (response) {
					if (response.success) {
						showNotice(response.data?.message || cfg().i18n?.testSent || 'Test email sent successfully.', 'success');
					} else {
						showNotice(response.data?.message || cfg().i18n?.error || 'Something went wrong.', 'error');
					}
				})
				.fail(function (xhr) {
					const message =
						xhr.responseJSON?.data?.message || cfg().i18n?.error || 'Something went wrong.';
					showNotice(message, 'error');
				})
				.always(function () {
					$btn.prop('disabled', false).text(cfg().i18n?.sendTest || 'Send Test Email');
				});
		});
	}

	$(function () {
		const $app = $('#etwa-email-settings-app');
		if (!$app.length) return;

		initMediaUploader();
		initTestEmail();
		initSignatureBuilder();

		$app.on('input change', '[data-etwa-preview], #etwa-email-logo-id, #etwa-signature-rows-json', updatePreview);
		updatePreview();
	});
})(jQuery, window);
