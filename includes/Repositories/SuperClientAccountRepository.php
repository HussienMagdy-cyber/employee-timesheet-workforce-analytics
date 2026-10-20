<?php
/**
 * Super Client Brand repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\SuperClientAccount;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientAccountRepository {

	private string $table;
	private string $requests_table;

	public function __construct() {
		global $wpdb;
		$this->table          = $wpdb->prefix . 'etwa_super_clients';
		$this->requests_table = $wpdb->prefix . 'etwa_super_client_requests';
	}

	public function find( int $id ): ?SuperClientAccount {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? SuperClientAccount::from_row( $row ) : null;
	}

	/** @return SuperClientAccount[] */
	public function list_by_super_client( int $super_client_id, bool $active_only = false ): array {
		global $wpdb;

		if ( $active_only ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE super_client_id = %d AND is_active = 1 ORDER BY name ASC",
					$super_client_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE super_client_id = %d ORDER BY name ASC",
					$super_client_id
				)
			);
		}

		return array_map( fn( $row ) => SuperClientAccount::from_row( $row ), $rows ?: array() );
	}

	public function count_by_super_client( int $super_client_id, bool $active_only = false ): int {
		global $wpdb;

		if ( $active_only ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE super_client_id = %d AND is_active = 1",
					$super_client_id
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE super_client_id = %d",
				$super_client_id
			)
		);
	}

	public function request_count( int $brand_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->requests_table} WHERE brand_id = %d",
				$brand_id
			)
		);
	}

	/**
	 * @return array<string,int>
	 */
	public function count_by_agency( int $super_client_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT agency AS grp, COUNT(*) AS cnt FROM {$this->table} WHERE super_client_id = %d GROUP BY agency",
				$super_client_id
			)
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$map[ (string) $row->grp ] = (int) $row->cnt;
		}
		return $map;
	}

	/**
	 * @return array<string,int>
	 */
	public function count_by_industry( int $super_client_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT industry AS grp, COUNT(*) AS cnt FROM {$this->table} WHERE super_client_id = %d AND industry != '' GROUP BY industry",
				$super_client_id
			)
		);

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
		$wpdb->insert( $this->table, $data );
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update( $this->table, $data, array( 'id' => $id ) );
	}

	public function deactivate( int $id ): bool {
		return $this->update( $id, array( 'is_active' => 0 ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
