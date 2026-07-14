<?php
/**
 * One-time correction for timesheet entry times saved under mislabeled dropdown values.
 *
 * Before DateHelper::format_time() was fixed, slot labels were shifted by the site
 * timezone offset (e.g. value 07:00 displayed as 10:00 AM when the server ran UTC
 * and WordPress was set to UTC+3). This migration adds that offset back to stored
 * start/end times on existing timesheet entries only.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Database;

use ETWA\Helpers\TimeCalculator;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\TimesheetRepository;
use ETWA\Services\AnalyticsService;
use ETWA\Services\TimesheetService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntryTimeOffsetMigration {

	private const OPTION_KEY = 'etwa_timesheet_entry_time_offset_fix_v1';

	public static function maybe_run(): void {
		if ( get_option( self::OPTION_KEY ) ) {
			return;
		}

		$timezone       = wp_timezone();
		$now            = new \DateTimeImmutable( 'now', $timezone );
		$offset_minutes = (int) ( $timezone->getOffset( $now ) / 60 );

		if ( 0 === $offset_minutes ) {
			update_option(
				self::OPTION_KEY,
				array(
					'applied_at'       => current_time( 'mysql' ),
					'offset_minutes'   => 0,
					'entries_updated'  => 0,
					'snapshots_updated'=> 0,
					'note'             => 'skipped_zero_offset',
				),
				false
			);
			return;
		}

		$cutoff              = current_time( 'mysql' );
		$entries_updated     = self::migrate_entries( $offset_minutes, $cutoff );
		$snapshots_updated   = self::migrate_draft_snapshots( $offset_minutes );
		$timesheets_updated  = self::recalculate_timesheet_totals();

		update_option(
			self::OPTION_KEY,
			array(
				'applied_at'          => current_time( 'mysql' ),
				'offset_minutes'      => $offset_minutes,
				'entries_updated'     => $entries_updated,
				'snapshots_updated'   => $snapshots_updated,
				'timesheets_updated'  => $timesheets_updated,
			),
			false
		);

		AnalyticsService::flush_dashboard_cache();
	}

	private static function migrate_entries( int $offset_minutes, string $cutoff ): int {
		global $wpdb;

		$table         = $wpdb->prefix . 'etwa_entries';
		$calculator    = new TimeCalculator();
		$holiday_from  = TimesheetService::CREATED_FROM_HOLIDAY_REQUEST;
		$updated       = 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, entry_date, start_time, end_time
				FROM {$table}
				WHERE created_at < %s
				AND ( created_from IS NULL OR created_from = '' OR created_from <> %s )",
				$cutoff,
				$holiday_from
			)
		);

		if ( ! $rows ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$entry_date = (string) $row->entry_date;
			$new_start  = self::shift_wall_time( (string) $row->start_time, $offset_minutes );
			$new_end    = self::shift_wall_time( (string) $row->end_time, $offset_minutes );
			$calc       = $calculator->calculate(
				$entry_date,
				substr( $new_start, 0, 5 ),
				substr( $new_end, 0, 5 )
			);

			$result = $wpdb->update(
				$table,
				array(
					'start_time'       => $new_start,
					'end_time'         => $new_end,
					'duration_minutes' => $calc['duration_minutes'],
					'regular_hours'    => $calc['regular_hours'],
					'overtime_hours'   => $calc['overtime_hours'],
					'updated_at'       => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%d', '%f', '%f', '%s' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				++$updated;
			}
		}

		return $updated;
	}

	private static function migrate_draft_snapshots( int $offset_minutes ): int {
		global $wpdb;

		$table     = $wpdb->prefix . 'etwa_timesheets';
		$calculator = new TimeCalculator();
		$updated   = 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$rows = $wpdb->get_results(
			"SELECT id, draft_snapshot FROM {$table}
			WHERE draft_snapshot IS NOT NULL AND draft_snapshot <> ''"
		);

		if ( ! $rows ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$decoded = json_decode( (string) $row->draft_snapshot, true );
			if ( ! is_array( $decoded ) || ! $decoded ) {
				continue;
			}

			$changed = false;
			foreach ( $decoded as $index => $entry_row ) {
				if ( ! is_array( $entry_row ) ) {
					continue;
				}

				$holiday_from = TimesheetService::CREATED_FROM_HOLIDAY_REQUEST;
				$created_from = (string) ( $entry_row['created_from'] ?? '' );
				if ( $created_from === $holiday_from ) {
					continue;
				}

				$entry_date = (string) ( $entry_row['entry_date'] ?? '' );
				$new_start  = self::shift_wall_time( (string) ( $entry_row['start_time'] ?? '' ), $offset_minutes );
				$new_end    = self::shift_wall_time( (string) ( $entry_row['end_time'] ?? '' ), $offset_minutes );
				$calc       = $calculator->calculate(
					$entry_date,
					substr( $new_start, 0, 5 ),
					substr( $new_end, 0, 5 )
				);

				$decoded[ $index ]['start_time']       = substr( $new_start, 0, 5 );
				$decoded[ $index ]['end_time']         = substr( $new_end, 0, 5 );
				$decoded[ $index ]['duration_minutes'] = $calc['duration_minutes'];
				$decoded[ $index ]['regular_hours']    = $calc['regular_hours'];
				$decoded[ $index ]['overtime_hours']   = $calc['overtime_hours'];
				$changed                               = true;
			}

			if ( ! $changed ) {
				continue;
			}

			$result = $wpdb->update(
				$table,
				array(
					'draft_snapshot' => wp_json_encode( $decoded ),
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				++$updated;
			}
		}

		return $updated;
	}

	private static function recalculate_timesheet_totals(): int {
		global $wpdb;

		$entries_table    = $wpdb->prefix . 'etwa_entries';
		$timesheets_table = $wpdb->prefix . 'etwa_timesheets';
		$entry_repo       = new EntryRepository();
		$timesheet_repo   = new TimesheetRepository();
		$updated          = 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$ids = $wpdb->get_col( "SELECT DISTINCT timesheet_id FROM {$entries_table}" );
		if ( ! $ids ) {
			return 0;
		}

		foreach ( array_map( 'intval', $ids ) as $timesheet_id ) {
			if ( $timesheet_id <= 0 ) {
				continue;
			}

			$sums = $entry_repo->sum_hours_by_timesheet( $timesheet_id );
			$ok   = $timesheet_repo->update(
				$timesheet_id,
				array(
					'total_regular_hours'  => $sums['regular'] + $sums['overtime'],
					'total_overtime_hours' => 0,
				)
			);

			if ( $ok ) {
				++$updated;
			}
		}

		return $updated;
	}

	private static function shift_wall_time( string $time, int $offset_minutes ): string {
		$time = trim( $time );
		if ( ! preg_match( '/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $matches ) ) {
			return $time;
		}

		$seconds = ( (int) $matches[1] * 3600 )
			+ ( (int) $matches[2] * 60 )
			+ (int) ( $matches[3] ?? 0 )
			+ ( $offset_minutes * 60 );

		$seconds = max( 0, min( 86399, $seconds ) );

		return gmdate( 'H:i:s', $seconds );
	}
}
