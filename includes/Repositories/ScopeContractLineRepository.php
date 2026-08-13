<?php
/**
 * Scope contract line repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\ScopeContractLine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopeContractLineRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_scope_contract_lines';
	}

	/** @return ScopeContractLine[] */
	public function for_scope( int $scope_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE scope_id = %d ORDER BY id ASC", $scope_id )
		);
		return array_map( static fn( $r ) => ScopeContractLine::from_row( $r ), $rows ?: array() );
	}

	/** @return array<int,float> type_id => planned_qty */
	public function planned_by_type( int $scope_id ): array {
		$map = array();
		foreach ( $this->for_scope( $scope_id ) as $line ) {
			$map[ $line->deliverable_type_id ] = ( $map[ $line->deliverable_type_id ] ?? 0.0 ) + $line->planned_qty;
		}
		return $map;
	}

	public function delete_for_scope( int $scope_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'scope_id' => $scope_id ), array( '%d' ) );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'scope_id'            => (int) $data['scope_id'],
				'deliverable_type_id' => (int) $data['deliverable_type_id'],
				'planned_qty'         => (float) $data['planned_qty'],
				'notes'               => (string) ( $data['notes'] ?? '' ),
				'created_at'          => $now,
				'updated_at'          => $now,
			),
			array( '%d', '%d', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}
}
