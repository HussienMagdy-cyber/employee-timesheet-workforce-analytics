<?php
/**
 * Admin audit logs page template.
 *
 * @var array<string,mixed> $log_view
 * @var string              $base_url
 * @var bool                $show_filter
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( $log_view, EXTR_SKIP );
?>
<div class="wrap etwa-app" id="etwa-logs-app">
	<h1><?php esc_html_e( 'Audit Logs', 'etwa' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Immutable record of timesheet, approval, and settings activity.', 'etwa' ); ?></p>

	<?php include ETWA_PLUGIN_DIR . 'templates/partials/audit-logs-table.php'; ?>

	<?php include ETWA_PLUGIN_DIR . 'templates/partials/wp-debug-log.php'; ?>
</div>
