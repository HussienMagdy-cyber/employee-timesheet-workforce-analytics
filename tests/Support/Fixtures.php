<?php
/**
 * Test data factories.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Support;

use ETWA\Models\Client;
use ETWA\Models\Entry;
use ETWA\Models\Timesheet;

final class Fixtures {

	public static function timesheet( int $id = 1, int $user_id = 100 ): Timesheet {
		return new Timesheet(
			$id,
			$user_id,
			'2024-01-07',
			'2024-01-11',
			'draft',
			0.0,
			0.0,
			null,
			null,
			null,
			null,
			false,
			'2024-01-07 08:00:00',
			'2024-01-08 10:00:00'
		);
	}

	public static function client( int $id = 10 ): Client {
		return new Client(
			$id,
			'Acme Corp',
			'ACME',
			'General',
			true,
			Client::TYPE_STANDARD,
			'2024-01-01 00:00:00'
		);
	}

	public static function entry( int $id = 5, int $timesheet_id = 1, int $user_id = 100 ): Entry {
		return new Entry(
			$id,
			$timesheet_id,
			$user_id,
			'2024-01-08',
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
			'2024-01-08 09:00:00',
			'2024-01-08 10:00:00'
		);
	}

	/** @return array<string,mixed> */
	public static function save_entry_payload( int $timesheet_id = 1 ): array {
		return array(
			'timesheet_id' => $timesheet_id,
			'entry_date'   => '2024-01-08',
			'start_time'   => '09:00',
			'end_time'     => '10:00',
			'client_id'    => 10,
			'task'         => 'Development',
			'notes'        => '',
		);
	}

	/** @return array<string,mixed> */
	public static function holiday_entry_payload( int $timesheet_id = 1 ): array {
		return array(
			'timesheet_id' => $timesheet_id,
			'entry_date'   => '2024-01-08',
			'start_time'   => '09:00',
			'end_time'     => '10:00',
			'client_id'    => 10,
			'task'         => 'Holiday work',
			'notes'        => '',
		);
	}
}
