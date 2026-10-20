<?php
/**
 * TimesheetService null-guard and save_failed unit tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Services;

use ETWA\Helpers\TimesheetDraftHelper;
use ETWA\Models\Entry;
use ETWA\Models\Timesheet;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\OvertimeRepository;
use ETWA\Repositories\ProspectRepository;
use ETWA\Repositories\TimesheetRepository;
use ETWA\Services\TimesheetService;
use ETWA\Tests\Support\Fixtures;
use ETWA\Tests\Support\WpdbMigrationTestDouble;
use ETWA\Tests\TestCase;

final class TimesheetServiceTest extends TestCase {

	private WpdbMigrationTestDouble $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb = new WpdbMigrationTestDouble();
		$this->wpdb->installGlobal();
	}

	// --- save_entry ---

	public function test_save_entry_returns_wp_error_when_create_returns_zero(): void {
		$service = $this->serviceWithEntryMocks(
			create_return: 0,
			final_entry: null
		);
		$service['entries']->expects( $this->never() )->method( 'sum_hours_by_timesheet' );
		$service['overtime']->expects( $this->never() )->method( 'delete_by_entry' );

		$result = $service['service']->save_entry( 100, Fixtures::save_entry_payload(), null );

		$this->assertSaveFailed( $result );
	}

	public function test_save_entry_returns_wp_error_when_final_find_returns_null(): void {
		$service = $this->serviceWithEntryMocks(
			create_return: 5,
			final_entry: null
		);

		$result = $service['service']->save_entry( 100, Fixtures::save_entry_payload(), null );

		$this->assertSaveFailed( $result );
	}

	public function test_save_entry_returns_entry_on_happy_path(): void {
		$entry   = Fixtures::entry( 5 );
		$service = $this->serviceWithEntryMocks(
			create_return: 5,
			final_entry: $entry
		);

		$result = $service['service']->save_entry( 100, Fixtures::save_entry_payload(), null );

		$this->assertInstanceOf( Entry::class, $result );
		$this->assertSame( 5, $result->id );
	}

	// --- create_from_holiday_request ---

	public function test_create_from_holiday_request_returns_wp_error_when_create_returns_zero(): void {
		$service = $this->serviceWithHolidayMocks(
			create_return: 0,
			final_entry: null
		);
		$service['entries']->expects( $this->never() )->method( 'sum_hours_by_timesheet' );

		$result = $service['service']->create_from_holiday_request( 100, Fixtures::holiday_entry_payload() );

		$this->assertSaveFailed( $result );
	}

	public function test_create_from_holiday_request_returns_wp_error_when_final_find_returns_null(): void {
		$service = $this->serviceWithHolidayMocks(
			create_return: 5,
			final_entry: null
		);

		$result = $service['service']->create_from_holiday_request( 100, Fixtures::holiday_entry_payload() );

		$this->assertSaveFailed( $result );
	}

	public function test_create_from_holiday_request_returns_entry_on_happy_path(): void {
		$entry   = Fixtures::entry( 5 );
		$service = $this->serviceWithHolidayMocks(
			create_return: 5,
			final_entry: $entry
		);

		$result = $service['service']->create_from_holiday_request( 100, Fixtures::holiday_entry_payload() );

		$this->assertInstanceOf( Entry::class, $result );
		$this->assertSame( 5, $result->id );
	}

	// --- save_draft ---

	public function test_save_draft_returns_wp_error_when_final_find_returns_null(): void {
		$timesheet = Fixtures::timesheet();
		$entries   = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )->willReturn( array( Fixtures::entry() ) );

		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find' )->willReturnOnConsecutiveCalls( $timesheet, null );
		$timesheets->expects( $this->once() )->method( 'update' );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$this->createMock( ClientRepository::class ),
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->save_draft( 100, 1 );

		$this->assertSaveFailed( $result );
	}

	public function test_save_draft_returns_timesheet_on_happy_path(): void {
		$timesheet = Fixtures::timesheet();
		$entries   = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )->willReturn( array( Fixtures::entry() ) );

		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find' )->willReturn( $timesheet );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$this->createMock( ClientRepository::class ),
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->save_draft( 100, 1 );

		$this->assertInstanceOf( Timesheet::class, $result );
		$this->assertSame( 1, $result->id );
	}

	// --- submit ---

	public function test_submit_returns_wp_error_when_final_find_returns_null(): void {
		$timesheet = Fixtures::timesheet();
		$entry     = Fixtures::entry();
		$snapshot = TimesheetDraftHelper::encode_snapshot( array( $entry ) );

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( Fixtures::client() );
		$clients->method( 'is_break_client' )->willReturn( false );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )->willReturn( array( $entry ) );
		$entries->method( 'delete_by_timesheet' );
		$entries->method( 'create_many' )->willReturn( array( 5 ) );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$timesheets = $this->createMock( TimesheetRepository::class );
		$draft_saved = new Timesheet(
			$timesheet->id,
			$timesheet->user_id,
			$timesheet->period_start,
			$timesheet->period_end,
			$timesheet->status,
			$timesheet->total_regular_hours,
			$timesheet->total_overtime_hours,
			$timesheet->submitted_at,
			$timesheet->reviewed_by,
			$timesheet->reviewed_at,
			$timesheet->review_notes,
			$timesheet->is_locked,
			$timesheet->created_at,
			$timesheet->updated_at,
			'2024-01-08 10:00:00',
			$snapshot
		);

		$timesheets->method( 'find' )->willReturnOnConsecutiveCalls(
			$timesheet,
			$timesheet,
			$draft_saved,
			null
		);
		$timesheets->method( 'update' );

		$overtime = $this->createMock( OvertimeRepository::class );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$overtime,
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->submit( 100, 1 );

		$this->assertSaveFailed( $result );
	}

	public function test_submit_returns_timesheet_on_happy_path(): void {
		$timesheet = Fixtures::timesheet();
		$entry     = Fixtures::entry();
		$snapshot  = TimesheetDraftHelper::encode_snapshot( array( $entry ) );
		$updated   = new Timesheet(
			$timesheet->id,
			$timesheet->user_id,
			$timesheet->period_start,
			$timesheet->period_end,
			'submitted',
			1.0,
			0.0,
			'2024-01-08 10:00:00',
			null,
			null,
			null,
			false,
			$timesheet->created_at,
			$timesheet->updated_at,
			'2024-01-08 10:00:00',
			$snapshot
		);

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( Fixtures::client() );
		$clients->method( 'is_break_client' )->willReturn( false );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )->willReturn( array( $entry ) );
		$entries->method( 'delete_by_timesheet' );
		$entries->method( 'create_many' )->willReturn( array( 5 ) );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$timesheets = $this->createMock( TimesheetRepository::class );
		$draft_saved = new Timesheet(
			$timesheet->id,
			$timesheet->user_id,
			$timesheet->period_start,
			$timesheet->period_end,
			$timesheet->status,
			$timesheet->total_regular_hours,
			$timesheet->total_overtime_hours,
			$timesheet->submitted_at,
			$timesheet->reviewed_by,
			$timesheet->reviewed_at,
			$timesheet->review_notes,
			$timesheet->is_locked,
			$timesheet->created_at,
			$timesheet->updated_at,
			'2024-01-08 10:00:00',
			$snapshot
		);

		$timesheets->method( 'find' )->willReturnOnConsecutiveCalls(
			$timesheet,
			$timesheet,
			$draft_saved,
			$updated
		);
		$timesheets->method( 'update' );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->submit( 100, 1 );

		$this->assertInstanceOf( Timesheet::class, $result );
		$this->assertSame( 'submitted', $result->status );
	}

	// --- copy_previous_week ---

	public function test_copy_previous_week_returns_wp_error_when_final_find_returns_null(): void {
		$prev = new Timesheet(
			2,
			100,
			'2023-12-31',
			'2024-01-04',
			'draft',
			0.0,
			0.0,
			null,
			null,
			null,
			null,
			false,
			'2023-12-31 08:00:00',
			'2024-01-04 17:00:00'
		);
		$current = Fixtures::timesheet( 3, 100 );
		$entry   = new Entry(
			10,
			2,
			100,
			'2024-01-02',
			'09:00',
			'10:00',
			10,
			null,
			null,
			'Task',
			'',
			60,
			1.0,
			0.0,
			'regular',
			null,
			'2024-01-02 09:00:00',
			'2024-01-02 10:00:00'
		);
		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find_by_user_period' )
			->willReturnCallback(
				static function ( int $user_id, string $start, string $end ) use ( $prev, $current ): ?Timesheet {
					unset( $user_id );
					if ( '2024-01-07' === $start && '2024-01-11' === $end ) {
						return $current;
					}
					if ( '2023-12-31' === $start && '2024-01-04' === $end ) {
						return $prev;
					}
					return null;
				}
			);
		$timesheets->method( 'find' )->willReturn( null );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )
			->willReturnCallback(
				static function ( int $timesheet_id ) use ( $entry ): array {
					return 2 === $timesheet_id ? array( $entry ) : array();
				}
			);
		$entries->method( 'create_many' )->willReturn( array( 11 ) );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( Fixtures::client() );
		$clients->method( 'is_break_client' )->willReturn( false );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->copy_previous_week( 100, '2024-01-08' );

		$this->assertSaveFailed( $result );
	}

	public function test_copy_previous_week_returns_timesheet_on_happy_path(): void {
		$prev = new Timesheet(
			2,
			100,
			'2023-12-31',
			'2024-01-04',
			'draft',
			0.0,
			0.0,
			null,
			null,
			null,
			null,
			false,
			'2023-12-31 08:00:00',
			'2024-01-04 17:00:00'
		);
		$current = Fixtures::timesheet( 3, 100 );
		$entry   = new Entry(
			10,
			2,
			100,
			'2024-01-02',
			'09:00',
			'10:00',
			10,
			null,
			null,
			'Task',
			'',
			60,
			1.0,
			0.0,
			'regular',
			null,
			'2024-01-02 09:00:00',
			'2024-01-02 10:00:00'
		);

		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find_by_user_period' )
			->willReturnCallback(
				static function ( int $user_id, string $start, string $end ) use ( $prev, $current ): ?Timesheet {
					unset( $user_id );
					if ( '2024-01-07' === $start && '2024-01-11' === $end ) {
						return $current;
					}
					if ( '2023-12-31' === $start && '2024-01-04' === $end ) {
						return $prev;
					}
					return null;
				}
			);
		$timesheets->method( 'find' )->willReturn( $current );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'find_by_timesheet' )
			->willReturnCallback(
				static function ( int $timesheet_id ) use ( $entry ): array {
					return 2 === $timesheet_id ? array( $entry ) : array();
				}
			);
		$entries->method( 'create_many' )->willReturn( array( 11 ) );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( Fixtures::client() );
		$clients->method( 'is_break_client' )->willReturn( false );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		$result = $service->copy_previous_week( 100, '2024-01-08' );

		$this->assertInstanceOf( Timesheet::class, $result );
		$this->assertSame( 3, $result->id );
	}

	/**
	 * @return array{
	 *   service: TimesheetService,
	 *   entries: EntryRepository&\PHPUnit\Framework\MockObject\MockObject,
	 *   overtime: OvertimeRepository&\PHPUnit\Framework\MockObject\MockObject
	 * }
	 */
	private function serviceWithEntryMocks( int $create_return, ?Entry $final_entry ): array {
		$timesheet = Fixtures::timesheet();
		$client    = Fixtures::client();

		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find' )->willReturn( $timesheet );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'create' )->willReturn( $create_return );
		$entries->method( 'has_overlap' )->willReturn( false );
		$entries->method( 'find' )->willReturn( $final_entry );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( $client );
		$clients->method( 'is_break_client' )->willReturn( false );
		$clients->method( 'is_all_clients_client' )->willReturn( false );

		$overtime = $this->createMock( OvertimeRepository::class );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$overtime,
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		return array(
			'service'  => $service,
			'entries'  => $entries,
			'overtime' => $overtime,
		);
	}

	/**
	 * @return array{
	 *   service: TimesheetService,
	 *   entries: EntryRepository&\PHPUnit\Framework\MockObject\MockObject
	 * }
	 */
	private function serviceWithHolidayMocks( int $create_return, ?Entry $final_entry ): array {
		$timesheet = Fixtures::timesheet();
		$client    = Fixtures::client();

		$timesheets = $this->createMock( TimesheetRepository::class );
		$timesheets->method( 'find' )->willReturn( $timesheet );

		$entries = $this->createMock( EntryRepository::class );
		$entries->method( 'create' )->willReturn( $create_return );
		$entries->method( 'has_overlap' )->willReturn( false );
		$entries->method( 'find' )->willReturn( $final_entry );
		$entries->method( 'sum_hours_by_timesheet' )->willReturn(
			array(
				'regular'  => 1.0,
				'overtime' => 0.0,
			)
		);

		$clients = $this->createMock( ClientRepository::class );
		$clients->method( 'find' )->willReturn( $client );
		$clients->method( 'is_break_client' )->willReturn( false );

		$service = new TimesheetService(
			$timesheets,
			$entries,
			$this->createMock( OvertimeRepository::class ),
			$this->createMock( AuditLogRepository::class ),
			$clients,
			$this->createMock( ProspectRepository::class )
		);

		return array(
			'service' => $service,
			'entries' => $entries,
		);
	}
}
