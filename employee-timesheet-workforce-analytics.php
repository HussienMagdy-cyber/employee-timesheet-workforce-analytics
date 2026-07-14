<?php
/**
 * Plugin Name:       Employee Timesheet & Workforce Analytics
 * Plugin URI:        https://example.com/employee-timesheet-workforce-analytics
 * Description:       Enterprise date-based employee time tracking, overtime management, approvals, analytics dashboards, and Excel exports.
 * Version:           1.1.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Think Marketing
 * License:           GPL-2.0-or-later
 * Text Domain:       etwa
 * Domain Path:       /languages
 *
 * @package ETWA
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ETWA_VERSION', '1.1.3' );
define( 'ETWA_PLUGIN_FILE', __FILE__ );
define( 'ETWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETWA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$etwa_autoload = ETWA_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $etwa_autoload ) ) {
	require_once $etwa_autoload;
}

require_once ETWA_PLUGIN_DIR . 'includes/class-etwa-autoloader.php';
ETWA\Autoloader::register();

require_once ETWA_PLUGIN_DIR . 'includes/class-etwa-activator.php';
require_once ETWA_PLUGIN_DIR . 'includes/class-etwa-deactivator.php';
require_once ETWA_PLUGIN_DIR . 'includes/class-etwa-plugin.php';

register_activation_hook( __FILE__, array( 'ETWA\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ETWA\\Deactivator', 'deactivate' ) );

function etwa(): ETWA\Plugin {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new ETWA\Plugin();
		$instance->run();
	}
	return $instance;
}

etwa();
