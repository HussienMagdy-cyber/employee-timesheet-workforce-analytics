<?php
/**
 * HR / department week review notice (draft not shared, access denied).
 *
 * @var string $message
 * @var string $back_url
 * @var string $back_label
 * @var string $type info|warning|error
 * @var bool   $dept_review
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type       = $type ?? 'info';
$back_label = $back_label ?? __( 'Employee weeks', 'etwa' );
$dept_review = ! empty( $dept_review );
$alert_class = match ( $type ) {
	'error'   => 'etwa-alert-error',
	'warning' => 'etwa-alert-warning',
	default   => 'etwa-alert-info',
};
?>
<div class="etwa-page-shell etwa-hr-review">
	<div class="etwa-hr-review-toolbar">
		<?php if ( ! empty( $back_url ) ) : ?>
			<a class="etwa-btn etwa-btn-secondary" href="<?php echo esc_url( $back_url ); ?>">← <?php echo esc_html( $back_label ); ?></a>
		<?php endif; ?>
	</div>
	<div class="etwa-panel">
		<div class="etwa-alert <?php echo esc_attr( $alert_class ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php if ( 'draft_not_shared' === ( $code ?? '' ) ) : ?>
			<p class="etwa-muted"><?php esc_html_e( 'You will see the last saved version only — not live edits — once the employee clicks Save Draft.', 'etwa' ); ?></p>
		<?php endif; ?>
	</div>
</div>
