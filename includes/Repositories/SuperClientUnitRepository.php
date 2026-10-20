<?php
/**
 * Department ↔ Product Tab access (many-to-many junction).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientUnitRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_super_client_units';
	}

	/** @return int[] Product Tab IDs enabled for a department. */
	public function list_ids_for_department( int $department_id ): array {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT super_client_id FROM {$this->table} WHERE department_id = %d ORDER BY super_client_id ASC",
				$department_id
			)
		);

		return array_map( 'intval', $rows ?: array() );
	}

	/**
	 * @param int[] $department_ids
	 * @return array<int,int[]> department_id => product tab ids
	 */
	public function map_for_departments( array $department_ids ): array {
		global $wpdb;

		$department_ids = array_values( array_unique( array_filter( array_map( 'intval', $department_ids ) ) ) );
		if ( ! $department_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $department_ids ), '%d' ) );
		$sql          = "SELECT department_id, super_client_id FROM {$this->table} WHERE department_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from a fixed int list.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$department_ids ) );

		$map = array();
		foreach ( $department_ids as $id ) {
			$map[ $id ] = array();
		}
		foreach ( $rows ?: array() as $row ) {
			$did = (int) $row->department_id;
			$map[ $did ][] = (int) $row->super_client_id;
		}

		return $map;
	}

	/** @param int[] $super_client_ids */
	public function replace_for_department( int $department_id, array $super_client_ids ): void {
		global $wpdb;

		$this->delete_for_department( $department_id );

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $super_client_ids ) ) ) );
		foreach ( $ids as $super_client_id ) {
			$wpdb->insert(
				$this->table,
				array(
					'super_client_id' => $super_client_id,
					'department_id'   => $department_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/** @return int[] @deprecated Use list_ids_for_department(); kept for migration reads. */
	public function list_ids_for_super_client( int $super_client_id ): array {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT department_id FROM {$this->table} WHERE super_client_id = %d ORDER BY department_id ASC",
				$super_client_id
			)
		);

		return array_map( 'intval', $rows ?: array() );
	}

	/**
	 * @param int[] $super_client_ids
	 * @return array<int,int[]> super_client_id => department ids
	 */
	public function map_for_super_clients( array $super_client_ids ): array {
		global $wpdb;

		$super_client_ids = array_values( array_unique( array_filter( array_map( 'intval', $super_client_ids ) ) ) );
		if ( ! $super_client_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $super_client_ids ), '%d' ) );
		$sql          = "SELECT super_client_id, department_id FROM {$this->table} WHERE super_client_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built from a fixed int list.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$super_client_ids ) );

		$map = array();
		foreach ( $super_client_ids as $id ) {
			$map[ $id ] = array();
		}
		foreach ( $rows ?: array() as $row ) {
			$sid = (int) $row->super_client_id;
			$map[ $sid ][] = (int) $row->department_id;
		}

		return $map;
	}

	/** @param int[] $department_ids */
	public function replace_for_super_client( int $super_client_id, array $department_ids ): void {
		global $wpdb;

		$this->delete_for_super_client( $super_client_id );

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $department_ids ) ) ) );
		foreach ( $ids as $department_id ) {
			$wpdb->insert(
				$this->table,
				array(
					'super_client_id' => $super_client_id,
					'department_id'   => $department_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	public function delete_for_super_client( int $super_client_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'super_client_id' => $super_client_id ), array( '%d' ) );
	}

	public function delete_for_department( int $department_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'department_id' => $department_id ), array( '%d' ) );
	}
}
