<?php
/**
 * Configurable wpdb double for Schema migration tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Support;

final class WpdbMigrationTestDouble {

	public string $prefix = 'wplh_';

	public string $options = 'wplh_options';

	public string $last_error = '';

	public int $insert_id = 0;

	/** @var list<string> */
	public array $tables = array();

	/** @var array<string, list<string>> */
	public array $columns = array();

	/** @var list<string> */
	public array $queries = array();

	/** @var list<mixed> */
	public array $get_results_responses = array();

	public function get_charset_collate(): string {
		return 'utf8mb4_unicode_ci';
	}

	/**
	 * @param mixed ...$args
	 */
	public function prepare( string $query, ...$args ): string {
		if ( ! $args ) {
			return $query;
		}

		$normalized = array();
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$normalized[] = (string) $arg;
				continue;
			}
			$normalized[] = "'" . addslashes( (string) $arg ) . "'";
		}

		return vsprintf(
			preg_replace( '/(%s|%d|%f)/', '%s', $query ),
			$normalized
		);
	}

	/**
	 * @param string $query
	 * @param mixed  $output
	 */
	public function get_var( $query, $output = OBJECT ): mixed {
		unset( $output );
		$this->queries[] = (string) $query;

		if ( str_contains( (string) $query, 'SHOW TABLES LIKE' ) ) {
			foreach ( $this->tables as $table ) {
				if ( str_contains( (string) $query, $table ) ) {
					return $table;
				}
			}
			return null;
		}

		if ( str_contains( (string) $query, 'INFORMATION_SCHEMA.COLUMNS' ) ) {
			foreach ( $this->columns as $table => $columns ) {
				foreach ( $columns as $column ) {
					if ( str_contains( (string) $query, $table ) && str_contains( (string) $query, $column ) ) {
						return '1';
					}
				}
			}
			return '0';
		}

		if ( str_contains( (string) $query, 'INFORMATION_SCHEMA.STATISTICS' ) ) {
			return '0';
		}

		return '0';
	}

	/**
	 * @param string $query
	 * @param mixed  $output
	 * @return array<int,object>|null
	 */
	public function get_results( $query, $output = OBJECT ): ?array {
		unset( $output );
		$this->queries[] = (string) $query;

		if ( $this->get_results_responses ) {
			return array_shift( $this->get_results_responses );
		}

		if ( str_contains( (string) $query, 'etwa_client_scopes' ) ) {
			return array();
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $where
	 * @param string[]|null       $format
	 * @param string[]|null       $where_format
	 */
	public function update( string $table, array $data, array $where, ?array $format = null, ?array $where_format = null ): int {
		unset( $format, $where_format );
		$this->queries[] = "UPDATE {$table} " . json_encode( $data ) . ' WHERE ' . json_encode( $where );
		return 1;
	}

	/**
	 * @param string $query
	 */
	public function query( $query ): int|false {
		$this->queries[] = (string) $query;
		return 1;
	}

	public function installGlobal(): void {
		global $wpdb;
		$wpdb = $this;
	}

	/** @return list<string> */
	public function queriesReferencing( string $needle ): array {
		return array_values(
			array_filter(
				$this->queries,
				static fn( string $query ): bool => str_contains( $query, $needle )
			)
		);
	}
}
