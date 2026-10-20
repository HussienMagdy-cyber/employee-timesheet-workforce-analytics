<?php
/**
 * Frontend workspace: Internal, Projects, General, and Clients.
 *
 * @var \ETWA\Models\Client[] $clients
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\AuthHelper;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ProjectRepository;
use ETWA\Repositories\ProspectRepository;

$internal_clients = array();
$general_clients  = array();
$work_clients     = array();
foreach ( $clients as $client ) {
	$code = strtoupper( $client->code );
	if ( in_array( $code, ClientRepository::internal_codes(), true ) ) {
		$internal_clients[] = $client;
	} elseif ( in_array( $code, ClientRepository::general_codes(), true ) ) {
		$general_clients[] = $client;
	} elseif ( ! in_array( $code, ClientRepository::system_codes(), true ) ) {
		$work_clients[] = $client;
	}
}

$standalone_parents = array_values(
	array_filter(
		( new ProjectRepository() )->list_standalone( true ),
		static fn( $project ) => ! $project->parent_project_id
	)
);

$converted_from = ( new ProspectRepository() )->map_by_converted_clients(
	array_map( static fn( $client ) => (int) $client->id, $work_clients )
);
$prospects_base = PortalNav::view_url( AuthHelper::portal_url(), 'prospects' );
$super_clients_base = PortalNav::view_url( AuthHelper::portal_url(), 'super-clients' );

/**
 * @param \ETWA\Models\Client[] $list
 * @param bool                  $show_actions
 */
$render_client_rows = static function ( array $list, bool $show_actions ) use ( $converted_from, $prospects_base, $super_clients_base ): void {
	foreach ( $list as $client ) {
		$is_system = in_array( strtoupper( $client->code ), ClientRepository::system_codes(), true );
		$from      = $converted_from[ (int) $client->id ] ?? null;
		?>
		<tr
			data-id="<?php echo esc_attr( (string) $client->id ); ?>"
			data-system="<?php echo $is_system ? '1' : '0'; ?>"
			data-name="<?php echo esc_attr( $client->name ); ?>"
			data-code="<?php echo esc_attr( $client->code ); ?>"
			data-department="<?php echo esc_attr( $client->department ); ?>"
			data-active="<?php echo $client->is_active ? '1' : '0'; ?>"
			data-client-type="<?php echo esc_attr( $client->client_type ); ?>"
		>
			<td>
				<?php echo esc_html( $client->name ); ?>
				<?php if ( $client->is_super() ) : ?>
					<div><span class="etwa-badge etwa-badge-super"><?php esc_html_e( 'PRODUCT', 'etwa' ); ?></span></div>
				<?php endif; ?>
				<?php if ( $from ) : ?>
					<div class="etwa-muted">
						<a class="etwa-link" href="<?php echo esc_url( add_query_arg( 'prospect_id', (int) $from->id, $prospects_base ) ); ?>">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: prospect name */
									__( 'Converted from %s', 'etwa' ),
									$from->name
								)
							);
							?>
						</a>
					</div>
				<?php endif; ?>
			</td>
			<td><code><?php echo esc_html( $client->code ); ?></code></td>
			<td><?php echo esc_html( $client->department ); ?></td>
			<td>
				<?php if ( $is_system ) : ?>
					<span class="etwa-badge"><?php esc_html_e( 'System', 'etwa' ); ?></span>
				<?php elseif ( $client->is_active ) : ?>
					<span class="etwa-status etwa-status-approved"><?php esc_html_e( 'Active', 'etwa' ); ?></span>
				<?php else : ?>
					<span class="etwa-status etwa-status-rejected"><?php esc_html_e( 'Inactive', 'etwa' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="etwa-actions-cell">
				<?php if ( $show_actions && ! $is_system ) : ?>
					<?php if ( $client->is_super() ) : ?>
						<a class="etwa-link" href="<?php echo esc_url( add_query_arg( 'client_id', (int) $client->id, $super_clients_base ) ); ?>"><?php esc_html_e( 'Manage', 'etwa' ); ?></a>
						<button type="button" class="etwa-link etwa-demote-super-client" data-client-id="<?php echo esc_attr( (string) $client->id ); ?>" data-client-name="<?php echo esc_attr( $client->name ); ?>"><?php esc_html_e( 'Demote Product', 'etwa' ); ?></button>
					<?php else : ?>
						<button type="button" class="etwa-link etwa-promote-super-client" data-client-id="<?php echo esc_attr( (string) $client->id ); ?>" data-client-name="<?php echo esc_attr( $client->name ); ?>"><?php esc_html_e( 'Promote to Product', 'etwa' ); ?></button>
					<?php endif; ?>
					<button type="button" class="etwa-link etwa-manage-projects" data-client-id="<?php echo esc_attr( (string) $client->id ); ?>" data-client-name="<?php echo esc_attr( $client->name ); ?>"><?php esc_html_e( 'Projects', 'etwa' ); ?></button>
					<?php if ( $client->is_active && ! $client->is_super() ) : ?>
						<button type="button" class="etwa-link etwa-convert-client" data-client-id="<?php echo esc_attr( (string) $client->id ); ?>" data-client-name="<?php echo esc_attr( $client->name ); ?>"><?php esc_html_e( 'Convert to Project', 'etwa' ); ?></button>
					<?php endif; ?>
					<button type="button" class="etwa-link etwa-edit-client"><?php esc_html_e( 'Edit', 'etwa' ); ?></button>
					<?php if ( $client->is_active && ! $client->is_super() ) : ?>
						<button type="button" class="etwa-link etwa-deactivate-client"><?php esc_html_e( 'Deactivate', 'etwa' ); ?></button>
					<?php endif; ?>
					<?php if ( ! $client->is_super() ) : ?>
						<button type="button" class="etwa-link etwa-delete-client"><?php esc_html_e( 'Delete', 'etwa' ); ?></button>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
};
?>
<div class="etwa-page-shell etwa-app etwa-portal etwa-workspace" id="etwa-clients-app">
	<div class="etwa-hero">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'Settings', 'etwa' ); ?></p>
			<h1><?php esc_html_e( 'Workspace', 'etwa' ); ?></h1>
			<p class="etwa-subtitle"><?php esc_html_e( 'Internal categories, projects under clients or standalone parents, general work, and client records.', 'etwa' ); ?></p>
		</div>
	</div>

	<div class="etwa-tabs etwa-workspace-tabs" role="tablist">
		<button type="button" class="etwa-tab active" data-tab="internal"><?php esc_html_e( 'Internal', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="projects"><?php esc_html_e( 'Projects', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="general"><?php esc_html_e( 'General', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="clients"><?php esc_html_e( 'Clients', 'etwa' ); ?></button>
	</div>

	<div class="etwa-tab-panel active" id="etwa-tab-internal" data-tab-panel="internal">
		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'Internal', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'System categories for internal time. These cannot be removed.', 'etwa' ); ?></p>
			</div>
			<div class="etwa-table-wrap">
			<table class="etwa-table etwa-clients-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Code', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php $render_client_rows( $internal_clients, false ); ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>
	<div class="etwa-tab-panel" id="etwa-tab-projects" data-tab-panel="projects">
		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'Projects', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'Create a Parent Project, add Sub-projects, and use Convert to Project to move a Client under a Parent as a sub-project.', 'etwa' ); ?></p>
			</div>
			<div class="etwa-workspace-layout">
				<section class="etwa-workspace-card">
					<h3><?php esc_html_e( 'Create project', 'etwa' ); ?></h3>
					<form id="etwa-standalone-project-form" class="etwa-form-grid">
						<label><?php esc_html_e( 'Parent Project', 'etwa' ); ?>
							<select name="workspace_parent" id="etwa-workspace-parent">
								<option value=""><?php esc_html_e( 'None — new standalone parent project', 'etwa' ); ?></option>
								<optgroup label="<?php esc_attr_e( 'Standalone parent projects', 'etwa' ); ?>" id="etwa-workspace-parent-projects"></optgroup>
							</select>
						</label>
						<label><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" required placeholder="<?php esc_attr_e( 'e.g. ETWA Development or Backend', 'etwa' ); ?>" /></label>
						<label class="etwa-span-2"><?php esc_html_e( 'Description', 'etwa' ); ?><textarea name="description" rows="2"></textarea></label>
						<p class="etwa-muted etwa-span-2 etwa-workspace-hint" id="etwa-workspace-add-hint"></p>
						<div class="etwa-span-2 etwa-form-actions">
							<button type="submit" class="etwa-btn etwa-btn-primary" id="etwa-workspace-add-btn"><?php esc_html_e( 'Add Project', 'etwa' ); ?></button>
						</div>
					</form>
				</section>
				<section class="etwa-workspace-card etwa-workspace-tree">
					<h3><?php esc_html_e( 'Hierarchy', 'etwa' ); ?></h3>
					<div class="etwa-table-wrap">
						<table class="etwa-table etwa-workspace-tree-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Type', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
								</tr>
							</thead>
							<tbody id="etwa-standalone-projects-body">
								<tr><td colspan="4"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</section>
			</div>
		</div>
	</div>

	<div class="etwa-tab-panel" id="etwa-tab-general" data-tab-panel="general">
		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'General', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'General and Moderation categories. These cannot be removed.', 'etwa' ); ?></p>
			</div>
			<div class="etwa-table-wrap">
			<table class="etwa-table etwa-clients-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Code', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php $render_client_rows( $general_clients, false ); ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>

	<div class="etwa-tab-panel" id="etwa-tab-clients" data-tab-panel="clients">
		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'Add Client', 'etwa' ); ?></h2>
			</div>
			<form id="etwa-client-form" class="etwa-form-grid">
				<label><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" required placeholder="<?php esc_attr_e( 'e.g. SDM', 'etwa' ); ?>" /></label>
				<label><?php esc_html_e( 'Code', 'etwa' ); ?><input type="text" name="code" required placeholder="<?php esc_attr_e( 'e.g. SDM', 'etwa' ); ?>" /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Department', 'etwa' ); ?><input type="text" name="department" value="General" /></label>
				<div class="etwa-span-2 etwa-form-actions">
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Add Client', 'etwa' ); ?></button>
				</div>
			</form>
		</div>

		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'Merge Clients', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'Reassign all related data to a primary client and deactivate merged clients.', 'etwa' ); ?></p>
			</div>
			<form id="etwa-client-merge-form" class="etwa-form-grid">
				<label><?php esc_html_e( 'Primary Client', 'etwa' ); ?>
					<select name="primary_id" id="etwa-merge-primary" required>
						<option value=""><?php esc_html_e( 'Select…', 'etwa' ); ?></option>
						<?php foreach ( $work_clients as $client ) : ?>
							<?php if ( ! $client->is_active ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="etwa-span-2"><?php esc_html_e( 'Clients to Merge', 'etwa' ); ?>
					<select name="merge_ids[]" id="etwa-merge-ids" multiple size="5" required>
						<?php foreach ( $work_clients as $client ) : ?>
							<?php if ( ! $client->is_active ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?> (<?php echo esc_html( $client->code ); ?>)</option>
						<?php endforeach; ?>
					</select>
				</label>
				<div class="etwa-span-2 etwa-form-actions">
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-merge-preview"><?php esc_html_e( 'Preview Merge', 'etwa' ); ?></button>
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Merge Clients', 'etwa' ); ?></button>
				</div>
			</form>
			<div id="etwa-merge-preview-output" class="etwa-muted etwa-workspace-preview" hidden></div>
		</div>

		<div class="etwa-panel">
			<div class="etwa-panel-intro">
				<h2><?php esc_html_e( 'Clients', 'etwa' ); ?></h2>
			</div>
			<div class="etwa-table-wrap">
			<table class="etwa-table etwa-clients-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Code', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-clients-body">
					<?php $render_client_rows( $work_clients, true ); ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>

	<div class="etwa-modal" id="etwa-client-edit-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-client-edit-title">
		<div class="etwa-modal-content">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
			<h3 id="etwa-client-edit-title"><?php esc_html_e( 'Edit Client', 'etwa' ); ?></h3>
			<form id="etwa-client-edit-form" class="etwa-form-grid">
				<input type="hidden" name="id" id="etwa-client-edit-id" value="" />
				<label><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" id="etwa-client-edit-name" required /></label>
				<label><?php esc_html_e( 'Code', 'etwa' ); ?><input type="text" name="code" id="etwa-client-edit-code" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Department', 'etwa' ); ?><input type="text" name="department" id="etwa-client-edit-department" /></label>
				<label class="etwa-span-2 etwa-checkbox-label">
					<input type="checkbox" name="is_active" id="etwa-client-edit-active" value="1" />
					<?php esc_html_e( 'Active (shown in timesheet dropdown)', 'etwa' ); ?>
				</label>
				<div class="etwa-modal-actions etwa-span-2">
					<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Changes', 'etwa' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<div class="etwa-modal" id="etwa-projects-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-projects-title">
		<div class="etwa-modal-content etwa-modal-wide">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
			<h3 id="etwa-projects-title"><?php esc_html_e( 'Projects', 'etwa' ); ?></h3>
			<p class="etwa-muted" id="etwa-projects-client-label"></p>
			<form id="etwa-project-form" class="etwa-form-grid">
				<input type="hidden" name="client_id" id="etwa-project-client-id" value="" />
				<label><?php esc_html_e( 'Project Name', 'etwa' ); ?><input type="text" name="name" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Description', 'etwa' ); ?><textarea name="description" rows="2"></textarea></label>
				<div class="etwa-span-2">
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Add Project', 'etwa' ); ?></button>
				</div>
			</form>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-projects-body">
					<tr><td colspan="3"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="etwa-modal" id="etwa-convert-client-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-convert-client-title">
		<div class="etwa-modal-content etwa-modal-wide">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
			<h3 id="etwa-convert-client-title"><?php esc_html_e( 'Convert Client to Project', 'etwa' ); ?></h3>
			<p class="etwa-muted" id="etwa-convert-source-label"></p>
			<form id="etwa-convert-client-form" class="etwa-form-grid">
				<input type="hidden" name="source_client_id" id="etwa-convert-source-id" value="" />
				<label class="etwa-span-2"><?php esc_html_e( 'Parent', 'etwa' ); ?>
					<select name="parent_client_id" id="etwa-convert-parent-id" required>
						<option value=""><?php esc_html_e( 'Select…', 'etwa' ); ?></option>
						<?php if ( $standalone_parents ) : ?>
							<optgroup label="<?php esc_attr_e( 'Parent Projects', 'etwa' ); ?>">
								<?php foreach ( $standalone_parents as $project ) : ?>
									<option value="<?php echo esc_attr( 'p:' . (string) (int) $project->id ); ?>"><?php echo esc_html( $project->name ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
						<?php if ( $work_clients ) : ?>
							<optgroup label="<?php esc_attr_e( 'Clients', 'etwa' ); ?>">
								<?php foreach ( $work_clients as $client ) : ?>
									<?php if ( ! $client->is_active ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?> (<?php echo esc_html( $client->code ); ?>)</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</label>
				<label class="etwa-span-2"><?php esc_html_e( 'Project', 'etwa' ); ?>
					<select name="destination_project_id" id="etwa-convert-project-id" disabled>
						<option value=""><?php esc_html_e( 'New project (use client name)', 'etwa' ); ?></option>
					</select>
				</label>
				<div class="etwa-span-2">
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-convert-preview"><?php esc_html_e( 'Preview Conversion', 'etwa' ); ?></button>
					<button type="submit" class="etwa-btn etwa-btn-primary" id="etwa-convert-submit" disabled><?php esc_html_e( 'Convert to Project', 'etwa' ); ?></button>
				</div>
			</form>
			<div id="etwa-convert-preview-output" class="etwa-muted" hidden></div>
		</div>
	</div>
</div>
