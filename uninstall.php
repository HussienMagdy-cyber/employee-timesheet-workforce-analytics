<?php
/**
 * Uninstall handler — removes plugin data when ETWA_DELETE_DATA is defined.
 *
 * @package ETWA
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ETWA_DELETE_DATA' ) || ! ETWA_DELETE_DATA ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'etwa_timesheets',
	$wpdb->prefix . 'etwa_entries',
	$wpdb->prefix . 'etwa_clients',
	$wpdb->prefix . 'etwa_overtime',
	$wpdb->prefix . 'etwa_settings',
	$wpdb->prefix . 'etwa_audit_logs',
	$wpdb->prefix . 'etwa_holidays',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'etwa_db_version' );
delete_option( 'etwa_settings_cache' );

$roles = array( 'etwa_employee', 'etwa_hr_manager', 'etwa_owner' );
foreach ( $roles as $role_slug ) {
	remove_role( $role_slug );
}

$caps = array(
	'etwa_view_own_timesheet',
	'etwa_edit_own_timesheet',
	'etwa_submit_timesheet',
	'etwa_view_all_timesheets',
	'etwa_approve_timesheets',
	'etwa_approve_overtime',
	'etwa_export_reports',
	'etwa_email_reports',
	'etwa_view_analytics',
	'etwa_view_advanced_analytics',
	'etwa_manage_settings',
	'etwa_lock_periods',
);

foreach ( $caps as $cap ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
			$wpdb->prefix . 'capabilities'
		)
	);
}

wp_clear_scheduled_hook( 'etwa_weekly_report_email' );
wp_clear_scheduled_hook( 'etwa_daily_reminder' );
