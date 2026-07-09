<?php
/**
 * Frontend audit logs page.
 *
 * @var array<string,mixed> $log_view
 * @var string              $portal_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( $log_view, EXTR_SKIP );
$base_url    = $portal_url;
$show_filter = true;
?>
<div class="etwa-page-shell">
	<div class="etwa-hero">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'System Activity', 'etwa' ); ?></p>
			<h1><?php esc_html_e( 'Audit Logs', 'etwa' ); ?></h1>
			<p class="etwa-subtitle"><?php esc_html_e( 'Track timesheet submissions, approvals, edits, and configuration changes.', 'etwa' ); ?></p>
		</div>
	</div>

	<?php include ETWA_PLUGIN_DIR . 'templates/partials/audit-logs-table.php'; ?>

	<?php include ETWA_PLUGIN_DIR . 'templates/partials/wp-debug-log.php'; ?>
</div>
