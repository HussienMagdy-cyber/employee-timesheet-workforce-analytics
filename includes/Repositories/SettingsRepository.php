<?php
/**
 * Settings repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsRepository {

	private string $table;

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_settings';
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/** @param array<string,mixed> $settings */
	public function set_many( array $settings ): void {
		foreach ( $settings as $key => $value ) {
			$this->set( $key, $value, false );
		}
		self::$cache = null;
	}

	public function set( string $key, mixed $value, bool $flush_cache = true ): void {
		global $wpdb;
		$encoded = maybe_serialize( $value );
		$exists  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table} WHERE setting_key = %s", $key ) );

		if ( $exists ) {
			$wpdb->update( $this->table, array( 'setting_value' => $encoded ), array( 'setting_key' => $key ) );
		} else {
			$wpdb->insert(
				$this->table,
				array(
					'setting_key'   => $key,
					'setting_value' => $encoded,
					'autoload'      => 1,
				)
			);
		}
		if ( $flush_cache ) {
			self::$cache = null;
		}
	}

	/** @return array<string,mixed> */
	public function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$this->table} WHERE autoload = 1" );
		$settings = array();
		foreach ( $rows ?: array() as $row ) {
			$settings[ $row->setting_key ] = maybe_unserialize( $row->setting_value );
		}
		self::$cache = $settings;
		return $settings;
	}
}
