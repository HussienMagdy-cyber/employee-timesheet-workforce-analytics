<?php
/**
 * Department analytics and dashboard data.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\ApprovalWorkflowHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\RequestHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Repositories\DepartmentRepository;
use ETWA\Repositories\RequestRepository;
use ETWA\Repositories\TimesheetRepository;
use ETWA\Repositories\VacationRequestRepository;
use ETWA\Repositories\VacationRecordRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentAnalyticsService {

	public function __construct(
		private DepartmentRepository $departments = new DepartmentRepository(),
		private RequestRepository $requests = new RequestRepository(),
		private VacationRequestRepository $vacation_requests = new VacationRequestRepository(),
		private VacationRecordRepository $vacation_records = new VacationRecordRepository(),
		private TimesheetRepository $timesheets = new TimesheetRepository(),
		private ApprovalService $approval = new ApprovalService(),
	) {}

	/** @return array<string,mixed>|WP_Error */
	public function dashboard( int $viewer_id ): array|WP_Error {
		if ( ! user_can( $viewer_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view the department dashboard.', 'etwa' ), array( 'status' => 403 ) );
		}

		$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
		if ( ! $dept_id ) {
			return new WP_Error( 'not_found', __( 'No department assigned to you.', 'etwa' ), array( 'status' => 404 ) );
		}

		$dept         = $this->departments->find( $dept_id );
		$employee_ids = DepartmentHelper::employee_ids_in_department( $dept_id );
		$user_filter  = array( 'user_ids' => $employee_ids );

		$pending_vac  = $this->vacation_requests->all( array_merge( $user_filter, array( 'status' => 'pending' ) ), 100, 0 );
		$pending_req  = $this->requests->all( array_merge( $user_filter, array( 'status' => 'pending' ) ), 100, 0 );
		$approved_req = $this->requests->all( array_merge( $user_filter, array( 'status' => 'approved' ) ), 20, 0 );
		$rejected_req = array_merge(
			$this->vacation_requests->all( array_merge( $user_filter, array( 'status' => 'rejected' ) ), 20, 0 ),
			$this->requests->all( array_merge( $user_filter, array( 'status' => 'rejected' ) ), 20, 0 )
		);

		$employees = array();
		foreach ( $employee_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( $user && EmployeeProfileHelper::is_active( $uid ) ) {
				$employees[] = array(
					'id'           => $uid,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'roles'        => EmployeeProfileHelper::role_label_for_user( $user ),
				);
			}
		}

		$start = gmdate( 'Y-m-01' );
		$end   = gmdate( 'Y-m-t', strtotime( '+60 days' ) );
		$leave = $this->vacation_records->approved_in_range( $start, $end );
		$upcoming = array();
		$leave_calendar = array();
		foreach ( $leave as $row ) {
			if ( ! in_array( (int) $row->user_id, $employee_ids, true ) ) {
				continue;
			}
			$user = get_userdata( (int) $row->user_id );
			$entry = array(
				'employee_name'   => $user ? $user->display_name : '',
				'leave_type_name' => (string) $row->leave_type_name,
				'start_date'      => (string) $row->start_date,
				'end_date'        => (string) $row->end_date,
				'days'            => (float) $row->days,
			);
			$upcoming[] = $entry;
			foreach ( $this->expand_date_range( (string) $row->start_date, (string) $row->end_date ) as $day ) {
				$leave_calendar[] = array_merge( $entry, array( 'date' => $day ) );
			}
		}

		$pending_dept_action = 0;
		$aging_count         = 0;
		foreach ( array_merge( $pending_vac, $pending_req ) as $item ) {
			if ( $this->approval->can_user_approve_department( $viewer_id, $item ) ) {
				++$pending_dept_action;
			}
			if ( $this->is_aging_pending( (string) $item->created_at ) ) {
				++$aging_count;
			}
		}

		return array(
			'department'            => $dept ? $dept->to_array() : null,
			'pending_count'         => count( $pending_vac ) + count( $pending_req ),
			'pending_dept_action'   => $pending_dept_action,
			'aging_count'           => $aging_count,
			'approved_count'        => count( $approved_req ),
			'rejected_count'        => count( $rejected_req ),
			'employees'             => $employees,
			'upcoming_leave'          => $upcoming,
			'leave_calendar'          => $leave_calendar,
			'team_timesheet_status'   => $this->team_timesheet_status( $employee_ids ),
			'statistics'              => $this->department_stats( $dept_id, $employee_ids ),
		);
	}

	/** @return array<string,mixed> */
	public function get_analytics( string $date_from, string $date_to ): array {
		global $wpdb;

		$departments = $this->departments->all( 'active' );
		$entries     = $wpdb->prefix . 'etwa_entries';
		$requests    = $wpdb->prefix . 'etwa_requests';
		$vac_req     = $wpdb->prefix . 'etwa_vacation_requests';

		$hours_by_dept = array();
		$ot_by_dept    = array();
		$req_by_dept   = array();

		foreach ( $departments as $dept ) {
			$employee_ids = DepartmentHelper::employee_ids_in_department( $dept->id );
			if ( ! $employee_ids ) {
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
			$params       = array_merge( array( $date_from, $date_to ), $employee_ids );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(regular_hours + overtime_hours), 0) AS total_hours,
						COALESCE(SUM(overtime_hours), 0) AS overtime_hours
					FROM {$entries}
					WHERE entry_date BETWEEN %s AND %s AND user_id IN ({$placeholders})",
					...$params
				)
			);

			$hours_by_dept[] = array(
				'department_id'   => $dept->id,
				'department_name' => $dept->name,
				'total_hours'     => round( (float) ( $row->total_hours ?? 0 ), 2 ),
			);
			$ot_by_dept[] = array(
				'department_id'   => $dept->id,
				'department_name' => $dept->name,
				'overtime_hours'  => round( (float) ( $row->overtime_hours ?? 0 ), 2 ),
			);

			$req_params = $employee_ids;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$req_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT request_type, COUNT(*) AS cnt FROM {$requests}
					WHERE user_id IN ({$placeholders}) AND request_date BETWEEN %s AND %s
					GROUP BY request_type",
					...array_merge( $req_params, array( $date_from, $date_to ) )
				)
			);

			$type_counts = array(
				'wfh'          => 0,
				'permission'   => 0,
				'holiday_work' => 0,
			);
			foreach ( $req_counts ?: array() as $rc ) {
				$key = (string) $rc->request_type;
				if ( isset( $type_counts[ $key ] ) ) {
					$type_counts[ $key ] = (int) $rc->cnt;
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$vac_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$vac_req}
					WHERE user_id IN ({$placeholders}) AND start_date BETWEEN %s AND %s",
					...array_merge( $employee_ids, array( $date_from, $date_to ) )
				)
			);

			$req_by_dept[] = array_merge(
				array(
					'department_id'   => $dept->id,
					'department_name' => $dept->name,
					'vacation'        => $vac_count,
				),
				$type_counts
			);
		}

		return array(
			'hours_by_department'      => $hours_by_dept,
			'overtime_by_department'   => $ot_by_dept,
			'requests_by_department'   => $req_by_dept,
			'date_from'                => $date_from,
			'date_to'                  => $date_to,
		);
	}

	/** @param int[] $employee_ids @return array<string,mixed> */
	private function department_stats( int $dept_id, array $employee_ids ): array {
		global $wpdb;
		if ( ! $employee_ids ) {
			return array(
				'employee_count'  => 0,
				'pending_requests'=> 0,
				'approved_month'  => 0,
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $employee_ids ), '%d' ) );
		$start        = gmdate( 'Y-m-01' );
		$end          = gmdate( 'Y-m-t' );
		$requests     = $wpdb->prefix . 'etwa_requests';
		$vac_req      = $wpdb->prefix . 'etwa_vacation_requests';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT (
					(SELECT COUNT(*) FROM {$vac_req} WHERE status = 'pending' AND user_id IN ({$placeholders}))
					+ (SELECT COUNT(*) FROM {$requests} WHERE status = 'pending' AND user_id IN ({$placeholders}))
				)",
				...array_merge( $employee_ids, $employee_ids )
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$approved = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT (
					(SELECT COUNT(*) FROM {$vac_req} WHERE status = 'approved' AND created_at BETWEEN %s AND %s AND user_id IN ({$placeholders}))
					+ (SELECT COUNT(*) FROM {$requests} WHERE status = 'approved' AND created_at BETWEEN %s AND %s AND user_id IN ({$placeholders}))
				)",
				...array_merge( array( $start, $end . ' 23:59:59' ), $employee_ids, array( $start, $end . ' 23:59:59' ), $employee_ids )
			)
		);

		return array(
			'employee_count'   => count( $employee_ids ),
			'pending_requests' => $pending,
			'approved_month'   => $approved,
		);
	}

	/** @param int[] $employee_ids @return array<int,array<string,mixed>> */
	private function team_timesheet_status( array $employee_ids ): array {
		if ( ! $employee_ids ) {
			return array();
		}

		$week      = DateHelper::get_current_work_week();
		$rows      = array();
		$labels    = array(
			'draft'       => __( 'Draft', 'etwa' ),
			'submitted'   => __( 'Submitted', 'etwa' ),
			'approved'    => __( 'Approved', 'etwa' ),
			'rejected'    => __( 'Rejected', 'etwa' ),
			'missing'     => __( 'Not started', 'etwa' ),
			'in_progress' => __( 'In progress', 'etwa' ),
		);
		$names     = UserDisplayNames::map( $employee_ids );
		$timesheets = $this->timesheets->find_by_users_period(
			$employee_ids,
			$week['period_start'],
			$week['period_end']
		);

		foreach ( $employee_ids as $uid ) {
			$ts     = $timesheets[ $uid ] ?? null;
			$status = $ts ? $ts->status : 'missing';
			if ( $ts && 'draft' === $status && ! \ETWA\Helpers\TimesheetDraftHelper::is_visible_to_reviewers( $ts ) ) {
				$status = 'in_progress';
			}
			$rows[] = array(
				'user_id'        => $uid,
				'display_name'   => UserDisplayNames::get( $names, $uid ),
				'status'         => $status,
				'status_label'   => $labels[ $status ] ?? $status,
				'period_start'   => $week['period_start'],
				'period_end'     => $week['period_end'],
				'total_hours'    => $ts ? round( (float) $ts->total_regular_hours + (float) $ts->total_overtime_hours, 2 ) : 0,
			);
		}

		return $rows;
	}

	/** @return string[] */
	private function expand_date_range( string $start, string $end ): array {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
			return array();
		}

		$dates  = array();
		$cursor = new \DateTimeImmutable( $start );
		$last   = new \DateTimeImmutable( $end );
		while ( $cursor <= $last ) {
			$dates[] = $cursor->format( 'Y-m-d' );
			$cursor  = $cursor->modify( '+1 day' );
		}

		return $dates;
	}

	private function is_aging_pending( string $created_at ): bool {
		if ( '' === $created_at ) {
			return false;
		}
		$created = DateHelper::mysql_to_timestamp( $created_at );
		if ( null === $created ) {
			return false;
		}
		return ( time() - $created ) >= ( 48 * HOUR_IN_SECONDS );
	}
}
