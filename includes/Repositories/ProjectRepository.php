<?php
/**
 * Project repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Project;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProjectRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_projects';
	}

	public function find( int $id ): ?Project {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Project::from_row( $row ) : null;
	}

	/** @return Project[] */
	public function list_for_client( int $client_id, bool $active_only = true ): array {
		global $wpdb;
		$sql    = "SELECT * FROM {$this->table} WHERE client_id = %d";
		$params = array( $client_id );
		if ( $active_only ) {
			$sql .= " AND status = %s";
			$params[] = Project::STATUS_ACTIVE;
		}
		$sql .= ' ORDER BY name ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Project::from_row( $row ), $rows ?: array() );
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array_merge(
				$data,
				array(
					'created_at' => $now,
					'updated_at' => $now,
				)
			)
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function archive( int $id ): bool {
		return $this->update( $id, array( 'status' => Project::STATUS_ARCHIVED ) );
	}

	public function reassign_client( int $from_client_id, int $to_client_id ): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET client_id = %d, updated_at = %s WHERE client_id = %d",
				$to_client_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function count_for_client( int $client_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE client_id = %d",
				$client_id
			)
		);
	}

	public function count_active_for_client( int $client_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE client_id = %d AND status = %s",
				$client_id,
				Project::STATUS_ACTIVE
			)
		);
	}

	/** @return array<string,true> Lowercase active project names keyed by name. */
	public function active_name_keys_for_client( int $client_id ): array {
		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT name FROM {$this->table} WHERE client_id = %d AND status = %s",
				$client_id,
				Project::STATUS_ACTIVE
			)
		);

		$keys = array();
		foreach ( $rows ?: array() as $name ) {
			$key = strtolower( trim( (string) $name ) );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}

		return $keys;
	}
}
