<?php
/**
 * Deliverables dashboard / scope list / scope details (shared admin + portal).
 *
 * @var string $context
 * @var bool   $can_manage_scopes
 * @var bool   $can_allocate_deliverables
 * @var bool   $can_manage_types
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context                   = $context ?? 'admin';
$in_portal                 = 'frontend' === $context;
$can_manage_scopes         = ! empty( $can_manage_scopes );
$can_allocate_deliverables = ! empty( $can_allocate_deliverables );
$can_manage_types          = ! empty( $can_manage_types ) || ( $can_manage_scopes || $can_allocate_deliverables );
$scope_id                  = isset( $_GET['scope_id'] ) ? absint( wp_unslash( (string) $_GET['scope_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$mode                      = $scope_id > 0 ? 'detail' : 'dashboard';
?>
<div
	class="etwa-app etwa-deliverables-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>"
	id="etwa-deliverables-app"
	data-mode="<?php echo esc_attr( $mode ); ?>"
	data-scope-id="<?php echo esc_attr( (string) $scope_id ); ?>"
	data-can-manage="<?php echo $can_manage_scopes ? '1' : '0'; ?>"
	data-can-allocate="<?php echo $can_allocate_deliverables ? '1' : '0'; ?>"
	data-can-manage-types="<?php echo $can_manage_types ? '1' : '0'; ?>"
>

	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero etwa-hero-compact">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Workforce Analytics', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Deliverables', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Executive analytics for scope progress, hours, and allocation activity. Browse scopes in Files.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $can_manage_types ) : ?>
		<div class="etwa-dlv-page-actions">
			<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-dlv-manage-types" data-dlv-manage-types><?php esc_html_e( 'Deliverable Types', 'etwa' ); ?></button>
		</div>
	<?php endif; ?>

	<div id="etwa-deliverables-dashboard" class="<?php echo 'detail' === $mode ? 'etwa-hidden' : ''; ?>">
		<form class="etwa-analytics-filter etwa-toolbar" id="etwa-deliverables-filters">
			<div class="etwa-analytics-filter-fields">
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'Client', 'etwa' ); ?></span>
					<select class="etwa-input" id="etwa-dlv-client-filter" name="client_id">
						<option value=""><?php esc_html_e( 'All clients', 'etwa' ); ?></option>
					</select>
				</label>
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'Scope', 'etwa' ); ?></span>
					<select class="etwa-input" id="etwa-dlv-scope-filter" name="scope_id">
						<option value=""><?php esc_html_e( 'All scopes', 'etwa' ); ?></option>
					</select>
				</label>
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'Status', 'etwa' ); ?></span>
					<select class="etwa-input" id="etwa-dlv-status-filter" name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="active"><?php esc_html_e( 'Active', 'etwa' ); ?></option>
						<option value="closed"><?php esc_html_e( 'Closed', 'etwa' ); ?></option>
						<option value="archived"><?php esc_html_e( 'Archived', 'etwa' ); ?></option>
					</select>
				</label>
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'Deliverable Type', 'etwa' ); ?></span>
					<select class="etwa-input" id="etwa-dlv-type-filter" name="deliverable_type_id">
						<option value=""><?php esc_html_e( 'All types', 'etwa' ); ?></option>
					</select>
				</label>
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'From', 'etwa' ); ?></span>
					<input type="date" class="etwa-input" id="etwa-dlv-date-from" name="date_from" />
				</label>
				<label class="etwa-analytics-field">
					<span><?php esc_html_e( 'To', 'etwa' ); ?></span>
					<input type="date" class="etwa-input" id="etwa-dlv-date-to" name="date_to" />
				</label>
			</div>
			<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Apply Filters', 'etwa' ); ?></button>
			<?php if ( $can_manage_scopes ) : ?>
				<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-dlv-create-scope"><?php esc_html_e( 'Create Scope', 'etwa' ); ?></button>
			<?php endif; ?>
		</form>

		<div class="etwa-analytics-kpi-grid" id="etwa-dlv-dashboard-kpis">
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Completion', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="completion_pct">—</strong>
					<div class="etwa-analytics-mini-bar"><span data-kpi-bar="completion_pct" style="width:0%"></span></div>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Remaining', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="remaining_qty">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Extra', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="extra_qty">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Hours', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="effort_score">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Scopes with Remaining', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="scopes_with_remaining">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Overdue Scopes', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="overdue_scopes">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Active Scopes', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-kpi="active_scopes">—</strong>
				</div>
			</article>
		</div>

		<div class="etwa-analytics-grid zwei-col">
			<section class="etwa-panel etwa-chart-card">
				<div class="etwa-panel-header">
					<h3><?php esc_html_e( 'Deliverables by Type', 'etwa' ); ?></h3>
				</div>
				<div class="etwa-chart-canvas-wrap is-loading" data-chart="by-type">
					<canvas id="etwa-dlv-chart-by-type" height="260"></canvas>
				</div>
			</section>
			<section class="etwa-panel etwa-chart-card">
				<div class="etwa-panel-header etwa-panel-header-actions">
					<h3><?php esc_html_e( 'Completion Trend', 'etwa' ); ?></h3>
					<div class="etwa-segmented" role="group" aria-label="<?php esc_attr_e( 'Completion trend grain', 'etwa' ); ?>">
						<button type="button" class="etwa-segment is-active" data-dlv-trend="month"><?php esc_html_e( 'Month', 'etwa' ); ?></button>
						<button type="button" class="etwa-segment" data-dlv-trend="week"><?php esc_html_e( 'Week', 'etwa' ); ?></button>
						<button type="button" class="etwa-segment" data-dlv-trend="scope"><?php esc_html_e( 'Scope', 'etwa' ); ?></button>
					</div>
				</div>
				<div class="etwa-chart-canvas-wrap is-loading" data-chart="completion">
					<canvas id="etwa-dlv-chart-completion" height="260"></canvas>
				</div>
			</section>
			<section class="etwa-panel etwa-chart-card">
				<div class="etwa-panel-header etwa-panel-header-actions">
					<h3><?php esc_html_e( 'Deliverables Over Time', 'etwa' ); ?></h3>
					<div class="etwa-segmented" role="group" aria-label="<?php esc_attr_e( 'Over time grain', 'etwa' ); ?>">
						<button type="button" class="etwa-segment" data-dlv-over-time="day"><?php esc_html_e( 'Day', 'etwa' ); ?></button>
						<button type="button" class="etwa-segment" data-dlv-over-time="week"><?php esc_html_e( 'Week', 'etwa' ); ?></button>
						<button type="button" class="etwa-segment is-active" data-dlv-over-time="month"><?php esc_html_e( 'Month', 'etwa' ); ?></button>
					</div>
				</div>
				<div class="etwa-chart-canvas-wrap is-loading" data-chart="over-time">
					<canvas id="etwa-dlv-chart-over-time" height="260"></canvas>
				</div>
			</section>
			<section class="etwa-panel etwa-chart-card">
				<div class="etwa-panel-header">
					<h3><?php esc_html_e( 'Hours Analytics', 'etwa' ); ?></h3>
				</div>
				<div class="etwa-dlv-effort-strip" id="etwa-dlv-effort-strip">
					<div><span class="etwa-muted"><?php esc_html_e( 'Planned', 'etwa' ); ?></span><strong data-effort="planned">—</strong></div>
					<div><span class="etwa-muted"><?php esc_html_e( 'Completed', 'etwa' ); ?></span><strong data-effort="completed">—</strong></div>
					<div><span class="etwa-muted"><?php esc_html_e( 'Hours %', 'etwa' ); ?></span><strong data-effort="pct">—</strong></div>
				</div>
				<div class="etwa-chart-canvas-wrap is-loading" data-chart="effort">
					<canvas id="etwa-dlv-chart-effort" height="220"></canvas>
				</div>
			</section>
		</div>

		<div class="etwa-analytics-grid zwei-col">
			<section class="etwa-panel">
				<div class="etwa-panel-header"><h3><?php esc_html_e( 'Scope Performance', 'etwa' ); ?></h3></div>
				<div class="etwa-dlv-perf-tabs">
					<button type="button" class="etwa-segment is-active" data-dlv-perf="top"><?php esc_html_e( 'Top', 'etwa' ); ?></button>
					<button type="button" class="etwa-segment" data-dlv-perf="lowest"><?php esc_html_e( 'Lowest', 'etwa' ); ?></button>
					<button type="button" class="etwa-segment" data-dlv-perf="overdue"><?php esc_html_e( 'Overdue', 'etwa' ); ?></button>
					<button type="button" class="etwa-segment" data-dlv-perf="exceeded"><?php esc_html_e( 'Exceeded', 'etwa' ); ?></button>
				</div>
				<div class="etwa-table-wrap">
					<table class="etwa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Scope', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Project', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Completion', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Remaining', 'etwa' ); ?></th>
							</tr>
						</thead>
						<tbody id="etwa-dlv-scope-perf-body">
							<tr><td colspan="5" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>
			<section class="etwa-panel">
				<div class="etwa-panel-header"><h3><?php esc_html_e( 'Client Performance', 'etwa' ); ?></h3></div>
				<div class="etwa-dlv-perf-tabs">
					<button type="button" class="etwa-segment is-active" data-dlv-client-perf="highest_completion"><?php esc_html_e( 'Completion', 'etwa' ); ?></button>
					<button type="button" class="etwa-segment" data-dlv-client-perf="highest_effort"><?php esc_html_e( 'Hours', 'etwa' ); ?></button>
					<button type="button" class="etwa-segment" data-dlv-client-perf="highest_extra"><?php esc_html_e( 'Extra', 'etwa' ); ?></button>
				</div>
				<div class="etwa-table-wrap">
					<table class="etwa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Scopes', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Value', 'etwa' ); ?></th>
							</tr>
						</thead>
						<tbody id="etwa-dlv-client-perf-body">
							<tr><td colspan="3" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<section class="etwa-panel">
			<div class="etwa-panel-header"><h3><?php esc_html_e( 'Quick Insights', 'etwa' ); ?></h3></div>
			<div id="etwa-dlv-insights" class="etwa-dlv-insights"></div>
		</section>

		<section class="etwa-panel">
			<div class="etwa-panel-header"><h3><?php esc_html_e( 'Recent Activity', 'etwa' ); ?></h3></div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Task Name', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Deliverables', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Total', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'User', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-dlv-activity-body">
						<tr><td colspan="7" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>

	<div id="etwa-deliverables-detail" class="<?php echo 'detail' === $mode ? '' : 'etwa-hidden'; ?>">
		<p class="etwa-back-link-wrap">
			<a href="#" class="etwa-link" id="etwa-dlv-back"><?php esc_html_e( '← Back to dashboard', 'etwa' ); ?></a>
		</p>
		<div class="etwa-panel-header etwa-dlv-detail-head">
			<div>
				<p class="etwa-muted" id="etwa-dlv-detail-client"></p>
				<p class="etwa-muted" id="etwa-dlv-detail-project"></p>
				<h2 id="etwa-dlv-detail-title"><?php esc_html_e( 'Scope', 'etwa' ); ?></h2>
				<span class="etwa-status" id="etwa-dlv-detail-status"></span>
			</div>
			<div class="etwa-hero-actions">
				<?php if ( $can_allocate_deliverables ) : ?>
					<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-dlv-allocate-btn"><?php esc_html_e( 'Allocate Deliverables', 'etwa' ); ?></button>
				<?php endif; ?>
				<?php if ( $can_manage_scopes ) : ?>
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-dlv-edit-scope"><?php esc_html_e( 'Edit Scope', 'etwa' ); ?></button>
				<?php endif; ?>
				<?php if ( $can_manage_types ) : ?>
					<button type="button" class="etwa-btn etwa-btn-ghost" data-dlv-manage-types><?php esc_html_e( 'Deliverable Types', 'etwa' ); ?></button>
				<?php endif; ?>
			</div>
		</div>

		<div class="etwa-analytics-kpi-grid" id="etwa-dlv-scope-kpis">
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Scope Progress', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-scope-kpi="completion_pct">—</strong>
					<div class="etwa-analytics-mini-bar"><span data-scope-bar="completion_pct" style="width:0%"></span></div>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Remaining', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-scope-kpi="remaining_qty">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Extra Deliverables', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-scope-kpi="extra_qty">—</strong>
				</div>
			</article>
			<article class="etwa-analytics-kpi">
				<div class="etwa-analytics-kpi-body">
					<span class="etwa-kpi-label"><?php esc_html_e( 'Hours', 'etwa' ); ?></span>
					<strong class="etwa-kpi-value" data-scope-kpi="effort_score">—</strong>
				</div>
			</article>
		</div>

		<section class="etwa-panel">
			<div class="etwa-panel-header"><h3><?php esc_html_e( 'Expected vs Delivered', 'etwa' ); ?></h3></div>
			<div id="etwa-dlv-contract-summary" class="etwa-dlv-contract-summary">
				<p class="etwa-muted"><?php esc_html_e( 'Loading…', 'etwa' ); ?></p>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Completed', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Normal', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Replacement', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Extra', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-dlv-contract-body">
						<tr><td colspan="5" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="etwa-panel">
			<div class="etwa-panel-header"><h3><?php esc_html_e( 'Allocations', 'etwa' ); ?></h3></div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Task Name', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Deliverables', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Total', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
							<?php if ( $can_allocate_deliverables ) : ?>
								<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody id="etwa-dlv-alloc-body">
						<tr><td colspan="<?php echo $can_allocate_deliverables ? '5' : '4'; ?>" class="etwa-empty-cell"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>

	<div class="etwa-modal" id="etwa-dlv-allocate-modal" hidden aria-hidden="true">
		<div class="etwa-modal-content etwa-modal-content--wide">
			<button type="button" class="etwa-modal-close-btn" data-close-modal>&times;</button>
			<h2 id="etwa-dlv-allocate-modal-title"><?php esc_html_e( 'Allocate Deliverables', 'etwa' ); ?></h2>
			<form id="etwa-dlv-allocate-form" class="etwa-form-grid">
				<input type="hidden" id="etwa-dlv-alloc-edit-id" value="" />
				<label class="etwa-span-2">
					<span><?php esc_html_e( 'Task Name', 'etwa' ); ?></span>
					<input type="text" class="etwa-input" name="task_name" id="etwa-dlv-alloc-task" required maxlength="255" />
				</label>
				<label>
					<span><?php esc_html_e( 'Completed Date', 'etwa' ); ?></span>
					<input type="date" class="etwa-input" name="completed_date" id="etwa-dlv-alloc-date" required />
				</label>
				<label class="etwa-span-2">
					<span><?php esc_html_e( 'Notes', 'etwa' ); ?></span>
					<textarea class="etwa-input" name="notes" rows="2"></textarea>
				</label>

				<div class="etwa-span-2 etwa-dlv-items-section">
					<div class="etwa-panel-header etwa-dlv-items-header">
						<h3><?php esc_html_e( 'Deliverables', 'etwa' ); ?></h3>
					</div>
					<div id="etwa-dlv-alloc-items" class="etwa-dlv-alloc-items"></div>
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-dlv-add-item"><?php esc_html_e( '+ Add Deliverable', 'etwa' ); ?></button>
					<div class="etwa-dlv-alloc-summary" id="etwa-dlv-alloc-summary" aria-live="polite">
						<span data-sum="items"><?php esc_html_e( 'Total Items:', 'etwa' ); ?> <strong>0</strong></span>
						<span data-sum="qty"><?php esc_html_e( 'Total Quantity:', 'etwa' ); ?> <strong>0</strong></span>
						<span data-sum="normal"><?php esc_html_e( 'Normal:', 'etwa' ); ?> <strong>0</strong></span>
						<span data-sum="replacement"><?php esc_html_e( 'Replacement:', 'etwa' ); ?> <strong>0</strong></span>
						<span data-sum="extra"><?php esc_html_e( 'Extra (over scope):', 'etwa' ); ?> <strong>0</strong></span>
					</div>
				</div>

				<div class="etwa-modal-actions etwa-span-2">
					<button type="button" class="etwa-btn etwa-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Allocation', 'etwa' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<div class="etwa-modal" id="etwa-dlv-alloc-detail-modal" hidden aria-hidden="true">
		<div class="etwa-modal-content">
			<button type="button" class="etwa-modal-close-btn" data-close-modal>&times;</button>
			<div id="etwa-dlv-alloc-detail-body" class="etwa-dlv-alloc-detail"></div>
			<div class="etwa-modal-actions">
				<?php if ( $can_allocate_deliverables ) : ?>
					<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-dlv-alloc-edit-btn"><?php esc_html_e( 'Edit', 'etwa' ); ?></button>
					<button type="button" class="etwa-btn etwa-btn-danger" id="etwa-dlv-alloc-delete-btn"><?php esc_html_e( 'Delete', 'etwa' ); ?></button>
				<?php endif; ?>
				<button type="button" class="etwa-btn etwa-btn-secondary" data-close-modal><?php esc_html_e( 'Close', 'etwa' ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $can_manage_scopes ) : ?>
		<div class="etwa-modal" id="etwa-dlv-scope-modal" hidden aria-hidden="true">
			<div class="etwa-modal-content etwa-modal-content--wide">
				<button type="button" class="etwa-modal-close-btn" data-close-modal>&times;</button>
				<h2 id="etwa-dlv-scope-modal-title"><?php esc_html_e( 'Create Scope', 'etwa' ); ?></h2>
				<form id="etwa-dlv-scope-form" class="etwa-form-grid">
					<input type="hidden" name="scope_id" id="etwa-dlv-scope-id" value="" />
					<label>
						<span><?php esc_html_e( 'Scope Code', 'etwa' ); ?></span>
						<input type="text" class="etwa-input" name="code" id="etwa-dlv-scope-code" readonly placeholder="<?php esc_attr_e( 'Auto-generated', 'etwa' ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Client', 'etwa' ); ?></span>
						<select class="etwa-input" name="client_id" id="etwa-dlv-scope-client" required>
							<option value=""><?php esc_html_e( 'Select client…', 'etwa' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Project (optional)', 'etwa' ); ?></span>
						<select class="etwa-input" name="project_id" id="etwa-dlv-scope-project">
							<option value=""><?php esc_html_e( 'None / Social Media', 'etwa' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Scope Name', 'etwa' ); ?></span>
						<input type="text" class="etwa-input" name="name" id="etwa-dlv-scope-name" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Start Date', 'etwa' ); ?></span>
						<input type="date" class="etwa-input" name="starts_on" id="etwa-dlv-scope-starts" />
					</label>
					<label>
						<span><?php esc_html_e( 'End Date', 'etwa' ); ?></span>
						<input type="date" class="etwa-input" name="ends_on" id="etwa-dlv-scope-ends" />
					</label>
					<label>
						<span><?php esc_html_e( 'Total Expected Deliverables', 'etwa' ); ?></span>
						<input type="number" class="etwa-input" name="contract_deliverables" id="etwa-dlv-scope-contract" min="0" step="0.01" value="0" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Status', 'etwa' ); ?></span>
						<select class="etwa-input" name="status" id="etwa-dlv-scope-status">
							<option value="active"><?php esc_html_e( 'Active', 'etwa' ); ?></option>
							<option value="closed"><?php esc_html_e( 'Closed', 'etwa' ); ?></option>
							<option value="archived"><?php esc_html_e( 'Archived', 'etwa' ); ?></option>
						</select>
					</label>
					<label class="etwa-span-2">
						<span><?php esc_html_e( 'Notes', 'etwa' ); ?></span>
						<textarea class="etwa-input" name="description" id="etwa-dlv-scope-desc" rows="2"></textarea>
					</label>

					<div class="etwa-modal-actions etwa-span-2">
						<button type="button" class="etwa-btn etwa-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
						<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Scope', 'etwa' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $can_manage_types ) : ?>
		<div class="etwa-modal" id="etwa-dlv-types-modal" hidden aria-hidden="true">
			<div class="etwa-modal-content">
				<button type="button" class="etwa-modal-close-btn" data-close-modal>&times;</button>
				<h2><?php esc_html_e( 'Deliverable Types', 'etwa' ); ?></h2>
				<form id="etwa-dlv-type-form" class="etwa-form-grid">
					<input type="hidden" name="type_id" id="etwa-dlv-type-id" value="" />
					<label>
						<span><?php esc_html_e( 'Name', 'etwa' ); ?></span>
						<input type="text" class="etwa-input" name="name" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Slug', 'etwa' ); ?></span>
						<input type="text" class="etwa-input" name="slug" />
					</label>
					<label>
						<span><?php esc_html_e( 'Hours', 'etwa' ); ?></span>
						<input type="number" class="etwa-input" name="effort_score" min="0" step="0.01" value="1" />
					</label>
					<div class="etwa-modal-actions">
						<button type="button" class="etwa-btn etwa-btn-secondary etwa-hidden" id="etwa-dlv-type-cancel-edit"><?php esc_html_e( 'Cancel Edit', 'etwa' ); ?></button>
						<button type="submit" class="etwa-btn etwa-btn-primary" id="etwa-dlv-type-submit"><?php esc_html_e( 'Add Type', 'etwa' ); ?></button>
					</div>
				</form>
				<div class="etwa-table-wrap" id="etwa-dlv-types-table-wrap">
					<table class="etwa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Active', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
							</tr>
						</thead>
						<tbody id="etwa-dlv-types-body"></tbody>
					</table>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
