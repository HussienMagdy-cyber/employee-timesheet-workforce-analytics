<?php
/**
 * EntryRepository unit tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Repositories;

use Brain\Monkey\Functions;
use ETWA\Repositories\EntryRepository;
use ETWA\Tests\TestCase;
use Mockery;

final class EntryRepositoryTest extends TestCase {

	public function test_create_returns_insert_id_on_success(): void {
		global $wpdb;

		$wpdb       = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wplh_';
		$wpdb->insert_id = 42;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wplh_etwa_entries',
				Mockery::on(
					static function ( array $row ): bool {
						return isset( $row['task'], $row['created_at'], $row['updated_at'] );
					}
				)
			)
			->andReturn( 1 );

		Functions\when( 'current_time' )->justReturn( '2024-01-08 10:00:00' );

		$repo = new EntryRepository();
		$id   = $repo->create(
			array(
				'timesheet_id'     => 1,
				'user_id'          => 100,
				'entry_date'       => '2024-01-08',
				'start_time'       => '09:00',
				'end_time'         => '10:00',
				'task'             => 'Work',
				'duration_minutes' => 60,
				'regular_hours'    => 1.0,
				'overtime_hours'   => 0.0,
				'entry_type'       => 'regular',
			)
		);

		$this->assertSame( 42, $id );
	}

	public function test_create_returns_zero_when_insert_fails(): void {
		global $wpdb;

		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wplh_';
		$wpdb->insert_id = 999;
		$wpdb->last_error = 'Duplicate entry for key PRIMARY';
		$wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		Functions\expect( 'error_log' )
			->once()
			->with( 'Duplicate entry for key PRIMARY' );

		Functions\when( 'current_time' )->justReturn( '2024-01-08 10:00:00' );

		$repo = new EntryRepository();
		$id   = $repo->create(
			array(
				'timesheet_id'     => 1,
				'user_id'          => 100,
				'entry_date'       => '2024-01-08',
				'start_time'       => '09:00',
				'end_time'         => '10:00',
				'task'             => 'Work',
				'duration_minutes' => 60,
				'regular_hours'    => 1.0,
				'overtime_hours'   => 0.0,
				'entry_type'       => 'regular',
			)
		);

		$this->assertSame( 0, $id );
	}
}
