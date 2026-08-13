<?php
/**
 * Deliverable allocation repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\DeliverableAllocation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableAllocationRepository {

	private string $table;
	private string $items_table;
	private DeliverableAllocationItemRepository $items;

	public function __construct( ?DeliverableAllocationItemRepository $items = null ) {
		global $wpdb;
		$this->table       = $wpdb->prefix . 'etwa_deliverable_allocations';
		$this->items_table = $wpdb->prefix . 'etwa_deliverable_allocation_items';
		$this->items       = $items ?? new DeliverableAllocationItemRepository();
	}

	public function find( int $id ): ?DeliverableAllocation {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		if ( ! $row ) {
			return null;
		}
		return DeliverableAllocation::from_row( $row, $this->items->list_for_allocation( $id ) );
	}

	/**
	 * @param array{scope_id?:int,patch_id?:int|null,scope_impact?:string,deliverable_type_id?:int,scope_ids?:int[],date_from?:string,date_to?:string,limit?:int} $filters
	 * @return DeliverableAllocation[]
	 */
	public function list( array $filters = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		$alias  = 'a';

		if ( ! empty( $filters['scope_id'] ) ) {
			$where[]  = "{$alias}.scope_id = %d";
			$params[] = (int) $filters['scope_id'];
		}
		if ( array_key_exists( 'patch_id', $filters ) ) {
			if ( null === $filters['patch_id'] ) {
				$where[] = "{$alias}.patch_id IS NULL";
			} else {
				$where[]  = "{$alias}.patch_id = %d";
				$params[] = (int) $filters['patch_id'];
			}
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = "{$alias}.completed_date >= %s";
			$params[] = (string) $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = "{$alias}.completed_date <= %s";
			$params[] = (string) $filters['date_to'];
		}
		if ( ! empty( $filters['scope_ids'] ) && is_array( $filters['scope_ids'] ) ) {
			$ids = array_values( array_filter( array_map( 'intval', $filters['scope_ids'] ) ) );
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where[]      = "{$alias}.scope_id IN ({$placeholders})";
				$params       = array_merge( $params, $ids );
			}
		}

		$join = '';
		if ( ! empty( $filters['scope_impact'] ) || ! empty( $filters['deliverable_type_id'] ) ) {
			$join = " INNER JOIN {$this->items_table} i ON i.allocation_id = {$alias}.id ";
			if ( ! empty( $filters['scope_impact'] ) ) {
				$where[]  = 'i.scope_impact = %s';
				$params[] = (string) $filters['scope_impact'];
			}
			if ( ! empty( $filters['deliverable_type_id'] ) ) {
				$where[]  = 'i.deliverable_type_id = %d';
				$params[] = (int) $filters['deliverable_type_id'];
			}
		}

		$limit = '';
		if ( ! empty( $filters['limit'] ) ) {
			$limit = ' LIMIT ' . absint( $filters['limit'] );
		}

		$sql = "SELECT DISTINCT {$alias}.* FROM {$this->table} {$alias}{$join} WHERE "
			. implode( ' AND ', $where )
			. " ORDER BY {$alias}.completed_date DESC, {$alias}.id DESC"
			. $limit;

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql );
		}

		$rows = $rows ?: array();
		if ( ! $rows ) {
			return array();
		}

		$ids     = array_map( static fn( $r ) => (int) $r->id, $rows );
		$grouped = $this->items->list_for_allocations( $ids );

		return array_map(
			static function ( $r ) use ( $grouped ) {
				$id = (int) $r->id;
				return DeliverableAllocation::from_row( $r, $grouped[ $id ] ?? array() );
			},
			$rows
		);
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = array(
			'scope_id'            => (int) $data['scope_id'],
			'patch_id'            => ( isset( $data['patch_id'] ) && null !== $data['patch_id'] && '' !== $data['patch_id'] ) ? (int) $data['patch_id'] : null,
			'task_name'           => (string) ( $data['task_name'] ?? '' ),
			'deliverable_type_id' => null,
			'quantity'            => null,
			'scope_impact'        => null,
			'replaced_type_id'    => null,
			'replacement_qty'     => null,
			'completed_date'      => (string) $data['completed_date'],
			'notes'               => (string) ( $data['notes'] ?? '' ),
			'allocated_by'        => (int) $data['allocated_by'],
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		$wpdb->insert( $this->table, $row );
		$id = (int) $wpdb->insert_id;

		if ( $id > 0 && ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			$this->items->replace_for_allocation( $id, $data['items'] );
		}

		return $id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$allowed = array(
			'patch_id',
			'task_name',
			'completed_date',
			'notes',
		);
		$payload = array( 'updated_at' => current_time( 'mysql' ) );
		$formats = array( '%s' );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$payload[ $key ] = $data[ $key ];
			if ( 'patch_id' === $key ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}
		$updated = false !== $wpdb->update( $this->table, $payload, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( array_key_exists( 'items', $data ) && is_array( $data['items'] ) ) {
			$this->items->replace_for_allocation( $id, $data['items'] );
			return true;
		}

		return $updated;
	}

	public function delete( int $id ): bool {
		global $wpdb;
		$this->items->delete_for_allocation( $id );
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
