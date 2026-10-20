<?php
/**
 * Super Client request repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Models\SuperClientRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientRequestRepository {

	private string $table;
	private string $brand_fk_column;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_super_client_requests';
		$this->brand_fk_column = self::detect_brand_fk_column( $this->table );
	}

	/**
	 * @param array{brand_id?:int,child_client_id?:int,request_type?:string,service_category?:string} $filters
	 * @return SuperClientRequest[]
	 */
	public function list_by_super_client( int $super_client_id, array $filters = array() ): array {
		global $wpdb;

		$where  = array( 'super_client_id = %d' );
		$params = array( $super_client_id );

		$brand_id = (int) ( $filters['brand_id'] ?? $filters['child_client_id'] ?? 0 );
		if ( $brand_id > 0 ) {
			$where[]  = "{$this->brand_fk_column} = %d";
			$params[] = $brand_id;
		}
		if ( ! empty( $filters['request_type'] ) ) {
			$where[]  = 'request_type = %s';
			$params[] = sanitize_key( (string) $filters['request_type'] );
		}
		if ( ! empty( $filters['service_category'] ) ) {
			$where[]  = 'service_category = %s';
			$params[] = sanitize_key( (string) $filters['service_category'] );
		}

		$sql = "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE uses prepared params.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		return array_map( fn( $row ) => SuperClientRequest::from_row( $row ), $rows ?: array() );
	}

	public function find( int $id ): ?SuperClientRequest {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? SuperClientRequest::from_row( $row ) : null;
	}

	public function count_pending(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				SuperClientRequestHelper::STATUS_PENDING
			)
		);
	}

	/**
	 * Pending request counts grouped by Product Tab in a single query.
	 *
	 * @param int[] $super_client_ids
	 * @return array<int,int>
	 */
	public function count_pending_grouped_by_super_client( array $super_client_ids ): array {
		$super_client_ids = array_values(
			array_filter(
				array_map( 'intval', $super_client_ids ),
				static fn( int $id ): bool => $id > 0
			)
		);

		if ( ! $super_client_ids ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $super_client_ids ), '%d' ) );
		$params       = array_merge( array( SuperClientRequestHelper::STATUS_PENDING ), $super_client_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- IN list uses prepared placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT super_client_id, COUNT(*) AS cnt
				FROM {$this->table}
				WHERE status = %s AND super_client_id IN ({$placeholders})
				GROUP BY super_client_id",
				...$params
			)
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (int) $row->super_client_id ] = (int) $row->cnt;
		}

		return $map;
	}

	public function count_by_super_client( int $super_client_id, ?string $date_from = null, ?string $date_to = null ): int {
		global $wpdb;

		$where  = array( 'super_client_id = %d' );
		$params = array( $super_client_id );
		$this->append_created_date_range( $where, $params, $date_from, $date_to );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE uses prepared params.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table . ' WHERE ' . implode( ' AND ', $where ), ...$params ) );
	}

	public function count_by_brand( int $brand_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE {$this->brand_fk_column} = %d",
				$brand_id
			)
		);
	}

	/**
	 * @return array<string,int>
	 */
	public function count_grouped( int $super_client_id, string $column, ?string $date_from = null, ?string $date_to = null ): array {
		global $wpdb;

		$allowed = array( 'request_type', 'service_category', 'service' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$where  = array( 'super_client_id = %d' );
		$params = array( $super_client_id );
		$this->append_created_date_range( $where, $params, $date_from, $date_to );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is whitelisted.
		$sql = "SELECT {$column} AS grp, COUNT(*) AS cnt FROM {$this->table} WHERE " . implode( ' AND ', $where ) . " GROUP BY {$column}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE uses prepared params.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (string) $row->grp ] = (int) $row->cnt;
		}
		return $map;
	}

	/**
	 * @return array<int,int> brand_id => count
	 */
	public function count_by_brands( int $super_client_id, ?string $date_from = null, ?string $date_to = null ): array {
		global $wpdb;

		$where  = array( 'super_client_id = %d' );
		$params = array( $super_client_id );
		$this->append_created_date_range( $where, $params, $date_from, $date_to );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is whitelisted.
		$sql = "SELECT {$this->brand_fk_column} AS brand_id, COUNT(*) AS cnt FROM {$this->table} WHERE " . implode( ' AND ', $where ) . " GROUP BY {$this->brand_fk_column}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE uses prepared params.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (int) $row->brand_id ] = (int) $row->cnt;
		}
		return $map;
	}

	/**
	 * @return array<string,int>
	 */
	public function count_by_brand_industry( int $super_client_id, ?string $date_from = null, ?string $date_to = null ): array {
		global $wpdb;

		$brands = $wpdb->prefix . 'etwa_super_clients';
		$fk     = $this->brand_fk_column;

		$where  = array( 'r.super_client_id = %d', "b.industry != ''" );
		$params = array( $super_client_id );
		$this->append_created_date_range( $where, $params, $date_from, $date_to, 'r.created_at' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names are internal.
		$sql = "SELECT b.industry AS grp, COUNT(*) AS cnt
				FROM {$this->table} r
				INNER JOIN {$brands} b ON b.id = r.{$fk}
				WHERE " . implode( ' AND ', $where ) . '
				GROUP BY b.industry';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE uses prepared params.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (string) $row->grp ] = (int) $row->cnt;
		}
		return $map;
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now                = current_time( 'mysql' );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		if ( isset( $data['brand_id'] ) && 'child_client_id' === $this->brand_fk_column ) {
			$data['child_client_id'] = $data['brand_id'];
			unset( $data['brand_id'] );
		}
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		if ( isset( $data['brand_id'] ) && 'child_client_id' === $this->brand_fk_column ) {
			$data['child_client_id'] = $data['brand_id'];
			unset( $data['brand_id'] );
		}
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param array<int,string> $where
	 * @param array<int,mixed>  $params
	 */
	private function append_created_date_range( array &$where, array &$params, ?string $date_from, ?string $date_to, string $column = 'created_at' ): void {
		if ( ! $date_from || ! $date_to ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is internal/whitelisted.
		$where[]  = "DATE({$column}) BETWEEN %s AND %s";
		$params[] = $date_from;
		$params[] = $date_to;
	}

	private static function detect_brand_fk_column( string $table ): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- migration metadata.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$table,
				'brand_id'
			)
		);

		return (int) $exists > 0 ? 'brand_id' : 'child_client_id';
	}
}
