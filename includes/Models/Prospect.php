<?php
/**
 * Prospect model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Prospect {

	public const STATUS_OPEN = 'open';
	public const STATUS_WON  = 'won';
	public const STATUS_LOST = 'lost';

	public const SOURCE_REFERRALS      = 'referrals';
	public const SOURCE_SOCIAL_MEDIA   = 'social_media';
	public const SOURCE_DIRECT_CONTACT = 'direct_contact';
	public const SOURCE_CAMPAIGNS      = 'campaigns';
	public const SOURCE_EVENTS         = 'events';

	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $status,
		public readonly ?string $source,
		public readonly ?string $prospect_start_date,
		public readonly ?string $won_lost_at,
		public readonly ?string $lost_reason,
		public readonly ?int $converted_client_id,
		public readonly ?string $converted_at,
		public readonly string $created_at,
	) {}

	/** @return string[] */
	public static function statuses(): array {
		return array( self::STATUS_OPEN, self::STATUS_WON, self::STATUS_LOST );
	}

	/** @return string[] */
	public static function sources(): array {
		return array(
			self::SOURCE_REFERRALS,
			self::SOURCE_SOCIAL_MEDIA,
			self::SOURCE_DIRECT_CONTACT,
			self::SOURCE_CAMPAIGNS,
			self::SOURCE_EVENTS,
		);
	}

	/** @return array<string,string> */
	public static function source_labels(): array {
		return array(
			self::SOURCE_REFERRALS      => __( 'Referrals', 'etwa' ),
			self::SOURCE_SOCIAL_MEDIA   => __( 'Social Media', 'etwa' ),
			self::SOURCE_DIRECT_CONTACT => __( 'Direct Contact', 'etwa' ),
			self::SOURCE_CAMPAIGNS      => __( 'Campaigns', 'etwa' ),
			self::SOURCE_EVENTS         => __( 'Events', 'etwa' ),
		);
	}

	public function is_open(): bool {
		return self::STATUS_OPEN === $this->status;
	}

	public function is_won(): bool {
		return self::STATUS_WON === $this->status;
	}

	public function is_lost(): bool {
		return self::STATUS_LOST === $this->status;
	}

	public function is_converted(): bool {
		return null !== $this->converted_client_id && $this->converted_client_id > 0;
	}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		$converted = isset( $r['converted_client_id'] ) ? (int) $r['converted_client_id'] : 0;

		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(string) $r['name'],
			self::normalize_status( (string) ( $r['status'] ?? self::STATUS_OPEN ) ),
			self::normalize_source( $r['source'] ?? null ),
			self::nullable_string( $r['prospect_start_date'] ?? null ),
			self::nullable_string( $r['won_lost_at'] ?? null ),
			self::nullable_string( $r['lost_reason'] ?? null ),
			$converted > 0 ? $converted : null,
			self::nullable_string( $r['converted_at'] ?? null ),
			(string) ( $r['created_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                   => $this->id,
			'name'                 => $this->name,
			'status'               => $this->status,
			'source'               => $this->source,
			'prospect_start_date'  => $this->prospect_start_date,
			'won_lost_at'          => $this->won_lost_at,
			'lost_reason'          => $this->lost_reason,
			'converted_client_id'  => $this->converted_client_id,
			'converted_at'         => $this->converted_at,
			'is_converted'         => $this->is_converted(),
			'created_at'           => $this->created_at,
		);
	}

	private static function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, self::statuses(), true ) ? $status : self::STATUS_OPEN;
	}

	public static function normalize_source_key( mixed $source ): string {
		$key = sanitize_key( (string) ( $source ?? '' ) );
		return in_array( $key, self::sources(), true ) ? $key : '';
	}

	private static function normalize_source( mixed $source ): ?string {
		$key = self::normalize_source_key( $source );
		return '' === $key ? null : $key;
	}

	private static function nullable_string( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}
		$text = trim( (string) $value );
		return '' === $text ? null : $text;
	}
}
