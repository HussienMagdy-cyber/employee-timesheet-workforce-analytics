<?php
/**
 * HR dashboard initial data (KPI counts + employees only).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Timesheet;
use ETWA\Repositories\TimesheetRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HrDashboardData {

	/** @return array{employees:\WP_User[],counts:array<string,int>} */
	public static function initial(): array {
		$repo = new TimesheetRepository();

		return array(
			'employees' => get_users( array( 'role__in' => RoleManager::workforce_role_slugs() ) ),
			'counts'    => array(
				'pending'  => $repo->count_by_filters( array( 'status' => 'submitted' ) ),
				'approved' => $repo->count_by_filters( array( 'status' => 'approved' ) ),
			),
		);
	}

	/**
	 * @param Timesheet[] $timesheets
	 * @return array<int,array{timesheet:Timesheet,display_name:string}>
	 */
	public static function timesheet_rows( array $timesheets ): array {
		$names = UserDisplayNames::map( array_map( fn( Timesheet $ts ) => $ts->user_id, $timesheets ) );
		$rows  = array();
		foreach ( $timesheets as $ts ) {
			$rows[] = array(
				'timesheet'    => $ts,
				'display_name' => UserDisplayNames::get( $names, $ts->user_id ),
			);
		}
		return $rows;
	}
}
