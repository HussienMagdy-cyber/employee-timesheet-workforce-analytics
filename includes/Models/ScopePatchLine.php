<?php
/**
 * Scope patch line model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopePatchLine {

	public function __construct(
		public readonly ?int $id,
		public readonly int $patch_id,
		public readonly int $deliverable_type_id,
		public readonly float $qty_delta,
		public readonly string $notes,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['patch_id'] ?? 0 ),
			(int) ( $r['deliverable_type_id'] ?? 0 ),
			(float) ( $r['qty_delta'] ?? 0 ),
			(string) ( $r['notes'] ?? '' ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'patch_id'            => $this->patch_id,
			'deliverable_type_id' => $this->deliverable_type_id,
			'qty_delta'           => $this->qty_delta,
			'notes'               => $this->notes,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		);
	}
}
