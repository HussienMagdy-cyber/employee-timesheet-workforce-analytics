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

		wp_mail(
			$hr,
			__( 'Timesheet Submitted for Review', 'etwa' ),
			sprintf(
				/* translators: 1: employee name, 2: timesheet id */
				__( '%1$s submitted timesheet #%2$d for approval.', 'etwa' ),
				$user ? $user->display_name : $user_id,
				$timesheet_id
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

			wp_mail(
				$user->user_email,
				$subject,
				sprintf(
					/* translators: 1: week start, 2: week end, 3: status */
					__( 'Your timesheet for %1$s – %2$s has been %3$s.', 'etwa' ),
					$timesheet->period_start,
					$timesheet->period_end,
					$status
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
			wp_mail(
				$user->user_email,
				'approved' === $status
					? __( 'Your Overtime Was Approved', 'etwa' )
					: __( 'Your Overtime Was Rejected', 'etwa' ),
				sprintf(
					/* translators: 1: date, 2: status */
					__( 'Your overtime entry on %1$s has been %2$s.', 'etwa' ),
					$record->entry_date,
					$status
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

		wp_mail(
			$user->user_email,
			__( 'Leave Approved', 'etwa' ),
			sprintf(
				/* translators: 1: leave type, 2: start, 3: end, 4: days, 5: remaining */
				__( '%1$s approved: %2$s – %3$s (%4$s days). Remaining balance: %5$s days.', 'etwa' ),
				(string) ( $notification['leave_type_name'] ?? '' ),
				(string) ( $notification['start_date'] ?? '' ),
				(string) ( $notification['end_date'] ?? '' ),
				(string) ( $notification['days'] ?? '' ),
				(string) ( $notification['remaining'] ?? '' )
			)
		);

		do_action( 'etwa_notification', 'vacation_approved', compact( 'record_id', 'user_id', 'notification' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_vacation_requested( int $request_id, int $user_id, ?array $payload ): void {
		$user = get_userdata( $user_id );
		$head_id = DepartmentHelper::get_department_head_id( $user_id );

		if ( $head_id ) {
			$head = get_userdata( $head_id );
			if ( $head && $head->user_email ) {
				wp_mail(
					$head->user_email,
					__( 'Vacation Request — Department Approval Required', 'etwa' ),
					sprintf(
						/* translators: 1: employee name, 2: start date, 3: end date, 4: days */
						__( '%1$s submitted a vacation request: %2$s – %3$s (%4$s days). Please review as Department Head.', 'etwa' ),
						$user ? $user->display_name : (string) $user_id,
						(string) ( $payload['start_date'] ?? '' ),
						(string) ( $payload['end_date'] ?? '' ),
						(string) ( $payload['days'] ?? '' )
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

		wp_mail(
			$hr,
			__( 'Vacation Request Submitted', 'etwa' ),
			sprintf(
				/* translators: 1: employee name, 2: start date, 3: end date, 4: days */
				__( '%1$s submitted a vacation request: %2$s – %3$s (%4$s days). Please review in Vacations → Requests.', 'etwa' ),
				$user ? $user->display_name : (string) $user_id,
				(string) ( $payload['start_date'] ?? '' ),
				(string) ( $payload['end_date'] ?? '' ),
				(string) ( $payload['days'] ?? '' )
			)
		);

		do_action( 'etwa_notification', 'vacation_requested', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_vacation_dept_approved( int $request_id, int $user_id, ?array $payload ): void {
		$user = get_userdata( $user_id );
		$hr   = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );

		wp_mail(
			$hr,
			__( 'Vacation Request — HR Approval Required', 'etwa' ),
			sprintf(
				/* translators: 1: employee name, 2: start date, 3: end date */
				__( 'Department Head approved %1$s\'s vacation request (%2$s – %3$s). Please review in Vacations → Requests.', 'etwa' ),
				$user ? $user->display_name : (string) $user_id,
				(string) ( $payload['start_date'] ?? '' ),
				(string) ( $payload['end_date'] ?? '' )
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

		wp_mail(
			$user->user_email,
			__( 'Leave Request Rejected', 'etwa' ),
			$body
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
				wp_mail(
					$head->user_email,
					$subject . ' — ' . __( 'Department Approval Required', 'etwa' ),
					$body . ' ' . __( 'Please review as Department Head.', 'etwa' )
				);
				do_action( 'etwa_notification', 'request_submitted_dept', compact( 'request_id', 'user_id', 'payload', 'head_id' ) );
				return;
			}
		}

		$hr = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );
		wp_mail( $hr, $subject, $body . ' ' . __( 'Please review in Requests.', 'etwa' ) );

		do_action( 'etwa_notification', 'request_submitted', compact( 'request_id', 'user_id', 'payload' ) );
	}

	/** @param array<string,mixed>|null $payload */
	public function on_request_dept_approved( int $request_id, int $user_id, ?array $payload ): void {
		$user = get_userdata( $user_id );
		$hr   = (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) );
		$type = (string) ( $payload['request_type_label'] ?? $payload['request_type'] ?? '' );

		wp_mail(
			$hr,
			__( 'Request — HR Approval Required', 'etwa' ),
			sprintf(
				/* translators: 1: request type, 2: employee name, 3: work date */
				__( 'Department Head approved %1$s request from %2$s for %3$s. Please review in Requests.', 'etwa' ),
				$type,
				$user ? $user->display_name : (string) $user_id,
				(string) ( $payload['work_date'] ?? $payload['request_date'] ?? '' )
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

		wp_mail(
			$user->user_email,
			$subject,
			sprintf(
				/* translators: 1: request type, 2: work date, 3: approver */
				__( 'Your %1$s request for %2$s was approved by %3$s.', 'etwa' ),
				(string) ( $notification['request_type_label'] ?? '' ),
				(string) ( $notification['work_date'] ?? '' ),
				(string) ( $notification['approved_by'] ?? '' )
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

		wp_mail(
			$user->user_email,
			$subject,
			$body
		);

		do_action( 'etwa_notification', 'request_rejected', compact( 'request_id', 'user_id', 'notification' ) );
	}
}
