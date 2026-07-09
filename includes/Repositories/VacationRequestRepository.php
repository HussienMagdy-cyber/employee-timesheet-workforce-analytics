<?php
/**
 * Vacation request repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\VacationRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationRequestRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_vacation_requests';
	}

	public function find( int $id ): ?VacationRequest {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? VacationRequest::from_row( $row ) : null;
	}

	public function count_pending(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending'"
		);
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
	 * @return VacationRequest[]
	 */
	public function all( array $filters, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$where = $this->build_where( $filters );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} r {$where['sql']} ORDER BY r.created_at DESC LIMIT %d OFFSET %d",
				...array_merge( $where['params'], array( $limit, $offset ) )
			)
		);
		return array_map( fn( $row ) => VacationRequest::from_row( $row ), $rows ?: array() );
	}

	/** @param array<string,mixed> $filters @return array{sql:string,params:array<int,mixed>} */
	private function build_where( array $filters ): array {
		$clauses = array( '1=1' );
		$params  = array();

		if ( ! empty( $filters['user_id'] ) ) {
			$clauses[] = 'r.user_id = %d';
			$params[]  = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['user_ids'] ) && is_array( $filters['user_ids'] ) ) {
			$ids = array_map( 'absint', $filters['user_ids'] );
			$ids = array_filter( $ids );
			if ( $ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$clauses[]    = "r.user_id IN ({$placeholders})";
				$params       = array_merge( $params, $ids );
			} else {
				$clauses[] = '1=0';
			}
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
			$params[]  = sanitize_text_field( (string) $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'r.end_date <= %s';
			$params[]  = sanitize_text_field( (string) $filters['date_to'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			global $wpdb;
			$like      = '%' . $wpdb->esc_like( sanitize_text_field( (string) $filters['search'] ) ) . '%';
			$clauses[] = 'r.reason LIKE %s';
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
				'status'          => $data['status'] ?? 'pending',
				'workflow_status' => $data['workflow_status'] ?? 'pending_hr',
				'notes'         => $data['notes'] ?? '',
				'reviewed_by'   => null,
				'reviewed_at'   => null,
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$allowed            = array(
			'status'            => '%s',
			'notes'             => '%s',
			'reviewed_by'       => '%d',
			'reviewed_at'       => '%s',
			'hr_approved_by'    => '%d',
			'hr_approved_at'    => '%s',
			'owner_approved_by' => '%d',
			'owner_approved_at' => '%s',
			'dept_approved_by'  => '%d',
			'dept_approved_at'  => '%s',
			'workflow_status'   => '%s',
			'updated_at'        => '%s',
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
