<?php
/**
 * WordPress debug.log reader for local troubleshooting.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpDebugLogReader {

	public static function is_logging_enabled(): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			return true;
		}

		return self::log_exists();
	}

	/** @return string[] */
	public static function candidate_paths(): array {
		$paths = array(
			WP_CONTENT_DIR . '/debug.log',
		);

		$ini_log = ini_get( 'error_log' );
		if ( is_string( $ini_log ) && '' !== $ini_log && 'syslog' !== $ini_log ) {
			$paths[] = $ini_log;
		}

		$local_log = dirname( dirname( ABSPATH ) ) . '/logs/php/error.log';
		if ( is_readable( $local_log ) ) {
			$paths[] = $local_log;
		}

		return array_values( array_unique( $paths ) );
	}

	public static function resolve_log_path(): string {
		foreach ( self::candidate_paths() as $path ) {
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	public static function log_path(): string {
		$resolved = self::resolve_log_path();
		if ( $resolved ) {
			return $resolved;
		}

		return WP_CONTENT_DIR . '/debug.log';
	}

	public static function log_exists(): bool {
		$path = self::resolve_log_path();
		return '' !== $path && is_readable( $path );
	}

	/** @return array{enabled:bool,exists:bool,path:string,lines:string[],size:int,modified:string} */
	public static function snapshot( int $max_lines = 150 ): array {
		$path = self::log_path();
		$exists = self::log_exists();

		return array(
			'enabled'  => self::is_logging_enabled(),
			'exists'   => $exists,
			'path'     => $path,
			'lines'    => self::tail( $max_lines ),
			'size'     => $exists ? (int) filesize( $path ) : 0,
			'modified' => $exists ? gmdate( 'Y-m-d H:i:s', (int) filemtime( $path ) ) . ' UTC' : '',
		);
	}

	/** @return string[] */
	public static function tail( int $max_lines = 150 ): array {
		if ( ! self::log_exists() ) {
			return array();
		}

		$path  = self::resolve_log_path();
		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		$lines = array_values(
			array_filter(
				$lines,
				static fn( $line ) => '' !== trim( (string) $line )
			)
		);

		if ( count( $lines ) <= $max_lines ) {
			return $lines;
		}

		return array_slice( $lines, -$max_lines );
	}

	public static function clear(): bool {
		$path = self::resolve_log_path();
		if ( ! $path ) {
			return true;
		}

		return false !== file_put_contents( $path, '' );
	}

	public static function clear_url( string $base_url ): string {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg( 'etwa_clear_debug_log', '1', $base_url ),
			'etwa_clear_debug_log'
		);
	}

	public static function register_handlers(): void {
		add_action( 'admin_init', array( self::class, 'maybe_handle_admin_clear_request' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_handle_frontend_clear_request' ) );
	}

	public static function maybe_handle_admin_clear_request(): void {
		if ( empty( $_GET['page'] ) || 'etwa-logs' !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) {
			return;
		}

		self::maybe_handle_clear_request(
			remove_query_arg( array( 'etwa_clear_debug_log', '_wpnonce' ) )
		);
	}

	public static function maybe_handle_frontend_clear_request(): void {
		if ( empty( $_GET['etwa_clear_debug_log'] ) ) {
			return;
		}

		$view = isset( $_GET['etwa_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) ) : '';
		if ( 'logs' !== $view ) {
			return;
		}

		self::maybe_handle_clear_request(
			remove_query_arg( array( 'etwa_clear_debug_log', '_wpnonce' ) )
		);
	}

	public static function maybe_handle_clear_request( string $redirect_url ): void {
		if ( empty( $_GET['etwa_clear_debug_log'] ) ) {
			return;
		}

		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return;
		}

		check_admin_referer( 'etwa_clear_debug_log' );
		self::clear();

		wp_safe_redirect( remove_query_arg( array( 'etwa_clear_debug_log', '_wpnonce' ), $redirect_url ) );
		exit;
	}
}
