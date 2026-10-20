<?php
/**
 * Entry repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Entry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntryRepository {

	private string $table;
	private string $clients_table;
	private string $projects_table;
	private string $prospects_table;

	public function __construct() {
		global $wpdb;
		$this->table           = $wpdb->prefix . 'etwa_entries';
		$this->clients_table   = $wpdb->prefix . 'etwa_clients';
		$this->projects_table  = $wpdb->prefix . 'etwa_projects';
		$this->prospects_table = $wpdb->prefix . 'etwa_prospects';
	}

	private function entry_select_sql(): string {
		return "SELECT e.*, c.name AS client_name, p.name AS project_name, pr.name AS prospect_name
			FROM {$this->table} e
			LEFT JOIN {$this->clients_table} c ON c.id = e.client_id
			LEFT JOIN {$this->projects_table} p ON p.id = e.project_id
			LEFT JOIN {$this->prospects_table} pr ON pr.id = e.prospect_id";
	}

	public function find( int $id ): ?Entry {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				$this->entry_select_sql() . ' WHERE e.id = %d',
				$id
			)
		);
		return $row ? Entry::from_row( $row ) : null;
	}

	/** @return Entry[] */
	public function find_by_timesheet( int $timesheet_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$this->entry_select_sql() . ' WHERE e.timesheet_id = %d ORDER BY e.entry_date ASC, e.start_time ASC',
				$timesheet_id
			)
		);
		return array_map( fn( $row ) => Entry::from_row( $row ), $rows ?: array() );
	}

	/** @return Entry[] */
	public function find_by_date_range( string $date_from, string $date_to, ?int $user_id = null ): array {
		return $this->find_by_date_range_page( $date_from, $date_to, 0, 0, $user_id );
	}

	/**
	 * @param int[]|null $project_ids
	 * @return Entry[]
	 */
	public function find_by_date_range_page(
		string $date_from,
		string $date_to,
		int $limit,
		int $offset,
		?int $user_id = null,
		?int $client_id = null,
		?int $project_id = null,
		?array $project_ids = null
	): array {
		global $wpdb;
		$sql    = $this->entry_select_sql() . ' WHERE e.entry_date BETWEEN %s AND %s';
		$params = array( $date_from, $date_to );

		if ( $user_id ) {
			$sql     .= ' AND e.user_id = %d';
			$params[] = $user_id;
		}
		if ( $client_id ) {
			$sql     .= ' AND e.client_id = %d';
			$params[] = $client_id;
		}
		$project_ids = array_values( array_filter( array_map( 'intval', $project_ids ?? array() ) ) );
		if ( $project_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
			$sql         .= " AND e.project_id IN ({$placeholders})";
			$params       = array_merge( $params, $project_ids );
		} elseif ( $project_id ) {
			$sql     .= ' AND e.project_id = %d';
			$params[] = $project_id;
		}
		$sql .= ' ORDER BY e.entry_date ASC, e.start_time ASC, e.id ASC';

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = max( 0, $offset );
		}

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		return array_map( fn( $row ) => Entry::from_row( $row ), $rows ?: array() );
	}

	/** @return Entry[] */
	public function find_by_prospect( int $prospect_id, int $limit = 100 ): array {
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				$this->entry_select_sql() . ' WHERE e.prospect_id = %d ORDER BY e.entry_date DESC, e.start_time DESC, e.id DESC LIMIT %d',
				$prospect_id,
				$limit
			)
		);
		return array_map( fn( $row ) => Entry::from_row( $row ), $rows ?: array() );
	}

	public function count_by_date_range( string $date_from, string $date_to, ?int $user_id = null ): int {
		global $wpdb;
		$sql    = "SELECT COUNT(*) FROM {$this->table} WHERE entry_date BETWEEN %s AND %s";
		$params = array( $date_from, $date_to );

		if ( $user_id ) {
			$sql     .= ' AND user_id = %d';
			$params[] = $user_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return int[]
	 */
	public function create_many( array $rows ): array {
		if ( ! $rows ) {
			return array();
		}

		global $wpdb;
		$now = current_time( 'mysql' );
		$ids = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $rows as $data ) {
			$inserted = $wpdb->insert(
				$this->table,
				array_merge( $data, array( 'created_at' => $now, 'updated_at' => $now ) )
			);
			if ( false === $inserted ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return array();
			}
			$ids[] = (int) $wpdb->insert_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );
		return $ids;
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$this->table,
			array_merge( $data, array( 'created_at' => $now, 'updated_at' => $now ) )
		);
		if ( false === $inserted ) {
			error_log( 'ETWA EntryRepository::create() insert failed: ' . $wpdb->last_error );
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ) );
	}

	public function delete_by_timesheet( int $timesheet_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table, array( 'timesheet_id' => $timesheet_id ) );
	}

	public function has_overlap(
		int $timesheet_id,
		string $entry_date,
		string $start_time,
		string $end_time,
		?int $exclude_id = null
	): bool {
		global $wpdb;

		$sql    = "SELECT COUNT(*) FROM {$this->table}
			WHERE timesheet_id = %d AND entry_date = %s
			AND start_time < %s AND end_time > %s";
		$params = array( $timesheet_id, $entry_date, $end_time, $start_time );

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ) > 0;
	}

	/** @return array{regular:float,overtime:float} */
	public function sum_hours_by_timesheet( int $timesheet_id ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(regular_hours),0) AS regular, COALESCE(SUM(overtime_hours),0) AS overtime
				FROM {$this->table} WHERE timesheet_id = %d",
				$timesheet_id
			)
		);
		return array(
			'regular'  => (float) ( $row->regular ?? 0 ),
			'overtime' => (float) ( $row->overtime ?? 0 ),
		);
	}
}
