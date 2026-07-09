<?php
/**
 * Overtime repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Overtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OvertimeRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_overtime';
	}

	public function find( int $id ): ?Overtime {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Overtime::from_row( $row ) : null;
	}

	public function find_by_entry( int $entry_id ): ?Overtime {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE entry_id = %d", $entry_id ) );
		return $row ? Overtime::from_row( $row ) : null;
	}

	/** @return Overtime[] */
	public function find_by_filters( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		list( $where, $params ) = $this->build_filter_where( $filters );

		$offset   = max( 0, ( $page - 1 ) * $per_page );
		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY entry_date DESC, id DESC LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Overtime::from_row( $row ), $rows ?: array() );
	}

	/** @return Overtime[] */
	public function find_by_date_range_page(
		string $date_from,
		string $date_to,
		int $limit,
		int $offset,
		?int $user_id = null
	): array {
		$filters = array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);
		if ( $user_id ) {
			$filters['user_id'] = $user_id;
		}

		global $wpdb;
		list( $where, $params ) = $this->build_filter_where( $filters );

		$sql      = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY entry_date ASC, id ASC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = max( 0, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Overtime::from_row( $row ), $rows ?: array() );
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

	/**
	 * @param array<string,mixed> $filters
	 * @return array{0:string[],1:array<int,mixed>}
	 */
	private function build_filter_where( array $filters ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'entry_date >= %s';
			$params[] = (string) $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'entry_date <= %s';
			$params[] = (string) $filters['date_to'];
		}

		return array( $where, $params );
	}

	/** @param array<string,mixed> $data */
	public function upsert_for_entry( array $data ): int {
		global $wpdb;
		$existing = $this->find_by_entry( (int) $data['entry_id'] );
		$now      = current_time( 'mysql' );

		if ( $existing ) {
			$wpdb->update(
				$this->table,
				array_merge( $data, array( 'updated_at' => $now ) ),
				array( 'id' => $existing->id )
			);
			return (int) $existing->id;
		}

		$wpdb->insert(
			$this->table,
			array_merge( $data, array( 'created_at' => $now, 'updated_at' => $now ) )
		);
		return (int) $wpdb->insert_id;
	}

	public function delete_by_entry( int $entry_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'entry_id' => $entry_id ) );
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	/** @return array{pending:float,approved:float,rejected:float} */
	public function summary_for_user( int $user_id, string $date_from, string $date_to ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, SUM(overtime_hours) AS total, SUM(approved_hours) AS approved
				FROM {$this->table}
				WHERE user_id = %d AND entry_date BETWEEN %s AND %s
				GROUP BY status",
				$user_id,
				$date_from,
				$date_to
			)
		);

		$summary = array( 'pending' => 0.0, 'approved' => 0.0, 'rejected' => 0.0 );
		foreach ( $rows ?: array() as $row ) {
			$summary[ $row->status ] = (float) $row->total;
			if ( 'approved' === $row->status ) {
				$summary['approved'] = (float) $row->approved;
			}
		}
		return $summary;
	}
}
