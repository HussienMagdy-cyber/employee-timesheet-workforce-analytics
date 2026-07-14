<?php
/**
 * Vacation management page (HR/Owner).
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\RoleManager;

$context   = $context ?? 'frontend';
$in_portal = 'frontend' === $context;
$can_delete = current_user_can( RoleManager::CAP_LOCK_PERIODS );
$can_export = current_user_can( RoleManager::CAP_EXPORT );
?>
<div class="etwa-page-shell etwa-app etwa-vacation-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-vacation-app" data-can-delete="<?php echo $can_delete ? '1' : '0'; ?>" data-can-export="<?php echo $can_export ? '1' : '0'; ?>">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'HR', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Vacation & Leave Management', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Allocate leave, record usage, view balances, and calendar.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-vacation-tabs" role="tablist">
		<button type="button" class="etwa-vacation-tab is-active" data-tab="profile"><?php esc_html_e( 'Profile', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="allocate"><?php esc_html_e( 'Set Balance', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="usage"><?php esc_html_e( 'Record Leave', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="history"><?php esc_html_e( 'History', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="calendar"><?php esc_html_e( 'Calendar', 'etwa' ); ?></button>
	</div>

	<section class="etwa-vacation-panel is-active" data-panel="profile">
		<div class="etwa-toolbar">
			<label class="etwa-vacation-employee-select">
				<span><?php esc_html_e( 'Employee', 'etwa' ); ?></span>
				<select id="etwa-vacation-employee" class="etwa-input"></select>
			</label>
		</div>
		<h2 id="etwa-vacation-profile-name"><?php esc_html_e( 'Employee Profile', 'etwa' ); ?></h2>
		<div id="etwa-vacation-balance-cards" class="etwa-vacation-cards"></div>
		<div class="etwa-panel etwa-edit-balance-panel">
			<h3><?php esc_html_e( 'Edit Balance', 'etwa' ); ?></h3>
			<p class="etwa-muted etwa-panel-intro"><?php esc_html_e( 'Add days, subtract days, or set the remaining balance directly. Changes are logged in vacation history.', 'etwa' ); ?></p>
			<form id="etwa-vacation-adjust-form" class="etwa-form-grid">
				<input type="hidden" name="user_id" id="etwa-adjust-user" value="" />
				<label>
					<?php esc_html_e( 'Adjustment', 'etwa' ); ?>
					<select name="mode" id="etwa-adjust-mode" class="etwa-input" required>
						<option value="add"><?php esc_html_e( 'Add days', 'etwa' ); ?></option>
						<option value="subtract"><?php esc_html_e( 'Subtract days', 'etwa' ); ?></option>
						<option value="set_remaining"><?php esc_html_e( 'Set remaining to', 'etwa' ); ?></option>
					</select>
				</label>
				<label>
					<span id="etwa-adjust-days-label"><?php esc_html_e( 'Days', 'etwa' ); ?></span>
					<input type="number" name="days" id="etwa-adjust-days" class="etwa-input" min="0" step="0.25" required />
				</label>
				<label><?php esc_html_e( 'Effective Date', 'etwa' ); ?><input type="date" name="effective_date" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Reason / Notes', 'etwa' ); ?><textarea name="notes" class="etwa-input" rows="2" placeholder="<?php esc_attr_e( 'e.g. Manual correction', 'etwa' ); ?>"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Apply Adjustment', 'etwa' ); ?></button></div>
			</form>
		</div>
		<div class="etwa-panel etwa-set-annual-panel">
			<h3><?php esc_html_e( 'Set Annual Leave Balance', 'etwa' ); ?></h3>
			<p class="etwa-muted etwa-panel-intro"><?php esc_html_e( 'All leave types (except unpaid) deduct from the annual leave balance.', 'etwa' ); ?></p>
			<form id="etwa-set-annual-form" class="etwa-form-grid">
				<input type="hidden" name="user_id" id="etwa-set-annual-user" value="" />
				<label><?php esc_html_e( 'Annual Leave Days', 'etwa' ); ?><input type="number" name="days" class="etwa-input" min="0.25" step="0.25" required placeholder="<?php esc_attr_e( 'e.g. 21', 'etwa' ); ?>" /></label>
				<label><?php esc_html_e( 'Effective Date', 'etwa' ); ?><input type="date" name="effective_date" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?><textarea name="notes" class="etwa-input" rows="2" placeholder="<?php esc_attr_e( 'e.g. 2026 entitlement', 'etwa' ); ?>"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Annual Balance', 'etwa' ); ?></button></div>
			</form>
		</div>
		<div class="etwa-panel">
			<h3><?php esc_html_e( 'Usage by Leave Type', 'etwa' ); ?></h3>
			<div id="etwa-vacation-usage-breakdown" class="etwa-vacation-cards etwa-vacation-usage-cards"></div>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="allocate" hidden>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Set Annual Leave Balance', 'etwa' ); ?></h2>
			<p class="etwa-muted etwa-panel-intro"><?php esc_html_e( 'Add annual leave days to an employee. All approved leave deducts from this pool.', 'etwa' ); ?></p>
			<form id="etwa-vacation-allocate-form" class="etwa-form-grid">
				<label><?php esc_html_e( 'Employee', 'etwa' ); ?><select name="user_id" class="etwa-input etwa-vacation-employee-field" required></select></label>
				<input type="hidden" name="leave_type_id" class="etwa-annual-leave-type-id" value="" />
				<label><?php esc_html_e( 'Annual Leave Days', 'etwa' ); ?><input type="number" name="days" class="etwa-input" min="0.25" step="0.25" required /></label>
				<label><?php esc_html_e( 'Effective Date', 'etwa' ); ?><input type="date" name="effective_date" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?><textarea name="notes" class="etwa-input" rows="2"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Annual Balance', 'etwa' ); ?></button></div>
			</form>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="usage" hidden>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Record Leave Usage', 'etwa' ); ?></h2>
			<form id="etwa-vacation-record-form" class="etwa-form-grid">
				<label><?php esc_html_e( 'Employee', 'etwa' ); ?><select name="user_id" class="etwa-input etwa-vacation-employee-field" required></select></label>
				<label><?php esc_html_e( 'Leave Type', 'etwa' ); ?><select name="leave_type_id" class="etwa-input etwa-vacation-type-all" required></select></label>
				<label><?php esc_html_e( 'Start Date', 'etwa' ); ?><input type="date" name="start_date" id="etwa-vacation-start" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'End Date', 'etwa' ); ?><input type="date" name="end_date" id="etwa-vacation-end" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Number of Days', 'etwa' ); ?><input type="number" name="days" id="etwa-vacation-days" class="etwa-input" min="0.25" step="0.25" required /></label>
				<label><?php esc_html_e( 'Status', 'etwa' ); ?>
					<select name="status" class="etwa-input">
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
				</label>
				<label class="etwa-span-2"><?php esc_html_e( 'Reason', 'etwa' ); ?><textarea name="reason" class="etwa-input" rows="2"></textarea></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?><textarea name="notes" class="etwa-input" rows="2"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Leave Record', 'etwa' ); ?></button></div>
			</form>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="history" hidden>
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-vacation-filters">
				<h2><?php esc_html_e( 'Vacation History', 'etwa' ); ?></h2>
				<div class="etwa-filter-row">
					<select id="etwa-vacation-filter-employee" class="etwa-input"><option value=""><?php esc_html_e( 'All employees', 'etwa' ); ?></option></select>
					<select id="etwa-vacation-filter-type" class="etwa-input"><option value=""><?php esc_html_e( 'All leave types', 'etwa' ); ?></option></select>
					<select id="etwa-vacation-filter-status" class="etwa-input">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
					<input type="month" id="etwa-vacation-filter-month" class="etwa-input" />
					<input type="date" id="etwa-vacation-filter-from" class="etwa-input" placeholder="<?php esc_attr_e( 'From', 'etwa' ); ?>" />
					<input type="date" id="etwa-vacation-filter-to" class="etwa-input" />
					<input type="search" id="etwa-vacation-filter-keyword" class="etwa-input" placeholder="<?php esc_attr_e( 'Keyword…', 'etwa' ); ?>" />
					<?php if ( $can_export ) : ?>
						<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-vacation-export"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Leave Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Days', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Added By', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Created', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="etwa-vacation-history-body"></tbody>
				</table>
			</div>
			<div id="etwa-vacation-history-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="calendar" hidden>
		<div class="etwa-panel">
			<?php
			$id_prefix     = 'etwa-vacation';
			$employee_view = false;
			include ETWA_PLUGIN_DIR . 'templates/partials/vacation-calendar-panel.php';
			?>
		</div>
	</section>

	<div class="etwa-modal" id="etwa-vacation-edit-modal" hidden aria-hidden="true" role="dialog">
		<div class="etwa-modal-content">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close">&times;</button>
			<h3 id="etwa-vacation-edit-title"><?php esc_html_e( 'Edit', 'etwa' ); ?></h3>
			<form id="etwa-vacation-edit-form" class="etwa-form-grid"></form>
		</div>
	</div>
</div>
