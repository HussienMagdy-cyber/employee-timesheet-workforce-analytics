<?php
/**
 * Centralized hierarchical approval service for all request types.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\ApprovalWorkflowHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApprovalService {

	/** @return array{workflow_status:string,status:string} */
	public function initial_workflow_state( int $employee_id ): array {
		if ( DepartmentHelper::department_requires_head_approval( $employee_id ) ) {
			return array(
				'workflow_status' => ApprovalWorkflowHelper::STAGE_PENDING_DEPARTMENT,
				'status'          => 'pending',
			);
		}
		return array(
			'workflow_status' => ApprovalWorkflowHelper::STAGE_PENDING_HR,
			'status'          => 'pending',
		);
	}

	public function requires_department_approval( int $employee_id ): bool {
		return DepartmentHelper::department_requires_head_approval( $employee_id );
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int,created_at:string} $request
	 */
	public function get_workflow_status( object $request ): string {
		if ( ! empty( $request->workflow_status ) ) {
			$stage = (string) $request->workflow_status;
		} elseif ( 'approved' === $request->status ) {
			$stage = ApprovalWorkflowHelper::STAGE_APPROVED;
		} elseif ( 'rejected' === $request->status ) {
			$stage = ApprovalWorkflowHelper::STAGE_REJECTED_HR;
		} elseif ( 'cancelled' === $request->status ) {
			$stage = ApprovalWorkflowHelper::STAGE_CANCELLED;
		} else {
			$stage = DepartmentHelper::department_requires_head_approval( (int) $request->user_id )
				? ApprovalWorkflowHelper::STAGE_PENDING_DEPARTMENT
				: ApprovalWorkflowHelper::STAGE_PENDING_HR;
		}

		return $this->normalize_workflow_stage( $request, $stage );
	}

	/**
	 * Escalate department-head self-requests that were stored as pending department.
	 *
	 * @param object{user_id:int} $request
	 */
	private function normalize_workflow_stage( object $request, string $stage ): string {
		if ( ApprovalWorkflowHelper::STAGE_PENDING_DEPARTMENT !== $stage ) {
			return $stage;
		}
		$head_id = DepartmentHelper::get_department_head_id( (int) $request->user_id );
		if ( $head_id && $head_id === (int) $request->user_id ) {
			return ApprovalWorkflowHelper::STAGE_PENDING_HR;
		}
		return $stage;
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int} $request
	 */
	public function reviewer_slot( int $user_id, object $request ): string {
		if ( $this->can_user_approve_department( $user_id, $request ) ) {
			return ApprovalWorkflowHelper::SLOT_DEPARTMENT;
		}
		if ( $this->can_user_approve_hr( $user_id, $request ) ) {
			return ApprovalWorkflowHelper::SLOT_HR;
		}
		return '';
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int} $request
	 */
	public function can_user_approve_department( int $user_id, object $request ): bool {
		if ( 'pending' !== $request->status ) {
			return false;
		}
		if ( (int) $request->user_id === $user_id ) {
			return false;
		}
		$stage = $this->get_workflow_status( $request );
		if ( ApprovalWorkflowHelper::STAGE_PENDING_DEPARTMENT !== $stage ) {
			return false;
		}
		$head_id = DepartmentHelper::get_department_head_id( (int) $request->user_id );
		return $head_id && $head_id === $user_id && user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS );
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int} $request
	 */
	public function can_user_approve_hr( int $user_id, object $request ): bool {
		if ( 'pending' !== $request->status ) {
			return false;
		}
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_VACATION ) ) {
			return false;
		}
		$stage = $this->get_workflow_status( $request );
		return ApprovalWorkflowHelper::STAGE_PENDING_HR === $stage;
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string} $request
	 */
	public function can_user_reject( int $user_id, object $request ): bool {
		if ( 'pending' !== $request->status ) {
			return false;
		}
		return $this->can_user_approve_department( $user_id, $request )
			|| $this->can_user_approve_hr( $user_id, $request );
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int} $request
	 */
	public function is_fully_approved( object $request ): bool {
		return 'approved' === $request->status
			|| ApprovalWorkflowHelper::STAGE_APPROVED === $this->get_workflow_status( $request );
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int} $request
	 */
	public function status_label( object $request ): string {
		$stage = $this->get_workflow_status( $request );
		return ApprovalWorkflowHelper::stage_label( $stage );
	}

	/**
	 * @param object{status:string,notes?:?string} $request
	 */
	public function rejection_reason( object $request ): string {
		if ( 'rejected' !== $request->status ) {
			return '';
		}

		return trim( (string) ( $request->notes ?? '' ) );
	}

	/**
	 * @param object{user_id:int,status:string,workflow_status?:string,dept_approved_by?:?int,hr_approved_by?:?int,created_at:string,reviewed_by?:?int,reviewed_at?:?string,dept_approved_at?:?string,hr_approved_at?:?string,notes?:?string} $request
	 * @return array<int,array{action:string,by:string,at:string,label:string,reason?:string}>
	 */
	public function build_timeline( object $request ): array {
		$timeline = array(
			array(
				'action' => 'submitted',
				'by'     => $this->employee_name( (int) $request->user_id ),
				'at'     => DateHelper::format_datetime( (string) $request->created_at ),
				'label'  => __( 'Submitted', 'etwa' ),
			),
		);

		if ( ! empty( $request->dept_approved_by ) && ! empty( $request->dept_approved_at ) ) {
			$user       = get_userdata( (int) $request->dept_approved_by );
			$timeline[] = array(
				'action' => 'dept_approved',
				'by'     => $user ? $user->display_name : '',
				'at'     => DateHelper::format_datetime( (string) $request->dept_approved_at ),
				'label'  => __( 'Approved by Department Head', 'etwa' ),
			);
		}

		$stage = $this->get_workflow_status( $request );

		if ( ApprovalWorkflowHelper::STAGE_REJECTED_DEPT === $stage && ! empty( $request->reviewed_by ) && ! empty( $request->reviewed_at ) ) {
			$user       = get_userdata( (int) $request->reviewed_by );
			$entry      = array(
				'action' => 'dept_rejected',
				'by'     => $user ? $user->display_name : '',
				'at'     => DateHelper::format_datetime( (string) $request->reviewed_at ),
				'label'  => __( 'Rejected by Department Head', 'etwa' ),
			);
			$reason = $this->rejection_reason( $request );
			if ( '' !== $reason ) {
				$entry['reason'] = $reason;
			}
			$timeline[] = $entry;
			return $timeline;
		}

		if ( ! empty( $request->hr_approved_by ) && ! empty( $request->hr_approved_at ) ) {
			$user       = get_userdata( (int) $request->hr_approved_by );
			$timeline[] = array(
				'action' => 'hr_approved',
				'by'     => $user ? $user->display_name : '',
				'at'     => DateHelper::format_datetime( (string) $request->hr_approved_at ),
				'label'  => __( 'Approved by HR', 'etwa' ),
			);
		}

		if ( ApprovalWorkflowHelper::STAGE_REJECTED_HR === $stage && ! empty( $request->reviewed_by ) && ! empty( $request->reviewed_at ) ) {
			$user  = get_userdata( (int) $request->reviewed_by );
			$entry = array(
				'action' => 'hr_rejected',
				'by'     => $user ? $user->display_name : '',
				'at'     => DateHelper::format_datetime( (string) $request->reviewed_at ),
				'label'  => __( 'Rejected by HR', 'etwa' ),
			);
			$reason = $this->rejection_reason( $request );
			if ( '' !== $reason ) {
				$entry['reason'] = $reason;
			}
			$timeline[] = $entry;
		}

		if ( 'approved' === $request->status && empty( $request->hr_approved_by ) && ! empty( $request->reviewed_by ) && ! empty( $request->reviewed_at ) ) {
			$user       = get_userdata( (int) $request->reviewed_by );
			$timeline[] = array(
				'action' => 'approved',
				'by'     => $user ? $user->display_name : '',
				'at'     => DateHelper::format_datetime( (string) $request->reviewed_at ),
				'label'  => __( 'Approved by HR', 'etwa' ),
			);
		}

		return $timeline;
	}

	/**
	 * @param object{user_id:int,status:string} $request
	 * @return array<string,mixed>
	 */
	public function record_department_approval( int $reviewer_id, object $request, string $notes = '' ): array {
		$now = current_time( 'mysql' );
		return array(
			'notes'            => $notes,
			'status'           => 'pending',
			'workflow_status'  => ApprovalWorkflowHelper::STAGE_PENDING_HR,
			'dept_approved_by' => $reviewer_id,
			'dept_approved_at' => $now,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function record_hr_approval( int $reviewer_id, string $notes = '' ): array {
		$now = current_time( 'mysql' );
		return array(
			'notes'           => $notes,
			'status'          => 'approved',
			'workflow_status' => ApprovalWorkflowHelper::STAGE_APPROVED,
			'hr_approved_by'  => $reviewer_id,
			'hr_approved_at'  => $now,
			'reviewed_by'     => $reviewer_id,
			'reviewed_at'     => $now,
		);
	}

	/**
	 * @param object{user_id:int} $request
	 * @return array<string,mixed>
	 */
	public function record_rejection( int $reviewer_id, object $request, string $notes = '' ): array {
		$now   = current_time( 'mysql' );
		$stage = $this->can_user_approve_department( $reviewer_id, $request )
			? ApprovalWorkflowHelper::STAGE_REJECTED_DEPT
			: ApprovalWorkflowHelper::STAGE_REJECTED_HR;

		return array(
			'notes'           => $notes,
			'status'          => 'rejected',
			'workflow_status' => $stage,
			'reviewed_by'     => $reviewer_id,
			'reviewed_at'     => $now,
		);
	}

	public function next_notification_recipient( int $employee_id, string $event ): ?array {
		if ( 'submitted' === $event ) {
			$head_id = DepartmentHelper::get_department_head_id( $employee_id );
			if ( $head_id ) {
				$user = get_userdata( $head_id );
				return $user ? array( 'email' => $user->user_email, 'user_id' => $head_id, 'role' => 'department_head' ) : null;
			}
			return $this->hr_recipient();
		}
		if ( 'dept_approved' === $event ) {
			return $this->hr_recipient();
		}
		if ( 'hr_approved' === $event || 'dept_rejected' === $event || 'hr_rejected' === $event ) {
			$user = get_userdata( $employee_id );
			return $user ? array( 'email' => $user->user_email, 'user_id' => $employee_id, 'role' => 'employee' ) : null;
		}
		return null;
	}

	/** @return array{email:string,user_id:int,role:string}|null */
	private function hr_recipient(): ?array {
		$settings = new \ETWA\Repositories\SettingsRepository();
		$email    = (string) $settings->get( 'hr_email', get_option( 'admin_email' ) );
		if ( ! $email ) {
			return null;
		}
		$user = get_user_by( 'email', $email );
		return array(
			'email'   => $email,
			'user_id' => $user ? (int) $user->ID : 0,
			'role'    => 'hr',
		);
	}

	private function employee_name( int $user_id ): string {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '';
	}
}
