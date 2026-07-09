<?php
/**
 * Leave type model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LeaveType {

	public function __construct(
		public int $id,
		public string $slug,
		public string $name,
		public bool $tracks_allocation,
		public int $sort_order,
		public bool $is_active,
		public string $created_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->slug,
			(string) $row->name,
			(bool) (int) $row->tracks_allocation,
			(int) $row->sort_order,
			(bool) (int) $row->is_active,
			(string) $row->created_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                => $this->id,
			'slug'              => $this->slug,
			'name'              => $this->name,
			'tracks_allocation' => $this->tracks_allocation,
			'sort_order'        => $this->sort_order,
			'is_active'         => $this->is_active,
			'created_at'        => $this->created_at,
		);
	}
}
