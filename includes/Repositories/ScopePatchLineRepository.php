<?php
/**
 * Scope patch line repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\ScopePatchLine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopePatchLineRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_scope_patch_lines';
	}

	/** @return ScopePatchLine[] */
	public function for_patch( int $patch_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE patch_id = %d ORDER BY id ASC", $patch_id )
		);
		return array_map( static fn( $r ) => ScopePatchLine::from_row( $r ), $rows ?: array() );
	}

	/** @return array<int,float> type_id => qty_delta sum for active patches on scope */
	public function active_deltas_by_type( int $scope_id ): array {
		global $wpdb;
		$patches = $wpdb->prefix . 'etwa_scope_patches';
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.deliverable_type_id, SUM(l.qty_delta) AS qty_delta
				FROM {$this->table} l
				INNER JOIN {$patches} p ON p.id = l.patch_id
				WHERE p.scope_id = %d AND p.status = %s
				GROUP BY l.deliverable_type_id",
				$scope_id,
				'active'
			)
		);
		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (int) $row->deliverable_type_id ] = (float) $row->qty_delta;
		}
		return $map;
	}

	/** @return array<int,float> type_id => qty_delta for one patch */
	public function deltas_by_type( int $patch_id ): array {
		$map = array();
		foreach ( $this->for_patch( $patch_id ) as $line ) {
			$map[ $line->deliverable_type_id ] = ( $map[ $line->deliverable_type_id ] ?? 0.0 ) + $line->qty_delta;
		}
		return $map;
	}

	public function delete_for_patch( int $patch_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'patch_id' => $patch_id ), array( '%d' ) );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'patch_id'            => (int) $data['patch_id'],
				'deliverable_type_id' => (int) $data['deliverable_type_id'],
				'qty_delta'           => (float) $data['qty_delta'],
				'notes'               => (string) ( $data['notes'] ?? '' ),
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
