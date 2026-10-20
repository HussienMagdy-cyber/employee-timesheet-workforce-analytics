<?php
/**
 * Settings page template.
 *
 * @var array $settings
 * @var object[] $audit
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap etwa-app" id="etwa-settings-app">
	<h1><?php esc_html_e( 'System Settings', 'etwa' ); ?></h1>

	<form id="etwa-settings-form">
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Working Hours', 'etwa' ); ?></h2>
			<div class="etwa-form-grid">
				<label><?php esc_html_e( 'Day Start', 'etwa' ); ?><input type="time" name="work_day_start" value="<?php echo esc_attr( $settings['work_day_start'] ?? '10:00' ); ?>" /></label>
				<label><?php esc_html_e( 'Day End', 'etwa' ); ?><input type="time" name="work_day_end" value="<?php echo esc_attr( $settings['work_day_end'] ?? '18:00' ); ?>" /></label>
				<label><?php esc_html_e( 'Time Interval (min)', 'etwa' ); ?><input type="number" name="time_interval_minutes" value="<?php echo esc_attr( (string) ( $settings['time_interval_minutes'] ?? 30 ) ); ?>" /></label>
				<label><?php esc_html_e( 'Available Hours / Week', 'etwa' ); ?><input type="number" name="available_hours_week" value="<?php echo esc_attr( (string) ( $settings['available_hours_week'] ?? 40 ) ); ?>" /></label>
			</div>
		</div>

		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Email & Notifications', 'etwa' ); ?></h2>
			<div class="etwa-form-grid">
				<label><?php esc_html_e( 'HR Email (TO)', 'etwa' ); ?><input type="email" name="hr_email" value="<?php echo esc_attr( $settings['hr_email'] ?? get_option( 'admin_email' ) ); ?>" /></label>
				<label><?php esc_html_e( 'Owner Email (CC)', 'etwa' ); ?><input type="email" name="owner_email" value="<?php echo esc_attr( $settings['owner_email'] ?? get_option( 'admin_email' ) ); ?>" /></label>
			</div>
		</div>

		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Lock Reporting Period', 'etwa' ); ?></h2>
			<div class="etwa-form-grid">
				<label><?php esc_html_e( 'Period Start', 'etwa' ); ?><input type="date" name="lock_period_start" /></label>
				<label><?php esc_html_e( 'Period End', 'etwa' ); ?><input type="date" name="lock_period_end" /></label>
			</div>
			<p class="etwa-muted"><?php esc_html_e( 'Locking prevents edits to all timesheets in the selected date range.', 'etwa' ); ?></p>
		</div>

		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Vacation Requests', 'etwa' ); ?></h2>
			<label class="etwa-checkbox-row">
				<input type="checkbox" name="vacation_dual_approval" value="1" <?php checked( (string) ( $settings['vacation_dual_approval'] ?? '1' ), '1' ); ?> />
				<?php esc_html_e( 'Require both HR Manager and Owner approval before leave requests are finalized', 'etwa' ); ?>
			</label>
			<p class="etwa-muted"><?php esc_html_e( 'When enabled, one HR approval and one Owner approval are required. Either reviewer can reject a request immediately.', 'etwa' ); ?></p>
		</div>

		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Audit Logging', 'etwa' ); ?></h2>
			<label class="etwa-checkbox-row">
				<input type="checkbox" name="enable_audit_logs" value="1" <?php checked( (string) ( $settings['enable_audit_logs'] ?? '1' ), '1' ); ?> />
				<?php esc_html_e( 'Enable audit logging for timesheet edits, approvals, exports, and settings changes', 'etwa' ); ?>
			</label>
			<p class="etwa-muted">
				<?php
				printf(
					wp_kses(
						/* translators: %s: admin logs page URL */
						__( 'View the full audit trail on the <a href="%s">Audit Logs</a> page. WordPress PHP errors are logged to <code>wp-content/debug.log</code> when debug mode is on.', 'etwa' ),
						array(
							'a'    => array( 'href' => array() ),
							'code' => array(),
						)
					),
					esc_url( admin_url( 'admin.php?page=etwa-logs' ) )
				);
				?>
			</p>
		</div>

		<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'etwa' ); ?></button></p>
	</form>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Recent Audit Logs', 'etwa' ); ?></h2>
		<table class="widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Time', 'etwa' ); ?></th><th><?php esc_html_e( 'User', 'etwa' ); ?></th><th><?php esc_html_e( 'Action', 'etwa' ); ?></th><th><?php esc_html_e( 'Entity', 'etwa' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $audit as $log ) : ?>
					<?php $user = get_userdata( (int) $log->user_id ); ?>
					<tr>
						<td><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : '—' ); ?></td>
						<td><?php echo esc_html( $log->action ); ?></td>
						<td><?php echo esc_html( $log->entity_type . ' #' . $log->entity_id ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
