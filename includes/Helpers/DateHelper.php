<?php
/**
 * Date and work-week helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DateHelper {

	/** Working week: Sunday (0) through Thursday (4). */
	public static function get_work_week_for_date( string $date ): array {
		$dt            = new \DateTimeImmutable( $date );
		$dow           = (int) $dt->format( 'w' );
		$sunday_offset = -$dow;
		$period_start  = $dt->modify( "{$sunday_offset} days" )->format( 'Y-m-d' );
		$period_end    = $dt->modify( "{$sunday_offset} days" )->modify( '+4 days' )->format( 'Y-m-d' );

		return array(
			'period_start' => $period_start,
			'period_end'   => $period_end,
		);
	}

	public static function get_current_work_week(): array {
		return self::get_work_week_for_date( current_time( 'Y-m-d' ) );
	}

	public static function get_previous_work_week(): array {
		$current = self::get_current_work_week();
		$start   = new \DateTimeImmutable( $current['period_start'] );
		return self::get_work_week_for_date( $start->modify( '-7 days' )->format( 'Y-m-d' ) );
	}

	/** @return array{period_start:string,period_end:string} */
	public static function get_adjacent_work_week( string $period_start, int $direction ): array {
		$start = new \DateTimeImmutable( $period_start );
		$days  = 7 * ( $direction >= 0 ? 1 : -1 );
		return self::get_work_week_for_date( $start->modify( "{$days} days" )->format( 'Y-m-d' ) );
	}

	public static function format_week_range( string $period_start, string $period_end ): string {
		$start = new \DateTimeImmutable( $period_start );
		$end   = new \DateTimeImmutable( $period_end );

		if ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
			if ( $start->format( 'M' ) === $end->format( 'M' ) ) {
				return $start->format( 'M j' ) . ' — ' . $end->format( 'j, Y' );
			}
			return $start->format( 'M j' ) . ' — ' . $end->format( 'M j, Y' );
		}

		return $start->format( 'M j, Y' ) . ' — ' . $end->format( 'M j, Y' );
	}

	/** @return array{name:string,short:string,formatted:string,dow:int} */
	public static function get_day_info( string $date ): array {
		$dt = new \DateTimeImmutable( $date );
		return array(
			'name'      => $dt->format( 'l' ),
			'short'     => $dt->format( 'D' ),
			'formatted' => $dt->format( 'M j' ),
			'dow'       => (int) $dt->format( 'w' ),
		);
	}

	/**
	 * @return string[] Dates Y-m-d for Sun–Thu in the given period.
	 */
	public static function get_work_dates( string $period_start, string $period_end ): array {
		$dates  = array();
		$cursor = new \DateTimeImmutable( $period_start );
		$end    = new \DateTimeImmutable( $period_end );

		while ( $cursor <= $end ) {
			$dates[] = $cursor->format( 'Y-m-d' );
			$cursor  = $cursor->modify( '+1 day' );
		}
		return $dates;
	}

	public static function is_work_day( string $date ): bool {
		return CalendarHelper::can_log_entry( $date );
	}

	/** 12-hour clock format used across the plugin UI. */
	public static function time_format(): string {
		return 'g:i A';
	}

	public static function format_time( ?string $time ): string {
		if ( ! $time ) {
			return '';
		}

		$time = substr( $time, 0, 5 );
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return $time;
		}

		$ts = strtotime( "today {$time}" );
		if ( ! $ts ) {
			return $time;
		}

		return wp_date( self::time_format(), $ts );
	}

	public static function format_time_range( ?string $start, ?string $end ): string {
		if ( ! $start && ! $end ) {
			return '';
		}

		$start_label = $start ? self::format_time( $start ) : '';
		$end_label   = $end ? self::format_time( $end ) : '';

		if ( $start_label && $end_label ) {
			return $start_label . ' – ' . $end_label;
		}

		return $start_label ?: $end_label;
	}

	public static function format_datetime( string $datetime ): string {
		$ts = strtotime( $datetime );
		if ( ! $ts ) {
			return $datetime;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . self::time_format(), $ts );
	}
}
