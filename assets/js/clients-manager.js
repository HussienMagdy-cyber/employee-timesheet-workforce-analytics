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
		};
	}

	$(function () {
		if (!$('#etwa-clients-app').length) return;

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

			const lines = [
				`<strong>${cfg.i18n?.convertPreviewSource || 'Source'}:</strong> ${data.source_client?.name || ''}`,
				`<strong>${cfg.i18n?.convertPreviewParent || 'Parent'}:</strong> ${data.parent_client?.name || ''}`,
				`<strong>${cfg.i18n?.convertPreviewProject || 'New project'}:</strong> ${data.new_project_name || ''}`,
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
			resetConvertPreview();
			openModal($convertModal);
		});

		$('#etwa-convert-parent-id').on('change', resetConvertPreview);

		$('#etwa-convert-preview').on('click', function () {
			const sourceId = parseInt($('#etwa-convert-source-id').val(), 10);
			const parentId = parseInt($('#etwa-convert-parent-id').val(), 10);
			if (!sourceId || !parentId) {
				ui.error(cfg.i18n?.convertSelectionRequired || 'Select a parent client.');
				return;
			}
			api(`clients/${sourceId}/convert-to-project/preview`, {
				method: 'POST',
				body: JSON.stringify({ parent_client_id: parentId }),
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
			const parentId = parseInt($('#etwa-convert-parent-id').val(), 10);
			const sourceName = $('#etwa-convert-source-label').text().replace(/^.*:\s*/, '');
			const parentName = $('#etwa-convert-parent-id option:selected').text();

			if ($('#etwa-convert-submit').prop('disabled')) {
				ui.error(cfg.i18n?.convertPreviewRequired || 'Preview the conversion and resolve conflicts first.');
				return;
			}

			ui.confirm(
				`${cfg.i18n?.confirmConvert || 'Convert this client into a project under the parent client?'}\n\nSource: ${sourceName}\nParent: ${parentName}`,
				cfg.i18n?.convertToProject || 'Convert to Project'
			).then((ok) => {
				if (!ok) return;
				const $btn = $('#etwa-convert-submit');
				ui.setButtonLoading($btn[0], true, cfg.i18n?.saving);
				api(`clients/${sourceId}/convert-to-project`, {
					method: 'POST',
					body: JSON.stringify({ parent_client_id: parentId }),
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
