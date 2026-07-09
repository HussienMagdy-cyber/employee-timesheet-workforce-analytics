<?php
/**
 * Timesheet model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Timesheet {

	public function __construct(
		public readonly ?int $id,
		public readonly int $user_id,
		public readonly string $period_start,
		public readonly string $period_end,
		public readonly string $status,
		public readonly float $total_regular_hours,
		public readonly float $total_overtime_hours,
		public readonly ?string $submitted_at,
		public readonly ?int $reviewed_by,
		public readonly ?string $reviewed_at,
		public readonly ?string $review_notes,
		public readonly bool $is_locked,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) $r['user_id'],
			(string) $r['period_start'],
			(string) $r['period_end'],
			(string) $r['status'],
			(float) $r['total_regular_hours'],
			(float) $r['total_overtime_hours'],
			$r['submitted_at'] ?? null,
			isset( $r['reviewed_by'] ) ? (int) $r['reviewed_by'] : null,
			$r['reviewed_at'] ?? null,
			$r['review_notes'] ?? null,
			(bool) ( $r['is_locked'] ?? false ),
			(string) $r['created_at'],
			(string) $r['updated_at'],
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                   => $this->id,
			'user_id'              => $this->user_id,
			'period_start'         => $this->period_start,
			'period_end'           => $this->period_end,
			'status'               => $this->status,
			'total_regular_hours'  => $this->total_regular_hours,
			'total_overtime_hours' => $this->total_overtime_hours,
			'submitted_at'         => $this->submitted_at,
			'reviewed_by'          => $this->reviewed_by,
			'reviewed_at'          => $this->reviewed_at,
			'review_notes'         => $this->review_notes,
			'is_locked'            => $this->is_locked,
			'created_at'           => $this->created_at,
			'updated_at'           => $this->updated_at,
		);
	}
}
