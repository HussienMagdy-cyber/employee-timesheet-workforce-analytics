(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};
	const i18n = cfg.i18n || {};

	if (!cfg.canAccessProducts && !cfg.canManage) {
		return;
	}

	const api = (path, options = {}) => window.etwaAPI.api(path, options);
	const $app = () => $('#etwa-super-clients-app');
	const canManage = () => !!cfg.canManage || $app().data('can-manage') === 1 || $app().data('can-manage') === '1';
	const canCompleteSubmission = () => !!cfg.canCompleteProductSubmission || $app().data('can-complete-submission') === 1 || $app().data('can-complete-submission') === '1';

	const labels = {
		requestTypes: cfg.superClientRequestTypeLabels || {},
		serviceCategories: cfg.superClientServiceCategoryLabels || {},
		agency: cfg.superClientAgencyLabels || {},
		industries: cfg.superClientIndustryLabels || {},
		statuses: cfg.superClientStatusLabels || {
			pending: 'Pending',
			published: 'Published',
			cancelled: 'Cancelled',
		},
	};

	let brands = [];
	let brandsById = {};
	let requests = [];
	let requestsById = {};
	let brandsSearchQuery = '';
	let requestsSearchQuery = '';
	let detailRequestId = null;
	let analyticsPreset = 'all_time';
	let analyticsDateFrom = '';
	let analyticsDateTo = '';
	let pendingCountsByTab = {};
	let directoryRows = [];

	function esc(value) {
		return $('<div>').text(value == null ? '' : String(value)).html();
	}

	function updateTabBadge(selector, count) {
		const $badge = $(selector);
		if (!$badge.length) return;
		if (count > 0) {
			$badge.text(String(count)).removeAttr('hidden');
		} else {
			$badge.attr('hidden', 'hidden').text('');
		}
	}

	function pendingCountForTab(tabId) {
		const id = parseInt(tabId, 10);
		if (!id) return 0;
		return pendingCountsByTab[id] || 0;
	}

	function pendingBadgeHtml(count) {
		if (!count || count <= 0) return '';
		return `<span class="etwa-badge">${esc(String(count))}</span>`;
	}

	function applyPendingCounts(data) {
		pendingCountsByTab = {};
		(data?.tabs || []).forEach((tab) => {
			const id = parseInt(tab.id, 10);
			if (id > 0) {
				pendingCountsByTab[id] = tab.pending_count || 0;
			}
		});

		const currentId = clientId();
		updateTabBadge('#etwa-sc-requests-tab-badge', pendingCountForTab(currentId));
		updateTabBadge('#etwa-sc-hub-pending-badge', pendingCountForTab(currentId));
		renderDirectoryRows();
		refreshNavBadges();
	}

	function refreshNavBadges() {
		if (!cfg.root || !window.etwaAPI) return;
		window.etwaAPI
			.api('portal/nav-badges')
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

	function refreshProductRequestBadges() {
		return api('super-client-requests/pending-count')
			.then((res) => {
				if (!res.success) return;
				applyPendingCounts(res.data || {});
			})
			.catch(() => {});
	}

	function formatIndustryDisplay(value) {
		if (!value) return '—';
		return labels.industries[value] || value;
	}

	function formatAgencyDisplay(row) {
		if (!row || row.agency === 'direct') {
			return labels.agency.direct || 'Direct';
		}
		if (row.agency_name) {
			return row.agency_name;
		}
		return labels.agency.agency || 'Agency';
	}

	function formatDate(value) {
		if (!value) return '—';
		const d = new Date(String(value).replace(' ', 'T'));
		if (Number.isNaN(d.getTime())) return value;
		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	function formatStatus(value) {
		const status = value || 'pending';
		return labels.statuses[status] || status;
	}

	function statusClass(value) {
		const status = value || 'pending';
		if (status === 'published') return 'etwa-status-approved';
		if (status === 'cancelled') return 'etwa-status-rejected';
		return 'etwa-status-pending';
	}

	function truncate(text, max) {
		const s = String(text || '');
		if (s.length <= max) return s;
		return s.slice(0, max) + '…';
	}

	function openModal($el) {
		$el.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModal($el) {
		($el || $('.etwa-modal')).attr('hidden', 'hidden').attr('aria-hidden', 'true');
		if (!$('.etwa-modal:not([hidden])').length) {
			$('body').removeClass('etwa-modal-open');
		}
	}

	function clientId() {
		return parseInt($app().data('client-id'), 10) || 0;
	}

	function hubUrl(id, tab) {
		const base = $app().data('portal-base');
		const url = base ? new URL(base, window.location.origin) : new URL(window.location.href);
		if (id) {
			url.searchParams.set('client_id', String(id));
		} else {
			url.searchParams.delete('client_id');
			url.searchParams.delete('tab');
		}
		if (tab && id) {
			url.searchParams.set('tab', tab === 'clients' ? 'brands' : tab);
		}
		return url.toString();
	}

	function setTableLoading($body, cols) {
		$body.html(`<tr><td colspan="${cols}"><span class="etwa-sc-loading">${esc(i18n.loading || 'Loading…')}</span></td></tr>`);
	}

	function setTableError($body, cols, message) {
		$body.html(`<tr><td colspan="${cols}"><span class="etwa-sc-error">${esc(message)}</span></td></tr>`);
	}

	function syncRadioCards($form, fieldName) {
		$form.find(`[name="${fieldName}"]`).each(function () {
			$(this).closest('.etwa-sc-radio-card').toggleClass('is-selected', this.checked);
		});
	}

	function clearFormErrors($form) {
		$form.find('.etwa-field-error').attr('hidden', true).text('');
		$form.find('label.is-invalid').removeClass('is-invalid');
		$form.find('.etwa-sc-radio-cards').removeClass('is-invalid');
	}

	function showFieldError($form, fieldName, message) {
		const $error = $form.find(`.etwa-field-error[data-for="${fieldName}"]`);
		if ($error.length) {
			$error.text(message).removeAttr('hidden');
		}
		const $radioGroup = $form.find(`[name="${fieldName}"]`).first().closest('.etwa-sc-radio-cards');
		if ($radioGroup.length) {
			$radioGroup.addClass('is-invalid');
			return;
		}
		$form.find(`[name="${fieldName}"]`).closest('label').addClass('is-invalid');
	}

	function scrollToFirstError($form) {
		const $firstError = $form.find('.etwa-field-error:not([hidden])').first();
		if ($firstError.length) {
			$firstError.closest('label, .etwa-sc-field-group, section')[0]?.scrollIntoView({ block: 'nearest' });
		}
	}

	function toggleAgencyNameField($form) {
		const isAgency = $form.find('[name="agency"]:checked').val() === 'agency';
		const $wrap = $form.find('#etwa-sc-agency-name-wrap');
		const $input = $wrap.find('[name="agency_name"]');
		if (isAgency) {
			$wrap.removeAttr('hidden');
			$input.prop('required', true);
		} else {
			$wrap.attr('hidden', 'hidden');
			$input.prop('required', false).val('');
			$wrap.removeClass('is-invalid');
			$form.find('.etwa-field-error[data-for="agency_name"]').attr('hidden', true).text('');
		}
	}

	function validateBrandForm($form) {
		clearFormErrors($form);
		let valid = true;
		const name = $form.find('[name="name"]').val().trim();
		if (!name) {
			showFieldError($form, 'name', i18n.brandNameRequired || 'Brand is required.');
			valid = false;
		}
		const industry = $form.find('[name="industry"]').val();
		if (!industry) {
			showFieldError($form, 'industry', i18n.industryRequired || 'Industry is required.');
			valid = false;
		}
		const agency = $form.find('[name="agency"]:checked').val();
		if (!agency) {
			showFieldError($form, 'agency', i18n.agencyRequired || 'Agency type is required.');
			valid = false;
		}
		const agencyName = $form.find('[name="agency_name"]').val().trim();
		if (agency === 'agency' && !agencyName) {
			showFieldError($form, 'agency_name', i18n.agencyNameRequired || 'Agency name is required when Agency is selected.');
			valid = false;
		}
		return valid;
	}

	function validateRequestForm($form) {
		clearFormErrors($form);
		let valid = true;

		const brandId = parseInt($form.find('[name="brand_id"]').val(), 10);
		if (!brandId) {
			showFieldError($form, 'brand_id', i18n.brandRequired || 'Brand is required.');
			valid = false;
		}

		const category = $form.find('[name="service_category"]:checked').val();
		if (!category) {
			showFieldError($form, 'service_category', i18n.serviceCategoryRequired || 'Service category is required.');
			valid = false;
		}

		const service = $form.find('[name="service"]').val().trim();
		if (!service) {
			showFieldError($form, 'service', i18n.serviceRequired || 'Service is required.');
			valid = false;
		}

		const email = $form.find('[name="contact_email"]').val().trim();
		if (canCompleteSubmission() && email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			showFieldError($form, 'contact_email', i18n.invalidEmail || 'Enter a valid email address.');
			valid = false;
		}

		return { valid, category, service };
	}

	function indexBrands(rows) {
		brands = rows || [];
		brandsById = {};
		brands.forEach((row) => {
			brandsById[row.id] = row;
		});
	}

	function indexRequests(rows) {
		requests = rows || [];
		requestsById = {};
		requests.forEach((row) => {
			requestsById[row.id] = row;
		});
	}

	function filterBrands(rows) {
		const q = brandsSearchQuery.trim().toLowerCase();
		if (!q) return rows;
		return rows.filter((row) => {
			const hay = [row.name, formatIndustryDisplay(row.industry), formatAgencyDisplay(row)].join(' ').toLowerCase();
			return hay.includes(q);
		});
	}

	function filterRequests(rows) {
		const q = requestsSearchQuery.trim().toLowerCase();
		if (!q) return rows;
		return rows.filter((row) => {
			const brandName = row.brand ? row.brand.name : '';
			const hay = [
				brandName,
				row.service,
				row.status,
				formatStatus(row.status),
				row.contact_name,
				row.contact_email,
				row.invoice_number,
				labels.requestTypes[row.request_type],
				labels.serviceCategories[row.service_category],
			]
				.join(' ')
				.toLowerCase();
			return hay.includes(q);
		});
	}

	function renderBrandsTable() {
		const $body = $('#etwa-sc-brands-body');
		const filtered = filterBrands(brands);
		if (!brands.length) {
			$body.html(`<tr><td colspan="6">
				<div class="etwa-empty-state">
					<p class="etwa-empty-state-title">${esc(i18n.noBrands || 'No brands yet.')}</p>
					<p>${esc(i18n.noBrandsHint || 'Create your first brand to get started.')}</p>
					${canManage() ? `<button type="button" class="etwa-btn etwa-btn-primary etwa-sc-empty-add-brand">${esc(i18n.addBrand || 'Add Brand')}</button>` : ''}
				</div>
			</td></tr>`);
			return;
		}
		if (!filtered.length) {
			$body.html(`<tr><td colspan="6">${esc(i18n.noSearchResults || 'No matching brands.')}</td></tr>`);
			return;
		}
		$body.html(
			filtered
				.map((row) => {
					const status = row.is_active
						? `<span class="etwa-status etwa-status-approved">${esc(i18n.active || 'Active')}</span>`
						: `<span class="etwa-status etwa-status-rejected">${esc(i18n.inactive || 'Inactive')}</span>`;
					const actions = [
						`<button type="button" class="etwa-link etwa-sc-view-brand-requests" data-id="${row.id}">${esc(i18n.viewRequests || 'Requests')}</button>`,
					];
					if (canManage()) {
						actions.unshift(`<button type="button" class="etwa-link etwa-sc-edit-brand" data-id="${row.id}">${esc(i18n.edit || 'Edit')}</button>`);
						if (row.is_active) {
							actions.push(`<button type="button" class="etwa-link etwa-sc-deactivate-brand" data-id="${row.id}">${esc(i18n.deactivate || 'Deactivate')}</button>`);
						}
					}
					return `<tr data-id="${row.id}">
						<td>${esc(row.name)}</td>
						<td>${esc(formatIndustryDisplay(row.industry))}</td>
						<td>${esc(formatAgencyDisplay(row))}</td>
						<td>${esc(row.request_count || 0)}</td>
						<td>${status}</td>
						<td class="etwa-actions-cell">${actions.join(' ')}</td>
					</tr>`;
				})
				.join('')
		);
	}

	function renderRequestsTable() {
		const $body = $('#etwa-sc-requests-body');
		const filtered = filterRequests(requests).sort((a, b) => {
			if (a.status === 'pending' && b.status !== 'pending') return -1;
			if (a.status !== 'pending' && b.status === 'pending') return 1;
			return 0;
		});
		if (!requests.length) {
			$body.html(`<tr><td colspan="9">
				<div class="etwa-empty-state">
					<p class="etwa-empty-state-title">${esc(i18n.noRequests || 'No requests yet.')}</p>
					<p>${esc(i18n.noRequestsHint || 'Create a request for one of your brands.')}</p>
					<button type="button" class="etwa-btn etwa-btn-primary etwa-sc-empty-add-request">${esc(i18n.createRequest || 'Create Request')}</button>
				</div>
			</td></tr>`);
			return;
		}
		if (!filtered.length) {
			$body.html(`<tr><td colspan="9">${esc(i18n.noSearchResults || 'No matching requests.')}</td></tr>`);
			return;
		}
		$body.html(
			filtered
				.map((row) => {
					const brandName = row.brand ? row.brand.name : `#${row.brand_id}`;
					const category = labels.serviceCategories[row.service_category] || row.service_category;
					const contact = [row.contact_name, row.contact_email].filter(Boolean).join(' · ') || '—';
					const serviceTitle = esc(row.service || '');
					const serviceCell = esc(truncate(row.service || '—', 48));
					const pendingMark =
						row.status === 'pending' && (canManage() || canCompleteSubmission())
							? ` <span class="etwa-badge" title="${esc(i18n.pendingReview || 'Pending review')}">!</span>`
							: '';
					const actions = [
						`<button type="button" class="etwa-link etwa-sc-view-request" data-id="${row.id}">${esc(i18n.view || 'View')}</button>`,
						`<button type="button" class="etwa-link etwa-sc-edit-request" data-id="${row.id}">${esc(i18n.edit || 'Edit')}</button>`,
					];
					if (canManage()) {
						actions.push(`<button type="button" class="etwa-link etwa-sc-delete-request" data-id="${row.id}">${esc(i18n.delete || 'Delete')}</button>`);
					}
					return `<tr data-id="${row.id}"${row.status === 'pending' ? ' class="etwa-sc-request-pending"' : ''}>
						<td>${esc(brandName)}${pendingMark}</td>
						<td>${esc(labels.requestTypes[row.request_type] || row.request_type)}</td>
						<td>${esc(category)}</td>
						<td class="etwa-sc-cell-truncate" title="${serviceTitle}">${serviceCell}</td>
						<td><span class="etwa-status ${statusClass(row.status)}">${esc(formatStatus(row.status))}</span></td>
						<td class="etwa-sc-cell-truncate" title="${esc(contact)}">${esc(truncate(contact, 32))}</td>
						<td>${esc(row.invoice_number || '—')}</td>
						<td>${esc(formatDate(row.created_at))}</td>
						<td class="etwa-actions-cell">${actions.join(' ')}</td>
					</tr>`;
				})
				.join('')
		);
	}

	function renderOverviewStats(data) {
		const totalBrands = data.total_brands != null ? data.total_brands : 0;
		const activeBrands = data.active_brands != null ? data.active_brands : brands.filter((b) => b.is_active).length;
		const totalRequests = data.total_requests != null ? data.total_requests : requests.length;
		const cards = [
			{ label: i18n.totalBrands || 'Total Brands', value: totalBrands },
			{ label: i18n.activeBrands || 'Active Brands', value: activeBrands },
			{ label: i18n.totalRequests || 'Total Requests', value: totalRequests },
		];
		let html = '';
		cards.forEach((c) => {
			html += `<div class="etwa-kpi-card"><span class="etwa-kpi-label">${esc(c.label)}</span><strong class="etwa-kpi-value">${esc(c.value)}</strong></div>`;
		});
		$('#etwa-sc-overview-stats').html(html);
	}

	function renderOverviewRecent() {
		const $panel = $('#etwa-sc-overview-recent');
		if (!brands.length) {
			$panel.html(`
				<div class="etwa-empty-state">
					<p class="etwa-empty-state-title">${esc(i18n.noBrands || 'No brands yet.')}</p>
					<p>${esc(i18n.noBrandsHint || 'Create your first brand to get started.')}</p>
					${canManage() ? `<button type="button" class="etwa-btn etwa-btn-primary etwa-sc-empty-add-brand">${esc(i18n.addBrand || 'Add Brand')}</button>` : ''}
				</div>
			`);
			return;
		}
		const recent = requests.slice(0, 5);
		if (!recent.length) {
			$panel.html(`
				<h3 class="etwa-super-client__section-title">${esc(i18n.recentRequests || 'Recent Requests')}</h3>
				<p class="etwa-muted">${esc(i18n.noRecentRequests || 'No requests yet.')}</p>
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-sc-empty-add-request">${esc(i18n.createRequest || 'Create Request')}</button>
			`);
			return;
		}
		let html = `<h3 class="etwa-super-client__section-title">${esc(i18n.recentRequests || 'Recent Requests')}</h3>`;
		html += '<div class="etwa-table-wrap"><table class="etwa-table"><thead><tr>';
		html += `<th>${esc(i18n.brandLabel || 'Brand')}</th><th>${esc(i18n.serviceCategory || 'Category')}</th><th>${esc(i18n.byService || 'Service')}</th><th>${esc(i18n.date || 'Date')}</th><th></th>`;
		html += '</tr></thead><tbody>';
		recent.forEach((row) => {
			const brandName = row.brand ? row.brand.name : `#${row.brand_id}`;
			html += `<tr>
				<td>${esc(brandName)}</td>
				<td>${esc(labels.serviceCategories[row.service_category] || row.service_category)}</td>
				<td class="etwa-sc-cell-truncate">${esc(truncate(row.service, 40))}</td>
				<td>${esc(formatDate(row.created_at))}</td>
				<td><button type="button" class="etwa-link etwa-sc-view-request" data-id="${row.id}">${esc(i18n.view || 'View')}</button></td>
			</tr>`;
		});
		html += '</tbody></table></div>';
		$panel.html(html);
	}

	function renderAnalytics(data) {
		const $panel = $('#etwa-sc-analytics-panel');
		const totalBrands = data.total_brands != null ? data.total_brands : 0;
		const totalRequests = data.total_requests != null ? data.total_requests : 0;
		const rangeMeta = data.range_meta || {};
		const $rangeLabel = $('#etwa-sc-analytics-range-label');
		if (rangeMeta.label) {
			$rangeLabel.text(rangeMeta.label).removeAttr('hidden');
		} else {
			$rangeLabel.attr('hidden', true).text('');
		}

		if (!totalBrands && !totalRequests) {
			$panel.html(`
				<div class="etwa-empty-state">
					<p class="etwa-empty-state-title">${esc(i18n.noAnalyticsData || 'No analytics data yet.')}</p>
					<p>${esc(i18n.noAnalyticsHint || 'Add brands and requests to see analytics here.')}</p>
				</div>
			`);
			return;
		}

		const renderGroup = (title, items) => {
			if (!items || !items.length) return '';
			let block = `<div class="etwa-super-client__analytics-block"><h3>${esc(title)}</h3><table class="etwa-table"><tbody>`;
			items.forEach((item) => {
				block += `<tr><td>${esc(item.label)}</td><td>${esc(item.count)}</td></tr>`;
			});
			block += '</tbody></table></div>';
			return block;
		};

		const filtered = rangeMeta.preset && rangeMeta.preset !== 'all_time';
		const requestLabel = filtered
			? (i18n.requestsInPeriod || 'Total Requests (in period)')
			: (i18n.totalRequests || 'Total Requests');
		const brandLabel = i18n.brandsAllTime || i18n.totalBrands || 'Total Brands';

		let html = '<div class="etwa-kpi-grid">';
		html += `<div class="etwa-kpi-card"><span class="etwa-kpi-label">${esc(brandLabel)}</span><strong class="etwa-kpi-value">${esc(totalBrands)}</strong></div>`;
		html += `<div class="etwa-kpi-card"><span class="etwa-kpi-label">${esc(requestLabel)}</span><strong class="etwa-kpi-value">${esc(totalRequests)}</strong></div>`;
		html += '</div>';

		html += '<div class="etwa-super-client__analytics-grid">';
		html += renderGroup(i18n.requestsByType || 'Requests by Type', data.by_request_type);
		html += renderGroup(i18n.serviceCategory || 'Service Category', data.by_service_category);
		html += renderGroup(i18n.byAgency || 'Brands by Agency', data.by_agency);
		html += renderGroup(i18n.byIndustry || 'Brands by Industry', data.by_industry);
		html += renderGroup(i18n.requestsByIndustry || 'Requests by Industry', data.by_industry_requests);
		html += '</div>';

		html += renderGroup(i18n.byService || 'Service', data.by_service || data.by_service_type);

		const byBrand = data.by_brand || data.by_child_client;
		if (byBrand && byBrand.length) {
			html += `<div class="etwa-super-client__analytics-block"><h3>${esc(i18n.byBrand || 'By Brand')}</h3><table class="etwa-table"><tbody>`;
			byBrand.forEach((row) => {
				const name = row.brand_name || row.child_client_name;
				html += `<tr><td>${esc(name)}</td><td>${esc(row.count)}</td></tr>`;
			});
			html += '</tbody></table></div>';
		}

		$panel.html(html);
	}

	function renderDirectoryRows() {
		const $body = $('#etwa-sc-directory-body');
		if (!$body.length || !directoryRows.length) return;

		$body.html(
			directoryRows
				.map((row) => {
					const c = row.client || {};
					const status = c.is_active
						? `<span class="etwa-status etwa-status-approved">${esc(i18n.active || 'Active')}</span>`
						: `<span class="etwa-status etwa-status-rejected">${esc(i18n.inactive || 'Inactive')}</span>`;
					const brandCount = row.brand_count != null ? row.brand_count : row.child_count;
					const pendingCount = pendingCountForTab(c.id);
					return `<tr>
						<td>${esc(c.name)} ${pendingBadgeHtml(pendingCount)} <span class="etwa-badge etwa-badge-super">${esc(i18n.superClientBadge || 'PRODUCT')}</span></td>
						<td><code>${esc(c.code)}</code></td>
						<td>${esc(brandCount)}</td>
						<td>${esc(row.request_count)}</td>
						<td>${status}</td>
						<td><a class="etwa-link" href="${esc(hubUrl(c.id, pendingCount > 0 ? 'requests' : 'overview'))}">${esc(i18n.manage || 'Manage')}</a></td>
					</tr>`;
				})
				.join('')
		);
	}

	function loadDirectory() {
		const $body = $('#etwa-sc-directory-body');
		setTableLoading($body, 6);
		return api('super-clients')
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				directoryRows = res.data || [];
				if (!directoryRows.length) {
					const empty = canManage()
						? (i18n.noSuperClients || 'No Products yet. Promote a client from Workspace.')
						: (i18n.noProductTabsForUnit || 'No Product Tabs are available for your department.');
					$body.html(`<tr><td colspan="6">${esc(empty)}</td></tr>`);
					return;
				}
				renderDirectoryRows();
			})
			.catch((err) => setTableError($body, 6, err.message));
	}

	function loadBrands() {
		const id = clientId();
		const $body = $('#etwa-sc-brands-body');
		setTableLoading($body, 6);
		return api(`clients/${id}/super-clients`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				indexBrands(res.data || []);
				renderBrandsTable();
			})
			.catch((err) => setTableError($body, 6, err.message));
	}

	function loadRequests() {
		const id = clientId();
		const $body = $('#etwa-sc-requests-body');
		setTableLoading($body, 9);
		return api(`clients/${id}/super-client-requests`)
			.then((res) => {
				if (!res.success) throw new Error(res.message);
				indexRequests(res.data || []);
				renderRequestsTable();
			})
			.catch((err) => setTableError($body, 9, err.message));
	}

	function analyticsQuery() {
		const params = new URLSearchParams({ preset: analyticsPreset || 'all_time' });
		if (analyticsPreset === 'custom') {
			if (analyticsDateFrom) params.set('date_from', analyticsDateFrom);
			if (analyticsDateTo) params.set('date_to', analyticsDateTo);
		}
		return params.toString();
	}

	function loadAnalyticsData(withRange) {
		const id = clientId();
		let path = `clients/${id}/super-client-analytics`;
		if (withRange) {
			path += '?' + analyticsQuery();
		}
		return api(path).then((res) => {
			if (!res.success) throw new Error(res.message);
			return res.data || {};
		});
	}

	function loadOverview() {
		$('#etwa-sc-overview-stats').html(`<p class="etwa-muted">${esc(i18n.loading || 'Loading…')}</p>`);
		$('#etwa-sc-overview-recent').html('');
		const tasks = [loadBrands(), loadRequests()];
		if (canManage()) {
			tasks.unshift(loadAnalyticsData());
		}
		return Promise.all(tasks)
			.then((results) => {
				const data = canManage() ? results[0] : {};
				renderOverviewStats(data);
				renderOverviewRecent();
			})
			.catch((err) => {
				$('#etwa-sc-overview-stats').html(`<p class="etwa-sc-error">${esc(err.message)}</p>`);
			});
	}

	function loadAnalytics() {
		const $panel = $('#etwa-sc-analytics-panel');
		$panel.html(`<p class="etwa-muted">${esc(i18n.loading || 'Loading analytics…')}</p>`);
		return loadAnalyticsData(true)
			.then((data) => renderAnalytics(data))
			.catch((err) => {
				$panel.html(`<p class="etwa-sc-error">${esc(err.message)}</p>`);
			});
	}

	function syncAnalyticsCustomFields() {
		$('#etwa-sc-analytics-filter .etwa-custom-range-fields').toggleClass('is-visible', analyticsPreset === 'custom');
	}

	function setAnalyticsPreset(preset) {
		analyticsPreset = preset || 'all_time';
		$('#etwa-sc-analytics-filter .etwa-date-preset-row .etwa-tab')
			.removeClass('active')
			.filter(`[data-preset="${analyticsPreset}"]`)
			.addClass('active');
		syncAnalyticsCustomFields();
	}

	function populateBrandSelect($select, selectedId) {
		$select.html(`<option value="">${esc(i18n.selectBrand || 'Select brand…')}</option>`);
		const activeBrands = brands.filter((b) => b.is_active);
		const selectedBrand = selectedId ? brandsById[selectedId] : null;
		if (selectedBrand && !selectedBrand.is_active && !activeBrands.some((b) => b.id === selectedBrand.id)) {
			activeBrands.push(selectedBrand);
		}
		activeBrands.forEach((b) => {
			$select.append(`<option value="${b.id}">${esc(b.name)}</option>`);
		});
		if (selectedId) {
			$select.val(String(selectedId));
		}
	}

	function openBrandModal(row) {
		const $modal = $('#etwa-sc-brand-modal');
		const $form = $('#etwa-sc-brand-form');
		$form[0].reset();
		clearFormErrors($form);
		$form.find('[name="id"]').val(row ? row.id : '');
		$('#etwa-sc-brand-modal-title').text(row ? (i18n.editBrand || 'Edit Brand') : (i18n.addBrand || 'Add Brand'));
		$('#etwa-sc-brand-modal-subtitle').text(
			row
				? (i18n.editBrandSubtitle || 'Update brand profile details')
				: (i18n.addBrandSubtitle || 'Add a brand profile for this Product')
		);
		if (row) {
			$form.find('[name="name"]').val(row.name);
			$form.find('[name="industry"]').val(row.industry);
			$form.find(`[name="agency"][value="${row.agency || 'direct'}"]`).prop('checked', true);
			$form.find('[name="agency_name"]').val(row.agency_name || '');
			$form.find('[name="is_active"]').prop('checked', !!row.is_active);
		} else {
			$form.find('[name="agency"][value="direct"]').prop('checked', true);
			$form.find('[name="agency_name"]').val('');
			$form.find('[name="is_active"]').prop('checked', true);
		}
		syncRadioCards($form, 'agency');
		toggleAgencyNameField($form);
		openModal($modal);
	}

	function setRequestModalMode(mode, row) {
		const $form = $('#etwa-sc-request-form');
		const $step2 = $('#etwa-sc-request-step2');
		const showSubmission = canCompleteSubmission() && mode !== 'create';
		if (showSubmission) {
			$step2.removeAttr('hidden');
		} else {
			$step2.attr('hidden', 'hidden');
		}
		$form.find('#etwa-sc-request-step1 input, #etwa-sc-request-step1 select').prop('disabled', mode === 'submission');
		if (mode === 'submission') {
			$form.find('#etwa-sc-request-step1 input[type="radio"]').prop('disabled', true);
		}
	}

	function openRequestModal(row, mode) {
		const $modal = $('#etwa-sc-request-modal');
		const $form = $('#etwa-sc-request-form');
		$form[0].reset();
		clearFormErrors($form);
		$form.find('[name="id"]').val(row ? row.id : '');
		const isSubmission = mode === 'submission' && row;
		$('#etwa-sc-request-modal-title').text(
			isSubmission
				? (i18n.completeSubmission || 'Complete Submission')
				: row
					? (i18n.editRequest || 'Edit Request')
					: (i18n.createRequest || 'Create Request')
		);
		$('#etwa-sc-request-modal-subtitle').text(
			isSubmission
				? (i18n.completeSubmissionSubtitle || 'Complete submission and receipt information')
				: row
					? (i18n.editRequestSubtitle || 'Update request details for this brand')
					: (i18n.createRequestSubtitle || 'Add a new request for a Product brand')
		);
		$('#etwa-sc-request-submit').text(
			isSubmission
				? (i18n.completeSubmission || 'Complete Submission')
				: (i18n.saveRequest || 'Save Request')
		);

		populateBrandSelect($('#etwa-sc-request-brand-select'), row ? row.brand_id : null);

		if (row) {
			$form.find('[name="request_type"]').val(row.request_type);
			$form.find(`[name="service_category"][value="${row.service_category}"]`).prop('checked', true);
			$form.find('[name="service"]').val(row.service);
			$form.find('[name="status"]').val(row.status || 'pending');
			$form.find('[name="contact_name"]').val(row.contact_name);
			$form.find('[name="contact_email"]').val(row.contact_email);
			$form.find('[name="contact_phone"]').val(row.contact_phone);
			$form.find('[name="invoice_number"]').val(row.invoice_number);
		} else {
			$form.find('[name="status"]').val('pending');
		}
		setRequestModalMode(isSubmission ? 'submission' : row ? 'edit' : 'create', row);
		$form.data('mode', isSubmission ? 'submission' : row ? 'edit' : 'create');
		syncRadioCards($form, 'service_category');
		openModal($modal);
	}

	function renderRequestDetail(row) {
		const brandName = row.brand ? row.brand.name : `#${row.brand_id}`;
		const contactLines = [row.contact_name, row.contact_email, row.contact_phone].filter(Boolean);
		const contact = contactLines.length ? contactLines.join('\n') : '—';
		const requestItems = [
			{ label: i18n.brandLabel || 'Brand', value: brandName },
			{ label: i18n.requestTypeLabel || 'Request Type', value: labels.requestTypes[row.request_type] || row.request_type },
			{ label: i18n.serviceCategory || 'Service Category', value: labels.serviceCategories[row.service_category] || row.service_category },
			{ label: i18n.byService || 'Service', value: row.service || '—', full: true },
			{ label: i18n.statusLabel || 'Status', value: formatStatus(row.status) },
		];
		const submissionItems = [
			{ label: i18n.contactInformation || 'Contact', value: contact, full: true },
			{ label: i18n.invoiceLabel || 'Invoice #', value: row.invoice_number || '—' },
		];

		const renderItems = (items) =>
			items
				.map((item) => {
					const cls = item.full ? ' etwa-sc-detail-item--full' : '';
					let valueHtml = esc(item.value);
					if (item.full && String(item.value).includes('\n')) {
						valueHtml = String(item.value)
							.split('\n')
							.map((line) => esc(line))
							.join('<br>');
					}
					return `<div class="etwa-sc-detail-item${cls}"><dt>${esc(item.label)}</dt><dd>${valueHtml}</dd></div>`;
				})
				.join('');

		let html = `<div class="etwa-sc-detail-section"><h3>${esc(i18n.requestDetails || 'Request')}</h3><div class="etwa-sc-detail-grid">${renderItems(requestItems)}</div></div>`;
		html += `<div class="etwa-sc-detail-section etwa-sc-detail-section--submission"><h3>${esc(i18n.submissionReceipt || 'Submission / Receipt')}</h3><div class="etwa-sc-detail-grid">${renderItems(submissionItems)}</div></div>`;
		$('#etwa-sc-request-detail-body').html(html);
		$('#etwa-sc-request-detail-subtitle').text(row.service ? truncate(row.service, 60) : '');
		const $submitBtn = $('#etwa-sc-request-detail-submit');
		if ($submitBtn.length) {
			$submitBtn.text(
				row.contact_name || row.invoice_number
					? (i18n.editSubmission || 'Edit Submission')
					: (i18n.completeSubmission || 'Complete Submission')
			);
		}
	}

	function openRequestDetailModal(row) {
		detailRequestId = row.id;
		renderRequestDetail(row);
		openModal($('#etwa-sc-request-detail-modal'));
	}

	function loadHubTab(tab) {
		const normalized = tab === 'clients' ? 'brands' : tab;
		switch (normalized) {
			case 'overview':
				return loadOverview();
			case 'brands':
				return loadBrands();
			case 'requests':
				return Promise.all([loadBrands(), loadRequests()]);
			case 'analytics':
				return loadAnalytics();
			default:
				return Promise.resolve();
		}
	}

	function switchTab(tab) {
		const normalized = tab === 'clients' ? 'brands' : tab;
		$('.etwa-sc-tabs .etwa-tab').removeClass('active').attr('aria-selected', 'false');
		$(`.etwa-sc-tabs .etwa-tab[data-tab="${normalized}"]`).addClass('active').attr('aria-selected', 'true');
		$app().find('.etwa-tab-panel').removeClass('active');
		$(`#etwa-sc-tab-${normalized}`).addClass('active');
		$app().data('tab', normalized);
		window.history.replaceState({}, '', hubUrl(clientId(), normalized));
		loadHubTab(normalized).catch((err) => ui.error(err.message));
	}

	function bindFormFieldClear($form) {
		$form.on('input change', 'input, select', function () {
			const $field = $(this);
			const name = $field.attr('name');
			if (!name) return;
			$field.closest('label').removeClass('is-invalid');
			$form.find(`.etwa-field-error[data-for="${name}"]`).attr('hidden', true).text('');
			if ($field.is('[type="radio"]')) {
				$form.find('.etwa-sc-radio-cards').removeClass('is-invalid');
				syncRadioCards($form, name);
			}
		});
	}

	$(function () {
		if (!$app().length) return;

		const mode = $app().data('mode');

		if (mode === 'list') {
			loadDirectory().finally(() => refreshProductRequestBadges());
			return;
		}

		let initialTab = $app().data('tab') || 'overview';
		if (initialTab === 'clients' || initialTab === 'projects') initialTab = 'brands';
		loadHubTab(initialTab).catch((err) => ui.error(err.message));
		refreshProductRequestBadges();

		$('.etwa-sc-tabs .etwa-tab').on('click', function () {
			switchTab($(this).data('tab'));
		});

		$(document).on('click', '.etwa-modal-close', function () {
			closeModal($(this).closest('.etwa-modal'));
		});

		$(document).on('click', '.etwa-modal', function (e) {
			if ($(e.target).is('.etwa-modal')) closeModal($(this));
		});

		$('#etwa-sc-brands-search').on('input', function () {
			brandsSearchQuery = $(this).val();
			renderBrandsTable();
		});

		$('#etwa-sc-requests-search').on('input', function () {
			requestsSearchQuery = $(this).val();
			renderRequestsTable();
		});

		$('#etwa-sc-analytics-filter').on('click', '.etwa-date-preset-row .etwa-tab', function () {
			const preset = $(this).data('preset');
			setAnalyticsPreset(preset);
			if (preset !== 'custom') {
				loadAnalytics().catch((err) => ui.error(err.message));
			}
		});

		$('#etwa-sc-analytics-apply').on('click', function () {
			analyticsDateFrom = $('#etwa-sc-analytics-from').val() || '';
			analyticsDateTo = $('#etwa-sc-analytics-to').val() || '';
			if (!analyticsDateFrom || !analyticsDateTo) {
				return;
			}
			setAnalyticsPreset('custom');
			loadAnalytics().catch((err) => ui.error(err.message));
		});

		$('#etwa-sc-demote').on('click', function () {
			const id = clientId();
			const $btn = $(this);
			ui.confirm(i18n.confirmDemoteSuper || 'Demote this client to a standard client? Child data will be preserved.', i18n.confirmTitle || 'Please confirm').then((ok) => {
				if (!ok) return;
				ui.setButtonLoading($btn, true, i18n.saving);
				api(`clients/${id}/demote-super`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(i18n.demotedSuper || 'Client demoted.');
						window.location.href = hubUrl(0);
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn, false));
			});
		});

		$('#etwa-sc-add-brand').on('click', () => openBrandModal(null));
		$(document).on('click', '.etwa-sc-empty-add-brand', () => openBrandModal(null));

		$('#etwa-sc-brand-form').on('change', '[name="agency"]', function () {
			const $form = $(this).closest('form');
			syncRadioCards($form, 'agency');
			toggleAgencyNameField($form);
		});

		$('#etwa-sc-add-request').on('click', () => {
			loadBrands().then(() => openRequestModal(null)).catch((err) => ui.error(err.message));
		});
		$(document).on('click', '.etwa-sc-empty-add-request', () => {
			loadBrands().then(() => openRequestModal(null)).catch((err) => ui.error(err.message));
		});

		$(document).on('click', '.etwa-sc-edit-brand', function () {
			const id = parseInt($(this).data('id'), 10);
			const row = brandsById[id];
			if (row) openBrandModal(row);
		});

		$(document).on('click', '.etwa-sc-view-brand-requests', function () {
			const brandId = parseInt($(this).data('id'), 10);
			requestsSearchQuery = brandsById[brandId]?.name || '';
			$('#etwa-sc-requests-search').val(requestsSearchQuery);
			switchTab('requests');
		});

		$(document).on('click', '.etwa-sc-deactivate-brand', function () {
			const id = parseInt($(this).data('id'), 10);
			ui.confirm(i18n.confirmDeactivateBrand || 'Deactivate this brand?', i18n.confirmTitle).then((ok) => {
				if (!ok) return;
				api(`super-clients/${id}/deactivate`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(i18n.brandDeactivated || 'Brand deactivated.');
						loadHubTab($app().data('tab') || 'brands').catch((err) => ui.error(err.message));
					})
					.catch((err) => ui.error(err.message));
			});
		});

		bindFormFieldClear($('#etwa-sc-brand-form'));
		bindFormFieldClear($('#etwa-sc-request-form'));

		$('#etwa-sc-brand-form').on('submit', function (e) {
			e.preventDefault();
			const $form = $(this);
			const $btn = $('#etwa-sc-brand-submit');
			if (!validateBrandForm($form)) {
				scrollToFirstError($form);
				return;
			}
			const accountId = parseInt($form.find('[name="id"]').val(), 10);
			const payload = {
				name: $form.find('[name="name"]').val(),
				industry: $form.find('[name="industry"]').val(),
				agency: $form.find('[name="agency"]:checked').val(),
				agency_name: $form.find('[name="agency_name"]').val(),
				is_active: $form.find('[name="is_active"]').is(':checked'),
			};
			ui.setButtonLoading($btn, true, i18n.saving);
			const path = accountId ? `super-clients/${accountId}` : `clients/${clientId()}/super-clients`;
			api(path, { method: accountId ? 'PATCH' : 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(i18n.brandSaved || 'Brand saved.');
					closeModal($('#etwa-sc-brand-modal'));
					loadHubTab($app().data('tab') || 'brands').catch((err) => ui.error(err.message));
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn, false));
		});

		$('#etwa-sc-request-form').on('submit', function (e) {
			e.preventDefault();
			const $form = $(this);
			const $btn = $('#etwa-sc-request-submit');
			const requestId = parseInt($form.find('[name="id"]').val(), 10);
			const validation = validateRequestForm($form);
			if (!validation.valid) {
				scrollToFirstError($form);
				return;
			}
			const payload = {
				brand_id: parseInt($form.find('[name="brand_id"]').val(), 10),
				request_type: $form.find('[name="request_type"]').val(),
				service_category: validation.category,
				service: validation.service,
				status: $form.find('[name="status"]').val() || 'pending',
			};
			if (canCompleteSubmission()) {
				payload.contact_name = $form.find('[name="contact_name"]').val();
				payload.contact_email = $form.find('[name="contact_email"]').val();
				payload.contact_phone = $form.find('[name="contact_phone"]').val();
				payload.invoice_number = $form.find('[name="invoice_number"]').val();
			}
			if ($form.data('mode') === 'submission' && payload.status === 'pending') {
				payload.status = 'published';
			}
			ui.setButtonLoading($btn, true, i18n.saving);
			const path = requestId ? `super-client-requests/${requestId}` : `clients/${clientId()}/super-client-requests`;
			api(path, { method: requestId ? 'PATCH' : 'POST', body: JSON.stringify(payload) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(i18n.requestSaved || 'Request saved.');
					closeModal($('#etwa-sc-request-modal'));
					loadHubTab($app().data('tab') || 'requests').catch((err) => ui.error(err.message));
					refreshProductRequestBadges();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn, false));
		});

		$(document).on('click', '.etwa-sc-view-request', function () {
			const id = parseInt($(this).data('id'), 10);
			const row = requestsById[id];
			if (row) {
				openRequestDetailModal(row);
				return;
			}
			api(`super-client-requests/${id}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					requestsById[id] = res.data;
					openRequestDetailModal(res.data);
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-sc-request-detail-edit').on('click', function () {
			const row = detailRequestId ? requestsById[detailRequestId] : null;
			closeModal($('#etwa-sc-request-detail-modal'));
			if (row) {
				loadBrands().then(() => openRequestModal(row)).catch((err) => ui.error(err.message));
				return;
			}
			if (!detailRequestId) return;
			api(`super-client-requests/${detailRequestId}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					return loadBrands().then(() => openRequestModal(res.data));
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-sc-request-detail-submit').on('click', function () {
			const row = detailRequestId ? requestsById[detailRequestId] : null;
			closeModal($('#etwa-sc-request-detail-modal'));
			const openSubmission = (request) => loadBrands().then(() => openRequestModal(request, 'submission'));
			if (row) {
				openSubmission(row).catch((err) => ui.error(err.message));
				return;
			}
			if (!detailRequestId) return;
			api(`super-client-requests/${detailRequestId}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					requestsById[detailRequestId] = res.data;
					return openSubmission(res.data);
				})
				.catch((err) => ui.error(err.message));
		});

		$(document).on('click', '.etwa-sc-edit-request', function () {
			const id = parseInt($(this).data('id'), 10);
			const cached = requestsById[id];
			if (cached) {
				loadBrands().then(() => openRequestModal(cached)).catch((err) => ui.error(err.message));
				return;
			}
			api(`super-client-requests/${id}`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					requestsById[id] = res.data;
					return loadBrands().then(() => openRequestModal(res.data));
				})
				.catch((err) => ui.error(err.message));
		});

		$(document).on('click', '.etwa-sc-delete-request', function () {
			const id = parseInt($(this).data('id'), 10);
			ui.confirm(i18n.confirmDeleteRequest || 'Delete this request?', i18n.confirmTitle).then((ok) => {
				if (!ok) return;
				api(`super-client-requests/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(i18n.requestDeleted || 'Request deleted.');
						delete requestsById[id];
						loadHubTab($app().data('tab') || 'requests').catch((err) => ui.error(err.message));
						refreshProductRequestBadges();
					})
					.catch((err) => ui.error(err.message));
			});
		});
	});
})(jQuery);
