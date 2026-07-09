<?php
/**
 * Shared view-model for timesheet templates.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Entry;
use ETWA\Repositories\ClientRepository;
use ETWA\Services\TimesheetService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimesheetViewData {

	/** @return array<string,mixed> */
	public static function prepare( int $user_id, ?string $ref_date = null, string $context = 'admin' ): array {
		$service = new TimesheetService();
		$data    = $service->get_dashboard_data( $user_id, $ref_date );
		$week           = $ref_date ? DateHelper::get_work_week_for_date( $ref_date ) : DateHelper::get_current_work_week();
		$calendar_dates = CalendarHelper::get_calendar_dates( $week['period_start'] );
		$day_meta       = CalendarHelper::build_day_meta( $calendar_dates );

		$entries_by_day = array();
		$day_totals     = array();
		foreach ( $calendar_dates as $date ) {
			$entries_by_day[ $date ] = array();
			$day_totals[ $date ]     = 0.0;
		}

		foreach ( $data['entries'] as $entry ) {
			if ( ! isset( $entries_by_day[ $entry->entry_date ] ) ) {
				continue;
			}
			$entries_by_day[ $entry->entry_date ][] = $entry;
			$day_totals[ $entry->entry_date ]      += $entry->regular_hours + $entry->overtime_hours;
		}

		$day_labels = array();
		foreach ( array_keys( $entries_by_day ) as $date ) {
			$day_labels[ $date ] = DateHelper::get_day_info( $date );
		}

		$timesheet = $data['timesheet'];
		$prev      = DateHelper::get_adjacent_work_week( $week['period_start'], -1 );
		$next      = DateHelper::get_adjacent_work_week( $week['period_start'], 1 );
		$readonly  = $timesheet->is_locked || 'approved' === $timesheet->status;

		return array(
			'timesheet'        => $timesheet,
			'entries'          => $data['entries'],
			'entries_by_day'   => $entries_by_day,
			'day_totals'       => $day_totals,
			'day_labels'       => $day_labels,
			'week'             => $week,
			'work_dates'       => $calendar_dates,
			'calendar_dates'   => $calendar_dates,
			'day_meta'         => $day_meta,
			'week_label'       => DateHelper::format_week_range( $calendar_dates[0], $calendar_dates[6] ),
			'prev_week_start'  => $prev['period_start'],
			'next_week_start'  => $next['period_start'],
			'clients'          => ( new ClientRepository() )->all_for_dropdown(),
			'dark_mode'        => '1' === get_user_meta( $user_id, 'etwa_dark_mode', true ),
			'readonly'         => $readonly,
			'context'          => $context,
			'hr_review'        => false,
			'review_user_id'   => $user_id,
		);
	}

	/** @return array<string,mixed>|null */
	public static function prepare_hr_review( int $employee_user_id, int $viewer_id, ?string $ref_date = null, string $context = 'admin', string $hr_back_url = '', ?string $portal_view = null ): ?array {
		if ( ! RoleManager::user_can_view_timesheet( $viewer_id, $employee_user_id ) ) {
			return null;
		}

		$view = self::prepare( $employee_user_id, $ref_date, $context );
		$ts   = $view['timesheet'];
		$is_hr_viewer = user_can( $viewer_id, RoleManager::CAP_VIEW_ALL );

		if ( $is_hr_viewer ) {
			$view['hr_can_edit']           = ( new \ETWA\Services\HrTimesheetEditService() )->can_hr_edit(
				$viewer_id,
				$employee_user_id,
				$ts->status,
				$ts->is_locked
			);
			$view['readonly']              = ! $view['hr_can_edit'];
			$view['can_approve_timesheet'] = user_can( $viewer_id, RoleManager::CAP_APPROVE_TS );
			$view['dept_review']           = false;
			$view['review_page']           = 'etwa-hr';
		} else {
			$view['hr_can_edit']           = false;
			$view['readonly']              = true;
			$view['can_approve_timesheet'] = false;
			$view['dept_review']           = true;
			$view['review_page']           = 'etwa-dept-dashboard';
		}

		$view['hr_review']      = true;
		$view['review_user_id'] = $employee_user_id;
		$view['hr_back_url']    = $hr_back_url;
		$view['portal_view']    = $portal_view ?? ( $is_hr_viewer ? 'hr' : 'dept-dashboard' );
		$view['dark_mode']      = '1' === get_user_meta( $viewer_id, 'etwa_dark_mode', true );

		return $view;
	}

	/** @return array{employee:\WP_User|null,rows:array<int,array<string,mixed>>,current_week:array{period_start:string,period_end:string}}|null */
	public static function weeks_for_employee( int $employee_user_id, int $viewer_id, int $limit = 52 ): ?array {
		if ( ! RoleManager::user_can_view_timesheet( $viewer_id, $employee_user_id ) ) {
			return null;
		}

		$employee = get_userdata( $employee_user_id );
		if ( ! $employee ) {
			return null;
		}

		$history = self::history_for_user( $employee_user_id, $limit );

		return array(
			'employee'     => $employee,
			'rows'         => $history['rows'],
			'current_week' => $history['current_week'],
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function history_for_user( int $user_id, int $limit = 52 ): array {
		$timesheets    = ( new \ETWA\Repositories\TimesheetRepository() )->find_by_filters( array( 'user_id' => $user_id ), 1, $limit );
		$current_week  = DateHelper::get_current_work_week();
		$rows          = array();

		foreach ( $timesheets as $timesheet ) {
			$rows[] = array(
				'timesheet_id'   => $timesheet->id,
				'period_start'   => $timesheet->period_start,
				'period_end'     => $timesheet->period_end,
				'week_label'     => DateHelper::format_week_range( $timesheet->period_start, $timesheet->period_end ),
				'total_hours'    => $timesheet->total_regular_hours + $timesheet->total_overtime_hours,
				'regular_hours'  => $timesheet->total_regular_hours,
				'overtime_hours' => $timesheet->total_overtime_hours,
				'status'         => $timesheet->status,
				'submitted_at'   => $timesheet->submitted_at,
				'is_locked'      => $timesheet->is_locked,
			);
		}

		return array(
			'rows'          => $rows,
			'current_week'  => $current_week,
		);
	}

	/** @param Entry[] $entries */
	public static function sum_day_hours( array $entries ): float {
		$total = 0.0;
		foreach ( $entries as $entry ) {
			$total += $entry->regular_hours + $entry->overtime_hours;
		}
		return $total;
	}
}
