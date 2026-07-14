<?php
/**
 * Client analytics aggregation service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EntryTypeHelper;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SettingsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientAnalyticsService {

	private const CACHE_TTL  = 300;
	private const PAGE_SIZE  = 25;

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private SettingsRepository $settings = new SettingsRepository(),
	) {}

	/** @return array{preset:string,date_from:string,date_to:string,label:string} */
	public function resolve_range( ?string $preset, ?string $date_from, ?string $date_to ): array {
		return DateRangeHelper::resolve( $preset, $date_from, $date_to );
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,range:array<string,string>}|WP_Error
	 */
	public function list_clients(
		string $date_from,
		string $date_to,
		int $page = 1,
		int $per_page = self::PAGE_SIZE,
		string $search = '',
		bool $use_cache = true
	): array|WP_Error {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );
		$cache_key = 'etwa_ca_list_' . md5( "{$date_from}|{$date_to}|{$page}|{$per_page}|{$search}" );

		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$entries = $wpdb->prefix . 'etwa_entries';
		$clients = $wpdb->prefix . 'etwa_clients';

		$where  = "e.entry_date BETWEEN %s AND %s AND c.code != %s";
		$params = array( $date_from, $date_to, ClientRepository::BREAK_CODE );

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (c.name LIKE %s OR c.code LIKE %s OR c.department LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE only.
		$count_sql = "SELECT COUNT(DISTINCT c.id) FROM {$entries} e
			INNER JOIN {$clients} c ON c.id = e.client_id
			WHERE {$where}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		$offset   = ( $page - 1 ) * $per_page;
		$list_sql = "SELECT c.id, c.name, c.code, c.department,
				SUM(e.regular_hours + e.overtime_hours) AS total_hours,
				SUM(e.regular_hours) AS regular_hours,
				SUM(e.overtime_hours) AS overtime_hours,
				COUNT(DISTINCT e.user_id) AS employee_count,
				MAX(e.entry_date) AS last_activity
			FROM {$entries} e
			INNER JOIN {$clients} c ON c.id = e.client_id
			WHERE {$where}
			GROUP BY c.id
			ORDER BY total_hours DESC
			LIMIT %d OFFSET %d";

		$list_params   = array_merge( $params, array( $per_page, $offset ) );
		$rows          = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ) );
		$client_ids    = array_map( fn( $row ) => (int) $row->id, $rows ?: array() );
		$labor_by_client = $this->labor_costs_for_clients( $client_ids, $date_from, $date_to );
		$include_cost  = EmployeeCostHelper::can_view_labor_cost();

		$items = array();
		foreach ( $rows ?: array() as $row ) {
			$id = (int) $row->id;
			$item = array(
				'id'              => $id,
				'name'            => $row->name,
				'code'            => $row->code,
				'department'      => $row->department,
				'total_hours'     => round( (float) $row->total_hours, 2 ),
				'regular_hours'   => round( (float) $row->regular_hours, 2 ),
				'overtime_hours'  => round( (float) $row->overtime_hours, 2 ),
				'employee_count'  => (int) $row->employee_count,
				'last_activity'   => $row->last_activity ?: null,
			);
			if ( $include_cost ) {
				$item['labor_cost_egp'] = round( $labor_by_client[ $id ] ?? 0.0, 2 );
			}
			$items[] = $item;
		}

		$result = array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'range'    => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
		);

		if ( $use_cache ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return $result;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_client_detail(
		int $client_id,
		string $date_from,
		string $date_to,
		bool $use_cache = true
	): array|WP_Error {
		$client = $this->clients->find( $client_id );
		if ( ! $client || ClientRepository::BREAK_CODE === strtoupper( $client->code ) ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$cache_key = 'etwa_ca_detail_' . md5( "{$client_id}|{$date_from}|{$date_to}" );
		if ( $use_cache ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$entries = $wpdb->prefix . 'etwa_entries';

		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(regular_hours + overtime_hours), 0) AS total_hours,
					COALESCE(SUM(regular_hours), 0) AS regular_hours,
					COALESCE(SUM(overtime_hours), 0) AS overtime_hours,
					COALESCE(SUM(CASE WHEN entry_type = %s THEN duration_minutes ELSE 0 END), 0) AS compensation_minutes,
					COUNT(DISTINCT user_id) AS employee_count,
					MAX(entry_date) AS last_activity
				FROM {$entries}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s",
				EntryTypeHelper::COMPENSATION,
				$client_id,
				$date_from,
				$date_to
			)
		);

		$employee_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id,
					SUM(regular_hours) AS regular_hours,
					SUM(overtime_hours) AS overtime_hours,
					SUM(regular_hours + overtime_hours) AS total_hours,
					SUM(CASE WHEN entry_type = %s THEN duration_minutes ELSE 0 END) AS compensation_minutes
				FROM {$entries}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s
				GROUP BY user_id
				ORDER BY total_hours DESC",
				EntryTypeHelper::COMPENSATION,
				$client_id,
				$date_from,
				$date_to
			)
		);

		$user_ids     = array_map( fn( $row ) => (int) $row->user_id, $employee_rows ?: array() );
		$names        = UserDisplayNames::map( $user_ids );
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$rates        = $include_cost ? EmployeeCostHelper::get_rates_batch_internal( $user_ids ) : array();
		$employees = array();
		$total_labor = 0.0;
		$total_ot_cost = 0.0;
		foreach ( $employee_rows ?: array() as $row ) {
			$user_id = (int) $row->user_id;
			$comp_hours = EntryTypeHelper::compensation_hours_from_minutes( (int) ( $row->compensation_minutes ?? 0 ) );
			$emp     = array(
				'user_id'             => $user_id,
				'display_name'        => UserDisplayNames::get( $names, $user_id ) ?: __( 'Unknown', 'etwa' ),
				'total_hours'         => round( (float) $row->total_hours, 2 ),
				'regular_hours'       => round( (float) $row->regular_hours, 2 ),
				'overtime_hours'      => round( (float) $row->overtime_hours, 2 ),
				'compensation_hours'  => $comp_hours,
				'combined_hours'      => round( (float) $row->regular_hours + (float) $row->overtime_hours, 2 ),
			);
			if ( $include_cost ) {
				$rate = $rates[ $user_id ] ?? 0.0;
				$cost = LaborCostCalculator::from_hours(
					(float) $row->regular_hours,
					(float) $row->overtime_hours,
					$rate
				);
				$ot_cost = LaborCostCalculator::overtime_cost_only( (float) $row->overtime_hours, $rate );
				$emp['labor_cost_egp']   = $cost;
				$emp['overtime_cost_egp'] = $ot_cost;
				$total_labor            += $cost;
				$total_ot_cost          += $ot_cost;
			}
			$employees[] = $emp;
		}

		$regular_hours  = round( (float) ( $summary->regular_hours ?? 0 ), 2 );
		$overtime_hours = round( (float) ( $summary->overtime_hours ?? 0 ), 2 );
		$comp_hours     = EntryTypeHelper::compensation_hours_from_minutes( (int) ( $summary->compensation_minutes ?? 0 ) );
		$combined_hours = round( $regular_hours + $overtime_hours, 2 );
		$ot_pct         = $combined_hours > 0 ? round( ( $overtime_hours / $combined_hours ) * 100, 1 ) : 0.0;

		$trend = $this->hours_trend( $client_id, $date_from, $date_to );
		$recent = $this->recent_timesheets( $client_id, $date_from, $date_to, 10 );

		$data = array(
			'client'     => $client->to_array(),
			'summary'    => array(
				'total_hours'          => $combined_hours,
				'regular_hours'        => $regular_hours,
				'overtime_hours'       => $overtime_hours,
				'combined_hours'       => $combined_hours,
				'compensation_hours'   => $comp_hours,
				'overtime_percentage'  => $ot_pct,
				'employee_count'       => (int) ( $summary->employee_count ?? 0 ),
				'last_activity'        => $summary->last_activity ?? null,
				'labor_cost_egp'       => $include_cost ? round( $total_labor, 2 ) : null,
				'overtime_cost_egp'    => $include_cost ? round( $total_ot_cost, 2 ) : null,
			),
			'employees'  => $employees,
			'trend'      => $trend,
			'recent'     => $recent,
			'range'      => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
		);

		if ( $use_cache ) {
			set_transient( $cache_key, $data, self::CACHE_TTL );
		}

		return $data;
	}

	/**
	 * @param int[] $client_ids
	 * @return array<int,float>
	 */
	private function labor_costs_for_clients( array $client_ids, string $date_from, string $date_to ): array {
		if ( ! $client_ids || ! EmployeeCostHelper::can_view_labor_cost() ) {
			return array();
		}

		global $wpdb;
		$entries = $wpdb->prefix . 'etwa_entries';
		$placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders.
		$sql = "SELECT client_id, user_id,
				SUM(regular_hours) AS regular_hours,
				SUM(overtime_hours) AS overtime_hours
			FROM {$entries}
			WHERE entry_date BETWEEN %s AND %s AND client_id IN ({$placeholders})
			GROUP BY client_id, user_id";

		$params = array_merge( array( $date_from, $date_to ), $client_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic IN.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$user_ids = array_map( fn( $row ) => (int) $row->user_id, $rows ?: array() );
		$rates    = EmployeeCostHelper::get_rates_batch_internal( $user_ids );
		$costs = array_fill_keys( $client_ids, 0.0 );
		foreach ( $rows ?: array() as $row ) {
			$cid = (int) $row->client_id;
			$costs[ $cid ] += LaborCostCalculator::from_hours(
				(float) $row->regular_hours,
				(float) $row->overtime_hours,
				$rates[ (int) $row->user_id ] ?? 0.0
			);
		}

		return $costs;
	}

	/** @return array<int,array<string,mixed>> */
	private function hours_trend( int $client_id, string $date_from, string $date_to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(entry_date, '%%Y-%%m') AS period,
					SUM(regular_hours) AS regular_hours,
					SUM(regular_hours + overtime_hours) AS total_hours,
					SUM(overtime_hours) AS overtime_hours
				FROM {$table}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s
				GROUP BY period
				ORDER BY period ASC",
				$client_id,
				$date_from,
				$date_to
			)
		);

		return array_map(
			fn( $row ) => array(
				'period'          => $row->period,
				'total_hours'     => round( (float) $row->total_hours, 2 ),
				'regular_hours'   => round( (float) $row->regular_hours, 2 ),
				'overtime_hours'  => round( (float) $row->overtime_hours, 2 ),
			),
			$rows ?: array()
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function recent_timesheets( int $client_id, string $date_from, string $date_to, int $limit ): array {
		global $wpdb;
		$entries    = $wpdb->prefix . 'etwa_entries';
		$timesheets = $wpdb->prefix . 'etwa_timesheets';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id AS timesheet_id, t.user_id, t.period_start, t.period_end, t.status,
					SUM(e.regular_hours + e.overtime_hours) AS client_hours
				FROM {$timesheets} t
				INNER JOIN {$entries} e ON e.timesheet_id = t.id
				WHERE e.client_id = %d AND e.entry_date BETWEEN %s AND %s
				GROUP BY t.id
				ORDER BY t.period_start DESC
				LIMIT %d",
				$client_id,
				$date_from,
				$date_to,
				$limit
			)
		);

		$user_ids = array_map( fn( $row ) => (int) $row->user_id, $rows ?: array() );
		$names    = UserDisplayNames::map( $user_ids );

		return array_map(
			function ( $row ) use ( $names ) {
				$user_id = (int) $row->user_id;
				return array(
					'timesheet_id'  => (int) $row->timesheet_id,
					'user_id'       => $user_id,
					'display_name'  => UserDisplayNames::get( $names, $user_id ) ?: __( 'Unknown', 'etwa' ),
					'period_start'  => $row->period_start,
					'period_end'    => $row->period_end,
					'status'        => $row->status,
					'client_hours'  => round( (float) $row->client_hours, 2 ),
				);
			},
			$rows ?: array()
		);
	}

	public static function flush_cache(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_etwa_ca_%'
			OR option_name LIKE '_transient_timeout_etwa_ca_%'"
		);
	}
}
