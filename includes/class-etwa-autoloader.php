<?php
/**
 * PSR-4 style autoloader for ETWA namespace.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	/** @var array<string, string> */
	private static array $prefix_map = array(
		'ETWA\\Database\\'     => 'includes/Database/',
		'ETWA\\Helpers\\'      => 'includes/Helpers/',
		'ETWA\\Models\\'        => 'includes/Models/',
		'ETWA\\Repositories\\' => 'includes/Repositories/',
		'ETWA\\Services\\'     => 'includes/Services/',
		'ETWA\\REST\\'          => 'includes/REST/',
		'ETWA\\Admin\\'         => 'includes/Admin/',
		'ETWA\\Cron\\'          => 'includes/Cron/',
		'ETWA\\Frontend\\'      => 'includes/Frontend/',
		'ETWA\\Assets\\'       => 'includes/Assets/',
	);

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	public static function load( string $class ): void {
		foreach ( self::$prefix_map as $prefix => $dir ) {
			if ( str_starts_with( $class, $prefix ) ) {
				$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
				$file     = ETWA_PLUGIN_DIR . $dir . $relative . '.php';
				if ( file_exists( $file ) ) {
					require_once $file;
				}
				return;
			}
		}

		if ( str_starts_with( $class, 'ETWA\\' ) ) {
			$relative = str_replace( '\\', '/', substr( $class, 5 ) );
			$file     = ETWA_PLUGIN_DIR . 'includes/class-etwa-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}
}
