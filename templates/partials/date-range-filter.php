<?php
/**
 * Shared analytics date range filter.
 *
 * @var string               $preset
 * @var string               $date_from
 * @var string               $date_to
 * @var string               $filter_url
 * @var array<string,string> $presets
 * @var string               $portal_view  Portal view slug.
 * @var int                  $client_id    Optional detail context.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\DateRangeHelper;

$presets     = $presets ?? DateRangeHelper::preset_labels();
$preset      = $preset ?? DateRangeHelper::PRESET_THIS_MONTH;
$portal_view = $portal_view ?? 'client-analytics';
$client_id   = (int) ( $client_id ?? 0 );
?>
<form method="get" class="etwa-analytics-filter etwa-date-range-filter" action="<?php echo esc_url( $filter_url ); ?>" id="etwa-date-range-form">
	<?php if ( ! empty( $in_portal ) ) : ?>
		<input type="hidden" name="etwa_view" value="<?php echo esc_attr( $portal_view ); ?>" />
	<?php elseif ( ! empty( $admin_page ) ) : ?>
		<input type="hidden" name="page" value="<?php echo esc_attr( $admin_page ); ?>" />
	<?php endif; ?>
	<?php if ( $client_id > 0 ) : ?>
		<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
	<?php endif; ?>

	<div class="etwa-tabs etwa-date-preset-row">
		<?php foreach ( $presets as $key => $label ) : ?>
			<button
				type="submit"
				name="preset"
				value="<?php echo esc_attr( $key ); ?>"
				class="etwa-tab<?php echo $preset === $key ? ' active' : ''; ?>"
			><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="etwa-analytics-filter-fields etwa-custom-range-fields<?php echo DateRangeHelper::PRESET_CUSTOM === $preset ? ' is-visible' : ''; ?>">
		<label class="etwa-analytics-field">
			<span><?php esc_html_e( 'From', 'etwa' ); ?></span>
			<input type="date" class="etwa-input" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
		</label>
		<label class="etwa-analytics-field">
			<span><?php esc_html_e( 'To', 'etwa' ); ?></span>
			<input type="date" class="etwa-input" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
		</label>
		<button type="submit" name="preset" value="<?php echo esc_attr( DateRangeHelper::PRESET_CUSTOM ); ?>" class="etwa-btn etwa-btn-primary">
			<?php esc_html_e( 'Apply custom range', 'etwa' ); ?>
		</button>
	</div>
</form>
