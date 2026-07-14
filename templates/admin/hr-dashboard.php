<?php
/**
 * HR dashboard template.
 *
 * @var \WP_User[] $employees
 * @var array<string,int> $counts
 * @var string $review_base_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\HrReviewLinks;

$review_base_url = $review_base_url ?? admin_url( 'admin.php?page=etwa-hr' );
$counts          = $counts ?? array(
	'pending'  => 0,
	'approved' => 0,
);
?>
<div class="etwa-hr-dashboard" id="etwa-hr-app">

	<div class="etwa-toolbar etwa-hr-toolbar">
		<input type="search" class="etwa-input" id="etwa-hr-search" placeholder="<?php esc_attr_e( 'Search employees…', 'etwa' ); ?>" />
		<select class="etwa-input" id="etwa-hr-dept-filter">
			<option value=""><?php esc_html_e( 'All departments', 'etwa' ); ?></option>
		</select>
		<select class="etwa-input" id="etwa-hr-status-filter">
			<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
			<option value="submitted"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
			<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
			<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
			<option value="draft"><?php esc_html_e( 'Draft', 'etwa' ); ?></option>
		</select>
		<input type="date" class="etwa-input" id="etwa-hr-date-from" />
		<input type="date" class="etwa-input" id="etwa-hr-date-to" />
		<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-hr-filter"><?php esc_html_e( 'Apply Filters', 'etwa' ); ?></button>
	</div>

	<div class="etwa-kpi-grid">
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Employees', 'etwa' ); ?></span><strong class="etwa-kpi-value"><?php echo esc_html( (string) count( $employees ) ); ?></strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Pending Reports', 'etwa' ); ?></span><strong class="etwa-kpi-value etwa-accent"><?php echo esc_html( (string) ( $counts['pending'] ?? 0 ) ); ?></strong></div>
		<div class="etwa-kpi-card"><span class="etwa-kpi-label"><?php esc_html_e( 'Approved Reports', 'etwa' ); ?></span><strong class="etwa-kpi-value"><?php echo esc_html( (string) ( $counts['approved'] ?? 0 ) ); ?></strong></div>
	</div>

	<div class="etwa-tabs etwa-hr-tabs">
		<button type="button" class="etwa-tab active" data-tab="employees"><?php esc_html_e( 'Employees', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="all-reports"><?php esc_html_e( 'All Reports', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab" data-tab="export"><?php esc_html_e( 'Export', 'etwa' ); ?></button>
	</div>

	<div class="etwa-tab-panel active" id="etwa-tab-employees">
		<div class="etwa-panel">
			<p class="etwa-muted"><?php esc_html_e( 'Open an employee to browse all of their work weeks, then open a single week report.', 'etwa' ); ?></p>
			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-hr-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Email', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $employees as $employee ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $employee->display_name ); ?></strong></td>
								<td><?php echo esc_html( $employee->user_email ); ?></td>
								<td class="etwa-hr-row-actions">
									<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="<?php echo esc_url( HrReviewLinks::employee_weeks_url( $review_base_url, (int) $employee->ID ) ); ?>">
										<?php esc_html_e( 'View', 'etwa' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="etwa-tab-panel" id="etwa-tab-pending">
		<div class="etwa-panel">
			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-hr-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Period', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-hr-pending-body">
						<tr class="etwa-hr-loading-row"><td colspan="5"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="etwa-tab-panel" id="etwa-tab-all-reports">
		<div class="etwa-panel">
			<div class="etwa-table-wrap">
				<table class="etwa-table etwa-hr-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Period', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-hr-all-body">
						<tr class="etwa-hr-loading-row"><td colspan="5"><?php esc_html_e( 'Open this tab to load reports.', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="etwa-tab-panel" id="etwa-tab-export">
		<div class="etwa-panel etwa-export-panel">
			<h2><?php esc_html_e( 'Export Center', 'etwa' ); ?></h2>
			<p class="etwa-muted"><?php esc_html_e( 'Download Excel reports or email them to HR with Owner CC.', 'etwa' ); ?></p>
			<div class="etwa-form-grid">
				<label><?php esc_html_e( 'From', 'etwa' ); ?><input type="date" class="etwa-input" id="etwa-export-from" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>" /></label>
				<label><?php esc_html_e( 'To', 'etwa' ); ?><input type="date" class="etwa-input" id="etwa-export-to" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" /></label>
			</div>
			<div class="etwa-export-actions">
				<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-export-excel"><?php esc_html_e( 'Export to Excel', 'etwa' ); ?></button>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-email-report"><?php esc_html_e( 'Email Report (HR + CC Owner)', 'etwa' ); ?></button>
			</div>
			<p id="etwa-export-result" class="etwa-muted"></p>
		</div>
	</div>
</div>
