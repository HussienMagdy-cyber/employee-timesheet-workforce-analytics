<?php
/**
 * Employee profile panel (shared by Employees list and My Profile).
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\EmployeeProfileHelper;

$context = $context ?? 'frontend';
?>
<div class="etwa-employee-profile-shell" id="etwa-employee-profile-app" hidden>
	<div class="etwa-profile-toolbar">
		<button type="button" class="etwa-btn etwa-btn-secondary etwa-profile-back" id="etwa-profile-back" hidden>
			← <?php esc_html_e( 'Back to employees', 'etwa' ); ?>
		</button>
	</div>

	<div class="etwa-panel etwa-profile-view" id="etwa-profile-view">
		<div class="etwa-profile-header">
			<span class="etwa-portal-avatar etwa-profile-avatar" id="etwa-profile-avatar" aria-hidden="true"></span>
			<div class="etwa-profile-heading">
				<h2 id="etwa-profile-name"></h2>
				<p class="etwa-muted" id="etwa-profile-job-title"></p>
				<p class="etwa-profile-role-badge" id="etwa-profile-role"></p>
			</div>
			<div class="etwa-profile-actions" id="etwa-profile-actions" hidden>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-profile-edit-btn"><?php esc_html_e( 'Edit', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-profile-reset-btn"><?php esc_html_e( 'Send password reset', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-profile-deactivate-btn" hidden><?php esc_html_e( 'Deactivate', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-profile-reactivate-btn" hidden><?php esc_html_e( 'Reactivate', 'etwa' ); ?></button>
			</div>
		</div>

		<dl class="etwa-profile-details" id="etwa-profile-details"></dl>
	</div>

	<div class="etwa-panel etwa-profile-edit-panel" id="etwa-profile-edit-panel" hidden>
		<h3 id="etwa-profile-edit-title"><?php esc_html_e( 'Edit Profile', 'etwa' ); ?></h3>
		<form id="etwa-profile-edit-form" class="etwa-form-grid">
			<label class="etwa-span-2">
				<?php esc_html_e( 'Full name', 'etwa' ); ?>
				<input type="text" id="etwa-profile-edit-name" required />
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Email', 'etwa' ); ?>
				<input type="email" id="etwa-profile-edit-email" required />
			</label>
			<label class="etwa-span-2 etwa-profile-field-hr">
				<?php esc_html_e( 'Access level', 'etwa' ); ?>
				<select id="etwa-profile-edit-role" class="etwa-input">
					<?php echo EmployeeProfileHelper::role_options_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</select>
			</label>
			<label class="etwa-span-2 etwa-profile-field-hr">
				<?php esc_html_e( 'Job title', 'etwa' ); ?>
				<input type="text" id="etwa-profile-edit-job-title" />
			</label>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Phone number', 'etwa' ); ?>
				<input type="tel" id="etwa-profile-edit-phone" />
			</label>
			<label class="etwa-profile-field-hr">
				<?php esc_html_e( 'Join date', 'etwa' ); ?>
				<input type="date" id="etwa-profile-edit-join-date" />
			</label>
			<label>
				<?php esc_html_e( 'Birthday', 'etwa' ); ?>
				<input type="date" id="etwa-profile-edit-birthday" />
			</label>
			<div class="etwa-modal-actions etwa-span-2">
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-profile-edit-cancel"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>
