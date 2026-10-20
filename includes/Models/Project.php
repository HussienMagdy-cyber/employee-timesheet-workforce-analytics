<?php
/**
 * Project model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Project {

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_ARCHIVED = 'archived';

	public function __construct(
		public readonly ?int $id,
		public readonly ?int $client_id,
		public readonly ?int $parent_project_id,
		public readonly string $name,
		public readonly string $description,
		public readonly string $status,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		$client_id = isset( $r['client_id'] ) && (int) $r['client_id'] > 0 ? (int) $r['client_id'] : null;
		$parent_id = isset( $r['parent_project_id'] ) && (int) $r['parent_project_id'] > 0 ? (int) $r['parent_project_id'] : null;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			$client_id,
			$parent_id,
			(string) ( $r['name'] ?? '' ),
			(string) ( $r['description'] ?? '' ),
			(string) ( $r['status'] ?? self::STATUS_ACTIVE ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'client_id'          => $this->client_id,
			'parent_project_id'  => $this->parent_project_id,
			'name'               => $this->name,
			'description'        => $this->description,
			'status'             => $this->status,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}

	public function is_active(): bool {
		return self::STATUS_ACTIVE === $this->status;
	}

	public function is_standalone(): bool {
		return null === $this->client_id;
	}
}
