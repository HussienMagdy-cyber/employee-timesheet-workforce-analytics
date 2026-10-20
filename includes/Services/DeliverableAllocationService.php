<?php
/**
 * Deliverable allocation service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\DeliverableAllocation;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\DeliverableAllocationRepository;
use ETWA\Repositories\DeliverableTypeRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableAllocationService {

	public function __construct(
		private DeliverableAllocationRepository $allocations = new DeliverableAllocationRepository(),
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private DeliverableTypeRepository $types = new DeliverableTypeRepository(),
		private DeliverableMetricsService $metrics = new DeliverableMetricsService(),
	) {}

	/**
	 * @param array{scope_id?:int,scope_impact?:string,deliverable_type_id?:int} $filters
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list( int $user_id, array $filters ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view allocations.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( empty( $filters['scope_id'] ) ) {
			return new WP_Error( 'invalid', __( 'scope_id is required.', 'etwa' ), array( 'status' => 400 ) );
		}
		$type_names = $this->type_name_map();
		return array_map(
			static fn( DeliverableAllocation $a ) => $a->to_array( $type_names ),
			$this->allocations->list( $filters )
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function get( int $user_id, int $id ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view allocations.', 'etwa' ), array( 'status' => 403 ) );
		}
		$allocation = $this->allocations->find( $id );
		if ( ! $allocation ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		$payload = $allocation->to_array( $this->type_name_map() );
		$user    = get_userdata( $allocation->allocated_by );
		$payload['allocated_by_name'] = $user ? $user->display_name : '';
		return array(
			'allocation' => $payload,
		);
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, array $data ): array|WP_Error {
		if ( ! RoleManager::user_can_allocate_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot allocate deliverables.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_payload( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$id = $this->allocations->create(
			array_merge(
				$validated,
				array( 'allocated_by' => $user_id )
			)
		);

		$allocation = $this->allocations->find( $id );
		if ( ! $allocation ) {
			return new WP_Error( 'create_failed', __( 'Could not create allocation.', 'etwa' ), array( 'status' => 500 ) );
		}

		$metrics = $this->metrics->scope_metrics( (int) $validated['scope_id'] );
		( new DeliverableNotificationService() )->maybe_notify_scope_completed( (int) $validated['scope_id'] );
		return array(
			'allocation' => $allocation->to_array( $this->type_name_map() ),
			'metrics'    => is_wp_error( $metrics ) ? null : $metrics,
		);
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $id, array $data ): array|WP_Error {
		if ( ! RoleManager::user_can_allocate_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot allocate deliverables.', 'etwa' ), array( 'status' => 403 ) );
		}
		$existing = $this->allocations->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$merged = array_merge(
			array(
				'scope_id'       => $existing->scope_id,
				'task_name'      => $existing->task_name,
				'completed_date' => $existing->completed_date,
				'notes'          => $existing->notes,
				'items'          => array_map( static fn( $i ) => $i->to_array(), $existing->items ),
			),
			$data,
			array( 'scope_id' => $existing->scope_id )
		);
		$validated = $this->validate_payload( $merged, false );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		unset( $validated['scope_id'] );
		$this->allocations->update( $id, $validated );
		$allocation = $this->allocations->find( $id );
		$metrics    = $this->metrics->scope_metrics( (int) $existing->scope_id );
		( new DeliverableNotificationService() )->maybe_notify_scope_completed( (int) $existing->scope_id );

		return array(
			'allocation' => $allocation ? $allocation->to_array( $this->type_name_map() ) : null,
			'metrics'    => is_wp_error( $metrics ) ? null : $metrics,
		);
	}

	public function delete( int $user_id, int $id ): array|WP_Error {
		if ( ! RoleManager::user_can_allocate_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot allocate deliverables.', 'etwa' ), array( 'status' => 403 ) );
		}
		$existing = $this->allocations->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		$this->allocations->delete( $id );
		$metrics = $this->metrics->scope_metrics( (int) $existing->scope_id );
		return array(
			'deleted' => true,
			'metrics' => is_wp_error( $metrics ) ? null : $metrics,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_payload( array $data, bool $require_scope = true ): array|WP_Error {
		$scope_id = (int) ( $data['scope_id'] ?? 0 );
		if ( $require_scope && $scope_id <= 0 ) {
			return new WP_Error( 'invalid', __( 'Scope is required.', 'etwa' ), array( 'status' => 400 ) );
		}
		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope ) {
			return new WP_Error( 'not_found', __( 'Scope not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( 'active' !== $scope->status && $require_scope ) {
			return new WP_Error( 'invalid', __( 'Allocations are only allowed on active scopes.', 'etwa' ), array( 'status' => 400 ) );
		}

		$task_name = sanitize_text_field( (string) ( $data['task_name'] ?? '' ) );
		if ( '' === $task_name ) {
			return new WP_Error( 'invalid', __( 'Task name is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$raw_items = $data['items'] ?? null;
		if ( ! is_array( $raw_items ) || ! $raw_items ) {
			return new WP_Error( 'invalid', __( 'At least one deliverable item is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$validated_items = array();
		foreach ( $raw_items as $raw ) {
			if ( ! is_array( $raw ) ) {
				return new WP_Error( 'invalid', __( 'Invalid deliverable item.', 'etwa' ), array( 'status' => 400 ) );
			}
			$item = $this->validate_item( $raw );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$validated_items[] = $item;
		}

		$items = $this->merge_duplicate_items( $validated_items );
		if ( ! $items ) {
			return new WP_Error( 'invalid', __( 'At least one deliverable item is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$completed = sanitize_text_field( (string) ( $data['completed_date'] ?? current_time( 'Y-m-d' ) ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $completed ) ) {
			return new WP_Error( 'invalid', __( 'Invalid completed date.', 'etwa' ), array( 'status' => 400 ) );
		}

		return array(
			'scope_id'       => $scope_id,
			'task_name'      => $task_name,
			'completed_date' => $completed,
			'notes'          => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'items'          => $items,
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_item( array $data ): array|WP_Error {
		$type_id = (int) ( $data['deliverable_type_id'] ?? 0 );
		$type    = $this->types->find( $type_id );
		if ( ! $type || ! $type->is_active ) {
			return new WP_Error( 'invalid', __( 'Active deliverable type is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$quantity = (float) ( $data['quantity'] ?? 0 );
		if ( $quantity <= 0 ) {
			return new WP_Error( 'invalid', __( 'Quantity must be greater than zero.', 'etwa' ), array( 'status' => 400 ) );
		}

		$impact = sanitize_key( (string) ( $data['scope_impact'] ?? '' ) );
		// Legacy "extra" is coerced to normal; Extra is derived as qty above planned scope.
		if ( DeliverableAllocation::IMPACT_EXTRA === $impact ) {
			$impact = DeliverableAllocation::IMPACT_NORMAL;
		}
		if ( ! in_array( $impact, array( DeliverableAllocation::IMPACT_NORMAL, DeliverableAllocation::IMPACT_REPLACEMENT ), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid scope impact.', 'etwa' ), array( 'status' => 400 ) );
		}

		$replaced_type_id = null;
		$replacement_qty  = null;
		if ( DeliverableAllocation::IMPACT_REPLACEMENT === $impact ) {
			$replaced_type_id = (int) ( $data['replaced_type_id'] ?? 0 );
			$replacement_qty  = (float) ( $data['replacement_qty'] ?? 0 );
			if ( $replaced_type_id <= 0 || $replacement_qty <= 0 ) {
				return new WP_Error( 'invalid', __( 'Replacement requires a replace type and replacement quantity.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( $replaced_type_id === $type_id ) {
				return new WP_Error( 'invalid', __( 'Replaced type must differ from the allocated type.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( ! $this->types->find( $replaced_type_id ) ) {
				return new WP_Error( 'invalid', __( 'Replaced deliverable type not found.', 'etwa' ), array( 'status' => 400 ) );
			}
		}

		return array(
			'deliverable_type_id' => $type_id,
			'quantity'            => $quantity,
			'scope_impact'        => $impact,
			'replaced_type_id'    => $replaced_type_id,
			'replacement_qty'     => $replacement_qty,
		);
	}

	/**
	 * Merge duplicate type + impact (+ replaced type for replacements).
	 *
	 * @param array<int,array<string,mixed>> $items
	 * @return array<int,array<string,mixed>>
	 */
	private function merge_duplicate_items( array $items ): array {
		$merged = array();
		foreach ( $items as $item ) {
			$key = (int) $item['deliverable_type_id'] . '|' . (string) $item['scope_impact'] . '|';
			if ( DeliverableAllocation::IMPACT_REPLACEMENT === $item['scope_impact'] ) {
				$key .= (string) (int) ( $item['replaced_type_id'] ?? 0 );
			}
			if ( ! isset( $merged[ $key ] ) ) {
				$merged[ $key ] = $item;
				continue;
			}
			$merged[ $key ]['quantity'] = (float) $merged[ $key ]['quantity'] + (float) $item['quantity'];
			if ( DeliverableAllocation::IMPACT_REPLACEMENT === $item['scope_impact'] ) {
				$merged[ $key ]['replacement_qty'] = (float) ( $merged[ $key ]['replacement_qty'] ?? 0 ) + (float) ( $item['replacement_qty'] ?? 0 );
			}
		}

		$out = array();
		$i   = 0;
		foreach ( $merged as $item ) {
			$item['sort_order'] = $i;
			$out[]              = $item;
			++$i;
		}
		return $out;
	}

	/** @return array<int,string> */
	private function type_name_map(): array {
		$map = array();
		foreach ( $this->types->all() as $t ) {
			if ( null !== $t->id ) {
				$map[ (int) $t->id ] = $t->name;
			}
		}
		return $map;
	}
}
