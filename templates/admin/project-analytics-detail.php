<?php
/**
 * Project analytics detail page.
 *
 * @var array<string,mixed>  $detail
 * @var array<string,string> $range
 * @var string               $preset
 * @var string               $date_from
 * @var string               $date_to
 * @var string               $context
 * @var string               $portal_base
 * @var bool                 $can_export
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context && ! empty( $portal_base );
$project   = $detail['project'] ?? array();
$client    = $detail['client'] ?? array();
$summary   = $detail['summary'] ?? array();
$project_id = (int) ( $project['id'] ?? 0 );
$client_id  = (int) ( $client['id'] ?? 0 );
$client_url = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics', array( 'client_id' => $client_id, 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
	: add_query_arg( array( 'page' => 'etwa-client-analytics', 'client_id' => $client_id, 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ), admin_url( 'admin.php' ) );
?>
<div class="etwa-app etwa-client-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-client-analytics-app" data-mode="project-detail" data-project-id="<?php echo esc_attr( (string) $project_id ); ?>">

	<p class="etwa-back-link-wrap">
		<a class="etwa-link" href="<?php echo esc_url( $client_url ); ?>">&larr; <?php echo esc_html( $client['name'] ?? __( 'Client', 'etwa' ) ); ?></a>
	</p>

	<div class="etwa-hero etwa-hero-compact">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'Project', 'etwa' ); ?></p>
			<h1><?php echo esc_html( $project['name'] ?? '' ); ?></h1>
			<p class="etwa-subtitle">
				<?php
				printf(
					/* translators: %s: client name */
					esc_html__( 'Client: %s', 'etwa' ),
					esc_html( $client['name'] ?? '' )
				);
				?>
				&middot; <?php echo esc_html( $range['label'] ?? '' ); ?>
			</p>
		</div>
		<?php if ( ! empty( $can_export ) ) : ?>
			<div class="etwa-hero-actions">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-quiet" id="etwa-ca-export-excel"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-quiet" id="etwa-ca-export-csv"><?php esc_html_e( 'Export CSV', 'etwa' ); ?></button>
			</div>
		<?php endif; ?>
	</div>

	<div class="etwa-analytics-kpi-grid etwa-ca-detail-kpis">
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Total Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['total_hours'] ?? 0 ), 1 ) ); ?>h</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Regular Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['regular_hours'] ?? 0 ), 1 ) ); ?>h</strong>
			</div>
		</article>
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Overtime Hours', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value etwa-accent"><?php echo esc_html( number_format( (float) ( $summary['overtime_hours'] ?? 0 ), 1 ) ); ?>h</strong>
			</div>
		</article>
	</div>

	<div class="etwa-analytics-grid zwei-col">
		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Recent Work', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Task', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['recent_entries'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No entries for this period.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['recent_entries'] as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['entry_date'] ?? '' ); ?></td>
								<td><?php echo esc_html( $entry['time_range_label'] ?? '' ); ?></td>
								<td><?php echo esc_html( $entry['task'] ?? '' ); ?></td>
								<td><?php echo esc_html( number_format( (float) ( $entry['regular_hours'] ?? 0 ) + (float) ( $entry['overtime_hours'] ?? 0 ), 1 ) ); ?>h</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Related Scopes', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Scope', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['scopes'] ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No scopes linked to this project.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['scopes'] as $scope ) : ?>
							<tr>
								<td><?php echo esc_html( $scope['name'] ?? '' ); ?></td>
								<td><?php echo esc_html( ucfirst( (string) ( $scope['status'] ?? '' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>
</div>
