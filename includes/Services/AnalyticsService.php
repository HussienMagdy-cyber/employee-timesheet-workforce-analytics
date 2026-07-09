<?php
/**
 * Analytics and KPI service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\RequestHelper;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\OvertimeRepository;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsService {

	public function __construct(
		private EntryRepository $entries = new EntryRepository(),
		private OvertimeRepository $overtime = new OvertimeRepository(),
		private SettingsRepository $settings = new SettingsRepository(),
	) {}

	/** @return array<string,mixed> */
	public function get_kpis( string $date_from, string $date_to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(regular_hours + overtime_hours), 0) AS total_hours,
					COALESCE(SUM(regular_hours), 0) AS regular_hours,
					COALESCE(SUM(overtime_hours), 0) AS overtime_hours,
					COUNT(DISTINCT user_id) AS active_employees
				FROM {$table}
				WHERE entry_date BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		$available_per_employee = (float) $this->settings->get( 'available_hours_week', 40 );
		$weeks = max( 1, $this->count_weeks( $date_from, $date_to ) );
		$available_total = $available_per_employee * max( 1, (int) $row->active_employees ) * $weeks;
		$total_hours     = (float) $row->total_hours;
		$utilization     = $available_total > 0 ? round( ( $total_hours / $available_total ) * 100, 1 ) : 0;

		return array(
			'total_hours'    => round( $total_hours, 2 ),
			'regular_hours'  => round( (float) $row->regular_hours, 2 ),
			'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
			'utilization_pct'=> $utilization,
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function get_top_clients( string $date_from, string $date_to, int $limit = 10, ?float $total_hours = null ): array {
		global $wpdb;
		$entries = $wpdb->prefix . 'etwa_entries';
		$clients = $wpdb->prefix . 'etwa_clients';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name, c.department,
					SUM(e.regular_hours + e.overtime_hours) AS total_hours,
					SUM(e.overtime_hours) AS overtime_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				WHERE e.entry_date BETWEEN %s AND %s AND c.code != %s
				GROUP BY c.id
				ORDER BY total_hours DESC
				LIMIT %d",
				$date_from,
				$date_to,
				ClientRepository::BREAK_CODE,
				$limit
			)
		);

		$total = null !== $total_hours ? max( 0.01, $total_hours ) : max( 0.01, (float) $this->get_kpis( $date_from, $date_to )['total_hours'] );

		return array_map(
			function ( $row ) use ( $total ) {
				$hours = (float) $row->total_hours;
				return array(
					'id'                 => (int) $row->id,
					'name'               => $row->name,
					'department'         => $row->department,
					'total_hours'        => round( $hours, 2 ),
					'overtime_hours'     => round( (float) $row->overtime_hours, 2 ),
					'consumption_pct'    => round( ( $hours / $total ) * 100, 1 ),
					'overtime_ratio_pct' => $hours > 0 ? round( ( (float) $row->overtime_hours / $hours ) * 100, 1 ) : 0,
				);
			},
			$rows ?: array()
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function get_employee_utilization( string $date_from, string $date_to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id,
					SUM(regular_hours) AS regular_hours,
					SUM(overtime_hours) AS overtime_hours,
					SUM(regular_hours + overtime_hours) AS worked_hours
				FROM {$table}
				WHERE entry_date BETWEEN %s AND %s
				GROUP BY user_id
				ORDER BY worked_hours DESC",
				$date_from,
				$date_to
			)
		);

		$available     = (float) $this->settings->get( 'available_hours_week', 40 );
		$weeks         = max( 1, $this->count_weeks( $date_from, $date_to ) );
		$capacity      = $available * $weeks;
		$user_ids      = array_map( fn( $row ) => (int) $row->user_id, $rows ?: array() );
		$names         = UserDisplayNames::map( $user_ids );
		$include_cost  = EmployeeCostHelper::can_view_labor_cost();
		$rates         = $include_cost ? EmployeeCostHelper::get_rates_batch_internal( $user_ids ) : array();
		return array_map(
			function ( $row ) use ( $capacity, $names, $include_cost, $rates ) {
				$user_id = (int) $row->user_id;
				$worked  = (float) $row->worked_hours;
				$pct     = $capacity > 0 ? round( ( $worked / $capacity ) * 100, 1 ) : 0;
				$item    = array(
					'user_id'         => $user_id,
					'display_name'    => UserDisplayNames::get( $names, $user_id ) ?: __( 'Unknown', 'etwa' ),
					'worked_hours'    => round( $worked, 2 ),
					'overtime_hours'  => round( (float) $row->overtime_hours, 2 ),
					'utilization_pct' => $pct,
					'status'          => $pct > 100 ? 'overloaded' : ( $pct < 60 ? 'underutilized' : 'normal' ),
				);
				if ( $include_cost ) {
					$item['labor_cost_egp'] = LaborCostCalculator::from_hours(
						(float) $row->regular_hours,
						(float) $row->overtime_hours,
						$rates[ $user_id ] ?? 0.0
					);
				}
				return $item;
			},
			$rows ?: array()
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function get_monthly_trends( string $date_from, string $date_to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(entry_date, '%%Y-%%m') AS month,
					SUM(regular_hours) AS regular_hours,
					SUM(overtime_hours) AS overtime_hours
				FROM {$table}
				WHERE entry_date BETWEEN %s AND %s
				GROUP BY month
				ORDER BY month ASC",
				$date_from,
				$date_to
			)
		);

		return array_map(
			fn( $row ) => array(
				'month'          => $row->month,
				'regular_hours'  => round( (float) $row->regular_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
			),
			$rows ?: array()
		);
	}

	/** @return array<string,mixed> */
	public function get_insights( string $date_from, string $date_to, ?array $clients = null, ?array $employees = null ): array {
		$clients   = $clients ?? $this->get_top_clients( $date_from, $date_to, 5 );
		$employees = $employees ?? $this->get_employee_utilization( $date_from, $date_to );

		return array(
			'top_clients'            => array_slice( $clients, 0, 3 ),
			'overloaded_employees'   => array_values( array_filter( $employees, fn( $e ) => 'overloaded' === $e['status'] ) ),
			'underutilized'          => array_values( array_filter( $employees, fn( $e ) => 'underutilized' === $e['status'] ) ),
			'client_overtime'        => array_values(
				array_filter( $clients, fn( $c ) => $c['overtime_ratio_pct'] > 20 )
			),
		);
	}

	/** @return array<string,mixed> */
	public function get_advanced_dashboard( string $date_from, string $date_to, bool $use_cache = true ): array {
		$cache_key = 'etwa_adv_' . md5( $date_from . '|' . $date_to );
		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$kpis        = $this->get_kpis( $date_from, $date_to );
		$total       = (float) $kpis['total_hours'];
		$top_clients = $this->get_top_clients( $date_from, $date_to, 20, $total );
		$employees   = $this->get_employee_utilization( $date_from, $date_to );
		$trends      = $this->get_monthly_trends( $date_from, $date_to );
		$departments = $this->get_department_performance( $date_from, $date_to );

		$data = array(
			'kpis'                   => $kpis,
			'top_clients'            => array_slice( $top_clients, 0, 10 ),
			'client_consumption'     => $top_clients,
			'employee_utilization'   => $employees,
			'monthly_trends'         => $trends,
			'overtime_trends'        => $trends,
			'department_performance' => $departments,
			'insights'               => $this->get_insights( $date_from, $date_to, array_slice( $top_clients, 0, 5 ), $employees ),
			'extended_metrics'       => $this->get_extended_metrics( $date_from, $date_to ),
		);

		if ( $use_cache ) {
			set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );
		}

		return $data;
	}

	public static function flush_dashboard_cache(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_etwa_adv_%'
			OR option_name LIKE '_transient_timeout_etwa_adv_%'"
		);
		ClientAnalyticsService::flush_cache();
	}

	/** @return array<string,mixed> */
	public function get_dashboard_bundle( string $date_from, string $date_to ): array {
		$kpis      = $this->get_kpis( $date_from, $date_to );
		$total     = (float) $kpis['total_hours'];
		$clients   = $this->get_top_clients( $date_from, $date_to, 10, $total );
		$employees = $this->get_employee_utilization( $date_from, $date_to );

		return array(
			'kpis'      => $kpis,
			'top_clients' => $clients,
			'employees' => $employees,
			'trends'    => $this->get_monthly_trends( $date_from, $date_to ),
		);
	}

	/** @return array<string,mixed> */
	public function get_department_performance( string $date_from, string $date_to ): array {
		global $wpdb;
		$entries = $wpdb->prefix . 'etwa_entries';
		$clients = $wpdb->prefix . 'etwa_clients';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.department,
					SUM(e.regular_hours + e.overtime_hours) AS total_hours,
					SUM(e.overtime_hours) AS overtime_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				WHERE e.entry_date BETWEEN %s AND %s AND c.code != %s
				GROUP BY c.department
				ORDER BY total_hours DESC",
				$date_from,
				$date_to,
				ClientRepository::BREAK_CODE
			)
		);

		return array_map(
			fn( $row ) => array(
				'department'     => $row->department ?: __( 'Unassigned', 'etwa' ),
				'total_hours'    => round( (float) $row->total_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
			),
			$rows ?: array()
		);
	}

	private function count_weeks( string $date_from, string $date_to ): int {
		$start = new \DateTimeImmutable( $date_from );
		$end   = new \DateTimeImmutable( $date_to );
		$days  = (int) $start->diff( $end )->days + 1;
		return max( 1, (int) ceil( $days / 7 ) );
	}

	/**
	 * Extended workforce metrics (weekend/holiday/event/meeting hours, work on site).
	 *
	 * @param array{user_id?:int,client_id?:int,department?:string} $filters
	 * @return array<string,mixed>
	 */
	public function get_extended_metrics( string $date_from, string $date_to, array $filters = array() ): array {
		global $wpdb;

		$entries  = $wpdb->prefix . 'etwa_entries';
		$clients  = $wpdb->prefix . 'etwa_clients';
		$holidays = $wpdb->prefix . 'etwa_holidays';
		$requests = $wpdb->prefix . 'etwa_requests';

		$where_parts = array( 'e.entry_date BETWEEN %s AND %s' );
		$params      = array( $date_from, $date_to );

		if ( ! empty( $filters['user_id'] ) ) {
			$where_parts[] = 'e.user_id = %d';
			$params[]      = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['client_id'] ) ) {
			$where_parts[] = 'e.client_id = %d';
			$params[]      = (int) $filters['client_id'];
		}
		if ( ! empty( $filters['department'] ) ) {
			$where_parts[] = 'c.department = %s';
			$params[]      = sanitize_text_field( (string) $filters['department'] );
		}

		$where_sql = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE with prepared params.
		$hours_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(CASE WHEN c.code = %s THEN e.regular_hours + e.overtime_hours ELSE 0 END), 0) AS meeting_hours,
					COALESCE(SUM(CASE WHEN c.code = %s THEN e.regular_hours + e.overtime_hours ELSE 0 END), 0) AS event_hours,
					COALESCE(SUM(CASE WHEN DAYOFWEEK(e.entry_date) IN (1, 7) AND c.code != %s THEN e.regular_hours + e.overtime_hours ELSE 0 END), 0) AS weekend_hours,
					COALESCE(SUM(CASE WHEN h.id IS NOT NULL AND c.code != %s THEN e.regular_hours + e.overtime_hours ELSE 0 END), 0) AS holiday_hours
				FROM {$entries} e
				INNER JOIN {$clients} c ON c.id = e.client_id
				LEFT JOIN {$holidays} h ON h.holiday_date = e.entry_date
				WHERE {$where_sql}",
				array_merge(
					array(
						ClientRepository::MEETING_CODE,
						ClientRepository::EVENT_CODE,
						ClientRepository::BREAK_CODE,
						ClientRepository::BREAK_CODE,
					),
					$params
				)
			)
		);

		$work_on_site_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(e.regular_hours + e.overtime_hours), 0)
				FROM {$requests} r
				INNER JOIN {$entries} e ON e.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(r.extra_data, '$.timesheet_entry_id')) AS UNSIGNED)
				WHERE r.request_type = %s
				AND r.status = 'approved'
				AND JSON_UNQUOTE(JSON_EXTRACT(r.extra_data, '$.work_type')) = 'work_on_site'
				AND r.work_date BETWEEN %s AND %s",
				RequestHelper::TYPE_HOLIDAY_WORK,
				$date_from,
				$date_to
			)
		);

		return array(
			'weekend_work_hours' => round( (float) ( $hours_row->weekend_hours ?? 0 ), 2 ),
			'holiday_work_hours' => round( (float) ( $hours_row->holiday_hours ?? 0 ), 2 ),
			'event_hours'        => round( (float) ( $hours_row->event_hours ?? 0 ), 2 ),
			'meeting_hours'      => round( (float) ( $hours_row->meeting_hours ?? 0 ), 2 ),
			'work_on_site_hours' => round( $work_on_site_hours, 2 ),
		);
	}
}
