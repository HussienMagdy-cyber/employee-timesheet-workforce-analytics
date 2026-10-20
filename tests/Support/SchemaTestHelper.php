<?php
/**
 * Reflection helpers for Schema private migration methods.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Support;

use ETWA\Database\Schema;
use ReflectionMethod;

final class SchemaTestHelper {

	public static function invokeUpgrade( string $method ): void {
		$ref = new ReflectionMethod( Schema::class, $method );
		$ref->setAccessible( true );
		$ref->invoke( null );
	}
}
