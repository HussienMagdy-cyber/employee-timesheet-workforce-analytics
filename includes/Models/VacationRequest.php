<?php
/**
 * Vacation request model (future employee self-service).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationRequest {

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
		public ?int $reviewed_by,
		public ?string $reviewed_at,
		public ?int $hr_approved_by,
		public ?string $hr_approved_at,
		public ?int $owner_approved_by,
		public ?string $owner_approved_at,
		public ?int $dept_approved_by,
		public ?string $dept_approved_at,
		public string $workflow_status,
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
			isset( $row->reviewed_by ) && $row->reviewed_by ? (int) $row->reviewed_by : null,
			isset( $row->reviewed_at ) ? (string) $row->reviewed_at : null,
			isset( $row->hr_approved_by ) && $row->hr_approved_by ? (int) $row->hr_approved_by : null,
			isset( $row->hr_approved_at ) ? (string) $row->hr_approved_at : null,
			isset( $row->owner_approved_by ) && $row->owner_approved_by ? (int) $row->owner_approved_by : null,
			isset( $row->owner_approved_at ) ? (string) $row->owner_approved_at : null,
			isset( $row->dept_approved_by ) && $row->dept_approved_by ? (int) $row->dept_approved_by : null,
			isset( $row->dept_approved_at ) ? (string) $row->dept_approved_at : null,
			(string) ( $row->workflow_status ?? 'pending_hr' ),
			(string) $row->created_at,
			(string) $row->updated_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'user_id'            => $this->user_id,
			'leave_type_id'      => $this->leave_type_id,
			'start_date'         => $this->start_date,
			'end_date'           => $this->end_date,
			'days'               => $this->days,
			'reason'             => $this->reason,
			'status'             => $this->status,
			'notes'              => $this->notes,
			'reviewed_by'        => $this->reviewed_by,
			'reviewed_at'        => $this->reviewed_at,
			'hr_approved_by'     => $this->hr_approved_by,
			'hr_approved_at'     => $this->hr_approved_at,
			'owner_approved_by'  => $this->owner_approved_by,
			'owner_approved_at'  => $this->owner_approved_at,
			'dept_approved_by'   => $this->dept_approved_by,
			'dept_approved_at'   => $this->dept_approved_at,
			'workflow_status'    => $this->workflow_status,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}
}
