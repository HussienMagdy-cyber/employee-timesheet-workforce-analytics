<?php
/**
 * Vacation analytics service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\VacationHelper;
use ETWA\Repositories\LeaveTypeRepository;
use ETWA\Repositories\VacationRecordRepository;
use ETWA\Repositories\VacationRequestRepository;
use ETWA\Repositories\VacationTransactionRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationAnalyticsService {

	public function __construct(
		private VacationService $vacation = new VacationService(),
		private LeaveTypeRepository $leave_types = new LeaveTypeRepository(),
		private VacationTransactionRepository $transactions = new VacationTransactionRepository(),
		private VacationRecordRepository $records = new VacationRecordRepository(),
		private VacationRequestRepository $requests = new VacationRequestRepository(),
	) {}

	/** @param array<string,mixed> $filters @return array<string,mixed>|WP_Error */
	public function dashboard( int $viewer_id, array $filters = array() ): array|WP_Error {
		if ( ! $this->vacation->can_manage( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view vacation analytics.', 'etwa' ), array( 'status' => 403 ) );
		}

		global $wpdb;
		$tx_table = $wpdb->prefix . 'etwa_vacation_transactions';
		$rec_table = $wpdb->prefix . 'etwa_vacation_records';
		$today     = current_time( 'Y-m-d' );

		$year = (int) ( $filters['year'] ?? gmdate( 'Y' ) );

		$total_allocated = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(days),0) FROM {$tx_table} WHERE transaction_type = 'allocation'"
		);
		$total_adjusted = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(days),0) FROM {$tx_table} WHERE transaction_type = 'adjustment'"
		);
		$total_used = abs( (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(days),0) FROM {$tx_table} WHERE transaction_type = 'usage'"
		) );
		$remaining = $total_allocated + $total_adjusted - $total_used;

		$pending_records = $this->records->count_by_status( VacationHelper::STATUS_PENDING );
		$pending_requests = $this->requests->count_pending();

		$on_leave = $this->records->currently_on_leave( $today );
		$on_leave_count = count( $on_leave );

		$upcoming = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, lt.name AS leave_type_name FROM {$rec_table} r
				INNER JOIN {$wpdb->prefix}etwa_leave_types lt ON lt.id = r.leave_type_id
				WHERE r.status = 'approved' AND r.start_date > %s
				ORDER BY r.start_date ASC LIMIT 10",
				$today
			)
		) ?: array();

		$usage_by_month = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MONTH(effective_date) AS month_num, ABS(SUM(days)) AS total
				FROM {$tx_table}
				WHERE transaction_type = 'usage' AND YEAR(effective_date) = %d
				GROUP BY MONTH(effective_date)
				ORDER BY month_num ASC",
				$year
			)
		) ?: array();

		$type_distribution = $wpdb->get_results(
			"SELECT lt.name AS label, ABS(SUM(t.days)) AS total
			FROM {$tx_table} t
			INNER JOIN {$wpdb->prefix}etwa_leave_types lt ON lt.id = t.leave_type_id
			WHERE t.transaction_type = 'usage'
			GROUP BY t.leave_type_id, lt.name
			ORDER BY total DESC"
		) ?: array();

		$top_employees = $wpdb->get_results(
			"SELECT t.user_id, ABS(SUM(t.days)) AS total
			FROM {$tx_table} t
			WHERE t.transaction_type = 'usage'
			GROUP BY t.user_id
			ORDER BY total DESC
			LIMIT 10"
		) ?: array();

		$top_employee_items = array();
		foreach ( $top_employees as $row ) {
			$user = get_userdata( (int) $row->user_id );
			$top_employee_items[] = array(
				'user_id'      => (int) $row->user_id,
				'display_name' => $user ? $user->display_name : (string) $row->user_id,
				'total'        => (float) $row->total,
			);
		}

		$remaining_by_employee = array();
		$employees = VacationHelper::list_employees( 1, 100 );
		foreach ( $employees['items'] as $emp ) {
			$pool = VacationHelper::annual_pool( (int) $emp['id'] );
			$remaining_by_employee[] = array(
				'user_id'      => (int) $emp['id'],
				'display_name' => $emp['display_name'],
				'remaining'    => (float) $pool['remaining'],
			);
		}
		usort(
			$remaining_by_employee,
			fn( $a, $b ) => $b['remaining'] <=> $a['remaining']
		);
		$remaining_by_employee = array_slice( $remaining_by_employee, 0, 10 );

		$month_labels = array();
		$month_values = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$month_labels[] = wp_date( 'M', mktime( 0, 0, 0, $m, 1, $year ) );
			$found          = 0.0;
			foreach ( $usage_by_month as $row ) {
				if ( (int) $row->month_num === $m ) {
					$found = (float) $row->total;
					break;
				}
			}
			$month_values[] = $found;
		}

		return array(
			'cards' => array(
				'total_allocated'      => round( $total_allocated, 2 ),
				'total_used'           => round( $total_used, 2 ),
				'remaining'            => round( $remaining, 2 ),
				'pending_requests'     => $pending_records + $pending_requests,
				'upcoming_count'       => count( $upcoming ),
				'on_leave_count'       => $on_leave_count,
			),
			'upcoming'             => array_map(
				function ( $row ) {
					$user = get_userdata( (int) $row->user_id );
					return array(
						'employee_name'   => $user ? $user->display_name : '',
						'leave_type_name' => (string) $row->leave_type_name,
						'start_date'      => (string) $row->start_date,
						'end_date'        => (string) $row->end_date,
						'days'            => (float) $row->days,
					);
				},
				$upcoming
			),
			'on_leave'             => array_map(
				function ( $row ) {
					$user = get_userdata( (int) $row->user_id );
					return array(
						'employee_name'   => $user ? $user->display_name : '',
						'leave_type_name' => (string) $row->leave_type_name,
						'start_date'      => (string) $row->start_date,
						'end_date'        => (string) $row->end_date,
					);
				},
				$on_leave
			),
			'charts' => array(
				'usage_by_month' => array(
					'labels' => $month_labels,
					'values' => $month_values,
				),
				'type_distribution' => array(
					'labels' => array_map( fn( $r ) => (string) $r->label, $type_distribution ),
					'values' => array_map( fn( $r ) => (float) $r->total, $type_distribution ),
				),
				'top_employees' => $top_employee_items,
				'remaining_by_employee' => $remaining_by_employee,
			),
		);
	}
}
