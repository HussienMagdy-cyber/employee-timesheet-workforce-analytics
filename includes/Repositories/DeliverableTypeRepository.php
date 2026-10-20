<?php
/**
 * Deliverable type repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\DeliverableType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableTypeRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_deliverable_types';
	}

	public function find( int $id ): ?DeliverableType {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? DeliverableType::from_row( $row ) : null;
	}

	public function find_by_slug( string $slug ): ?DeliverableType {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug ) );
		return $row ? DeliverableType::from_row( $row ) : null;
	}

	/** @return DeliverableType[] */
	public function all( ?bool $active_only = null ): array {
		global $wpdb;
		$sql = "SELECT * FROM {$this->table}";
		if ( true === $active_only ) {
			$sql .= ' WHERE is_active = 1';
		} elseif ( false === $active_only ) {
			$sql .= ' WHERE is_active = 0';
		}
		$sql .= ' ORDER BY sort_order ASC, name ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql );
		return array_map( static fn( $r ) => DeliverableType::from_row( $r ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'slug'         => (string) $data['slug'],
				'name'         => (string) $data['name'],
				'description'  => (string) ( $data['description'] ?? '' ),
				'effort_score' => (float) ( $data['effort_score'] ?? 1 ),
				'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
				'is_active'    => (int) ( $data['is_active'] ?? 1 ),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$allowed = array( 'slug', 'name', 'description', 'effort_score', 'sort_order', 'is_active' );
		$payload = array( 'updated_at' => current_time( 'mysql' ) );
		$formats = array( '%s' );
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$payload[ $key ] = $data[ $key ];
			$formats[]       = in_array( $key, array( 'effort_score' ), true ) ? '%f' : ( in_array( $key, array( 'sort_order', 'is_active' ), true ) ? '%d' : '%s' );
		}
		return false !== $wpdb->update( $this->table, $payload, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public function is_referenced( int $id ): bool {
		global $wpdb;
		$p = $wpdb->prefix;
		$q = $wpdb->prepare(
			"SELECT (
				(SELECT COUNT(1) FROM {$p}etwa_deliverable_allocation_items WHERE deliverable_type_id = %d OR replaced_type_id = %d)
			)",
			$id,
			$id
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $q ) > 0;
	}
}
