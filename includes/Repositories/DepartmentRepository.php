<?php
/**
 * Department repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Department;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_departments';
	}

	public function find( int $id ): ?Department {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Department::from_row( $row ) : null;
	}

	public function find_by_head( int $head_user_id ): ?Department {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE head_user_id = %d AND status = 'active' LIMIT 1",
				$head_user_id
			)
		);
		return $row ? Department::from_row( $row ) : null;
	}

	public function count_by_head( int $head_user_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE head_user_id = %d AND status = 'active'",
				$head_user_id
			)
		);
	}

	public function count( ?string $status = null ): int {
		global $wpdb;
		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status )
			);
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/** @return Department[] */
	public function all( ?string $status = null ): array {
		global $wpdb;
		if ( $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC",
					$status
				)
			);
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY name ASC" );
		}
		return array_map( fn( $row ) => Department::from_row( $row ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'name'              => $data['name'],
				'description'       => $data['description'] ?? '',
				'head_user_id'      => $data['head_user_id'] ?? null,
				'approval_enabled'  => ! empty( $data['approval_enabled'] ) ? 1 : 0,
				'status'            => $data['status'] ?? 'active',
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$allowed            = array(
			'name'             => '%s',
			'description'      => '%s',
			'head_user_id'     => '%d',
			'approval_enabled' => '%d',
			'status'           => '%s',
			'updated_at'       => '%s',
		);
		$fields             = array();
		$formats            = array();
		foreach ( $allowed as $key => $format ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$formats[]      = $format;
			}
		}
		if ( ! $fields ) {
			return false;
		}
		return false !== $wpdb->update( $this->table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	public function name_exists( string $name, ?int $exclude_id = null ): bool {
		global $wpdb;
		if ( $exclude_id ) {
			return (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE name = %s AND id != %d",
					$name,
					$exclude_id
				)
			);
		}
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE name = %s", $name )
		);
	}
}
