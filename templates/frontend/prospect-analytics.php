<?php
/**
 * Frontend prospects analytics wrapper.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context     = 'frontend';
$portal_base = $portal_base ?? '';
include ETWA_PLUGIN_DIR . 'templates/admin/prospect-analytics.php';
