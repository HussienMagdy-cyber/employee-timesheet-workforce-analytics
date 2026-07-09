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

	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $code,
		public readonly string $department,
		public readonly bool $is_active,
		public readonly string $created_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(string) $r['name'],
			(string) ( $r['code'] ?? '' ),
			(string) ( $r['department'] ?? '' ),
			(bool) ( $r['is_active'] ?? true ),
			(string) $r['created_at'],
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'name'       => $this->name,
			'code'       => $this->code,
			'department' => $this->department,
			'is_active'  => $this->is_active,
			'created_at' => $this->created_at,
		);
	}
}
