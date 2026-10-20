<?php
/**
 * Client analytics listing.
 *
 * @var array<string,mixed>  $list
 * @var array<string,string> $range
 * @var string               $preset
 * @var string               $date_from
 * @var string               $date_to
 * @var string               $context
 * @var string               $portal_base
 * @var bool                 $show_labor_cost
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\RoleManager;

$context     = $context ?? 'frontend';
$in_portal   = 'frontend' === $context && ! empty( $portal_base );
$filter_url  = $in_portal ? PortalNav::view_url( $portal_base, 'client-analytics' ) : admin_url( 'admin.php?page=etwa-client-analytics' );
$items       = $list['items'] ?? array();
$total       = (int) ( $list['total'] ?? 0 );
$page        = (int) ( $list['page'] ?? 1 );
$per_page    = (int) ( $list['per_page'] ?? 25 );
$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
$search      = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';
$can_compare = current_user_can( RoleManager::CAP_ANALYTICS ) && current_user_can( RoleManager::CAP_EXPORT );
$compare_url = $in_portal
	? PortalNav::view_url( $portal_base, 'reports' ) . '#etwa-all-clients-comparison'
	: admin_url( 'admin.php?page=etwa-reports#etwa-all-clients-comparison' );
?>
<div class="etwa-app etwa-client-analytics-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-client-analytics-app" data-mode="list">

	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Analytics', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Client Analytics', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php echo esc_html( $range['label'] ?? '' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php
	$admin_page = 'etwa-client-analytics';
	include ETWA_PLUGIN_DIR . 'templates/partials/date-range-filter.php';
	?>

	<?php $standalone_projects = $standalone_projects ?? array(); ?>
	<?php if ( $standalone_projects ) : ?>
	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Workspace Projects', 'etwa' ); ?></h2>
		<p class="etwa-muted"><?php esc_html_e( 'Standalone parent projects. Hours include work logged on the parent and its sub-projects.', 'etwa' ); ?></p>
		<div class="etwa-table-wrap">
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Project', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Employees', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Overtime', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Last Activity', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $standalone_projects as $item ) : ?>
						<?php
						$project_url = $in_portal
							? PortalNav::view_url( $portal_base, 'client-analytics', array( 'project_id' => $item['id'], 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
							: add_query_arg(
								array(
									'page'       => 'etwa-client-analytics',
									'project_id' => $item['id'],
									'preset'     => $preset,
									'date_from'  => $date_from,
									'date_to'    => $date_to,
								),
								admin_url( 'admin.php' )
							);
						?>
						<tr>
							<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
							<td><?php echo esc_html( number_format( (float) $item['total_hours'], 1 ) ); ?>h</td>
							<td><?php echo esc_html( (string) $item['employee_count'] ); ?></td>
							<td><?php echo esc_html( number_format( (float) $item['overtime_hours'], 1 ) ); ?>h</td>
							<td><?php echo esc_html( $item['last_activity'] ? DateHelper::format_date( (string) $item['last_activity'] ) : '—' ); ?></td>
							<td><a class="etwa-btn etwa-btn-secondary etwa-btn-sm" href="<?php echo esc_url( $project_url ); ?>"><?php esc_html_e( 'View', 'etwa' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<div class="etwa-panel">
		<div class="etwa-toolbar">
			<label class="etwa-search-field">
				<span class="screen-reader-text"><?php esc_html_e( 'Search clients', 'etwa' ); ?></span>
				<input type="search" id="etwa-ca-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search clients…', 'etwa' ); ?>" autocomplete="off" />
			</label>
			<p class="etwa-muted" id="etwa-ca-count"><?php echo esc_html( sprintf( _n( '%d client', '%d clients', $total, 'etwa' ), $total ) ); ?></p>
			<?php if ( $can_compare ) : ?>
				<a class="etwa-btn etwa-btn-secondary" href="<?php echo esc_url( $compare_url ); ?>">
					<?php esc_html_e( 'Compare All Clients', 'etwa' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<div class="etwa-table-wrap">
			<table class="etwa-table etwa-ca-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Total Hours', 'etwa' ); ?></th>
						<?php if ( $show_labor_cost ) : ?>
							<th><?php esc_html_e( 'Labor Cost (EGP)', 'etwa' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Employees', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Overtime', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Last Activity', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-ca-body">
					<?php if ( ! $items ) : ?>
						<tr><td colspan="<?php echo $show_labor_cost ? 8 : 7; ?>" class="etwa-empty-cell"><?php esc_html_e( 'No client activity for this period.', 'etwa' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$detail_url = $in_portal
								? PortalNav::view_url( $portal_base, 'client-analytics', array( 'client_id' => $item['id'], 'preset' => $preset, 'date_from' => $date_from, 'date_to' => $date_to ) )
								: add_query_arg(
									array(
										'page'      => 'etwa-client-analytics',
										'client_id' => $item['id'],
										'preset'    => $preset,
										'date_from' => $date_from,
										'date_to'   => $date_to,
									),
									admin_url( 'admin.php' )
								);
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $item['name'] ); ?></strong>
									<code class="etwa-code-inline"><?php echo esc_html( $item['code'] ); ?></code>
								</td>
								<td><?php echo esc_html( $item['department'] ); ?></td>
								<td><?php echo esc_html( number_format( (float) $item['total_hours'], 1 ) ); ?>h</td>
								<?php if ( $show_labor_cost ) : ?>
									<td><?php echo esc_html( number_format( (float) ( $item['labor_cost_egp'] ?? 0 ), 2 ) ); ?></td>
								<?php endif; ?>
								<td><?php echo esc_html( (string) $item['employee_count'] ); ?></td>
								<td><?php echo esc_html( number_format( (float) $item['overtime_hours'], 1 ) ); ?>h</td>
								<td><?php echo esc_html( $item['last_activity'] ? DateHelper::format_date( (string) $item['last_activity'] ) : '—' ); ?></td>
								<td><a class="etwa-btn etwa-btn-secondary etwa-btn-sm" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'etwa' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="etwa-pagination" id="etwa-ca-pagination" aria-label="<?php esc_attr_e( 'Client analytics pagination', 'etwa' ); ?>">
				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php
					$page_url = add_query_arg(
						array(
							'page'      => $in_portal ? null : 'etwa-client-analytics',
							'etwa_view' => $in_portal ? 'client-analytics' : null,
							'preset'    => $preset,
							'date_from' => $date_from,
							'date_to'   => $date_to,
							'ca_page'   => $i,
							'search'    => $search ?: null,
						),
						$filter_url
					);
					?>
					<a class="etwa-btn etwa-btn-secondary<?php echo $i === $page ? ' is-active' : ''; ?>" href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( (string) $i ); ?></a>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	</div>
</div>
