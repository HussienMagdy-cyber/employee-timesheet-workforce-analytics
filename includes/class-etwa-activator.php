<?php
/**
 * Plugin activation handler.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA;

use ETWA\Database\Schema;
use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Repositories\EmailSettingsRepository;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( ETWA_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Employee Timesheet & Workforce Analytics requires PHP 8.0 or higher.', 'etwa' ),
				esc_html__( 'Plugin Activation Error', 'etwa' ),
				array( 'back_link' => true )
			);
		}

		Schema::create_tables();
		Schema::maybe_upgrade();
		Schema::seed_calendar_defaults();
		delete_option( 'etwa_roles_version' );
		RoleManager::register_roles_and_caps();
		AuthHelper::ensure_pages();
		self::seed_defaults();
		EmailSettingsRepository::seed_defaults();
		self::schedule_cron();

		update_option( 'etwa_db_version', Schema::VERSION );
		delete_transient( 'etwa_break_client_ok' );
		flush_rewrite_rules();
	}

	private static function seed_defaults(): void {
		$settings = new SettingsRepository();

		$defaults = array(
			'work_week_start'       => 'sunday',
			'work_week_end'         => 'thursday',
			'work_day_start'        => '10:00',
			'work_day_end'          => '18:00',
			'time_interval_minutes' => 30,
			'min_overtime_minutes'  => 30,
			'overtime_round_minutes'=> 30,
			'available_hours_week'  => 40,
			'overtime_cost_multiplier' => 1,
			'hr_email'              => get_option( 'admin_email' ),
			'owner_email'           => get_option( 'admin_email' ),
			'email_cc_owner'        => '1',
			'dark_mode_default'     => '0',
			'autosave_interval'     => 30,
			'enable_audit_logs'     => '1',
			'vacation_dual_approval'=> '1',
		);

		foreach ( $defaults as $key => $value ) {
			if ( null === $settings->get( $key ) ) {
				$settings->set( $key, $value );
			}
		}

		global $wpdb;
		$table = $wpdb->prefix . 'etwa_clients';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", 'SDM' ) );
		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'name'       => 'SDM',
					'code'       => 'SDM',
					'department' => 'General',
					'is_active'  => 1,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%d', '%s' )
			);
		}

		( new \ETWA\Repositories\ClientRepository() )->ensure_system_clients();
	}

	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'etwa_weekly_report_email' ) ) {
			wp_schedule_event( strtotime( 'next friday 08:00' ), 'weekly', 'etwa_weekly_report_email' );
		}
		if ( ! wp_next_scheduled( 'etwa_daily_reminder' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 09:00' ), 'daily', 'etwa_daily_reminder' );
		}
	}
}
