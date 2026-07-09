<?php
/**
 * Overtime record model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Overtime {

	public function __construct(
		public readonly ?int $id,
		public readonly int $user_id,
		public readonly int $timesheet_id,
		public readonly int $entry_id,
		public readonly string $entry_date,
		public readonly float $overtime_hours,
		public readonly string $status,
		public readonly float $approved_hours,
		public readonly ?int $reviewed_by,
		public readonly ?string $reviewed_at,
		public readonly ?string $review_notes,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) $r['user_id'],
			(int) $r['timesheet_id'],
			(int) $r['entry_id'],
			(string) $r['entry_date'],
			(float) $r['overtime_hours'],
			(string) $r['status'],
			(float) $r['approved_hours'],
			isset( $r['reviewed_by'] ) ? (int) $r['reviewed_by'] : null,
			$r['reviewed_at'] ?? null,
			$r['review_notes'] ?? null,
			(string) $r['created_at'],
			(string) $r['updated_at'],
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'user_id'         => $this->user_id,
			'timesheet_id'    => $this->timesheet_id,
			'entry_id'        => $this->entry_id,
			'entry_date'      => $this->entry_date,
			'overtime_hours'  => $this->overtime_hours,
			'status'          => $this->status,
			'approved_hours'  => $this->approved_hours,
			'reviewed_by'     => $this->reviewed_by,
			'reviewed_at'     => $this->reviewed_at,
			'review_notes'    => $this->review_notes,
			'created_at'      => $this->created_at,
			'updated_at'      => $this->updated_at,
		);
	}
}
