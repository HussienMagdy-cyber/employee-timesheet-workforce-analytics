<?php
/**
 * Prospect repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Prospect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectRepository {

	private string $table;
	private string $entries_table;

	public function __construct() {
		global $wpdb;
		$this->table         = $wpdb->prefix . 'etwa_prospects';
		$this->entries_table = $wpdb->prefix . 'etwa_entries';
	}

	public function find( int $id ): ?Prospect {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Prospect::from_row( $row ) : null;
	}

	/** @param int[] $ids */
	public function find_many( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id IN ({$placeholders}) ORDER BY name ASC",
				...$ids
			)
		);

		return array_map( fn( $row ) => Prospect::from_row( $row ), $rows ?: array() );
	}

	public function find_by_converted_client( int $client_id ): ?Prospect {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE converted_client_id = %d",
				$client_id
			)
		);
		return $row ? Prospect::from_row( $row ) : null;
	}

	/**
	 * @param int[] $client_ids
	 * @return array<int,Prospect> keyed by converted_client_id
	 */
	public function map_by_converted_clients( array $client_ids ): array {
		$client_ids = array_values( array_unique( array_filter( array_map( 'intval', $client_ids ) ) ) );
		if ( ! $client_ids ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN placeholders are %d tokens.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE converted_client_id IN ({$placeholders})",
				...$client_ids
			)
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$prospect = Prospect::from_row( $row );
			if ( $prospect->converted_client_id ) {
				$map[ $prospect->converted_client_id ] = $prospect;
			}
		}

		return $map;
	}

	/** @return Prospect[] */
	public function all_open_for_dropdown(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY name ASC",
				Prospect::STATUS_OPEN
			)
		);
		return array_map( fn( $row ) => Prospect::from_row( $row ), $rows ?: array() );
	}

	/** @return Prospect[] */
	public function all( ?string $status = null ): array {
		global $wpdb;

		if ( $status && in_array( $status, Prospect::statuses(), true ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC, name ASC",
					$status
				)
			);
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY created_at DESC, name ASC" );
		}

		return array_map( fn( $row ) => Prospect::from_row( $row ), $rows ?: array() );
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

	public function lock_for_update( int $id ): ?Prospect {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d FOR UPDATE", $id ) );
		return $row ? Prospect::from_row( $row ) : null;
	}

	public function mark_converted( int $id, int $client_id ): bool {
		global $wpdb;

		if ( $client_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				SET converted_client_id = %d, converted_at = %s
				WHERE id = %d AND (converted_client_id IS NULL OR converted_client_id = 0)",
				$client_id,
				current_time( 'mysql' ),
				$id
			)
		);

		return false !== $updated && (int) $updated > 0;
	}

	public function total_hours( int $prospect_id ): float {
		global $wpdb;

		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(regular_hours + overtime_hours), 0)
				FROM {$this->entries_table}
				WHERE prospect_id = %d",
				$prospect_id
			)
		);
	}

	public function entry_count( int $prospect_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->entries_table} WHERE prospect_id = %d",
				$prospect_id
			)
		);
	}
}
