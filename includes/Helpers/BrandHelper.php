<?php
/**
 * Product branding (user-facing name).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BrandHelper {

	public const NAME = 'Think Marketing';

	public const SLUG = 'think-marketing';

	public static function name(): string {
		return __( 'Think Marketing', 'etwa' );
	}

	public static function role_label( string $role ): string {
		/* translators: %s: role name, e.g. Employee, HR Manager, Owner */
		return sprintf( __( '%1$s %2$s', 'etwa' ), self::name(), $role );
	}

	public static function workforce_display_label(): string {
		return __( 'WorkForce', 'etwa' );
	}

	/**
	 * Fallback letter when no WordPress site icon is set.
	 */
	public static function mark_letter(): string {
		return strtoupper( mb_substr( self::name(), 0, 1 ) );
	}

	/**
	 * Site favicon / Site Icon URL, or empty string if unset.
	 */
	public static function icon_url( int $size = 64 ): string {
		if ( ! has_site_icon() ) {
			return '';
		}

		$url = get_site_icon_url( $size );

		return is_string( $url ) ? $url : '';
	}
}
