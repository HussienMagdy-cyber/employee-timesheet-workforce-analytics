<?php
/**
 * Branded auth page shell.
 *
 * @var string $content
 * @var string $page_title
 * @var string $brand_name
 * @var string $brand_mark
 * @var string $brand_icon
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$brand_icon = $brand_icon ?? '';
?>
<div class="etwa-auth-layout etwa-app" id="etwa-auth-app">
	<div class="etwa-auth-card">
		<div class="etwa-auth-brand">
			<?php if ( $brand_icon !== '' ) : ?>
				<img class="etwa-auth-brand-mark etwa-auth-brand-mark--icon" src="<?php echo esc_url( $brand_icon ); ?>" alt="" width="40" height="40" />
			<?php else : ?>
				<span class="etwa-auth-brand-mark" aria-hidden="true"><?php echo esc_html( $brand_mark ); ?></span>
			<?php endif; ?>
			<div class="etwa-auth-brand-text">
				<strong><?php echo esc_html( $brand_name ); ?></strong>
				<span><?php esc_html_e( 'Workforce', 'etwa' ); ?></span>
			</div>
		</div>

		<h1 class="etwa-auth-title"><?php echo esc_html( $page_title ); ?></h1>

		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
