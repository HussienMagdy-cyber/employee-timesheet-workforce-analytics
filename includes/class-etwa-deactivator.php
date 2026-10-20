<?php
/**
 * Plugin deactivation handler.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'etwa_weekly_report_email' );
		wp_clear_scheduled_hook( 'etwa_daily_reminder' );
		wp_clear_scheduled_hook( 'etwa_deliverable_expiration_check' );
		flush_rewrite_rules();
	}
}
