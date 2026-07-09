<?php
/**
 * URL helpers for HR employee week review flows.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HrReviewLinks {

	public static function employee_weeks_url( string $hr_base_url, int $employee_user_id ): string {
		return add_query_arg( 'review_user', $employee_user_id, $hr_base_url );
	}

	public static function week_report_url( string $hr_base_url, int $employee_user_id, string $period_start ): string {
		return add_query_arg(
			array(
				'review_user' => $employee_user_id,
				'etwa_week'   => $period_start,
			),
			$hr_base_url
		);
	}
}
