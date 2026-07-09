<?php
/**
 * Composer / PhpSpreadsheet dependency checks.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ComposerDependencyHelper {

	public static function vendor_autoload_path(): string {
		return ETWA_PLUGIN_DIR . 'vendor/autoload.php';
	}

	public static function is_vendor_installed(): bool {
		return is_readable( self::vendor_autoload_path() );
	}

	public static function is_phpspreadsheet_available(): bool {
		return class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' );
	}

	public static function install_command(): string {
		return 'composer install --no-dev';
	}

	public static function plugin_directory(): string {
		return str_replace( '\\', '/', ETWA_PLUGIN_DIR );
	}

	public static function register_admin_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( self::is_phpspreadsheet_available() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function (): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}

				$dir     = ComposerDependencyHelper::plugin_directory();
				$command = ComposerDependencyHelper::install_command();
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Think Marketing Timesheets — Excel export unavailable', 'etwa' ); ?></strong>
					</p>
					<p><?php esc_html_e( 'PhpSpreadsheet is not installed. Excel exports and emailed reports will fail until Composer dependencies are installed.', 'etwa' ); ?></p>
					<p>
						<?php esc_html_e( 'In Local, open Site Shell for this site, then run:', 'etwa' ); ?>
					</p>
					<pre style="white-space:pre-wrap;margin:0 0 12px;padding:10px;background:#f6f7f7;border:1px solid #dcdcde;">cd <?php echo esc_html( $dir ); ?>

<?php echo esc_html( $command ); ?></pre>
					<p class="description">
						<?php esc_html_e( 'If composer is not found in Site Shell, install Composer globally first or run: php composer.phar install --no-dev', 'etwa' ); ?>
					</p>
				</div>
				<?php
			}
		);
	}
}
