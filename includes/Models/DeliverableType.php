<?php
/**
 * Deliverable type catalog model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableType {

	public function __construct(
		public readonly ?int $id,
		public readonly string $slug,
		public readonly string $name,
		public readonly string $description,
		public readonly float $effort_score,
		public readonly int $sort_order,
		public readonly bool $is_active,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(string) ( $r['slug'] ?? '' ),
			(string) ( $r['name'] ?? '' ),
			(string) ( $r['description'] ?? '' ),
			(float) ( $r['effort_score'] ?? 1 ),
			(int) ( $r['sort_order'] ?? 0 ),
			(bool) (int) ( $r['is_active'] ?? 1 ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'slug'         => $this->slug,
			'name'         => $this->name,
			'description'  => $this->description,
			'effort_score' => $this->effort_score,
			'sort_order'   => $this->sort_order,
			'is_active'    => $this->is_active,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
		);
	}
}
