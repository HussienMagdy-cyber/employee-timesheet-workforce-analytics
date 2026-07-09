<?php
/**
 * Vacation usage record model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationRecord {

	public function __construct(
		public int $id,
		public int $user_id,
		public int $leave_type_id,
		public string $start_date,
		public string $end_date,
		public float $days,
		public string $reason,
		public string $status,
		public string $notes,
		public int $created_by,
		public ?int $updated_by,
		public string $created_at,
		public string $updated_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->user_id,
			(int) $row->leave_type_id,
			(string) $row->start_date,
			(string) $row->end_date,
			(float) $row->days,
			(string) ( $row->reason ?? '' ),
			(string) $row->status,
			(string) ( $row->notes ?? '' ),
			(int) $row->created_by,
			isset( $row->updated_by ) && $row->updated_by ? (int) $row->updated_by : null,
			(string) $row->created_at,
			(string) $row->updated_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'            => $this->id,
			'user_id'       => $this->user_id,
			'leave_type_id' => $this->leave_type_id,
			'start_date'    => $this->start_date,
			'end_date'      => $this->end_date,
			'days'          => $this->days,
			'reason'        => $this->reason,
			'status'        => $this->status,
			'notes'         => $this->notes,
			'created_by'    => $this->created_by,
			'updated_by'    => $this->updated_by,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
		);
	}
}
