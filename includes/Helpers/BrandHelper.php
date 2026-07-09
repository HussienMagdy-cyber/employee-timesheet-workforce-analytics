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
}
