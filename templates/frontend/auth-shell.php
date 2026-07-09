<?php
/**
 * Branded auth page shell.
 *
 * @var string $content
 * @var string $page_title
 * @var string $brand_name
 * @var string $brand_mark
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="etwa-auth-layout etwa-app" id="etwa-auth-app">
	<div class="etwa-auth-card">
		<div class="etwa-auth-brand">
			<span class="etwa-auth-brand-mark" aria-hidden="true"><?php echo esc_html( $brand_mark ); ?></span>
			<div class="etwa-auth-brand-text">
				<strong><?php echo esc_html( $brand_name ); ?></strong>
				<span><?php esc_html_e( 'Workforce', 'etwa' ); ?></span>
			</div>
		</div>

		<h1 class="etwa-auth-title"><?php echo esc_html( $page_title ); ?></h1>

		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
