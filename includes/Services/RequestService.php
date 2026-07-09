<?php
/**
 * Generic employee request service (WFH, permission, business trip).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\ApprovalWorkflowHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\RequestApprovalHelper;
use ETWA\Helpers\RequestHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\VacationHelper;
use ETWA\Models\Request;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\RequestRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestService {

	private const NOTIFICATION_META = 'etwa_request_notifications';

	public function __construct(
		private RequestRepository $requests = new RequestRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private TimesheetService $timesheets = new TimesheetService(),
	) {}

	public function can_manage( int $user_id ): bool {
		return user_can( $user_id, RoleManager::CAP_MANAGE_VACATION );
	}

	public function can_review( int $user_id ): bool {
		return $this->can_manage( $user_id ) || $this->is_department_head( $user_id );
	}

	public function is_department_head( int $user_id ): bool {
		return user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS )
			&& DepartmentHelper::user_heads_department( $user_id );
	}

	public function can_view_own( int $user_id ): bool {
		return user_can( $user_id, RoleManager::CAP_VIEW_OWN_VACATION );
	}

	/** @return array<string,string> */
	public function permission_types(): array {
		return RequestHelper::permission_types();
	}

	/** @return array<string,string> */
	public function holiday_work_types(): array {
		return RequestHelper::holiday_work_types();
	}

	/** @param array<string,mixed> $data @return Request|WP_Error */
	public function submit( int $user_id, array $data ): Request|WP_Error {
		if ( ! $this->can_view_own( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot submit requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! VacationHelper::is_etwa_employee( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Only employees can submit requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_payload( $data, $user_id );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$workflow  = ( new ApprovalService() )->initial_workflow_state( $user_id );
		$validated = array_merge( $validated, $workflow );

		$id      = $this->requests->create( $validated );
		$request = $this->requests->find( $id );

		$this->audit_log(
			'create',
			$request?->request_type ?? 'request',
			$id,
			null,
			$request?->to_array(),
			$user_id,
			$user_id,
			__( 'Submitted request', 'etwa' )
		);

		do_action( 'etwa_request_submitted', $id, $user_id, $request ? $this->format_request( $request, $user_id ) : null );

		return $request ?: new WP_Error( 'create_failed', __( 'Could not submit request.', 'etwa' ) );
	}

	/** @param array<string,mixed> $data @return Request|WP_Error */
	public function update_request( int $user_id, int $request_id, array $data ): Request|WP_Error {
		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( RequestHelper::STATUS_PENDING !== $request->status ) {
			return new WP_Error( 'invalid', __( 'Only pending requests can be updated.', 'etwa' ), array( 'status' => 409 ) );
		}

		if ( (int) $request->user_id !== $user_id && ! $this->can_manage( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot update this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		$data['request_type'] = $request->request_type;
		$validated            = $this->validate_payload( $data, (int) $request->user_id, true );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		unset( $validated['request_type'], $validated['user_id'], $validated['status'] );
		$this->requests->update( $request_id, $validated );
		$updated = $this->requests->find( $request_id );

		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
	}

	/** @param array<string,mixed> $filters @return array<string,mixed>|WP_Error */
	public function list_requests( int $viewer_id, array $filters, int $page = 1, int $per_page = 25 ): array|WP_Error {
		if ( $this->can_manage( $viewer_id ) ) {
			// HR sees all.
		} elseif ( $this->is_department_head( $viewer_id ) ) {
			$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
			if ( ! $dept_id ) {
				return new WP_Error( 'forbidden', __( 'You cannot view requests.', 'etwa' ), array( 'status' => 403 ) );
			}
			$filters['user_ids'] = DepartmentHelper::employee_ids_in_department( $dept_id );
		} elseif ( $this->can_view_own( $viewer_id ) ) {
			$filters['user_id'] = $viewer_id;
		} else {
			return new WP_Error( 'forbidden', __( 'You cannot view requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! empty( $filters['request_type'] ) && ! in_array( $filters['request_type'], RequestHelper::valid_types(), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid request type.', 'etwa' ), array( 'status' => 400 ) );
		}

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$items    = $this->requests->all( $filters, $per_page, $offset );

		return array(
			'items'    => array_map( fn( Request $r ) => $this->format_request( $r, $viewer_id ), $items ),
			'total'    => $this->requests->count( $filters ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	public function get_request( int $viewer_id, int $request_id ): Request|WP_Error|array {
		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_manage( $viewer_id ) && ! $this->is_department_head( $viewer_id ) && ( ! $this->can_view_own( $viewer_id ) || (int) $request->user_id !== $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		return $this->format_request( $request, $viewer_id, true );
	}

	/** @return true|WP_Error */
	public function cancel_request( int $user_id, int $request_id ): bool|WP_Error {
		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( (int) $request->user_id !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'You cannot cancel this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( RequestHelper::STATUS_PENDING !== $request->status ) {
			return new WP_Error( 'invalid', __( 'Only pending requests can be cancelled.', 'etwa' ), array( 'status' => 409 ) );
		}

		$this->requests->delete( $request_id );
		return true;
	}

	/** @return Request|WP_Error */
	public function review_request( int $reviewer_id, int $request_id, string $status, string $notes = '', string $approval_slot = '' ): Request|WP_Error {
		if ( ! $this->can_review( $reviewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot review requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! in_array( $status, RequestHelper::valid_statuses(), true ) || RequestHelper::STATUS_PENDING === $status ) {
			return new WP_Error( 'invalid_status', __( 'Invalid review status.', 'etwa' ), array( 'status' => 400 ) );
		}

		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( RequestHelper::STATUS_PENDING !== $request->status ) {
			return new WP_Error( 'invalid', __( 'This request has already been reviewed.', 'etwa' ), array( 'status' => 409 ) );
		}

		$old      = $request->to_array();
		$approval = new ApprovalService();

		if ( RequestHelper::STATUS_REJECTED === $status ) {
			$notes = trim( $notes );
			if ( '' === $notes ) {
				return new WP_Error( 'notes_required', __( 'A rejection reason is required.', 'etwa' ), array( 'status' => 400 ) );
			}

			if ( ! RequestApprovalHelper::can_user_reject( $reviewer_id, $request ) ) {
				return new WP_Error( 'forbidden', __( 'You cannot reject this request.', 'etwa' ), array( 'status' => 403 ) );
			}

			$update = $approval->record_rejection( $reviewer_id, $request, $notes ?: $request->notes );
			$this->requests->update( $request_id, $update );
			$updated = $this->requests->find( $request_id );

			if ( $updated ) {
				$this->notify_employee_rejected( $updated, $notes );
			}

			$audit_msg = ApprovalWorkflowHelper::STAGE_REJECTED_DEPT === ( $update['workflow_status'] ?? '' )
				? __( 'Department Head rejected request', 'etwa' )
				: __( 'HR rejected request', 'etwa' );

			$this->audit_log(
				$status,
				$request->request_type,
				$request_id,
				$old,
				$updated?->to_array(),
				$reviewer_id,
				$request->user_id,
				$audit_msg
			);

			do_action( 'etwa_request_rejected', $request_id, $request->user_id, $this->format_request( $updated ?: $request, $request->user_id ) );

			return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
		}

		$approval_slot = sanitize_key( $approval_slot );
		if ( '' === $approval_slot ) {
			$approval_slot = RequestApprovalHelper::reviewer_slot( $reviewer_id );
		}

		if ( ApprovalWorkflowHelper::SLOT_DEPARTMENT === $approval_slot ) {
			if ( ! RequestApprovalHelper::can_user_approve_department_slot( $reviewer_id, $request ) ) {
				return new WP_Error( 'forbidden', __( 'You cannot approve this request as Department Head.', 'etwa' ), array( 'status' => 403 ) );
			}

			$update = $approval->record_department_approval( $reviewer_id, $request, $notes ?: $request->notes );
			$this->requests->update( $request_id, $update );
			$updated = $this->requests->find( $request_id );

			$this->audit_log(
				'dept_approved',
				$request->request_type,
				$request_id,
				$old,
				$updated?->to_array(),
				$reviewer_id,
				$request->user_id,
				__( 'Department Head approved request', 'etwa' )
			);

			do_action( 'etwa_request_dept_approved', $request_id, $request->user_id, $updated ? $this->format_request( $updated, $reviewer_id ) : null );

			return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
		}

		if ( ! RequestApprovalHelper::can_user_approve_hr_slot( $reviewer_id, $request ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot approve this request.', 'etwa' ), array( 'status' => 403 ) );
		}

		$update = $approval->record_hr_approval( $reviewer_id, $notes ?: $request->notes );
		$this->requests->update( $request_id, $update );
		$updated = $this->requests->find( $request_id );

		if ( $updated ) {
			$this->notify_employee_approved( $updated );
			$this->maybe_create_holiday_timesheet( $updated, $reviewer_id );
		}

		$this->audit_log(
			$status,
			$request->request_type,
			$request_id,
			$old,
			$updated?->to_array(),
			$reviewer_id,
			$request->user_id,
			__( 'HR approved request', 'etwa' )
		);

		do_action( 'etwa_request_approved', $request_id, $request->user_id, $updated ? $this->format_request( $updated, $request->user_id ) : array() );

		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update request.', 'etwa' ) );
	}

	/** @return array{items:array<int,array<string,mixed>>} */
	public function unread_notifications( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::NOTIFICATION_META, true );
		if ( ! is_array( $stored ) ) {
			return array( 'items' => array() );
		}

		return array(
			'items' => array_values(
				array_filter(
					$stored,
					fn( $item ) => is_array( $item ) && empty( $item['read'] )
				)
			),
		);
	}

	public function dismiss_notifications( int $user_id ): void {
		delete_user_meta( $user_id, self::NOTIFICATION_META );
	}

	public function pending_counts( ?int $viewer_id = null ): array {
		$viewer_id = $viewer_id ?? get_current_user_id();
		$filters   = array( 'status' => RequestHelper::STATUS_PENDING );

		if ( $this->is_department_head( $viewer_id ) && ! $this->can_manage( $viewer_id ) ) {
			$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
			if ( ! $dept_id ) {
				return array(
					'wfh'          => 0,
					'permission'   => 0,
					'holiday_work' => 0,
					'total'        => 0,
				);
			}
			$filters['user_ids'] = DepartmentHelper::employee_ids_in_department( $dept_id );
		}

		return array(
			'wfh'          => $this->requests->count( array_merge( $filters, array( 'request_type' => RequestHelper::TYPE_WFH ) ) ),
			'permission'   => $this->requests->count( array_merge( $filters, array( 'request_type' => RequestHelper::TYPE_PERMISSION ) ) ),
			'holiday_work' => $this->requests->count( array_merge( $filters, array( 'request_type' => RequestHelper::TYPE_HOLIDAY_WORK ) ) ),
			'total'        => $this->requests->count( $filters ),
		);
	}

	/**
	 * @param array<int,array{id:int,source:string}> $items
	 * @return array{processed:int,failed:int,errors:array<int,string>}|WP_Error
	 */
	public function bulk_review( int $reviewer_id, array $items, string $status, string $notes = '', string $approval_slot = '' ): array|WP_Error {
		if ( ! $this->can_review( $reviewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot review requests.', 'etwa' ), array( 'status' => 403 ) );
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

	/** @return array<string,mixed> */
	public function format_request_for_viewer( Request $request, ?int $viewer_id = null ): array {
		return $this->format_request( $request, $viewer_id, true );
	}

	/** @return array<string,mixed> */
	private function format_request( Request $request, ?int $viewer_id = null, bool $detailed = false ): array {
		$user     = get_userdata( $request->user_id );
		$reviewer = $request->reviewed_by ? get_userdata( $request->reviewed_by ) : null;
		$approvers = RequestApprovalHelper::approver_names( $request );
		$extra     = RequestHelper::decode_extra_data( $request->extra_data );
		$dept      = DepartmentHelper::department_summary( $request->user_id );

		$approval  = new ApprovalService();
		$formatted = array_merge(
			$request->to_array(),
			array(
				'request_type_label'         => RequestHelper::type_label( $request->request_type ),
				'rejection_reason'           => $approval->rejection_reason( $request ),
				'permission_type_label'      => RequestHelper::permission_type_label( $request->permission_type ),
				'holiday_work_type'          => $extra['work_type'] ?? null,
				'holiday_work_type_label'    => RequestHelper::holiday_work_type_label( $extra['work_type'] ?? null ),
				'pickup_location'            => $extra['pickup_location'] ?? '',
				'destination'                => $extra['destination'] ?? '',
				'client_id'                  => isset( $extra['client_id'] ) ? (int) $extra['client_id'] : null,
				'client_name'                => $this->client_name_from_extra( $extra ),
				'task'                       => $extra['task'] ?? '',
				'timesheet_entry_id'         => isset( $extra['timesheet_entry_id'] ) ? (int) $extra['timesheet_entry_id'] : null,
				'time_range'                 => RequestHelper::format_time_range( $request->start_time, $request->end_time ),
				'employee_name'              => $user ? $user->display_name : '',
				'employee_email'             => $user ? $user->user_email : '',
				'reviewed_by_name'           => $reviewer ? $reviewer->display_name : '',
				'department'                 => $dept,
				'department_name'            => $dept['name'] ?? '',
				'dept_status_label'          => ApprovalWorkflowHelper::dept_status_label( $request->dept_approved_by, $request->workflow_status ),
				'hr_status_label'            => ApprovalWorkflowHelper::hr_status_label( $request->hr_approved_by, $request->status, $request->workflow_status ),
				'approved_by'                => RequestApprovalHelper::approved_by_label( $request ),
				'approved_at'                => RequestApprovalHelper::approved_at_label( $request ),
				'dual_approval_enabled'      => RequestApprovalHelper::is_dual_approval_enabled(),
				'hr_approved'                => RequestApprovalHelper::has_hr_approval( $request ),
				'dept_approved'              => RequestApprovalHelper::has_department_approval( $request ),
				'owner_approved'             => false,
				'status_label'               => RequestApprovalHelper::status_label( $request ),
				'hr_approved_by_name'          => $approvers['hr_approved_by_name'],
				'dept_approved_by_name'      => $approvers['dept_approved_by_name'],
				'owner_approved_by_name'     => '',
				'approval_timeline'          => RequestApprovalHelper::timeline( $request ),
				'created_at_label'           => DateHelper::format_datetime( $request->created_at ),
			),
			$this->pending_meta( $request )
		);

		if ( null !== $viewer_id ) {
			$formatted['can_approve_as_hr']         = RequestApprovalHelper::can_user_approve_hr_slot( $viewer_id, $request );
			$formatted['can_approve_as_department'] = RequestApprovalHelper::can_user_approve_department_slot( $viewer_id, $request );
			$formatted['can_approve_as_owner']      = false;
			$formatted['can_reject']                = RequestApprovalHelper::can_user_reject( $viewer_id, $request );
		}

		if ( $detailed ) {
			$formatted['approval_history'] = $this->build_approval_history( $request );
			$formatted['attached_files']   = array();
		}

		return $formatted;
	}

	/** @return array<int,array<string,mixed>> */
	private function build_approval_history( Request $request ): array {
		return RequestApprovalHelper::timeline( $request );
	}

	/** @return array{pending_age_hours:int,is_aging:bool} */
	private function pending_meta( Request $request ): array {
		if ( RequestHelper::STATUS_PENDING !== $request->status ) {
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
	private function validate_payload( array $data, int $user_id, bool $is_update = false ): array|WP_Error {
		$type = sanitize_key( (string) ( $data['request_type'] ?? '' ) );

		if ( ! in_array( $type, RequestHelper::valid_types(), true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid request type.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $type, RequestHelper::exposed_types(), true ) && ! $is_update ) {
			return new WP_Error( 'invalid_type', __( 'This request type is not available.', 'etwa' ), array( 'status' => 400 ) );
		}

		$request_date = sanitize_text_field( (string) ( $data['request_date'] ?? '' ) );
		$work_date    = sanitize_text_field( (string) ( $data['work_date'] ?? $data['date'] ?? '' ) );
		$start_time   = sanitize_text_field( (string) ( $data['start_time'] ?? '' ) );
		$end_time     = sanitize_text_field( (string) ( $data['end_time'] ?? '' ) );
		$reason       = sanitize_textarea_field( (string) ( $data['reason'] ?? '' ) );
		$notes        = sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) );
		$extra        = array();

		if ( ! $request_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $request_date ) ) {
			return new WP_Error( 'invalid_date', __( 'Request date is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( RequestHelper::TYPE_WFH === $type ) {
			if ( ! $work_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $work_date ) ) {
				return new WP_Error( 'invalid_date', __( 'Work date is required.', 'etwa' ), array( 'status' => 400 ) );
			}
		} elseif ( RequestHelper::TYPE_PERMISSION === $type ) {
			$work_date = $work_date ?: $request_date;
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $work_date ) ) {
				return new WP_Error( 'invalid_date', __( 'Date is required.', 'etwa' ), array( 'status' => 400 ) );
			}
		} elseif ( RequestHelper::TYPE_HOLIDAY_WORK === $type ) {
			$work_date = $work_date ?: $request_date;
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $work_date ) ) {
				return new WP_Error( 'invalid_date', __( 'Work date is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( ! $start_time || ! $end_time ) {
				return new WP_Error( 'invalid_time', __( 'Start and end times are required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$work_type = sanitize_key( (string) ( $data['work_type'] ?? '' ) );
			if ( ! array_key_exists( $work_type, RequestHelper::holiday_work_types() ) ) {
				return new WP_Error( 'invalid_work_type', __( 'Invalid work type.', 'etwa' ), array( 'status' => 400 ) );
			}
			$client_id = (int) ( $data['client_id'] ?? 0 );
			$client    = $this->clients->find( $client_id );
			if ( ! $client || ! $client->is_active || $this->clients->is_break_client( $client_id, $client ) ) {
				return new WP_Error( 'invalid_client', __( 'Please select a valid client.', 'etwa' ), array( 'status' => 400 ) );
			}
			$task = sanitize_text_field( (string) ( $data['task'] ?? '' ) );
			if ( '' === trim( $task ) ) {
				return new WP_Error( 'invalid_task', __( 'Task is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$extra = array(
				'work_type'  => $work_type,
				'client_id'  => $client_id,
				'task'       => $task,
			);
		}

		if ( $start_time && ! RequestHelper::is_valid_time( $start_time ) ) {
			return new WP_Error( 'invalid_time', __( 'Invalid start time.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $end_time && ! RequestHelper::is_valid_time( $end_time ) ) {
			return new WP_Error( 'invalid_time', __( 'Invalid end time.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $start_time && $end_time && $start_time >= $end_time ) {
			return new WP_Error( 'invalid_time', __( 'End time must be after start time.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( '' === trim( $reason ) ) {
			return new WP_Error( 'invalid_reason', __( 'Reason is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$permission_type = null;
		if ( RequestHelper::TYPE_PERMISSION === $type ) {
			$permission_type = sanitize_key( (string) ( $data['permission_type'] ?? '' ) );
			if ( ! array_key_exists( $permission_type, RequestHelper::permission_types() ) ) {
				return new WP_Error( 'invalid_permission_type', __( 'Invalid permission type.', 'etwa' ), array( 'status' => 400 ) );
			}
		}

		$payload = array(
			'request_type'    => $type,
			'user_id'         => $user_id,
			'request_date'    => $request_date,
			'work_date'       => $work_date ?: null,
			'start_time'      => $start_time ?: null,
			'end_time'        => $end_time ?: null,
			'permission_type' => $permission_type,
			'reason'          => $reason,
			'notes'           => $notes,
			'status'          => RequestHelper::STATUS_PENDING,
		);

		if ( ! empty( $extra ) ) {
			$payload['extra_data'] = wp_json_encode( $extra );
		}

		return $payload;
	}

	/** @param array<string,mixed> $extra */
	private function client_name_from_extra( array $extra ): string {
		$client_id = isset( $extra['client_id'] ) ? (int) $extra['client_id'] : 0;
		if ( ! $client_id ) {
			return '';
		}
		$client = $this->clients->find( $client_id );
		return $client ? $client->name : '';
	}

	private function maybe_create_holiday_timesheet( Request $request, int $reviewer_id ): void {
		if ( RequestHelper::TYPE_HOLIDAY_WORK !== $request->request_type || RequestHelper::STATUS_APPROVED !== $request->status ) {
			return;
		}

		$extra = RequestHelper::decode_extra_data( $request->extra_data );
		if ( ! empty( $extra['timesheet_entry_id'] ) ) {
			return;
		}

		if ( ! $request->work_date || ! $request->start_time || ! $request->end_time ) {
			return;
		}

		$client_id = (int) ( $extra['client_id'] ?? 0 );
		$task      = sanitize_text_field( (string) ( $extra['task'] ?? '' ) );
		if ( ! $client_id || '' === trim( $task ) ) {
			return;
		}

		$timesheet = $this->timesheets->get_or_create_for_week( (int) $request->user_id, $request->work_date );
		$entry     = $this->timesheets->create_from_holiday_request(
			$reviewer_id,
			array(
				'timesheet_id'  => (int) $timesheet->id,
				'entry_date'    => $request->work_date,
				'start_time'    => $request->start_time,
				'end_time'      => $request->end_time,
				'client_id'     => $client_id,
				'task'          => $task,
				'notes'         => $request->notes,
				'audit_reason'  => sprintf(
					/* translators: %d: request id */
					__( 'Auto-created from approved holiday work request #%d', 'etwa' ),
					$request->id
				),
			)
		);

		if ( is_wp_error( $entry ) || ! $entry->id ) {
			return;
		}

		$extra['timesheet_entry_id'] = (int) $entry->id;
		$this->requests->update(
			$request->id,
			array( 'extra_data' => wp_json_encode( $extra ) )
		);

		$this->audit_log(
			'auto_timesheet',
			$request->request_type,
			$request->id,
			null,
			array(
				'timesheet_entry_id' => (int) $entry->id,
				'timesheet_id'       => (int) $timesheet->id,
				'created_from'       => TimesheetService::CREATED_FROM_HOLIDAY_REQUEST,
			),
			$reviewer_id,
			$request->user_id,
			__( 'Automatic timesheet entry created from approved holiday work request', 'etwa' )
		);
	}

	private function notify_employee_approved( Request $request ): void {
		$notification = array(
			'type'                => 'request_approved',
			'request_type'        => $request->request_type,
			'request_type_label'  => RequestHelper::type_label( $request->request_type ),
			'work_date'           => $request->work_date,
			'time_range'          => RequestHelper::format_time_range( $request->start_time, $request->end_time ),
			'approved_by'         => RequestApprovalHelper::approved_by_label( $request ),
			'approved_at'         => RequestApprovalHelper::approved_at_label( $request ),
			'read'                => false,
			'created_at'          => current_time( 'mysql' ),
		);

		$this->push_notification( $request->user_id, $notification );
		do_action( 'etwa_request_approved', $request->id, $request->user_id, $notification );
	}

	private function notify_employee_rejected( Request $request, string $notes ): void {
		$notification = array(
			'type'               => 'request_rejected',
			'request_type'       => $request->request_type,
			'request_type_label' => RequestHelper::type_label( $request->request_type ),
			'work_date'          => $request->work_date,
			'time_range'         => RequestHelper::format_time_range( $request->start_time, $request->end_time ),
			'notes'              => $notes,
			'rejection_reason'   => $notes,
			'read'               => false,
			'created_at'         => current_time( 'mysql' ),
		);

		$this->push_notification( $request->user_id, $notification );
		do_action( 'etwa_request_rejected', $request->id, $request->user_id, $notification );
	}

	/** @param array<string,mixed> $notification */
	private function push_notification( int $user_id, array $notification ): void {
		$stored = get_user_meta( $user_id, self::NOTIFICATION_META, true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored[] = $notification;
		update_user_meta( $user_id, self::NOTIFICATION_META, $stored );
	}

	/** @param array<string,mixed>|null $old @param array<string,mixed>|null $new */
	private function audit_log(
		string $action,
		string $entity_type,
		int $entity_id,
		?array $old,
		?array $new,
		int $user_id,
		int $employee_id,
		string $reason
	): void {
		$this->audit->log(
			$action,
			$entity_type,
			$entity_id,
			$old,
			$new,
			$user_id,
			array(
				'employee_id' => $employee_id,
				'edit_reason' => $reason,
			)
		);
	}
}
