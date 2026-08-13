<?php
/**
 * Deliverable allocation item repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\DeliverableAllocationItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableAllocationItemRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_deliverable_allocation_items';
	}

	/**
	 * @return DeliverableAllocationItem[]
	 */
	public function list_for_allocation( int $allocation_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE allocation_id = %d ORDER BY sort_order ASC, id ASC",
				$allocation_id
			)
		);
		return array_map( static fn( $r ) => DeliverableAllocationItem::from_row( $r ), $rows ?: array() );
	}

	/**
	 * @param int[] $allocation_ids
	 * @return array<int,DeliverableAllocationItem[]> keyed by allocation_id
	 */
	public function list_for_allocations( array $allocation_ids ): array {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $allocation_ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE allocation_id IN ({$placeholders}) ORDER BY allocation_id ASC, sort_order ASC, id ASC",
				$ids
			)
		);

		$grouped = array();
		foreach ( $ids as $id ) {
			$grouped[ $id ] = array();
		}
		foreach ( $rows ?: array() as $row ) {
			$item = DeliverableAllocationItem::from_row( $row );
			$grouped[ $item->allocation_id ][] = $item;
		}
		return $grouped;
	}

	/**
	 * Replace all items for an allocation.
	 *
	 * @param array<int,array<string,mixed>> $items
	 */
	public function replace_for_allocation( int $allocation_id, array $items ): void {
		global $wpdb;
		$this->delete_for_allocation( $allocation_id );

		$now = current_time( 'mysql' );
		foreach ( array_values( $items ) as $index => $item ) {
			$impact = (string) ( $item['scope_impact'] ?? 'normal' );
			$wpdb->insert(
				$this->table,
				array(
					'allocation_id'       => $allocation_id,
					'deliverable_type_id' => (int) $item['deliverable_type_id'],
					'quantity'            => (float) $item['quantity'],
					'scope_impact'        => $impact,
					'replaced_type_id'    => 'replacement' === $impact ? (int) ( $item['replaced_type_id'] ?? 0 ) : null,
					'replacement_qty'     => 'replacement' === $impact ? (float) ( $item['replacement_qty'] ?? 0 ) : null,
					'sort_order'          => (int) ( $item['sort_order'] ?? $index ),
					'created_at'          => $now,
					'updated_at'          => $now,
				)
			);
		}
	}

	public function delete_for_allocation( int $allocation_id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'allocation_id' => $allocation_id ), array( '%d' ) );
	}
}
