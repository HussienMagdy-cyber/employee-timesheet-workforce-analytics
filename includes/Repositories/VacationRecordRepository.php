<?php
/**
 * Vacation usage record repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\VacationRecord;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationRecordRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_vacation_records';
	}

	public function find( int $id ): ?VacationRecord {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? VacationRecord::from_row( $row ) : null;
	}

	/** @param array<string,mixed> $filters */
	public function count( array $filters = array() ): int {
		global $wpdb;
		$where = $this->build_where( $filters );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} r {$where['sql']}",
				...$where['params']
			)
		);
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return VacationRecord[]
	 */
	public function all( array $filters, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$where = $this->build_where( $filters );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} r {$where['sql']} ORDER BY start_date DESC, id DESC LIMIT %d OFFSET %d",
				...array_merge( $where['params'], array( $limit, $offset ) )
			)
		);
		return array_map( fn( $row ) => VacationRecord::from_row( $row ), $rows ?: array() );
	}

	/**
	 * Approved records overlapping a date range (for calendar).
	 *
	 * @return object[]
	 */
	public function approved_in_range( string $date_from, string $date_to, ?int $user_id = null ): array {
		global $wpdb;
		$types = $wpdb->prefix . 'etwa_leave_types';

		$sql    = "SELECT r.*, lt.name AS leave_type_name FROM {$this->table} r
			INNER JOIN {$types} lt ON lt.id = r.leave_type_id
			WHERE r.status = 'approved'
			AND r.start_date <= %s AND r.end_date >= %s";
		$params = array( $date_to, $date_from );

		if ( $user_id ) {
			$sql     .= ' AND r.user_id = %d';
			$params[] = $user_id;
		}

		$sql .= ' ORDER BY r.start_date ASC';

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: array();
	}

	/** @return object[] */
	public function currently_on_leave( string $today ): array {
		global $wpdb;
		$types = $wpdb->prefix . 'etwa_leave_types';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, lt.name AS leave_type_name FROM {$this->table} r
				INNER JOIN {$types} lt ON lt.id = r.leave_type_id
				WHERE r.status = 'approved' AND r.start_date <= %s AND r.end_date >= %s
				ORDER BY r.start_date ASC",
				$today,
				$today
			)
		) ?: array();
	}

	public function count_by_status( string $status ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			)
		);
	}

	/** @param array<string,mixed> $filters @return array{sql:string,params:array<int,mixed>} */
	private function build_where( array $filters ): array {
		$clauses = array( '1=1' );
		$params  = array();

		if ( ! empty( $filters['user_id'] ) ) {
			$clauses[] = 'r.user_id = %d';
			$params[]  = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['leave_type_id'] ) ) {
			$clauses[] = 'r.leave_type_id = %d';
			$params[]  = (int) $filters['leave_type_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$clauses[] = 'r.status = %s';
			$params[]  = sanitize_text_field( (string) $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'r.start_date >= %s';
			$params[]  = (string) $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'r.end_date <= %s';
			$params[]  = (string) $filters['date_to'];
		}
		if ( ! empty( $filters['month'] ) && ! empty( $filters['year'] ) ) {
			$clauses[] = 'MONTH(r.start_date) = %d AND YEAR(r.start_date) = %d';
			$params[]  = (int) $filters['month'];
			$params[]  = (int) $filters['year'];
		}
		if ( ! empty( $filters['keyword'] ) ) {
			global $wpdb;
			$like      = '%' . $wpdb->esc_like( (string) $filters['keyword'] ) . '%';
			$clauses[] = '(r.reason LIKE %s OR r.notes LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $clauses ),
			'params' => $params,
		);
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'user_id'       => (int) $data['user_id'],
				'leave_type_id' => (int) $data['leave_type_id'],
				'start_date'    => $data['start_date'],
				'end_date'      => $data['end_date'],
				'days'          => $data['days'],
				'reason'        => $data['reason'] ?? '',
				'status'        => $data['status'],
				'notes'         => $data['notes'] ?? '',
				'created_by'    => (int) $data['created_by'],
				'updated_by'    => null,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$allowed            = array(
			'leave_type_id' => '%d',
			'start_date'    => '%s',
			'end_date'      => '%s',
			'days'          => '%f',
			'reason'        => '%s',
			'status'        => '%s',
			'notes'         => '%s',
			'updated_by'    => '%d',
			'updated_at'    => '%s',
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
}
