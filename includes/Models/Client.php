<?php
/**
 * Client model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Client {

	public const TYPE_STANDARD = 'standard';
	public const TYPE_SUPER    = 'super';

	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $code,
		public readonly string $department,
		public readonly bool $is_active,
		public readonly string $client_type,
		public readonly string $created_at,
	) {}

	/** @return string[] */
	public static function client_types(): array {
		return array( self::TYPE_STANDARD, self::TYPE_SUPER );
	}

	public function is_super(): bool {
		return self::TYPE_SUPER === $this->client_type;
	}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		$type = sanitize_key( (string) ( $r['client_type'] ?? self::TYPE_STANDARD ) );
		if ( ! in_array( $type, self::client_types(), true ) ) {
			$type = self::TYPE_STANDARD;
		}
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(string) $r['name'],
			(string) ( $r['code'] ?? '' ),
			(string) ( $r['department'] ?? '' ),
			(bool) ( $r['is_active'] ?? true ),
			$type,
			(string) ( $r['created_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'          => $this->id,
			'name'        => $this->name,
			'code'        => $this->code,
			'department'  => $this->department,
			'is_active'   => $this->is_active,
			'client_type' => $this->client_type,
			'created_at'  => $this->created_at,
		);
	}
}
