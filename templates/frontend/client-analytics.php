<?php
/**
 * Frontend client analytics wrapper.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Services\ClientAnalyticsService;

$service = new ClientAnalyticsService();
$preset  = isset( $_GET['preset'] ) ? sanitize_key( wp_unslash( (string) $_GET['preset'] ) ) : DateRangeHelper::PRESET_THIS_MONTH;
$from    = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : null;
$to      = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : null;
$range   = $service->resolve_range( $preset, $from, $to );

$context         = 'frontend';
$portal_base     = $portal_base ?? '';
$show_labor_cost = EmployeeCostHelper::can_view_labor_cost();
$can_export      = current_user_can( RoleManager::CAP_EXPORT );
$preset          = $range['preset'];
$date_from       = $range['date_from'];
$date_to         = $range['date_to'];
$range           = $range;
$client_id       = isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0;
$project_id      = isset( $_GET['project_id'] ) ? (int) $_GET['project_id'] : 0;
$page            = max( 1, (int) ( $_GET['ca_page'] ?? 1 ) );
$search          = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';

if ( $project_id > 0 ) {
	$detail = $service->get_project_detail( $project_id, $date_from, $date_to );
	if ( is_wp_error( $detail ) ) {
		echo '<div class="etwa-portal-message"><p>' . esc_html( $detail->get_error_message() ) . '</p></div>';
		return;
	}
	include ETWA_PLUGIN_DIR . 'templates/admin/project-analytics-detail.php';
	return;
}

if ( $client_id > 0 ) {
	$detail = $service->get_client_detail( $client_id, $date_from, $date_to );
	if ( is_wp_error( $detail ) ) {
		echo '<div class="etwa-portal-message"><p>' . esc_html( $detail->get_error_message() ) . '</p></div>';
		return;
	}
	include ETWA_PLUGIN_DIR . 'templates/admin/client-analytics-detail.php';
	return;
}

$list = $service->list_clients( $date_from, $date_to, $page, 25, $search );
if ( is_wp_error( $list ) ) {
	echo '<div class="etwa-portal-message"><p>' . esc_html( $list->get_error_message() ) . '</p></div>';
	$list = array( 'items' => array(), 'total' => 0, 'page' => 1, 'per_page' => 25 );
}
include ETWA_PLUGIN_DIR . 'templates/admin/client-analytics-list.php';
