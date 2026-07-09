<?php
/**
 * Department Head dashboard.
 *
 * @var string $context admin|frontend
 * @var string $portal_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;

$context    = $context ?? 'frontend';
$in_portal  = 'frontend' === $context;
$portal_url = $portal_url ?? '';
$requests_url = $in_portal && $portal_url
	? PortalNav::view_url( $portal_url, 'requests-hr' )
	: admin_url( 'admin.php?page=etwa-requests' );
?>
<div class="etwa-page-shell etwa-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-dept-dashboard-app">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Department', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Department Dashboard', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Review pending requests, view team members, and track department activity.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-kpi-grid" id="etwa-dept-kpis">
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Awaiting Your Action', 'etwa' ); ?></span><strong id="etwa-dept-action-pending">—</strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Pending Requests', 'etwa' ); ?></span><strong id="etwa-dept-pending">—</strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Aging (>48h)', 'etwa' ); ?></span><strong id="etwa-dept-aging" class="etwa-accent">—</strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Approved (Month)', 'etwa' ); ?></span><strong id="etwa-dept-approved">—</strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Team Members', 'etwa' ); ?></span><strong id="etwa-dept-employees">—</strong></div>
	</div>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Team Timesheets', 'etwa' ); ?></h2>
		<p class="etwa-muted"><?php esc_html_e( 'Open an employee to browse all of their work weeks, then open a single week report.', 'etwa' ); ?></p>
		<div class="etwa-table-wrap">
			<table class="etwa-table etwa-hr-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Email', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Role', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="etwa-dept-employees-body">
					<tr><td colspan="4" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="etwa-panel-grid">
		<section class="etwa-panel">
			<h2><?php esc_html_e( 'This Week — Timesheet Status', 'etwa' ); ?></h2>
			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-hr-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-dept-timesheet-body">
						<tr><td colspan="4" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Quick Actions', 'etwa' ); ?></h2>
			<p class="etwa-muted"><?php esc_html_e( 'Review pending requests in the Requests center.', 'etwa' ); ?></p>
			<a class="etwa-btn etwa-btn-primary" href="<?php echo esc_url( $requests_url ); ?>"><?php esc_html_e( 'Open Team Requests', 'etwa' ); ?></a>
		</section>
	</div>

	<div class="etwa-panel-grid">
		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Team Leave Calendar', 'etwa' ); ?></h2>
			<div id="etwa-dept-leave-calendar" class="etwa-dept-leave-calendar"></div>
		</section>

		<section class="etwa-panel">
			<h2><?php esc_html_e( 'Upcoming Leave', 'etwa' ); ?></h2>
			<div id="etwa-dept-upcoming-leave" class="etwa-list-stack"></div>
		</section>
	</div>
</div>
