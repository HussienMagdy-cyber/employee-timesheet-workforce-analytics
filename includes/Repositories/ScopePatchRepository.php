<?php
/**
 * Scope patch repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\ScopePatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopePatchRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_scope_patches';
	}

	public function find( int $id ): ?ScopePatch {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? ScopePatch::from_row( $row ) : null;
	}

	/** @return ScopePatch[] */
	public function for_scope( int $scope_id, ?string $status = null ): array {
		global $wpdb;
		if ( $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE scope_id = %d AND status = %s ORDER BY id ASC",
					$scope_id,
					$status
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$this->table} WHERE scope_id = %d ORDER BY id ASC", $scope_id )
			);
		}
		return array_map( static fn( $r ) => ScopePatch::from_row( $r ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'scope_id'     => (int) $data['scope_id'],
				'name'         => (string) $data['name'],
				'code'         => $data['code'] ?? null,
				'description'  => (string) ( $data['description'] ?? '' ),
				'status'       => (string) ( $data['status'] ?? 'draft' ),
				'expected_qty' => (float) ( $data['expected_qty'] ?? 0 ),
				'effective_on' => $data['effective_on'] ?? null,
				'created_by'   => (int) ( $data['created_by'] ?? 0 ),
				'created_at'   => $now,
				'updated_at'   => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$allowed = array( 'name', 'code', 'description', 'status', 'expected_qty', 'effective_on' );
		$payload = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$payload[ $key ] = $data[ $key ];
		}
		return false !== $wpdb->update( $this->table, $payload, array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
