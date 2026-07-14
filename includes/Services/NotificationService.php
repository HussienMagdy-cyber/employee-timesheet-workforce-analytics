<?php
/**
 * In-app and email notifications.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DepartmentHelper;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NotificationService {

	public function __construct(
		private SettingsRepository $settings = new SettingsRepository(),
		private MailService $mail = new MailService(),
	) {
		add_action( 'etwa_timesheet_submitted', array( $this, 'on_timesheet_submitted' ), 10, 2 );
		add_action( 'etwa_timesheet_reviewed', array( $this, 'on_timesheet_reviewed' ), 10, 3 );
		add_action( 'etwa_overtime_reviewed', array( $this, 'on_overtime_reviewed' ), 10, 3 );
		add_action( 'etwa_vacation_approved', array( $this, 'on_vacation_approved' ), 10, 3 );
		add_action( 'etwa_vacation_requested', array( $this, 'on_vacation_requested' ), 10, 3 );
		add_action( 'etwa_vacation_dept_approved', array( $this, 'on_vacation_dept_approved' ), 10, 3 );
		add_action( 'etwa_vacation_hr_approved', array( $this, 'on_vacation_hr_approved' ), 10, 3 );
		add_action( 'etwa_vacation_rejected', array( $this, 'on_vacation_rejected' ), 10, 3 );
		add_action( 'etwa_request_submitted', array( $this, 'on_request_submitted' ), 10, 3 );
		add_action( 'etwa_request_dept_approved', array( $this, 'on_request_dept_approved' ), 10, 3 );
		add_action( 'etwa_request_approved', array( $this, 'on_request_approved' ), 10, 3 );
		add_action( 'etwa_request_rejected', array( $this, 'on_request_rejected' ), 10, 3 );
	}

	public function on_timesheet_submitted( int $timesheet_id, int $user_id ): void {
		$user = get_userdata( $user_id );
		$hr   = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );

		$this->mail->send(
			array(
				'to'               => $hr,
				'subject'          => __( 'Timesheet Submitted for Review', 'etwa' ),
				'content'          => sprintf(
					/* translators: 1: employee name, 2: timesheet id */
					__( '%1$s submitted timesheet #%2$d for approval.', 'etwa' ),
					$user ? $user->display_name : (string) $user_id,
					$timesheet_id
				),
				'sender_user_id'   => $user_id,
				'recipient_user_id'=> null,
				'placeholders'     => array(
					'employee_name' => $user ? $user->display_name : (string) $user_id,
				),
			)
		);
	}

	public function on_timesheet_reviewed( int $timesheet_id, string $status, int $reviewer_id ): void {
		$timesheet = ( new \ETWA\Repositories\TimesheetRepository() )->find( $timesheet_id );
		if ( ! $timesheet ) {
			return;
		}

		$user = get_userdata( $timesheet->user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		if ( in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			$subject = 'approved' === $status
				? __( 'Your Timesheet Was Approved', 'etwa' )
				: __( 'Your Timesheet Needs Changes', 'etwa' );

			$this->mail->send(
				array(
					'to'               => $user->user_email,
					'subject'          => $subject,
					'content'          => sprintf(
						/* translators: 1: week start, 2: week end, 3: status */
						__( 'Your timesheet for %1$s – %2$s has been %3$s.', 'etwa' ),
						$timesheet->period_start,
						$timesheet->period_end,
						$status
					),
					'sender_user_id'   => $reviewer_id,
					'recipient_user_id'=> (int) $user->ID,
					'placeholders'     => array(
						'request_type'   => __( 'Timesheet', 'etwa' ),
						'request_status' => $status,
					),
				)
			);
		}

		do_action( 'etwa_notification', 'timesheet_reviewed', compact( 'timesheet_id', 'status', 'reviewer_id' ) );
	}

	public function on_overtime_reviewed( int $overtime_id, string $status, int $reviewer_id ): void {
		$record = ( new \ETWA\Repositories\OvertimeRepository() )->find( $overtime_id );
		if ( ! $record ) {
			return;
		}

		$user = get_userdata( $record->user_id );
		if ( $user && $user->user_email && in_array( $status, array( 'approved', 'rejected' ), true ) ) {
			$this->mail->send(
				array(
					'to'               => $user->user_email,
					'subject'          => 'approved' === $status
						? __( 'Your Overtime Was Approved', 'etwa' )
						: __( 'Your Overtime Was Rejected', 'etwa' ),
					'content'          => sprintf(
						/* translators: 1: date, 2: status */
						__( 'Your overtime entry on %1$s has been %2$s.', 'etwa' ),
						$record->entry_date,
						$status
					),
					'sender_user_id'   => $reviewer_id,
					'recipient_user_id'=> (int) $user->ID,
					'placeholders'     => array(
						'request_type'   => __( 'Overtime', 'etwa' ),
						'request_status' => $status,
					),
				)
			);
		}

		do_action( 'etwa_notification', 'overtime_reviewed', compact( 'overtime_id', 'status', 'reviewer_id' ) );
	}

	/** @param array<string,mixed> $notification */
	public function on_vacation_approved( int $record_id, int $user_id, array $notification ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$this->mail->send(
			array(
				'to'               => $user->user_email,
				'subject'          => __( 'Leave Approved', 'etwa' ),
				'content'          => sprintf(
					/* translators: 1: leave type, 2: start, 3: end, 4: days, 5: remaining */
					__( '%1$s approved: %2$s – %3$s (%4$s days). Remaining balance: %5$s days.', 'etwa' ),
					(string) ( $notification['leave_type_name'] ?? '' ),
					(string) ( $notification['start_date'] ?? '' ),
					(string) ( $notification['end_date'] ?? '' ),
					(string) ( $notification['days'] ?? '' ),
					(string) ( $notification['remaining'] ?? '' )
				),
				'sender_user_id'   => $this->reviewer_sender_id( $notification ),
				'recipient_user_id'=> $user_id,
				'placeholders'     => array(
					'request_type'   => (string) ( $notification['leave_type_name'] ?? __( 'Leave', 'etwa' ) ),
					'request_status' => __( 'Approved', 'etwa' ),
				),
			)
		);

		do_action( 'etwa_notification', 'vacation_approved', compact( 'record_id', 'user_id', 'notification' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_vacation_requested( int $request_id, int $user_id, ?array $payload ): void {
		$user    = get_userdata( $user_id );
		$head_id = DepartmentHelper::get_department_head_id( $user_id );

		if ( $head_id ) {
			$head = get_userdata( $head_id );
			if ( $head && $head->user_email ) {
				$this->mail->send(
					array(
						'to'               => $head->user_email,
						'subject'          => __( 'Vacation Request — Department Approval Required', 'etwa' ),
						'content'          => sprintf(
							/* translators: 1: employee name, 2: start date, 3: end date, 4: days */
							__( '%1$s submitted a vacation request: %2$s – %3$s (%4$s days). Please review as Department Head.', 'etwa' ),
							$user ? $user->display_name : (string) $user_id,
							(string) ( $payload['start_date'] ?? '' ),
							(string) ( $payload['end_date'] ?? '' ),
							(string) ( $payload['days'] ?? '' )
						),
						'sender_user_id'   => $user_id,
						'recipient_user_id'=> $head_id,
						'placeholders'     => array(
							'request_type'   => __( 'Vacation Request', 'etwa' ),
							'request_status' => __( 'Pending Department Approval', 'etwa' ),
							'approval_link'  => admin_url( 'admin.php?page=etwa-vacations' ),
						),
					)
				);
				do_action( 'etwa_notification', 'vacation_requested_dept', compact( 'request_id', 'user_id', 'payload', 'head_id' ) );
				return;
			}
		}

		$this->notify_hr_vacation_request( $user, $user_id, $payload, $request_id );
	}

	/** @param array<string,mixed>|null $payload */
	private function notify_hr_vacation_request( ?\WP_User $user, int $user_id, ?array $payload, int $request_id ): void {
		$hr = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );

		$this->mail->send(
			array(
				'to'             => $hr,
				'subject'        => __( 'Vacation Request Submitted', 'etwa' ),
				'content'        => sprintf(
					/* translators: 1: employee name, 2: start date, 3: end date, 4: days */
					__( '%1$s submitted a vacation request: %2$s – %3$s (%4$s days). Please review in Vacations → Requests.', 'etwa' ),
					$user ? $user->display_name : (string) $user_id,
					(string) ( $payload['start_date'] ?? '' ),
					(string) ( $payload['end_date'] ?? '' ),
					(string) ( $payload['days'] ?? '' )
				),
				'sender_user_id' => $user_id,
				'placeholders'   => array(
					'employee_name'  => $user ? $user->display_name : (string) $user_id,
					'request_type'   => __( 'Vacation Request', 'etwa' ),
					'request_status' => __( 'Pending HR Approval', 'etwa' ),
					'approval_link'  => admin_url( 'admin.php?page=etwa-vacations' ),
				),
			)
		);

		do_action( 'etwa_notification', 'vacation_requested', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_vacation_dept_approved( int $request_id, int $user_id, ?array $payload ): void {
		$user = get_userdata( $user_id );
		$hr   = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );

		$this->mail->send(
			array(
				'to'             => $hr,
				'subject'        => __( 'Vacation Request — HR Approval Required', 'etwa' ),
				'content'        => sprintf(
					/* translators: 1: employee name, 2: start date, 3: end date */
					__( 'Department Head approved %1$s\'s vacation request (%2$s – %3$s). Please review in Vacations → Requests.', 'etwa' ),
					$user ? $user->display_name : (string) $user_id,
					(string) ( $payload['start_date'] ?? '' ),
					(string) ( $payload['end_date'] ?? '' )
				),
				'sender_user_id' => get_current_user_id() ?: null,
				'placeholders'   => array(
					'employee_name'  => $user ? $user->display_name : (string) $user_id,
					'request_type'   => __( 'Vacation Request', 'etwa' ),
					'request_status' => __( 'Pending HR Approval', 'etwa' ),
					'approval_link'  => admin_url( 'admin.php?page=etwa-vacations' ),
				),
			)
		);

		do_action( 'etwa_notification', 'vacation_dept_approved', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_vacation_hr_approved( int $request_id, int $user_id, ?array $payload ): void {
		// Final employee notification handled by etwa_vacation_approved via create_record.
		do_action( 'etwa_notification', 'vacation_hr_approved', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed> $notification */
	public function on_vacation_rejected( int $request_id, int $user_id, array $notification ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$reason = trim( (string) ( $notification['rejection_reason'] ?? $notification['notes'] ?? '' ) );
		$body   = sprintf(
			/* translators: 1: leave type, 2: start, 3: end */
			__( 'Your %1$s request for %2$s – %3$s was rejected.', 'etwa' ),
			(string) ( $notification['leave_type_name'] ?? '' ),
			(string) ( $notification['start_date'] ?? '' ),
			(string) ( $notification['end_date'] ?? '' )
		);
		if ( '' !== $reason ) {
			$body .= "\n\n" . sprintf(
				/* translators: %s: rejection reason */
				__( 'Rejection reason: %s', 'etwa' ),
				$reason
			);
		}

		$this->mail->send(
			array(
				'to'               => $user->user_email,
				'subject'          => __( 'Leave Request Rejected', 'etwa' ),
				'content'          => $body,
				'sender_user_id'   => $this->reviewer_sender_id( $notification ),
				'recipient_user_id'=> $user_id,
				'placeholders'     => array(
					'request_type'   => (string) ( $notification['leave_type_name'] ?? __( 'Leave', 'etwa' ) ),
					'request_status' => __( 'Rejected', 'etwa' ),
				),
			)
		);

		do_action( 'etwa_notification', 'vacation_rejected', compact( 'request_id', 'user_id', 'notification' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_request_submitted( int $request_id, int $user_id, ?array $payload ): void {
		$user     = get_userdata( $user_id );
		$type     = (string) ( $payload['request_type_label'] ?? $payload['request_type'] ?? '' );
		$type_key = sanitize_key( (string) ( $payload['request_type'] ?? '' ) );
		$head_id  = DepartmentHelper::get_department_head_id( $user_id );

		$subject = match ( $type_key ) {
			'holiday_work' => __( 'Work on Holiday Request Submitted', 'etwa' ),
			default        => __( 'New Employee Request Submitted', 'etwa' ),
		};

		$body = sprintf(
			/* translators: 1: employee name, 2: request type, 3: work date */
			__( '%1$s submitted a %2$s request for %3$s.', 'etwa' ),
			$user ? $user->display_name : (string) $user_id,
			$type,
			(string) ( $payload['work_date'] ?? $payload['request_date'] ?? '' )
		);

		if ( $head_id ) {
			$head = get_userdata( $head_id );
			if ( $head && $head->user_email ) {
				$this->mail->send(
					array(
						'to'               => $head->user_email,
						'subject'          => $subject . ' — ' . __( 'Department Approval Required', 'etwa' ),
						'content'          => $body . ' ' . __( 'Please review as Department Head.', 'etwa' ),
						'sender_user_id'   => $user_id,
						'recipient_user_id'=> $head_id,
						'placeholders'     => array(
							'request_type'   => $type,
							'request_status' => __( 'Pending Department Approval', 'etwa' ),
							'approval_link'  => admin_url( 'admin.php?page=etwa-requests' ),
						),
					)
				);
				do_action( 'etwa_notification', 'request_submitted_dept', compact( 'request_id', 'user_id', 'payload', 'head_id' ) );
				return;
			}
		}

		$hr = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );
		$this->mail->send(
			array(
				'to'             => $hr,
				'subject'        => $subject,
				'content'        => $body . ' ' . __( 'Please review in Requests.', 'etwa' ),
				'sender_user_id' => $user_id,
				'placeholders'   => array(
					'employee_name'  => $user ? $user->display_name : (string) $user_id,
					'request_type'   => $type,
					'request_status' => __( 'Pending HR Approval', 'etwa' ),
					'approval_link'  => admin_url( 'admin.php?page=etwa-requests' ),
				),
			)
		);

		do_action( 'etwa_notification', 'request_submitted', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_request_dept_approved( int $request_id, int $user_id, ?array $payload ): void {
		$user = get_userdata( $user_id );
		$hr   = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );
		$type = (string) ( $payload['request_type_label'] ?? $payload['request_type'] ?? '' );

		$this->mail->send(
			array(
				'to'             => $hr,
				'subject'        => __( 'Request — HR Approval Required', 'etwa' ),
				'content'        => sprintf(
					/* translators: 1: request type, 2: employee name, 3: work date */
					__( 'Department Head approved %1$s request from %2$s for %3$s. Please review in Requests.', 'etwa' ),
					$type,
					$user ? $user->display_name : (string) $user_id,
					(string) ( $payload['work_date'] ?? $payload['request_date'] ?? '' )
				),
				'sender_user_id' => get_current_user_id() ?: null,
				'placeholders'   => array(
					'employee_name'  => $user ? $user->display_name : (string) $user_id,
					'request_type'   => $type,
					'request_status' => __( 'Pending HR Approval', 'etwa' ),
					'approval_link'  => admin_url( 'admin.php?page=etwa-requests' ),
				),
			)
		);

		do_action( 'etwa_notification', 'request_dept_approved', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed> $notification */
	public function on_request_approved( int $request_id, int $user_id, array $notification ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$type_key = sanitize_key( (string) ( $notification['request_type'] ?? '' ) );
		$subject  = match ( $type_key ) {
			'holiday_work' => __( 'Work on Holiday Request Approved', 'etwa' ),
			default        => __( 'Request Approved', 'etwa' ),
		};

		$this->mail->send(
			array(
				'to'               => $user->user_email,
				'subject'          => $subject,
				'content'          => sprintf(
					/* translators: 1: request type, 2: work date, 3: approver */
					__( 'Your %1$s request for %2$s was approved by %3$s.', 'etwa' ),
					(string) ( $notification['request_type_label'] ?? '' ),
					(string) ( $notification['work_date'] ?? '' ),
					(string) ( $notification['approved_by'] ?? '' )
				),
				'sender_user_id'   => $this->reviewer_sender_id( $notification ),
				'recipient_user_id'=> $user_id,
				'placeholders'     => array(
					'request_type'   => (string) ( $notification['request_type_label'] ?? '' ),
					'request_status' => __( 'Approved', 'etwa' ),
				),
			)
		);

		do_action( 'etwa_notification', 'request_approved', compact( 'request_id', 'user_id', 'notification' ) );
	}

	/** @param array<string,mixed> $notification */
	public function on_request_rejected( int $request_id, int $user_id, array $notification ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$type_key = sanitize_key( (string) ( $notification['request_type'] ?? '' ) );
		$subject  = match ( $type_key ) {
			'holiday_work' => __( 'Work on Holiday Request Rejected', 'etwa' ),
			default        => __( 'Request Rejected', 'etwa' ),
		};

		$reason = trim( (string) ( $notification['rejection_reason'] ?? $notification['notes'] ?? '' ) );
		$body   = sprintf(
			/* translators: 1: request type, 2: work date */
			__( 'Your %1$s request for %2$s was rejected.', 'etwa' ),
			(string) ( $notification['request_type_label'] ?? '' ),
			(string) ( $notification['work_date'] ?? '' )
		);
		if ( '' !== $reason ) {
			$body .= "\n\n" . sprintf(
				/* translators: %s: rejection reason */
				__( 'Rejection reason: %s', 'etwa' ),
				$reason
			);
		}

		$this->mail->send(
			array(
				'to'               => $user->user_email,
				'subject'          => $subject,
				'content'          => $body,
				'sender_user_id'   => $this->reviewer_sender_id( $notification ),
				'recipient_user_id'=> $user_id,
				'placeholders'     => array(
					'request_type'   => (string) ( $notification['request_type_label'] ?? '' ),
					'request_status' => __( 'Rejected', 'etwa' ),
				),
			)
		);

		do_action( 'etwa_notification', 'request_rejected', compact( 'request_id', 'user_id', 'notification' ) );
	}

	/** @param array<string,mixed> $notification */
	private function reviewer_sender_id( array $notification ): ?int {
		if ( ! empty( $notification['reviewed_by'] ) ) {
			return absint( $notification['reviewed_by'] );
		}
		if ( ! empty( $notification['hr_approved_by'] ) ) {
			return absint( $notification['hr_approved_by'] );
		}
		if ( ! empty( $notification['dept_approved_by'] ) ) {
			return absint( $notification['dept_approved_by'] );
		}
		if ( ! empty( $notification['created_by'] ) ) {
			return absint( $notification['created_by'] );
		}

		$current = get_current_user_id();
		return $current > 0 ? $current : null;
	}
}
