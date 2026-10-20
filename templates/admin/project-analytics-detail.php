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

$context     = $context ?? 'frontend';
$in_portal   = 'frontend' === $context && ! empty( $portal_base );
$project     = $detail['project'] ?? array();
$client      = $detail['client'] ?? array();
$parent_proj = $detail['parent_project'] ?? array();
$summary     = $detail['summary'] ?? array();
$project_id  = (int) ( $project['id'] ?? 0 );
$client_id   = (int) ( $client['id'] ?? 0 );
$parent_id   = (int) ( $parent_proj['id'] ?? 0 );
$includes_children = ! empty( $detail['includes_children'] );
$list_url    = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics', array( 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
	: add_query_arg( array( 'page' => 'etwa-client-analytics', 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ), admin_url( 'admin.php' ) );
$filter_url  = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics' )
	: admin_url( 'admin.php?page=etwa-client-analytics' );

if ( $client_id ) {
	$back_url  = $in_portal
		? PortalNav::view_url( $portal_base, 'client-analytics', array( 'client_id' => $client_id, 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
		: add_query_arg(
			array(
				'page'      => 'etwa-client-analytics',
				'client_id' => $client_id,
				'preset'    => $preset,
				'date_from' => $date_from,
				'date_to'   => $date_to,
			),
			admin_url( 'admin.php' )
		);
	$back_label = $client['name'] ?? __( 'Client Analytics', 'etwa' );
} elseif ( $parent_id ) {
	$back_url  = $in_portal
		? PortalNav::view_url( $portal_base, 'client-analytics', array( 'project_id' => $parent_id, 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
		: add_query_arg(
			array(
				'page'       => 'etwa-client-analytics',
				'project_id' => $parent_id,
				'preset'     => $preset,
				'date_from'  => $date_from,
				'date_to'    => $date_to,
			),
			admin_url( 'admin.php' )
		);
	$back_label = $parent_proj['name'] ?? __( 'Parent Project', 'etwa' );
} else {
	$back_url   = $list_url;
	$back_label = __( 'Client Analytics', 'etwa' );
}

$project_url = static function ( int $pid ) use ( $in_portal, $portal_base, $preset, $date_from, $date_to ): string {
	return $in_portal
		? PortalNav::view_url( $portal_base, 'client-analytics', array( 'project_id' => $pid, 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
		: add_query_arg(
			array(
				'page'       => 'etwa-client-analytics',
				'project_id' => $pid,
				'preset'     => $preset,
				'date_from'  => $date_from,
				'date_to'    => $date_to,
			),
			admin_url( 'admin.php' )
		);
};
?>
<div class="etwa-app etwa-client-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-client-analytics-app" data-mode="project-detail" data-project-id="<?php echo esc_attr( (string) $project_id ); ?>">

	<p class="etwa-back-link-wrap">
		<a class="etwa-link" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html( $back_label ); ?></a>
	</p>

	<div class="etwa-hero etwa-hero-compact">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php echo $client_id ? esc_html__( 'Client Project', 'etwa' ) : ( $parent_id ? esc_html__( 'Sub-project', 'etwa' ) : esc_html__( 'Parent Project', 'etwa' ) ); ?></p>
			<h1><?php echo esc_html( $project['name'] ?? '' ); ?></h1>
			<p class="etwa-subtitle">
				<?php if ( $client_id ) : ?>
					<?php
					printf(
						/* translators: %s: client name */
						esc_html__( 'Client: %s', 'etwa' ),
						esc_html( $client['name'] ?? '' )
					);
					?>
				<?php elseif ( $parent_id ) : ?>
					<?php
					printf(
						/* translators: %s: parent project name */
						esc_html__( 'Parent: %s', 'etwa' ),
						esc_html( $parent_proj['name'] ?? '' )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Standalone project', 'etwa' ); ?>
					<?php if ( $includes_children ) : ?>
						&middot; <?php esc_html_e( 'Includes sub-project hours', 'etwa' ); ?>
					<?php endif; ?>
				<?php endif; ?>
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

	<?php
	$admin_page = 'etwa-client-analytics';
	include ETWA_PLUGIN_DIR . 'templates/partials/date-range-filter.php';
	?>

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
		<article class="etwa-analytics-kpi">
			<div class="etwa-analytics-kpi-body">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Employees', 'etwa' ); ?></span>
				<strong class="etwa-kpi-value"><?php echo esc_html( (string) ( $summary['employee_count'] ?? 0 ) ); ?></strong>
			</div>
		</article>
		<?php if ( $includes_children ) : ?>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Direct Hours', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['direct_hours'] ?? 0 ), 1 ) ); ?>h</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Sub-project Hours', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value"><?php echo esc_html( number_format( (float) ( $summary['child_hours'] ?? 0 ), 1 ) ); ?>h</strong>
				</div>
			</article>
		<?php endif; ?>
	</div>

	<?php $child_breakdown = $detail['child_breakdown'] ?? array(); ?>
	<?php if ( $child_breakdown ) : ?>
	<section class="etwa-panel">
		<h2><?php esc_html_e( 'Project Breakdown', 'etwa' ); ?></h2>
		<table class="etwa-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Project', 'etwa' ); ?></th>
					<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $child_breakdown as $row ) : ?>
					<?php $pid = (int) ( $row['project_id'] ?? 0 ); ?>
					<tr>
						<td>
							<?php if ( $pid && $pid !== $project_id ) : ?>
								<a class="etwa-link" href="<?php echo esc_url( $project_url( $pid ) ); ?>"><?php echo esc_html( $row['name'] ?? '' ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $row['name'] ?? '' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format( (float) ( $row['total_hours'] ?? 0 ), 1 ) ); ?>h</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
	<?php endif; ?>

	<div class="etwa-analytics-grid zwei-col">
		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Employee Breakdown', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Regular', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'OT', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Combined', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['employees'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No entries for this period.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['employees'] as $emp ) : ?>
							<tr>
								<td><?php echo esc_html( $emp['display_name'] ?? '' ); ?></td>
								<td><?php echo esc_html( number_format( (float) ( $emp['regular_hours'] ?? 0 ), 1 ) ); ?>h</td>
								<td><?php echo esc_html( number_format( (float) ( $emp['overtime_hours'] ?? 0 ), 1 ) ); ?>h</td>
								<td><?php echo esc_html( number_format( (float) ( $emp['combined_hours'] ?? $emp['total_hours'] ?? 0 ), 1 ) ); ?>h</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Recent Work', 'etwa' ); ?></h2>
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
						<?php if ( $includes_children ) : ?>
							<th><?php esc_html_e( 'Project', 'etwa' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Task', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $detail['recent_entries'] ) ) : ?>
						<tr><td colspan="<?php echo $includes_children ? 5 : 4; ?>"><?php esc_html_e( 'No entries for this period.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $detail['recent_entries'] as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['entry_date'] ?? '' ); ?></td>
								<td><?php echo esc_html( $entry['time_range_label'] ?? '' ); ?></td>
								<?php if ( $includes_children ) : ?>
									<td><?php echo esc_html( $entry['project_name'] ?? '' ); ?></td>
								<?php endif; ?>
								<td><?php echo esc_html( $entry['task'] ?? '' ); ?></td>
								<td><?php echo esc_html( number_format( (float) ( $entry['regular_hours'] ?? 0 ) + (float) ( $entry['overtime_hours'] ?? 0 ), 1 ) ); ?>h</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
	</div>

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
