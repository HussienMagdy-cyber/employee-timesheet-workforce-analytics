<?php
/**
 * PHPUnit bootstrap.
 *
 * @package ETWA
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/fake-wp/' );
define( 'DB_NAME', 'etwa_test' );
define( 'ETWA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

require_once __DIR__ . '/stubs/WordPressStubs.php';

require_once ETWA_PLUGIN_DIR . 'includes/class-etwa-autoloader.php';
ETWA\Autoloader::register();

DG\BypassFinals::enable();
