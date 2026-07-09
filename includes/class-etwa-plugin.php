<?php
/**
 * Main plugin bootstrap class.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA;

use ETWA\Admin\AdminMenu;
use ETWA\Admin\AjaxHandler;
use ETWA\Assets\AssetRegistry;
use ETWA\Cron\CronJobs;
use ETWA\Frontend\Auth;
use ETWA\Frontend\Shortcodes;
use ETWA\Database\Schema;
use ETWA\Repositories\ClientRepository;
use ETWA\REST\AuthController;
use ETWA\REST\AnalyticsController;
use ETWA\REST\ApprovalController;
use ETWA\REST\EntryController;
use ETWA\REST\ExportController;
use ETWA\REST\HolidayController;
use ETWA\REST\ClientAnalyticsController;
use ETWA\REST\ClientController;
use ETWA\REST\DepartmentController;
use ETWA\REST\EmployeeController;
use ETWA\REST\HrTimesheetController;
use ETWA\REST\TimesheetController;
use ETWA\REST\PortalController;
use ETWA\REST\RequestController;
use ETWA\REST\VacationController;
use ETWA\Helpers\ComposerDependencyHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\WpDebugLogReader;
use ETWA\Services\NotificationService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'bootstrap_runtime' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		WpDebugLogReader::register_handlers();
		AssetRegistry::boot();
		new Shortcodes();
		new Auth();
		new AjaxHandler();

		if ( is_admin() ) {
			new AdminMenu();
			ComposerDependencyHelper::register_admin_notice();
		}

		new CronJobs();
		new NotificationService();
	}

	public function bootstrap_runtime(): void {
		RoleManager::register_roles_and_caps();
		Schema::maybe_upgrade();
		\ETWA\Helpers\AuthHelper::ensure_pages();

		if ( false === get_transient( 'etwa_break_client_ok' ) ) {
			( new ClientRepository() )->ensure_system_clients();
			set_transient( 'etwa_break_client_ok', 1, DAY_IN_SECONDS );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'etwa', false, dirname( ETWA_PLUGIN_BASENAME ) . '/languages' );
	}

	public function register_rest_routes(): void {
		( new TimesheetController() )->register_routes();
		( new EntryController() )->register_routes();
		( new ApprovalController() )->register_routes();
		( new AnalyticsController() )->register_routes();
		( new ExportController() )->register_routes();
		( new ClientController() )->register_routes();
		( new ClientAnalyticsController() )->register_routes();
		( new EmployeeController() )->register_routes();
		( new HolidayController() )->register_routes();
		( new HrTimesheetController() )->register_routes();
		( new VacationController() )->register_routes();
		( new RequestController() )->register_routes();
		( new DepartmentController() )->register_routes();
		( new PortalController() )->register_routes();
		( new AuthController() )->register_routes();
	}
}
