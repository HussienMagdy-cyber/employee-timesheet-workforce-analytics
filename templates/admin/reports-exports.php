<?php
/**
 * Centralized Reports & Exports page.
 *
 * @var string $context
 * @var bool   $can_view_labor_cost
 * @var bool   $can_view_analytics
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context             = $context ?? 'admin';
$in_portal           = 'frontend' === $context;
$can_view_labor_cost = $can_view_labor_cost ?? false;
$can_view_analytics  = $can_view_analytics ?? false;
?>
<div class="etwa-app etwa-reports-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-reports-app">

	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Workforce Analytics', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Reports & Exports', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Generate Excel reports with custom date ranges using centralized ETWA calculations.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-reports-grid">

		<section class="etwa-panel etwa-report-card" data-report="employee_timesheet">
			<header class="etwa-report-card-head">
				<h2><?php esc_html_e( 'Employee Timesheet Report', 'etwa' ); ?></h2>
				<p><?php esc_html_e( 'Detailed timesheet entries for one employee within a custom date range.', 'etwa' ); ?></p>
			</header>
			<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="employee_timesheet">
				<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
			</button>
		</section>

		<?php if ( $can_view_analytics ) : ?>
			<section class="etwa-panel etwa-report-card" data-report="client">
				<header class="etwa-report-card-head">
					<h2><?php esc_html_e( 'Client Report', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Detailed activity and hours for one client or all clients, with period totals.', 'etwa' ); ?></p>
				</header>
				<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="client">
					<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
				</button>
			</section>

			<section class="etwa-panel etwa-report-card" data-report="prospect">
				<header class="etwa-report-card-head">
					<h2><?php esc_html_e( 'Prospect Report', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Hours and activity for one prospect.', 'etwa' ); ?></p>
				</header>
				<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="prospect">
					<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
				</button>
			</section>
		<?php endif; ?>

		<?php if ( $can_view_labor_cost && $can_view_analytics ) : ?>
			<section class="etwa-panel etwa-report-card" data-report="client_labor_cost">
				<header class="etwa-report-card-head">
					<h2><?php esc_html_e( 'Client Labor Cost Report', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Employee hours, hourly costs, and labor costs for a selected client.', 'etwa' ); ?></p>
				</header>
				<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="client_labor_cost">
					<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
				</button>
			</section>
		<?php endif; ?>

		<?php if ( $can_view_analytics ) : ?>
			<section class="etwa-panel etwa-report-card" data-report="parent_project">
				<header class="etwa-report-card-head">
					<h2><?php esc_html_e( 'Parent Project Report', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Detailed hours, employees, sub-projects, and entries for a selected standalone parent project.', 'etwa' ); ?></p>
				</header>
				<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="parent_project">
					<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
				</button>
			</section>

			<section class="etwa-panel etwa-report-card" data-report="all_parent_projects_comparison">
				<header class="etwa-report-card-head">
					<h2><?php esc_html_e( 'All Parent Projects Comparison', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Compare rolled-up hours and employee activity across all standalone parent projects.', 'etwa' ); ?></p>
				</header>
				<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="all_parent_projects_comparison">
					<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
				</button>
			</section>
		<?php endif; ?>

		<section class="etwa-panel etwa-report-card" data-report="employee_hours_by_client">
			<header class="etwa-report-card-head">
				<h2><?php esc_html_e( 'Employee Hours by Client', 'etwa' ); ?></h2>
				<p><?php esc_html_e( 'See how employee hours are distributed across clients.', 'etwa' ); ?></p>
			</header>
			<button type="button" class="etwa-btn etwa-btn-primary etwa-report-export-btn" data-report="employee_hours_by_client">
				<?php esc_html_e( 'Export to Excel', 'etwa' ); ?>
			</button>
		</section>

		<?php if ( $can_view_analytics ) : ?>
			<section class="etwa-panel etwa-report-card etwa-report-card-featured" data-report="all_clients_comparison" id="etwa-all-clients-comparison">
				<header class="etwa-report-card-head">
					<p class="etwa-eyebrow"><?php esc_html_e( 'Management Report', 'etwa' ); ?></p>
					<h2><?php esc_html_e( 'All Clients Comparison', 'etwa' ); ?></h2>
					<p><?php esc_html_e( 'Compare working hours, overtime, employee allocation, and labor costs across all clients for a selected date range.', 'etwa' ); ?></p>
				</header>

				<div class="etwa-comparison-filters">
					<label class="etwa-analytics-field">
						<span><?php esc_html_e( 'Start Date', 'etwa' ); ?></span>
						<input type="date" class="etwa-input" id="etwa-comparison-from" />
					</label>
					<label class="etwa-analytics-field">
						<span><?php esc_html_e( 'End Date', 'etwa' ); ?></span>
						<input type="date" class="etwa-input" id="etwa-comparison-to" />
					</label>
					<label class="etwa-analytics-field">
						<span><?php esc_html_e( 'Department', 'etwa' ); ?></span>
						<select class="etwa-input" id="etwa-comparison-department">
							<option value=""><?php esc_html_e( 'All Departments', 'etwa' ); ?></option>
						</select>
					</label>
					<label class="etwa-analytics-field">
						<span><?php esc_html_e( 'Employee', 'etwa' ); ?></span>
						<select class="etwa-input" id="etwa-comparison-employee">
							<option value=""><?php esc_html_e( 'All Employees', 'etwa' ); ?></option>
						</select>
					</label>
				</div>

				<div class="etwa-comparison-actions">
					<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-comparison-generate">
						<?php esc_html_e( 'Generate Comparison', 'etwa' ); ?>
					</button>
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-comparison-export" hidden>
						<?php esc_html_e( 'Export Excel', 'etwa' ); ?>
					</button>
				</div>

				<div id="etwa-comparison-results" class="etwa-comparison-results" hidden>
					<div class="etwa-comparison-charts" id="etwa-comparison-charts">
						<article class="etwa-panel etwa-analytics-chart-panel">
							<h3><?php esc_html_e( 'Total Hours by Client', 'etwa' ); ?></h3>
							<div class="etwa-chart-wrap" data-chart="comparison-hours">
								<canvas id="etwa-comparison-chart-hours" height="280"></canvas>
							</div>
						</article>
						<?php if ( $can_view_labor_cost ) : ?>
							<article class="etwa-panel etwa-analytics-chart-panel">
								<h3><?php esc_html_e( 'Labor Cost by Client', 'etwa' ); ?></h3>
								<div class="etwa-chart-wrap" data-chart="comparison-cost">
									<canvas id="etwa-comparison-chart-cost" height="280"></canvas>
								</div>
							</article>
						<?php endif; ?>
						<article class="etwa-panel etwa-analytics-chart-panel">
							<h3><?php esc_html_e( 'Employee Allocation by Client', 'etwa' ); ?></h3>
							<div class="etwa-chart-wrap" data-chart="comparison-employees">
								<canvas id="etwa-comparison-chart-employees" height="280"></canvas>
							</div>
						</article>
					</div>

					<div class="etwa-panel">
						<h3><?php esc_html_e( 'Client Comparison', 'etwa' ); ?></h3>
						<div class="etwa-table-wrap">
							<table class="etwa-table etwa-comparison-table" id="etwa-comparison-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
										<th data-sort="employee_count" class="etwa-sortable"><?php esc_html_e( 'Employees', 'etwa' ); ?></th>
										<th data-sort="regular_hours" class="etwa-sortable"><?php esc_html_e( 'Regular Hours', 'etwa' ); ?></th>
										<th data-sort="overtime_hours" class="etwa-sortable"><?php esc_html_e( 'Overtime Hours', 'etwa' ); ?></th>
										<th data-sort="total_hours" class="etwa-sortable is-sorted is-desc"><?php esc_html_e( 'Total Hours', 'etwa' ); ?></th>
										<?php if ( $can_view_labor_cost ) : ?>
											<th data-sort="labor_cost_egp" class="etwa-sortable"><?php esc_html_e( 'Labor Cost', 'etwa' ); ?></th>
										<?php endif; ?>
										<th data-sort="pct_total_hours" class="etwa-sortable"><?php esc_html_e( '% of Total Hours', 'etwa' ); ?></th>
										<?php if ( $can_view_labor_cost ) : ?>
											<th data-sort="pct_labor_cost" class="etwa-sortable"><?php esc_html_e( '% of Total Labor Cost', 'etwa' ); ?></th>
										<?php endif; ?>
									</tr>
								</thead>
								<tbody id="etwa-comparison-table-body"></tbody>
								<tfoot id="etwa-comparison-table-foot"></tfoot>
							</table>
						</div>
					</div>

					<div class="etwa-panel" id="etwa-comparison-allocations">
						<h3><?php esc_html_e( 'Employee Allocation Breakdown', 'etwa' ); ?></h3>
						<div id="etwa-comparison-allocations-body"></div>
					</div>
				</div>
			</section>
		<?php endif; ?>

	</div>
</div>
