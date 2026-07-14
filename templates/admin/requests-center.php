<?php
/**
 * HR / Owner requests center.
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\RoleManager;

$context    = $context ?? 'frontend';
$in_portal  = 'frontend' === $context;
$can_export = current_user_can( RoleManager::CAP_EXPORT );
$initial_tab = isset( $_GET['etwa_request_tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_request_tab'] ) ) : 'vacation';
if ( ! in_array( $initial_tab, array( 'vacation', 'wfh', 'permission', 'holiday_work' ), true ) ) {
	$initial_tab = 'vacation';
}
?>
<div class="etwa-page-shell etwa-app etwa-requests-center-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>" id="etwa-requests-center-app" data-can-export="<?php echo $can_export ? '1' : '0'; ?>" data-initial-tab="<?php echo esc_attr( $initial_tab ); ?>">
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'HR', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Requests Center', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Review vacation, work from home, permission, and holiday work requests.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-bulk-toolbar" id="etwa-requests-bulk-toolbar" hidden>
		<span id="etwa-bulk-selected-count"><?php esc_html_e( '0 selected', 'etwa' ); ?></span>
		<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-sm" id="etwa-bulk-approve"><?php esc_html_e( 'Approve selected', 'etwa' ); ?></button>
	</div>

	<div class="etwa-requests-tabs etwa-vacation-tabs" role="tablist">
		<button type="button" class="etwa-vacation-tab<?php echo 'vacation' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="vacation"><?php esc_html_e( 'Vacation Requests', 'etwa' ); ?> <span id="etwa-vacation-tab-badge" class="etwa-badge" hidden></span></button>
		<button type="button" class="etwa-vacation-tab<?php echo 'wfh' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="wfh"><?php esc_html_e( 'WFH Requests', 'etwa' ); ?> <span id="etwa-wfh-tab-badge" class="etwa-badge" hidden></span></button>
		<button type="button" class="etwa-vacation-tab<?php echo 'permission' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="permission"><?php esc_html_e( 'Permission Requests', 'etwa' ); ?> <span id="etwa-permission-tab-badge" class="etwa-badge" hidden></span></button>
		<button type="button" class="etwa-vacation-tab<?php echo 'holiday_work' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="holiday_work"><?php esc_html_e( 'Work on Holiday Requests', 'etwa' ); ?> <span id="etwa-holiday-work-tab-badge" class="etwa-badge" hidden></span></button>
	</div>

	<section class="etwa-requests-panel<?php echo 'vacation' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="vacation"<?php echo 'vacation' === $initial_tab ? '' : ' hidden'; ?>>
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-vacation-filters">
				<div class="etwa-filter-row">
					<input type="search" id="etwa-vac-req-search" class="etwa-input" placeholder="<?php esc_attr_e( 'Search…', 'etwa' ); ?>" />
					<select id="etwa-vac-req-employee" class="etwa-input"><option value=""><?php esc_html_e( 'All employees', 'etwa' ); ?></option></select>
					<select id="etwa-vac-req-status" class="etwa-input">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
					<input type="date" id="etwa-vac-req-from" class="etwa-input" placeholder="<?php esc_attr_e( 'From', 'etwa' ); ?>" />
					<input type="date" id="etwa-vac-req-to" class="etwa-input" />
					<?php if ( $can_export ) : ?>
						<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-vac-req-export"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th class="etwa-col-select" aria-label="<?php esc_attr_e( 'Select', 'etwa' ); ?>"></th>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Department', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Leave Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'From', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'To', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Days', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Dept Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'HR Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Overall Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-vac-req-body"></tbody>
				</table>
			</div>
			<div id="etwa-vac-req-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-requests-panel<?php echo 'wfh' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="wfh"<?php echo 'wfh' === $initial_tab ? '' : ' hidden'; ?>>
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-vacation-filters">
				<div class="etwa-filter-row">
					<input type="search" id="etwa-wfh-req-search" class="etwa-input" placeholder="<?php esc_attr_e( 'Search…', 'etwa' ); ?>" />
					<select id="etwa-wfh-req-employee" class="etwa-input"><option value=""><?php esc_html_e( 'All employees', 'etwa' ); ?></option></select>
					<select id="etwa-wfh-req-status" class="etwa-input">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
					<input type="date" id="etwa-wfh-req-from" class="etwa-input" />
					<input type="date" id="etwa-wfh-req-to" class="etwa-input" />
					<?php if ( $can_export ) : ?>
						<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-wfh-req-export"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th class="etwa-col-select" aria-label="<?php esc_attr_e( 'Select', 'etwa' ); ?>"></th>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Work Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-wfh-req-body"></tbody>
				</table>
			</div>
			<div id="etwa-wfh-req-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-requests-panel<?php echo 'permission' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="permission"<?php echo 'permission' === $initial_tab ? '' : ' hidden'; ?>>
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-vacation-filters">
				<div class="etwa-filter-row">
					<input type="search" id="etwa-perm-req-search" class="etwa-input" placeholder="<?php esc_attr_e( 'Search…', 'etwa' ); ?>" />
					<select id="etwa-perm-req-employee" class="etwa-input"><option value=""><?php esc_html_e( 'All employees', 'etwa' ); ?></option></select>
					<select id="etwa-perm-req-status" class="etwa-input">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
					<input type="date" id="etwa-perm-req-from" class="etwa-input" />
					<input type="date" id="etwa-perm-req-to" class="etwa-input" />
					<?php if ( $can_export ) : ?>
						<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-perm-req-export"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th class="etwa-col-select" aria-label="<?php esc_attr_e( 'Select', 'etwa' ); ?>"></th>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Permission Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-perm-req-body"></tbody>
				</table>
			</div>
			<div id="etwa-perm-req-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-requests-panel<?php echo 'holiday_work' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="holiday_work"<?php echo 'holiday_work' === $initial_tab ? '' : ' hidden'; ?>>
		<div class="etwa-panel">
			<div class="etwa-toolbar etwa-vacation-filters">
				<div class="etwa-filter-row">
					<input type="search" id="etwa-holiday-req-search" class="etwa-input" placeholder="<?php esc_attr_e( 'Search…', 'etwa' ); ?>" />
					<select id="etwa-holiday-req-employee" class="etwa-input"><option value=""><?php esc_html_e( 'All employees', 'etwa' ); ?></option></select>
					<select id="etwa-holiday-req-status" class="etwa-input">
						<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Pending', 'etwa' ); ?></option>
						<option value="approved"><?php esc_html_e( 'Approved', 'etwa' ); ?></option>
						<option value="rejected"><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>
					</select>
					<input type="date" id="etwa-holiday-req-from" class="etwa-input" />
					<input type="date" id="etwa-holiday-req-to" class="etwa-input" />
					<?php if ( $can_export ) : ?>
						<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-holiday-req-export"><?php esc_html_e( 'Export Excel', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th class="etwa-col-select" aria-label="<?php esc_attr_e( 'Select', 'etwa' ); ?>"></th>
							<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Work Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Work Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
						</tr>
					</thead>
					<tbody id="etwa-holiday-req-body"></tbody>
				</table>
			</div>
			<div id="etwa-holiday-req-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<div class="etwa-modal" id="etwa-request-detail-modal" hidden aria-hidden="true" role="dialog">
		<div class="etwa-modal-content etwa-request-detail-modal">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close">&times;</button>
			<h3 id="etwa-request-detail-title"><?php esc_html_e( 'Request Details', 'etwa' ); ?></h3>
			<div id="etwa-request-detail-body"></div>
			<div id="etwa-request-detail-actions" class="etwa-request-detail-actions"></div>
		</div>
	</div>
</div>
