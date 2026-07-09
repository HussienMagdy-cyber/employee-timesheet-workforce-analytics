<?php
/**
 * Generic request helpers (WFH, permission, business trip, holiday work).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

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
		return $types[ $slug ] ?? ucfirst( str_replace( '_', ' ', $slug ) );
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

