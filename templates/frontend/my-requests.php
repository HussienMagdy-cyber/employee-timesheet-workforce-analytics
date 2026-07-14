<?php
/**
 * Employee requests center (vacation + WFH + permission).
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$initial_tab = isset( $_GET['etwa_request_tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_request_tab'] ) ) : 'vacation';
if ( ! in_array( $initial_tab, array( 'vacation', 'wfh', 'permission', 'holiday_work' ), true ) ) {
	$initial_tab = 'vacation';
}
?>
<div class="etwa-page-shell etwa-app etwa-portal etwa-requests-app" id="etwa-requests-app" data-initial-tab="<?php echo esc_attr( $initial_tab ); ?>">
	<div class="etwa-requests-tabs" role="tablist">
		<button type="button" class="etwa-tab<?php echo 'vacation' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="vacation"><?php esc_html_e( 'Vacation', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab<?php echo 'wfh' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="wfh"><?php esc_html_e( 'Work From Home', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab<?php echo 'permission' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="permission"><?php esc_html_e( 'Permissions', 'etwa' ); ?></button>
		<button type="button" class="etwa-tab<?php echo 'holiday_work' === $initial_tab ? ' is-active' : ''; ?>" data-request-tab="holiday_work"><?php esc_html_e( 'Work on Holiday', 'etwa' ); ?></button>
	</div>

	<section class="etwa-requests-panel<?php echo 'vacation' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="vacation"<?php echo 'vacation' === $initial_tab ? '' : ' hidden'; ?>>
		<?php
		$embedded = true;
		include ETWA_PLUGIN_DIR . 'templates/partials/vacation-employee-panel.php';
		?>
	</section>

	<section class="etwa-requests-panel<?php echo 'wfh' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="wfh"<?php echo 'wfh' === $initial_tab ? '' : ' hidden'; ?>>
		<div id="etwa-wfh-notifications" class="etwa-vacation-notifications"></div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Work From Home Request', 'etwa' ); ?></h2>
			<form id="etwa-wfh-request-form" class="etwa-form-grid" data-request-type="wfh">
				<label><?php esc_html_e( 'Request Date', 'etwa' ); ?><input type="date" name="request_date" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Work Date', 'etwa' ); ?><input type="date" name="work_date" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Start Time', 'etwa' ); ?><input type="time" name="start_time" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'End Time', 'etwa' ); ?><input type="time" name="end_time" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Reason', 'etwa' ); ?><textarea name="reason" class="etwa-input" rows="2" required></textarea></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?> <span class="etwa-muted">(<?php esc_html_e( 'optional', 'etwa' ); ?>)</span><textarea name="notes" class="etwa-input" rows="2"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Submit Request', 'etwa' ); ?></button></div>
			</form>
		</div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'My WFH Requests', 'etwa' ); ?></h2>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Work Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Approved By', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="etwa-wfh-requests-body"></tbody>
				</table>
			</div>
			<div id="etwa-wfh-requests-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-requests-panel<?php echo 'permission' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="permission"<?php echo 'permission' === $initial_tab ? '' : ' hidden'; ?>>
		<div id="etwa-permission-notifications" class="etwa-vacation-notifications"></div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Permission Request', 'etwa' ); ?></h2>
			<form id="etwa-permission-request-form" class="etwa-form-grid" data-request-type="permission">
				<label><?php esc_html_e( 'Request Date', 'etwa' ); ?><input type="date" name="request_date" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Permission Type', 'etwa' ); ?><select name="permission_type" id="etwa-permission-type" class="etwa-input" required></select></label>
				<label><?php esc_html_e( 'Reason', 'etwa' ); ?><select name="permission_reason" id="etwa-permission-reason" class="etwa-input" required></select></label>
				<label class="etwa-span-2 etwa-permission-specify-reason" id="etwa-permission-specify-wrap" hidden>
					<?php esc_html_e( 'Specify Reason', 'etwa' ); ?>
					<textarea name="specify_reason" id="etwa-permission-specify-reason" class="etwa-input" rows="2"></textarea>
				</label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?> <span class="etwa-muted">(<?php esc_html_e( 'optional', 'etwa' ); ?>)</span><textarea name="notes" class="etwa-input" rows="2"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Submit Request', 'etwa' ); ?></button></div>
			</form>
		</div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'My Permission Requests', 'etwa' ); ?></h2>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Approved By', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="etwa-permission-requests-body"></tbody>
				</table>
			</div>
			<div id="etwa-permission-requests-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-requests-panel<?php echo 'holiday_work' === $initial_tab ? ' is-active' : ''; ?>" data-request-panel="holiday_work"<?php echo 'holiday_work' === $initial_tab ? '' : ' hidden'; ?>>
		<div id="etwa-holiday-work-notifications" class="etwa-vacation-notifications"></div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Work on Holiday Request', 'etwa' ); ?></h2>
			<form id="etwa-holiday-work-request-form" class="etwa-form-grid" data-request-type="holiday_work">
				<label><?php esc_html_e( 'Request Date', 'etwa' ); ?><input type="date" name="request_date" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Work Date', 'etwa' ); ?><input type="date" name="work_date" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Start Time', 'etwa' ); ?><input type="time" name="start_time" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'End Time', 'etwa' ); ?><input type="time" name="end_time" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'Work Type', 'etwa' ); ?><select name="work_type" id="etwa-holiday-work-type" class="etwa-input" required></select></label>
				<label><?php esc_html_e( 'Client', 'etwa' ); ?><select name="client_id" id="etwa-holiday-work-client" class="etwa-input" required></select></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Task', 'etwa' ); ?><input type="text" name="task" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Reason', 'etwa' ); ?><textarea name="reason" class="etwa-input" rows="2" required></textarea></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?> <span class="etwa-muted">(<?php esc_html_e( 'optional', 'etwa' ); ?>)</span><textarea name="notes" class="etwa-input" rows="2"></textarea></label>
				<div class="etwa-span-2"><button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Submit Request', 'etwa' ); ?></button></div>
			</form>
		</div>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'My Work on Holiday Requests', 'etwa' ); ?></h2>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Work Date', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Work Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Approved By', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="etwa-holiday-work-requests-body"></tbody>
				</table>
			</div>
			<div id="etwa-holiday-work-requests-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<div class="etwa-modal" id="etwa-request-detail-modal" hidden aria-hidden="true" role="dialog">
		<div class="etwa-modal-content etwa-request-detail-modal">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close">&times;</button>
			<h3><?php esc_html_e( 'Request Details', 'etwa' ); ?></h3>
			<div id="etwa-request-detail-body"></div>
		</div>
	</div>
</div>
