<?php
/**
 * Timesheet repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Timesheet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimesheetRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_timesheets';
	}

	public function find( int $id ): ?Timesheet {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Timesheet::from_row( $row ) : null;
	}

	public function find_by_user_period( int $user_id, string $period_start, string $period_end ): ?Timesheet {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d AND period_start = %s AND period_end = %s",
				$user_id,
				$period_start,
				$period_end
			)
		);
		return $row ? Timesheet::from_row( $row ) : null;
	}

	/**
	 * @param int[] $user_ids
	 * @return array<int,Timesheet>
	 */
	public function find_by_users_period( array $user_ids, string $period_start, string $period_end ): array {
		global $wpdb;

		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( ! $user_ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$params       = array_merge( array( $period_start, $period_end ), $user_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE period_start = %s AND period_end = %s AND user_id IN ({$placeholders})",
				...$params
			)
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (int) $row->user_id ] = Timesheet::from_row( $row );
		}

		return $map;
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return array{0:string[],1:array<int,mixed>}
	 */
	private function build_filter_where( array $filters ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'period_start >= %s';
			$params[] = (string) $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'period_end <= %s';
			$params[] = (string) $filters['date_to'];
		}
		if ( ! empty( $filters['search_user_ids'] ) ) {
			$ids          = array_map( 'intval', (array) $filters['search_user_ids'] );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$where[]      = "user_id IN ({$placeholders})";
			$params       = array_merge( $params, $ids );
		}

		return array( $where, $params );
	}

	/** @return Timesheet[] */
	public function find_by_filters( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		list( $where, $params ) = $this->build_filter_where( $filters );

		$offset   = max( 0, ( $page - 1 ) * $per_page );
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY period_start DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Timesheet::from_row( $row ), $rows ?: array() );
	}

	public function count_by_filters( array $filters = array() ): int {
		global $wpdb;

		list( $where, $params ) = $this->build_filter_where( $filters );
		$sql = 'SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . implode( ' AND ', $where );

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
		}

		return (int) $wpdb->get_var( $sql );
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
}
