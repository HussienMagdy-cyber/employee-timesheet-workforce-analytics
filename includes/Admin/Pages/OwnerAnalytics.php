<?php
/**
 * Owner analytics page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Services\AnalyticsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OwnerAnalytics {

	public function render(): void {
		$from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : gmdate( 'Y-m-01' );
		$to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
		$context  = 'admin';
		$service  = new AnalyticsService();
		$kpis     = $service->get_kpis( $from, $to );
		$insights = $service->get_insights( $from, $to );

		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/owner-analytics.php';
		echo '</div>';
	}
}
