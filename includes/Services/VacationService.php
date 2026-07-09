<?php
/**
 * Vacation and leave management service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\CalendarHelper;
use ETWA\Helpers\ApprovalWorkflowHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\VacationApprovalHelper;
use ETWA\Helpers\VacationHelper;
use ETWA\Models\LeaveType;
use ETWA\Models\VacationRecord;
use ETWA\Models\VacationTransaction;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\LeaveTypeRepository;
use ETWA\Repositories\VacationRecordRepository;
use ETWA\Repositories\VacationRequestRepository;
use ETWA\Repositories\VacationTransactionRepository;
use ETWA\Models\VacationRequest;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationService {

	public function __construct(
		private LeaveTypeRepository $leave_types = new LeaveTypeRepository(),
		private VacationTransactionRepository $transactions = new VacationTransactionRepository(),
		private VacationRecordRepository $records = new VacationRecordRepository(),
		private VacationRequestRepository $requests = new VacationRequestRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function can_manage( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_MANAGE_VACATION );
	}

	public function can_review( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return $this->can_manage( $user_id ) || $this->is_department_head( $user_id );
	}

	public function is_department_head( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS )
			&& DepartmentHelper::user_heads_department( $user_id );
	}

	public function can_view_own( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_VIEW_OWN_VACATION );
	}

	public function can_view_employee( int $viewer_id, int $employee_id ): bool {
		if ( $this->can_manage( $viewer_id ) ) {
			return true;
		}
		return $viewer_id === $employee_id && $this->can_view_own( $viewer_id );
	}

	public function can_delete( int $user_id ): bool {
		return user_can( $user_id, RoleManager::CAP_LOCK_PERIODS );
	}

	/** @return LeaveType[]|WP_Error */
	public function list_leave_types(): array|WP_Error {
		return $this->leave_types->all_active();
	}

	/** @return array<string,mixed>|WP_Error */
	public function employee_profile( int $viewer_id, int $employee_id ): array|WP_Error {
		if ( ! $this->can_view_employee( $viewer_id, $employee_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this vacation profile.', 'etwa' ), array( 'status' => 403 ) );
		}

		$user = get_userdata( $employee_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		return array(
			'employee'    => array(
				'id'           => $employee_id,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
			),
			'balances'    => VacationHelper::employee_balances( $employee_id ),
			'annual_pool' => VacationHelper::annual_pool( $employee_id ),
			'can_edit'    => $this->can_manage( $viewer_id ),
		);
	}

	/** @param array<string,mixed> $filters @return array<string,mixed>|WP_Error */
	public function list_history( int $viewer_id, array $filters, int $page = 1, int $per_page = 25 ): array|WP_Error {
		if ( ! $this->can_manage( $viewer_id ) ) {
			if ( empty( $filters['user_id'] ) || (int) $filters['user_id'] !== $viewer_id ) {
				return new WP_Error( 'forbidden', __( 'You cannot view vacation history.', 'etwa' ), array( 'status' => 403 ) );
			}
		}

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$total    = $this->transactions->count_history( $filters );
		$rows     = $this->transactions->list_history( $filters, $per_page, $offset );

		$items = array();
		foreach ( $rows as $row ) {
			$creator = get_userdata( (int) $row->created_by );
			$items[] = array(
				'id'               => (int) $row->id,
				'source'           => (string) $row->source,
				'user_id'          => (int) $row->user_id,
				'leave_type_id'    => (int) $row->leave_type_id,
				'leave_type_name'  => (string) $row->leave_type_name,
				'transaction_type' => (string) $row->transaction_type,
				'transaction_label'=> VacationHelper::transaction_type_label( (string) $row->transaction_type ),
				'days'             => (float) $row->days,
				'status'           => $row->status ? (string) $row->status : null,
				'reason'           => (string) ( $row->reason ?? '' ),
				'event_date'       => (string) $row->event_date,
				'created_by'       => (int) $row->created_by,
				'created_by_name'  => $creator ? $creator->display_name : '',
				'created_at'       => (string) $row->created_at,
				'created_at_label' => DateHelper::format_datetime( (string) $row->created_at ),
				'record_id'        => isset( $row->record_id ) ? (int) $row->record_id : null,
			);
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @param array<string,mixed> $data @return VacationTransaction|WP_Error */
	public function create_allocation( int $editor_id, array $data ): VacationTransaction|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot allocate leave.', 'etwa' ), array( 'status' => 403 ) );
		}

		$user_id       = absint( $data['user_id'] ?? 0 );
		$leave_type_id = absint( $data['leave_type_id'] ?? 0 );
		$days          = (float) ( $data['days'] ?? 0 );
		$effective     = sanitize_text_field( (string) ( $data['effective_date'] ?? '' ) );
		$notes         = sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) );

		$error = $this->validate_employee_and_type( $user_id, $leave_type_id, true );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$annual = VacationHelper::annual_leave_type();
		if ( ! $annual || (int) $leave_type_id !== (int) $annual->id ) {
			return new WP_Error(
				'invalid_type',
				__( 'Annual leave balance can only be set on the Annual Leave type.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( $days <= 0 ) {
			return new WP_Error( 'invalid_days', __( 'Allocated days must be greater than zero.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! VacationHelper::validate_date( $effective ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid effective date.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $this->transactions->allocation_exists( $user_id, $leave_type_id, $effective ) ) {
			return new WP_Error( 'duplicate', __( 'An allocation already exists for this employee, leave type, and effective date.', 'etwa' ), array( 'status' => 409 ) );
		}

		$id = $this->transactions->create(
			array(
				'user_id'          => $user_id,
				'leave_type_id'    => $leave_type_id,
				'transaction_type' => 'allocation',
				'days'             => $days,
				'effective_date'   => $effective,
				'notes'            => $notes,
				'created_by'       => $editor_id,
			)
		);

		$transaction = $this->transactions->find( $id );
		$type        = $this->leave_types->find( $leave_type_id );
		$this->audit_vacation(
			'allocate',
			'vacation_allocation',
			$id,
			null,
			$transaction?->to_array(),
			$editor_id,
			$user_id,
			sprintf(
				/* translators: 1: days, 2: leave type name */
				__( 'Allocated %1$s %2$s', 'etwa' ),
				$days,
				$type ? $type->name : ''
			)
		);

		return $transaction ?: new WP_Error( 'create_failed', __( 'Could not create allocation.', 'etwa' ) );
	}

	/** @param array<string,mixed> $data @return VacationTransaction|WP_Error */
	public function update_allocation( int $editor_id, int $id, array $data ): VacationTransaction|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit allocations.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->transactions->find( $id );
		if ( ! $existing || 'allocation' !== $existing->transaction_type ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['days'] ) ) {
			$days = (float) $data['days'];
			if ( $days <= 0 ) {
				return new WP_Error( 'invalid_days', __( 'Allocated days must be greater than zero.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['days'] = $days;
		}
		if ( isset( $data['effective_date'] ) ) {
			$effective = sanitize_text_field( (string) $data['effective_date'] );
			if ( ! VacationHelper::validate_date( $effective ) ) {
				return new WP_Error( 'invalid_date', __( 'Invalid effective date.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( $this->transactions->allocation_exists( $existing->user_id, $existing->leave_type_id, $effective, $id ) ) {
				return new WP_Error( 'duplicate', __( 'An allocation already exists for this effective date.', 'etwa' ), array( 'status' => 409 ) );
			}
			$payload['effective_date'] = $effective;
		}
		if ( isset( $data['notes'] ) ) {
			$payload['notes'] = sanitize_textarea_field( (string) $data['notes'] );
		}
		if ( isset( $data['leave_type_id'] ) ) {
			$type_id = (int) $data['leave_type_id'];
			$type    = $this->leave_types->find( $type_id );
			if ( ! $type || ! $type->tracks_allocation ) {
				return new WP_Error( 'invalid_type', __( 'Invalid leave type for allocation.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['leave_type_id'] = $type_id;
		}

		if ( $payload ) {
			$check = $this->validate_balance_after_change( $existing->user_id, $payload['leave_type_id'] ?? $existing->leave_type_id, $existing, $payload );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$this->transactions->update( $id, $payload );
		}

		$updated = $this->transactions->find( $id );
		$this->audit_vacation( 'update', 'vacation_allocation', $id, $existing->to_array(), $updated?->to_array(), $editor_id, $existing->user_id, __( 'Edited allocation', 'etwa' ) );

		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update allocation.', 'etwa' ) );
	}

	public function delete_allocation( int $editor_id, int $id ): true|WP_Error {
		if ( ! $this->can_delete( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'Only the owner can delete allocations.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->transactions->find( $id );
		if ( ! $existing || 'allocation' !== $existing->transaction_type ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$this->transactions->delete( $id );
		$this->audit_vacation( 'delete', 'vacation_allocation', $id, $existing->to_array(), null, $editor_id, $existing->user_id, __( 'Deleted allocation', 'etwa' ) );

		return true;
	}

	/** @param array<string,mixed> $data @return VacationRecord|WP_Error */
	public function create_record( int $editor_id, array $data ): VacationRecord|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot record leave.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_record_payload( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		if ( VacationHelper::STATUS_APPROVED === $validated['status'] ) {
			$balance_check = $this->check_sufficient_balance(
				$validated['user_id'],
				$validated['leave_type_id'],
				$validated['days']
			);
			if ( is_wp_error( $balance_check ) ) {
				return $balance_check;
			}
		}

		$id = $this->records->create(
			array_merge(
				$validated,
				array( 'created_by' => $editor_id )
			)
		);

		$record = $this->records->find( $id );
		if ( $record && VacationHelper::STATUS_APPROVED === $record->status ) {
			$this->sync_usage_transaction( $record, $editor_id );
			$this->notify_employee_approved( $record );
		}

		$this->audit_vacation(
			'create',
			'vacation_record',
			$id,
			null,
			$record?->to_array(),
			$editor_id,
			$validated['user_id'],
			$this->record_audit_message( 'create', $record )
		);

		return $record ?: new WP_Error( 'create_failed', __( 'Could not create vacation record.', 'etwa' ) );
	}

	/** @param array<string,mixed> $data @return VacationRecord|WP_Error */
	public function update_record( int $editor_id, int $id, array $data ): VacationRecord|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit vacation records.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->records->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Vacation record not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$merged = array_merge( $existing->to_array(), $data );
		$validated = $this->validate_record_payload( $merged, $id );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$old_status = $existing->status;
		$new_status = $validated['status'];

		if ( VacationHelper::STATUS_APPROVED === $new_status ) {
			$extra_days = $validated['days'];
			if ( VacationHelper::STATUS_APPROVED === $old_status ) {
				$extra_days = $validated['days'] - $existing->days;
				if ( (int) $validated['leave_type_id'] !== $existing->leave_type_id ) {
					$extra_days = $validated['days'];
				}
			}
			if ( $extra_days > 0 || (int) $validated['leave_type_id'] !== $existing->leave_type_id ) {
				$check = $this->check_sufficient_balance(
					$validated['user_id'],
					$validated['leave_type_id'],
					max( 0, $extra_days ),
					$existing
				);
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
		}

		$this->records->update(
			$id,
			array(
				'leave_type_id' => $validated['leave_type_id'],
				'start_date'    => $validated['start_date'],
				'end_date'      => $validated['end_date'],
				'days'          => $validated['days'],
				'reason'        => $validated['reason'],
				'status'        => $validated['status'],
				'notes'         => $validated['notes'],
				'updated_by'    => $editor_id,
			)
		);

		$record = $this->records->find( $id );
		if ( ! $record ) {
			return new WP_Error( 'update_failed', __( 'Could not update vacation record.', 'etwa' ) );
		}

		$this->sync_record_transactions( $existing, $record, $editor_id );

		if ( VacationHelper::STATUS_APPROVED === $new_status && VacationHelper::STATUS_APPROVED !== $old_status ) {
			$this->notify_employee_approved( $record );
		}

		$this->audit_vacation(
			'update',
			'vacation_record',
			$id,
			$existing->to_array(),
			$record->to_array(),
			$editor_id,
			$record->user_id,
			$this->record_audit_message( 'update', $record, $existing )
		);

		return $record;
	}

	public function delete_record( int $editor_id, int $id ): true|WP_Error {
		if ( ! $this->can_delete( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'Only the owner can delete vacation records.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->records->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Vacation record not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$this->transactions->delete_by_record( $id );
		$this->records->delete( $id );
		$this->audit_vacation( 'delete', 'vacation_record', $id, $existing->to_array(), null, $editor_id, $existing->user_id, __( 'Deleted vacation', 'etwa' ) );

		return true;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_record( int $viewer_id, int $id ): array|WP_Error {
		$record = $this->records->find( $id );
		if ( ! $record ) {
			return new WP_Error( 'not_found', __( 'Vacation record not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( ! $this->can_view_employee( $viewer_id, $record->user_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this record.', 'etwa' ), array( 'status' => 403 ) );
		}

		$type = $this->leave_types->find( $record->leave_type_id );
		$user = get_userdata( $record->user_id );
		return array_merge(
			$record->to_array(),
			array(
				'leave_type_name' => $type ? $type->name : '',
				'employee_name'   => $user ? $user->display_name : '',
			)
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_allocation( int $viewer_id, int $id ): array|WP_Error {
		if ( ! $this->can_manage( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view allocations.', 'etwa' ), array( 'status' => 403 ) );
		}

		$transaction = $this->transactions->find( $id );
		if ( ! $transaction || 'allocation' !== $transaction->transaction_type ) {
			return new WP_Error( 'not_found', __( 'Allocation not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$type = $this->leave_types->find( $transaction->leave_type_id );
		return array_merge(
			$transaction->to_array(),
			array( 'leave_type_name' => $type ? $type->name : '' )
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function calendar( int $viewer_id, int $year, int $month ): array|WP_Error {
		if ( ! $this->can_manage( $viewer_id ) && ! $this->can_view_own( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view the vacation calendar.', 'etwa' ), array( 'status' => 403 ) );
		}

		$month = max( 1, min( 12, $month ) );
		$start = sprintf( '%04d-%02d-01', $year, $month );
		$end   = ( new \DateTimeImmutable( $start ) )->modify( 'last day of this month' )->format( 'Y-m-d' );

		$rows = $this->records->approved_in_range( $start, $end );
		$events = array();
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$events[] = array(
				'id'              => (int) $row->id,
				'user_id'         => (int) $row->user_id,
				'employee_name'   => $user ? $user->display_name : '',
				'leave_type_name' => (string) $row->leave_type_name,
				'start_date'      => (string) $row->start_date,
				'end_date'        => (string) $row->end_date,
				'days'            => (float) $row->days,
				'reason'          => (string) ( $row->reason ?? '' ),
			);
		}

		return array(
			'year'     => $year,
			'month'    => $month,
			'events'   => $events,
			'holidays' => CalendarHelper::holidays_map_for_range( $start, $end ),
		);
	}

	/** @return object[] */
	public function unread_notifications( int $user_id ): array {
		$stored = get_user_meta( $user_id, 'etwa_vacation_notifications', true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$stored,
				fn( $item ) => is_array( $item ) && empty( $item['read'] )
			)
		);
	}

	public function dismiss_notifications( int $user_id ): void {
		delete_user_meta( $user_id, 'etwa_vacation_notifications' );
	}

	/** @param array<string,mixed> $data @return VacationRequest|WP_Error */
	public function submit_request( int $user_id, array $data ): VacationRequest|WP_Error {
		if ( ! $this->can_view_own( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot submit vacation requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! VacationHelper::is_etwa_employee( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Only employees can submit vacation requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_request_payload( $data, $user_id );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$workflow = ( new ApprovalService() )->initial_workflow_state( $user_id );
		$validated = array_merge( $validated, $workflow );

		$id = $this->requests->create( $validated );
		$request = $this->requests->find( $id );

		$this->audit_vacation(
			'create',
			'vacation_request',
			$id,
			null,
			$request?->to_array(),
			$user_id,
			$user_id,
			__( 'Submitted vacation request', 'etwa' )
		);

		do_action( 'etwa_vacation_requested', $id, $user_id, $request?->to_array() );

		return $request ?: new WP_Error( 'create_failed', __( 'Could not submit request.', 'etwa' ) );
	}

	/** @param array<string,mixed> $filters @return array<string,mixed>|WP_Error */
	public function list_requests( int $viewer_id, array $filters, int $page = 1, int $per_page = 25 ): array|WP_Error {
		if ( $this->can_manage( $viewer_id ) ) {
			// HR sees all.
		} elseif ( $this->is_department_head( $viewer_id ) ) {
			$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
			if ( ! $dept_id ) {
				return new WP_Error( 'forbidden', __( 'You cannot view vacation requests.', 'etwa' ), array( 'status' => 403 ) );
			}
			$filters['user_ids'] = DepartmentHelper::employee_ids_in_department( $dept_id );
		} elseif ( $this->can_view_own( $viewer_id ) ) {
			$filters['user_id'] = $viewer_id;
		} else {
			return new WP_Error( 'forbidden', __( 'You cannot view vacation requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$items    = $this->requests->all( $filters, $per_page, $offset );

		return array(
			'items'    => array_map( fn( VacationRequest $r ) => $this->format_request( $r, $viewer_id ), $items ),
			'total'    => $this->requests->count( $filters ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/** @return VacationRequest|WP_Error */
	public function review_request( int $reviewer_id, int $request_id, string $status, string $notes = '', string $approval_slot = '' ): VacationRequest|WP_Error {
		if ( ! $this->can_review( $reviewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot review vacation requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! in_array( $status, array( VacationHelper::STATUS_APPROVED, VacationHelper::STATUS_REJECTED ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid review status.', 'etwa' ), array( 'status' => 400 ) );
		}

		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( VacationHelper::STATUS_PENDING !== $request->status ) {
			return new WP_Error( 'invalid', __( 'This request has already been reviewed.', 'etwa' ), array( 'status' => 409 ) );
		}

		$old      = $request->to_array();
		$approval = new ApprovalService();

		if ( VacationHelper::STATUS_REJECTED === $status ) {
			$notes = trim( $notes );
			if ( '' === $notes ) {
				return new WP_Error( 'notes_required', __( 'A rejection reason is required.', 'etwa' ), array( 'status' => 400 ) );
			}

			if ( ! VacationApprovalHelper::can_user_reject( $reviewer_id, $request ) ) {
				return new WP_Error( 'forbidden', __( 'You cannot reject this request.', 'etwa' ), array( 'status' => 403 ) );
			}

			$update = $approval->record_rejection( $reviewer_id, $request, $notes ?: $request->notes );
			$this->requests->update( $request_id, $update );
			$updated = $this->requests->find( $request_id );

			$this->notify_employee_rejected( $request, $notes );
			$audit_msg = ApprovalWorkflowHelper::STAGE_REJECTED_DEPT === ( $update['workflow_status'] ?? '' )
				? __( 'Department Head rejected vacation request', 'etwa' )
				: __( 'HR rejected vacation request', 'etwa' );

			$this->audit_vacation(
				$status,
				'vacation_request',
				$request_id,
				$old,
				$updated?->to_array(),
				$reviewer_id,
				$request->user_id,
				$audit_msg
			);

			do_action( 'etwa_vacation_rejected', $request_id, $request->user_id, $updated?->to_array() );

			return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
		}

		$approval_slot = sanitize_key( $approval_slot );
		if ( '' === $approval_slot ) {
			$approval_slot = VacationApprovalHelper::reviewer_slot( $reviewer_id );
		}

		if ( ApprovalWorkflowHelper::SLOT_DEPARTMENT === $approval_slot ) {
			if ( ! VacationApprovalHelper::can_user_approve_department_slot( $reviewer_id, $request ) ) {
				return new WP_Error( 'forbidden', __( 'You cannot approve this request as Department Head.', 'etwa' ), array( 'status' => 403 ) );
			}

			$update = $approval->record_department_approval( $reviewer_id, $request, $notes ?: $request->notes );
			$this->requests->update( $request_id, $update );
			$updated = $this->requests->find( $request_id );

			$this->audit_vacation(
				'dept_approved',
				'vacation_request',
				$request_id,
				$old,
				$updated?->to_array(),
				$reviewer_id,
				$request->user_id,
				__( 'Department Head approved vacation request', 'etwa' )
			);

			do_action( 'etwa_vacation_dept_approved', $request_id, $request->user_id, $updated?->to_array() );

			return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
		}

		if ( ! VacationApprovalHelper::can_user_approve_hr_slot( $reviewer_id, $request ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot approve this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		$record = $this->create_record(
			$reviewer_id,
			array(
				'user_id'       => $request->user_id,
				'leave_type_id' => $request->leave_type_id,
				'start_date'    => $request->start_date,
				'end_date'      => $request->end_date,
				'days'          => $request->days,
				'reason'        => $request->reason,
				'status'        => VacationHelper::STATUS_APPROVED,
				'notes'         => $notes ?: $request->notes,
			)
		);
		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$update = $approval->record_hr_approval( $reviewer_id, $notes ?: $request->notes );
		$this->requests->update( $request_id, $update );
		$updated = $this->requests->find( $request_id );

		$this->audit_vacation(
			$status,
			'vacation_request',
			$request_id,
			$old,
			$updated?->to_array(),
			$reviewer_id,
			$request->user_id,
			__( 'HR approved vacation request', 'etwa' )
		);

		do_action( 'etwa_vacation_hr_approved', $request_id, $request->user_id, $updated?->to_array() );

		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
	}

	/**
	 * @param array<int,array{id:int}> $items
	 * @return array{processed:int,failed:int,errors:array<int,string>}|WP_Error
	 */
	public function bulk_review( int $reviewer_id, array $items, string $status, string $notes = '', string $approval_slot = '' ): array|WP_Error {
		if ( ! $this->can_review( $reviewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot review vacation requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$processed = 0;
		$failed    = 0;
		$errors    = array();

		foreach ( $items as $item ) {
			$id     = (int) ( $item['id'] ?? 0 );
			$result = $this->review_request( $reviewer_id, $id, $status, $notes, $approval_slot );
			if ( is_wp_error( $result ) ) {
				++$failed;
				$errors[] = sprintf(
					/* translators: 1: request id, 2: error message */
					__( 'Request #%1$d: %2$s', 'etwa' ),
					$id,
					$result->get_error_message()
				);
				continue;
			}
			++$processed;
		}

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'errors'    => $errors,
		);
	}

	public function cancel_request( int $user_id, int $request_id ): true|WP_Error {
		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( $request->user_id !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'You cannot cancel this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( VacationHelper::STATUS_PENDING !== $request->status ) {
			return new WP_Error( 'invalid', __( 'Only pending requests can be cancelled.', 'etwa' ), array( 'status' => 409 ) );
		}

		$this->requests->delete( $request_id );
		$this->audit_vacation( 'cancel', 'vacation_request', $request_id, $request->to_array(), null, $user_id, $user_id, __( 'Cancelled vacation request', 'etwa' ) );

		return true;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_request( int $viewer_id, int $request_id ): array|WP_Error {
		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_manage( $viewer_id ) && ! $this->is_department_head( $viewer_id ) && ( ! $this->can_view_own( $viewer_id ) || (int) $request->user_id !== $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		return $this->format_request( $request, $viewer_id );
	}

	/** @return array<string,mixed> */
	public function format_request_for_viewer( VacationRequest $request, ?int $viewer_id = null ): array {
		return $this->format_request( $request, $viewer_id );
	}

	/** @return array<string,mixed> */
	private function format_request( VacationRequest $request, ?int $viewer_id = null ): array {
		$type = $this->leave_types->find( $request->leave_type_id );
		$user = get_userdata( $request->user_id );
		$reviewer = $request->reviewed_by ? get_userdata( $request->reviewed_by ) : null;
		$approvers = VacationApprovalHelper::approver_names( $request );
		$dept = DepartmentHelper::department_summary( $request->user_id );

		$approval  = new ApprovalService();
		$formatted = array_merge(
			$request->to_array(),
			array(
				'leave_type_name'         => $type ? $type->name : '',
				'employee_name'           => $user ? $user->display_name : '',
				'reviewed_by_name'        => $reviewer ? $reviewer->display_name : '',
				'rejection_reason'        => $approval->rejection_reason( $request ),
				'department'              => $dept,
				'department_name'         => $dept['name'] ?? '',
				'dept_status_label'       => ApprovalWorkflowHelper::dept_status_label( $request->dept_approved_by, $request->workflow_status ),
				'hr_status_label'         => ApprovalWorkflowHelper::hr_status_label( $request->hr_approved_by, $request->status, $request->workflow_status ),
				'dual_approval_enabled'   => VacationApprovalHelper::is_dual_approval_enabled(),
				'hr_approved'             => VacationApprovalHelper::has_hr_approval( $request ),
				'dept_approved'           => VacationApprovalHelper::has_department_approval( $request ),
				'owner_approved'          => false,
				'status_label'            => VacationApprovalHelper::status_label( $request ),
				'hr_approved_by_name'     => $approvers['hr_approved_by_name'],
				'dept_approved_by_name'   => $approvers['dept_approved_by_name'],
				'owner_approved_by_name'  => '',
				'approval_timeline'       => VacationApprovalHelper::timeline( $request ),
				'created_at_label'        => DateHelper::format_datetime( $request->created_at ),
			),
			$this->pending_meta( $request )
		);

		if ( null !== $viewer_id ) {
			$formatted['can_approve_as_hr']         = VacationApprovalHelper::can_user_approve_hr_slot( $viewer_id, $request );
			$formatted['can_approve_as_department'] = VacationApprovalHelper::can_user_approve_department_slot( $viewer_id, $request );
			$formatted['can_approve_as_owner']      = false;
			$formatted['can_reject']                = VacationApprovalHelper::can_user_reject( $viewer_id, $request );
		}

		return $formatted;
	}

	/** @return array{pending_age_hours:int,is_aging:bool} */
	private function pending_meta( VacationRequest $request ): array {
		if ( VacationHelper::STATUS_PENDING !== $request->status ) {
			return array(
				'pending_age_hours' => 0,
				'is_aging'          => false,
			);
		}

		$created = strtotime( (string) $request->created_at );
		$hours   = $created ? max( 0, (int) floor( ( time() - $created ) / HOUR_IN_SECONDS ) ) : 0;

		return array(
			'pending_age_hours' => $hours,
			'is_aging'          => $hours >= 48,
		);
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|WP_Error */
	private function validate_request_payload( array $data, int $user_id ): array|WP_Error {
		$leave_type_id = (int) ( $data['leave_type_id'] ?? 0 );
		$start         = sanitize_text_field( (string) ( $data['start_date'] ?? '' ) );
		$end           = sanitize_text_field( (string) ( $data['end_date'] ?? '' ) );
		$days          = isset( $data['days'] ) ? (float) $data['days'] : VacationHelper::days_between( $start, $end );
		$reason        = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );

		$error = $this->validate_employee_and_type( $user_id, $leave_type_id );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( '' === trim( $reason ) ) {
			return new WP_Error( 'invalid_reason', __( 'Reason is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! VacationHelper::validate_date( $start ) || ! VacationHelper::validate_date( $end ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid start or end date.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $end < $start ) {
			return new WP_Error( 'invalid_range', __( 'End date cannot be before start date.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $days <= 0 ) {
			return new WP_Error( 'invalid_days', __( 'Number of days must be greater than zero.', 'etwa' ), array( 'status' => 400 ) );
		}

		return array(
			'user_id'       => $user_id,
			'leave_type_id' => $leave_type_id,
			'start_date'    => $start,
			'end_date'      => $end,
			'days'          => round( $days, 2 ),
			'reason'        => $reason,
			'status'        => VacationHelper::STATUS_PENDING,
			'notes'         => '',
		);
	}

	private function notify_employee_rejected( VacationRequest $request, string $notes ): void {
		$type = $this->leave_types->find( $request->leave_type_id );

		$notification = array(
			'type'             => 'vacation_rejected',
			'leave_type_name'  => $type ? $type->name : '',
			'start_date'       => $request->start_date,
			'end_date'         => $request->end_date,
			'days'             => $request->days,
			'notes'            => $notes,
			'rejection_reason' => $notes,
			'read'             => false,
			'created_at'       => current_time( 'mysql' ),
		);

		$existing = get_user_meta( $request->user_id, 'etwa_vacation_notifications', true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing[] = $notification;
		update_user_meta( $request->user_id, 'etwa_vacation_notifications', $existing );

		do_action( 'etwa_vacation_rejected', $request->id, $request->user_id, $notification );
	}

	private function notify_employee_approved( VacationRecord $record ): void {
		$type = $this->leave_types->find( $record->leave_type_id );
		$remaining = VacationHelper::remaining_annual_balance( $record->user_id );

		$notification = array(
			'type'             => 'vacation_approved',
			'leave_type_name'  => $type ? $type->name : '',
			'start_date'       => $record->start_date,
			'end_date'         => $record->end_date,
			'days'             => $record->days,
			'remaining'        => $remaining,
			'read'             => false,
			'created_at'       => current_time( 'mysql' ),
		);

		$existing = get_user_meta( $record->user_id, 'etwa_vacation_notifications', true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing[] = $notification;
		update_user_meta( $record->user_id, 'etwa_vacation_notifications', $existing );

		do_action( 'etwa_vacation_approved', $record->id, $record->user_id, $notification );
	}

	private function sync_record_transactions( VacationRecord $old, VacationRecord $new, int $editor_id ): void {
		$this->transactions->delete_by_record( $old->id );

		if ( VacationHelper::STATUS_APPROVED === $new->status ) {
			$this->sync_usage_transaction( $new, $editor_id );
		}
	}

	private function sync_usage_transaction( VacationRecord $record, int $editor_id ): void {
		$this->transactions->create(
			array(
				'user_id'          => $record->user_id,
				'leave_type_id'    => $record->leave_type_id,
				'transaction_type' => 'usage',
				'days'             => -1 * abs( $record->days ),
				'record_id'        => $record->id,
				'effective_date'   => $record->start_date,
				'notes'            => $record->reason,
				'created_by'       => $editor_id,
			)
		);
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|WP_Error */
	private function validate_record_payload( array $data, ?int $record_id = null ): array|WP_Error {
		$user_id       = absint( $data['user_id'] ?? 0 );
		$leave_type_id = absint( $data['leave_type_id'] ?? 0 );
		$start         = sanitize_text_field( (string) ( $data['start_date'] ?? '' ) );
		$end           = sanitize_text_field( (string) ( $data['end_date'] ?? '' ) );
		$days          = isset( $data['days'] ) ? (float) $data['days'] : VacationHelper::days_between( $start, $end );
		$reason        = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );
		$status        = sanitize_text_field( (string) ( $data['status'] ?? VacationHelper::STATUS_PENDING ) );
		$notes         = sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) );

		$error = $this->validate_employee_and_type( $user_id, $leave_type_id );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( ! VacationHelper::validate_date( $start ) || ! VacationHelper::validate_date( $end ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid start or end date.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $end < $start ) {
			return new WP_Error( 'invalid_range', __( 'End date cannot be before start date.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $days <= 0 ) {
			return new WP_Error( 'invalid_days', __( 'Number of days must be greater than zero.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $status, VacationHelper::valid_statuses(), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid status.', 'etwa' ), array( 'status' => 400 ) );
		}

		return array(
			'user_id'       => $user_id,
			'leave_type_id' => $leave_type_id,
			'start_date'    => $start,
			'end_date'      => $end,
			'days'          => round( $days, 2 ),
			'reason'        => $reason,
			'status'        => $status,
			'notes'         => $notes,
		);
	}

	private function validate_employee_and_type( int $user_id, int $leave_type_id, bool $requires_allocation = false ): true|WP_Error {
		if ( $user_id <= 0 ) {
			return new WP_Error( 'invalid_employee', __( 'Please select an employee.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! VacationHelper::is_etwa_employee( $user_id ) ) {
			return new WP_Error(
				'invalid_employee',
				__( 'The selected user is not an employee. Assign the Think Marketing Employee role in WordPress Users.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$type = $this->leave_types->find( $leave_type_id );
		if ( ! $type || ! $type->is_active ) {
			return new WP_Error( 'invalid_type', __( 'Please select a valid leave type.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $requires_allocation && ! $type->tracks_allocation ) {
			return new WP_Error( 'invalid_type', __( 'This leave type does not support allocation.', 'etwa' ), array( 'status' => 400 ) );
		}

		return true;
	}

	private function check_sufficient_balance( int $user_id, int $leave_type_id, float $days, ?VacationRecord $exclude = null ): true|WP_Error {
		if ( ! VacationHelper::deducts_from_annual_pool( $leave_type_id ) ) {
			return true;
		}

		$remaining = VacationHelper::remaining_annual_balance( $user_id );
		if ( $exclude && VacationHelper::STATUS_APPROVED === $exclude->status && VacationHelper::deducts_from_annual_pool( $exclude->leave_type_id ) ) {
			$remaining += $exclude->days;
		}

		if ( $days > $remaining ) {
			return new WP_Error(
				'insufficient_balance',
				sprintf(
					/* translators: 1: remaining annual days */
					__( 'Insufficient annual leave balance. Remaining: %s days.', 'etwa' ),
					number_format( $remaining, 2 )
				),
				array( 'status' => 409 )
			);
		}

		return true;
	}

	/** @param array<string,mixed> $payload */
	private function validate_balance_after_change( int $user_id, int $leave_type_id, VacationTransaction $existing, array $payload ): true|WP_Error {
		$annual = VacationHelper::annual_leave_type();
		if ( ! $annual || (int) $leave_type_id !== (int) $annual->id ) {
			return true;
		}

		$remaining = VacationHelper::remaining_annual_balance( $user_id );
		$old_days = $existing->days;
		$new_days = isset( $payload['days'] ) ? (float) $payload['days'] : $old_days;
		$delta    = $new_days - $old_days;

		if ( $delta < 0 && abs( $delta ) > $remaining + $old_days ) {
			return new WP_Error( 'negative_balance', __( 'This change would create a negative balance.', 'etwa' ), array( 'status' => 409 ) );
		}

		return true;
	}

	private function record_audit_message( string $action, ?VacationRecord $record, ?VacationRecord $old = null ): string {
		if ( ! $record ) {
			return __( 'Vacation record changed', 'etwa' );
		}

		$type = $this->leave_types->find( $record->leave_type_id );
		$name = $type ? $type->name : '';

		if ( 'update' === $action && $old && $old->status !== $record->status ) {
			return match ( $record->status ) {
				VacationHelper::STATUS_APPROVED => sprintf(
					/* translators: %s: leave type */
					__( 'Approved %s vacation', 'etwa' ),
					$name
				),
				VacationHelper::STATUS_REJECTED => sprintf(
					/* translators: %s: leave type */
					__( 'Rejected %s vacation', 'etwa' ),
					$name
				),
				default => __( 'Changed vacation status', 'etwa' ),
			};
		}

		if ( 'update' === $action && $old && (int) $old->leave_type_id !== (int) $record->leave_type_id ) {
			return __( 'Changed leave type', 'etwa' );
		}

		if ( 'update' === $action && $old && (float) $old->days !== (float) $record->days ) {
			return __( 'Changed number of days', 'etwa' );
		}

		return sprintf(
			/* translators: 1: leave type, 2: days */
			__( 'Recorded %1$s leave (%2$s days)', 'etwa' ),
			$name,
			$record->days
		);
	}

	/** @param array<string,mixed>|null $old @param array<string,mixed>|null $new */
	private function audit_vacation(
		string $action,
		string $entity_type,
		int $entity_id,
		?array $old,
		?array $new,
		int $editor_id,
		int $employee_id,
		string $reason
	): void {
		$this->audit->log(
			$action,
			$entity_type,
			$entity_id,
			$old,
			$new,
			$editor_id,
			array(
				'employee_id' => $employee_id,
				'edit_reason' => $reason,
			)
		);
	}
}
