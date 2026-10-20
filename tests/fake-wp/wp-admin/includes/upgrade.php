<?php
/**
 * Minimal upgrade.php stub for Schema migration unit tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * @param string|string[] $queries
	 * @return string[]
	 */
	function dbDelta( $queries ): array {
		return array();
	}
}
