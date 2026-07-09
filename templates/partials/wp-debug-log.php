<?php
/**
 * WordPress debug.log viewer partial.
 *
 * @var array{enabled:bool,exists:bool,path:string,lines:string[],size:int,modified:string} $wp_debug_log
 * @var string $clear_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="etwa-panel etwa-wp-debug-panel">
	<h2><?php esc_html_e( 'WordPress Debug Log', 'etwa' ); ?></h2>
	<p class="etwa-muted">
		<?php esc_html_e( 'PHP warnings, notices, and fatal errors are written to wp-content/debug.log when WP_DEBUG_LOG is enabled.', 'etwa' ); ?>
	</p>

	<?php if ( ! $wp_debug_log['enabled'] ) : ?>
		<div class="etwa-alert etwa-alert-warning">
			<?php esc_html_e( 'Debug logging is off. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to capture errors.', 'etwa' ); ?>
		</div>
	<?php elseif ( ! $wp_debug_log['exists'] ) : ?>
		<div class="etwa-alert">
			<?php esc_html_e( 'No debug log file yet — that usually means no PHP errors have been recorded. Browse the site and retry actions to generate entries.', 'etwa' ); ?>
		</div>
	<?php else : ?>
		<div class="etwa-wp-debug-meta">
			<span class="etwa-badge"><?php esc_html_e( 'Active', 'etwa' ); ?></span>
			<span><?php echo esc_html( size_format( $wp_debug_log['size'] ) ); ?></span>
			<?php if ( $wp_debug_log['modified'] ) : ?>
				<span><?php printf( esc_html__( 'Updated: %s', 'etwa' ), esc_html( $wp_debug_log['modified'] ) ); ?></span>
			<?php endif; ?>
			<span><?php printf( esc_html__( 'Showing last %d lines', 'etwa' ), count( $wp_debug_log['lines'] ) ); ?></span>
			<?php if ( ! empty( $clear_url ) ) : ?>
				<a class="etwa-btn etwa-btn-secondary etwa-btn-small" href="<?php echo esc_url( $clear_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear the WordPress debug log?', 'etwa' ) ); ?>');">
					<?php esc_html_e( 'Clear log', 'etwa' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<pre class="etwa-wp-debug-log"><?php
		if ( empty( $wp_debug_log['lines'] ) ) {
			esc_html_e( '(empty log file)', 'etwa' );
		} else {
			foreach ( $wp_debug_log['lines'] as $line ) {
				$is_plugin = str_contains( $line, 'employee-timesheet-workforce-analytics' );
				if ( $is_plugin ) {
					echo '<span class="etwa-log-line etwa-log-line-plugin">' . esc_html( $line ) . "</span>\n";
				} else {
					echo esc_html( $line ) . "\n";
				}
			}
		}
		?></pre>
	<?php endif; ?>

	<p class="etwa-muted"><code><?php echo esc_html( $wp_debug_log['path'] ); ?></code></p>
</div>
