<?php
/**
 * Prospects analytics dashboard (admin + portal).
 *
 * @var string $context
 * @var string $portal_base
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Models\Prospect;
use ETWA\Services\ProspectAnalyticsService;

$context     = $context ?? ( is_admin() ? 'admin' : 'frontend' );
$portal_base = $portal_base ?? '';
$in_portal   = 'frontend' === $context && ! empty( $portal_base );

$service   = new ProspectAnalyticsService();
$preset    = isset( $_GET['preset'] ) ? sanitize_key( wp_unslash( (string) $_GET['preset'] ) ) : DateRangeHelper::PRESET_ALL_TIME; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$range     = $service->resolve_range( $preset, $from, $to );
$preset    = $range['preset'];
$date_from = $range['date_from'];
$date_to   = $range['date_to'];
$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search    = isset( $_GET['prospect_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['prospect_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( '' === $search && isset( $_GET['search'] ) ) {
	$search = sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
$interval  = isset( $_GET['interval'] ) ? sanitize_key( wp_unslash( (string) $_GET['interval'] ) ) : ProspectAnalyticsService::INTERVAL_MONTH; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $status, Prospect::statuses(), true ) ) {
	$status = '';
}
$interval = $service->sanitize_interval( $interval );

$data     = $service->dashboard( $date_from, $date_to, $status, $search, $interval );
$kpis     = $data['kpis'];
$sources  = $data['sources'];
$rows     = $data['rows'];
$presets  = DateRangeHelper::prospect_analytics_preset_labels();
$intervals = ProspectAnalyticsService::interval_labels();

$filter_url = '';
$client_analytics_url = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics' )
	: admin_url( 'admin.php?page=etwa-client-analytics' );

$status_labels = array(
	Prospect::STATUS_OPEN => __( 'Open', 'etwa' ),
	Prospect::STATUS_WON  => __( 'Won', 'etwa' ),
	Prospect::STATUS_LOST => __( 'Lost', 'etwa' ),
);

$format_rate = static function ( ?float $rate ): string {
	return null === $rate ? '—' : number_format( $rate, 1 ) . '%';
};
$format_days = static function ( ?float $days ): string {
	if ( null === $days ) {
		return '—';
	}
	$rounded = (int) round( $days );
	return sprintf(
		/* translators: %s: number of days */
		_n( '%s day', '%s days', $rounded, 'etwa' ),
		number_format( $days, 1 )
	);
};
$format_hours = static function ( float $value ): string {
	return number_format( $value, 2 ) . 'h';
};
?>
<div class="etwa-app etwa-analytics-page etwa-prospect-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-prospect-analytics-app">

	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Analytics', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Prospects Analytics', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php echo esc_html( $range['label'] ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php
	$admin_page    = 'etwa-prospect-analytics';
	$portal_view   = 'prospect-analytics';
	$filter_hidden = array(
		'status'           => $status,
		'prospect_search'  => $search,
		'interval'         => $interval,
	);
	include ETWA_PLUGIN_DIR . 'templates/partials/date-range-filter.php';
	?>

	<p class="etwa-muted etwa-pa-date-hint">
		<?php esc_html_e( 'New prospects, acquisition trends, and source analytics use Prospect Start Date. Converted prospects use the existing Convert to Client relationship and conversion date.', 'etwa' ); ?>
	</p>

	<form method="get" class="etwa-analytics-filter etwa-pa-toolbar" action="">
		<?php if ( $in_portal ) : ?>
			<input type="hidden" name="etwa_view" value="prospect-analytics" />
		<?php else : ?>
			<input type="hidden" name="page" value="etwa-prospect-analytics" />
		<?php endif; ?>
		<input type="hidden" name="preset" value="<?php echo esc_attr( $preset ); ?>" />
		<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
		<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
		<input type="hidden" name="interval" value="<?php echo esc_attr( $interval ); ?>" />
		<label class="etwa-analytics-field">
			<span><?php esc_html_e( 'Status', 'etwa' ); ?></span>
			<select name="status" class="etwa-input">
				<option value=""><?php esc_html_e( 'All', 'etwa' ); ?></option>
				<?php foreach ( $status_labels as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="etwa-analytics-field etwa-search-field">
			<span><?php esc_html_e( 'Search', 'etwa' ); ?></span>
			<input type="search" class="etwa-input" name="prospect_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search prospects…', 'etwa' ); ?>" autocomplete="off" />
		</label>
		<button type="submit" class="etwa-btn etwa-btn-secondary"><?php esc_html_e( 'Apply', 'etwa' ); ?></button>
	</form>

	<?php if ( empty( $data['has_prospects'] ) ) : ?>
		<div class="etwa-panel">
			<p class="etwa-insight-empty"><?php esc_html_e( 'Prospect Analytics will appear once prospects are created. Add a prospect with a source and start date, then return here for acquisition and conversion reporting.', 'etwa' ); ?></p>
		</div>
	<?php else : ?>

	<section class="etwa-pa-section" aria-label="<?php esc_attr_e( 'KPI overview', 'etwa' ); ?>">
		<div class="etwa-analytics-kpi-grid etwa-pa-kpi-grid">
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Total Prospects', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( (string) $kpis['total'] ); ?></strong>
					<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'All prospects on record', 'etwa' ); ?></span>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'New Prospects', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( (string) $kpis['new'] ); ?></strong>
					<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Start date in this range', 'etwa' ); ?></span>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Converted', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( (string) $kpis['converted'] ); ?></strong>
					<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Converted to a client', 'etwa' ); ?></span>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Conversion Rate', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( $format_rate( $kpis['conversion_rate'] ) ); ?></strong>
					<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Converted ÷ new prospects', 'etwa' ); ?></span>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Average Conversion Time', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( $format_days( $kpis['avg_conversion_days'] ) ); ?></strong>
					<span class="etwa-analytics-kpi-hint"><?php esc_html_e( 'Start date → conversion date', 'etwa' ); ?></span>
				</div>
			</article>
		</div>
	</section>

	<?php if ( empty( $data['has_range_data'] ) ) : ?>
		<div class="etwa-panel">
			<p class="etwa-insight-empty"><?php esc_html_e( 'No prospects have a start date in this range. Try a different date range or add start dates to existing prospects.', 'etwa' ); ?></p>
		</div>
	<?php else : ?>

	<section class="etwa-analytics-charts" aria-label="<?php esc_attr_e( 'Prospect acquisition trend', 'etwa' ); ?>">
		<article class="etwa-chart-card etwa-chart-card-wide">
			<header class="etwa-chart-card-head etwa-pa-chart-head">
				<div>
					<h2><?php esc_html_e( 'Prospect Acquisition', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'New prospects by start date', 'etwa' ); ?></p>
				</div>
				<form method="get" class="etwa-tabs etwa-pa-interval-tabs" action="" aria-label="<?php esc_attr_e( 'Acquisition interval', 'etwa' ); ?>">
					<?php if ( $in_portal ) : ?>
						<input type="hidden" name="etwa_view" value="prospect-analytics" />
					<?php else : ?>
						<input type="hidden" name="page" value="etwa-prospect-analytics" />
					<?php endif; ?>
					<input type="hidden" name="preset" value="<?php echo esc_attr( $preset ); ?>" />
					<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
					<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
					<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
					<input type="hidden" name="prospect_search" value="<?php echo esc_attr( $search ); ?>" />
					<?php foreach ( $intervals as $key => $label ) : ?>
						<button
							type="submit"
							name="interval"
							value="<?php echo esc_attr( $key ); ?>"
							class="etwa-tab<?php echo $interval === $key ? ' active' : ''; ?>"
						><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</form>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="acquisition">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-pa-acquisition-chart" height="240" role="img" aria-label="<?php esc_attr_e( 'Prospect acquisition over time', 'etwa' ); ?>"></canvas>
			</div>
		</article>
	</section>

	<section class="etwa-analytics-charts" aria-label="<?php esc_attr_e( 'Source and conversion', 'etwa' ); ?>">
		<article class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Prospects by Source', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'How prospects reached the agency', 'etwa' ); ?></p>
				</div>
			</header>
			<div class="etwa-chart-canvas-wrap is-loading" data-chart="source">
				<div class="etwa-chart-skeleton" aria-hidden="true"></div>
				<canvas id="etwa-pa-source-chart" height="240" role="img" aria-label="<?php esc_attr_e( 'Prospects by source', 'etwa' ); ?>"></canvas>
			</div>
		</article>
		<article class="etwa-chart-card">
			<header class="etwa-chart-card-head">
				<div>
					<h2><?php esc_html_e( 'Conversion Analytics', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Existing Convert to Client outcome for prospects started in this range', 'etwa' ); ?></p>
				</div>
			</header>
			<?php if ( empty( $data['has_conversions'] ) ) : ?>
				<div class="etwa-chart-canvas-wrap">
					<p class="etwa-insight-empty"><?php esc_html_e( 'No conversions yet for prospects started in this range.', 'etwa' ); ?></p>
				</div>
			<?php else : ?>
				<div class="etwa-pa-conversion-stats">
					<div>
						<span class="etwa-kpi-label"><?php esc_html_e( 'Total Prospects', 'etwa' ); ?></span>
						<strong><?php echo esc_html( (string) $kpis['new'] ); ?></strong>
					</div>
					<div>
						<span class="etwa-kpi-label"><?php esc_html_e( 'Converted Prospects', 'etwa' ); ?></span>
						<strong><?php echo esc_html( (string) $kpis['converted'] ); ?></strong>
					</div>
					<div>
						<span class="etwa-kpi-label"><?php esc_html_e( 'Conversion Rate', 'etwa' ); ?></span>
						<strong><?php echo esc_html( $format_rate( $kpis['conversion_rate'] ) ); ?></strong>
					</div>
					<div>
						<span class="etwa-kpi-label"><?php esc_html_e( 'Average Conversion Time', 'etwa' ); ?></span>
						<strong><?php echo esc_html( $format_days( $kpis['avg_conversion_days'] ) ); ?></strong>
					</div>
				</div>
			<?php endif; ?>
		</article>
	</section>

	<section class="etwa-panel etwa-pa-source-panel">
		<div class="etwa-panel-intro">
			<h2><?php esc_html_e( 'Source Performance', 'etwa' ); ?></h2>
			<p class="etwa-muted"><?php esc_html_e( 'Compare acquisition channels. Click a column heading to sort.', 'etwa' ); ?></p>
		</div>
		<?php if ( empty( $data['has_source_data'] ) && empty( $sources ) ) : ?>
			<p class="etwa-insight-empty"><?php esc_html_e( 'No prospects with a source in this range.', 'etwa' ); ?></p>
		<?php else : ?>
			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-pa-source-table" id="etwa-pa-source-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source', 'etwa' ); ?></th>
							<th class="etwa-sortable is-sorted is-desc" data-sort="total" data-type="num"><?php esc_html_e( 'Prospects', 'etwa' ); ?></th>
							<th class="etwa-sortable" data-sort="share" data-type="num"><?php esc_html_e( '% of Total', 'etwa' ); ?></th>
							<th class="etwa-sortable" data-sort="converted" data-type="num"><?php esc_html_e( 'Converted', 'etwa' ); ?></th>
							<th class="etwa-sortable" data-sort="conversion_rate" data-type="num"><?php esc_html_e( 'Conversion Rate', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $data['has_source_data'] ) ) : ?>
							<tr><td colspan="5" class="etwa-empty-cell"><?php esc_html_e( 'No prospects with a Source in this range.', 'etwa' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $sources as $source_row ) : ?>
								<?php if ( '' === (string) $source_row['source'] && (int) $source_row['total'] <= 0 ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<tr
									data-total="<?php echo esc_attr( (string) $source_row['total'] ); ?>"
									data-share="<?php echo esc_attr( (string) ( $source_row['share'] ?? 0 ) ); ?>"
									data-converted="<?php echo esc_attr( (string) $source_row['converted'] ); ?>"
									data-conversion_rate="<?php echo esc_attr( (string) ( $source_row['conversion_rate'] ?? -1 ) ); ?>"
								>
									<td><?php echo esc_html( (string) $source_row['label'] ); ?></td>
									<td><?php echo esc_html( (string) $source_row['total'] ); ?></td>
									<td><?php echo esc_html( $format_rate( $source_row['share'] ) ); ?></td>
									<td><?php echo esc_html( (string) $source_row['converted'] ); ?></td>
									<td><?php echo esc_html( $format_rate( $source_row['conversion_rate'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>

	<?php endif; ?>

	<section class="etwa-panel">
		<div class="etwa-panel-intro">
			<h2><?php esc_html_e( 'Prospects in this range', 'etwa' ); ?></h2>
			<p class="etwa-muted"><?php echo esc_html( sprintf( _n( '%d prospect', '%d prospects', count( $rows ), 'etwa' ), count( $rows ) ) ); ?></p>
		</div>
		<div class="etwa-table-wrap">
			<table class="etwa-table etwa-ca-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Prospect', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Source', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Converted', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="6" class="etwa-empty-cell"><?php esc_html_e( 'No prospects match these filters.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$detail = $in_portal
								? PortalNav::view_url( $portal_base, 'prospects', array( 'prospect_id' => $row['id'] ) )
								: add_query_arg( array( 'page' => 'etwa-prospects', 'prospect_id' => $row['id'] ), admin_url( 'admin.php' ) );
							$client_url = $row['converted_client_id'] > 0
								? add_query_arg( 'client_id', $row['converted_client_id'], $client_analytics_url )
								: '';
							?>
							<tr>
								<td><a class="etwa-link" href="<?php echo esc_url( $detail ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
								<td><span class="etwa-status etwa-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $status_labels[ $row['status'] ] ?? $row['status'] ); ?></span></td>
								<td><?php echo esc_html( $row['source_label'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $row['start_label'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $format_hours( (float) $row['hours'] ) ); ?></td>
								<td>
									<?php if ( $client_url && $row['converted_client_name'] ) : ?>
										<a class="etwa-link" href="<?php echo esc_url( $client_url ); ?>"><?php echo esc_html( $row['converted_client_name'] ); ?></a>
										<span class="etwa-muted"> · <?php echo esc_html( $row['converted_label'] ); ?></span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<script type="application/json" id="etwa-pa-chart-data"><?php echo wp_json_encode(
		array(
			'sources'     => array_values(
				array_filter(
					$sources,
					static fn( array $row ): bool => '' !== (string) ( $row['source'] ?? '' )
				)
			),
			'acquisition' => $data['acquisition'],
			'i18n'        => array(
				'noData'       => __( 'No data for this period.', 'etwa' ),
				'noSource'     => __( 'No prospects with a Source in this range.', 'etwa' ),
				'noAcquisition'=> __( 'No prospects with a start date in this range.', 'etwa' ),
				'prospects'    => __( 'Prospects', 'etwa' ),
			),
		)
	); ?></script>

	<?php endif; ?>
</div>
