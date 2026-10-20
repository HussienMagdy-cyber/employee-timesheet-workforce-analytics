<?php
/**
 * Shared week timesheet partial (Sun–Thu day cards).
 *
 * @var array $view
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Repositories\ClientRepository;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\EntryTypeHelper;
use ETWA\Services\ProjectService;

extract( $view, EXTR_SKIP );

$day_meta = $day_meta ?? array();

$is_frontend = 'frontend' === $context;
$in_portal   = $is_frontend && ! empty( $portal_base );
$app_class   = 'etwa-app etwa-timesheet-page' . ( $dark_mode ? ' etwa-dark' : '' ) . ( $is_frontend ? ' etwa-portal' : ' wrap' ) . ( $in_portal ? ' etwa-in-portal' : '' );
$week_param  = 'etwa_week';
$nav_base    = $is_frontend
	? ( ! empty( $portal_base ) ? $portal_base : ( get_permalink() ?: home_url( '/' ) ) )
	: admin_url( 'admin.php?page=etwa-dashboard' );

$week_nav_args = array();
if ( ! empty( $hr_review ) && ! empty( $review_user_id ) ) {
	$week_nav_args['review_user'] = (int) $review_user_id;
	if ( $is_frontend && ! empty( $portal_base ) ) {
		$nav_base = $portal_base;
		$week_nav_args['etwa_view'] = ! empty( $portal_view ) ? (string) $portal_view : 'hr';
	} else {
		$nav_base = admin_url( 'admin.php?page=' . ( ! empty( $review_page ) ? (string) $review_page : 'etwa-hr' ) );
	}
} elseif ( $is_frontend && ! empty( $portal_base ) ) {
	$week_nav_args['etwa_view'] = 'timesheet';
}
$break_client_id = 0;
$all_clients_id  = 0;
foreach ( $clients as $client ) {
	$code = strtoupper( $client->code );
	if ( ClientRepository::BREAK_CODE === $code ) {
		$break_client_id = (int) $client->id;
	}
	if ( ClientRepository::ALL_CLIENTS_CODE === $code ) {
		$all_clients_id = (int) $client->id;
	}
}
$hr_can_edit = ! empty( $hr_can_edit );
?>
<div class="<?php echo esc_attr( $app_class ); ?>" id="etwa-employee-app" data-context="<?php echo esc_attr( $context ); ?>" data-week-start="<?php echo esc_attr( $week['period_start'] ); ?>" data-break-client-id="<?php echo esc_attr( (string) $break_client_id ); ?>" data-all-clients-client-id="<?php echo esc_attr( (string) $all_clients_id ); ?>"<?php echo $hr_can_edit ? ' data-hr-edit="1"' : ''; ?><?php echo ! empty( $hr_review ) ? ' data-review-user-id="' . esc_attr( (string) ( $review_user_id ?? $timesheet->user_id ) ) . '"' : ''; ?>>
	<?php if ( ! $in_portal ) : ?>
	<div class="etwa-hero">
		<div class="etwa-hero-copy">
			<p class="etwa-eyebrow"><?php esc_html_e( 'Workforce Timesheet', 'etwa' ); ?></p>
			<h1>
				<?php if ( ! empty( $hr_review ) ) : ?>
					<?php esc_html_e( 'Week Report', 'etwa' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'My Timesheet', 'etwa' ); ?>
				<?php endif; ?>
			</h1>
			<p class="etwa-subtitle">
				<?php if ( ! empty( $hr_review ) ) : ?>
					<?php esc_html_e( 'Day-by-day breakdown for HR review (Sun–Sat).', 'etwa' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Track your work week day by day — Sunday through Saturday.', 'etwa' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="etwa-hero-actions">
			<button type="button" class="etwa-btn etwa-btn-ghost etwa-dark-toggle" id="etwa-dark-toggle"><?php esc_html_e( 'Dark mode', 'etwa' ); ?></button>
		</div>
	</div>
	<?php endif; ?>

	<div class="etwa-week-nav">
		<a class="etwa-week-arrow etwa-week-arrow-prev" href="<?php echo esc_url( add_query_arg( array_merge( $week_nav_args, array( $week_param => $prev_week_start ) ), $nav_base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous week', 'etwa' ); ?>">
			<span class="etwa-week-arrow-icon" aria-hidden="true"></span>
		</a>
		<div class="etwa-week-badge">
			<span class="etwa-week-label"><?php esc_html_e( 'Week', 'etwa' ); ?></span>
			<strong><?php echo esc_html( $week_label ); ?></strong>
			<span class="etwa-week-range"><?php echo esc_html( $week['period_start'] . ' — ' . $week['period_end'] ); ?></span>
			<span class="etwa-week-days-tag"><?php esc_html_e( 'Sun – Sat', 'etwa' ); ?></span>
		</div>
		<a class="etwa-week-arrow etwa-week-arrow-next" href="<?php echo esc_url( add_query_arg( array_merge( $week_nav_args, array( $week_param => $next_week_start ) ), $nav_base ) ); ?>" aria-label="<?php esc_attr_e( 'Next week', 'etwa' ); ?>">
			<span class="etwa-week-arrow-icon" aria-hidden="true"></span>
		</a>
	</div>

	<div class="etwa-kpi-grid etwa-timesheet-kpis">
		<div class="etwa-kpi-card">
			<span class="etwa-kpi-label"><?php esc_html_e( 'Worked Hours', 'etwa' ); ?></span>
			<strong class="etwa-kpi-value"><?php echo esc_html( number_format( $timesheet->total_regular_hours + $timesheet->total_overtime_hours, 2 ) ); ?></strong>
		</div>
		<div class="etwa-kpi-card etwa-kpi-status">
			<span class="etwa-kpi-label"><?php esc_html_e( 'Status', 'etwa' ); ?></span>
			<span class="etwa-status etwa-status-<?php echo esc_attr( $timesheet->status ); ?>">
				<span class="etwa-status-dot" aria-hidden="true"></span>
				<span class="etwa-status-label"><?php echo esc_html( ucfirst( $timesheet->status ) ); ?></span>
			</span>
		</div>
		<?php if ( empty( $hr_review ) && ! $readonly ) : ?>
			<div class="etwa-kpi-card etwa-kpi-draft-sync">
				<span class="etwa-kpi-label"><?php esc_html_e( 'Draft Sync', 'etwa' ); ?></span>
				<span class="etwa-draft-sync-status is-<?php echo esc_attr( $draft_sync_state ?? 'never_saved' ); ?>" id="etwa-draft-sync-status">
					<?php echo esc_html( $draft_sync_label ?? __( 'Not saved for review yet', 'etwa' ) ); ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $hr_review ) && ! $readonly && in_array( $draft_sync_state ?? 'never_saved', array( 'unsaved', 'never_saved' ), true ) ) : ?>
		<div class="etwa-alert etwa-alert-info" id="etwa-draft-sync-notice">
			<?php esc_html_e( 'HR can only see your timesheet after you click Save Draft. Until then, your latest edits stay private.', 'etwa' ); ?>
		</div>
	<?php else : ?>
		<div class="etwa-alert etwa-alert-info" id="etwa-draft-sync-notice" hidden></div>
	<?php endif; ?>

	<?php if ( $readonly ) : ?>
		<div class="etwa-alert etwa-alert-warning"><?php esc_html_e( 'This week is approved or locked. Editing is disabled.', 'etwa' ); ?></div>
	<?php endif; ?>

	<div class="etwa-week-toolbar">
		<?php if ( ! $readonly && empty( $hr_review ) ) : ?>
			<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-copy-week"><?php esc_html_e( 'Copy Previous Week', 'etwa' ); ?></button>
		<?php endif; ?>
	</div>

	<div class="etwa-week-grid etwa-week-grid-seven">
		<?php foreach ( $work_dates as $date ) : ?>
			<?php
			$day       = $day_labels[ $date ];
			$day_items = $entries_by_day[ $date ] ?? array();
			$day_hours = $day_totals[ $date ] ?? 0;
			$is_today  = $date === current_time( 'Y-m-d' );
			$meta      = $day_meta[ $date ] ?? array( 'status' => 'working', 'label' => __( 'Working', 'etwa' ), 'holiday_name' => null, 'vacation_label' => null );
			$status    = $meta['status'] ?? 'working';
			$can_log   = 'working' === $status;
			$show_entry_actions = ! $readonly && ( 'vacation' !== $status || $hr_can_edit );
			?>
			<section class="etwa-day-card etwa-day-<?php echo esc_attr( $status ); ?><?php echo $is_today ? ' is-today' : ''; ?><?php echo ! $can_log ? ' is-non-working' : ''; ?>" data-date="<?php echo esc_attr( $date ); ?>" data-day-status="<?php echo esc_attr( $status ); ?>">
				<header class="etwa-day-head">
					<div>
						<span class="etwa-day-name"><?php echo esc_html( $day['name'] ); ?></span>
						<span class="etwa-day-date"><?php echo esc_html( $day['formatted'] ); ?></span>
					</div>
					<div class="etwa-day-head-end">
						<?php if ( 'holiday' === $status ) : ?>
							<span class="etwa-day-badge etwa-day-badge-holiday"><?php echo esc_html( strtoupper( $meta['label'] ?? __( 'Holiday', 'etwa' ) ) ); ?></span>
						<?php elseif ( 'vacation' === $status ) : ?>
							<span class="etwa-day-badge etwa-day-badge-vacation"><?php esc_html_e( 'VACATION', 'etwa' ); ?></span>
						<?php elseif ( 'day_off' === $status ) : ?>
							<span class="etwa-day-badge etwa-day-badge-off"><?php esc_html_e( 'DAY OFF', 'etwa' ); ?></span>
						<?php else : ?>
							<span class="etwa-day-hours"><?php echo esc_html( number_format( $day_hours, 1 ) ); ?>h</span>
						<?php endif; ?>
					</div>
				</header>

				<div class="etwa-day-entries">
					<?php if ( 'holiday' === $status && empty( $day_items ) ) : ?>
						<p class="etwa-day-holiday-msg"><?php echo esc_html( $meta['holiday_name'] ?? __( 'Holiday', 'etwa' ) ); ?></p>
					<?php elseif ( 'vacation' === $status && empty( $day_items ) ) : ?>
						<p class="etwa-day-vacation-msg"><?php echo esc_html( $meta['vacation_label'] ?? __( 'Vacation', 'etwa' ) ); ?></p>
					<?php elseif ( 'day_off' === $status && empty( $day_items ) ) : ?>
						<p class="etwa-day-empty"><?php esc_html_e( 'Day off', 'etwa' ); ?></p>
					<?php elseif ( empty( $day_items ) ) : ?>
						<p class="etwa-day-empty"><?php esc_html_e( 'No entries', 'etwa' ); ?></p>
					<?php else : ?>
						<?php foreach ( $day_items as $entry ) : ?>
							<?php $is_break_entry = (int) $entry->client_id === $break_client_id; ?>
							<?php $is_all_clients_entry = (int) $entry->client_id === $all_clients_id; ?>
							<?php
							$entry_hours = $is_break_entry
								? $entry->duration_minutes / 60
								: $entry->regular_hours + $entry->overtime_hours;
							?>
							<article
								class="etwa-entry-chip is-clickable<?php echo $is_break_entry ? ' is-break' : ''; ?>"
								data-entry-id="<?php echo esc_attr( (string) $entry->id ); ?>"
								data-entry-date="<?php echo esc_attr( $entry->entry_date ); ?>"
								data-start-time="<?php echo esc_attr( $entry->start_time ); ?>"
								data-end-time="<?php echo esc_attr( $entry->end_time ); ?>"
								data-client-id="<?php echo esc_attr( (string) ( $entry->client_id ?? '' ) ); ?>"
								data-project-id="<?php echo esc_attr( (string) ( $entry->project_id ?? '' ) ); ?>"
								data-prospect-id="<?php echo esc_attr( (string) ( $entry->prospect_id ?? '' ) ); ?>"
								data-task="<?php echo esc_attr( $entry->task ); ?>"
								data-notes="<?php echo esc_attr( (string) $entry->notes ); ?>"
								data-entry-type="<?php echo esc_attr( $entry->entry_type ); ?>"
								data-overtime-hours="<?php echo esc_attr( (string) $entry->overtime_hours ); ?>"
								data-duration-minutes="<?php echo esc_attr( (string) $entry->duration_minutes ); ?>"
								tabindex="0"
								role="button"
							>
								<div class="etwa-entry-time"><?php echo esc_html( DateHelper::format_time_range( $entry->start_time, $entry->end_time ) ); ?></div>
								<div class="etwa-entry-meta">
									<strong><?php echo esc_html( $entry->display_target_name() ); ?></strong>
									<?php if ( $entry->project_id && $entry->client_id && $entry->project_name ) : ?>
										<span class="etwa-entry-project"><?php echo esc_html( $entry->project_name ); ?></span>
									<?php elseif ( $entry->client_id && ! $entry->project_id && ! $is_break_entry && ! $is_all_clients_entry ) : ?>
										<span class="etwa-entry-project"><?php echo esc_html( ProjectService::unassigned_label() ); ?></span>
									<?php endif; ?>
									<span><?php echo esc_html( $entry->task ); ?></span>
								</div>
								<div class="etwa-entry-hours">
									<?php if ( $is_break_entry ) : ?>
										<span class="etwa-break-tag"><?php echo esc_html( number_format( $entry_hours, 1 ) ); ?>h <?php esc_html_e( 'Break', 'etwa' ); ?></span>
									<?php else : ?>
										<?php echo esc_html( number_format( $entry_hours, 1 ) ); ?>h
										<?php if ( EntryTypeHelper::OVERTIME === $entry->entry_type || (float) $entry->overtime_hours > 0 ) : ?>
											<span class="etwa-ot-tag"><?php esc_html_e( 'OT', 'etwa' ); ?></span>
										<?php elseif ( EntryTypeHelper::COMPENSATION === $entry->entry_type ) : ?>
											<span class="etwa-comp-tag"><?php esc_html_e( 'Comp', 'etwa' ); ?></span>
										<?php endif; ?>
									<?php endif; ?>
								</div>
								<?php if ( $show_entry_actions ) : ?>
									<div class="etwa-entry-actions">
										<button type="button" class="etwa-link etwa-edit-entry"><?php esc_html_e( 'Edit', 'etwa' ); ?></button>
										<button type="button" class="etwa-link etwa-delete-entry"><?php esc_html_e( 'Delete', 'etwa' ); ?></button>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ( ! $readonly && $can_log ) : ?>
					<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-block etwa-add-day-entry" data-date="<?php echo esc_attr( $date ); ?>">
						<?php esc_html_e( '+ Add entry', 'etwa' ); ?>
					</button>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<div class="etwa-footer-actions etwa-sticky-footer">
		<?php if ( ! $readonly && empty( $hr_review ) ) : ?>
			<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-large" id="etwa-save-draft"><?php esc_html_e( 'Save Draft', 'etwa' ); ?></button>
			<button type="button" class="etwa-btn etwa-btn-primary etwa-btn-large" id="etwa-submit-timesheet" data-timesheet-id="<?php echo esc_attr( (string) $timesheet->id ); ?>"><?php esc_html_e( 'Submit Report', 'etwa' ); ?></button>
		<?php endif; ?>
	</div>

	<div class="etwa-modal" id="etwa-entry-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-modal-title">
		<div class="etwa-modal-content">
			<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
			<h3 id="etwa-modal-title"><?php esc_html_e( 'Add Time Entry', 'etwa' ); ?></h3>
			<form id="etwa-entry-form">
				<input type="hidden" name="entry_id" id="etwa-entry-id" value="" />
				<input type="hidden" name="timesheet_id" value="<?php echo esc_attr( (string) $timesheet->id ); ?>" />
				<div class="etwa-form-grid">
					<label class="etwa-span-2">
						<?php esc_html_e( 'Day', 'etwa' ); ?>
						<input type="date" name="entry_date" id="etwa-entry-date" required <?php echo $hr_can_edit ? '' : 'readonly'; ?> />
					</label>
					<label><?php esc_html_e( 'Start', 'etwa' ); ?><select name="start_time" id="etwa-start-time" required></select></label>
					<label><?php esc_html_e( 'End', 'etwa' ); ?><select name="end_time" id="etwa-end-time" required></select></label>
					<label class="etwa-span-2" id="etwa-entry-type-wrap" hidden>
						<?php esc_html_e( 'Entry Type', 'etwa' ); ?>
						<select name="entry_type" id="etwa-entry-type" required>
							<option value=""><?php esc_html_e( 'Select…', 'etwa' ); ?></option>
							<option value="overtime"><?php esc_html_e( 'Overtime', 'etwa' ); ?></option>
							<option value="compensation"><?php esc_html_e( 'Compensation', 'etwa' ); ?></option>
						</select>
						<span class="etwa-muted"><?php esc_html_e( 'Required for entries starting after 6:00 PM.', 'etwa' ); ?></span>
					</label>
					<div class="etwa-span-2 etwa-duration-preview-wrap">
						<p class="etwa-duration-preview" id="etwa-duration-preview" aria-live="polite"></p>
					</div>
					<label class="etwa-span-2">
						<?php esc_html_e( 'Client / Prospect', 'etwa' ); ?>
						<select name="client_id" id="etwa-client-id" required>
							<option value=""><?php esc_html_e( 'Select…', 'etwa' ); ?></option>
							<?php
							$internal_clients     = array();
							$general_clients      = array();
							$work_clients         = array();
							$standalone_projects  = $standalone_projects ?? array();
							foreach ( $clients as $client ) {
								$code = strtoupper( $client->code );
								if ( in_array( $code, ClientRepository::internal_codes(), true ) ) {
									$internal_clients[] = $client;
								} elseif ( in_array( $code, ClientRepository::general_codes(), true ) ) {
									$general_clients[] = $client;
								} else {
									$work_clients[] = $client;
								}
							}
							?>
							<?php if ( $internal_clients ) : ?>
								<optgroup label="<?php esc_attr_e( 'Internal', 'etwa' ); ?>">
									<?php foreach ( $internal_clients as $client ) : ?>
										<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
							<?php if ( $standalone_projects ) : ?>
								<optgroup label="<?php esc_attr_e( 'Projects', 'etwa' ); ?>">
									<?php foreach ( $standalone_projects as $parent ) : ?>
										<option value="<?php echo esc_attr( 'p:' . (int) $parent['id'] ); ?>"><?php echo esc_html( (string) $parent['name'] ); ?></option>
										<?php foreach ( $parent['children'] ?? array() as $child ) : ?>
											<option value="<?php echo esc_attr( 'p:' . (int) $child['id'] ); ?>"><?php echo esc_html( '— ' . (string) $child['name'] ); ?></option>
										<?php endforeach; ?>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
							<?php if ( $general_clients ) : ?>
								<optgroup label="<?php esc_attr_e( 'General', 'etwa' ); ?>">
									<?php foreach ( $general_clients as $client ) : ?>
										<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
							<?php if ( $work_clients ) : ?>
								<optgroup label="<?php esc_attr_e( 'Clients', 'etwa' ); ?>">
									<?php foreach ( $work_clients as $client ) : ?>
										<option value="<?php echo esc_attr( (string) $client->id ); ?>"><?php echo esc_html( $client->name ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
							<?php
							$prospects = $prospects ?? array();
							if ( $prospects ) :
								?>
								<optgroup label="<?php esc_attr_e( 'Prospects', 'etwa' ); ?>">
									<?php foreach ( $prospects as $prospect ) : ?>
										<option value="<?php echo esc_attr( 'pr:' . (int) $prospect->id ); ?>"<?php disabled( ! $prospect->is_open() ); ?>><?php echo esc_html( $prospect->name ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endif; ?>
						</select>
					</label>
					<label class="etwa-span-2" id="etwa-project-wrap" hidden>
						<?php esc_html_e( 'Project', 'etwa' ); ?>
						<select name="project_id" id="etwa-project-id">
							<option value=""><?php esc_html_e( 'Social Media', 'etwa' ); ?></option>
						</select>
					</label>
					<label class="etwa-span-2"><?php esc_html_e( 'Task', 'etwa' ); ?><input type="text" name="task" id="etwa-task" placeholder="<?php esc_attr_e( 'e.g. Website', 'etwa' ); ?>" /></label>
					<label class="etwa-span-2"><?php esc_html_e( 'Notes', 'etwa' ); ?><textarea name="notes" id="etwa-notes" rows="2" placeholder="<?php esc_attr_e( 'Optional details…', 'etwa' ); ?>"></textarea></label>
					<p class="etwa-span-2 etwa-field-hint" id="etwa-all-clients-hint" hidden><?php esc_html_e( 'Notes are required — describe the work performed in detail because this entry applies to Moderation.', 'etwa' ); ?></p>
					<?php if ( $hr_can_edit ) : ?>
						<label class="etwa-span-2 etwa-hr-reason-field">
							<?php esc_html_e( 'Audit reason (required)', 'etwa' ); ?>
							<textarea name="edit_reason" id="etwa-edit-reason" rows="2" required minlength="3" placeholder="<?php esc_attr_e( 'Why are you making this change?', 'etwa' ); ?>"></textarea>
						</label>
					<?php endif; ?>
				</div>
				<div class="etwa-modal-actions">
					<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
					<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save Entry', 'etwa' ); ?></button>
				</div>
			</form>
		</div>
	</div>
	<?php if ( ! empty( $standalone_projects ) ) : ?>
		<script type="application/json" id="etwa-standalone-projects-json"><?php echo wp_json_encode( $standalone_projects ); ?></script>
	<?php endif; ?>
</div>
