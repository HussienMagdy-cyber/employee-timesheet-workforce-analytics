<?php
/**
 * Holiday management page.
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\CalendarHelper;

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context;
$labels    = CalendarHelper::weekday_labels();
$weekdays  = CalendarHelper::weekday_config();
?>
<div class="etwa-page-shell etwa-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-holidays-app">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Workforce', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Holidays & Calendar', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Manage company holidays and configure the seven-day work calendar.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Seven-Day Calendar', 'etwa' ); ?></h2>
		<p class="etwa-muted etwa-panel-intro"><?php esc_html_e( 'Set each weekday as Working or Day Off. Holidays override any day and appear on employee timesheets.', 'etwa' ); ?></p>
		<form id="etwa-calendar-form" class="etwa-calendar-weekdays">
			<?php foreach ( $labels as $dow => $label ) : ?>
				<label class="etwa-calendar-day-config">
					<span><?php echo esc_html( $label ); ?></span>
					<select name="weekdays[<?php echo esc_attr( (string) $dow ); ?>]" class="etwa-input">
						<option value="working" <?php selected( ( $weekdays[ $dow ] ?? '' ), 'working' ); ?>><?php esc_html_e( 'Working', 'etwa' ); ?></option>
						<option value="day_off" <?php selected( ( $weekdays[ $dow ] ?? '' ), 'day_off' ); ?>><?php esc_html_e( 'Day Off', 'etwa' ); ?></option>
					</select>
				</label>
			<?php endforeach; ?>
			<div class="etwa-calendar-form-actions">
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Calendar', 'etwa' ); ?></button>
			</div>
		</form>
	</div>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Add Holiday', 'etwa' ); ?></h2>
		<form id="etwa-holiday-form" class="etwa-form-grid">
			<label><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" required placeholder="<?php esc_attr_e( 'e.g. Eid Al-Fitr', 'etwa' ); ?>" /></label>
			<label><?php esc_html_e( 'Date', 'etwa' ); ?><input type="date" name="holiday_date" required /></label>
			<label class="etwa-span-2 etwa-checkbox-row">
				<input type="checkbox" name="is_recurring" value="1" />
				<?php esc_html_e( 'Recurring every year (same month & day)', 'etwa' ); ?>
			</label>
			<div class="etwa-span-2">
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Add Holiday', 'etwa' ); ?></button>
			</div>
		</form>
	</div>

	<div class="etwa-panel">
		<div class="etwa-toolbar">
			<h2><?php esc_html_e( 'Holidays', 'etwa' ); ?></h2>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by year', 'etwa' ); ?></span>
				<select id="etwa-holiday-year" class="etwa-input">
					<option value=""><?php esc_html_e( 'All years', 'etwa' ); ?></option>
					<?php for ( $y = (int) gmdate( 'Y' ) + 1; $y >= (int) gmdate( 'Y' ) - 5; $y-- ) : ?>
						<option value="<?php echo esc_attr( (string) $y ); ?>"><?php echo esc_html( (string) $y ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
		</div>
		<div class="etwa-table-wrap">
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Recurring', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-holidays-body">
					<tr class="etwa-skeleton-row"><td colspan="4"><div class="etwa-skeleton" style="height:40px"></div></td></tr>
				</tbody>
			</table>
		</div>
		<nav class="etwa-pagination" id="etwa-holidays-pagination" aria-label="<?php esc_attr_e( 'Holidays pagination', 'etwa' ); ?>"></nav>
	</div>
</div>
