<?php
/**
 * Portal wrapper for Deliverables.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context                   = 'frontend';
$can_manage_scopes         = $can_manage_scopes ?? false;
$can_allocate_deliverables = $can_allocate_deliverables ?? false;
$can_manage_types          = $can_manage_types ?? ( $can_manage_scopes || $can_allocate_deliverables );
include ETWA_PLUGIN_DIR . 'templates/admin/deliverables.php';
