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
	public const GENERAL_CODE     = 'GENERAL';

	/** @return string[] Reserved system client codes. */
	public static function system_codes(): array {
		return array(
			self::BREAK_CODE,
			self::ALL_CLIENTS_CODE,
			self::MEETING_CODE,
			self::EVENT_CODE,
			self::GENERAL_CODE,
		);
	}

	/** @return string[] Internal work category codes (zero-hour break + billable internal). */
	public static function internal_codes(): array {
		return array(
			self::BREAK_CODE,
			self::MEETING_CODE,
		);
	}

	/** @return string[] General category system client codes. */
	public static function general_codes(): array {
		return array(
			self::GENERAL_CODE,
			self::ALL_CLIENTS_CODE,
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

	public function is_general_client( int $client_id, ?Client $client = null ): bool {
		$client = $client ?? $this->find( $client_id );
		return $client && in_array( strtoupper( $client->code ), self::general_codes(), true );
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
			self::GENERAL_CODE,
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

	/** @return Client[] */
	public function list_super_clients( bool $active_only = false ): array {
		global $wpdb;

		if ( $active_only ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE client_type = %s AND is_active = 1 ORDER BY name ASC",
					Client::TYPE_SUPER
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE client_type = %s ORDER BY name ASC",
					Client::TYPE_SUPER
				)
			);
		}

		return array_map( fn( $row ) => Client::from_row( $row ), $rows ?: array() );
	}

	public function set_client_type( int $id, string $type ): bool {
		if ( ! in_array( $type, Client::client_types(), true ) ) {
			return false;
		}
		return $this->update( $id, array( 'client_type' => $type ) );
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
				'code'       => self::GENERAL_CODE,
				'name'       => __( 'General', 'etwa' ),
				'department' => 'General',
			),
			array(
				'code'       => self::ALL_CLIENTS_CODE,
				'name'       => __( 'Moderation', 'etwa' ),
				'department' => 'General',
			),
		);

		foreach ( $system as $row ) {
			$existing = $this->find_by_code( $row['code'] );
			if ( ! $existing ) {
				$this->create(
					array(
						'name'       => $row['name'],
						'code'       => $row['code'],
						'department' => $row['department'],
						'is_active'  => self::EVENT_CODE === strtoupper( $row['code'] ) ? 0 : 1,
					)
				);
				continue;
			}

			if ( self::ALL_CLIENTS_CODE === strtoupper( $existing->code ) && $existing->name !== $row['name'] ) {
				$this->update( (int) $existing->id, array( 'name' => $row['name'] ) );
			}
		}
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$data['created_at'] = current_time( 'mysql' );
		$inserted           = $wpdb->insert( $this->table, $data );
		if ( false === $inserted ) {
			return 0;
		}
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

	public function reassign_client( int $from_client_id, int $to_client_id ): int {
		global $wpdb;
		$entries = $wpdb->prefix . 'etwa_entries';
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$entries} SET client_id = %d, updated_at = %s WHERE client_id = %d",
				$to_client_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function scope_count( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_client_scopes';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE client_id = %d",
				$client_id
			)
		);
	}

	public function reassign_scopes( int $from_client_id, int $to_client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_client_scopes';
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET client_id = %d, updated_at = %s WHERE client_id = %d",
				$to_client_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function entry_count_direct( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)",
				$client_id
			)
		);
	}

	public function scope_count_direct( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_client_scopes';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)",
				$client_id
			)
		);
	}

	public function allocation_count_for_client( int $client_id ): int {
		global $wpdb;
		$scopes = $wpdb->prefix . 'etwa_client_scopes';
		$allocs = $wpdb->prefix . 'etwa_deliverable_allocations';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$allocs} a
				INNER JOIN {$scopes} s ON s.id = a.scope_id
				WHERE s.client_id = %d",
				$client_id
			)
		);
	}

	public function convert_reassign_entries_direct( int $from_client_id, int $to_client_id, int $project_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		if ( $to_client_id <= 0 ) {
			return (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET client_id = NULL, project_id = %d, updated_at = %s
					WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)",
					$project_id,
					current_time( 'mysql' ),
					$from_client_id
				)
			);
		}

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET client_id = %d, project_id = %d, updated_at = %s
				WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)",
				$to_client_id,
				$project_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function convert_reassign_entries_with_project( int $from_client_id, int $to_client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_entries';

		if ( $to_client_id <= 0 ) {
			return (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					SET client_id = NULL, updated_at = %s
					WHERE client_id = %d AND project_id IS NOT NULL AND project_id != 0",
					current_time( 'mysql' ),
					$from_client_id
				)
			);
		}

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET client_id = %d, updated_at = %s
				WHERE client_id = %d AND project_id IS NOT NULL AND project_id != 0",
				$to_client_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function convert_reassign_scopes_direct( int $from_client_id, int $to_client_id, int $project_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_client_scopes';

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET client_id = %d, project_id = %d, updated_at = %s
				WHERE client_id = %d AND (project_id IS NULL OR project_id = 0)",
				$to_client_id,
				$project_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function convert_reassign_scopes_with_project( int $from_client_id, int $to_client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_client_scopes';

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET client_id = %d, updated_at = %s
				WHERE client_id = %d AND project_id IS NOT NULL AND project_id != 0",
				$to_client_id,
				current_time( 'mysql' ),
				$from_client_id
			)
		);
	}

	public function request_count_referencing_client( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_requests';
		$like1 = '%"client_id":' . $client_id . ',%';
		$like2 = '%"client_id":' . $client_id . '}%';
		$like3 = '%"client_id": ' . $client_id . ',%';
		$like4 = '%"client_id": ' . $client_id . '}%';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE extra_data LIKE %s OR extra_data LIKE %s OR extra_data LIKE %s OR extra_data LIKE %s",
				$like1,
				$like2,
				$like3,
				$like4
			)
		);
	}

	public function draft_snapshot_count_referencing_client( int $client_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'etwa_timesheets';
		$like1 = '%"client_id":' . $client_id . ',%';
		$like2 = '%"client_id":' . $client_id . '}%';
		$like3 = '%"client_id": ' . $client_id . ',%';
		$like4 = '%"client_id": ' . $client_id . '}%';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE draft_snapshot IS NOT NULL AND draft_snapshot != ''
				AND (draft_snapshot LIKE %s OR draft_snapshot LIKE %s OR draft_snapshot LIKE %s OR draft_snapshot LIKE %s)",
				$like1,
				$like2,
				$like3,
				$like4
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
