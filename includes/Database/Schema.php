<?php
/**
 * Database schema definitions and migrations.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	public const VERSION = '1.17.0';

	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql_timesheets = "CREATE TABLE {$p}etwa_timesheets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			period_start date NOT NULL,
			period_end date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			total_regular_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			total_overtime_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			submitted_at datetime DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_notes text DEFAULT NULL,
			is_locked tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_period (user_id, period_start, period_end),
			KEY status (status),
			KEY period_start (period_start),
			KEY user_id (user_id)
		) {$charset};";

		$sql_entries = "CREATE TABLE {$p}etwa_entries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timesheet_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			entry_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			task varchar(255) NOT NULL DEFAULT '',
			notes text DEFAULT NULL,
			duration_minutes int(11) NOT NULL DEFAULT 0,
			regular_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			overtime_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			entry_type varchar(20) NOT NULL DEFAULT 'regular',
			created_from varchar(100) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY timesheet_id (timesheet_id),
			KEY user_id (user_id),
			KEY entry_date (entry_date),
			KEY client_id (client_id)
		) {$charset};";

		$sql_clients = "CREATE TABLE {$p}etwa_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			code varchar(50) NOT NULL DEFAULT '',
			department varchar(100) NOT NULL DEFAULT '',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY is_active (is_active)
		) {$charset};";

		$sql_overtime = "CREATE TABLE {$p}etwa_overtime (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			timesheet_id bigint(20) unsigned NOT NULL,
			entry_id bigint(20) unsigned NOT NULL,
			entry_date date NOT NULL,
			overtime_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			status varchar(20) NOT NULL DEFAULT 'pending',
			approved_hours decimal(8,2) NOT NULL DEFAULT 0.00,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			review_notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY entry_id (entry_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY entry_date (entry_date)
		) {$charset};";

		$sql_settings = "CREATE TABLE {$p}etwa_settings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			setting_key varchar(100) NOT NULL,
			setting_value longtext NOT NULL,
			autoload tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset};";

		$sql_audit = "CREATE TABLE {$p}etwa_audit_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			employee_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(50) NOT NULL,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_value longtext DEFAULT NULL,
			new_value longtext DEFAULT NULL,
			edit_reason text DEFAULT NULL,
			editor_role varchar(50) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY employee_id (employee_id),
			KEY entity (entity_type, entity_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_timesheets );
		dbDelta( $sql_entries );
		dbDelta( $sql_clients );
		dbDelta( $sql_overtime );
		dbDelta( $sql_settings );
		dbDelta( $sql_audit );
		dbDelta( self::holidays_sql( $p, $charset ) );
		dbDelta( self::leave_types_sql( $p, $charset ) );
		dbDelta( self::vacation_transactions_sql( $p, $charset ) );
		dbDelta( self::vacation_records_sql( $p, $charset ) );
		dbDelta( self::vacation_requests_sql( $p, $charset ) );
		dbDelta( self::requests_sql( $p, $charset ) );
		dbDelta( self::departments_sql( $p, $charset ) );
	}

	private static function leave_types_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_leave_types (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(50) NOT NULL,
			name varchar(191) NOT NULL,
			tracks_allocation tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active),
			KEY sort_order (sort_order)
		) {$charset};";
	}

	private static function vacation_transactions_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_vacation_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			leave_type_id bigint(20) unsigned NOT NULL,
			transaction_type varchar(20) NOT NULL,
			days decimal(8,2) NOT NULL DEFAULT 0.00,
			record_id bigint(20) unsigned DEFAULT NULL,
			effective_date date NOT NULL,
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY leave_type_id (leave_type_id),
			KEY transaction_type (transaction_type),
			KEY effective_date (effective_date),
			KEY record_id (record_id),
			KEY user_type_date (user_id, leave_type_id, effective_date)
		) {$charset};";
	}

	private static function vacation_records_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_vacation_records (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			leave_type_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			days decimal(8,2) NOT NULL DEFAULT 0.00,
			reason text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY leave_type_id (leave_type_id),
			KEY status (status),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY user_status (user_id, status)
		) {$charset};";
	}

	private static function requests_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_type varchar(30) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			request_date date NOT NULL,
			work_date date DEFAULT NULL,
			start_time time DEFAULT NULL,
			end_time time DEFAULT NULL,
			permission_type varchar(50) DEFAULT NULL,
			reason text DEFAULT NULL,
			notes text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			hr_approved_by bigint(20) unsigned DEFAULT NULL,
			hr_approved_at datetime DEFAULT NULL,
			owner_approved_by bigint(20) unsigned DEFAULT NULL,
			owner_approved_at datetime DEFAULT NULL,
			dept_approved_by bigint(20) unsigned DEFAULT NULL,
			dept_approved_at datetime DEFAULT NULL,
			workflow_status varchar(30) NOT NULL DEFAULT 'pending_hr',
			extra_data longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY request_type (request_type),
			KEY status (status),
			KEY work_date (work_date),
			KEY request_date (request_date),
			KEY type_status (request_type, status)
		) {$charset};";
	}

	private static function vacation_requests_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_vacation_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			leave_type_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			days decimal(8,2) NOT NULL DEFAULT 0.00,
			reason text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			notes text DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			hr_approved_by bigint(20) unsigned DEFAULT NULL,
			hr_approved_at datetime DEFAULT NULL,
			owner_approved_by bigint(20) unsigned DEFAULT NULL,
			owner_approved_at datetime DEFAULT NULL,
			dept_approved_by bigint(20) unsigned DEFAULT NULL,
			dept_approved_at datetime DEFAULT NULL,
			workflow_status varchar(30) NOT NULL DEFAULT 'pending_hr',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY leave_type_id (leave_type_id),
			KEY status (status),
			KEY start_date (start_date),
			KEY end_date (end_date)
		) {$charset};";
	}

	public static function seed_default_leave_types(): void {
		$repo = new \ETWA\Repositories\LeaveTypeRepository();
		if ( $repo->count() > 0 ) {
			return;
		}

		$defaults = array(
			array( 'slug' => 'annual', 'name' => __( 'Annual Leave', 'etwa' ), 'tracks_allocation' => 1, 'sort_order' => 1 ),
			array( 'slug' => 'sick', 'name' => __( 'Sick Leave', 'etwa' ), 'tracks_allocation' => 1, 'sort_order' => 2 ),
			array( 'slug' => 'emergency', 'name' => __( 'Emergency Leave', 'etwa' ), 'tracks_allocation' => 1, 'sort_order' => 3 ),
			array( 'slug' => 'compensatory', 'name' => __( 'Compensatory Leave', 'etwa' ), 'tracks_allocation' => 1, 'sort_order' => 4 ),
			array( 'slug' => 'unpaid', 'name' => __( 'Unpaid Leave', 'etwa' ), 'tracks_allocation' => 0, 'sort_order' => 5 ),
		);

		foreach ( $defaults as $type ) {
			$repo->create( $type );
		}
	}

	public static function migrate_annual_pool_leave_types(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_leave_types';
		$pool_only = array( 'sick', 'emergency', 'compensatory' );

		foreach ( $pool_only as $slug ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->update(
				$table,
				array( 'tracks_allocation' => 0 ),
				array( 'slug' => $slug ),
				array( '%d' ),
				array( '%s' )
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			$table,
			array( 'tracks_allocation' => 1 ),
			array( 'slug' => 'annual' ),
			array( '%d' ),
			array( '%s' )
		);
	}

	private static function holidays_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_holidays (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			holiday_date date NOT NULL,
			is_recurring tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY holiday_date (holiday_date),
			KEY is_recurring (is_recurring)
		) {$charset};";
	}

	private static function departments_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_departments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			description text DEFAULT NULL,
			head_user_id bigint(20) unsigned DEFAULT NULL,
			approval_enabled tinyint(1) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name),
			KEY head_user_id (head_user_id),
			KEY status (status)
		) {$charset};";
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( 'etwa_db_version', '0' );

		if ( version_compare( (string) $installed, self::VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();

		if ( version_compare( (string) $installed, '1.1.0', '<' ) ) {
			self::add_performance_indexes();
		}

		if ( version_compare( (string) $installed, '1.2.0', '<' ) ) {
			self::seed_calendar_defaults();
		}

		if ( version_compare( (string) $installed, '1.3.0', '<' ) ) {
			self::upgrade_audit_logs_v13();
		}

		if ( version_compare( (string) $installed, '1.4.0', '<' ) ) {
			self::seed_default_leave_types();
		}

		if ( version_compare( (string) $installed, '1.4.1', '<' ) ) {
			self::migrate_annual_pool_leave_types();
		}

		if ( version_compare( (string) $installed, '1.5.0', '<' ) ) {
			self::upgrade_vacation_requests_dual_approval();
		}

		if ( version_compare( (string) $installed, '1.5.1', '<' ) ) {
			self::seed_vacation_dual_approval_default();
		}

		if ( version_compare( (string) $installed, '1.6.0', '<' ) ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( self::requests_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );
		}

		if ( version_compare( (string) $installed, '1.7.0', '<' ) ) {
			self::upgrade_entries_v17();
			( new \ETWA\Repositories\ClientRepository() )->ensure_system_clients();
		}

		if ( version_compare( (string) $installed, '1.8.0', '<' ) ) {
			self::upgrade_hierarchical_approval_v18();
		}

		if ( version_compare( (string) $installed, '1.9.0', '<' ) ) {
			self::upgrade_timesheet_draft_visibility_v19();
		}

		if ( version_compare( (string) $installed, '1.10.0', '<' ) ) {
			EntryTimeOffsetMigration::maybe_run();
		}

		if ( version_compare( (string) $installed, '1.11.0', '<' ) ) {
			self::upgrade_entries_v1110();
			self::upgrade_moderation_client_label();
		}

		if ( version_compare( (string) $installed, '1.12.0', '<' ) ) {
			self::upgrade_ot_cost_multiplier_v1120();
		}

		if ( version_compare( (string) $installed, '1.13.0', '<' ) ) {
			self::upgrade_entry_type_flow_v1130();
		}

		if ( version_compare( (string) $installed, '1.14.0', '<' ) ) {
			self::upgrade_reclassify_after_hours_v1140();
		}

		if ( version_compare( (string) $installed, '1.15.0', '<' ) ) {
			self::upgrade_normalize_entry_hours_v1150();
		}

		if ( version_compare( (string) $installed, '1.16.0', '<' ) ) {
			self::upgrade_fix_after_hours_regular_v1160();
		}

		if ( version_compare( (string) $installed, '1.17.0', '<' ) ) {
			self::upgrade_compensation_regular_hours_v1170();
		}

		update_option( 'etwa_db_version', self::VERSION );
	}

	public static function seed_calendar_defaults(): void {
		$settings = new \ETWA\Repositories\SettingsRepository();
		if ( null === $settings->get( 'calendar_weekdays', null ) ) {
			$settings->set(
				'calendar_weekdays',
				wp_json_encode( \ETWA\Helpers\CalendarHelper::default_weekday_config() )
			);
		}
	}

	public static function add_performance_indexes(): void {
		global $wpdb;
		$p = $wpdb->prefix;

		self::add_index_if_missing( "{$p}etwa_timesheets", 'status_period', 'status, period_start' );
		self::add_index_if_missing( "{$p}etwa_timesheets", 'period_range', 'period_start, period_end' );
		self::add_index_if_missing( "{$p}etwa_entries", 'date_user', 'entry_date, user_id' );
		self::add_index_if_missing( "{$p}etwa_entries", 'ts_date', 'timesheet_id, entry_date' );
		self::add_index_if_missing( "{$p}etwa_overtime", 'status_date', 'status, entry_date' );
	}

	private static function add_index_if_missing( string $table, string $index_name, string $columns ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW INDEX requires table name.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
				DB_NAME,
				$table,
				$index_name
			)
		);

		if ( (int) $exists > 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration.
		$wpdb->query( "ALTER TABLE {$table} ADD INDEX {$index_name} ({$columns})" );
	}

	public static function upgrade_vacation_requests_dual_approval(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_vacation_requests';

		self::add_column_if_missing( $table, 'hr_approved_by', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_column_if_missing( $table, 'hr_approved_at', 'datetime DEFAULT NULL' );
		self::add_column_if_missing( $table, 'owner_approved_by', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_column_if_missing( $table, 'owner_approved_at', 'datetime DEFAULT NULL' );
	}

	public static function seed_vacation_dual_approval_default(): void {
		( new \ETWA\Repositories\SettingsRepository() )->set( 'vacation_dual_approval', '1' );
	}

	public static function upgrade_audit_logs_v13(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_audit_logs';

		self::add_column_if_missing( $table, 'employee_id', 'bigint(20) unsigned NOT NULL DEFAULT 0' );
		self::add_column_if_missing( $table, 'edit_reason', 'text DEFAULT NULL' );
		self::add_column_if_missing( $table, 'editor_role', 'varchar(50) DEFAULT NULL' );
		self::add_index_if_missing( $table, 'employee_id', 'employee_id' );
	}

	public static function upgrade_entries_v17(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		self::add_column_if_missing( $table, 'created_from', 'varchar(100) DEFAULT NULL' );
	}

	public static function upgrade_entries_v1110(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		self::add_column_if_missing( $table, 'entry_type', "varchar(20) NOT NULL DEFAULT 'regular'" );
	}

	public static function upgrade_ot_cost_multiplier_v1120(): void {
		( new \ETWA\Repositories\SettingsRepository() )->set( 'overtime_cost_multiplier', 1 );
	}

	public static function upgrade_entry_type_flow_v1130(): void {
		global $wpdb;

		$entries = $wpdb->prefix . 'etwa_entries';
		$clients = $wpdb->prefix . 'etwa_clients';
		$break   = \ETWA\Repositories\ClientRepository::BREAK_CODE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration update.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				SET e.entry_type = %s
				WHERE e.entry_type = %s AND e.overtime_hours > 0 AND UPPER(c.code) <> %s",
				\ETWA\Helpers\EntryTypeHelper::OVERTIME,
				\ETWA\Helpers\EntryTypeHelper::REGULAR,
				$break
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.id, e.duration_minutes, e.entry_type, e.regular_hours, e.overtime_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				WHERE e.entry_type IN (%s, %s) AND UPPER(c.code) <> %s",
				\ETWA\Helpers\EntryTypeHelper::OVERTIME,
				\ETWA\Helpers\EntryTypeHelper::COMPENSATION,
				$break
			)
		);

		foreach ( $rows ?: array() as $row ) {
			$duration_hours = round( max( 0, (int) $row->duration_minutes ) / 60, 2 );
			$update         = array();

			if ( \ETWA\Helpers\EntryTypeHelper::OVERTIME === $row->entry_type ) {
				if ( (float) $row->overtime_hours <= 0 && $duration_hours > 0 ) {
					$update['regular_hours']  = 0.0;
					$update['overtime_hours'] = $duration_hours;
				}
			}

			if ( \ETWA\Helpers\EntryTypeHelper::COMPENSATION === $row->entry_type ) {
				$update['regular_hours']  = $duration_hours;
				$update['overtime_hours'] = 0.0;
			}

			if ( $update ) {
				$wpdb->update( $entries, $update, array( 'id' => (int) $row->id ) );
			}
		}

		self::upgrade_draft_snapshots_entry_type_v1130();

		$overtime_ids = $wpdb->get_col(
			"SELECT id FROM {$entries} WHERE overtime_hours > 0"
		);
		$overtime_service = new \ETWA\Services\OvertimeService();
		foreach ( $overtime_ids ?: array() as $entry_id ) {
			$overtime_service->sync_from_entry( (int) $entry_id );
		}

		\ETWA\Services\ClientAnalyticsService::flush_cache();
		\ETWA\Services\AnalyticsService::flush_dashboard_cache();
	}

	private static function upgrade_draft_snapshots_entry_type_v1130(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_timesheets';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration select.
		$rows = $wpdb->get_results(
			"SELECT id, draft_snapshot FROM {$table}
			WHERE draft_snapshot IS NOT NULL AND draft_snapshot <> ''"
		);

		foreach ( $rows ?: array() as $row ) {
			$decoded = json_decode( (string) $row->draft_snapshot, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$changed = false;
			foreach ( $decoded as $index => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				if ( ! empty( $entry['entry_type'] ) && \ETWA\Helpers\EntryTypeHelper::REGULAR !== $entry['entry_type'] ) {
					continue;
				}

				if ( (float) ( $entry['overtime_hours'] ?? 0 ) > 0 ) {
					$decoded[ $index ]['entry_type'] = \ETWA\Helpers\EntryTypeHelper::OVERTIME;
					$changed                        = true;
					continue;
				}

				if ( empty( $entry['entry_type'] ) ) {
					$decoded[ $index ]['entry_type'] = \ETWA\Helpers\EntryTypeHelper::REGULAR;
					$changed                        = true;
				}
			}

			if ( $changed ) {
				$wpdb->update(
					$table,
					array( 'draft_snapshot' => wp_json_encode( $decoded ) ),
					array( 'id' => (int) $row->id )
				);
			}
		}
	}

	public static function upgrade_reclassify_after_hours_v1140(): void {
		global $wpdb;

		$entries  = $wpdb->prefix . 'etwa_entries';
		$clients  = $wpdb->prefix . 'etwa_clients';
		$break    = \ETWA\Repositories\ClientRepository::BREAK_CODE;
		$work_end = \ETWA\Helpers\EntryTypeHelper::work_day_end();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration update.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				SET e.entry_type = %s,
					e.overtime_hours = e.regular_hours,
					e.regular_hours = 0
				WHERE e.entry_type = %s
					AND e.regular_hours > 0
					AND e.start_time >= %s
					AND UPPER(c.code) <> %s",
				\ETWA\Helpers\EntryTypeHelper::OVERTIME,
				\ETWA\Helpers\EntryTypeHelper::REGULAR,
				$work_end,
				$break
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.id, e.duration_minutes, e.entry_type, e.regular_hours, e.overtime_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				WHERE e.entry_type = %s AND e.overtime_hours > 0 AND UPPER(c.code) <> %s",
				\ETWA\Helpers\EntryTypeHelper::OVERTIME,
				$break
			)
		);

		foreach ( $rows ?: array() as $row ) {
			if ( (float) $row->regular_hours <= 0 ) {
				continue;
			}

			$duration_hours = round( max( 0, (int) $row->duration_minutes ) / 60, 2 );
			$overtime_hours = $duration_hours > 0 ? $duration_hours : (float) $row->overtime_hours;

			$wpdb->update(
				$entries,
				array(
					'regular_hours'  => 0.0,
					'overtime_hours' => $overtime_hours,
				),
				array( 'id' => (int) $row->id )
			);
		}

		$timesheet_ids = $wpdb->get_col(
			"SELECT DISTINCT timesheet_id FROM {$entries} WHERE overtime_hours > 0"
		);
		foreach ( $timesheet_ids ?: array() as $timesheet_id ) {
			$sums = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(regular_hours),0) AS regular, COALESCE(SUM(overtime_hours),0) AS overtime
					FROM {$entries} WHERE timesheet_id = %d",
					(int) $timesheet_id
				)
			);
			if ( $sums ) {
				$wpdb->update(
					$wpdb->prefix . 'etwa_timesheets',
					array(
						'total_regular_hours'  => (float) $sums->regular,
						'total_overtime_hours' => (float) $sums->overtime,
					),
					array( 'id' => (int) $timesheet_id )
				);
			}
		}

		$overtime_service = new \ETWA\Services\OvertimeService();
		$overtime_ids     = $wpdb->get_col( "SELECT id FROM {$entries} WHERE overtime_hours > 0" );
		foreach ( $overtime_ids ?: array() as $entry_id ) {
			$overtime_service->sync_from_entry( (int) $entry_id );
		}

		\ETWA\Services\ClientAnalyticsService::flush_cache();
		\ETWA\Services\AnalyticsService::flush_dashboard_cache();
	}

	public static function upgrade_normalize_entry_hours_v1150(): void {
		global $wpdb;

		$entries     = $wpdb->prefix . 'etwa_entries';
		$clients     = $wpdb->prefix . 'etwa_clients';
		$timesheets  = $wpdb->prefix . 'etwa_timesheets';
		$break       = \ETWA\Repositories\ClientRepository::BREAK_CODE;
		$calculator  = new \ETWA\Helpers\TimeCalculator();
		$client_repo = new \ETWA\Repositories\ClientRepository();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration select.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.id, e.timesheet_id, e.entry_date, e.start_time, e.end_time, e.client_id,
					e.entry_type, e.duration_minutes, e.regular_hours, e.overtime_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				WHERE UPPER(c.code) <> %s",
				$break
			)
		);

		$timesheet_ids = array();
		foreach ( $rows ?: array() as $row ) {
			$entry_type = (string) $row->entry_type;
			if ( \ETWA\Helpers\EntryTypeHelper::REGULAR === $entry_type && (float) $row->overtime_hours > 0 ) {
				$entry_type = \ETWA\Helpers\EntryTypeHelper::OVERTIME;
			}

			$client     = $client_repo->find( (int) $row->client_id );
			$is_break   = $client_repo->is_break_client( (int) $row->client_id, $client );
			$normalized = \ETWA\Helpers\EntryTypeHelper::normalize_stored_hours(
				$entry_type,
				(string) $row->entry_date,
				(string) $row->start_time,
				(string) $row->end_time,
				$is_break,
				$calculator
			);

			$needs_update = (int) $row->duration_minutes !== $normalized['duration_minutes']
				|| abs( (float) $row->regular_hours - $normalized['regular_hours'] ) > 0.001
				|| abs( (float) $row->overtime_hours - $normalized['overtime_hours'] ) > 0.001
				|| (string) $row->entry_type !== $normalized['entry_type'];

			if ( ! $needs_update ) {
				continue;
			}

			$wpdb->update(
				$entries,
				array(
					'duration_minutes' => $normalized['duration_minutes'],
					'regular_hours'    => $normalized['regular_hours'],
					'overtime_hours'   => $normalized['overtime_hours'],
					'entry_type'       => $normalized['entry_type'],
				),
				array( 'id' => (int) $row->id )
			);

			$timesheet_ids[ (int) $row->timesheet_id ] = true;
		}

		self::upgrade_draft_snapshots_normalize_hours_v1150();

		foreach ( array_keys( $timesheet_ids ) as $timesheet_id ) {
			$sums = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(regular_hours),0) AS regular, COALESCE(SUM(overtime_hours),0) AS overtime
					FROM {$entries} WHERE timesheet_id = %d",
					(int) $timesheet_id
				)
			);
			if ( ! $sums ) {
				continue;
			}

			$wpdb->update(
				$timesheets,
				array(
					'total_regular_hours'  => (float) $sums->regular,
					'total_overtime_hours' => (float) $sums->overtime,
				),
				array( 'id' => (int) $timesheet_id )
			);
		}

		$overtime_service = new \ETWA\Services\OvertimeService();
		$overtime_ids     = $wpdb->get_col( "SELECT id FROM {$entries} WHERE overtime_hours > 0" );
		foreach ( $overtime_ids ?: array() as $entry_id ) {
			$overtime_service->sync_from_entry( (int) $entry_id );
		}

		\ETWA\Services\ClientAnalyticsService::flush_cache();
		\ETWA\Services\AnalyticsService::flush_dashboard_cache();
	}

	private static function upgrade_draft_snapshots_normalize_hours_v1150(): void {
		global $wpdb;

		$table       = $wpdb->prefix . 'etwa_timesheets';
		$calculator  = new \ETWA\Helpers\TimeCalculator();
		$client_repo = new \ETWA\Repositories\ClientRepository();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration select.
		$rows = $wpdb->get_results(
			"SELECT id, draft_snapshot FROM {$table}
			WHERE draft_snapshot IS NOT NULL AND draft_snapshot <> ''"
		);

		foreach ( $rows ?: array() as $row ) {
			$decoded = json_decode( (string) $row->draft_snapshot, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$changed = false;
			foreach ( $decoded as $index => $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}

				$entry_type = (string) ( $entry['entry_type'] ?? \ETWA\Helpers\EntryTypeHelper::REGULAR );
				if ( \ETWA\Helpers\EntryTypeHelper::REGULAR === $entry_type && (float) ( $entry['overtime_hours'] ?? 0 ) > 0 ) {
					$entry_type = \ETWA\Helpers\EntryTypeHelper::OVERTIME;
				}

				$client_id  = (int) ( $entry['client_id'] ?? 0 );
				$client     = $client_repo->find( $client_id );
				$is_break   = $client_repo->is_break_client( $client_id, $client );
				$normalized = \ETWA\Helpers\EntryTypeHelper::normalize_stored_hours(
					$entry_type,
					(string) ( $entry['entry_date'] ?? '' ),
					(string) ( $entry['start_time'] ?? '' ),
					(string) ( $entry['end_time'] ?? '' ),
					$is_break,
					$calculator
				);

				$decoded[ $index ]['duration_minutes'] = $normalized['duration_minutes'];
				$decoded[ $index ]['regular_hours']    = $normalized['regular_hours'];
				$decoded[ $index ]['overtime_hours']   = $normalized['overtime_hours'];
				$decoded[ $index ]['entry_type']       = $normalized['entry_type'];
				$changed                               = true;
			}

			if ( $changed ) {
				$wpdb->update(
					$table,
					array( 'draft_snapshot' => wp_json_encode( $decoded ) ),
					array( 'id' => (int) $row->id )
				);
			}
		}
	}

	public static function upgrade_fix_after_hours_regular_v1160(): void {
		self::upgrade_normalize_entry_hours_v1150();
	}

	public static function upgrade_compensation_regular_hours_v1170(): void {
		global $wpdb;

		$entries    = $wpdb->prefix . 'etwa_entries';
		$timesheets = $wpdb->prefix . 'etwa_timesheets';
		$comp_type  = \ETWA\Helpers\EntryTypeHelper::COMPENSATION;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration select.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, timesheet_id, duration_minutes, regular_hours
				FROM {$entries}
				WHERE entry_type = %s AND duration_minutes > 0",
				$comp_type
			)
		);

		$timesheet_ids = array();
		foreach ( $rows ?: array() as $row ) {
			$hours = round( max( 0, (int) $row->duration_minutes ) / 60, 2 );
			if ( abs( (float) $row->regular_hours - $hours ) < 0.001 ) {
				continue;
			}

			$wpdb->update(
				$entries,
				array(
					'regular_hours'  => $hours,
					'overtime_hours' => 0.0,
				),
				array( 'id' => (int) $row->id )
			);

			$timesheet_ids[ (int) $row->timesheet_id ] = true;
		}

		self::upgrade_draft_snapshots_normalize_hours_v1150();

		foreach ( array_keys( $timesheet_ids ) as $timesheet_id ) {
			$sums = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(regular_hours),0) AS regular, COALESCE(SUM(overtime_hours),0) AS overtime
					FROM {$entries} WHERE timesheet_id = %d",
					(int) $timesheet_id
				)
			);
			if ( ! $sums ) {
				continue;
			}

			$wpdb->update(
				$timesheets,
				array(
					'total_regular_hours'  => (float) $sums->regular,
					'total_overtime_hours' => (float) $sums->overtime,
				),
				array( 'id' => (int) $timesheet_id )
			);
		}

		\ETWA\Services\ClientAnalyticsService::flush_cache();
		\ETWA\Services\AnalyticsService::flush_dashboard_cache();
	}

	public static function upgrade_moderation_client_label(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_clients';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration update.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET name = %s WHERE code = %s",
				__( 'Moderation', 'etwa' ),
				\ETWA\Repositories\ClientRepository::ALL_CLIENTS_CODE
			)
		);
	}

	public static function upgrade_hierarchical_approval_v18(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::departments_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );

		foreach ( array( 'etwa_vacation_requests', 'etwa_requests' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			self::add_column_if_missing( $table, 'dept_approved_by', 'bigint(20) unsigned DEFAULT NULL' );
			self::add_column_if_missing( $table, 'dept_approved_at', 'datetime DEFAULT NULL' );
			self::add_column_if_missing( $table, 'workflow_status', "varchar(30) NOT NULL DEFAULT 'pending_hr'" );
			self::add_index_if_missing( $table, 'workflow_status', 'workflow_status' );
		}

		// Migrate pending requests to hierarchical workflow.
		foreach ( array( 'etwa_vacation_requests', 'etwa_requests' ) as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$table} SET workflow_status = 'approved' WHERE status = 'approved'"
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$table} SET workflow_status = 'rejected_hr' WHERE status = 'rejected'"
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"UPDATE {$table} SET workflow_status = 'pending_hr' WHERE status = 'pending'"
			);
		}
	}

	public static function upgrade_timesheet_draft_visibility_v19(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_timesheets';

		self::add_column_if_missing( $table, 'draft_saved_at', 'datetime DEFAULT NULL' );
		self::add_column_if_missing( $table, 'draft_snapshot', 'longtext DEFAULT NULL' );
	}

	private static function add_column_if_missing( string $table, string $column, string $definition ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW COLUMNS requires table name.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$table,
				$column
			)
		);

		if ( (int) $exists > 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
	}
}
