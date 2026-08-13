<?php
/**
 * Departments management (HR/Owner).
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context;
?>
<div class="etwa-page-shell etwa-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-departments-app">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Organization', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Departments', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Create departments, assign department heads, and configure approval workflows.', 'etwa' ); ?></p>
			</div>
			<div class="etwa-hero-actions">
				<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-add-department"><?php esc_html_e( 'Add Department', 'etwa' ); ?></button>
			</div>
		</div>
	<?php else : ?>
		<div class="etwa-toolbar">
			<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-add-department"><?php esc_html_e( 'Add Department', 'etwa' ); ?></button>
		</div>
	<?php endif; ?>

	<div class="etwa-panel">
		<div class="etwa-table-wrap">
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Department Head', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Employees', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Dept Approval', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Deliverables', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-departments-body">
					<tr class="etwa-skeleton-row"><td colspan="7"><div class="etwa-skeleton" style="height:40px"></div></td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div id="etwa-department-modal" class="etwa-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-department-modal-title">
	<div class="etwa-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<h3 id="etwa-department-modal-title"><?php esc_html_e( 'Department', 'etwa' ); ?></h3>
		<form id="etwa-department-form" class="etwa-form-grid">
			<input type="hidden" id="etwa-dept-id" value="" />
			<label class="etwa-span-2">
				<?php esc_html_e( 'Department Name', 'etwa' ); ?>
				<input type="text" id="etwa-dept-name" required />
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Description', 'etwa' ); ?>
				<textarea id="etwa-dept-description" rows="3"></textarea>
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Department Head', 'etwa' ); ?>
				<select id="etwa-dept-head">
					<option value=""><?php esc_html_e( 'No Department Head', 'etwa' ); ?></option>
				</select>
			</label>
			<label class="etwa-span-2 etwa-checkbox-label">
				<input type="checkbox" id="etwa-dept-approval" checked />
				<?php esc_html_e( 'Enable Department Approval', 'etwa' ); ?>
			</label>
			<label class="etwa-span-2 etwa-checkbox-label">
				<input type="checkbox" id="etwa-dept-deliverables" />
				<?php esc_html_e( 'Allow department members to view & allocate deliverables', 'etwa' ); ?>
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Status', 'etwa' ); ?>
				<select id="etwa-dept-status">
					<option value="active"><?php esc_html_e( 'Active', 'etwa' ); ?></option>
					<option value="inactive"><?php esc_html_e( 'Inactive', 'etwa' ); ?></option>
				</select>
			</label>
			<div class="etwa-modal-actions etwa-span-2">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>
