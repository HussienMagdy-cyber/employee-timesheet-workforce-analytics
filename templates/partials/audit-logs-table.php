<?php

/**

 * Audit logs table partial.

 *

 * @var object[]             $logs

 * @var int                  $total

 * @var int                  $page

 * @var int                  $per_page

 * @var string|null          $entity_type

 * @var bool                 $enabled

 * @var string[]             $entity_types

 * @var string               $base_url

 * @var bool                 $show_filter

 *

 * @package ETWA

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



use ETWA\Helpers\DateHelper;



$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );

?>

<?php if ( ! $enabled ) : ?>

	<div class="etwa-alert etwa-alert-warning">

		<?php esc_html_e( 'Audit logging is currently disabled. New actions will not be recorded until you enable it in Settings.', 'etwa' ); ?>

	</div>

<?php endif; ?>



<?php if ( ! empty( $show_filter ) ) : ?>

	<form class="etwa-log-filter" method="get">

		<?php if ( ! empty( $_GET['etwa_view'] ) ) : ?>

			<input type="hidden" name="etwa_view" value="<?php echo esc_attr( sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) ) ); ?>" />

		<?php elseif ( ! empty( $_GET['page'] ) ) : ?>

			<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ); ?>" />

		<?php endif; ?>

		<label>

			<?php esc_html_e( 'Entity type', 'etwa' ); ?>

			<select name="etwa_log_type" onchange="this.form.submit()">

				<option value=""><?php esc_html_e( 'All types', 'etwa' ); ?></option>

				<?php foreach ( $entity_types as $type ) : ?>

					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $entity_type, $type ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $type ) ) ); ?></option>

				<?php endforeach; ?>

			</select>

		</label>

	</form>

<?php endif; ?>



<div class="etwa-panel">

	<table class="etwa-table etwa-audit-logs-table">

		<thead>

			<tr>

				<th><?php esc_html_e( 'Time', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Edited by', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Employee', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Action', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Entity', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Reason', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'Role', 'etwa' ); ?></th>

				<th><?php esc_html_e( 'IP', 'etwa' ); ?></th>

			</tr>

		</thead>

		<tbody>

			<?php if ( empty( $logs ) ) : ?>

				<tr>

					<td colspan="8" class="etwa-muted"><?php esc_html_e( 'No audit logs found.', 'etwa' ); ?></td>

				</tr>

			<?php else : ?>

				<?php foreach ( $logs as $log ) : ?>

					<?php

					$editor   = get_userdata( (int) $log->user_id );

					$employee = ! empty( $log->employee_id ) ? get_userdata( (int) $log->employee_id ) : null;

					?>

					<tr>

						<td><?php echo esc_html( DateHelper::format_datetime( (string) $log->created_at ) ); ?></td>

						<td><?php echo esc_html( $editor ? $editor->display_name : '—' ); ?></td>

						<td><?php echo esc_html( $employee ? $employee->display_name : '—' ); ?></td>

						<td><code><?php echo esc_html( (string) $log->action ); ?></code></td>

						<td><?php echo esc_html( (string) $log->entity_type . ' #' . (int) $log->entity_id ); ?></td>

						<td class="etwa-audit-reason"><?php echo esc_html( (string) ( $log->edit_reason ?? '' ) ?: '—' ); ?></td>

						<td><?php echo esc_html( (string) ( $log->editor_role ?? '' ) ?: '—' ); ?></td>

						<td><?php echo esc_html( (string) ( $log->ip_address ?: '—' ) ); ?></td>

					</tr>

				<?php endforeach; ?>

			<?php endif; ?>

		</tbody>

	</table>



	<?php if ( $total_pages > 1 ) : ?>

		<nav class="etwa-pagination" aria-label="<?php esc_attr_e( 'Audit log pages', 'etwa' ); ?>">

			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>

				<?php

				$args = array( 'etwa_log_page' => $i );

				if ( $entity_type ) {

					$args['etwa_log_type'] = $entity_type;

				}

				if ( ! empty( $_GET['etwa_view'] ) ) {

					$args['etwa_view'] = sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) );

				} elseif ( ! empty( $_GET['page'] ) ) {

					$args['page'] = sanitize_key( wp_unslash( (string) $_GET['page'] ) );

				}

				$url = add_query_arg( $args, $base_url );

				?>

				<a class="etwa-btn etwa-btn-secondary etwa-btn-small<?php echo $page === $i ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( (string) $i ); ?></a>

			<?php endfor; ?>

		</nav>

	<?php endif; ?>

</div>

