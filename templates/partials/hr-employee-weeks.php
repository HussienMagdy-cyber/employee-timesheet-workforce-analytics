<?php
/**
 * HR view of an employee's timesheet weeks.
 *
 * @var \WP_User|null              $employee
 * @var array<int,array<string,mixed>> $week_rows
 * @var array                        $current_week
 * @var string                       $hr_base_url
 * @var string                       $hr_dashboard_url
 * @var bool                         $dept_review
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\DateHelper;
use ETWA\Helpers\HrReviewLinks;

$dept_review = ! empty( $dept_review );
?>
<div class="etwa-page-shell etwa-hr-review">
	<div class="etwa-hr-review-toolbar">
		<a class="etwa-btn etwa-btn-secondary" href="<?php echo esc_url( $hr_dashboard_url ); ?>">← <?php echo $dept_review ? esc_html__( 'Back to Department Dashboard', 'etwa' ) : esc_html__( 'Back to HR Dashboard', 'etwa' ); ?></a>
		<div class="etwa-hr-review-meta">
			<strong><?php echo esc_html( $employee ? $employee->display_name : __( 'Employee', 'etwa' ) ); ?></strong>
			<span class="etwa-muted"><?php esc_html_e( 'All work weeks', 'etwa' ); ?></span>
		</div>
	</div>

	<div class="etwa-panel">
		<h2><?php esc_html_e( 'Employee Weeks', 'etwa' ); ?></h2>
		<p class="etwa-muted"><?php esc_html_e( 'Select a week to open the full day-by-day report.', 'etwa' ); ?></p>

		<?php if ( empty( $week_rows ) ) : ?>
			<p class="etwa-history-empty"><?php esc_html_e( 'No timesheets recorded for this employee yet.', 'etwa' ); ?></p>
		<?php else : ?>
			<div class="etwa-table-wrap">
			<table class="etwa-table etwa-hr-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Week', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Hours', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'etwa' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $week_rows as $row ) : ?>
						<?php
						$is_current = $row['period_start'] === $current_week['period_start'];
						$report_url = HrReviewLinks::week_report_url( $hr_base_url, (int) $employee->ID, $row['period_start'] );
						?>
						<tr class="<?php echo $is_current ? 'is-current-week' : ''; ?>">
							<td>
								<strong><?php echo esc_html( $row['week_label'] ); ?></strong>
								<span class="etwa-history-dates"><?php echo esc_html( $row['period_start'] . ' — ' . $row['period_end'] ); ?></span>
								<?php if ( $is_current ) : ?>
									<span class="etwa-badge"><?php esc_html_e( 'Current week', 'etwa' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format( $row['total_hours'], 2 ) ); ?></td>
							<td><span class="etwa-status etwa-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td>
							<td>
								<?php
								if ( $row['submitted_at'] ) {
									echo esc_html( DateHelper::format_datetime( $row['submitted_at'] ) );
								} else {
									echo '<span class="etwa-muted">—</span>';
								}
								?>
							</td>
							<td>
								<a class="etwa-btn etwa-btn-primary etwa-btn-small" href="<?php echo esc_url( $report_url ); ?>">
									<?php esc_html_e( 'Week report', 'etwa' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
	</div>
</div>
