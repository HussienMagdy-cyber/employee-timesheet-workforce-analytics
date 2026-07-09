<?php
/**
 * Admin menu registration.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin;

use ETWA\Admin\Pages\AuditLogsPage;
use ETWA\Admin\Pages\ClientAnalyticsPage;
use ETWA\Admin\Pages\DepartmentDashboardPage;
use ETWA\Admin\Pages\DepartmentsPage;
use ETWA\Admin\Pages\EmployeeDashboard;
use ETWA\Admin\Pages\EmployeesPage;
use ETWA\Admin\Pages\HolidaysPage;
use ETWA\Admin\Pages\HRDashboard;
use ETWA\Admin\Pages\OwnerAnalytics;
use ETWA\Admin\Pages\RequestsPage;
use ETWA\Admin\Pages\SettingsPage;
use ETWA\Admin\Pages\VacationPage;
use ETWA\Assets\AssetRegistry;
use ETWA\Helpers\RoleManager;
use ETWA\Services\DepartmentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminMenu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_menu', array( $this, 'trim_employee_menus_for_hr_owner' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menus(): void {
		add_menu_page(
			__( 'Workforce Analytics', 'etwa' ),
			__( 'Timesheets', 'etwa' ),
			'read',
			'etwa-dashboard',
			array( $this, 'render_dashboard_root' ),
			'dashicons-clock',
			26
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'My Timesheet', 'etwa' ),
			__( 'My Timesheet', 'etwa' ),
			RoleManager::CAP_VIEW_OWN,
			'etwa-dashboard',
			array( new EmployeeDashboard(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'HR Dashboard', 'etwa' ),
			__( 'HR Dashboard', 'etwa' ),
			RoleManager::CAP_VIEW_ALL,
			'etwa-hr',
			array( new HRDashboard(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Employees', 'etwa' ),
			__( 'Employees', 'etwa' ),
			RoleManager::CAP_VIEW_ALL,
			'etwa-employees',
			array( new EmployeesPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Departments', 'etwa' ),
			__( 'Departments', 'etwa' ),
			RoleManager::CAP_MANAGE_DEPARTMENTS,
			'etwa-departments',
			array( new DepartmentsPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Department Dashboard', 'etwa' ),
			__( 'Department Dashboard', 'etwa' ),
			RoleManager::CAP_APPROVE_DEPT_REQUESTS,
			'etwa-dept-dashboard',
			array( new DepartmentDashboardPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Holidays', 'etwa' ),
			__( 'Holidays', 'etwa' ),
			RoleManager::CAP_VIEW_ALL,
			'etwa-holidays',
			array( new HolidaysPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Requests', 'etwa' ),
			__( 'Requests', 'etwa' ),
			RoleManager::CAP_APPROVE_DEPT_REQUESTS,
			'etwa-requests',
			array( new RequestsPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Vacations', 'etwa' ),
			__( 'Vacations', 'etwa' ),
			RoleManager::CAP_MANAGE_VACATION,
			'etwa-vacations',
			array( new VacationPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Client Analytics', 'etwa' ),
			__( 'Client Analytics', 'etwa' ),
			RoleManager::CAP_ANALYTICS,
			'etwa-client-analytics',
			array( new ClientAnalyticsPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Owner Analytics', 'etwa' ),
			__( 'Owner Analytics', 'etwa' ),
			RoleManager::CAP_ADV_ANALYTICS,
			'etwa-analytics',
			array( new OwnerAnalytics(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Settings', 'etwa' ),
			__( 'Settings', 'etwa' ),
			RoleManager::CAP_MANAGE_SETTINGS,
			'etwa-settings',
			array( new SettingsPage(), 'render' )
		);

		add_submenu_page(
			'etwa-dashboard',
			__( 'Audit Logs', 'etwa' ),
			__( 'Audit Logs', 'etwa' ),
			RoleManager::CAP_VIEW_ALL,
			'etwa-logs',
			array( new AuditLogsPage(), 'render' )
		);
	}

	public function render_dashboard_root(): void {
		if ( ( new DepartmentService() )->can_view_department_dashboard() && ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			( new DepartmentDashboardPage() )->render();
			return;
		}

		if ( current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			( new HRDashboard() )->render();
			return;
		}

		if ( current_user_can( RoleManager::CAP_VIEW_OWN ) ) {
			( new EmployeeDashboard() )->render();
			return;
		}

		wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
	}

	public function trim_employee_menus_for_hr_owner(): void {
		if ( current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			remove_submenu_page( 'etwa-dashboard', 'etwa-dashboard' );
		}
	}

	public function enqueue_assets( string $hook ): void {
		$script = 'employee-dashboard';
		if ( str_contains( $hook, 'etwa-hr' ) ) {
			$script = 'hr-dashboard';
			if ( ! empty( $_GET['review_user'] ) && ! empty( $_GET['etwa_week'] ) ) {
				AssetRegistry::mark_hr_week_review();
			}
		} elseif ( str_contains( $hook, 'etwa-employees' ) ) {
			$script = 'employees-manager';
		} elseif ( str_contains( $hook, 'etwa-departments' ) ) {
			$script = 'departments-manager';
		} elseif ( str_contains( $hook, 'etwa-dept-dashboard' ) ) {
			$script = 'department-dashboard';
			if ( ! empty( $_GET['review_user'] ) && ! empty( $_GET['etwa_week'] ) ) {
				AssetRegistry::mark_hr_week_review();
			}
		} elseif ( str_contains( $hook, 'etwa-holidays' ) ) {
			$script = 'holidays-manager';
		} elseif ( str_contains( $hook, 'etwa-requests' ) ) {
			$script = 'requests-manager';
		} elseif ( str_contains( $hook, 'etwa-vacations' ) ) {
			$script = 'vacation-manager';
		} elseif ( str_contains( $hook, 'etwa-client-analytics' ) ) {
			$script = 'client-analytics';
		} elseif ( str_contains( $hook, 'etwa-analytics' ) ) {
			$script = 'owner-analytics';
		} elseif ( str_contains( $hook, 'etwa-settings' ) || str_contains( $hook, 'etwa-logs' ) ) {
			$script = 'admin';
		} elseif ( str_contains( $hook, 'etwa-dashboard' ) ) {
			$script = current_user_can( RoleManager::CAP_VIEW_OWN ) ? 'employee-dashboard' : 'hr-dashboard';
		}

		AssetRegistry::enqueue_admin( $hook, $script );
	}
}
