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

		$start = new \DateTimeImmutable( "{$entry_date} {$start_time}" );
		$end   = new \DateTimeImmutable( "{$entry_date} {$end_time}" );

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
	 * Generate 30-minute time slot options between configured bounds.
	 *
	 * @return string[]
	 */
	public function get_time_slots(): array {
		$interval = (int) $this->settings->get( 'time_interval_minutes', 30 );
		$start    = $this->settings->get( 'work_day_start', '10:00' );
		$end      = (string) $this->settings->get( 'time_slot_end', '22:00' );

		$slots   = array();
		$cursor  = new \DateTimeImmutable( "today {$start}" );
		$limit   = new \DateTimeImmutable( "today {$end}" );

		while ( $cursor <= $limit ) {
			$slots[] = $cursor->format( 'H:i' );
			$cursor  = $cursor->modify( "+{$interval} minutes" );
		}
		return $slots;
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
