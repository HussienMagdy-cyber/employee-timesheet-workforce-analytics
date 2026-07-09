<?php
/**
 * Vacation calendar panel (month grid + navigation).
 *
 * @var string $id_prefix      Element ID prefix, e.g. etwa-vacation or etwa-my-vacation.
 * @var bool   $employee_view  Show employee-specific legend item.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$id_prefix     = $id_prefix ?? 'etwa-vacation';
$employee_view = ! empty( $employee_view );
?>
<div class="etwa-vacation-calendar-panel">
	<div class="etwa-toolbar etwa-vacation-cal-toolbar">
		<h2 class="etwa-vacation-cal-heading"><?php esc_html_e( 'Vacation Calendar', 'etwa' ); ?></h2>
		<div class="etwa-vacation-cal-nav">
			<button type="button" class="etwa-btn etwa-btn-secondary etwa-vacation-cal-arrow" id="<?php echo esc_attr( $id_prefix ); ?>-cal-prev" aria-label="<?php esc_attr_e( 'Previous month', 'etwa' ); ?>">&larr;</button>
			<span class="etwa-vacation-cal-label" id="<?php echo esc_attr( $id_prefix ); ?>-cal-label"></span>
			<button type="button" class="etwa-btn etwa-btn-secondary etwa-vacation-cal-arrow" id="<?php echo esc_attr( $id_prefix ); ?>-cal-next" aria-label="<?php esc_attr_e( 'Next month', 'etwa' ); ?>">&rarr;</button>
		</div>
	</div>
	<div id="<?php echo esc_attr( $id_prefix ); ?>-calendar" class="etwa-vacation-calendar"></div>
	<p class="etwa-vacation-cal-legend etwa-muted">
		<span class="etwa-vacation-cal-legend-item"><span class="etwa-vacation-cal-legend-swatch is-holiday"></span><?php esc_html_e( 'Company holiday', 'etwa' ); ?></span>
		<span class="etwa-vacation-cal-legend-item"><span class="etwa-vacation-cal-legend-swatch is-event"></span><?php esc_html_e( 'Approved leave', 'etwa' ); ?></span>
		<?php if ( $employee_view ) : ?>
			<span class="etwa-vacation-cal-legend-item"><span class="etwa-vacation-cal-legend-swatch is-mine"></span><?php esc_html_e( 'My leave', 'etwa' ); ?></span>
		<?php endif; ?>
	</p>
</div>
