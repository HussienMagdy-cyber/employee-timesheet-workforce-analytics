<?php
/**
 * Deliverables analytics dashboard aggregation.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Models\ClientScope;
use ETWA\Models\DeliverableAllocation;
use ETWA\Models\DeliverableAllocationItem;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\DeliverableAllocationRepository;
use ETWA\Repositories\DeliverableTypeRepository;
use ETWA\Repositories\ProjectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableDashboardService {

	private DeliverableMetricsService $metrics;
	private ClientScopeRepository $scopes;
	private DeliverableAllocationRepository $allocations;
	private DeliverableTypeRepository $types;
	private ClientRepository $clients;
	private ProjectRepository $projects;

	public function __construct() {
		$this->metrics     = new DeliverableMetricsService();
		$this->scopes      = new ClientScopeRepository();
		$this->allocations = new DeliverableAllocationRepository();
		$this->types       = new DeliverableTypeRepository();
		$this->clients     = new ClientRepository();
		$this->projects    = new ProjectRepository();
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return array<string,mixed>
	 */
	public function analytics( array $filters = array() ): array {
		$trend      = in_array( (string) ( $filters['trend'] ?? 'month' ), array( 'week', 'month', 'scope' ), true )
			? (string) $filters['trend']
			: 'month';
		$over_time  = in_array( (string) ( $filters['over_time'] ?? 'month' ), array( 'day', 'week', 'month' ), true )
			? (string) $filters['over_time']
			: 'month';
		$activity_n = max( 1, min( 50, (int) ( $filters['activity_limit'] ?? 15 ) ) );

		$scope_rows = $this->filter_scopes( $filters );
		$scope_ids  = array_map(
			static fn( ClientScope $s ): int => (int) $s->id,
			$scope_rows
		);

		$scope_metrics = array();
		foreach ( $scope_rows as $scope ) {
			if ( null === $scope->id ) {
				continue;
			}
			$project_name = '';
			if ( $scope->project_id ) {
				$project = $this->projects->find( (int) $scope->project_id );
				$project_name = $project ? $project->name : '';
			}
			$m = $this->metrics->scope_metrics( $scope->id );
			if ( ! is_wp_error( $m ) ) {
				$scope_metrics[ $scope->id ] = array_merge(
					$m,
					array(
						'scope_id'     => $scope->id,
						'scope_name'   => $scope->name,
						'client_id'    => $scope->client_id,
						'project_id'   => $scope->project_id,
						'project_name' => $project_name,
						'status'       => $scope->status,
						'starts_on'    => $scope->starts_on,
						'ends_on'      => $scope->ends_on,
					)
				);
			}
		}

		$scope_ids = array_keys( $scope_metrics );
		if ( ! $scope_ids && ! empty( $filters['date_from'] ) ) {
			return $this->empty_payload();
		}

		$alloc_filters = array( 'scope_ids' => $scope_ids );
		if ( ! empty( $filters['deliverable_type_id'] ) ) {
			$alloc_filters['deliverable_type_id'] = (int) $filters['deliverable_type_id'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$alloc_filters['date_from'] = (string) $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$alloc_filters['date_to'] = (string) $filters['date_to'];
		}

		$allocs = $scope_ids ? $this->allocations->list( $alloc_filters ) : array();

		if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) || ! empty( $filters['deliverable_type_id'] ) ) {
			$scope_metrics = $this->apply_allocation_filters_to_scopes( $scope_metrics, $filters, $allocs );
			$scope_ids     = array_keys( $scope_metrics );
		}

		$type_map    = $this->type_name_map();
		$client_map  = $this->client_name_map();
		$kpis        = $this->build_kpis( $scope_metrics );
		$by_type     = $this->build_by_type( $allocs, $type_map, $scope_metrics );
		$effort      = $this->build_effort( $scope_metrics, $allocs );
		$completion  = $this->build_completion_trend( $allocs, $scope_metrics, $trend );
		$over_time_s = $this->build_deliverables_over_time( $allocs, $over_time );
		$effort_tr   = $this->build_effort_trend( $allocs, $trend === 'scope' ? 'month' : $trend );
		$scope_perf  = $this->build_scope_performance( $scope_metrics, $client_map );
		$client_perf = $this->build_client_performance( $scope_metrics, $client_map );
		$activity    = $this->build_recent_activity( $scope_ids, $activity_n, $type_map, $client_map, $scope_metrics );
		$insights    = $this->build_insights( $scope_metrics, $scope_perf, $client_perf, $kpis );

		return array(
			'kpis'                   => $kpis,
			'by_type'                => $by_type,
			'completion_trend'       => $completion,
			'deliverables_over_time' => $over_time_s,
			'effort_trend'           => $effort_tr,
			'scope_performance'      => $scope_perf,
			'client_performance'     => $client_perf,
			'effort'                 => $effort,
			'recent_activity'        => $activity,
			'insights'               => $insights,
			'meta'                   => array(
				'trend'      => $trend,
				'over_time'  => $over_time,
				'scope_count' => count( $scope_ids ),
			),
		);
	}

	/** @return array<string,mixed> */
	private function empty_payload(): array {
		return array(
			'kpis'                   => $this->zero_kpis(),
			'by_type'                => array(),
			'completion_trend'       => array(),
			'deliverables_over_time' => array(),
			'effort_trend'           => array(),
			'scope_performance'      => array(
				'top'      => array(),
				'lowest'   => array(),
				'overdue'  => array(),
				'exceeded' => array(),
			),
			'client_performance'     => array(
				'highest_completion' => array(),
				'highest_effort'     => array(),
				'highest_extra'      => array(),
			),
			'effort'                 => array( 'planned' => 0, 'completed' => 0, 'pct' => 0 ),
			'recent_activity'        => array(),
			'insights'               => array(),
			'meta'                   => array( 'trend' => 'month', 'over_time' => 'month', 'scope_count' => 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return ClientScope[]
	 */
	private function filter_scopes( array $filters ): array {
		$scope_filters = array();
		if ( ! empty( $filters['client_id'] ) ) {
			$scope_filters['client_id'] = (int) $filters['client_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$scope_filters['status'] = (string) $filters['status'];
		}
		if ( ! empty( $filters['scope_id'] ) ) {
			$scope = $this->scopes->find( (int) $filters['scope_id'] );
			return $scope ? array( $scope ) : array();
		}

		$rows = $this->scopes->list( $scope_filters );
		if ( empty( $filters['date_from'] ) && empty( $filters['date_to'] ) ) {
			return $rows;
		}

		$date_from = (string) ( $filters['date_from'] ?? '' );
		$date_to   = (string) ( $filters['date_to'] ?? '' );

		return array_values(
			array_filter(
				$rows,
				static function ( ClientScope $scope ) use ( $date_from, $date_to ): bool {
					if ( '' === $scope->starts_on && '' === $scope->ends_on ) {
						return true;
					}
					$start = $scope->starts_on ?: '1970-01-01';
					$end   = $scope->ends_on ?: '2099-12-31';
					if ( '' !== $date_to && $start > $date_to ) {
						return false;
					}
					if ( '' !== $date_from && $end < $date_from ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @param array<string,mixed>            $filters
	 * @param DeliverableAllocation[]        $allocs
	 * @return array<int,array<string,mixed>>
	 */
	private function apply_allocation_filters_to_scopes( array $scope_metrics, array $filters, array $allocs ): array {
		if ( empty( $filters['date_from'] ) && empty( $filters['date_to'] ) && empty( $filters['deliverable_type_id'] ) ) {
			return $scope_metrics;
		}

		$scope_with_allocs = array();
		foreach ( $allocs as $alloc ) {
			$scope_with_allocs[ $alloc->scope_id ] = true;
		}

		$filtered = array();
		foreach ( $scope_metrics as $id => $m ) {
			if ( ! empty( $scope_with_allocs[ $id ] ) ) {
				$filtered[ $id ] = $m;
				continue;
			}
			if ( empty( $filters['date_from'] ) && empty( $filters['date_to'] ) ) {
				$filtered[ $id ] = $m;
			}
		}

		return $filtered;
	}

	/**
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @return array<string,mixed>
	 */
	private function build_kpis( array $scope_metrics ): array {
		$kpis = $this->zero_kpis();
		$today = gmdate( 'Y-m-d' );

		foreach ( $scope_metrics as $m ) {
			$kpis['total_scopes']++;
			$kpis['planned_qty']   += (float) ( $m['planned_qty'] ?? 0 );
			$kpis['remaining_qty'] += (float) ( $m['remaining_qty'] ?? 0 );

			if ( 'active' === ( $m['status'] ?? '' ) ) {
				$kpis['active_scopes']++;
				if ( (float) ( $m['remaining_qty'] ?? 0 ) > 0 ) {
					$kpis['scopes_with_remaining']++;
				}
				if ( ! empty( $m['ends_on'] ) && $m['ends_on'] < $today && (float) ( $m['remaining_qty'] ?? 0 ) > 0 ) {
					$kpis['overdue_scopes']++;
				}
			}
			if ( in_array( (string) ( $m['status'] ?? '' ), array( 'closed', 'archived' ), true ) ) {
				$kpis['completed_scopes']++;
			}
			$kpis['effort_score'] += (float) ( $m['effort_score'] ?? 0 );
			$kpis['contract_total'] += (float) ( $m['contract_deliverables'] ?? 0 );
		}

		$delivered = 0.0;
		$extra     = 0.0;
		foreach ( $scope_metrics as $m ) {
			$delivered += (float) ( $m['completed_qty'] ?? 0 );
			$extra     += (float) ( $m['extra_qty'] ?? 0 );
		}

		$kpis['delivered_qty']  = round( $delivered, 2 );
		$kpis['extra_qty']      = round( $extra, 2 );
		$planned                = (float) $kpis['planned_qty'];
		$kpis['completion_pct'] = $planned > 0 ? round( ( $delivered / $planned ) * 100, 1 ) : 0.0;
		$kpis['effort_score']   = round( (float) $kpis['effort_score'], 2 );
		$kpis['contract_total'] = round( (float) $kpis['contract_total'], 2 );
		$kpis['planned_qty']    = round( (float) $kpis['planned_qty'], 2 );
		$kpis['remaining_qty']  = round( (float) $kpis['remaining_qty'], 2 );

		return $kpis;
	}

	/** @return array<string,mixed> */
	private function zero_kpis(): array {
		return array(
			'total_scopes'          => 0,
			'active_scopes'         => 0,
			'completed_scopes'      => 0,
			'scopes_with_remaining' => 0,
			'overdue_scopes'        => 0,
			'contract_total'        => 0.0,
			'planned_qty'           => 0.0,
			'delivered_qty'         => 0.0,
			'remaining_qty'         => 0.0,
			'extra_qty'             => 0.0,
			'completion_pct'        => 0.0,
			'effort_score'          => 0.0,
		);
	}

	/**
	 * @param DeliverableAllocation[]        $allocs
	 * @param array<int,string>              $type_map
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @return array<int,array<string,mixed>>
	 */
	private function build_by_type( array $allocs, array $type_map, array $scope_metrics = array() ): array {
		$by = array();
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$tid = $item->deliverable_type_id;
				if ( ! isset( $by[ $tid ] ) ) {
					$by[ $tid ] = array(
						'deliverable_type_id' => $tid,
						'name'                => $type_map[ $tid ] ?? ( 'Type #' . $tid ),
						'completed_qty'       => 0.0,
						'extra_qty'           => 0.0,
						'effort_score'        => 0.0,
					);
				}
				$by[ $tid ]['completed_qty'] += $item->quantity;
				$by[ $tid ]['effort_score']  += $this->item_effort( $item );
			}
		}

		$planned = 0.0;
		foreach ( $scope_metrics as $m ) {
			$planned += (float) ( $m['planned_qty'] ?? 0 );
		}

		$total_completed = 0.0;
		foreach ( $by as $row ) {
			$total_completed += (float) $row['completed_qty'];
		}
		$overflow = max( 0.0, $total_completed - $planned );

		$rows     = array_values( $by );
		$assigned = 0.0;
		$last_i   = count( $rows ) - 1;
		foreach ( $rows as $i => &$row ) {
			$share = 0.0;
			if ( $overflow > 0 && $total_completed > 0 ) {
				if ( $i === $last_i ) {
					$share = max( 0.0, $overflow - $assigned );
				} else {
					$share     = round( ( (float) $row['completed_qty'] / $total_completed ) * $overflow, 2 );
					$assigned += $share;
				}
			}
			$row['completed_qty'] = round( (float) $row['completed_qty'], 2 );
			$row['extra_qty']     = round( $share, 2 );
			$row['effort_score']  = round( (float) $row['effort_score'], 2 );
		}
		unset( $row );

		usort(
			$rows,
			static fn( array $a, array $b ): int => ( $b['completed_qty'] + $b['extra_qty'] ) <=> ( $a['completed_qty'] + $a['extra_qty'] )
		);

		return $rows;
	}

	/**
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @param DeliverableAllocation[]        $allocs
	 * @return array<string,mixed>
	 */
	private function build_effort( array $scope_metrics, array $allocs ): array {
		$planned = 0.0;
		foreach ( $scope_metrics as $m ) {
			$planned += (float) ( $m['planned_qty'] ?? 0 );
		}

		$completed = 0.0;
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$completed += $this->item_effort( $item );
			}
		}

		return array(
			'planned'   => round( $planned, 2 ),
			'completed' => round( $completed, 2 ),
			'pct'       => $planned > 0 ? round( ( $completed / $planned ) * 100, 1 ) : 0.0,
		);
	}

	/**
	 * @param DeliverableAllocation[]        $allocs
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @return array<int,array<string,mixed>>
	 */
	private function build_completion_trend( array $allocs, array $scope_metrics, string $trend ): array {
		if ( 'scope' === $trend ) {
			$rows = array();
			foreach ( $scope_metrics as $m ) {
				$planned = (float) ( $m['planned_qty'] ?? 0 );
				$done    = (float) ( $m['completed_qty'] ?? 0 );
				$rows[]  = array(
					'period'         => (string) ( $m['scope_name'] ?? '' ),
					'scope_id'       => (int) ( $m['scope_id'] ?? 0 ),
					'planned_qty'    => round( $planned, 2 ),
					'completed_qty'  => round( $done, 2 ),
					'completion_pct' => $planned > 0 ? round( ( $done / $planned ) * 100, 1 ) : 0.0,
				);
			}
			usort( $rows, static fn( array $a, array $b ): int => $b['completion_pct'] <=> $a['completion_pct'] );
			return array_slice( $rows, 0, 15 );
		}

		$buckets = array();
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$key = $this->period_key( $alloc->completed_date, $trend );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array( 'period' => $key, 'completed_qty' => 0.0 );
				}
				$buckets[ $key ]['completed_qty'] += $item->quantity;
			}
		}

		$planned_total = 0.0;
		foreach ( $scope_metrics as $m ) {
			$planned_total += (float) ( $m['planned_qty'] ?? 0 );
		}

		$rows = array();
		ksort( $buckets );
		foreach ( $buckets as $bucket ) {
			$done   = (float) $bucket['completed_qty'];
			$rows[] = array(
				'period'         => (string) $bucket['period'],
				'planned_qty'    => round( $planned_total, 2 ),
				'completed_qty'  => round( $done, 2 ),
				'completion_pct' => $planned_total > 0 ? round( ( $done / $planned_total ) * 100, 1 ) : 0.0,
			);
		}

		return $rows;
	}

	/**
	 * @param DeliverableAllocation[] $allocs
	 * @return array<int,array<string,mixed>>
	 */
	private function build_deliverables_over_time( array $allocs, string $grain ): array {
		$buckets = array();
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$key = $this->period_key( $alloc->completed_date, $grain );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array( 'period' => $key, 'completed_qty' => 0.0 );
				}
				$buckets[ $key ]['completed_qty'] += $item->quantity;
			}
		}

		ksort( $buckets );
		$rows = array();
		foreach ( $buckets as $bucket ) {
			$rows[] = array(
				'period'        => (string) $bucket['period'],
				'completed_qty' => round( (float) $bucket['completed_qty'], 2 ),
			);
		}

		return $rows;
	}

	/**
	 * @param DeliverableAllocation[] $allocs
	 * @return array<int,array<string,mixed>>
	 */
	private function build_effort_trend( array $allocs, string $grain ): array {
		$buckets = array();
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$key = $this->period_key( $alloc->completed_date, $grain );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array( 'period' => $key, 'completed_effort' => 0.0 );
				}
				$buckets[ $key ]['completed_effort'] += $this->item_effort( $item );
			}
		}

		ksort( $buckets );
		$rows = array();
		foreach ( $buckets as $bucket ) {
			$rows[] = array(
				'period'           => (string) $bucket['period'],
				'completed_effort' => round( (float) $bucket['completed_effort'], 2 ),
			);
		}

		return $rows;
	}

	/**
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @param array<int,string>              $client_map
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function build_scope_performance( array $scope_metrics, array $client_map ): array {
		$rows = array();
		$today = gmdate( 'Y-m-d' );

		foreach ( $scope_metrics as $m ) {
			$planned = (float) ( $m['planned_qty'] ?? 0 );
			$done    = (float) ( $m['completed_qty'] ?? 0 );
			$pct     = $planned > 0 ? round( ( $done / $planned ) * 100, 1 ) : 0.0;
			$rows[]  = array(
				'scope_id'       => (int) ( $m['scope_id'] ?? 0 ),
				'scope_name'     => (string) ( $m['scope_name'] ?? '' ),
				'client_id'      => (int) ( $m['client_id'] ?? 0 ),
				'client_name'    => $client_map[ (int) ( $m['client_id'] ?? 0 ) ] ?? '',
				'project_name'   => (string) ( $m['project_name'] ?? '' ),
				'completion_pct' => $pct,
				'delivered_qty'  => round( $done, 2 ),
				'planned_qty'    => round( $planned, 2 ),
				'remaining_qty'  => round( (float) ( $m['remaining_qty'] ?? 0 ), 2 ),
				'extra_qty'      => round( (float) ( $m['extra_qty'] ?? 0 ), 2 ),
				'ends_on'        => (string) ( $m['ends_on'] ?? '' ),
				'status'         => (string) ( $m['status'] ?? '' ),
			);
		}

		$by_pct = $rows;
		usort( $by_pct, static fn( array $a, array $b ): int => $b['completion_pct'] <=> $a['completion_pct'] );

		$overdue = array_values(
			array_filter(
				$rows,
				static fn( array $r ): bool => 'active' === $r['status']
					&& '' !== $r['ends_on']
					&& $r['ends_on'] < $today
					&& $r['remaining_qty'] > 0
			)
		);

		$exceeded = array_values(
			array_filter(
				$rows,
				static fn( array $r ): bool => $r['extra_qty'] > 0
			)
		);
		usort( $exceeded, static fn( array $a, array $b ): int => $b['extra_qty'] <=> $a['extra_qty'] );

		return array(
			'top'      => array_slice( $by_pct, 0, 5 ),
			'lowest'   => array_slice( array_reverse( $by_pct ), 0, 5 ),
			'overdue'  => array_slice( $overdue, 0, 10 ),
			'exceeded' => array_slice( $exceeded, 0, 5 ),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @param array<int,string>              $client_map
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function build_client_performance( array $scope_metrics, array $client_map ): array {
		$by_client = array();
		foreach ( $scope_metrics as $m ) {
			$cid = (int) ( $m['client_id'] ?? 0 );
			if ( ! isset( $by_client[ $cid ] ) ) {
				$by_client[ $cid ] = array(
					'client_id'      => $cid,
					'name'             => $client_map[ $cid ] ?? ( 'Client #' . $cid ),
					'planned_qty'      => 0.0,
					'delivered_qty'    => 0.0,
					'extra_qty'        => 0.0,
					'effort_score'     => 0.0,
					'scope_count'      => 0,
				);
			}
			$by_client[ $cid ]['planned_qty']   += (float) ( $m['planned_qty'] ?? 0 );
			$by_client[ $cid ]['delivered_qty'] += (float) ( $m['completed_qty'] ?? 0 );
			$by_client[ $cid ]['extra_qty']     += (float) ( $m['extra_qty'] ?? 0 );
			$by_client[ $cid ]['effort_score']  += (float) ( $m['effort_score'] ?? 0 );
			$by_client[ $cid ]['scope_count']++;
		}

		$rows = array();
		foreach ( $by_client as $c ) {
			$planned = (float) $c['planned_qty'];
			$rows[]  = array(
				'client_id'      => (int) $c['client_id'],
				'name'           => (string) $c['name'],
				'completion_pct' => $planned > 0 ? round( ( (float) $c['delivered_qty'] / $planned ) * 100, 1 ) : 0.0,
				'delivered_qty'  => round( (float) $c['delivered_qty'], 2 ),
				'extra_qty'      => round( (float) $c['extra_qty'], 2 ),
				'effort_score'   => round( (float) $c['effort_score'], 2 ),
				'scope_count'    => (int) $c['scope_count'],
			);
		}

		$by_completion = $rows;
		usort( $by_completion, static fn( array $a, array $b ): int => $b['completion_pct'] <=> $a['completion_pct'] );

		$by_effort = $rows;
		usort( $by_effort, static fn( array $a, array $b ): int => $b['effort_score'] <=> $a['effort_score'] );

		$by_extra = $rows;
		usort( $by_extra, static fn( array $a, array $b ): int => $b['extra_qty'] <=> $a['extra_qty'] );

		return array(
			'highest_completion' => array_slice( $by_completion, 0, 5 ),
			'highest_effort'     => array_slice( $by_effort, 0, 5 ),
			'highest_extra'      => array_slice( $by_extra, 0, 5 ),
		);
	}

	/**
	 * @param int[]                      $scope_ids
	 * @param array<int,string>          $type_map
	 * @param array<int,string>          $client_map
	 * @param array<int,array<string,mixed>> $scope_metrics
	 * @return array<int,array<string,mixed>>
	 */
	private function build_recent_activity( array $scope_ids, int $limit, array $type_map, array $client_map, array $scope_metrics ): array {
		if ( ! $scope_ids ) {
			return array();
		}

		$allocs = $this->allocations->list(
			array(
				'scope_ids' => $scope_ids,
				'limit'     => $limit,
			)
		);

		$rows = array();
		foreach ( $allocs as $alloc ) {
			$sid  = $alloc->scope_id;
			$m    = $scope_metrics[ $sid ] ?? array();
			$user = get_userdata( $alloc->allocated_by );
			$task = '' !== $alloc->task_name ? $alloc->task_name : __( 'Untitled allocation', 'etwa' );
			$rows[] = array(
				'allocation_id'  => $alloc->id,
				'client'         => $client_map[ (int) ( $m['client_id'] ?? 0 ) ] ?? '',
				'scope'          => (string) ( $m['scope_name'] ?? '' ),
				'task_name'      => $task,
				'summary'        => $alloc->summary( $type_map ),
				'quantity'       => $alloc->total_quantity(),
				'user'           => $user ? $user->display_name : '',
				'date'           => $alloc->completed_date,
			);
		}

		return $rows;
	}

	/**
	 * @param array<int,array<string,mixed>>                          $scope_metrics
	 * @param array<string,array<int,array<string,mixed>>>            $scope_perf
	 * @param array<string,array<int,array<string,mixed>>>            $client_perf
	 * @param array<string,mixed>                                     $kpis
	 * @return array<int,array<string,mixed>>
	 */
	private function build_insights( array $scope_metrics, array $scope_perf, array $client_perf, array $kpis ): array {
		$insights = array();

		if ( (int) ( $kpis['overdue_scopes'] ?? 0 ) > 0 ) {
			$insights[] = array(
				'code'  => 'overdue_scopes',
				'label' => sprintf(
					/* translators: %d: scope count */
					_n( '%d overdue scope with remaining work', '%d overdue scopes with remaining work', (int) $kpis['overdue_scopes'], 'etwa' ),
					(int) $kpis['overdue_scopes']
				),
				'items' => $scope_perf['overdue'] ?? array(),
			);
		}

		if ( ! empty( $scope_perf['exceeded'] ) ) {
			$insights[] = array(
				'code'  => 'extra_deliverables',
				'label' => __( 'Scopes with extra deliverables', 'etwa' ),
				'items' => $scope_perf['exceeded'],
			);
		}

		if ( (int) ( $kpis['scopes_with_remaining'] ?? 0 ) > 0 ) {
			$remaining = array_values(
				array_filter(
					array_map(
						static function ( array $m ): ?array {
							if ( 'active' !== ( $m['status'] ?? '' ) || (float) ( $m['remaining_qty'] ?? 0 ) <= 0 ) {
								return null;
							}
							return array(
								'scope_name'    => (string) ( $m['scope_name'] ?? '' ),
								'remaining_qty' => round( (float) ( $m['remaining_qty'] ?? 0 ), 2 ),
							);
						},
						$scope_metrics
					)
				)
			);
			$insights[] = array(
				'code'  => 'remaining_work',
				'label' => __( 'Active scopes with remaining deliverables', 'etwa' ),
				'items' => array_slice( $remaining, 0, 5 ),
			);
		}

		if ( ! empty( $client_perf['highest_extra'][0]['extra_qty'] ) && (float) $client_perf['highest_extra'][0]['extra_qty'] > 0 ) {
			$insights[] = array(
				'code'  => 'client_extra',
				'label' => __( 'Clients with highest extra volume', 'etwa' ),
				'items' => $client_perf['highest_extra'],
			);
		}

		return $insights;
	}

	/** @return array<int,string> */
	private function type_name_map(): array {
		$map = array();
		foreach ( $this->types->all() as $type ) {
			if ( null !== $type->id ) {
				$map[ $type->id ] = $type->name;
			}
		}
		return $map;
	}

	/** @return array<int,string> */
	private function client_name_map(): array {
		$map = array();
		foreach ( $this->clients->all() as $client ) {
			if ( null !== $client->id ) {
				$map[ $client->id ] = $client->name;
			}
		}
		return $map;
	}

	private function item_effort( DeliverableAllocationItem $item ): float {
		$effort = $this->effort_map();
		return (float) $item->quantity * ( $effort[ $item->deliverable_type_id ] ?? 1.0 );
	}

	/** @return array<int,float> */
	private function effort_map(): array {
		$map = array();
		foreach ( $this->types->all() as $type ) {
			if ( null !== $type->id ) {
				$map[ $type->id ] = (float) $type->effort_score;
			}
		}
		return $map;
	}

	private function period_key( string $date, string $grain ): string {
		if ( '' === $date ) {
			return 'unknown';
		}
		$ts = strtotime( $date . ' UTC' );
		if ( false === $ts ) {
			return $date;
		}

		return match ( $grain ) {
			'day'   => gmdate( 'Y-m-d', $ts ),
			'week'  => gmdate( 'o-\\WW', $ts ),
			default => gmdate( 'Y-m', $ts ),
		};
	}
}
