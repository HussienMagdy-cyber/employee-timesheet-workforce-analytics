(function ($) {
	'use strict';

	const cfg = window.etwaApp || {};
	const ui = window.etwaUI || {};

	if (!cfg.canManage) {
		return;
	}

	const api = (path, options = {}) => window.etwaAPI.api(path, options);

	const $modal = $('#etwa-client-edit-modal');
	const $projectsModal = $('#etwa-projects-modal');
	const $convertModal = $('#etwa-convert-client-modal');

	function openModal($el) {
		$el.removeAttr('hidden').attr('aria-hidden', 'false');
		$('body').addClass('etwa-modal-open');
	}

	function closeModal($el) {
		($el || $modal).attr('hidden', 'hidden').attr('aria-hidden', 'true');
		if (!$('.etwa-modal:not([hidden])').length) {
			$('body').removeClass('etwa-modal-open');
		}
	}

	function loadProjects(clientId) {
		const $body = $('#etwa-projects-body');
		$body.html(`<tr><td colspan="3">${cfg.i18n?.loading || 'Loading…'}</td></tr>`);
		return api(`clients/${clientId}/projects?include_archived=1`).then((res) => {
			if (!res.success) throw new Error(res.message);
			const rows = res.data || [];
			if (!rows.length) {
				$body.html(`<tr><td colspan="3">${cfg.i18n?.noProjects || 'No projects yet.'}</td></tr>`);
				return;
			}
			$body.html(
				rows
					.map(
						(p) => `<tr>
							<td>${p.name}</td>
							<td>${p.status}</td>
							<td>${p.status === 'active' ? `<button type="button" class="etwa-link etwa-archive-project" data-id="${p.id}">${cfg.i18n?.archive || 'Archive'}</button>` : ''}</td>
						</tr>`
					)
					.join('')
			);
		});
	}

	function rowData($row) {
		return {
			id: $row.data('id'),
			name: String($row.data('name') || ''),
			code: String($row.data('code') || ''),
			department: String($row.data('department') || 'General'),
			is_active: String($row.data('active')) === '1',
			client_type: String($row.data('client-type') || 'standard'),
		};
	}

	$(function () {
		if (!$('#etwa-clients-app').length) return;

		$('.etwa-workspace-tabs .etwa-tab').on('click', function () {
			const tab = $(this).data('tab');
			$('.etwa-workspace-tabs .etwa-tab').removeClass('active');
			$(this).addClass('active');
			$('#etwa-clients-app .etwa-tab-panel').removeClass('active');
			$(`#etwa-tab-${tab}`).addClass('active');
			if (tab === 'projects') {
				loadStandaloneProjects().catch((err) => ui.error(err.message));
			}
		});

		function parseParentValue(val) {
			const raw = String(val || '');
			if (raw.startsWith('p:')) {
				return { kind: 'project', id: parseInt(raw.slice(2), 10) };
			}
			return { kind: 'none', id: 0 };
		}

		function esc(text) {
			return $('<div>').text(String(text || '')).html();
		}

		function analyticsUrl(params) {
			const base = cfg.clientAnalyticsUrl;
			if (!base || !cfg.canViewAnalytics) {
				return '';
			}
			try {
				const url = new URL(base, window.location.origin);
				Object.keys(params || {}).forEach((key) => {
					if (params[key]) {
						url.searchParams.set(key, String(params[key]));
					}
				});
				return url.toString();
			} catch (e) {
				return '';
			}
		}

		function workspaceRow(opts) {
			const depth = opts.depth || 0;
			const rowClass = depth === 1 ? 'etwa-project-child' : 'etwa-project-parent';
			const nameHtml = opts.analyticsHref
				? `<a class="etwa-tree-name etwa-link" href="${esc(opts.analyticsHref)}">${esc(opts.name)}</a>`
				: `<span class="etwa-tree-name">${esc(opts.name)}</span>`;
			const addBtn = opts.addAction
				? `<button type="button" class="etwa-link ${opts.addAction}" data-parent="${esc(opts.parentValue || '')}" data-id="${opts.actionId || ''}" data-name="${esc(opts.name)}">${opts.addLabel}</button>`
				: '';
			const hoursBtn = opts.analyticsHref
				? `<a class="etwa-link" href="${esc(opts.analyticsHref)}">${cfg.i18n?.viewHours || 'View hours'}</a>`
				: '';
			const archive = opts.archiveId
				? `<button type="button" class="etwa-link etwa-archive-standalone-project" data-id="${opts.archiveId}">${cfg.i18n?.archive || 'Archive'}</button>`
				: '';
			const status = String(opts.status || '');
			const statusClass = /active/i.test(status) ? 'etwa-status-approved' : /inactive|archived/i.test(status) ? 'etwa-status-rejected' : 'etwa-status-draft';
			return `<tr class="${rowClass}">
				<td>${nameHtml}</td>
				<td><span class="etwa-type-pill etwa-type-${esc(opts.typeKind || 'item')}">${esc(opts.type)}</span></td>
				<td><span class="etwa-status ${statusClass}">${esc(status)}</span></td>
				<td class="etwa-actions-cell">${hoursBtn}${addBtn}${archive}</td>
			</tr>`;
		}

		function fillParentProjectOptions(standaloneParents) {
			const $group = $('#etwa-workspace-parent-projects');
			const current = $('#etwa-workspace-parent').val();
			$group.empty();
			(standaloneParents || []).forEach((parent) => {
				if (parent.status && parent.status !== 'active') return;
				$group.append($('<option></option>').val(`p:${parent.id}`).text(parent.name));
			});
			if (current) {
				$('#etwa-workspace-parent').val(current);
			}
		}

		function setAddHint() {
			const parent = parseParentValue($('#etwa-workspace-parent').val());
			const $hint = $('#etwa-workspace-add-hint');
			const $btn = $('#etwa-workspace-add-btn');
			if (parent.kind === 'project') {
				const label = $('#etwa-workspace-parent option:selected').text();
				$hint.text((cfg.i18n?.addingSubProject || 'Adding a Sub-project under %s.').replace('%s', label));
				$btn.text(cfg.i18n?.addSubProject || 'Add Sub-project');
				return;
			}
			$hint.text(cfg.i18n?.addingParentProject || 'Creating a new Parent Project.');
			$btn.text(cfg.i18n?.addProject || 'Add Project');
		}

		function loadStandaloneProjects() {
			const $body = $('#etwa-standalone-projects-body');
			if (!$body.length) return Promise.resolve();
			$body.html(`<tr><td colspan="4">${cfg.i18n?.loading || 'Loading…'}</td></tr>`);

			return api('projects?include_archived=1').then((res) => {
				if (!res.success) throw new Error(res.message);
				const standalone = res.data || [];
				fillParentProjectOptions(standalone);
				const html = [];
				standalone.forEach((parent) => {
					html.push(
						workspaceRow({
							name: parent.name,
							type: cfg.i18n?.parentProject || 'Parent Project',
							typeKind: 'parent',
							status: parent.status,
							parentValue: `p:${parent.id}`,
							actionId: parent.id,
							addAction: parent.status === 'active' ? 'etwa-add-under-parent' : '',
							addLabel: cfg.i18n?.addSubProject || 'Add Sub-project',
							archiveId: parent.status === 'active' ? parent.id : 0,
							analyticsHref: analyticsUrl({ project_id: parent.id }),
						})
					);
					(parent.children || []).forEach((child) => {
						html.push(
							workspaceRow({
								depth: 1,
								name: child.name,
								type: cfg.i18n?.subProject || 'Sub-project',
								typeKind: 'sub',
								status: child.status,
								archiveId: child.status === 'active' ? child.id : 0,
								analyticsHref: analyticsUrl({ project_id: child.id }),
							})
						);
					});
				});
				if (!html.length) {
					$body.html(`<tr><td colspan="4">${cfg.i18n?.noProjects || 'No projects yet.'}</td></tr>`);
					return;
				}
				$body.html(html.join(''));
				setAddHint();
			});
		}

		function setParentAndFocus(parentValue) {
			$('#etwa-workspace-parent').val(parentValue);
			setAddHint();
			$('#etwa-standalone-project-form input[name="name"]').trigger('focus');
		}

		function createWorkspaceItem(data) {
			const payload = { name: data.name, description: data.description || '' };
			const parent = parseParentValue(data.workspace_parent);
			if (parent.kind === 'project') {
				payload.parent_project_id = parent.id;
			}
			return api('projects', { method: 'POST', body: JSON.stringify(payload) });
		}

		$('#etwa-standalone-project-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			createWorkspaceItem(data)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					const keptParent = data.workspace_parent || '';
					this.reset();
					$('#etwa-workspace-parent').val(keptParent);
					ui.success(cfg.i18n?.projectSaved || 'Project saved.');
					return loadStandaloneProjects();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-workspace-parent').on('change', setAddHint);

		$('#etwa-standalone-projects-body').on('click', '.etwa-add-under-parent', function () {
			setParentAndFocus($(this).attr('data-parent'));
		});

		$('#etwa-standalone-projects-body').on('click', '.etwa-archive-standalone-project', function () {
			const id = $(this).data('id');
			ui.confirm(cfg.i18n?.confirmArchiveProject || 'Archive this project?', cfg.i18n?.archive || 'Archive').then((ok) => {
				if (!ok) return;
				api(`projects/${id}/archive`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						return loadStandaloneProjects();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-client-form').on('submit', function (e) {
			e.preventDefault();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api('clients', {
				method: 'POST',
				body: JSON.stringify(data),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.clientSaved || 'Client saved.');
					location.reload();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-clients-body').on('click', '.etwa-edit-client', function () {
			const data = rowData($(this).closest('tr'));
			$('#etwa-client-edit-id').val(data.id);
			$('#etwa-client-edit-name').val(data.name);
			$('#etwa-client-edit-code').val(data.code);
			$('#etwa-client-edit-department').val(data.department);
			$('#etwa-client-edit-active').prop('checked', data.is_active);
			openModal($modal);
		});

		$('#etwa-client-edit-form').on('submit', function (e) {
			e.preventDefault();
			const id = $('#etwa-client-edit-id').val();
			const $btn = $(this).find('[type="submit"]');
			const payload = {
				name: $('#etwa-client-edit-name').val().trim(),
				code: $('#etwa-client-edit-code').val().trim(),
				department: $('#etwa-client-edit-department').val().trim() || 'General',
				is_active: $('#etwa-client-edit-active').is(':checked'),
			};

			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);

			api(`clients/${id}`, {
				method: 'PUT',
				body: JSON.stringify(payload),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					ui.success(cfg.i18n?.clientUpdated || 'Client updated.');
					location.reload();
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-clients-body').on('click', '.etwa-promote-super-client', function () {
			const id = $(this).data('client-id');
			ui.confirm(cfg.i18n?.confirmPromoteSuper || 'Promote this client to a Product?', cfg.i18n?.confirmTitle || 'Please confirm').then((ok) => {
				if (!ok) return;
				api(`clients/${id}/promote-super`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.promotedSuper || 'Client promoted to Product.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-clients-body').on('click', '.etwa-demote-super-client', function () {
			const id = $(this).data('client-id');
			ui.confirm(cfg.i18n?.confirmDemoteSuper || 'Demote this client to a standard client? Child data will be preserved.', cfg.i18n?.confirmTitle || 'Please confirm').then((ok) => {
				if (!ok) return;
				api(`clients/${id}/demote-super`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.demotedSuper || 'Client demoted to standard client.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-clients-body').on('click', '.etwa-deactivate-client', function () {
			const id = $(this).closest('tr').data('id');
			ui.confirm(
				cfg.i18n?.confirmDeactivateClient || 'Deactivate this client? It will be hidden from the timesheet dropdown.',
				cfg.i18n?.deactivate || 'Deactivate'
			).then((ok) => {
				if (!ok) return;
				api(`clients/${id}/deactivate`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.clientDeactivated || 'Client deactivated.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$('#etwa-clients-body').on('click', '.etwa-delete-client', function () {
			const id = $(this).closest('tr').data('id');
			ui.confirm(
				cfg.i18n?.confirmDeleteClient || 'Permanently delete this client? This cannot be undone.',
				cfg.i18n?.delete || 'Delete'
			).then((ok) => {
				if (!ok) return;
				api(`clients/${id}`, { method: 'DELETE' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.clientDeleted || 'Client deleted.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$modal.on('click', '.etwa-modal-close', () => closeModal($modal));
		$modal.on('click', function (e) {
			if (e.target === this) closeModal($modal);
		});

		$('#etwa-clients-body').on('click', '.etwa-manage-projects', function () {
			const clientId = $(this).data('client-id');
			const clientName = $(this).data('client-name');
			$('#etwa-project-client-id').val(clientId);
			$('#etwa-projects-client-label').text(clientName);
			$('#etwa-projects-title').text(`${cfg.i18n?.projects || 'Projects'} — ${clientName}`);
			openModal($projectsModal);
			loadProjects(clientId).catch((err) => ui.error(err.message));
		});

		$('#etwa-project-form').on('submit', function (e) {
			e.preventDefault();
			const clientId = $('#etwa-project-client-id').val();
			const data = Object.fromEntries(new FormData(this).entries());
			const $btn = $(this).find('[type="submit"]');
			ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
			api(`clients/${clientId}/projects`, { method: 'POST', body: JSON.stringify(data) })
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					this.reset();
					$('#etwa-project-client-id').val(clientId);
					ui.success(cfg.i18n?.projectSaved || 'Project saved.');
					return loadProjects(clientId);
				})
				.catch((err) => ui.error(err.message))
				.finally(() => ui.setButtonLoading($btn[0], false));
		});

		$('#etwa-projects-body').on('click', '.etwa-archive-project', function () {
			const id = $(this).data('id');
			const clientId = $('#etwa-project-client-id').val();
			ui.confirm(cfg.i18n?.confirmArchiveProject || 'Archive this project?', cfg.i18n?.archive || 'Archive').then((ok) => {
				if (!ok) return;
				api(`projects/${id}/archive`, { method: 'POST' })
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						return loadProjects(clientId);
					})
					.catch((err) => ui.error(err.message));
			});
		});

		$projectsModal.on('click', '.etwa-modal-close', () => closeModal($projectsModal));
		$projectsModal.on('click', function (e) {
			if (e.target === this) closeModal($projectsModal);
		});

		$('#etwa-merge-preview').on('click', function () {
			const primaryId = parseInt($('#etwa-merge-primary').val(), 10);
			const mergeIds = $('#etwa-merge-ids').val() || [];
			if (!primaryId || !mergeIds.length) {
				ui.error(cfg.i18n?.mergeSelectionRequired || 'Select a primary client and clients to merge.');
				return;
			}
			api('clients/merge/preview', {
				method: 'POST',
				body: JSON.stringify({ primary_id: primaryId, merge_ids: mergeIds.map(Number) }),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					const lines = (res.data.merge || [])
						.map((m) => `${m.client_name}: ${m.entries} entries, ${m.scopes} scopes, ${m.projects} projects`)
						.join('<br>');
					$('#etwa-merge-preview-output')
						.html(`<strong>${res.data.primary?.name || ''}</strong> ← ${lines}`)
						.removeAttr('hidden');
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-client-merge-form').on('submit', function (e) {
			e.preventDefault();
			const primaryId = parseInt($('#etwa-merge-primary').val(), 10);
			const mergeIds = ($('#etwa-merge-ids').val() || []).map(Number);
			const primaryName = $('#etwa-merge-primary option:selected').text();
			const mergeNames = ($('#etwa-merge-ids option:selected')
				.map(function () {
					return $(this).text();
				})
				.get() || []
			).join(', ');

			ui.confirm(
				`${cfg.i18n?.confirmMerge || 'Merge these clients into the primary client?'}\n\nPrimary: ${primaryName}\nMerge: ${mergeNames}`,
				cfg.i18n?.merge || 'Merge'
			).then((ok) => {
				if (!ok) return;
				api('clients/merge', {
					method: 'POST',
					body: JSON.stringify({ primary_id: primaryId, merge_ids: mergeIds }),
				})
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.mergeComplete || 'Clients merged successfully.');
						location.reload();
					})
					.catch((err) => ui.error(err.message));
			});
		});

		function resetConvertPreview() {
			$('#etwa-convert-preview-output').attr('hidden', 'hidden').empty();
			$('#etwa-convert-submit').prop('disabled', true);
		}

		function parseConvertParent(value) {
			const raw = String(value || '');
			if (raw.indexOf('p:') === 0) {
				return {
					parent_project_id: parseInt(raw.slice(2), 10) || 0,
					parent_client_id: 0,
				};
			}
			return {
				parent_project_id: 0,
				parent_client_id: parseInt(raw, 10) || 0,
			};
		}

		function convertNewProjectLabel(isStandaloneParent) {
			if (isStandaloneParent) {
				return cfg.i18n?.convertNewSubProjectOption || 'New sub-project (use client name)';
			}
			return cfg.i18n?.convertNewProjectOption || 'New project (use client name)';
		}

		function convertParentProjectLabel(name) {
			const tpl = cfg.i18n?.convertParentProjectOption || '%s (parent project)';
			return tpl.replace('%s', name);
		}

		function resetConvertProjectSelect(disabled, isStandaloneParent) {
			$('#etwa-convert-project-id')
				.html(`<option value="">${convertNewProjectLabel(!!isStandaloneParent)}</option>`)
				.prop('disabled', !!disabled)
				.val('');
		}

		function convertDestinationPayload() {
			const parent = parseConvertParent($('#etwa-convert-parent-id').val());
			const destId = parseInt($('#etwa-convert-project-id').val(), 10) || 0;
			const payload = {};
			if (parent.parent_project_id) {
				payload.parent_project_id = parent.parent_project_id;
			} else {
				payload.parent_client_id = parent.parent_client_id;
			}
			if (destId > 0) {
				payload.destination_project_id = destId;
			}
			return payload;
		}

		function loadConvertProjects(parentValue) {
			resetConvertPreview();
			const parent = parseConvertParent(parentValue);
			if (!parent.parent_client_id && !parent.parent_project_id) {
				resetConvertProjectSelect(true);
				return Promise.resolve();
			}
			const $select = $('#etwa-convert-project-id');
			$select
				.prop('disabled', true)
				.html(`<option value="">${cfg.i18n?.loading || 'Loading…'}</option>`);

			if (parent.parent_project_id) {
				return api('projects')
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						const tree = res.data || [];
						const parentNode = tree.find(
							(p) => parseInt(p.id, 10) === parent.parent_project_id
						);
						const children = (parentNode?.children || []).filter(
							(p) => p.status === 'active'
						);
						let html = `<option value="">${convertNewProjectLabel(true)}</option>`;
						if (parentNode) {
							html += `<option value="${parentNode.id}">${convertParentProjectLabel(parentNode.name)}</option>`;
						}
						children.forEach((p) => {
							html += `<option value="${p.id}">${p.name}</option>`;
						});
						$select.html(html).prop('disabled', false).val('');
					})
					.catch((err) => {
						resetConvertProjectSelect(false, true);
						ui.error(err.message);
					});
			}

			return api(`clients/${parent.parent_client_id}/projects`)
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					const projects = (res.data || []).filter((p) => p.status === 'active');
					let html = `<option value="">${convertNewProjectLabel(false)}</option>`;
					projects.forEach((p) => {
						html += `<option value="${p.id}">${p.name}</option>`;
					});
					$select.html(html).prop('disabled', false).val('');
				})
				.catch((err) => {
					resetConvertProjectSelect(false);
					ui.error(err.message);
				});
		}

		function renderConvertPreview(data) {
			const counts = data.counts || {};
			const childProjects = (data.child_projects || [])
				.map((p) => p.name)
				.join(', ');
			const conflicts = (data.duplicate_conflicts || [])
				.map((c) => `${c.name}: ${c.reason}`)
				.join('<br>');
			const warnings = (data.cannot_migrate || [])
				.map((w) => {
					const suffix = w.count != null ? ` (${w.count})` : '';
					return `${w.note}${suffix}`;
				})
				.join('<br>');
			const projectLabel = data.uses_existing_project
				? cfg.i18n?.convertPreviewExistingProject || 'Existing project'
				: cfg.i18n?.convertPreviewProject || 'New project';
			const parentName = data.parent_project?.name || data.parent_client?.name || '';

			const lines = [
				`<strong>${cfg.i18n?.convertPreviewSource || 'Source'}:</strong> ${data.source_client?.name || ''}`,
				`<strong>${cfg.i18n?.convertPreviewParent || 'Parent'}:</strong> ${parentName}`,
				`<strong>${projectLabel}:</strong> ${data.new_project_name || ''}`,
				`${counts.entries || 0} ${cfg.i18n?.convertEntries || 'entries'} (${counts.entries_direct || 0} ${cfg.i18n?.convertDirect || 'social media'}, ${counts.entries_on_child_projects || 0} ${cfg.i18n?.convertOnChildProjects || 'on child projects'})`,
				`${counts.scopes || 0} ${cfg.i18n?.convertScopes || 'scopes'}`,
				`${counts.allocations || 0} ${cfg.i18n?.convertAllocations || 'allocations (preserved via scopes)'}`,
				`${counts.child_projects || 0} ${cfg.i18n?.convertChildProjects || 'child projects will become siblings under the parent'}`,
			];

			if (childProjects) {
				lines.push(`${cfg.i18n?.convertChildProjectNames || 'Child projects'}: ${childProjects}`);
			}
			if (conflicts) {
				lines.push(`<strong>${cfg.i18n?.convertConflicts || 'Conflicts'}:</strong><br>${conflicts}`);
			}
			if (warnings) {
				lines.push(`<strong>${cfg.i18n?.convertWarnings || 'Notes'}:</strong><br>${warnings}`);
			}

			$('#etwa-convert-preview-output')
				.html(lines.join('<br>'))
				.removeAttr('hidden');
			$('#etwa-convert-submit').prop('disabled', !data.can_convert);
		}

		$('#etwa-clients-body').on('click', '.etwa-convert-client', function () {
			const clientId = $(this).data('client-id');
			const clientName = $(this).data('client-name');
			$('#etwa-convert-source-id').val(clientId);
			$('#etwa-convert-source-label').text(
				`${cfg.i18n?.convertSourceLabel || 'Converting client:'} ${clientName}`
			);
			$('#etwa-convert-parent-id option').prop('disabled', false);
			$(`#etwa-convert-parent-id option[value="${clientId}"]`).prop('disabled', true);
			$('#etwa-convert-parent-id').val('');
			resetConvertProjectSelect(true);
			resetConvertPreview();
			openModal($convertModal);
		});

		$('#etwa-convert-parent-id').on('change', function () {
			loadConvertProjects($(this).val());
		});
		$('#etwa-convert-project-id').on('change', resetConvertPreview);

		$('#etwa-convert-preview').on('click', function () {
			const sourceId = parseInt($('#etwa-convert-source-id').val(), 10);
			const parent = parseConvertParent($('#etwa-convert-parent-id').val());
			if (!sourceId || (!parent.parent_client_id && !parent.parent_project_id)) {
				ui.error(cfg.i18n?.convertSelectionRequired || 'Select a parent client or parent project.');
				return;
			}
			api(`clients/${sourceId}/convert-to-project/preview`, {
				method: 'POST',
				body: JSON.stringify(convertDestinationPayload()),
			})
				.then((res) => {
					if (!res.success) throw new Error(res.message);
					renderConvertPreview(res.data || {});
				})
				.catch((err) => ui.error(err.message));
		});

		$('#etwa-convert-client-form').on('submit', function (e) {
			e.preventDefault();
			const sourceId = parseInt($('#etwa-convert-source-id').val(), 10);
			const parent = parseConvertParent($('#etwa-convert-parent-id').val());
			const sourceName = $('#etwa-convert-source-label').text().replace(/^.*:\s*/, '');
			const parentName = $('#etwa-convert-parent-id option:selected').text();
			const destId = parseInt($('#etwa-convert-project-id').val(), 10) || 0;
			const destName = destId
				? $('#etwa-convert-project-id option:selected').text()
				: convertNewProjectLabel(!!parent.parent_project_id);

			if ($('#etwa-convert-submit').prop('disabled')) {
				ui.error(cfg.i18n?.convertPreviewRequired || 'Preview the conversion and resolve conflicts first.');
				return;
			}

			ui.confirm(
				`${cfg.i18n?.confirmConvert || 'Convert this client into a project under the selected parent?'}\n\nSource: ${sourceName}\nParent: ${parentName}\nProject: ${destName}`,
				cfg.i18n?.convertToProject || 'Convert to Project'
			).then((ok) => {
				if (!ok) return;
				const $btn = $('#etwa-convert-submit');
				ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
				api(`clients/${sourceId}/convert-to-project`, {
					method: 'POST',
					body: JSON.stringify(convertDestinationPayload()),
				})
					.then((res) => {
						if (!res.success) throw new Error(res.message);
						ui.success(cfg.i18n?.convertComplete || 'Client converted to project successfully.');
						location.reload();
					})
					.catch((err) => ui.error(err.message))
					.finally(() => ui.setButtonLoading($btn[0], false));
			});
		});

		$convertModal.on('click', '.etwa-modal-close', () => closeModal($convertModal));
		$convertModal.on('click', function (e) {
			if (e.target === this) closeModal($convertModal);
		});
	});
})(jQuery);
