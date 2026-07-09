<?php
/**
 * Frontend owner analytics wrapper.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Services\AnalyticsService;

$from        = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : gmdate( 'Y-m-01' );
$to          = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
$portal_base = $portal_base ?? ( get_permalink() ?: home_url( '/' ) );
$context     = 'frontend';

$service  = new AnalyticsService();
$kpis     = $service->get_kpis( $from, $to );
$insights = $service->get_insights( $from, $to );

include ETWA_PLUGIN_DIR . 'templates/admin/owner-analytics.php';
