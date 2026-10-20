<?php
/**
 * Client scope repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\ClientScope;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientScopeRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_client_scopes';
	}

	public function find( int $id ): ?ClientScope {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? ClientScope::from_row( $row ) : null;
	}

	/**
	 * @param array{client_id?:int,project_id?:int,status?:string,search?:string} $filters
	 * @return ClientScope[]
	 */
	public function list( array $filters = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['client_id'] ) ) {
			$where[]  = 'client_id = %d';
			$params[] = (int) $filters['client_id'];
		}
		if ( ! empty( $filters['project_id'] ) ) {
			$where[]  = 'project_id = %d';
			$params[] = (int) $filters['project_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[]  = '(name LIKE %s OR code LIKE %s)';
			$like     = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC, id DESC';
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql );
		}

		return array_map( static fn( $r ) => ClientScope::from_row( $r ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'client_id'             => (int) $data['client_id'],
				'project_id'            => ! empty( $data['project_id'] ) ? (int) $data['project_id'] : null,
				'name'                  => (string) $data['name'],
				'code'                  => $data['code'] ?? null,
				'description'           => (string) ( $data['description'] ?? '' ),
				'status'                => (string) ( $data['status'] ?? 'active' ),
				'starts_on'             => $data['starts_on'] ?? null,
				'ends_on'               => $data['ends_on'] ?? null,
				'contract_deliverables' => (float) ( $data['contract_deliverables'] ?? 0 ),
				'created_by'            => (int) ( $data['created_by'] ?? 0 ),
				'created_at'            => $now,
				'updated_at'            => $now,
			)
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$allowed = array(
			'client_id',
			'project_id',
			'name',
			'code',
			'description',
			'status',
			'starts_on',
			'ends_on',
			'contract_deliverables',
		);
		$payload = array( 'updated_at' => current_time( 'mysql' ) );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$payload[ $key ] = $data[ $key ];
		}
		return false !== $wpdb->update( $this->table, $payload, array( 'id' => $id ) );
	}
}
