<?php
/**
 * Department model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Department {

	public function __construct(
		public int $id,
		public string $name,
		public string $description,
		public ?int $head_user_id,
		public bool $approval_enabled,
		public string $status,
		public string $created_at,
		public string $updated_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->name,
			(string) ( $row->description ?? '' ),
			isset( $row->head_user_id ) && $row->head_user_id ? (int) $row->head_user_id : null,
			(bool) ( $row->approval_enabled ?? 1 ),
			(string) ( $row->status ?? 'active' ),
			(string) $row->created_at,
			(string) $row->updated_at,
		);
	}

	public function is_active(): bool {
		return 'active' === $this->status;
	}

	public function has_head(): bool {
		return null !== $this->head_user_id && $this->head_user_id > 0;
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'name'             => $this->name,
			'description'      => $this->description,
			'head_user_id'     => $this->head_user_id,
			'approval_enabled' => $this->approval_enabled,
			'status'           => $this->status,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		);
	}
}
