<?php
/**
 * Employee vacation panel (embedded in Requests Center).
 *
 * @var bool $embedded When true, omits outer hero shell.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$embedded = $embedded ?? false;
?>
<div class="etwa-vacation-app<?php echo $embedded ? ' etwa-vacation-embedded' : ' etwa-page-shell etwa-app etwa-portal'; ?>" id="etwa-my-vacation-app">
	<?php if ( ! $embedded ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<h1><?php esc_html_e( 'My Vacation', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'View your annual leave balance, submit requests, and track history.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="etwa-vacation-tabs" role="tablist">
		<button type="button" class="etwa-vacation-tab is-active" data-tab="overview"><?php esc_html_e( 'Overview', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="calendar"><?php esc_html_e( 'Calendar', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="requests"><?php esc_html_e( 'My Requests', 'etwa' ); ?></button>
		<button type="button" class="etwa-vacation-tab" data-tab="history"><?php esc_html_e( 'History', 'etwa' ); ?></button>
	</div>

	<section class="etwa-vacation-panel is-active" data-panel="overview">
		<div id="etwa-vacation-notifications" class="etwa-vacation-notifications"></div>

		<h2 id="etwa-my-vacation-name"></h2>
		<div id="etwa-my-vacation-cards" class="etwa-vacation-cards"></div>

		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Request Leave', 'etwa' ); ?></h2>
			<form id="etwa-vacation-request-form" class="etwa-form-grid">
				<label><?php esc_html_e( 'Leave Type', 'etwa' ); ?><select name="leave_type_id" id="etwa-request-leave-type" class="etwa-input" required></select></label>
				<label><?php esc_html_e( 'Start Date', 'etwa' ); ?><input type="date" name="start_date" id="etwa-request-start" class="etwa-input" required /></label>
				<label><?php esc_html_e( 'End Date', 'etwa' ); ?><input type="date" name="end_date" id="etwa-request-end" class="etwa-input" required /></label>
				<label class="etwa-span-2"><?php esc_html_e( 'Reason', 'etwa' ); ?><textarea name="reason" class="etwa-input" rows="2" required placeholder="<?php esc_attr_e( 'Why are you requesting this leave?', 'etwa' ); ?>"></textarea></label>
				<div class="etwa-span-2">
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Submit Request', 'etwa' ); ?></button>
				</div>
			</form>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="calendar" hidden>
		<div class="etwa-panel">
			<?php
			$id_prefix     = 'etwa-my-vacation';
			$employee_view = true;
			include ETWA_PLUGIN_DIR . 'templates/partials/vacation-calendar-panel.php';
			?>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="requests" hidden>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'My Requests', 'etwa' ); ?></h2>
			<div class="etwa-table-wrap">
				<table class="etwa-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Leave Type', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Dates', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Days', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody id="etwa-my-vacation-requests"></tbody>
				</table>
			</div>
			<div id="etwa-my-requests-pagination" class="etwa-pagination"></div>
		</div>
	</section>

	<section class="etwa-vacation-panel" data-panel="history" hidden>
		<div class="etwa-panel">
			<h2><?php esc_html_e( 'Vacation History', 'etwa' ); ?></h2>
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
						</tr>
					</thead>
					<tbody id="etwa-my-vacation-history"></tbody>
				</table>
			</div>
			<div id="etwa-my-vacation-pagination" class="etwa-pagination"></div>
		</div>
	</section>
</div>

<div class="etwa-modal" id="etwa-vacation-detail-modal" hidden aria-hidden="true" role="dialog">
	<div class="etwa-modal-content etwa-request-detail-modal">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close">&times;</button>
		<h3><?php esc_html_e( 'Request Details', 'etwa' ); ?></h3>
		<div id="etwa-vacation-detail-body"></div>
	</div>
</div>
