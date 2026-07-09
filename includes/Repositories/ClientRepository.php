<?php
/**
 * Client repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientRepository {

	public const BREAK_CODE       = 'BREAK';
	public const ALL_CLIENTS_CODE = 'ALL_CLIENTS';
	public const MEETING_CODE     = 'MEETING';
	public const EVENT_CODE       = 'EVENT';

	/** @return string[] Reserved system client codes. */
	public static function system_codes(): array {
		return array(
			self::BREAK_CODE,
			self::ALL_CLIENTS_CODE,
			self::MEETING_CODE,
			self::EVENT_CODE,
		);
	}

	/** @return string[] Internal work category codes (zero-hour break + billable internal). */
	public static function internal_codes(): array {
		return array(
			self::BREAK_CODE,
			self::MEETING_CODE,
			self::EVENT_CODE,
		);
	}

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_clients';
	}

	public function find( int $id ): ?Client {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Client::from_row( $row ) : null;
	}

	public function find_by_code( string $code ): ?Client {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE code = %s", $code ) );
		return $row ? Client::from_row( $row ) : null;
	}

	public function is_system_client( ?Client $client ): bool {
		if ( ! $client ) {
			return false;
		}
		return in_array( strtoupper( $client->code ), self::system_codes(), true );
	}

	public function is_break_client( int $client_id, ?Client $client = null ): bool {
		$client = $client ?? $this->find( $client_id );
		return $client && self::BREAK_CODE === strtoupper( $client->code );
	}

	public function is_all_clients_client( int $client_id, ?Client $client = null ): bool {
		$client = $client ?? $this->find( $client_id );
		return $client && self::ALL_CLIENTS_CODE === strtoupper( $client->code );
	}

	public function is_internal_client( int $client_id, ?Client $client = null ): bool {
		$client = $client ?? $this->find( $client_id );
		return $client && in_array( strtoupper( $client->code ), self::internal_codes(), true );
	}

	/** @return Client[] */
	public function all_for_dropdown( ?string $search = null ): array {
		global $wpdb;
		$order_codes = array(
			self::BREAK_CODE,
			self::MEETING_CODE,
			self::EVENT_CODE,
			self::ALL_CLIENTS_CODE,
		);
		$case_parts  = array();
		foreach ( $order_codes as $i => $code ) {
			$case_parts[] = $wpdb->prepare( 'WHEN code = %s THEN %d', $code, $i );
		}
		$order_case = 'CASE ' . implode( ' ', $case_parts ) . ' ELSE 99 END';

		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ORDER CASE uses prepared fragments.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table}
					WHERE is_active = 1 AND (name LIKE %s OR code LIKE %s)
					ORDER BY {$order_case}, name ASC",
					$like,
					$like
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ORDER CASE uses prepared fragments.
			$rows = $wpdb->get_results(
				"SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY {$order_case}, name ASC"
			);
		}
		return array_map( fn( $row ) => Client::from_row( $row ), $rows ?: array() );
	}

	/** @return Client[] */
	public function all( bool $active_only = false ): array {
		global $wpdb;
		$sql = $active_only
			? "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC"
			: "SELECT * FROM {$this->table} ORDER BY CASE WHEN code = 'BREAK' THEN 0 ELSE 1 END, name ASC";
		$rows = $wpdb->get_results( $sql );
		return array_map( fn( $row ) => Client::from_row( $row ), $rows ?: array() );
	}

	public function ensure_system_clients(): void {
		$system = array(
			array(
				'code'       => self::BREAK_CODE,
				'name'       => __( 'Break', 'etwa' ),
				'department' => 'Internal',
			),
			array(
				'code'       => self::MEETING_CODE,
				'name'       => __( 'Meeting', 'etwa' ),
				'department' => 'Internal',
			),
			array(
				'code'       => self::EVENT_CODE,
				'name'       => __( 'Event', 'etwa' ),
				'department' => 'Internal',
			),
			array(
				'code'       => self::ALL_CLIENTS_CODE,
				'name'       => __( 'All Clients', 'etwa' ),
				'department' => 'General',
			),
		);

		foreach ( $system as $row ) {
			if ( ! $this->find_by_code( $row['code'] ) ) {
				$this->create(
					array(
						'name'       => $row['name'],
						'code'       => $row['code'],
						'department' => $row['department'],
						'is_active'  => 1,
					)
				);
			}
		}
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function deactivate( int $id ): bool {
		$client = $this->find( $id );
		if ( ! $client || $this->is_system_client( $client ) ) {
			return false;
		}
		return $this->update( $id, array( 'is_active' => 0 ) );
	}

	public function entry_count( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE client_id = %d",
				$client_id
			)
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$client = $this->find( $id );
		if ( ! $client || $this->is_system_client( $client ) ) {
			return false;
		}

		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
