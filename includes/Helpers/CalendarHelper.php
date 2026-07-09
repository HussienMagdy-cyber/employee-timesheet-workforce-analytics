<?php
/**
 * Work calendar — seven-day week + holiday resolution.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Repositories\HolidayRepository;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CalendarHelper {

	public const STATUS_WORKING = 'working';
	public const STATUS_HOLIDAY = 'holiday';
	public const STATUS_DAY_OFF = 'day_off';

	/** @return array<int,string> dow (0=Sun) => status */
	public static function weekday_config(): array {
		$raw = ( new SettingsRepository() )->get( 'calendar_weekdays', '' );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return self::normalize_weekday_config( $decoded );
			}
		}

		return self::default_weekday_config();
	}

	/** @return array<int,string> */
	public static function default_weekday_config(): array {
		return array(
			0 => self::STATUS_WORKING,
			1 => self::STATUS_WORKING,
			2 => self::STATUS_WORKING,
			3 => self::STATUS_WORKING,
			4 => self::STATUS_WORKING,
			5 => self::STATUS_DAY_OFF,
			6 => self::STATUS_DAY_OFF,
		);
	}

	/**
	 * @param array<int|string,string> $config
	 * @return array<int,string>
	 */
	public static function normalize_weekday_config( array $config ): array {
		$defaults = self::default_weekday_config();
		$out      = $defaults;
		foreach ( $config as $dow => $status ) {
			$dow = (int) $dow;
			if ( $dow >= 0 && $dow <= 6 ) {
				$status = sanitize_key( (string) $status );
				if ( in_array( $status, array( self::STATUS_WORKING, self::STATUS_DAY_OFF ), true ) ) {
					$out[ $dow ] = $status;
				}
			}
		}
		return $out;
	}

	/** @return string[] Sun–Sat Y-m-d for the week containing period_start (Sunday). */
	public static function get_calendar_dates( string $period_start ): array {
		$dt    = new \DateTimeImmutable( $period_start );
		$dow   = (int) $dt->format( 'w' );
		$sunday = $dt->modify( "-{$dow} days" );
		$dates = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$dates[] = $sunday->modify( "+{$i} days" )->format( 'Y-m-d' );
		}
		return $dates;
	}

	public static function get_day_status( string $date ): string {
		$holiday = self::holiday_for_date( $date );
		if ( $holiday ) {
			return self::STATUS_HOLIDAY;
		}

		$dow    = (int) ( new \DateTimeImmutable( $date ) )->format( 'w' );
		$config = self::weekday_config();

		return $config[ $dow ] ?? self::STATUS_DAY_OFF;
	}

	/** @return array{name:string,id:int}|null */
	public static function holiday_for_date( string $date ): ?array {
		$map = self::holidays_map_for_range( $date, $date );
		return $map[ $date ] ?? null;
	}

	public static function can_log_entry( string $date ): bool {
		return self::STATUS_WORKING === self::get_day_status( $date );
	}

	/**
	 * @return array<string,array{name:string,id:int}>
	 */
	public static function holidays_map_for_range( string $date_from, string $date_to ): array {
		$repo = new HolidayRepository();
		return $repo->map_for_date_range( $date_from, $date_to );
	}

	/** @return array<string,array{status:string,label:string,holiday_name:?string}> */
	public static function build_day_meta( array $dates ): array {
		$from = $dates[0] ?? '';
		$to   = $dates[ count( $dates ) - 1 ] ?? $from;
		$map  = $from && $to ? self::holidays_map_for_range( $from, $to ) : array();
		$meta = array();

		foreach ( $dates as $date ) {
			$status = self::get_day_status( $date );
			$name   = $map[ $date ]['name'] ?? null;
			if ( self::STATUS_HOLIDAY === $status && $name ) {
				$label = $name;
			} elseif ( self::STATUS_HOLIDAY === $status ) {
				$label = __( 'Holiday', 'etwa' );
			} elseif ( self::STATUS_DAY_OFF === $status ) {
				$label = __( 'Day Off', 'etwa' );
			} else {
				$label = __( 'Working', 'etwa' );
			}

			$meta[ $date ] = array(
				'status'       => $status,
				'label'        => $label,
				'holiday_name' => $name,
			);
		}

		return $meta;
	}

	public static function weekday_labels(): array {
		return array(
			0 => __( 'Sunday', 'etwa' ),
			1 => __( 'Monday', 'etwa' ),
			2 => __( 'Tuesday', 'etwa' ),
			3 => __( 'Wednesday', 'etwa' ),
			4 => __( 'Thursday', 'etwa' ),
			5 => __( 'Friday', 'etwa' ),
			6 => __( 'Saturday', 'etwa' ),
		);
	}
}
