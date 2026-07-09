<?php
/**
 * Holiday repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Models\Holiday;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HolidayRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_holidays';
	}

	public function find( int $id ): ?Holiday {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ) );
		return $row ? Holiday::from_row( $row ) : null;
	}

	/** @return Holiday[] */
	public function all( int $limit = 100, int $offset = 0, ?int $year = null ): array {
		global $wpdb;

		if ( $year ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table}
					WHERE is_recurring = 1 OR YEAR(holiday_date) = %d
					ORDER BY holiday_date ASC
					LIMIT %d OFFSET %d",
					$year,
					$limit,
					$offset
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} ORDER BY holiday_date ASC LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
		}

		return array_map( fn( $row ) => Holiday::from_row( $row ), $rows ?: array() );
	}

	public function count( ?int $year = null ): int {
		global $wpdb;

		if ( $year ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE is_recurring = 1 OR YEAR(holiday_date) = %d",
					$year
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * @return array<string,array{name:string,id:int}>
	 */
	public function map_for_date_range( string $date_from, string $date_to ): array {
		global $wpdb;

		$start = new \DateTimeImmutable( $date_from );
		$end   = new \DateTimeImmutable( $date_to );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, holiday_date, is_recurring FROM {$this->table}
				WHERE (holiday_date BETWEEN %s AND %s)
				OR is_recurring = 1",
				$date_from,
				$date_to
			)
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			if ( (int) $row->is_recurring ) {
				$md = substr( (string) $row->holiday_date, 5 );
				$cursor = $start;
				while ( $cursor <= $end ) {
					if ( $cursor->format( 'm-d' ) === $md ) {
						$key = $cursor->format( 'Y-m-d' );
						$map[ $key ] = array(
							'id'   => (int) $row->id,
							'name' => (string) $row->name,
						);
					}
					$cursor = $cursor->modify( '+1 day' );
				}
			} else {
				$key = (string) $row->holiday_date;
				if ( $key >= $date_from && $key <= $date_to ) {
					$map[ $key ] = array(
						'id'   => (int) $row->id,
						'name' => (string) $row->name,
					);
				}
			}
		}

		return $map;
	}

	/** @param array<string,mixed> $data */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert(
			$this->table,
			array(
				'name'          => $data['name'],
				'holiday_date'  => $data['holiday_date'],
				'is_recurring'  => (int) ( $data['is_recurring'] ?? 0 ),
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/** @param array<string,mixed> $data */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql' );
		return false !== $wpdb->update(
			$this->table,
			array(
				'name'         => $data['name'],
				'holiday_date' => $data['holiday_date'],
				'is_recurring' => (int) ( $data['is_recurring'] ?? 0 ),
				'updated_at'   => $data['updated_at'],
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}
}
