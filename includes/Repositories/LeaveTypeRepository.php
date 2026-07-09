<?php
/**
 * Leave type repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\LeaveType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LeaveTypeRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_leave_types';
	}

	public function find( int $id ): ?LeaveType {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? LeaveType::from_row( $row ) : null;
	}

	public function find_by_slug( string $slug ): ?LeaveType {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug ) );
		return $row ? LeaveType::from_row( $row ) : null;
	}

	/** @return LeaveType[] */
	public function all_active(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
		);
		return array_map( fn( $row ) => LeaveType::from_row( $row ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'slug'               => $data['slug'],
				'name'               => $data['name'],
				'tracks_allocation'  => (int) ( $data['tracks_allocation'] ?? 1 ),
				'sort_order'         => (int) ( $data['sort_order'] ?? 0 ),
				'is_active'          => (int) ( $data['is_active'] ?? 1 ),
				'created_at'         => $now,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}
}
