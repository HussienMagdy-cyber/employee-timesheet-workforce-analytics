<?php
/**
 * Frontend HR dashboard wrapper.
 *
 * @var string $review_base_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="etwa-page-shell">
	<div class="etwa-hero">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'HR Operations', 'etwa' ); ?></p>
			<h1><?php esc_html_e( 'HR Dashboard', 'etwa' ); ?></h1>
			<p class="etwa-subtitle"><?php esc_html_e( 'Review submissions and export workforce reports.', 'etwa' ); ?></p>
		</div>
	</div>
	<?php include ETWA_PLUGIN_DIR . 'templates/admin/hr-dashboard.php'; ?>
</div>
