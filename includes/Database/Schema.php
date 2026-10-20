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

	public const VERSION = '1.39.0';

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
			client_id bigint(20) unsigned DEFAULT NULL,
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
		dbDelta( self::prospects_sql( $p, $charset ) );
		dbDelta( self::holidays_sql( $p, $charset ) );
		dbDelta( self::leave_types_sql( $p, $charset ) );
		dbDelta( self::vacation_transactions_sql( $p, $charset ) );
		dbDelta( self::vacation_records_sql( $p, $charset ) );
		dbDelta( self::vacation_requests_sql( $p, $charset ) );
		dbDelta( self::requests_sql( $p, $charset ) );
		dbDelta( self::departments_sql( $p, $charset ) );
		foreach ( self::deliverables_sql_statements( $p, $charset ) as $sql ) {
			dbDelta( $sql );
		}
		dbDelta( self::super_client_accounts_sql( $p, $charset ) );
		dbDelta( self::super_client_requests_sql( $p, $charset ) );
		dbDelta( self::super_client_units_sql( $p, $charset ) );
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
			source varchar(30) DEFAULT NULL,
			source_request_id bigint(20) unsigned DEFAULT NULL,
			balance_deducted decimal(8,2) NOT NULL DEFAULT 0.00,
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
			KEY user_status (user_id, status),
			KEY source_request (source_request_id),
			KEY source (source)
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
			array( 'slug' => 'unpaid', 'name' => __( 'Unpaid Leave', 'etwa' ), 'tracks_allocation' => 0, 'sort_order' => 4 ),
		);

		foreach ( $defaults as $type ) {
			$repo->create( $type );
		}
	}

	public static function migrate_annual_pool_leave_types(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_leave_types';
		$pool_only = array( 'sick', 'emergency' );

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
			can_allocate_deliverables tinyint(1) NOT NULL DEFAULT 0,
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

		if ( version_compare( (string) $installed, '1.18.0', '<' ) ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			foreach ( self::deliverables_sql_statements( $wpdb->prefix, $wpdb->get_charset_collate() ) as $sql ) {
				dbDelta( $sql );
			}
		}

		if ( version_compare( (string) $installed, '1.19.0', '<' ) ) {
			self::upgrade_deliverables_contract_totals_v1190();
		}

		if ( version_compare( (string) $installed, '1.20.0', '<' ) ) {
			self::upgrade_department_deliverables_v1200();
		}

		if ( version_compare( (string) $installed, '1.21.0', '<' ) ) {
			self::upgrade_multi_deliverable_allocations_v1210();
		}

		// 1.22.0 previously created content-calendar tables; feature removed — tables left in place if present.

		if ( version_compare( (string) $installed, '1.23.0', '<' ) ) {
			self::upgrade_packages_and_retire_batches_v1230();
		}

		if ( version_compare( (string) $installed, '1.24.0', '<' ) ) {
			self::upgrade_projects_and_notifications_v1240();
		}

		if ( version_compare( (string) $installed, '1.25.0', '<' ) ) {
			self::upgrade_retire_packages_from_scope_logic_v1250();
		}

		if ( version_compare( (string) $installed, '1.26.0', '<' ) ) {
			self::upgrade_legacy_feature_cleanup_v1260();
		}

		if ( version_compare( (string) $installed, '1.27.0', '<' ) ) {
			self::upgrade_standalone_projects_v1270();
		}

		if ( version_compare( (string) $installed, '1.28.0', '<' ) ) {
			self::upgrade_client_parent_project_v1280();
		}

		if ( version_compare( (string) $installed, '1.29.0', '<' ) ) {
			self::upgrade_remove_client_nested_projects_v1290();
		}

		if ( version_compare( (string) $installed, '1.30.0', '<' ) ) {
			self::upgrade_prospects_v1300();
		}

		if ( version_compare( (string) $installed, '1.31.0', '<' ) ) {
			self::upgrade_prospects_v1310();
		}

		if ( version_compare( (string) $installed, '1.32.0', '<' ) ) {
			self::upgrade_super_clients_v1320();
		}

		if ( version_compare( (string) $installed, '1.33.0', '<' ) ) {
			self::upgrade_super_client_brands_v1330();
		}

		if ( version_compare( (string) $installed, '1.34.0', '<' ) ) {
			self::upgrade_super_client_brand_agency_name_v1340();
		}

		if ( version_compare( (string) $installed, '1.35.0', '<' ) ) {
			self::upgrade_super_client_request_date_index_v1350();
		}

		if ( version_compare( (string) $installed, '1.36.0', '<' ) ) {
			self::deactivate_compensatory_leave_v1360();
		}

		if ( version_compare( (string) $installed, '1.37.0', '<' ) ) {
			self::upgrade_permission_leave_v1370();
		}

		if ( version_compare( (string) $installed, '1.38.0', '<' ) ) {
			self::upgrade_permission_balance_only_v1380();
		}

		if ( version_compare( (string) $installed, '1.39.0', '<' ) ) {
			self::upgrade_product_tab_units_and_request_status_v1390();
		}

		update_option( 'etwa_db_version', self::VERSION );
	}

	private static function prospects_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_prospects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			source varchar(50) DEFAULT NULL,
			prospect_start_date date DEFAULT NULL,
			won_lost_at datetime DEFAULT NULL,
			lost_reason text DEFAULT NULL,
			converted_client_id bigint(20) unsigned DEFAULT NULL,
			converted_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY converted_client_id (converted_client_id),
			KEY status (status),
			KEY source (source),
			KEY prospect_start_date (prospect_start_date)
		) {$charset};";
	}

	/**
	 * Prospects table plus nullable prospect_id on entries.
	 * Additive only: existing entry rows are left unchanged.
	 */
	private static function upgrade_prospects_v1300(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::prospects_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );

		$entries = $wpdb->prefix . 'etwa_entries';
		self::add_column_if_missing( $entries, 'prospect_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_index_if_missing( $entries, 'prospect_id', 'prospect_id' );
	}

	/**
	 * Prospect source and user-managed start date.
	 * Additive only: existing prospect rows keep NULL values; created_at is unchanged.
	 */
	private static function upgrade_prospects_v1310(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'etwa_prospects';
		self::add_column_if_missing( $table, 'source', 'varchar(50) DEFAULT NULL' );
		self::add_column_if_missing( $table, 'prospect_start_date', 'date DEFAULT NULL' );
		self::add_index_if_missing( $table, 'source', 'source' );
		self::add_index_if_missing( $table, 'prospect_start_date', 'prospect_start_date' );
	}

	/**
	 * Super Client promotion flag on clients plus child-account and request tables.
	 * Additive only: existing clients remain standard type.
	 */
	private static function upgrade_super_clients_v1320(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$clients = $wpdb->prefix . 'etwa_clients';
		self::add_column_if_missing( $clients, 'client_type', "varchar(20) NOT NULL DEFAULT 'standard'" );
		self::add_index_if_missing( $clients, 'client_type', 'client_type' );

		$charset = $wpdb->get_charset_collate();
		dbDelta( self::super_client_accounts_sql( $wpdb->prefix, $charset ) );
		dbDelta( self::super_client_requests_sql( $wpdb->prefix, $charset ) );
	}

	private static function super_client_accounts_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_super_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			super_client_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			industry varchar(191) NOT NULL DEFAULT '',
			agency varchar(20) NOT NULL DEFAULT 'direct',
			agency_name varchar(191) NOT NULL DEFAULT '',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY super_client_id (super_client_id),
			KEY is_active (is_active)
		) {$charset};";
	}

	private static function super_client_requests_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_super_client_requests (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			super_client_id bigint(20) unsigned NOT NULL,
			brand_id bigint(20) unsigned NOT NULL,
			request_type varchar(30) NOT NULL,
			service_category varchar(30) NOT NULL,
			service varchar(191) NOT NULL,
			contact_name varchar(191) NOT NULL DEFAULT '',
			contact_email varchar(191) NOT NULL DEFAULT '',
			contact_phone varchar(50) NOT NULL DEFAULT '',
			invoice_number varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY super_client_id (super_client_id),
			KEY brand_id (brand_id),
			KEY super_request_created (super_client_id, created_at),
			KEY super_request_type (super_client_id, request_type),
			KEY super_service_category (super_client_id, service_category),
			KEY super_request_status (super_client_id, status)
		) {$charset};";
	}

	private static function super_client_units_sql( string $p, string $charset ): string {
		return "CREATE TABLE {$p}etwa_super_client_units (
			super_client_id bigint(20) unsigned NOT NULL,
			department_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (super_client_id, department_id),
			KEY department_id (department_id)
		) {$charset};";
	}

	/**
	 * Department Product Tab access plus request status workflow.
	 * Additive: existing request rows are marked published (they were complete one-step records).
	 */
	private static function upgrade_product_tab_units_and_request_status_v1390(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$requests = $wpdb->prefix . 'etwa_super_client_requests';

		dbDelta( self::super_client_units_sql( $wpdb->prefix, $charset ) );

		$status_was_missing = self::table_exists( $requests ) && ! self::column_exists( $requests, 'status' );
		if ( self::table_exists( $requests ) ) {
			self::add_column_if_missing( $requests, 'status', "varchar(20) NOT NULL DEFAULT 'pending'" );
			self::add_index_if_missing( $requests, 'super_request_status', 'super_client_id, status' );
			dbDelta( self::super_client_requests_sql( $wpdb->prefix, $charset ) );
		}

		if ( $status_was_missing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$requests} SET status = 'published' WHERE status = 'pending' OR status = ''" );
		}
	}

	/**
	 * Restructure Super Client child rows as Brands and simplify requests.
	 * Additive migration: preserves existing brand/request rows where possible.
	 */
	private static function upgrade_super_client_brand_agency_name_v1340(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$brands = $wpdb->prefix . 'etwa_super_clients';
		if ( self::table_exists( $brands ) ) {
			self::add_column_if_missing( $brands, 'agency_name', "varchar(191) NOT NULL DEFAULT ''" );
			dbDelta( self::super_client_accounts_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );
		}
	}

	private static function upgrade_super_client_request_date_index_v1350(): void {
		global $wpdb;

		$requests = $wpdb->prefix . 'etwa_super_client_requests';
		if ( self::table_exists( $requests ) ) {
			self::add_index_if_missing( $requests, 'super_request_created', 'super_client_id, created_at' );
		}
	}

	/**
	 * Soft-remove Compensatory Leave from leave-type pickers.
	 * Keeps the row so historical vacation requests remain valid.
	 */
	private static function deactivate_compensatory_leave_v1360(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'etwa_leave_types';
		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$wpdb->update(
			$table,
			array( 'is_active' => 0 ),
			array( 'slug' => 'compensatory' ),
			array( '%d' ),
			array( '%s' )
		);
	}

	private static function upgrade_super_client_brands_v1330(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$brands   = $wpdb->prefix . 'etwa_super_clients';
		$requests = $wpdb->prefix . 'etwa_super_client_requests';

		if ( self::table_exists( $brands ) ) {
			self::add_column_if_missing( $brands, 'industry', "varchar(191) NOT NULL DEFAULT ''" );
			self::add_column_if_missing( $brands, 'agency', "varchar(20) NOT NULL DEFAULT 'direct'" );

			// Prefer legacy brand column as the display name when present.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( self::column_exists( $brands, 'brand' ) ) {
				$wpdb->query(
					"UPDATE {$brands} SET name = brand WHERE brand IS NOT NULL AND brand != ''"
				);
			}

			self::drop_index_if_exists( $brands, 'super_client_code' );
			foreach ( array( 'code', 'brand', 'contact_name', 'contact_email', 'contact_phone' ) as $column ) {
				self::drop_column_if_exists( $brands, $column );
			}

			dbDelta( self::super_client_accounts_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );
		}

		if ( self::table_exists( $requests ) ) {
			if ( self::column_exists( $requests, 'child_client_id' ) && ! self::column_exists( $requests, 'brand_id' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					"ALTER TABLE {$requests} CHANGE child_client_id brand_id bigint(20) unsigned NOT NULL"
				);
				self::drop_index_if_exists( $requests, 'child_client_id' );
				self::add_index_if_missing( $requests, 'brand_id', 'brand_id' );
			}

			self::add_column_if_missing( $requests, 'service', "varchar(191) NOT NULL DEFAULT ''" );

			if ( self::column_exists( $requests, 'service_type' ) ) {
				$labels = \ETWA\Helpers\SuperClientRequestHelper::legacy_service_type_labels();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results( "SELECT id, service_type FROM {$requests} WHERE service_type != ''" );
				foreach ( $rows ?: array() as $row ) {
					$key = (string) $row->service_type;
					$service = $labels[ $key ] ?? $key;
					$wpdb->update(
						$requests,
						array( 'service' => $service ),
						array( 'id' => (int) $row->id )
					);
				}
				self::drop_column_if_exists( $requests, 'service_type' );
			}

			foreach ( array( 'brand', 'agency' ) as $column ) {
				self::drop_column_if_exists( $requests, $column );
			}

			dbDelta( self::super_client_requests_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );
		}
	}

	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration metadata.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$table,
				$column
			)
		);

		return (int) $exists > 0;
	}

	/**
	 * Standalone (clientless) projects and optional parent/child project hierarchy.
	 * Additive only: no existing row values are rewritten.
	 */
	private static function upgrade_standalone_projects_v1270(): void {
		global $wpdb;

		$entries  = $wpdb->prefix . 'etwa_entries';
		$projects = $wpdb->prefix . 'etwa_projects';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
		$wpdb->query( "ALTER TABLE {$entries} MODIFY client_id bigint(20) unsigned DEFAULT NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
		$wpdb->query( "ALTER TABLE {$projects} MODIFY client_id bigint(20) unsigned DEFAULT NULL" );

		self::add_column_if_missing( $projects, 'parent_project_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_index_if_missing( $projects, 'parent_project_id', 'parent_project_id' );
	}

	/**
	 * Optional Parent Project pointer on an existing Client row.
	 * Additive only: no existing row values are rewritten.
	 */
	private static function upgrade_client_parent_project_v1280(): void {
		global $wpdb;

		$clients = $wpdb->prefix . 'etwa_clients';
		self::add_column_if_missing( $clients, 'parent_project_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_index_if_missing( $clients, 'parent_project_id', 'parent_project_id' );
	}

	/**
	 * Remove nested-client pointer from clients; detach existing rows first.
	 */
	private static function upgrade_remove_client_nested_projects_v1290(): void {
		global $wpdb;

		$clients = $wpdb->prefix . 'etwa_clients';
		if ( ! self::table_exists( $clients ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
		$wpdb->query( "UPDATE {$clients} SET parent_project_id = NULL WHERE parent_project_id IS NOT NULL" );

		self::drop_index_if_exists( $clients, 'parent_project_id' );
		self::drop_column_if_exists( $clients, 'parent_project_id' );
	}

	/**
	 * Permanently remove packages, patches/batches, contract lines, content-calendar tables,
	 * and related scope/allocation columns. Runs after historical upgrade chain completes.
	 */
	private static function upgrade_legacy_feature_cleanup_v1260(): void {
		global $wpdb;

		$p = $wpdb->prefix;

		foreach (
			array(
				$p . 'etwa_scope_addons',
				$p . 'etwa_addons',
				$p . 'etwa_packages',
				$p . 'etwa_scope_patch_lines',
				$p . 'etwa_scope_patches',
				$p . 'etwa_scope_contract_lines',
			) as $table
		) {
			self::drop_table_if_exists( $table );
		}

		self::drop_orphan_etwa_tables();

		$scopes  = $p . 'etwa_client_scopes';
		$allocs  = $p . 'etwa_deliverable_allocations';
		$scope_columns = array( 'patch_count', 'expected_per_patch', 'package_id', 'package_snapshot' );
		foreach ( $scope_columns as $column ) {
			self::drop_column_if_exists( $scopes, $column );
		}

		$alloc_columns = array(
			'patch_id',
			'deliverable_type_id',
			'quantity',
			'scope_impact',
			'replaced_type_id',
			'replacement_qty',
		);
		foreach ( $alloc_columns as $column ) {
			self::drop_column_if_exists( $allocs, $column );
		}

		$settings = $p . 'etwa_settings';
		foreach (
			array(
				'autosave_interval',
				'min_overtime_minutes',
				'overtime_round_minutes',
				'dark_mode_default',
				'work_week_start',
				'work_week_end',
			) as $key
		) {
			$wpdb->delete( $settings, array( 'setting_key' => $key ), array( '%s' ) );
		}
	}

	/** Drop etwa_* tables not part of the current schema (e.g. removed content-calendar). */
	private static function drop_orphan_etwa_tables(): void {
		global $wpdb;

		$allowed = array(
			$wpdb->prefix . 'etwa_timesheets',
			$wpdb->prefix . 'etwa_entries',
			$wpdb->prefix . 'etwa_clients',
			$wpdb->prefix . 'etwa_projects',
			$wpdb->prefix . 'etwa_overtime',
			$wpdb->prefix . 'etwa_settings',
			$wpdb->prefix . 'etwa_audit_logs',
			$wpdb->prefix . 'etwa_holidays',
			$wpdb->prefix . 'etwa_leave_types',
			$wpdb->prefix . 'etwa_vacation_transactions',
			$wpdb->prefix . 'etwa_vacation_records',
			$wpdb->prefix . 'etwa_vacation_requests',
			$wpdb->prefix . 'etwa_requests',
			$wpdb->prefix . 'etwa_departments',
			$wpdb->prefix . 'etwa_deliverable_types',
			$wpdb->prefix . 'etwa_client_scopes',
			$wpdb->prefix . 'etwa_deliverable_allocations',
			$wpdb->prefix . 'etwa_deliverable_allocation_items',
			$wpdb->prefix . 'etwa_notification_log',
		);
		$allowed_map = array_fill_keys( $allowed, true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW TABLES pattern.
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'etwa_' ) . '%' ) );
		foreach ( $tables ?: array() as $table ) {
			if ( ! isset( $allowed_map[ $table ] ) ) {
				self::drop_table_if_exists( $table );
			}
		}
	}

	private static function drop_table_if_exists( string $table ): void {
		global $wpdb;
		if ( ! self::table_exists( $table ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SHOW TABLES.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	private static function drop_index_if_exists( string $table, string $index_name ): void {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration metadata.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
				DB_NAME,
				$table,
				$index_name
			)
		);

		if ( (int) $exists <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration.
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX {$index_name}" );
	}

	private static function drop_column_if_exists( string $table, string $column ): void {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration metadata.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$table,
				$column
			)
		);

		if ( (int) $exists <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL migration.
		$wpdb->query( "ALTER TABLE {$table} DROP COLUMN {$column}" );
	}

	/**
	 * Fold batch planning into contract totals, soft-retire patches, add packages/add-ons.
	 */
	private static function upgrade_packages_and_retire_batches_v1230(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;
		$scopes  = $p . 'etwa_client_scopes';
		$patches = $p . 'etwa_scope_patches';

		foreach ( self::packages_sql_statements( $p, $charset ) as $sql ) {
			dbDelta( $sql );
		}

		self::add_column_if_missing( $scopes, 'package_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_column_if_missing( $scopes, 'package_snapshot', 'longtext DEFAULT NULL' );
		self::add_index_if_missing( $scopes, 'package_id', 'package_id' );

		// Fold batch contribution into contract_deliverables so Planned stays continuous.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scope_rows = $wpdb->get_results( "SELECT id, contract_deliverables, patch_count, expected_per_patch FROM {$scopes}" );
		foreach ( $scope_rows ?: array() as $scope ) {
			$scope_id   = (int) $scope->id;
			$contract   = (float) $scope->contract_deliverables;
			$patch_count = (int) $scope->patch_count;
			$expected   = (float) $scope->expected_per_patch;
			$folded     = $contract + ( $patch_count * $expected );
			$wpdb->update(
				$scopes,
				array(
					'contract_deliverables' => $folded,
					'patch_count'           => 0,
					'expected_per_patch'    => 0,
				),
				array( 'id' => $scope_id ),
				array( '%f', '%d', '%f' ),
				array( '%d' )
			);
		}

		// Soft-retire historical batch rows (tables retained for audit).
		if ( self::table_exists( $patches ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$patches} SET status = 'void' WHERE status <> 'void'" );
		}
	}

	/**
	 * Projects, entry/scope project_id, notification log, client category migration.
	 */
	private static function upgrade_projects_and_notifications_v1240(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$projects_sql = "CREATE TABLE {$p}etwa_projects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL,
			description text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY client_status (client_id, status)
		) {$charset};";
		dbDelta( $projects_sql );

		$log_sql = "CREATE TABLE {$p}etwa_notification_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			notification_type varchar(50) NOT NULL,
			entity_type varchar(50) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			threshold_key varchar(20) NOT NULL DEFAULT '',
			recipient_hash varchar(64) NOT NULL DEFAULT '',
			sent_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedup (notification_type, entity_type, entity_id, threshold_key)
		) {$charset};";
		dbDelta( $log_sql );

		$entries = $p . 'etwa_entries';
		$scopes  = $p . 'etwa_client_scopes';
		self::add_column_if_missing( $entries, 'project_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_column_if_missing( $scopes, 'project_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_index_if_missing( $entries, 'client_project', 'client_id, project_id' );
		self::add_index_if_missing( $scopes, 'project_id', 'project_id' );

		$clients = $p . 'etwa_clients';
		$event   = \ETWA\Repositories\ClientRepository::EVENT_CODE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$clients} SET is_active = 0 WHERE code = %s",
				$event
			)
		);

		( new \ETWA\Repositories\ClientRepository() )->ensure_system_clients();
	}

	/**
	 * Backfill contract_deliverables from package snapshots before package logic is retired from active scope flow.
	 * Legacy package tables/columns are removed by upgrade_legacy_feature_cleanup_v1260().
	 */
	private static function upgrade_retire_packages_from_scope_logic_v1250(): void {
		global $wpdb;

		$scopes = $wpdb->prefix . 'etwa_client_scopes';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT id, contract_deliverables, package_snapshot FROM {$scopes}
			WHERE (contract_deliverables IS NULL OR contract_deliverables = 0)
			AND package_snapshot IS NOT NULL AND package_snapshot != ''"
		);

		foreach ( $rows ?: array() as $row ) {
			$snap = json_decode( (string) $row->package_snapshot, true );
			if ( ! is_array( $snap ) || ! isset( $snap['total_deliverables'] ) ) {
				continue;
			}
			$total = (float) $snap['total_deliverables'];
			if ( $total <= 0 ) {
				continue;
			}
			$wpdb->update(
				$scopes,
				array( 'contract_deliverables' => $total ),
				array( 'id' => (int) $row->id ),
				array( '%f' ),
				array( '%d' )
			);
		}
	}

	private static function upgrade_department_deliverables_v1200(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_departments';
		self::add_column_if_missing( $table, 'can_allocate_deliverables', 'tinyint(1) NOT NULL DEFAULT 0' );
	}

	/**
	 * Parent allocation + child items: task_name, items table, backfill, nullable legacy columns.
	 */
	private static function upgrade_multi_deliverable_allocations_v1210(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;
		$allocs  = $p . 'etwa_deliverable_allocations';
		$items   = $p . 'etwa_deliverable_allocation_items';

		foreach ( self::deliverables_sql_statements( $p, $charset ) as $sql ) {
			dbDelta( $sql );
		}

		self::add_column_if_missing( $allocs, 'task_name', "varchar(255) NOT NULL DEFAULT ''" );

		if ( self::column_exists( $allocs, 'deliverable_type_id' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
			$wpdb->query( "ALTER TABLE {$allocs} MODIFY deliverable_type_id bigint(20) unsigned DEFAULT NULL" );
		}
		if ( self::column_exists( $allocs, 'quantity' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
			$wpdb->query( "ALTER TABLE {$allocs} MODIFY quantity decimal(10,2) DEFAULT NULL" );
		}
		if ( self::column_exists( $allocs, 'scope_impact' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration DDL.
			$wpdb->query( "ALTER TABLE {$allocs} MODIFY scope_impact varchar(20) DEFAULT NULL" );
		}

		if (
			self::table_exists( $items )
			&& self::column_exists( $allocs, 'deliverable_type_id' )
			&& self::column_exists( $allocs, 'quantity' )
			&& self::column_exists( $allocs, 'scope_impact' )
		) {
			$now = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration backfill.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$items} (
						allocation_id, deliverable_type_id, quantity, scope_impact,
						replaced_type_id, replacement_qty, sort_order, created_at, updated_at
					)
					SELECT a.id, a.deliverable_type_id, a.quantity, COALESCE(a.scope_impact, 'normal'),
						a.replaced_type_id, a.replacement_qty, 0, %s, %s
					FROM {$allocs} a
					WHERE a.deliverable_type_id IS NOT NULL
						AND NOT EXISTS (
							SELECT 1 FROM {$items} i WHERE i.allocation_id = a.id
						)",
					$now,
					$now
				)
			);
		}
	}

	private static function upgrade_deliverables_contract_totals_v1190(): void {
		global $wpdb;
		$scopes  = $wpdb->prefix . 'etwa_client_scopes';
		$patches = $wpdb->prefix . 'etwa_scope_patches';
		$lines   = $wpdb->prefix . 'etwa_scope_contract_lines';

		self::add_column_if_missing( $scopes, 'contract_deliverables', 'decimal(10,2) NOT NULL DEFAULT 0.00' );
		self::add_column_if_missing( $scopes, 'patch_count', 'int(11) NOT NULL DEFAULT 0' );
		self::add_column_if_missing( $scopes, 'expected_per_patch', 'decimal(10,2) NOT NULL DEFAULT 0.00' );
		if ( self::table_exists( $patches ) ) {
			self::add_column_if_missing( $patches, 'expected_qty', 'decimal(10,2) NOT NULL DEFAULT 0.00' );
		}

		$patch_lines = $wpdb->prefix . 'etwa_scope_patch_lines';
		if ( self::table_exists( $patches ) && self::table_exists( $patch_lines ) ) {
			// Backfill patch expected_qty from legacy type deltas when still zero.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$zero_patches = $wpdb->get_results( "SELECT id FROM {$patches} WHERE expected_qty <= 0" );
			foreach ( $zero_patches ?: array() as $patch_row ) {
				$pid = (int) $patch_row->id;
				$sum = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COALESCE(SUM(ABS(qty_delta)), 0) FROM {$patch_lines} WHERE patch_id = %d",
						$pid
					)
				);
				if ( $sum > 0 ) {
					$wpdb->update(
						$patches,
						array( 'expected_qty' => $sum ),
						array( 'id' => $pid ),
						array( '%f' ),
						array( '%d' )
					);
				}
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scope_rows = $wpdb->get_results( "SELECT id, contract_deliverables, patch_count, expected_per_patch FROM {$scopes}" );
		foreach ( $scope_rows ?: array() as $scope ) {
			$scope_id = (int) $scope->id;
			$contract = (float) $scope->contract_deliverables;
			if ( $contract <= 0 && self::table_exists( $lines ) ) {
				$sum = (float) $wpdb->get_var(
					$wpdb->prepare( "SELECT COALESCE(SUM(planned_qty), 0) FROM {$lines} WHERE scope_id = %d", $scope_id )
				);
				if ( $sum > 0 ) {
					$wpdb->update(
						$scopes,
						array( 'contract_deliverables' => $sum ),
						array( 'id' => $scope_id ),
						array( '%f' ),
						array( '%d' )
					);
				}
			}

			$patch_count = (int) $scope->patch_count;
			$expected    = (float) $scope->expected_per_patch;
			if ( $patch_count <= 0 && self::table_exists( $patches ) ) {
				$active = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(1) FROM {$patches} WHERE scope_id = %d AND status = %s",
						$scope_id,
						'active'
					)
				);
				if ( $active > 0 ) {
					$avg = (float) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COALESCE(AVG(expected_qty), 0) FROM {$patches} WHERE scope_id = %d AND status = %s",
							$scope_id,
							'active'
						)
					);
					$wpdb->update(
						$scopes,
						array(
							'patch_count'         => $active,
							'expected_per_patch'  => $avg > 0 ? $avg : $expected,
						),
						array( 'id' => $scope_id ),
						array( '%d', '%f' ),
						array( '%d' )
					);
				}
			}
		}
	}

	/** @return string[] */
	private static function deliverables_sql_statements( string $p, string $charset ): array {
		return array(
			"CREATE TABLE {$p}etwa_deliverable_types (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slug varchar(50) NOT NULL,
				name varchar(191) NOT NULL,
				description text DEFAULT NULL,
				effort_score decimal(8,2) NOT NULL DEFAULT 1.00,
				sort_order int(11) NOT NULL DEFAULT 0,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY is_active (is_active),
				KEY sort_order (sort_order)
			) {$charset};",
			"CREATE TABLE {$p}etwa_client_scopes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				client_id bigint(20) unsigned NOT NULL,
				project_id bigint(20) unsigned DEFAULT NULL,
				name varchar(191) NOT NULL,
				code varchar(50) DEFAULT NULL,
				description text DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				starts_on date DEFAULT NULL,
				ends_on date DEFAULT NULL,
				contract_deliverables decimal(10,2) NOT NULL DEFAULT 0.00,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY client_id (client_id),
				KEY status (status),
				KEY client_status (client_id, status),
				KEY project_id (project_id)
			) {$charset};",
			"CREATE TABLE {$p}etwa_deliverable_allocations (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scope_id bigint(20) unsigned NOT NULL,
				task_name varchar(255) NOT NULL DEFAULT '',
				completed_date date NOT NULL,
				notes text DEFAULT NULL,
				allocated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY scope_id (scope_id),
				KEY completed_date (completed_date)
			) {$charset};",
			"CREATE TABLE {$p}etwa_deliverable_allocation_items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				allocation_id bigint(20) unsigned NOT NULL,
				deliverable_type_id bigint(20) unsigned NOT NULL,
				quantity decimal(10,2) NOT NULL DEFAULT 0.00,
				scope_impact varchar(20) NOT NULL DEFAULT 'normal',
				replaced_type_id bigint(20) unsigned DEFAULT NULL,
				replacement_qty decimal(10,2) DEFAULT NULL,
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY allocation_id (allocation_id),
				KEY deliverable_type_id (deliverable_type_id),
				KEY scope_impact (scope_impact),
				KEY allocation_type (allocation_id, deliverable_type_id)
			) {$charset};",
		);
	}

	/** @return string[] */
	private static function packages_sql_statements( string $p, string $charset ): array {
		return array(
			"CREATE TABLE {$p}etwa_packages (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				description text DEFAULT NULL,
				total_deliverables decimal(10,2) NOT NULL DEFAULT 0.00,
				duration_days int(11) DEFAULT NULL,
				billing_period varchar(50) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY name (name)
			) {$charset};",
			"CREATE TABLE {$p}etwa_addons (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				description text DEFAULT NULL,
				quantity decimal(10,2) NOT NULL DEFAULT 0.00,
				deliverable_type_id bigint(20) unsigned DEFAULT NULL,
				unit_price decimal(12,2) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY deliverable_type_id (deliverable_type_id),
				KEY name (name)
			) {$charset};",
			"CREATE TABLE {$p}etwa_scope_addons (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scope_id bigint(20) unsigned NOT NULL,
				addon_id bigint(20) unsigned DEFAULT NULL,
				addon_snapshot longtext DEFAULT NULL,
				quantity decimal(10,2) NOT NULL DEFAULT 0.00,
				deliverable_type_id bigint(20) unsigned DEFAULT NULL,
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY scope_id (scope_id),
				KEY addon_id (addon_id),
				KEY deliverable_type_id (deliverable_type_id)
			) {$charset};",
		);
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

	public static function upgrade_permission_leave_v1370(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_vacation_records';

		self::add_column_if_missing( $table, 'source', 'varchar(30) DEFAULT NULL' );
		self::add_column_if_missing( $table, 'source_request_id', 'bigint(20) unsigned DEFAULT NULL' );
		self::add_column_if_missing( $table, 'balance_deducted', 'decimal(8,2) NOT NULL DEFAULT 0.00' );
		self::add_index_if_missing( $table, 'source_request', 'source_request_id' );
		self::add_index_if_missing( $table, 'source', 'source' );
	}

	/**
	 * Migrate legacy permission-linked vacation records to request-level balance tracking.
	 * Removes leave records that incorrectly locked timesheet days.
	 */
	public static function upgrade_permission_balance_only_v1380(): void {
		global $wpdb;

		$records_table = $wpdb->prefix . 'etwa_vacation_records';
		$requests_table = $wpdb->prefix . 'etwa_requests';
		$transactions_table = $wpdb->prefix . 'etwa_vacation_transactions';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_request_id, balance_deducted FROM {$records_table}
				WHERE source = %s AND source_request_id IS NOT NULL",
				\ETWA\Helpers\PermissionHelper::RECORD_SOURCE
			)
		);

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $row ) {
			$request_id = (int) $row->source_request_id;
			$record_id  = (int) $row->id;
			if ( $request_id <= 0 || $record_id <= 0 ) {
				continue;
			}

			$request = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, extra_data FROM {$requests_table} WHERE id = %d",
					$request_id
				)
			);
			if ( ! $request ) {
				$wpdb->delete( $records_table, array( 'id' => $record_id ), array( '%d' ) );
				continue;
			}

			$extra = \ETWA\Helpers\RequestHelper::decode_extra_data( (string) ( $request->extra_data ?? '' ) );

			if ( empty( $extra[ \ETWA\Helpers\PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] ) ) {
				$usage = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id, days FROM {$transactions_table}
						WHERE record_id = %d AND transaction_type = 'usage' LIMIT 1",
						$record_id
					)
				);
				if ( $usage ) {
					$extra[ \ETWA\Helpers\PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] = (int) $usage->id;
					$extra[ \ETWA\Helpers\PermissionHelper::EXTRA_BALANCE_DEDUCTED ]           = abs( (float) $usage->days );
					$wpdb->update(
						$transactions_table,
						array( 'record_id' => null ),
						array( 'id' => (int) $usage->id ),
						array( '%d' ),
						array( '%d' )
					);
				} elseif ( (float) $row->balance_deducted > 0 ) {
					$extra[ \ETWA\Helpers\PermissionHelper::EXTRA_BALANCE_DEDUCTED ] = (float) $row->balance_deducted;
				}
			}

			$extra[ \ETWA\Helpers\PermissionHelper::EXTRA_BALANCE_SYNCED ] = true;
			unset( $extra[ \ETWA\Helpers\PermissionHelper::EXTRA_VACATION_RECORD_ID ] );

			$wpdb->update(
				$requests_table,
				array( 'extra_data' => wp_json_encode( $extra ) ),
				array( 'id' => $request_id ),
				array( '%s' ),
				array( '%d' )
			);

			$wpdb->delete( $records_table, array( 'id' => $record_id ), array( '%d' ) );
		}
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
