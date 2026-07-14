<?php
/**
 * Frontend clients management page.
 *
 * @var \ETWA\Models\Client[] $clients
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Repositories\ClientRepository;
?>
<div class="etwa-page-shell etwa-app etwa-portal" id="etwa-clients-app">
	<div class="etwa-hero">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'Settings', 'etwa' ); ?></p>
			<h1><?php esc_html_e( 'Manage Clients', 'etwa' ); ?></h1>
			<p class="etwa-subtitle"><?php esc_html_e( 'Add, edit, or remove clients for the timesheet dropdown. System options (Break, Meeting, Event, Moderation) cannot be removed.', 'etwa' ); ?></p>
		</div>
	</div>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Add Client', 'etwa' ); ?></h2>
		<form id="etwa-client-form" class="etwa-form-grid">
			<label><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" required placeholder="<?php esc_attr_e( 'e.g. SDM', 'etwa' ); ?>" /></label>
			<label><?php esc_html_e( 'Code', 'etwa' ); ?><input type="text" name="code" required placeholder="<?php esc_attr_e( 'e.g. SDM', 'etwa' ); ?>" /></label>
			<label class="etwa-span-2"><?php esc_html_e( 'Department', 'etwa' ); ?><input type="text" name="department" value="General" /></label>
			<div class="etwa-span-2">
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Add Client', 'etwa' ); ?></button>
			</div>
		</form>
	</div>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Clients & Break', 'etwa' ); ?></h2>
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
				<?php foreach ( $clients as $client ) : ?>
					<?php $is_system = in_array( strtoupper( $client->code ), ClientRepository::system_codes(), true ); ?>
					<tr
						data-id="<?php echo esc_attr( (string) $client->id ); ?>"
						data-system="<?php echo $is_system ? '1' : '0'; ?>"
						data-name="<?php echo esc_attr( $client->name ); ?>"
						data-code="<?php echo esc_attr( $client->code ); ?>"
						data-department="<?php echo esc_attr( $client->department ); ?>"
						data-active="<?php echo $client->is_active ? '1' : '0'; ?>"
					>
						<td><?php echo esc_html( $client->name ); ?></td>
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
							<?php if ( ! $is_system ) : ?>
								<button type="button" class="etwa-link etwa-edit-client"><?php esc_html_e( 'Edit', 'etwa' ); ?></button>
								<?php if ( $client->is_active ) : ?>
									<button type="button" class="etwa-link etwa-deactivate-client"><?php esc_html_e( 'Deactivate', 'etwa' ); ?></button>
								<?php endif; ?>
								<button type="button" class="etwa-link etwa-delete-client"><?php esc_html_e( 'Delete', 'etwa' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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
</div>
