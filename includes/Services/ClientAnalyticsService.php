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
use ETWA\Helpers\UserDisplayNames;
use ETWA\Models\Project;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\ProjectRepository;
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
		private ProjectRepository $projects = new ProjectRepository(),
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
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
					COUNT(DISTINCT user_id) AS employee_count,
					MAX(entry_date) AS last_activity
				FROM {$entries}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s",
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
					SUM(regular_hours + overtime_hours) AS total_hours
				FROM {$entries}
				WHERE client_id = %d AND entry_date BETWEEN %s AND %s
				GROUP BY user_id
				ORDER BY total_hours DESC",
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
			$emp     = array(
				'user_id'        => $user_id,
				'display_name'   => UserDisplayNames::get( $names, $user_id ) ?: __( 'Unknown', 'etwa' ),
				'total_hours'    => round( (float) $row->total_hours, 2 ),
				'regular_hours'  => round( (float) $row->regular_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
				'combined_hours' => round( (float) $row->regular_hours + (float) $row->overtime_hours, 2 ),
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
				'overtime_percentage'  => $ot_pct,
				'employee_count'       => (int) ( $summary->employee_count ?? 0 ),
				'last_activity'        => $summary->last_activity ?? null,
				'labor_cost_egp'       => $include_cost ? round( $total_labor, 2 ) : null,
				'overtime_cost_egp'    => $include_cost ? round( $total_ot_cost, 2 ) : null,
			),
			'employees'  => $employees,
			'trend'      => $trend,
			'recent'     => $recent,
			'project_breakdown' => $this->project_breakdown( $client_id, $date_from, $date_to ),
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

	/** @return array<string,mixed>|WP_Error */
	public function get_project_detail( int $project_id, string $date_from, string $date_to ): array|WP_Error {
		$project = $this->projects->find( $project_id );
		if ( ! $project ) {
			return new WP_Error( 'not_found', __( 'Project not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$client = $project->client_id ? $this->clients->find( (int) $project->client_id ) : null;
		if ( $project->client_id && ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$parent = $project->parent_project_id ? $this->projects->find( (int) $project->parent_project_id ) : null;
		$ids    = $this->analytics_project_ids( $project );
		$in     = $this->in_clause( $ids );

		global $wpdb;
		$entries  = $wpdb->prefix . 'etwa_entries';
		$projects = $wpdb->prefix . 'etwa_projects';

		$summary = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- project_id IN uses %d placeholders.
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(regular_hours + overtime_hours), 0) AS total_hours,
					COALESCE(SUM(regular_hours), 0) AS regular_hours,
					COALESCE(SUM(overtime_hours), 0) AS overtime_hours,
					COUNT(DISTINCT user_id) AS employee_count,
					MAX(entry_date) AS last_activity
				FROM {$entries}
				WHERE project_id IN ({$in['placeholders']}) AND entry_date BETWEEN %s AND %s",
				...array_merge( $in['ids'], array( $date_from, $date_to ) )
			)
		);

		$direct_hours = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(regular_hours + overtime_hours), 0)
				FROM {$entries}
				WHERE project_id = %d AND entry_date BETWEEN %s AND %s",
				$project_id,
				$date_from,
				$date_to
			)
		);

		$employee_rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- project_id IN uses %d placeholders.
			$wpdb->prepare(
				"SELECT user_id,
					SUM(regular_hours) AS regular_hours,
					SUM(overtime_hours) AS overtime_hours,
					SUM(regular_hours + overtime_hours) AS total_hours
				FROM {$entries}
				WHERE project_id IN ({$in['placeholders']}) AND entry_date BETWEEN %s AND %s
				GROUP BY user_id
				ORDER BY total_hours DESC",
				...array_merge( $in['ids'], array( $date_from, $date_to ) )
			)
		);

		$user_ids  = array_map( fn( $row ) => (int) $row->user_id, $employee_rows ?: array() );
		$names     = UserDisplayNames::map( $user_ids );
		$employees = array();
		foreach ( $employee_rows ?: array() as $row ) {
			$user_id     = (int) $row->user_id;
			$employees[] = array(
				'user_id'        => $user_id,
				'display_name'   => UserDisplayNames::get( $names, $user_id ) ?: __( 'Unknown', 'etwa' ),
				'total_hours'    => round( (float) $row->total_hours, 2 ),
				'regular_hours'  => round( (float) $row->regular_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
				'combined_hours' => round( (float) $row->regular_hours + (float) $row->overtime_hours, 2 ),
			);
		}

		$recent_rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
			$wpdb->prepare(
				"SELECT e.*, c.name AS client_name, p.name AS project_name
				FROM {$entries} e
				LEFT JOIN {$wpdb->prefix}etwa_clients c ON c.id = e.client_id
				LEFT JOIN {$projects} p ON p.id = e.project_id
				WHERE e.project_id IN ({$in['placeholders']}) AND e.entry_date BETWEEN %s AND %s
				ORDER BY e.entry_date DESC, e.start_time DESC
				LIMIT 20",
				...array_merge( $in['ids'], array( $date_from, $date_to ) )
			)
		);

		$child_breakdown = array();
		if ( $project->is_standalone() && ! $project->parent_project_id ) {
			$breakdown_ids = array( (int) $project->id );
			foreach ( $this->projects->list_children( (int) $project->id, false ) as $child ) {
				$breakdown_ids[] = (int) $child->id;
			}
			$breakdown_ids = array_values( array_unique( $breakdown_ids ) );
			if ( count( $breakdown_ids ) > 1 ) {
				$in = $this->in_clause( $breakdown_ids );
				$child_rows = $wpdb->get_results(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
					$wpdb->prepare(
						"SELECT p.id AS project_id, p.name, p.parent_project_id,
						COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours
					FROM {$projects} p
					LEFT JOIN {$entries} e ON e.project_id = p.id AND e.entry_date BETWEEN %s AND %s
					WHERE p.id IN ({$in['placeholders']})
					GROUP BY p.id
					ORDER BY CASE WHEN p.id = %d THEN 0 ELSE 1 END, total_hours DESC, p.name ASC",
						...array_merge( array( $date_from, $date_to ), $in['ids'], array( $project_id ) )
					)
				);
				foreach ( $child_rows ?: array() as $row ) {
					$child_breakdown[] = array(
						'project_id'  => (int) $row->project_id,
						'name'        => (int) $row->project_id === $project_id
							? sprintf(
								/* translators: %s: parent project name */
								__( '%s (direct)', 'etwa' ),
								(string) $row->name
							)
							: (string) $row->name,
						'is_direct'   => (int) $row->project_id === $project_id,
						'total_hours' => round( (float) $row->total_hours, 2 ),
					);
				}
			}
		}

		$related_scopes = array();
		foreach ( $ids as $sid ) {
			foreach ( $this->scopes->list( array( 'project_id' => $sid ) ) as $scope ) {
				$related_scopes[] = $scope->to_array();
			}
		}

		$regular  = round( (float) ( $summary->regular_hours ?? 0 ), 2 );
		$overtime = round( (float) ( $summary->overtime_hours ?? 0 ), 2 );
		$combined = round( $regular + $overtime, 2 );

		return array(
			'project'    => $project->to_array(),
			'client'     => $client ? $client->to_array() : null,
			'parent_project' => $parent ? $parent->to_array() : null,
			'includes_children' => count( $ids ) > 1,
			'analytics_project_ids' => $ids,
			'summary'    => array(
				'total_hours'    => $combined,
				'regular_hours'  => $regular,
				'overtime_hours' => $overtime,
				'direct_hours'   => round( $direct_hours, 2 ),
				'child_hours'    => round( max( 0, $combined - $direct_hours ), 2 ),
				'employee_count' => (int) ( $summary->employee_count ?? 0 ),
				'last_activity'  => $summary->last_activity ?? null,
			),
			'employees'  => $employees,
			'child_breakdown' => $child_breakdown,
			'recent_entries' => array_map(
				static fn( $row ) => \ETWA\Models\Entry::from_row( $row )->to_api_array(),
				$recent_rows ?: array()
			),
			'scopes'     => $related_scopes,
			'range'      => array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
		);
	}

	/**
	 * Active standalone parent projects with rolled-up sub-project hours.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_standalone_parents( string $date_from, string $date_to ): array {
		global $wpdb;
		$entries  = $wpdb->prefix . 'etwa_entries';
		$projects = $wpdb->prefix . 'etwa_projects';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.name, p.status,
					COALESCE(h.total_hours, 0) AS total_hours,
					COALESCE(h.regular_hours, 0) AS regular_hours,
					COALESCE(h.overtime_hours, 0) AS overtime_hours,
					COALESCE(h.employee_count, 0) AS employee_count,
					h.last_activity
				FROM {$projects} p
				LEFT JOIN (
					SELECT COALESCE(pr.parent_project_id, pr.id) AS root_id,
						SUM(e.regular_hours + e.overtime_hours) AS total_hours,
						SUM(e.regular_hours) AS regular_hours,
						SUM(e.overtime_hours) AS overtime_hours,
						COUNT(DISTINCT e.user_id) AS employee_count,
						MAX(e.entry_date) AS last_activity
					FROM {$entries} e
					INNER JOIN {$projects} pr ON pr.id = e.project_id
					WHERE pr.client_id IS NULL
						AND e.entry_date BETWEEN %s AND %s
					GROUP BY root_id
				) h ON h.root_id = p.id
				WHERE p.client_id IS NULL AND p.parent_project_id IS NULL AND p.status = %s
				ORDER BY total_hours DESC, p.name ASC",
				$date_from,
				$date_to,
				Project::STATUS_ACTIVE
			)
		);

		$items = array();
		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'id'             => (int) $row->id,
				'name'           => (string) $row->name,
				'status'         => (string) $row->status,
				'total_hours'    => round( (float) $row->total_hours, 2 ),
				'regular_hours'  => round( (float) $row->regular_hours, 2 ),
				'overtime_hours' => round( (float) $row->overtime_hours, 2 ),
				'employee_count' => (int) $row->employee_count,
				'last_activity'  => $row->last_activity ?: null,
			);
		}

		return $items;
	}

	/** @return int[] */
	private function analytics_project_ids( Project $project ): array {
		$ids = array( (int) $project->id );
		if ( $project->is_standalone() && ! $project->parent_project_id ) {
			foreach ( $this->projects->list_children( (int) $project->id, false ) as $child ) {
				$ids[] = (int) $child->id;
			}
		}
		return $ids;
	}

	/**
	 * @param int[] $ids
	 * @return array{placeholders:string,ids:int[]}
	 */
	private function in_clause( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			$ids = array( 0 );
		}
		return array(
			'placeholders' => implode( ',', array_fill( 0, count( $ids ), '%d' ) ),
			'ids'          => $ids,
		);
	}

	/** @return array<int,array<string,mixed>> */
	private function project_breakdown( int $client_id, string $date_from, string $date_to ): array {
		global $wpdb;
		$entries  = $wpdb->prefix . 'etwa_entries';
		$projects = $wpdb->prefix . 'etwa_projects';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id AS project_id, p.name,
					COALESCE(SUM(e.regular_hours + e.overtime_hours), 0) AS total_hours
				FROM {$projects} p
				LEFT JOIN {$entries} e ON e.project_id = p.id
					AND e.entry_date BETWEEN %s AND %s
				WHERE p.client_id = %d AND p.status = 'active'
				GROUP BY p.id
				ORDER BY total_hours DESC, p.name ASC",
				$date_from,
				$date_to,
				$client_id
			)
		);

		$breakdown = array();
		foreach ( $rows ?: array() as $row ) {
			$breakdown[] = array(
				'project_id'  => (int) $row->project_id,
				'name'        => (string) $row->name,
				'total_hours' => round( (float) $row->total_hours, 2 ),
			);
		}

		$direct = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(regular_hours + overtime_hours), 0)
				FROM {$entries}
				WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)
				AND entry_date BETWEEN %s AND %s",
				$client_id,
				$date_from,
				$date_to
			)
		);

		$breakdown[] = array(
			'project_id'  => null,
			'name'        => ProjectService::unassigned_label(),
			'total_hours' => round( $direct, 2 ),
		);

		return $breakdown;
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
