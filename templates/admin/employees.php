<?php
/**
 * Employees page — management, create, and profiles (HR/Owner only).
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context;
$viewer_id = get_current_user_id();
$can_view_labor_cost = EmployeeCostHelper::can_view_labor_cost( $viewer_id );
?>
<div class="etwa-page-shell etwa-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-employees-app">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Workforce', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Employees', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Create employees, view profiles, and manage departments.', 'etwa' ); ?></p>
			</div>
			<div class="etwa-hero-actions">
				<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-add-employee"><?php esc_html_e( 'Add Employee', 'etwa' ); ?></button>
			</div>
		</div>
	<?php else : ?>
		<div class="etwa-toolbar">
			<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-add-employee"><?php esc_html_e( 'Add Employee', 'etwa' ); ?></button>
		</div>
	<?php endif; ?>

	<div id="etwa-employees-list-wrap">
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-employees-toolbar">
				<label class="etwa-search-field">
					<span class="screen-reader-text"><?php esc_html_e( 'Search employees', 'etwa' ); ?></span>
					<input type="search" id="etwa-employees-search" placeholder="<?php esc_attr_e( 'Search by name or email…', 'etwa' ); ?>" autocomplete="off" />
				</label>
				<p class="etwa-muted etwa-employees-count" id="etwa-employees-count" aria-live="polite"></p>
			</div>

			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-employees-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Role', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Email', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-employees-body">
						<tr class="etwa-skeleton-row"><td colspan="5"><div class="etwa-skeleton" style="height:40px"></div></td></tr>
					</tbody>
				</table>
			</div>

			<nav class="etwa-pagination" id="etwa-employees-pagination" aria-label="<?php esc_attr_e( 'Employees pagination', 'etwa' ); ?>"></nav>
		</div>
	</div>

	<?php include ETWA_PLUGIN_DIR . 'templates/partials/employee-profile-panel.php'; ?>
</div>

<div id="etwa-employee-modal" class="etwa-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-employee-modal-title">
	<div class="etwa-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<h3 id="etwa-employee-modal-title"><?php esc_html_e( 'Add Employee', 'etwa' ); ?></h3>
		<p class="etwa-muted etwa-modal-note"><?php esc_html_e( 'A password setup link will be emailed — no password is required here.', 'etwa' ); ?></p>
		<form id="etwa-employee-form" class="etwa-form-grid">
			<label class="etwa-span-2">
				<?php esc_html_e( 'Full name', 'etwa' ); ?>
				<input type="text" id="etwa-employee-name" required autocomplete="name" />
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Email', 'etwa' ); ?>
				<input type="email" id="etwa-employee-email" required autocomplete="email" />
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Role', 'etwa' ); ?>
				<select id="etwa-employee-role" class="etwa-input" required>
					<?php echo EmployeeProfileHelper::role_options_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Department', 'etwa' ); ?>
				<select id="etwa-employee-department" class="etwa-input">
					<?php echo EmployeeProfileHelper::department_options_html( $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Job title', 'etwa' ); ?>
				<input type="text" id="etwa-employee-job-title" required />
			</label>
			<label>
				<?php esc_html_e( 'Join date', 'etwa' ); ?>
				<input type="date" id="etwa-employee-join-date" required />
			</label>
			<label>
				<?php esc_html_e( 'Birthday', 'etwa' ); ?>
				<input type="date" id="etwa-employee-birthday" />
			</label>
			<div class="etwa-modal-actions etwa-span-2">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Create Employee', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>

<div id="etwa-employee-edit-modal" class="etwa-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-employee-edit-modal-title">
	<div class="etwa-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<h3 id="etwa-employee-edit-modal-title"><?php esc_html_e( 'Edit Employee', 'etwa' ); ?></h3>
		<p class="etwa-muted etwa-modal-note" id="etwa-employee-edit-name"></p>
		<form id="etwa-employee-edit-form" class="etwa-form-grid">
			<label class="etwa-span-2">
				<?php esc_html_e( 'Role', 'etwa' ); ?>
				<select id="etwa-employee-edit-role" class="etwa-input" required>
					<?php echo EmployeeProfileHelper::role_options_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Department', 'etwa' ); ?>
				<select id="etwa-employee-edit-department" class="etwa-input">
					<?php echo EmployeeProfileHelper::department_options_html( $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</label>
			<?php if ( $can_view_labor_cost ) : ?>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Hourly Cost (EGP)', 'etwa' ); ?>
				<input type="number" id="etwa-employee-edit-hourly-cost" class="etwa-input" min="0" max="999999.99" step="0.01" inputmode="decimal" />
			</label>
			<?php endif; ?>
			<div class="etwa-modal-actions etwa-span-2">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Changes', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>
