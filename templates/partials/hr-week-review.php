<?php

/**

 * HR employee week review — single week report.

 *

 * @var array  $view

 * @var string $hr_back_url

 *

 * @package ETWA

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$user         = get_userdata( (int) $view['timesheet']->user_id );
$can_review   = ! empty( $view['can_approve_timesheet'] ) && 'submitted' === $view['timesheet']->status;
$hr_can_edit  = ! empty( $view['hr_can_edit'] );
$dept_review  = ! empty( $view['dept_review'] );
$week_label   = $view['week_label'] ?? '';
$hr_back_url  = $view['hr_back_url'] ?? '';
$ts_status    = $view['timesheet']->status;

?>

<div class="etwa-page-shell etwa-hr-review">

	<div class="etwa-hr-review-toolbar">

		<a class="etwa-btn etwa-btn-secondary" href="<?php echo esc_url( $hr_back_url ); ?>">← <?php echo $dept_review ? esc_html__( 'Employee weeks', 'etwa' ) : esc_html__( 'Employee weeks', 'etwa' ); ?></a>

		<div class="etwa-hr-review-meta">

			<strong><?php echo esc_html( $user ? $user->display_name : __( 'Employee', 'etwa' ) ); ?></strong>

			<span class="etwa-muted"><?php echo esc_html( $week_label ); ?></span>

			<span class="etwa-status etwa-status-<?php echo esc_attr( $ts_status ); ?>"><?php echo esc_html( ucfirst( $ts_status ) ); ?></span>

		</div>

		<?php if ( $hr_can_edit ) : ?>

			<div class="etwa-hr-status-edit" id="etwa-hr-status-edit">

				<label class="etwa-hr-status-label">

					<span class="screen-reader-text"><?php esc_html_e( 'Timesheet status', 'etwa' ); ?></span>

					<select id="etwa-hr-status-select" class="etwa-hr-status-select">

						<option value="draft" <?php selected( $ts_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'etwa' ); ?></option>

						<option value="submitted" <?php selected( $ts_status, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'etwa' ); ?></option>

						<option value="approved" <?php selected( $ts_status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'etwa' ); ?></option>

						<option value="rejected" <?php selected( $ts_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'etwa' ); ?></option>

					</select>

				</label>

				<button type="button" class="etwa-btn etwa-btn-secondary etwa-btn-small" id="etwa-hr-status-save" data-id="<?php echo esc_attr( (string) $view['timesheet']->id ); ?>">

					<?php esc_html_e( 'Update status', 'etwa' ); ?>

				</button>

			</div>

		<?php elseif ( $can_review ) : ?>

			<div class="etwa-hr-review-actions">

				<button type="button" class="etwa-btn etwa-btn-primary etwa-approve-ts" data-id="<?php echo esc_attr( (string) $view['timesheet']->id ); ?>"><?php esc_html_e( 'Approve', 'etwa' ); ?></button>

				<button type="button" class="etwa-btn etwa-btn-secondary etwa-reject-ts" data-id="<?php echo esc_attr( (string) $view['timesheet']->id ); ?>"><?php esc_html_e( 'Reject', 'etwa' ); ?></button>

			</div>

		<?php endif; ?>

	</div>



	<?php if ( $hr_can_edit ) : ?>

		<div class="etwa-alert etwa-alert-info etwa-hr-edit-notice">

			<?php esc_html_e( 'HR edit mode — every change requires an audit reason and is logged permanently.', 'etwa' ); ?>

		</div>

	<?php elseif ( $dept_review ) : ?>

		<div class="etwa-alert etwa-alert-info etwa-hr-edit-notice">

			<?php esc_html_e( 'Read-only view — department employees only.', 'etwa' ); ?>

		</div>

	<?php endif; ?>

	<div id="etwa-hr-app" class="etwa-hr-review-app">
		<?php include ETWA_PLUGIN_DIR . 'templates/partials/week-timesheet.php'; ?>
	</div>

</div>

