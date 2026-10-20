<?php
/**
 * WP Cron scheduled jobs.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Cron;

use ETWA\Helpers\DateHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Services\DeliverableNotificationService;
use ETWA\Services\MailService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CronJobs {

	public function __construct() {
		wp_clear_scheduled_hook( 'etwa_weekly_report_email' );
		add_action( 'etwa_daily_reminder', array( $this, 'send_daily_reminder' ) );
		add_action( 'etwa_deliverable_expiration_check', array( $this, 'send_deliverable_expiration_alerts' ) );
	}

	public function send_daily_reminder(): void {
		$week = DateHelper::get_current_work_week();
		global $wpdb;

		$table = $wpdb->prefix . 'etwa_timesheets';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$completed_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$table}
				WHERE period_start = %s AND period_end = %s AND status IN ('submitted','approved')",
				$week['period_start'],
				$week['period_end']
			)
		);
		$completed_map = array_fill_keys( array_map( 'intval', $completed_ids ?: array() ), true );

		$users = get_users(
			array(
				'role__in' => RoleManager::workforce_role_slugs(),
				'fields'   => array( 'ID', 'user_email' ),
			)
		);

		$mail = new MailService();

		foreach ( $users as $user ) {
			if ( ! user_can( (int) $user->ID, RoleManager::CAP_SUBMIT ) ) {
				continue;
			}
			if ( isset( $completed_map[ (int) $user->ID ] ) ) {
				continue;
			}

			$mail->send(
				array(
					'to'               => $user->user_email,
					'subject'          => __( 'Timesheet Reminder', 'etwa' ),
					'content'          => sprintf(
						/* translators: 1: period start, 2: period end */
						__( 'Reminder: please complete your timesheet for %1$s – %2$s.', 'etwa' ),
						$week['period_start'],
						$week['period_end']
					),
					'recipient_user_id'=> (int) $user->ID,
					'placeholders'     => array(
						'request_type' => __( 'Timesheet Reminder', 'etwa' ),
					),
				)
			);
		}
	}

	public function send_deliverable_expiration_alerts(): void {
		( new DeliverableNotificationService() )->process_expiration_alerts();
	}
}
