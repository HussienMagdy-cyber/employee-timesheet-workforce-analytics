<?php
/**
 * Timesheet entry type helpers (regular, overtime, compensation).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Repositories\SettingsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntryTypeHelper {

	public const REGULAR      = 'regular';
	public const OVERTIME     = 'overtime';
	public const COMPENSATION = 'compensation';

	public const WORK_DAY_END = '18:00:00';

	public static function work_day_end(): string {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		$setting = (string) ( new SettingsRepository() )->get( 'work_day_end', '18:00' );
		$cached  = self::normalize_boundary_time( $setting );

		return $cached;
	}

	private static function normalize_boundary_time( string $time ): string {
		$time = trim( $time );
		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return $time . ':00';
		}
		if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			return $time;
		}

		return self::WORK_DAY_END;
	}

	/** @return string[] */
	public static function allowed(): array {
		return array(
			self::REGULAR,
			self::OVERTIME,
			self::COMPENSATION,
		);
	}

	/** @return array<string,string> */
	public static function labels(): array {
		return array(
			self::REGULAR      => __( 'Regular', 'etwa' ),
			self::OVERTIME     => __( 'Overtime', 'etwa' ),
			self::COMPENSATION => __( 'Compensation', 'etwa' ),
		);
	}

	public static function label( string $type ): string {
		return self::labels()[ $type ] ?? ucfirst( $type );
	}

	public static function is_after_work_hours( string $start_time ): bool {
		$time = strlen( $start_time ) <= 5 ? $start_time . ':00' : $start_time;
		return strcmp( $time, self::work_day_end() ) >= 0;
	}

	public static function resolve_entry_type( string $start_time, string $requested, bool $is_break ): string|WP_Error {
		if ( $is_break ) {
			return self::REGULAR;
		}

		if ( ! self::is_after_work_hours( $start_time ) ) {
			return self::REGULAR;
		}

		$requested = sanitize_key( $requested );
		if ( ! in_array( $requested, array( self::OVERTIME, self::COMPENSATION ), true ) ) {
			return new WP_Error(
				'missing_entry_type',
				__( 'Please select an entry type (Overtime or Compensation) for entries after 6:00 PM.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		return $requested;
	}

	/**
	 * @return array{regular:float,overtime:float}
	 */
	public static function split_hours( string $entry_type, float $duration_hours, bool $is_break ): array {
		if ( $is_break ) {
			return array(
				'regular'  => 0.0,
				'overtime' => 0.0,
			);
		}

		if ( self::OVERTIME === $entry_type ) {
			return array(
				'regular'  => 0.0,
				'overtime' => round( max( 0.0, $duration_hours ), 2 ),
			);
		}

		if ( self::COMPENSATION === $entry_type ) {
			return array(
				'regular'  => round( max( 0.0, $duration_hours ), 2 ),
				'overtime' => 0.0,
			);
		}

		return array(
			'regular'  => round( max( 0.0, $duration_hours ), 2 ),
			'overtime' => 0.0,
		);
	}

	public static function compensation_hours_from_minutes( int $duration_minutes ): float {
		return round( max( 0, $duration_minutes ) / 60, 2 );
	}

	/**
	 * Recalculate duration and hour columns from wall-clock times and entry type.
	 *
	 * @return array{duration_minutes:int,regular_hours:float,overtime_hours:float,entry_type:string}
	 */
	public static function normalize_stored_hours(
		string $entry_type,
		string $entry_date,
		string $start_time,
		string $end_time,
		bool $is_break,
		?TimeCalculator $calculator = null
	): array {
		$calculator = $calculator ?? new TimeCalculator();
		$start      = strlen( $start_time ) <= 5 ? $start_time : substr( $start_time, 0, 5 );
		$end        = strlen( $end_time ) <= 5 ? $end_time : substr( $end_time, 0, 5 );
		$calc       = $calculator->calculate( $entry_date, $start, $end );

		$type = sanitize_key( $entry_type );
		if ( ! in_array( $type, self::allowed(), true ) ) {
			$type = self::REGULAR;
		}

		if (
			self::REGULAR === $type
			&& ! $is_break
			&& self::is_after_work_hours( $start_time )
			&& $calc['duration_minutes'] > 0
		) {
			$type = self::OVERTIME;
		}

		$hours = self::split_hours( $type, (float) $calc['regular_hours'], $is_break );

		return array(
			'duration_minutes' => (int) $calc['duration_minutes'],
			'regular_hours'    => $hours['regular'],
			'overtime_hours'   => $hours['overtime'],
			'entry_type'       => $type,
		);
	}
}
