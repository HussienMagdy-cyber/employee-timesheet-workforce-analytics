<?php
/**
 * Holiday model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Holiday {

	public function __construct(
		public int $id,
		public string $name,
		public string $holiday_date,
		public bool $is_recurring,
		public string $created_at,
		public string $updated_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->name,
			(string) $row->holiday_date,
			(bool) (int) $row->is_recurring,
			(string) $row->created_at,
			(string) $row->updated_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'            => $this->id,
			'name'          => $this->name,
			'holiday_date'  => $this->holiday_date,
			'is_recurring'  => $this->is_recurring,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
		);
	}
}
