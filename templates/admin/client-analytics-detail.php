<?php
/**
 * Client analytics detail page.
 *
 * @var array<string,mixed>  $detail
 * @var array<string,string> $range
 * @var string               $preset
 * @var string               $date_from
 * @var string               $date_to
 * @var string               $context
 * @var string               $portal_base
 * @var bool                 $show_labor_cost
 * @var bool                 $can_export
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\DateHelper;

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context && ! empty( $portal_base );
$client    = $detail['client'] ?? array();
$summary   = $detail['summary'] ?? array();
$client_id = (int) ( $client['id'] ?? 0 );
$list_url  = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics', array( 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
	: add_query_arg( array( 'page' => 'etwa-client-analytics', 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ), admin_url( 'admin.php' ) );
$filter_url = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics', array( 'client_id' => $client_id ) )
	: add_query_arg( array( 'page' => 'etwa-client-analytics', 'client_id' => $client_id ), admin_url( 'admin.php' ) );
?>
<div class="etwa-app etwa-client-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-client-analytics-app" data-mode="detail" data-client-id="<?php echo esc_attr( (string) $client_id ); ?>">

	<p class="etwa-back-link-wrap">
		<a class="etwa-link" href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'All clients', 'etwa' ); ?></a>
	</p>

	<div class="etwa-hero etwa-hero-compact">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php echo esc_html( $client['department'] ?? '' ); ?></p>
			<h1><?php echo esc_html( $client['name'] ?? '' ); ?> <code class="etwa-code-inline"><?php echo esc_html( $client['code'] ?? '' ); ?></code></h1>
			<p class="etwa-subtitle"><?php echo esc_html( $range['label'] ?? '' ); ?></p>
		</div>
		<?php if ( $can_export ) : ?>
			<div class="etwa-hero-actions">
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-ca-export-excel"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-ca-export-csv"><?php esc_html_e( 'Export CSV', 'etwa' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<?php
	$admin_page = 'etwa-client-analytics';
	include ETWA_PLUGIN_DIR . 'templates/partials/date-range-filter.php';
	?>

	<div class="etwa-analytics-kpi-grid etwa-ca-detail-kpis">
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Total Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['total_hours'] ?? 0 ), 1 ) ); ?></strong>
			</div>
		</article>
		<?php if ( $show_labor_cost ) : ?>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Labor Cost (EGP)', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['labor_cost_egp'] ?? 0 ), 2 ) ); ?></strong>
				</div>
			</article>
		<?php endif; ?>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Employees', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value"><?php echo esc_html( (string) ( $summary['employee_count'] ?? 0 ) ); ?></strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Overtime', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value etwa-accent"><?php echo esc_html( number_format( (float) ( $summary['overtime_hours'] ?? 0 ), 1 ) ); ?>h</strong>
			</div>
		</article>
	</div>

	<div class="etwa-analytics-charts-grid">
		<section class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<h2><?php esc_html_e( 'Hours Trend', 'etwa' ); ?></h2>
				<p><?php esc_html_e( 'Monthly hours logged for this client', 'etwa' ); ?></p>
			</header>
			<div class="etwa-chart-wrap is-loading" data-chart="client-trend">
				<canvas id="etwa-ca-trend-chart" height="220"></canvas>
			</div>
		</section>
	</div>

	<div class="etwa-analytics-grid zwei-col">
		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Employee Breakdown', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'OT', 'etwa' ); ?></th>
						<?php if ( $show_labor_cost ) : ?>
							<th><?php esc_html_e( 'Labor Cost', 'etwa' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['employees'] ) ) : ?>
						<tr><td colspan="<?php echo $show_labor_cost ? 4 : 3; ?>"><?php esc_html_e( 'No entries for this period.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['employees'] as $emp ) : ?>
							<tr>
								<td><?php echo esc_html( $emp['display_name'] ); ?></td>
								<td><?php echo esc_html( number_format( (float) $emp['total_hours'], 1 ) ); ?>h</td>
								<td><?php echo esc_html( number_format( (float) $emp['overtime_hours'], 1 ) ); ?>h</td>
								<?php if ( $show_labor_cost ) : ?>
									<td><?php echo esc_html( number_format( (float) ( $emp['labor_cost_egp'] ?? 0 ), 2 ) ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Recent Timesheets', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Week', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['recent'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No recent timesheets.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['recent'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['display_name'] ); ?></td>
								<td><?php echo esc_html( DateHelper::format_week_range( $row['period_start'], $row['period_end'] ) ); ?></td>
								<td><?php echo esc_html( number_format( (float) $row['client_hours'], 1 ) ); ?>h</td>
								<td><span class="etwa-status etwa-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>

	<script type="application/json" id="etwa-ca-detail-data"><?php echo wp_json_encode( array( 'trend' => $detail['trend'] ?? array() ) ); ?></script>
</div>
