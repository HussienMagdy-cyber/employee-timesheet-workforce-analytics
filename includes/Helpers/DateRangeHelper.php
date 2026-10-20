<?php
/**
 * Analytics date range presets.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DateRangeHelper {

	public const PRESET_ALL_TIME      = 'all_time';
	public const PRESET_TODAY         = 'today';
	public const PRESET_THIS_WEEK     = 'this_week';
	public const PRESET_THIS_MONTH    = 'this_month';
	public const PRESET_LAST_MONTH    = 'last_month';
	public const PRESET_LAST_3_MONTHS = 'last_3_months';
	public const PRESET_LAST_6_MONTHS = 'last_6_months';
	public const PRESET_THIS_YEAR     = 'this_year';
	public const PRESET_CUSTOM        = 'custom';

	/** @return array<string,string> preset id => label */
	public static function preset_labels(): array {
		return array(
			self::PRESET_THIS_WEEK     => __( 'This Week', 'etwa' ),
			self::PRESET_THIS_MONTH    => __( 'This Month', 'etwa' ),
			self::PRESET_LAST_MONTH    => __( 'Last Month', 'etwa' ),
			self::PRESET_LAST_3_MONTHS => __( 'Last 3 Months', 'etwa' ),
			self::PRESET_LAST_6_MONTHS => __( 'Last 6 Months', 'etwa' ),
			self::PRESET_THIS_YEAR     => __( 'This Year', 'etwa' ),
			self::PRESET_CUSTOM        => __( 'Custom Range', 'etwa' ),
		);
	}

	/** @return array<string,string> preset id => label */
	public static function prospect_analytics_preset_labels(): array {
		return array(
			self::PRESET_ALL_TIME      => __( 'All Time', 'etwa' ),
			self::PRESET_TODAY         => __( 'Today', 'etwa' ),
			self::PRESET_THIS_WEEK     => __( 'This Week', 'etwa' ),
			self::PRESET_THIS_MONTH    => __( 'This Month', 'etwa' ),
			self::PRESET_LAST_MONTH    => __( 'Last Month', 'etwa' ),
			self::PRESET_LAST_3_MONTHS => __( 'Last 3 Months', 'etwa' ),
			self::PRESET_LAST_6_MONTHS => __( 'Last 6 Months', 'etwa' ),
			self::PRESET_THIS_YEAR     => __( 'This Year', 'etwa' ),
			self::PRESET_CUSTOM        => __( 'Custom Range', 'etwa' ),
		);
	}

	/**
	 * @return array{preset:string,date_from:string,date_to:string,label:string}
	 */
	public static function resolve( ?string $preset = null, ?string $date_from = null, ?string $date_to = null ): array {
		$today   = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
		$preset  = self::sanitize_preset( $preset );
		$custom  = self::PRESET_CUSTOM === $preset;

		if ( $custom && self::is_valid_date( $date_from ) && self::is_valid_date( $date_to ) ) {
			$from = $date_from;
			$to   = $date_to;
			if ( $from > $to ) {
				$swap = $from;
				$from = $to;
				$to   = $swap;
			}
		} else {
			$preset = $custom ? self::PRESET_THIS_MONTH : $preset;
			$range  = self::range_for_preset( $preset, $today );
			$from   = $range['date_from'];
			$to     = $range['date_to'];
		}

		$labels = array_merge(
			self::preset_labels(),
			array(
				self::PRESET_ALL_TIME => __( 'All Time', 'etwa' ),
				self::PRESET_TODAY    => __( 'Today', 'etwa' ),
			)
		);
		$label  = $custom
			? self::format_custom_label( $from, $to )
			: ( $labels[ $preset ] ?? $labels[ self::PRESET_THIS_MONTH ] );

		return array(
			'preset'     => $custom ? self::PRESET_CUSTOM : $preset,
			'date_from'  => $from,
			'date_to'    => $to,
			'label'      => $label,
		);
	}

	/** @return array{preset:string,date_from:string,date_to:string} */
	private static function range_for_preset( string $preset, \DateTimeImmutable $today ): array {
		switch ( $preset ) {
			case self::PRESET_ALL_TIME:
				return array(
					'preset'     => $preset,
					'date_from'  => '2000-01-01',
					'date_to'    => $today->format( 'Y-m-d' ),
				);

			case self::PRESET_TODAY:
				return array(
					'preset'     => $preset,
					'date_from'  => $today->format( 'Y-m-d' ),
					'date_to'    => $today->format( 'Y-m-d' ),
				);

			case self::PRESET_THIS_WEEK:
				$week = DateHelper::get_work_week_for_date( $today->format( 'Y-m-d' ) );
				return array(
					'preset'     => $preset,
					'date_from'  => $week['period_start'],
					'date_to'    => min( $week['period_end'], $today->format( 'Y-m-d' ) ),
				);

			case self::PRESET_LAST_MONTH:
				$start = $today->modify( 'first day of last month' );
				$end   = $today->modify( 'last day of last month' );
				return array(
					'preset'     => $preset,
					'date_from'  => $start->format( 'Y-m-d' ),
					'date_to'    => $end->format( 'Y-m-d' ),
				);

			case self::PRESET_LAST_3_MONTHS:
				$start = $today->modify( 'first day of -2 months' );
				return array(
					'preset'     => $preset,
					'date_from'  => $start->format( 'Y-m-d' ),
					'date_to'    => $today->format( 'Y-m-d' ),
				);

			case self::PRESET_LAST_6_MONTHS:
				$start = $today->modify( 'first day of -5 months' );
				return array(
					'preset'     => $preset,
					'date_from'  => $start->format( 'Y-m-d' ),
					'date_to'    => $today->format( 'Y-m-d' ),
				);

			case self::PRESET_THIS_YEAR:
				return array(
					'preset'     => $preset,
					'date_from'  => $today->format( 'Y-01-01' ),
					'date_to'    => $today->format( 'Y-m-d' ),
				);

			case self::PRESET_THIS_MONTH:
			default:
				return array(
					'preset'     => self::PRESET_THIS_MONTH,
					'date_from'  => $today->format( 'Y-m-01' ),
					'date_to'    => $today->format( 'Y-m-d' ),
				);
		}
	}

	public static function sanitize_preset( ?string $preset ): string {
		$preset = sanitize_key( (string) $preset );
		$valid  = array_unique(
			array_merge(
				array_keys( self::preset_labels() ),
				array( self::PRESET_ALL_TIME, self::PRESET_TODAY )
			)
		);
		return in_array( $preset, $valid, true ) ? $preset : self::PRESET_THIS_MONTH;
	}

	private static function is_valid_date( ?string $date ): bool {
		if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	private static function format_custom_label( string $from, string $to ): string {
		return DateHelper::format_week_range( $from, $to );
	}
}
