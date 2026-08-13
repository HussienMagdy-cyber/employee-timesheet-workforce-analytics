<?php
/**
 * Deliverable metrics — contract-total planned math + allocation progress.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Models\DeliverableAllocation;
use ETWA\Models\DeliverableAllocationItem;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\DeliverableAllocationRepository;
use ETWA\Repositories\DeliverableTypeRepository;
use ETWA\Repositories\ScopePatchRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableMetricsService {

	public function __construct(
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private ScopePatchRepository $patches = new ScopePatchRepository(),
		private DeliverableAllocationRepository $allocations = new DeliverableAllocationRepository(),
		private DeliverableTypeRepository $types = new DeliverableTypeRepository(),
	) {}

	/** @return array<string,mixed>|WP_Error */
	public function scope_metrics( int $scope_id ): array|WP_Error {
		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope ) {
			return new WP_Error( 'not_found', __( 'Scope not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$allocs  = $this->allocations->list( array( 'scope_id' => $scope_id ) );
		$planned = $scope->planned_total();
		$totals  = $this->summarize_allocations( $planned, $allocs );
		$by_type = $this->allocation_type_breakdown( $allocs, $planned );

		return array_merge(
			$totals,
			array(
				'scope_id'              => $scope_id,
				'contract_deliverables' => round( (float) $scope->contract_deliverables, 2 ),
				'by_type'               => $by_type,
				'patches'               => array(),
			)
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function patch_progress( int $patch_id ): array|WP_Error {
		return new WP_Error(
			'gone',
			__( 'Batches have been retired from deliverables planning.', 'etwa' ),
			array( 'status' => 410 )
		);
	}

	/**
	 * @param array{client_id?:int,status?:string} $filters
	 * @return array<string,mixed>
	 */
	public function dashboard( array $filters = array() ): array {
		$scopes = $this->scopes->list( $filters );
		$items  = array();
		$agg    = array(
			'planned_qty'     => 0.0,
			'completed_qty'   => 0.0,
			'normal_qty'      => 0.0,
			'replacement_qty' => 0.0,
			'extra_qty'       => 0.0,
			'remaining_qty'   => 0.0,
			'effort_score'    => 0.0,
			'completion_pct'  => 0.0,
			'scope_count'     => count( $scopes ),
		);

		foreach ( $scopes as $scope ) {
			$metrics = $this->scope_metrics( (int) $scope->id );
			if ( is_wp_error( $metrics ) ) {
				continue;
			}
			$items[] = array(
				'scope'   => $scope->to_array(),
				'metrics' => $metrics,
			);
			$agg['planned_qty']     += (float) $metrics['planned_qty'];
			$agg['completed_qty']   += (float) $metrics['completed_qty'];
			$agg['normal_qty']      += (float) $metrics['normal_qty'];
			$agg['replacement_qty'] += (float) $metrics['replacement_qty'];
			$agg['extra_qty']       += (float) $metrics['extra_qty'];
			$agg['remaining_qty']   += (float) $metrics['remaining_qty'];
			$agg['effort_score']    += (float) $metrics['effort_score'];
		}

		$agg['completion_pct'] = $agg['planned_qty'] > 0
			? round( ( $agg['completed_qty'] / $agg['planned_qty'] ) * 100, 2 )
			: 0.0;

		return array(
			'totals' => $agg,
			'items'  => $items,
		);
	}

	/**
	 * @param DeliverableAllocation[] $allocs
	 * @return DeliverableAllocationItem[]
	 */
	private function flatten_items( array $allocs ): array {
		$items = array();
		foreach ( $allocs as $alloc ) {
			foreach ( $alloc->items as $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * @param DeliverableAllocation[] $allocs
	 * @return array<string,float>
	 */
	private function summarize_allocations( float $planned, array $allocs ): array {
		$effort_map = $this->effort_map();
		$normal = $replacement = $toward = $effort = 0.0;

		foreach ( $this->flatten_items( $allocs ) as $item ) {
			$qty    = (float) $item->quantity;
			$weight = $effort_map[ (int) $item->deliverable_type_id ] ?? 1.0;
			$effort += $qty * $weight;

			if ( DeliverableAllocation::IMPACT_REPLACEMENT === $item->scope_impact ) {
				$toward      += $qty;
				$rep_qty      = (float) ( $item->replacement_qty ?? 0 );
				$replacement += $rep_qty;
				$toward      += $rep_qty;
				continue;
			}

			// Normal (and legacy Extra lines) count toward plan; overflow becomes Extra.
			$normal += $qty;
			$toward += $qty;
		}

		$extra     = max( 0.0, $toward - $planned );
		$remaining = max( 0.0, $planned - $toward );

		return array(
			'planned_qty'     => round( $planned, 2 ),
			'completed_qty'   => round( $toward, 2 ),
			'normal_qty'      => round( $normal, 2 ),
			'replacement_qty' => round( $replacement, 2 ),
			'extra_qty'       => round( $extra, 2 ),
			'remaining_qty'   => round( $remaining, 2 ),
			'effort_score'    => round( $effort, 2 ),
			'completion_pct'  => $planned > 0 ? round( ( $toward / $planned ) * 100, 2 ) : 0.0,
		);
	}

	/**
	 * Type breakdown from allocation items; Extra is share of plan overflow.
	 *
	 * @param DeliverableAllocation[] $allocs
	 * @return array<int,array<string,mixed>>
	 */
	private function allocation_type_breakdown( array $allocs, float $planned = 0.0 ): array {
		$names   = $this->type_name_map();
		$effort  = $this->effort_map();
		$buckets = array();

		$ensure = static function ( int $type_id ) use ( &$buckets ): void {
			if ( ! isset( $buckets[ $type_id ] ) ) {
				$buckets[ $type_id ] = array(
					'normal_qty'      => 0.0,
					'replacement_qty' => 0.0,
					'extra_qty'       => 0.0,
					'completed_qty'   => 0.0,
					'effort_score'    => 0.0,
				);
			}
		};

		foreach ( $this->flatten_items( $allocs ) as $item ) {
			$type_id = (int) $item->deliverable_type_id;
			$ensure( $type_id );
			$qty = (float) $item->quantity;
			$buckets[ $type_id ]['effort_score'] += $qty * ( $effort[ $type_id ] ?? 1.0 );

			if ( DeliverableAllocation::IMPACT_REPLACEMENT === $item->scope_impact ) {
				$buckets[ $type_id ]['completed_qty'] += $qty;
				$replaced = (int) ( $item->replaced_type_id ?? 0 );
				$rep_qty  = (float) ( $item->replacement_qty ?? 0 );
				if ( $replaced > 0 && $rep_qty > 0 ) {
					$ensure( $replaced );
					$buckets[ $replaced ]['replacement_qty'] += $rep_qty;
					$buckets[ $replaced ]['completed_qty']   += $rep_qty;
				}
				continue;
			}

			$buckets[ $type_id ]['normal_qty']    += $qty;
			$buckets[ $type_id ]['completed_qty'] += $qty;
		}

		$total_completed = 0.0;
		foreach ( $buckets as $b ) {
			$total_completed += (float) $b['completed_qty'];
		}
		$overflow = max( 0.0, $total_completed - max( 0.0, $planned ) );

		$out      = array();
		$assigned = 0.0;
		$ids      = array_keys( $buckets );
		$last_i   = count( $ids ) - 1;
		foreach ( $ids as $i => $type_id ) {
			$b     = $buckets[ $type_id ];
			$share = 0.0;
			if ( $overflow > 0 && $total_completed > 0 ) {
				if ( $i === $last_i ) {
					$share = max( 0.0, $overflow - $assigned );
				} else {
					$share     = round( ( (float) $b['completed_qty'] / $total_completed ) * $overflow, 2 );
					$assigned += $share;
				}
			}
			$out[] = array(
				'deliverable_type_id' => $type_id,
				'deliverable_type'    => $names[ $type_id ] ?? (string) $type_id,
				'normal_qty'          => round( (float) $b['normal_qty'], 2 ),
				'replacement_qty'     => round( (float) $b['replacement_qty'], 2 ),
				'extra_qty'           => round( $share, 2 ),
				'completed_qty'       => round( (float) $b['completed_qty'], 2 ),
				'effort_score'        => round( (float) $b['effort_score'], 2 ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $patch_array
	 * @return array<string,mixed>
	 */
	private function patch_progress_payload( int $patch_id, array $patch_array ): array {
		$planned = max( 0.0, (float) ( $patch_array['expected_qty'] ?? 0 ) );
		$allocs  = $this->allocations->list(
			array(
				'scope_id' => (int) $patch_array['scope_id'],
				'patch_id' => $patch_id,
			)
		);
		$totals  = $this->summarize_allocations( $planned, $allocs );
		$by_type = $this->allocation_type_breakdown( $allocs, $planned );

		return array_merge(
			array(
				'patch'   => $patch_array,
				'by_type' => $by_type,
			),
			$totals
		);
	}

	/** @return array<int,string> */
	private function type_name_map(): array {
		$map = array();
		foreach ( $this->types->all() as $t ) {
			$map[ (int) $t->id ] = $t->name;
		}
		return $map;
	}

	/** @return array<int,float> */
	private function effort_map(): array {
		$map = array();
		foreach ( $this->types->all() as $t ) {
			$map[ (int) $t->id ] = (float) $t->effort_score;
		}
		return $map;
	}
}
