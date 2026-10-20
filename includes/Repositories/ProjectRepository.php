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

	/**
	 * @param int[] $client_ids
	 * @return Project[]
	 */
	public function list_for_clients( array $client_ids, bool $active_only = true ): array {
		global $wpdb;
		$client_ids = array_values( array_filter( array_map( 'intval', $client_ids ) ) );
		if ( ! $client_ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );
		$sql          = "SELECT * FROM {$this->table} WHERE client_id IN ({$placeholders})";
		$params       = $client_ids;
		if ( $active_only ) {
			$sql     .= ' AND status = %s';
			$params[] = Project::STATUS_ACTIVE;
		}
		$sql .= ' ORDER BY name ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Project::from_row( $row ), $rows ?: array() );
	}

	/** @return Project[] */
	public function list_standalone( bool $active_only = true ): array {
		global $wpdb;
		$sql    = "SELECT * FROM {$this->table} WHERE client_id IS NULL";
		$params = array();
		if ( $active_only ) {
			$sql     .= ' AND status = %s';
			$params[] = Project::STATUS_ACTIVE;
		}
		$sql .= ' ORDER BY CASE WHEN parent_project_id IS NULL THEN 0 ELSE 1 END, name ASC';
		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) )
			: $wpdb->get_results( $sql );
		return array_map( fn( $row ) => Project::from_row( $row ), $rows ?: array() );
	}

	/** @return Project[] */
	public function list_children( int $parent_id, bool $active_only = false ): array {
		global $wpdb;
		$sql    = "SELECT * FROM {$this->table} WHERE parent_project_id = %d";
		$params = array( $parent_id );
		if ( $active_only ) {
			$sql     .= ' AND status = %s';
			$params[] = Project::STATUS_ACTIVE;
		}
		$sql .= ' ORDER BY name ASC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Project::from_row( $row ), $rows ?: array() );
	}

	public function count_children( int $parent_id, bool $active_only = false ): int {
		global $wpdb;
		$sql    = "SELECT COUNT(*) FROM {$this->table} WHERE parent_project_id = %d";
		$params = array( $parent_id );
		if ( $active_only ) {
			$sql     .= ' AND status = %s';
			$params[] = Project::STATUS_ACTIVE;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
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

	public function convert_client_projects_to_standalone( int $from_client_id, int $parent_project_id ): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET client_id = NULL, parent_project_id = %d, updated_at = %s WHERE client_id = %d",
				$parent_project_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	/** @return array<string,true> Lowercase active child names keyed by name. */
	public function active_name_keys_for_standalone_parent( int $parent_id ): array {
		$keys = array();
		foreach ( $this->list_children( $parent_id, true ) as $child ) {
			$key = strtolower( trim( $child->name ) );
			if ( '' !== $key ) {
				$keys[ $key ] = true;
			}
		}
		return $keys;
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
