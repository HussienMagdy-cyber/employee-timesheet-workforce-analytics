<?php
/**
 * Centralized reporting query and calculation layer.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EntryTypeHelper;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Models\Entry;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\EntryRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportingService {

	public const REPORT_EMPLOYEE_TIMESHEET     = 'employee_timesheet';
	public const REPORT_CLIENT                 = 'client';
	public const REPORT_CLIENT_LABOR_COST      = 'client_labor_cost';
	public const REPORT_EMPLOYEE_HOURS_BY_CLIENT = 'employee_hours_by_client';
	public const REPORT_ALL_CLIENTS_COMPARISON   = 'all_clients_comparison';

	private const CHUNK_SIZE = 500;

	public function __construct(
		private EntryRepository $entries = new EntryRepository(),
		private ClientRepository $clients = new ClientRepository(),
	) {}

	/**
	 * @return array{preset:string,date_from:string,date_to:string,label:string}|WP_Error
	 */
	public function resolve_date_range( ?string $preset, ?string $date_from, ?string $date_to ): array|WP_Error {
		$range = DateRangeHelper::resolve( $preset, $date_from, $date_to );
		return $this->validate_range_bounds( $range );
	}

	/**
	 * @param array{preset:string,date_from:string,date_to:string,label:string} $range
	 * @return array{preset:string,date_from:string,date_to:string,label:string}|WP_Error
	 */
	private function validate_range_bounds( array $range ): array|WP_Error {
		$from = $range['date_from'];
		$to   = $range['date_to'];

		if ( ! $this->is_valid_date( $from ) || ! $this->is_valid_date( $to ) ) {
			return new WP_Error( 'invalid_date_range', __( 'Invalid date range.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $from > $to ) {
			return new WP_Error( 'invalid_date_range', __( 'Start date must be on or before end date.', 'etwa' ), array( 'status' => 400 ) );
		}

		return $range;
	}

	/**
	 * Employee timesheet detail report.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_employee_timesheet_report(
		int $user_id,
		string $date_from,
		string $date_to,
		?int $client_id = null
	): array|WP_Error {
		if ( $user_id <= 0 ) {
			return new WP_Error( 'invalid_employee', __( 'Please select an employee.', 'etwa' ), array( 'status' => 400 ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_employee', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( $client_id ) {
			$client_error = $this->validate_client_id( $client_id );
			if ( is_wp_error( $client_error ) ) {
				return $client_error;
			}
		}

		$rows     = $this->fetch_entry_rows( $date_from, $date_to, $user_id, $client_id );
		$summary  = $this->summarize_entry_rows( $rows );
		$employee = UserDisplayNames::get( UserDisplayNames::map( array( $user_id ) ), $user_id );

		return array(
			'report_type' => self::REPORT_EMPLOYEE_TIMESHEET,
			'range'       => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'employee'    => array(
				'id'           => $user_id,
				'display_name' => $employee,
			),
			'client_id'   => $client_id,
			'rows'        => $rows,
			'summary'     => $summary,
		);
	}

	/**
	 * Client activity report with entry-level detail.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_client_report( int $client_id, string $date_from, string $date_to ): array|WP_Error {
		$client = $this->validate_client_id( $client_id );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$rows    = $this->fetch_entry_rows( $date_from, $date_to, null, $client_id );
		$summary = $this->summarize_entry_rows( $rows );

		if ( EmployeeCostHelper::can_view_labor_cost() ) {
			$summary['total_labor_cost_egp'] = $this->calculate_labor_cost_from_rows( $rows );
		}

		return array(
			'report_type' => self::REPORT_CLIENT,
			'range'       => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'client'      => $client,
			'rows'        => $rows,
			'summary'     => $summary,
		);
	}

	/**
	 * Client labor cost summary by employee.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_client_labor_cost_report( int $client_id, string $date_from, string $date_to ): array|WP_Error {
		if ( ! EmployeeCostHelper::can_view_labor_cost() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to view labor cost data.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->validate_client_id( $client_id );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		global $wpdb;
		$entries_table  = $wpdb->prefix . 'etwa_entries';
		$projects_table = $wpdb->prefix . 'etwa_projects';

		$employee_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id,
					COALESCE(SUM(regular_hours), 0) AS regular_hours,
					COALESCE(SUM(overtime_hours), 0) AS overtime_hours,
					COALESCE(SUM(regular_hours + overtime_hours), 0) AS total_hours
				FROM {$entries_table}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s
				GROUP BY user_id
				ORDER BY total_hours DESC",
				$client_id,
				$date_from,
				$date_to
			)
		);

		$user_ids = array_map( fn( $row ) => (int) $row->user_id, $employee_rows ?: array() );
		$names    = UserDisplayNames::map( $user_ids );
		$rates    = EmployeeCostHelper::get_rates_batch_internal( $user_ids );

		$rows            = array();
		$total_regular   = 0.0;
		$total_overtime  = 0.0;
		$total_hours     = 0.0;
		$total_labor     = 0.0;

		foreach ( $employee_rows ?: array() as $row ) {
			$user_id        = (int) $row->user_id;
			$regular        = round( (float) $row->regular_hours, 2 );
			$overtime       = round( (float) $row->overtime_hours, 2 );
			$combined       = round( (float) $row->total_hours, 2 );
			$hourly_cost    = round( $rates[ $user_id ] ?? 0.0, 2 );
			$labor_cost     = LaborCostCalculator::from_hours( $regular, $overtime, $hourly_cost );

			$rows[] = array(
				'employee_id'       => $user_id,
				'employee_name'     => UserDisplayNames::get( $names, $user_id ),
				'regular_hours'     => $regular,
				'overtime_hours'    => $overtime,
				'total_hours'       => $combined,
				'hourly_cost_egp'   => $hourly_cost,
				'total_labor_cost_egp' => $labor_cost,
			);

			$total_regular  += $regular;
			$total_overtime += $overtime;
			$total_hours    += $combined;
			$total_labor    += $labor_cost;
		}

		$project_rows_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.project_id,
					COALESCE(p.name, %s) AS project_name,
					COALESCE(SUM(e.regular_hours), 0) AS regular_hours,
					COALESCE(SUM(e.overtime_hours), 0) AS overtime_hours,
					COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours
				FROM {$entries_table} e
				LEFT JOIN {$projects_table} p ON p.id = e.project_id
				WHERE e.client_id = %d AND e.entry_date BETWEEN %s AND %s
				GROUP BY e.project_id
				ORDER BY total_hours DESC",
				ProjectService::unassigned_label(),
				$client_id,
				$date_from,
				$date_to
			)
		);

		$project_rows = array();
		foreach ( $project_rows_raw ?: array() as $row ) {
			$regular  = round( (float) $row->regular_hours, 2 );
			$overtime = round( (float) $row->overtime_hours, 2 );
			$item     = array(
				'project_id'     => $row->project_id ? (int) $row->project_id : null,
				'project_name'   => (string) $row->project_name,
				'regular_hours'  => $regular,
				'overtime_hours' => $overtime,
				'total_hours'    => round( (float) $row->total_hours, 2 ),
			);
			$project_rows[] = $item;
		}

		return array(
			'report_type' => self::REPORT_CLIENT_LABOR_COST,
			'range'       => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'client'      => $client,
			'rows'        => $rows,
			'project_rows'=> $project_rows,
			'summary'     => array(
				'total_regular_hours'  => round( $total_regular, 2 ),
				'total_overtime_hours' => round( $total_overtime, 2 ),
				'total_hours'          => round( $total_hours, 2 ),
				'total_labor_cost_egp' => round( $total_labor, 2 ),
			),
		);
	}

	/**
	 * Employee hours distribution across clients.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_employee_hours_by_client_report(
		string $date_from,
		string $date_to,
		?int $user_id = null,
		?int $client_id = null
	): array|WP_Error {
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return new WP_Error( 'invalid_employee', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
			}
		}

		if ( $client_id ) {
			$client_error = $this->validate_client_id( $client_id );
			if ( is_wp_error( $client_error ) ) {
				return $client_error;
			}
		}

		global $wpdb;
		$entries_table = $wpdb->prefix . 'etwa_entries';
		$clients_table = $wpdb->prefix . 'etwa_clients';
		$projects_table = $wpdb->prefix . 'etwa_projects';

		$sql    = "SELECT e.user_id, e.client_id, c.name AS client_name, e.project_id,
				COALESCE(p.name, %s) AS project_name,
				COALESCE(SUM(e.regular_hours), 0) AS regular_hours,
				COALESCE(SUM(e.overtime_hours), 0) AS overtime_hours,
				COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours
			FROM {$entries_table} e
			INNER JOIN {$clients_table} c ON c.id = e.client_id
			LEFT JOIN {$projects_table} p ON p.id = e.project_id
			WHERE e.entry_date BETWEEN %s AND %s
				AND c.code != %s";
		$params = array( ProjectService::unassigned_label(), $date_from, $date_to, ClientRepository::BREAK_CODE );

		if ( $user_id ) {
			$sql     .= ' AND e.user_id = %d';
			$params[] = $user_id;
		}
		if ( $client_id ) {
			$sql     .= ' AND e.client_id = %d';
			$params[] = $client_id;
		}

		$sql .= ' GROUP BY e.user_id, e.client_id, e.project_id ORDER BY e.user_id ASC, total_hours DESC';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$user_ids   = array();
		$client_map = array();
		foreach ( $results ?: array() as $row ) {
			$user_ids[] = (int) $row->user_id;
			$client_map[ (int) $row->client_id ] = (string) $row->client_name;
		}

		$names = UserDisplayNames::map( $user_ids );
		$rows  = array();

		foreach ( $results ?: array() as $row ) {
			$uid = (int) $row->user_id;
			$cid = (int) $row->client_id;
			$rows[] = array(
				'employee_id'    => $uid,
				'employee_name'  => UserDisplayNames::get( $names, $uid ),
				'client_id'      => $cid,
				'client_name'    => (string) $row->client_name,
				'project_id'     => $row->project_id ? (int) $row->project_id : null,
				'project_name'   => (string) $row->project_name,
				'regular_hours'  => round( (float) $row->regular_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
				'total_hours'    => round( (float) $row->total_hours, 2 ),
			);
		}

		$employee_summary = $this->build_employee_client_pivot( $rows, $client_map );

		return array(
			'report_type'       => self::REPORT_EMPLOYEE_HOURS_BY_CLIENT,
			'range'             => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'filter_user_id'    => $user_id,
			'filter_client_id'  => $client_id,
			'rows'              => $rows,
			'employee_summary'  => $employee_summary['rows'],
			'client_columns'    => $employee_summary['client_columns'],
		);
	}

	/**
	 * Side-by-side comparison of all clients for management reporting.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_all_clients_comparison_report(
		string $date_from,
		string $date_to,
		?int $department_id = null,
		?int $user_id = null
	): array|WP_Error {
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return new WP_Error( 'invalid_employee', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
			}
		}

		if ( $department_id ) {
			$dept = ( new \ETWA\Repositories\DepartmentRepository() )->find( $department_id );
			if ( ! $dept ) {
				return new WP_Error( 'invalid_department', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
			}
		}

		$user_filter = $this->resolve_comparison_user_filter( $department_id, $user_id );
		if ( is_wp_error( $user_filter ) ) {
			return $user_filter;
		}

		if ( is_array( $user_filter ) && ! $user_filter ) {
			return $this->empty_all_clients_comparison_report( $date_from, $date_to, $department_id, $user_id );
		}

		global $wpdb;
		$entries_table  = $wpdb->prefix . 'etwa_entries';
		$clients_table  = $wpdb->prefix . 'etwa_clients';
		$projects_table = $wpdb->prefix . 'etwa_projects';

		$where  = 'e.entry_date BETWEEN %s AND %s AND c.code != %s';
		$params = array( $date_from, $date_to, ClientRepository::BREAK_CODE );

		if ( is_array( $user_filter ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $user_filter ), '%d' ) );
			$where       .= " AND e.user_id IN ({$placeholders})";
			$params       = array_merge( $params, $user_filter );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE only.
		$client_sql = "SELECT c.id, c.name, c.code, c.department,
				COALESCE(SUM(e.regular_hours), 0) AS regular_hours,
				COALESCE(SUM(e.overtime_hours), 0) AS overtime_hours,
				COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours,
				COUNT(DISTINCT e.user_id) AS employee_count
			FROM {$entries_table} e
			INNER JOIN {$clients_table} c ON c.id = e.client_id
			WHERE {$where}
			GROUP BY c.id
			ORDER BY total_hours DESC";

		$client_rows = $wpdb->get_results( $wpdb->prepare( $client_sql, ...$params ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE only.
		$allocation_sql = "SELECT c.id AS client_id, c.name AS client_name, e.project_id,
				COALESCE(p.name, %s) AS project_name, e.user_id,
				COALESCE(SUM(e.regular_hours), 0) AS regular_hours,
				COALESCE(SUM(e.overtime_hours), 0) AS overtime_hours,
				COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours
			FROM {$entries_table} e
			INNER JOIN {$clients_table} c ON c.id = e.client_id
			LEFT JOIN {$projects_table} p ON p.id = e.project_id
			WHERE {$where}
			GROUP BY c.id, e.project_id, e.user_id
			ORDER BY c.id ASC, total_hours DESC";

		$allocation_params = array_merge( array( ProjectService::unassigned_label() ), $params );
		$allocation_rows   = $wpdb->get_results( $wpdb->prepare( $allocation_sql, ...$allocation_params ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE only.
		$project_sql = "SELECT c.id AS client_id, c.name AS client_name, e.project_id,
				COALESCE(p.name, %s) AS project_name,
				COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours,
				COUNT(DISTINCT e.user_id) AS employee_count
			FROM {$entries_table} e
			INNER JOIN {$clients_table} c ON c.id = e.client_id
			LEFT JOIN {$projects_table} p ON p.id = e.project_id
			WHERE {$where}
			GROUP BY c.id, e.project_id
			ORDER BY total_hours DESC";

		$project_params = array_merge( array( ProjectService::unassigned_label() ), $params );
		$project_rows   = $wpdb->get_results( $wpdb->prepare( $project_sql, ...$project_params ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE only.
		$totals_sql = "SELECT
				COALESCE(SUM(e.regular_hours), 0) AS regular_hours,
				COALESCE(SUM(e.overtime_hours), 0) AS overtime_hours,
				COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours,
				COUNT(DISTINCT e.user_id) AS employee_count
			FROM {$entries_table} e
			INNER JOIN {$clients_table} c ON c.id = e.client_id
			WHERE {$where}";

		$totals_row = $wpdb->get_row( $wpdb->prepare( $totals_sql, ...$params ) );

		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$client_ids   = array_map( fn( $row ) => (int) $row->id, $client_rows ?: array() );
		$labor_costs  = $include_cost ? $this->labor_costs_for_client_rows( $allocation_rows ?: array() ) : array();

		$grand_regular  = round( (float) ( $totals_row->regular_hours ?? 0 ), 2 );
		$grand_overtime = round( (float) ( $totals_row->overtime_hours ?? 0 ), 2 );
		$grand_hours    = round( (float) ( $totals_row->total_hours ?? 0 ), 2 );
		$grand_labor    = $include_cost ? round( array_sum( $labor_costs ), 2 ) : 0.0;

		$rows = array();
		foreach ( $client_rows ?: array() as $row ) {
			$client_id     = (int) $row->id;
			$regular       = round( (float) $row->regular_hours, 2 );
			$overtime      = round( (float) $row->overtime_hours, 2 );
			$total_hours   = round( (float) $row->total_hours, 2 );
			$labor_cost    = $include_cost ? round( $labor_costs[ $client_id ] ?? 0.0, 2 ) : null;

			$item = array(
				'client_id'       => $client_id,
				'client_name'     => (string) $row->name,
				'client_code'     => (string) $row->code,
				'department'      => (string) $row->department,
				'employee_count'  => (int) $row->employee_count,
				'regular_hours'   => $regular,
				'overtime_hours'  => $overtime,
				'total_hours'     => $total_hours,
				'pct_total_hours' => $grand_hours > 0 ? round( ( $total_hours / $grand_hours ) * 100, 1 ) : 0.0,
			);

			if ( $include_cost ) {
				$item['labor_cost_egp']     = $labor_cost;
				$item['pct_labor_cost']     = $grand_labor > 0 ? round( ( ( $labor_cost ?? 0.0 ) / $grand_labor ) * 100, 1 ) : 0.0;
			}

			$rows[] = $item;
		}

		$employee_allocations = $this->build_client_employee_allocations( $allocation_rows ?: array(), $include_cost );

		$project_comparison = array();
		foreach ( $project_rows ?: array() as $row ) {
			$project_comparison[] = array(
				'client_id'       => (int) $row->client_id,
				'client_name'     => (string) $row->client_name,
				'project_id'      => $row->project_id ? (int) $row->project_id : null,
				'project_name'    => (string) $row->project_name,
				'total_hours'     => round( (float) $row->total_hours, 2 ),
				'employee_count'  => (int) $row->employee_count,
			);
		}

		$summary = array(
			'total_clients'        => count( $rows ),
			'total_employees'      => (int) ( $totals_row->employee_count ?? 0 ),
			'total_regular_hours'  => $grand_regular,
			'total_overtime_hours' => $grand_overtime,
			'total_hours'          => $grand_hours,
		);

		if ( $include_cost ) {
			$summary['total_labor_cost_egp'] = $grand_labor;
		}

		return array(
			'report_type'           => self::REPORT_ALL_CLIENTS_COMPARISON,
			'range'                 => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'filter_department_id'  => $department_id,
			'filter_user_id'        => $user_id,
			'can_view_labor_cost'   => $include_cost,
			'rows'                  => $rows,
			'employee_allocations'  => $employee_allocations,
			'project_comparison'    => $project_comparison,
			'summary'               => $summary,
		);
	}

	/** @return array{id:int,name:string,code:string,department:string}|WP_Error */
	private function validate_client_id( int $client_id ): array|WP_Error {
		if ( $client_id <= 0 ) {
			return new WP_Error( 'invalid_client', __( 'Please select a client.', 'etwa' ), array( 'status' => 400 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client || ClientRepository::BREAK_CODE === strtoupper( $client->code ) ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		return array(
			'id'         => (int) $client->id,
			'name'       => $client->name,
			'code'       => $client->code,
			'department' => $client->department,
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch_entry_rows(
		string $date_from,
		string $date_to,
		?int $user_id,
		?int $client_id
	): array {
		global $wpdb;
		$entries_table    = $wpdb->prefix . 'etwa_entries';
		$clients_table    = $wpdb->prefix . 'etwa_clients';
		$timesheets_table = $wpdb->prefix . 'etwa_timesheets';

		$rows    = array();
		$offset  = 0;

		do {
			$sql    = "SELECT e.*, c.name AS client_name, p.name AS project_name, t.status AS timesheet_status
				FROM {$entries_table} e
				LEFT JOIN {$clients_table} c ON c.id = e.client_id
				LEFT JOIN {$wpdb->prefix}etwa_projects p ON p.id = e.project_id
				LEFT JOIN {$timesheets_table} t ON t.id = e.timesheet_id
				WHERE e.entry_date BETWEEN %s AND %s";
			$params = array( $date_from, $date_to );

			if ( $user_id ) {
				$sql     .= ' AND e.user_id = %d';
				$params[] = $user_id;
			}
			if ( $client_id ) {
				$sql     .= ' AND e.client_id = %d';
				$params[] = $client_id;
			}

			$sql     .= ' ORDER BY e.entry_date ASC, e.start_time ASC, e.id ASC LIMIT %d OFFSET %d';
			$params[] = self::CHUNK_SIZE;
			$params[] = $offset;

			$chunk = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
			if ( ! $chunk ) {
				break;
			}

			$user_ids = array_map( fn( $row ) => (int) $row->user_id, $chunk );
			$names    = UserDisplayNames::map( $user_ids );

			foreach ( $chunk as $row ) {
				$entry = Entry::from_row( $row );
				$rows[] = $this->format_entry_row( $entry, $names, (string) ( $row->timesheet_status ?? '' ) );
			}

			$offset += self::CHUNK_SIZE;
		} while ( count( $chunk ) === self::CHUNK_SIZE );

		return $rows;
	}

	/**
	 * @param array<int,int> $names
	 * @return array<string,mixed>
	 */
	private function format_entry_row( Entry $entry, array $names, string $timesheet_status ): array {
		$day_info = DateHelper::get_day_info( $entry->entry_date );
		$regular  = round( (float) $entry->regular_hours, 2 );
		$overtime = round( (float) $entry->overtime_hours, 2 );

		return array(
			'employee_id'       => $entry->user_id,
			'employee_name'     => UserDisplayNames::get( $names, $entry->user_id ),
			'date'              => $entry->entry_date,
			'day'               => $day_info['name'],
			'client_id'         => $entry->client_id,
			'client_name'       => $entry->client_name ?? '',
			'project_id'        => $entry->project_id,
			'project_name'      => $entry->project_id ? ( $entry->project_name ?? '' ) : ProjectService::unassigned_label(),
			'activity'          => $entry->task,
			'entry_type'        => $entry->entry_type,
			'entry_type_label'  => EntryTypeHelper::label( $entry->entry_type ),
			'regular_hours'     => $regular,
			'overtime_hours'    => $overtime,
			'total_hours'       => round( $regular + $overtime, 2 ),
			'timesheet_status'  => $timesheet_status ?: 'draft',
			'start_time'        => DateHelper::format_time( $entry->start_time ),
			'end_time'          => DateHelper::format_time( $entry->end_time ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,mixed>
	 */
	private function summarize_entry_rows( array $rows ): array {
		$regular   = 0.0;
		$overtime  = 0.0;
		$employees = array();

		foreach ( $rows as $row ) {
			$regular  += (float) $row['regular_hours'];
			$overtime += (float) $row['overtime_hours'];
			$employees[ (int) $row['employee_id'] ] = true;
		}

		return array(
			'total_employees'      => count( $employees ),
			'total_regular_hours'  => round( $regular, 2 ),
			'total_overtime_hours' => round( $overtime, 2 ),
			'total_hours'          => round( $regular + $overtime, 2 ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 */
	private function calculate_labor_cost_from_rows( array $rows ): float {
		if ( ! $rows || ! EmployeeCostHelper::can_view_labor_cost() ) {
			return 0.0;
		}

		$user_ids = array_unique( array_map( fn( $row ) => (int) $row['employee_id'], $rows ) );
		$rates    = EmployeeCostHelper::get_rates_batch_internal( $user_ids );
		$total    = 0.0;

		foreach ( $rows as $row ) {
			$total += LaborCostCalculator::from_hours(
				(float) $row['regular_hours'],
				(float) $row['overtime_hours'],
				$rates[ (int) $row['employee_id'] ] ?? 0.0
			);
		}

		return round( $total, 2 );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @param array<int,string>              $client_map
	 * @return array{rows:array<int,array<string,mixed>>,client_columns:array<int,string>}
	 */
	private function build_employee_client_pivot( array $rows, array $client_map ): array {
		$client_columns = array_values( array_unique( $client_map ) );
		sort( $client_columns );

		$by_employee = array();
		foreach ( $rows as $row ) {
			$uid = (int) $row['employee_id'];
			if ( ! isset( $by_employee[ $uid ] ) ) {
				$by_employee[ $uid ] = array(
					'employee_id'   => $uid,
					'employee_name' => $row['employee_name'],
					'total_hours'   => 0.0,
					'clients'       => array(),
				);
			}
			$by_employee[ $uid ]['total_hours'] += (float) $row['total_hours'];
			$client_name = (string) $row['client_name'];
			$by_employee[ $uid ]['clients'][ $client_name ] = (float) ( $by_employee[ $uid ]['clients'][ $client_name ] ?? 0 ) + (float) $row['total_hours'];
		}

		$summary_rows = array();
		foreach ( $by_employee as $emp ) {
			$row = array(
				'employee_id'   => $emp['employee_id'],
				'employee_name' => $emp['employee_name'],
				'total_hours'   => round( $emp['total_hours'], 2 ),
			);
			foreach ( $client_columns as $client_name ) {
				$row[ $client_name ] = round( (float) ( $emp['clients'][ $client_name ] ?? 0 ), 2 );
			}
			$summary_rows[] = $row;
		}

		return array(
			'rows'           => $summary_rows,
			'client_columns' => $client_columns,
		);
	}

	private function is_valid_date( string $date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	/**
	 * @return int[]|null|WP_Error null means no user restriction.
	 */
	private function resolve_comparison_user_filter( ?int $department_id, ?int $user_id ): array|null|WP_Error {
		if ( $user_id ) {
			return array( $user_id );
		}

		if ( $department_id ) {
			return DepartmentHelper::employee_ids_in_department( $department_id );
		}

		return null;
	}

	/** @return array<string,mixed> */
	private function empty_all_clients_comparison_report(
		string $date_from,
		string $date_to,
		?int $department_id,
		?int $user_id
	): array {
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$summary      = array(
			'total_clients'        => 0,
			'total_employees'      => 0,
			'total_regular_hours'  => 0.0,
			'total_overtime_hours' => 0.0,
			'total_hours'          => 0.0,
		);

		if ( $include_cost ) {
			$summary['total_labor_cost_egp'] = 0.0;
		}

		return array(
			'report_type'           => self::REPORT_ALL_CLIENTS_COMPARISON,
			'range'                 => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			'filter_department_id'  => $department_id,
			'filter_user_id'        => $user_id,
			'can_view_labor_cost'   => $include_cost,
			'rows'                  => array(),
			'employee_allocations'  => array(),
			'summary'               => $summary,
		);
	}

	/**
	 * @param array<int,object> $allocation_rows
	 * @return array<int,float>
	 */
	private function labor_costs_for_client_rows( array $allocation_rows ): array {
		if ( ! $allocation_rows || ! EmployeeCostHelper::can_view_labor_cost() ) {
			return array();
		}

		$user_ids = array_unique( array_map( fn( $row ) => (int) $row->user_id, $allocation_rows ) );
		$rates    = EmployeeCostHelper::get_rates_batch_internal( $user_ids );
		$costs    = array();

		foreach ( $allocation_rows as $row ) {
			$client_id = (int) $row->client_id;
			$user_id   = (int) $row->user_id;
			$costs[ $client_id ] = ( $costs[ $client_id ] ?? 0.0 ) + LaborCostCalculator::from_hours(
				(float) $row->regular_hours,
				(float) $row->overtime_hours,
				$rates[ $user_id ] ?? 0.0
			);
		}

		return $costs;
	}

	/**
	 * @param array<int,object> $allocation_rows
	 * @return array<int,array<string,mixed>>
	 */
	private function build_client_employee_allocations( array $allocation_rows, bool $include_cost ): array {
		if ( ! $allocation_rows ) {
			return array();
		}

		$user_ids = array_map( fn( $row ) => (int) $row->user_id, $allocation_rows );
		$names    = UserDisplayNames::map( $user_ids );
		$rates    = $include_cost ? EmployeeCostHelper::get_rates_batch_internal( $user_ids ) : array();

		$by_client = array();
		foreach ( $allocation_rows as $row ) {
			$client_id = (int) $row->client_id;
			if ( ! isset( $by_client[ $client_id ] ) ) {
				$by_client[ $client_id ] = array(
					'client_id'   => $client_id,
					'client_name' => (string) $row->client_name,
					'employees'   => array(),
				);
			}

			$user_id  = (int) $row->user_id;
			$regular  = round( (float) $row->regular_hours, 2 );
			$overtime = round( (float) $row->overtime_hours, 2 );
			$total    = round( (float) $row->total_hours, 2 );

			$employee = array(
				'employee_id'    => $user_id,
				'employee_name'  => UserDisplayNames::get( $names, $user_id ),
				'project_id'     => isset( $row->project_id ) && $row->project_id ? (int) $row->project_id : null,
				'project_name'   => isset( $row->project_name ) ? (string) $row->project_name : ProjectService::unassigned_label(),
				'regular_hours'  => $regular,
				'overtime_hours' => $overtime,
				'total_hours'    => $total,
			);

			if ( $include_cost ) {
				$hourly = round( $rates[ $user_id ] ?? 0.0, 2 );
				$employee['hourly_cost_egp'] = $hourly;
				$employee['labor_cost_egp']  = LaborCostCalculator::from_hours( $regular, $overtime, $hourly );
			}

			$by_client[ $client_id ]['employees'][] = $employee;
		}

		return array_values( $by_client );
	}
}
