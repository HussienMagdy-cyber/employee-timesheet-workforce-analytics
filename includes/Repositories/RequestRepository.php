<?php
/**
 * Generic employee request repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_requests';
	}

	public function find( int $id ): ?Request {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Request::from_row( $row ) : null;
	}

	public function count_pending( ?string $request_type = null ): int {
		global $wpdb;

		if ( $request_type ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending' AND request_type = %s",
					sanitize_key( $request_type )
				)
			);
		}

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
	 * @return Request[]
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
		return array_map( fn( $row ) => Request::from_row( $row ), $rows ?: array() );
	}

	/** @param array<string,mixed> $filters @return array{sql:string,params:array<int,mixed>} */
	private function build_where( array $filters ): array {
		global $wpdb;

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
		if ( ! empty( $filters['request_type'] ) ) {
			$clauses[] = 'r.request_type = %s';
			$params[]  = sanitize_key( (string) $filters['request_type'] );
		}
		if ( ! empty( $filters['status'] ) ) {
			$clauses[] = 'r.status = %s';
			$params[]  = sanitize_text_field( (string) $filters['status'] );
		}
		if ( ! empty( $filters['permission_type'] ) ) {
			$clauses[] = 'r.permission_type = %s';
			$params[]  = sanitize_key( (string) $filters['permission_type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'COALESCE(r.work_date, r.request_date) >= %s';
			$params[]  = sanitize_text_field( (string) $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'COALESCE(r.work_date, r.request_date) <= %s';
			$params[]  = sanitize_text_field( (string) $filters['date_to'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( sanitize_text_field( (string) $filters['search'] ) ) . '%';
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
				'request_type'    => sanitize_key( (string) $data['request_type'] ),
				'user_id'         => (int) $data['user_id'],
				'request_date'    => $data['request_date'],
				'work_date'       => $data['work_date'] ?? null,
				'start_time'      => $data['start_time'] ?? null,
				'end_time'        => $data['end_time'] ?? null,
				'permission_type' => $data['permission_type'] ?? null,
				'reason'          => $data['reason'] ?? '',
				'notes'           => $data['notes'] ?? '',
				'status'          => $data['status'] ?? 'pending',
				'workflow_status' => $data['workflow_status'] ?? 'pending_hr',
				'extra_data'      => $data['extra_data'] ?? null,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		$allowed            = array(
			'request_date'      => '%s',
			'work_date'         => '%s',
			'start_time'        => '%s',
			'end_time'          => '%s',
			'permission_type'   => '%s',
			'reason'            => '%s',
			'notes'             => '%s',
			'status'            => '%s',
			'reviewed_by'       => '%d',
			'reviewed_at'       => '%s',
			'hr_approved_by'    => '%d',
			'hr_approved_at'    => '%s',
			'owner_approved_by' => '%d',
			'owner_approved_at' => '%s',
			'dept_approved_by'  => '%d',
			'dept_approved_at'  => '%s',
			'workflow_status'   => '%s',
			'extra_data'        => '%s',
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

	public function count_approved_permissions_in_month( int $user_id, int $year, int $month, ?int $exclude_id = null ): int {
		global $wpdb;

		$sql    = "SELECT COUNT(*) FROM {$this->table}
			WHERE user_id = %d
			AND request_type = %s
			AND status = %s
			AND MONTH(COALESCE(work_date, request_date)) = %d
			AND YEAR(COALESCE(work_date, request_date)) = %d";
		$params = array(
			$user_id,
			\ETWA\Helpers\RequestHelper::TYPE_PERMISSION,
			\ETWA\Helpers\RequestHelper::STATUS_APPROVED,
			$month,
			$year,
		);

		if ( $exclude_id ) {
			$sql     .= ' AND id != %d';
			$params[] = $exclude_id;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	public function sum_permission_balance_deducted_in_month( int $user_id, int $year, int $month ): float {
		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT extra_data FROM {$this->table}
				WHERE user_id = %d
				AND request_type = %s
				AND status = %s
				AND MONTH(COALESCE(work_date, request_date)) = %d
				AND YEAR(COALESCE(work_date, request_date)) = %d",
				$user_id,
				\ETWA\Helpers\RequestHelper::TYPE_PERMISSION,
				\ETWA\Helpers\RequestHelper::STATUS_APPROVED,
				$month,
				$year
			)
		);

		$sum = 0.0;
		foreach ( $rows ?: array() as $extra_json ) {
			if ( ! is_string( $extra_json ) || '' === $extra_json ) {
				continue;
			}
			$extra = json_decode( $extra_json, true );
			if ( ! is_array( $extra ) ) {
				continue;
			}
			$sum += (float) ( $extra[ \ETWA\Helpers\PermissionHelper::EXTRA_BALANCE_DEDUCTED ] ?? 0 );
		}

		return round( $sum, 2 );
	}
}
