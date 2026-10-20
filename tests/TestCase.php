<?php
/**
 * Base test case.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests;

use Brain\Monkey;
use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\when( '__' )->returnArg( 1 );
		Monkey\Functions\when( 'esc_html__' )->returnArg( 1 );
		Monkey\Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? trim( $value ) : '';
			}
		);
		Monkey\Functions\when( 'sanitize_textarea_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? trim( $value ) : '';
			}
		);
		Monkey\Functions\when( 'sanitize_key' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ) : '';
			}
		);
		Monkey\Functions\when( 'current_time' )->justReturn( '2024-01-08 10:00:00' );
		Monkey\Functions\when( 'get_option' )->justReturn( '' );
		Monkey\Functions\when( 'user_can' )->justReturn( true );
		Monkey\Functions\when( 'get_user_meta' )->justReturn( 'active' );
		Monkey\Functions\when( 'do_action' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * @return array{code:string,status:int}
	 */
	protected function assertSaveFailed( mixed $result ): array {
		$this->assertInstanceOf( \WP_Error::class, $result );
		/** @var \WP_Error $result */
		$this->assertSame( 'save_failed', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertIsArray( $data );
		$this->assertSame( 500, $data['status'] ?? null );

		return array(
			'code'   => $result->get_error_code(),
			'status' => (int) $data['status'],
		);
	}
}
