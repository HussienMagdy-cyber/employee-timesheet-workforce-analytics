<?php
/**
 * Period lock service for approved reporting windows.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\TimesheetRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PeriodLockService {

	public function __construct(
		private TimesheetRepository $timesheets = new TimesheetRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function lock_period( int $user_id, string $period_start, string $period_end ): bool|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_LOCK_PERIODS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot lock periods.', 'etwa' ), array( 'status' => 403 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'etwa_timesheets';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_locked = 1, updated_at = %s
				WHERE period_start >= %s AND period_end <= %s",
				current_time( 'mysql' ),
				$period_start,
				$period_end
			)
		);

		$this->audit->log(
			'lock_period',
			'timesheet',
			0,
			null,
			array( 'period_start' => $period_start, 'period_end' => $period_end )
		);

		return true;
	}

	public function unlock_period( int $user_id, string $period_start, string $period_end ): bool|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_LOCK_PERIODS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot unlock periods.', 'etwa' ), array( 'status' => 403 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'etwa_timesheets';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_locked = 0, updated_at = %s
				WHERE period_start >= %s AND period_end <= %s AND status != 'approved'",
				current_time( 'mysql' ),
				$period_start,
				$period_end
			)
		);

		$this->audit->log(
			'unlock_period',
			'timesheet',
			0,
			null,
			array( 'period_start' => $period_start, 'period_end' => $period_end )
		);

		return true;
	}
}
