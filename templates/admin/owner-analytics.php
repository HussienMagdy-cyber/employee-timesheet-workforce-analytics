<?php
/**
 * Owner analytics template (admin + portal).
 *
 * @var array  $kpis
 * @var array  $insights
 * @var string $from
 * @var string $to
 * @var string $context      frontend|admin
 * @var string $portal_base  Optional portal URL for date filter.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;

$context     = $context ?? ( is_admin() ? 'admin' : 'frontend' );
$portal_base = $portal_base ?? '';
$in_portal   = 'frontend' === $context && $portal_base;
$filter_url  = $in_portal ? PortalNav::view_url( $portal_base, 'analytics' ) : '';

$ot_ratio = $kpis['total_hours'] > 0
	? min( 100, round( ( $kpis['overtime_hours'] / $kpis['total_hours'] ) * 100, 1 ) )
	: 0;
$util_pct = (float) ( $kpis['utilization_pct'] ?? 0 );
?>
<div class="etwa-app etwa-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-owner-app">

	<form method="get" class="etwa-analytics-filter" action="<?php echo esc_url( $in_portal ? $filter_url : '' ); ?>">
		<?php if ( ! $in_portal && is_admin() ) : ?>
			<input type="hidden" name="page" value="etwa-analytics" />
		<?php endif; ?>
		<div class="etwa-analytics-filter-fields">
			<label class="etwa-analytics-field">
				<span><?php esc_html_e( 'From', 'etwa' ); ?></span>
				<input type="date" class="etwa-input" name="date_from" value="<?php echo esc_attr( $from ); ?>" />
			</label>
			<label class="etwa-analytics-field">
				<span><?php esc_html_e( 'To', 'etwa' ); ?></span>
				<input type="date" class="etwa-input" name="date_to" value="<?php echo esc_attr( $to ); ?>" />
			</label>
		</div>
		<button type="submit" class="etwa-btn etwa-btn-primary">
			<span class="dashicons dashicons-update" aria-hidden="true"></span>
			<?php esc_html_e( 'Apply range', 'etwa' ); ?>
		</button>
	</form>

	<div class="etwa-analytics-kpi-grid">
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-icon etwa-analytics-kpi-icon-hours" aria-hidden="true">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Total Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-kpi="total_hours"><?php echo esc_html( number_format( (float) $kpis['total_hours'], 1 ) ); ?></strong>
				<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Regular + overtime', 'etwa' ); ?></span>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-icon etwa-analytics-kpi-icon-regular" aria-hidden="true">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Regular Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-kpi="regular_hours"><?php echo esc_html( number_format( (float) $kpis['regular_hours'], 1 ) ); ?></strong>
				<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Within standard schedule', 'etwa' ); ?></span>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-icon etwa-analytics-kpi-icon-ot" aria-hidden="true">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Overtime Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value etwa-accent" data-kpi="overtime_hours"><?php echo esc_html( number_format( (float) $kpis['overtime_hours'], 1 ) ); ?></strong>
				<div class="etwa-analytics-mini-bar" aria-hidden="true">
					<span style="width: <?php echo esc_attr( (string) $ot_ratio ); ?>%;"></span>
				</div>
				<span class="etwa-analytics-kpi-hint"><?php echo esc_html( sprintf( __( '%s%% of total hours', 'etwa' ), number_format( $ot_ratio, 1 ) ) ); ?></span>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-icon etwa-analytics-kpi-icon-util" aria-hidden="true">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Utilization', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-kpi="utilization_pct"><?php echo esc_html( number_format( $util_pct, 1 ) ); ?>%</strong>
				<div class="etwa-analytics-mini-bar etwa-analytics-mini-bar-util" aria-hidden="true">
					<span style="width: <?php echo esc_attr( (string) min( 100, $util_pct ) ); ?>%;"></span>
				</div>
				<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Capacity vs worked hours', 'etwa' ); ?></span>
			</div>
		</article>
	</div>

	<div class="etwa-analytics-kpi-grid etwa-analytics-extended-kpis" id="etwa-extended-metrics" hidden>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Weekend Work Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-ext-kpi="weekend_work_hours">—</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Holiday Work Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-ext-kpi="holiday_work_hours">—</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Meeting Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-ext-kpi="meeting_hours">—</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Event Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-ext-kpi="event_hours">—</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Work On Site Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value" data-ext-kpi="work_on_site_hours">—</strong>
			</div>
		</article>
	</div>

	<section class="etwa-analytics-charts" aria-label="<?php esc_attr_e( 'Charts', 'etwa' ); ?>">
		<article class="etwa-chart-card etwa-chart-card-wide">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Monthly Trends', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Regular vs overtime hours over time', 'etwa' ); ?></p>
				</div>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="trends">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-chart-trends" height="240" role="img" aria-label="<?php esc_attr_e( 'Monthly trends chart', 'etwa' ); ?>"></canvas>
			</div>
		</article>

		<article class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Top Clients', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Share of billable hours', 'etwa' ); ?></p>
				</div>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="clients">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-chart-clients" height="240" role="img" aria-label="<?php esc_attr_e( 'Top clients chart', 'etwa' ); ?>"></canvas>
			</div>
		</article>

		<article class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Employee Utilization', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Worked hours vs capacity', 'etwa' ); ?></p>
				</div>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="utilization">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-chart-utilization" height="240" role="img" aria-label="<?php esc_attr_e( 'Employee utilization chart', 'etwa' ); ?>"></canvas>
			</div>
		</article>

		<article class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Departments', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Hours by client department', 'etwa' ); ?></p>
				</div>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="departments">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-chart-departments" height="240" role="img" aria-label="<?php esc_attr_e( 'Department performance chart', 'etwa' ); ?>"></canvas>
			</div>
		</article>
	</section>

	<section class="etwa-insights-section" aria-labelledby="etwa-insights-heading">
		<header class="etwa-insights-section-head">
			<div>
				<p class="etwa-eyebrow"><?php esc_html_e( 'Intelligence', 'etwa' ); ?></p>
				<h2 id="etwa-insights-heading"><?php esc_html_e( 'Insights &amp; Alerts', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'Actionable patterns from your workforce data', 'etwa' ); ?></p>
			</div>
		</header>

		<div class="etwa-insights-grid">
			<article class="etwa-insight-card">
				<header class="etwa-insight-card-head">
					<span class="etwa-insight-icon etwa-insight-icon-clients" aria-hidden="true">
						<span class="dashicons dashicons-building"></span>
					</span>
					<div>
						<h3><?php esc_html_e( 'Top Clients', 'etwa' ); ?></h3>
						<p><?php esc_html_e( 'Highest hour consumption', 'etwa' ); ?></p>
					</div>
				</header>
				<div class="etwa-insight-list" id="etwa-insight-top-clients"></div>
			</article>

			<article class="etwa-insight-card etwa-insight-card-warning">
				<header class="etwa-insight-card-head">
					<span class="etwa-insight-icon etwa-insight-icon-overload" aria-hidden="true">
						<span class="dashicons dashicons-performance"></span>
					</span>
					<div>
						<h3><?php esc_html_e( 'Overloaded', 'etwa' ); ?></h3>
						<p><?php esc_html_e( 'Above 100% utilization', 'etwa' ); ?></p>
					</div>
				</header>
				<div class="etwa-insight-list" id="etwa-insight-overloaded"></div>
			</article>

			<article class="etwa-insight-card">
				<header class="etwa-insight-card-head">
					<span class="etwa-insight-icon etwa-insight-icon-under" aria-hidden="true">
						<span class="dashicons dashicons-arrow-down-alt"></span>
					</span>
					<div>
						<h3><?php esc_html_e( 'Underutilized', 'etwa' ); ?></h3>
						<p><?php esc_html_e( 'Below 60% utilization', 'etwa' ); ?></p>
					</div>
				</header>
				<div class="etwa-insight-list" id="etwa-insight-underutilized"></div>
			</article>

			<article class="etwa-insight-card etwa-insight-card-ot">
				<header class="etwa-insight-card-head">
					<span class="etwa-insight-icon etwa-insight-icon-ot" aria-hidden="true">
						<span class="dashicons dashicons-clock"></span>
					</span>
					<div>
						<h3><?php esc_html_e( 'OT Hotspots', 'etwa' ); ?></h3>
						<p><?php esc_html_e( 'Clients with high overtime ratio', 'etwa' ); ?></p>
					</div>
				</header>
				<div class="etwa-insight-list" id="etwa-insight-client-ot"></div>
			</article>
		</div>
	</section>
</div>

<script>
window.etwaOwnerData = {
	dateFrom: <?php echo wp_json_encode( $from ); ?>,
	dateTo: <?php echo wp_json_encode( $to ); ?>,
	insights: <?php echo wp_json_encode( $insights ); ?>,
	i18n: {
		noData: <?php echo wp_json_encode( __( 'No data for this period', 'etwa' ) ); ?>,
		hours: <?php echo wp_json_encode( __( 'hours', 'etwa' ) ); ?>,
		utilization: <?php echo wp_json_encode( __( 'utilization', 'etwa' ) ); ?>,
		otRatio: <?php echo wp_json_encode( __( 'OT ratio', 'etwa' ) ); ?>
	}
};
</script>
