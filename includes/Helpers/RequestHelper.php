<?php
/**
 * Generic request helpers (WFH, permission, business trip, holiday work).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestHelper {

	public const TYPE_WFH            = 'wfh';
	public const TYPE_PERMISSION     = 'permission';
	public const TYPE_BUSINESS_TRIP  = 'business_trip';
	public const TYPE_HOLIDAY_WORK   = 'holiday_work';
	public const TYPE_TRANSPORTATION = 'transportation';

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/** Fixed permission duration in hours for new permission requests. */
	public const PERMISSION_DURATION_HOURS = 2;

	/** @return string[] */
	public static function valid_types(): array {
		return array(
			self::TYPE_WFH,
			self::TYPE_PERMISSION,
			self::TYPE_BUSINESS_TRIP,
			self::TYPE_HOLIDAY_WORK,
			self::TYPE_TRANSPORTATION,
		);
	}

	/** @return string[] Types exposed in the UI. */
	public static function exposed_types(): array {
		return array(
			self::TYPE_WFH,
			self::TYPE_PERMISSION,
			self::TYPE_HOLIDAY_WORK,
		);
	}

	/** @return string[] */
	public static function valid_statuses(): array {
		return array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED );
	}

	/** @return array<string,string> */
	public static function permission_types(): array {
		return array(
			'late_arrival' => __( 'Late Arrival', 'etwa' ),
			'early_leave'  => __( 'Early Leave', 'etwa' ),
		);
	}

	/** @return array<string,string> Reason categories for permission requests. */
	public static function permission_reasons(): array {
		return array(
			'personal'       => __( 'Personal', 'etwa' ),
			'medical'        => __( 'Medical', 'etwa' ),
			'government'     => __( 'Government', 'etwa' ),
			'family'         => __( 'Family', 'etwa' ),
			'transportation' => __( 'Transportation', 'etwa' ),
			'emergency'      => __( 'Emergency', 'etwa' ),
			'other'          => __( 'Other', 'etwa' ),
		);
	}

	/** @return array<string,string> Legacy permission type labels for older records. */
	public static function legacy_permission_types(): array {
		return array(
			'doctor'          => __( 'Doctor appointment', 'etwa' ),
			'government'      => __( 'Government office', 'etwa' ),
			'personal_errand' => __( 'Personal errand', 'etwa' ),
			'school'          => __( 'School', 'etwa' ),
			'other'           => __( 'Other', 'etwa' ),
		);
	}

	/** @return array<string,string> */
	public static function holiday_work_types(): array {
		return array(
			'weekend_work'   => __( 'Weekend Work', 'etwa' ),
			'public_holiday' => __( 'Public Holiday', 'etwa' ),
			'company_event'  => __( 'Company Event', 'etwa' ),
			'work_on_site'   => __( 'Work On Site', 'etwa' ),
		);
	}

	public static function type_label( string $type ): string {
		return match ( sanitize_key( $type ) ) {
			self::TYPE_WFH            => __( 'Work From Home', 'etwa' ),
			self::TYPE_PERMISSION     => __( 'Permission', 'etwa' ),
			self::TYPE_BUSINESS_TRIP  => __( 'Business Trip / Field Visit', 'etwa' ),
			self::TYPE_HOLIDAY_WORK   => __( 'Work on Holiday', 'etwa' ),
			self::TYPE_TRANSPORTATION => __( 'Transportation Request', 'etwa' ),
			default                   => ucfirst( str_replace( '_', ' ', $type ) ),
		};
	}

	public static function permission_type_label( ?string $slug ): string {
		if ( ! $slug ) {
			return '';
		}
		$types = self::permission_types();
		if ( isset( $types[ $slug ] ) ) {
			return $types[ $slug ];
		}
		$legacy = self::legacy_permission_types();
		return $legacy[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
	}

	public static function permission_reason_label( ?string $slug ): string {
		if ( ! $slug ) {
			return '';
		}
		$reasons = self::permission_reasons();
		if ( isset( $reasons[ $slug ] ) ) {
			return $reasons[ $slug ];
		}
		$legacy = self::legacy_permission_types();
		return $legacy[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
	}

	/** @return array{start:string,end:string}|null */
	public static function permission_time_window( ?string $permission_type ): ?array {
		return match ( sanitize_key( (string) $permission_type ) ) {
			'late_arrival' => array(
				'start' => '10:00',
				'end'   => '12:00',
			),
			'early_leave' => array(
				'start' => '16:00',
				'end'   => '18:00',
			),
			default => null,
		};
	}

	public static function permission_duration_label(
		?string $permission_type,
		?string $start_time,
		?string $end_time,
		?array $extra = null
	): string {
		if ( $start_time && $end_time ) {
			return self::format_time_range( $start_time, $end_time );
		}

		$window = self::permission_time_window( $permission_type );
		if ( $window ) {
			return self::format_time_range( $window['start'], $window['end'] );
		}

		$duration_hours = isset( $extra['duration_hours'] ) ? (int) $extra['duration_hours'] : 0;
		if ( $duration_hours > 0 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $duration_hours, 'etwa' ),
				$duration_hours
			);
		}

		if ( $start_time || $end_time ) {
			return self::format_time_range( $start_time, $end_time );
		}

		return '';
	}

	public static function permission_reason_display( Request $request, ?array $extra = null ): string {
		$extra = null !== $extra ? $extra : self::decode_extra_data( $request->extra_data );
		if ( ! empty( $extra['permission_reason'] ) ) {
			$slug = (string) $extra['permission_reason'];
			if ( 'other' === $slug && ! empty( $extra['specify_reason'] ) ) {
				return sanitize_text_field( (string) $extra['specify_reason'] );
			}
			$label = self::permission_reason_label( $slug );
			if ( $label ) {
				return $label;
			}
		}

		return $request->reason;
	}

	public static function holiday_work_type_label( ?string $slug ): string {
		if ( ! $slug ) {
			return '';
		}
		$types = self::holiday_work_types();
		return $types[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
	}

	public static function format_time_range( ?string $start, ?string $end ): string {
		return DateHelper::format_time_range( $start, $end );
	}

	public static function is_valid_time( string $time ): bool {
		return (bool) preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $time );
	}

	/** @return array<string,mixed> */
	public static function decode_extra_data( ?string $json ): array {
		if ( ! $json ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}

