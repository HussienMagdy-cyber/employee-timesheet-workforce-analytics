<?php
/**
 * Vacation transaction ledger repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\VacationTransaction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationTransactionRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_vacation_transactions';
	}

	public function find( int $id ): ?VacationTransaction {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? VacationTransaction::from_row( $row ) : null;
	}

	public function find_by_record( int $record_id ): ?VacationTransaction {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE record_id = %d AND transaction_type = 'usage' LIMIT 1",
				$record_id
			)
		);
		return $row ? VacationTransaction::from_row( $row ) : null;
	}

	/** @return array<string,float> leave_type_id => sum days */
	public function sum_by_type( int $user_id, ?string $transaction_type = null ): array {
		global $wpdb;

		$sql = "SELECT leave_type_id, SUM(days) AS total FROM {$this->table} WHERE user_id = %d";
		$params = array( $user_id );

		if ( $transaction_type ) {
			$sql     .= ' AND transaction_type = %s';
			$params[] = $transaction_type;
		}

		$sql .= ' GROUP BY leave_type_id';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
		$map  = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (int) $row->leave_type_id ] = (float) $row->total;
		}
		return $map;
	}

	public function allocation_exists( int $user_id, int $leave_type_id, string $effective_date, ?int $exclude_id = null ): bool {
		global $wpdb;

		$sql    = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND leave_type_id = %d AND transaction_type = 'allocation' AND effective_date = %s";
		$params = array( $user_id, $leave_type_id, $effective_date );

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) ) > 0;
	}

	/** @param array<string,mixed> $filters */
	public function count_history( array $filters ): int {
		global $wpdb;
		$where  = $this->build_history_where( $filters );
		$records = $wpdb->prefix . 'etwa_vacation_records';
		$types   = $wpdb->prefix . 'etwa_leave_types';

		$params = array_merge( $where['params'], $where['record_params'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic WHERE built with prepare.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (
				SELECT t.id FROM {$this->table} t
				INNER JOIN {$types} lt ON lt.id = t.leave_type_id
				{$where['sql']}
				UNION ALL
				SELECT r.id FROM {$records} r
				INNER JOIN {$types} lt ON lt.id = r.leave_type_id
				{$where['records_sql']}
			) AS combined",
				...$params
			)
		);
	}

	/**
	 * @param array<string,mixed> $filters
	 * @return object[]
	 */
	public function list_history( array $filters, int $limit, int $offset ): array {
		global $wpdb;
		$where   = $this->build_history_where( $filters );
		$records = $wpdb->prefix . 'etwa_vacation_records';
		$types   = $wpdb->prefix . 'etwa_leave_types';

		$sql = "SELECT * FROM (
			SELECT
				t.id AS id,
				'transaction' AS source,
				t.user_id,
				t.leave_type_id,
				lt.name AS leave_type_name,
				t.transaction_type,
				t.days,
				NULL AS status,
				t.notes AS reason,
				t.effective_date AS event_date,
				t.created_by,
				t.created_at,
				t.record_id
			FROM {$this->table} t
			INNER JOIN {$types} lt ON lt.id = t.leave_type_id
			{$where['sql']}
			UNION ALL
			SELECT
				r.id AS id,
				'record' AS source,
				r.user_id,
				r.leave_type_id,
				lt.name AS leave_type_name,
				'vacation' AS transaction_type,
				-r.days AS days,
				r.status,
				r.reason,
				r.start_date AS event_date,
				r.created_by,
				r.created_at,
				r.id AS record_id
			FROM {$records} r
			INNER JOIN {$types} lt ON lt.id = r.leave_type_id
			{$where['records_sql']}
			AND r.status IN ('pending','rejected')
		) AS history
		ORDER BY event_date DESC, created_at DESC
		LIMIT %d OFFSET %d";

		$params = array_merge( $where['params'], $where['record_params'], array( $limit, $offset ) );

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: array();
	}

	/** @param array<string,mixed> $filters @return array{sql:string,params:array<int,mixed>,records_sql:string,record_params:array<int,mixed>} */
	private function build_history_where( array $filters ): array {
		global $wpdb;
		$records = $wpdb->prefix . 'etwa_vacation_records';

		$clauses  = array( '1=1' );
		$params   = array();
		$r_clauses = array( "NOT EXISTS (SELECT 1 FROM {$this->table} tx WHERE tx.record_id = r.id AND tx.transaction_type = 'usage')" );
		$r_params  = array();

		if ( ! empty( $filters['user_id'] ) ) {
			$clauses[]   = 't.user_id = %d';
			$params[]    = (int) $filters['user_id'];
			$r_clauses[] = 'r.user_id = %d';
			$r_params[]  = (int) $filters['user_id'];
		}

		if ( ! empty( $filters['leave_type_id'] ) ) {
			$clauses[]   = 't.leave_type_id = %d';
			$params[]    = (int) $filters['leave_type_id'];
			$r_clauses[] = 'r.leave_type_id = %d';
			$r_params[]  = (int) $filters['leave_type_id'];
		}

		if ( ! empty( $filters['transaction_type'] ) ) {
			$clauses[] = 't.transaction_type = %s';
			$params[]  = sanitize_text_field( (string) $filters['transaction_type'] );
		}

		if ( ! empty( $filters['month'] ) && ! empty( $filters['year'] ) ) {
			$clauses[]   = 'MONTH(t.effective_date) = %d AND YEAR(t.effective_date) = %d';
			$params[]    = (int) $filters['month'];
			$params[]    = (int) $filters['year'];
			$r_clauses[] = 'MONTH(r.start_date) = %d AND YEAR(r.start_date) = %d';
			$r_params[]  = (int) $filters['month'];
			$r_params[]  = (int) $filters['year'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[]   = 't.effective_date >= %s';
			$params[]    = (string) $filters['date_from'];
			$r_clauses[] = 'r.start_date >= %s';
			$r_params[]  = (string) $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[]   = 't.effective_date <= %s';
			$params[]    = (string) $filters['date_to'];
			$r_clauses[] = 'r.end_date <= %s';
			$r_params[]  = (string) $filters['date_to'];
		}

		if ( ! empty( $filters['keyword'] ) ) {
			$like        = '%' . $wpdb->esc_like( (string) $filters['keyword'] ) . '%';
			$clauses[]   = 't.notes LIKE %s';
			$params[]    = $like;
			$r_clauses[] = '(r.reason LIKE %s OR r.notes LIKE %s)';
			$r_params[]  = $like;
			$r_params[]  = $like;
		}

		if ( ! empty( $filters['status'] ) ) {
			$r_clauses[] = 'r.status = %s';
			$r_params[]  = sanitize_text_field( (string) $filters['status'] );
		}

		return array(
			'sql'           => 'WHERE ' . implode( ' AND ', $clauses ),
			'params'        => $params,
			'records_sql'   => 'WHERE ' . implode( ' AND ', $r_clauses ),
			'record_params' => $r_params,
		);
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'user_id'          => (int) $data['user_id'],
				'leave_type_id'    => (int) $data['leave_type_id'],
				'transaction_type' => $data['transaction_type'],
				'days'             => $data['days'],
				'record_id'        => $data['record_id'] ?? null,
				'effective_date'   => $data['effective_date'],
				'notes'            => $data['notes'] ?? '',
				'created_by'       => (int) $data['created_by'],
				'created_at'       => $now,
			),
			array( '%d', '%d', '%s', '%f', '%d', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();
		$formats = array();

		foreach ( array( 'days', 'effective_date', 'notes', 'leave_type_id' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$formats[]      = in_array( $key, array( 'days' ), true ) ? '%f' : ( 'leave_type_id' === $key ? '%d' : '%s' );
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

	public function delete_by_record( int $record_id ): bool {
		global $wpdb;
		return false !== $wpdb->delete(
			$this->table,
			array(
				'record_id'        => $record_id,
				'transaction_type' => 'usage',
			),
			array( '%d', '%s' )
		);
	}
}
