<?php
/**
 * Super Client field enums and validation.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientRequestHelper {

	public const REQUEST_TMM_INITIATED    = 'tmm_initiated';
	public const REQUEST_CLIENT_INITIATED = 'client_initiated';

	public const CATEGORY_STORYTELLING = 'storytelling';
	public const CATEGORY_SM_CONTENT   = 'sm_content';

	public const AGENCY_AGENCY = 'agency';
	public const AGENCY_DIRECT = 'direct';

	public const INDUSTRY_BANKING     = 'banking';
	public const INDUSTRY_REAL_ESTATE = 'real_estate';
	public const INDUSTRY_TELECOM     = 'telecom';
	public const INDUSTRY_AUTOMOTIVE  = 'automotive';
	public const INDUSTRY_F_AND_B     = 'f_and_b';
	public const INDUSTRY_FASHION     = 'fashion';
	public const INDUSTRY_TECH        = 'tech';

	public const STATUS_PENDING   = 'pending';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_CANCELLED = 'cancelled';

	/** @return string[] */
	public static function industries(): array {
		return array(
			self::INDUSTRY_BANKING,
			self::INDUSTRY_REAL_ESTATE,
			self::INDUSTRY_TELECOM,
			self::INDUSTRY_AUTOMOTIVE,
			self::INDUSTRY_F_AND_B,
			self::INDUSTRY_FASHION,
			self::INDUSTRY_TECH,
		);
	}

	/** @return array<string,string> */
	public static function industry_labels(): array {
		return array(
			self::INDUSTRY_BANKING     => __( 'Banking', 'etwa' ),
			self::INDUSTRY_REAL_ESTATE => __( 'Real Estate', 'etwa' ),
			self::INDUSTRY_TELECOM     => __( 'Telecom', 'etwa' ),
			self::INDUSTRY_AUTOMOTIVE  => __( 'Automotive', 'etwa' ),
			self::INDUSTRY_F_AND_B     => __( 'F&B', 'etwa' ),
			self::INDUSTRY_FASHION     => __( 'Fashion', 'etwa' ),
			self::INDUSTRY_TECH        => __( 'Tech', 'etwa' ),
		);
	}

	/** @return string[] */
	public static function request_types(): array {
		return array( self::REQUEST_TMM_INITIATED, self::REQUEST_CLIENT_INITIATED );
	}

	/** @return array<string,string> */
	public static function request_type_labels(): array {
		return array(
			self::REQUEST_TMM_INITIATED    => __( 'TMM-Initiated', 'etwa' ),
			self::REQUEST_CLIENT_INITIATED => __( 'Client-Initiated', 'etwa' ),
		);
	}

	/** @return string[] */
	public static function service_categories(): array {
		return array( self::CATEGORY_STORYTELLING, self::CATEGORY_SM_CONTENT );
	}

	/** @return array<string,string> */
	public static function service_category_labels(): array {
		return array(
			self::CATEGORY_STORYTELLING => __( 'Storytelling', 'etwa' ),
			self::CATEGORY_SM_CONTENT   => __( 'SM Content', 'etwa' ),
		);
	}

	/** @return string[] */
	public static function agency_types(): array {
		return array( self::AGENCY_AGENCY, self::AGENCY_DIRECT );
	}

	/** @return array<string,string> */
	public static function agency_labels(): array {
		return array(
			self::AGENCY_AGENCY => __( 'Agency', 'etwa' ),
			self::AGENCY_DIRECT => __( 'Direct', 'etwa' ),
		);
	}

	/** @return array<string,string> Legacy keys for v1.32.0 → v1.33.0 data migration only. */
	public static function legacy_service_type_labels(): array {
		return array(
			'pawn'           => 'Pawn',
			'bishop'         => 'Bishop',
			'knight'         => 'Knight',
			'starter_social' => 'Starter Social',
			'smart_social'   => 'Smart Social',
			'genius_social'  => 'Genius Social',
			'social_video'   => 'Social Video',
			'event_coverage' => 'Event Coverage',
		);
	}

	/** @return string[] */
	public static function statuses(): array {
		return array( self::STATUS_PENDING, self::STATUS_PUBLISHED, self::STATUS_CANCELLED );
	}

	/** @return array<string,string> */
	public static function status_labels(): array {
		return array(
			self::STATUS_PENDING   => __( 'Pending', 'etwa' ),
			self::STATUS_PUBLISHED => __( 'Published', 'etwa' ),
			self::STATUS_CANCELLED => __( 'Cancelled', 'etwa' ),
		);
	}

	public static function validate_email( string $email ): bool {
		if ( '' === $email ) {
			return true;
		}
		return (bool) is_email( $email );
	}
}
