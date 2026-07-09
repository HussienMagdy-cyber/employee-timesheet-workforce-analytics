<?php
/**
 * Generic employee request model (WFH, permission, business trip).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Request {

	public function __construct(
		public int $id,
		public string $request_type,
		public int $user_id,
		public string $request_date,
		public ?string $work_date,
		public ?string $start_time,
		public ?string $end_time,
		public ?string $permission_type,
		public string $reason,
		public string $notes,
		public string $status,
		public ?int $reviewed_by,
		public ?string $reviewed_at,
		public ?int $hr_approved_by,
		public ?string $hr_approved_at,
		public ?int $owner_approved_by,
		public ?string $owner_approved_at,
		public ?int $dept_approved_by,
		public ?string $dept_approved_at,
		public string $workflow_status,
		public ?string $extra_data,
		public string $created_at,
		public string $updated_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->request_type,
			(int) $row->user_id,
			(string) $row->request_date,
			isset( $row->work_date ) ? (string) $row->work_date : null,
			isset( $row->start_time ) ? (string) $row->start_time : null,
			isset( $row->end_time ) ? (string) $row->end_time : null,
			isset( $row->permission_type ) ? (string) $row->permission_type : null,
			(string) ( $row->reason ?? '' ),
			(string) ( $row->notes ?? '' ),
			(string) $row->status,
			isset( $row->reviewed_by ) && $row->reviewed_by ? (int) $row->reviewed_by : null,
			isset( $row->reviewed_at ) ? (string) $row->reviewed_at : null,
			isset( $row->hr_approved_by ) && $row->hr_approved_by ? (int) $row->hr_approved_by : null,
			isset( $row->hr_approved_at ) ? (string) $row->hr_approved_at : null,
			isset( $row->owner_approved_by ) && $row->owner_approved_by ? (int) $row->owner_approved_by : null,
			isset( $row->owner_approved_at ) ? (string) $row->owner_approved_at : null,
			isset( $row->dept_approved_by ) && $row->dept_approved_by ? (int) $row->dept_approved_by : null,
			isset( $row->dept_approved_at ) ? (string) $row->dept_approved_at : null,
			(string) ( $row->workflow_status ?? 'pending_hr' ),
			isset( $row->extra_data ) ? (string) $row->extra_data : null,
			(string) $row->created_at,
			(string) $row->updated_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'request_type'        => $this->request_type,
			'user_id'             => $this->user_id,
			'request_date'        => $this->request_date,
			'work_date'           => $this->work_date,
			'start_time'          => $this->start_time,
			'end_time'            => $this->end_time,
			'permission_type'     => $this->permission_type,
			'reason'              => $this->reason,
			'notes'               => $this->notes,
			'status'              => $this->status,
			'reviewed_by'         => $this->reviewed_by,
			'reviewed_at'         => $this->reviewed_at,
			'hr_approved_by'      => $this->hr_approved_by,
			'hr_approved_at'      => $this->hr_approved_at,
			'owner_approved_by'   => $this->owner_approved_by,
			'owner_approved_at'   => $this->owner_approved_at,
			'dept_approved_by'    => $this->dept_approved_by,
			'dept_approved_at'    => $this->dept_approved_at,
			'workflow_status'     => $this->workflow_status,
			'extra_data'          => $this->extra_data,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		);
	}
}
