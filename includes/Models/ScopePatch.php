<?php
/**
 * Scope patch model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopePatch {

	public function __construct(
		public readonly ?int $id,
		public readonly int $scope_id,
		public readonly string $name,
		public readonly ?string $code,
		public readonly string $description,
		public readonly string $status,
		public readonly float $expected_qty,
		public readonly ?string $effective_on,
		public readonly int $created_by,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r    = (array) $row;
		$code = $r['code'] ?? null;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['scope_id'] ?? 0 ),
			(string) ( $r['name'] ?? '' ),
			null !== $code && '' !== (string) $code ? (string) $code : null,
			(string) ( $r['description'] ?? '' ),
			(string) ( $r['status'] ?? 'draft' ),
			(float) ( $r['expected_qty'] ?? 0 ),
			! empty( $r['effective_on'] ) ? (string) $r['effective_on'] : null,
			(int) ( $r['created_by'] ?? 0 ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'scope_id'     => $this->scope_id,
			'name'         => $this->name,
			'code'         => $this->code,
			'description'  => $this->description,
			'status'       => $this->status,
			'expected_qty' => $this->expected_qty,
			'effective_on' => $this->effective_on,
			'created_by'   => $this->created_by,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		);
	}
}
