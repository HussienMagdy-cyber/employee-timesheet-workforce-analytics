<?php

/**

 * Employee timesheet history — previous work weeks.

 *

 * @var array<int,array<string,mixed>> $history_rows

 * @var string                          $portal_url

 * @var array                           $current_week

 *

 * @package ETWA

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



use ETWA\Frontend\PortalNav;

use ETWA\Helpers\DateHelper;

?>

<div class="etwa-page-shell etwa-app etwa-in-portal">

	<div class="etwa-hero">

		<div class="etwa-hero-copy">

			<p class="etwa-eyebrow"><?php esc_html_e( 'Timesheet Archive', 'etwa' ); ?></p>

			<h1><?php esc_html_e( 'History', 'etwa' ); ?></h1>

			<p class="etwa-subtitle"><?php esc_html_e( 'Browse your previous work weeks and open any timesheet.', 'etwa' ); ?></p>

		</div>

	</div>



	<div class="etwa-panel">

		<?php if ( empty( $history_rows ) ) : ?>

			<div class="etwa-empty-state">

				<span class="dashicons dashicons-backup" aria-hidden="true"></span>

				<p><?php esc_html_e( 'No previous timesheets yet. Your submitted weeks will appear here.', 'etwa' ); ?></p>

			</div>

		<?php else : ?>

			<div class="etwa-history-toolbar">

				<label class="etwa-search-field">

					<span class="screen-reader-text"><?php esc_html_e( 'Search weeks', 'etwa' ); ?></span>

					<input type="search" id="etwa-history-search" placeholder="<?php esc_attr_e( 'Search by week or status…', 'etwa' ); ?>" />

				</label>

			</div>



			<table class="etwa-table etwa-history-table etwa-history-table-desktop">

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

					<?php foreach ( $history_rows as $row ) : ?>

						<?php

						$is_current  = $row['period_start'] === $current_week['period_start'];

						$view_url    = PortalNav::view_url( $portal_url, 'timesheet', array( 'etwa_week' => $row['period_start'] ) );

						$search_blob = strtolower( $row['week_label'] . ' ' . $row['status'] . ' ' . $row['period_start'] );

						?>

						<tr class="etwa-history-row<?php echo $is_current ? ' is-current-week' : ''; ?>" data-search="<?php echo esc_attr( $search_blob ); ?>">

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

								<a class="etwa-btn etwa-btn-secondary etwa-btn-small" href="<?php echo esc_url( $view_url ); ?>">

									<?php esc_html_e( 'View', 'etwa' ); ?>

								</a>

							</td>

						</tr>

					<?php endforeach; ?>

				</tbody>

			</table>



			<div class="etwa-history-cards">

				<?php foreach ( $history_rows as $row ) : ?>

					<?php

					$is_current  = $row['period_start'] === $current_week['period_start'];

					$view_url    = PortalNav::view_url( $portal_url, 'timesheet', array( 'etwa_week' => $row['period_start'] ) );

					$search_blob = strtolower( $row['week_label'] . ' ' . $row['status'] . ' ' . $row['period_start'] );

					?>

					<article class="etwa-history-card<?php echo $is_current ? ' is-current-week' : ''; ?>" data-search="<?php echo esc_attr( $search_blob ); ?>">

						<div class="etwa-history-card-head">

							<div>

								<strong><?php echo esc_html( $row['week_label'] ); ?></strong>

								<span class="etwa-history-dates"><?php echo esc_html( $row['period_start'] . ' — ' . $row['period_end'] ); ?></span>

							</div>

							<span class="etwa-status etwa-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span>

						</div>

						<div class="etwa-history-card-stats">

							<div><span><?php esc_html_e( 'Total', 'etwa' ); ?></span><strong><?php echo esc_html( number_format( $row['total_hours'], 2 ) ); ?>h</strong></div>

						</div>

						<a class="etwa-btn etwa-btn-secondary etwa-btn-block" href="<?php echo esc_url( $view_url ); ?>">

							<?php esc_html_e( 'View Timesheet', 'etwa' ); ?>

						</a>

					</article>

				<?php endforeach; ?>

			</div>

		<?php endif; ?>

	</div>

</div>


