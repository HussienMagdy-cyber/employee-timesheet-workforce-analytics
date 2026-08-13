<?php
/**
 * Portal wrapper for Client Files.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context            = 'frontend';
$can_view_analytics = $can_view_analytics ?? false;
$can_allocate       = $can_allocate ?? false;
$client_id          = $client_id ?? 0;
include ETWA_PLUGIN_DIR . 'templates/admin/files.php';
