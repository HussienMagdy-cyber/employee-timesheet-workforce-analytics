<?php
/**
 * Time calculation engine for work entries.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimeCalculator {

	private SettingsRepository $settings;

	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/**
	 * Calculate duration and billable hours for a time entry.
	 *
	 * @return array{duration_minutes:int,regular_hours:float,overtime_hours:float}
	 */
	public function calculate( string $entry_date, string $start_time, string $end_time ): array {
		$interval = (int) $this->settings->get( 'time_interval_minutes', 30 );

		$start = DateHelper::parse_local_datetime( $entry_date, $start_time );
		$end   = DateHelper::parse_local_datetime( $entry_date, $end_time );

		if ( ! $start || ! $end ) {
			return array(
				'duration_minutes' => 0,
				'regular_hours'    => 0.0,
				'overtime_hours'   => 0.0,
			);
		}

		if ( $end <= $start ) {
			return array(
				'duration_minutes' => 0,
				'regular_hours'    => 0.0,
				'overtime_hours'   => 0.0,
			);
		}

		$duration_minutes = (int) round( ( $end->getTimestamp() - $start->getTimestamp() ) / 60 );
		$duration_minutes = $this->round_to_interval( $duration_minutes, $interval );
		$regular_hours    = round( $duration_minutes / 60, 2 );

		return array(
			'duration_minutes' => $duration_minutes,
			'regular_hours'    => $regular_hours,
			'overtime_hours'   => 0.0,
		);
	}

	public function round_to_interval( int $minutes, int $interval ): int {
		if ( $interval <= 0 ) {
			return $minutes;
		}
		return (int) ( round( $minutes / $interval ) * $interval );
	}

	/**
	 * Generate time slot options for the full day (00:00 through last interval before midnight).
	 *
	 * @return string[]
	 */
	public function get_time_slots(): array {
		$interval = max( 1, (int) $this->settings->get( 'time_interval_minutes', 30 ) );

		$slots  = array();
		$timezone = wp_timezone();
		$cursor   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', current_time( 'Y-m-d' ) . ' 00:00', $timezone );
		$limit    = $cursor ? $cursor->modify( '+1 day' ) : null;

		if ( ! $cursor || ! $limit ) {
			return array();
		}

		while ( $cursor < $limit ) {
			$slots[] = $cursor->format( 'H:i' );
			$cursor  = $cursor->modify( "+{$interval} minutes" );
		}

		return $slots;
	}

	/** @return array{start:string,end:string} */
	public function get_default_entry_times(): array {
		$interval = max( 1, (int) $this->settings->get( 'time_interval_minutes', 30 ) );
		$start    = $this->snap_to_slot( (string) $this->settings->get( 'work_day_start', '09:00' ) );
		$end_dt = DateHelper::parse_wall_time( $start );

		if ( ! $end_dt ) {
			$end_dt = DateHelper::parse_wall_time( '09:00' );
		}

		if ( ! $end_dt ) {
			return array(
				'start' => $start,
				'end'   => $start,
			);
		}

		$end = $this->snap_to_slot( $end_dt->modify( "+{$interval} minutes" )->format( 'H:i' ) );

		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	private function snap_to_slot( string $time ): string {
		$time  = $this->normalize_slot_time( $time );
		$slots = $this->get_time_slots();

		if ( in_array( $time, $slots, true ) ) {
			return $time;
		}

		foreach ( $slots as $slot ) {
			if ( $slot >= $time ) {
				return $slot;
			}
		}

		return $slots[0] ?? '00:00';
	}

	private function normalize_slot_time( string $time ): string {
		if ( preg_match( '/^\d{2}:\d{2}/', $time ) ) {
			return substr( $time, 0, 5 );
		}

		return '00:00';
	}

	/** @return array<int,array{value:string,label:string}> */
	public function get_time_slot_options(): array {
		return array_map(
			static fn( string $slot ): array => array(
				'value' => $slot,
				'label' => DateHelper::format_time( $slot ),
			),
			$this->get_time_slots()
		);
	}
}
