<?php
/**
 * Frontend shortcode handlers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Frontend;

use ETWA\Assets\AssetRegistry;
use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\AuditLogViewData;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Helpers\WpDebugLogReader;
use ETWA\Helpers\HrDashboardData;
use ETWA\Helpers\HrReviewLinks;
use ETWA\Helpers\TimesheetViewData;
use ETWA\Repositories\ClientRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcodes {

	/** @var string[] */
	public static array $active = array();

	public function __construct() {
		add_shortcode( 'etwa_portal', array( $this, 'render_portal' ) );
		add_shortcode( 'etwa_timesheet', array( $this, 'render_timesheet' ) );
		add_shortcode( 'etwa_hr', array( $this, 'render_hr' ) );
		add_shortcode( 'etwa_analytics', array( $this, 'render_analytics' ) );
		add_shortcode( 'etwa_clients', array( $this, 'render_clients' ) );
		add_shortcode( 'etwa_prospects', array( $this, 'render_prospects' ) );
		add_shortcode( 'etwa_prospect_analytics', array( $this, 'render_prospect_analytics' ) );
		add_shortcode( 'etwa_employees', array( $this, 'render_employees' ) );
		add_shortcode( 'etwa_client_analytics', array( $this, 'render_client_analytics' ) );
		add_shortcode( 'etwa_reports', array( $this, 'render_reports' ) );
		add_shortcode( 'etwa_deliverables', array( $this, 'render_deliverables' ) );
		add_shortcode( 'etwa_files', array( $this, 'render_files' ) );
		add_shortcode( 'etwa_holidays', array( $this, 'render_holidays' ) );
		add_shortcode( 'etwa_vacations', array( $this, 'render_vacations' ) );
		add_shortcode( 'etwa_my_vacation', array( $this, 'render_my_vacation' ) );
		add_shortcode( 'etwa_requests', array( $this, 'render_requests' ) );
		add_shortcode( 'etwa_requests_hr', array( $this, 'render_requests_hr' ) );
	}

	public function render_portal( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}

		$user_id   = get_current_user_id();
		$nav_items  = PortalNav::items_for_user( $user_id );
		$nav_groups = PortalNav::groups_for_user( $user_id );
		if ( ! $nav_items ) {
			return $this->denied_message();
		}

		$current_view = PortalNav::resolve_view( $nav_items );
		$portal_url   = get_permalink() ?: home_url( '/' );
		$dark_mode    = '1' === get_user_meta( $user_id, 'etwa_dark_mode', true );
		$page_meta    = PortalNav::view_meta( $current_view );

		self::$active[] = 'portal';
		AssetRegistry::mark( 'portal-shell' );
		AssetRegistry::mark( PortalNav::script_for_view( $current_view ) );
		if ( PortalNav::is_employee_requests_view( $current_view ) ) {
			AssetRegistry::mark( 'employee-vacation' );
		}
		if ( 'hr' === $current_view && ! empty( $_GET['review_user'] ) && ! empty( $_GET['etwa_week'] ) ) {
			AssetRegistry::mark_hr_week_review();
		}
		if ( 'dept-dashboard' === $current_view && ! empty( $_GET['review_user'] ) && ! empty( $_GET['etwa_week'] ) ) {
			AssetRegistry::mark_hr_week_review();
		}
		if ( 'my-profile' === $current_view ) {
			AssetRegistry::mark( 'employee-profile' );
		}

		ob_start();
		$this->render_portal_view( $current_view, $portal_url, $atts );
		$portal_content = (string) ob_get_clean();

		$page_title        = $page_meta['title'];
		$page_subtitle     = $page_meta['subtitle'];
		$page_detail_title = $this->portal_page_detail_title( $current_view );

		ob_start();
		include ETWA_PLUGIN_DIR . 'templates/frontend/portal-shell.php';
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $atts */
	private function render_portal_view( string $view, string $portal_url, array $atts = array() ): void {
		if ( SuperClientAccessHelper::is_super_client_view( $view ) ) {
			$this->output_super_clients();
			return;
		}

		switch ( $view ) {
			case 'history':
				$this->output_history( $portal_url );
				break;
			case 'hr':
				$this->output_hr( $portal_url );
				break;
			case 'analytics':
				$this->output_analytics( $portal_url );
				break;
			case 'clients':
				$this->output_clients();
				break;
			case 'prospects':
				$this->output_prospects();
				break;
			case 'prospect-analytics':
				$this->output_prospect_analytics( $portal_url );
				break;
			case 'client-analytics':
				$this->output_client_analytics( $portal_url );
				break;
			case 'reports':
				$this->output_reports();
				break;
			case 'files':
				$this->output_files();
				break;
			case 'deliverables':
				$this->output_deliverables();
				break;
			case 'employees':
				$this->output_employees();
				break;
			case 'departments':
				$this->output_departments();
				break;
			case 'dept-dashboard':
				$this->output_dept_dashboard( $portal_url );
				break;
			case 'holidays':
				$this->output_holidays();
				break;
			case 'vacations':
				$this->output_vacations();
				break;
			case 'requests-hr':
			case 'requests-hr-vacation':
			case 'requests-hr-wfh':
			case 'requests-hr-permission':
			case 'requests-hr-holiday-work':
				$this->output_requests_hr( PortalNav::request_tab_for_view( $view ) );
				break;
			case 'requests':
			case 'requests-vacation':
			case 'requests-wfh':
			case 'requests-permission':
			case 'requests-holiday-work':
				$this->output_requests( PortalNav::request_tab_for_view( $view ) );
				break;
			case 'my-vacation':
				$this->output_my_vacation();
				break;
			case 'logs':
				$this->output_logs( $portal_url );
				break;
			case 'my-profile':
				$this->output_my_profile();
				break;
			case 'timesheet':
			default:
				$this->output_timesheet( $portal_url );
				break;
		}
	}

	private function output_timesheet( string $portal_url ): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$ref  = $this->get_week_reference_date();
		$view = TimesheetViewData::prepare( get_current_user_id(), $ref, 'frontend' );
		$view['portal_base'] = $portal_url;
		$view['portal_view'] = 'timesheet';

		include ETWA_PLUGIN_DIR . 'templates/frontend/timesheet.php';
	}

	private function output_history( string $portal_url ): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$history      = TimesheetViewData::history_for_user( get_current_user_id() );
		$history_rows = $history['rows'];
		$current_week = $history['current_week'];

		include ETWA_PLUGIN_DIR . 'templates/frontend/timesheet-history.php';
	}

	private function output_hr( string $portal_url = '' ): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$hr_base_url      = $portal_url ? PortalNav::view_url( $portal_url, 'hr' ) : admin_url( 'admin.php?page=etwa-hr' );
		$hr_dashboard_url = $hr_base_url;
		$review_user      = ! empty( $_GET['review_user'] ) ? (int) $_GET['review_user'] : 0;

		if ( $review_user > 0 ) {
			$ref = $this->get_week_reference_date( true );
			if ( $ref ) {
				$employee_weeks_url = HrReviewLinks::employee_weeks_url( $hr_base_url, $review_user );
				$result             = TimesheetViewData::prepare_hr_review_result( $review_user, get_current_user_id(), $ref, 'frontend', $employee_weeks_url );
				if ( ! $result['ok'] ) {
					$this->render_hr_review_notice( $result, $employee_weeks_url );
					return;
				}
				$view                = $result['view'];
				$view['portal_base'] = $portal_url ?: $hr_base_url;
				include ETWA_PLUGIN_DIR . 'templates/partials/hr-week-review.php';
				return;
			}

			$data = TimesheetViewData::weeks_for_employee( $review_user, get_current_user_id() );
			if ( ! $data ) {
				echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}

			$employee         = $data['employee'];
			$week_rows        = $data['rows'];
			$current_week     = $data['current_week'];
			$hr_base_url      = $hr_base_url;
			$hr_dashboard_url = $hr_dashboard_url;
			include ETWA_PLUGIN_DIR . 'templates/partials/hr-employee-weeks.php';
			return;
		}

		$dashboard       = HrDashboardData::initial();
		$employees       = $dashboard['employees'];
		$counts          = $dashboard['counts'];
		$review_base_url = $hr_base_url;

		include ETWA_PLUGIN_DIR . 'templates/frontend/hr-dashboard.php';
	}

	private function output_analytics( string $portal_url = '' ): void {
		if ( ! current_user_can( RoleManager::CAP_ADV_ANALYTICS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$portal_base = $portal_url;
		include ETWA_PLUGIN_DIR . 'templates/frontend/owner-analytics.php';
	}

	private function output_clients(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$clients = ( new ClientRepository() )->all();
		include ETWA_PLUGIN_DIR . 'templates/frontend/clients.php';
	}

	private function output_prospects(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/prospects.php';
	}

	private function output_super_clients(): void {
		if ( ! SuperClientAccessHelper::can_access_product_hub( get_current_user_id() ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/super-clients.php';
	}

	private function output_prospect_analytics( string $portal_url = '' ): void {
		if ( ! current_user_can( RoleManager::CAP_ANALYTICS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$portal_base = $portal_url;
		include ETWA_PLUGIN_DIR . 'templates/frontend/prospect-analytics.php';
	}

	private function output_client_analytics( string $portal_url = '' ): void {
		if ( ! current_user_can( RoleManager::CAP_ANALYTICS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$portal_base = $portal_url;
		include ETWA_PLUGIN_DIR . 'templates/frontend/client-analytics.php';
	}

	private function output_reports(): void {
		if ( ! current_user_can( RoleManager::CAP_EXPORT ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$can_view_labor_cost = EmployeeCostHelper::can_view_labor_cost();
		$can_view_analytics  = current_user_can( RoleManager::CAP_ANALYTICS );
		include ETWA_PLUGIN_DIR . 'templates/frontend/reports-exports.php';
	}

	private function output_files(): void {
		if ( ! RoleManager::user_can_view_deliverables() ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$can_view_analytics = current_user_can( RoleManager::CAP_ANALYTICS );
		$can_allocate       = RoleManager::user_can_allocate_deliverables();
		$client_id          = isset( $_GET['client_id'] ) ? absint( wp_unslash( (string) $_GET['client_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		include ETWA_PLUGIN_DIR . 'templates/frontend/files.php';
	}

	private function output_deliverables(): void {
		if ( ! RoleManager::user_can_view_deliverables() ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$can_manage_scopes         = current_user_can( RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES );
		$can_allocate_deliverables = RoleManager::user_can_allocate_deliverables();
		$can_manage_types          = RoleManager::user_can_manage_deliverable_types();
		include ETWA_PLUGIN_DIR . 'templates/frontend/deliverables.php';
	}

	private function output_employees(): void {
		if ( ! EmployeeCostHelper::can_view_labor_cost() ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/employees.php';
	}

	private function output_my_profile(): void {
		if ( ! is_user_logged_in() ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/my-profile.php';
	}

	private function output_departments(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_DEPARTMENTS ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/admin/departments.php';
	}

	private function output_dept_dashboard( string $portal_url = '' ): void {
		$service = new \ETWA\Services\DepartmentService();
		if ( ! $service->can_view_department_dashboard() ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$dept_base_url = $portal_url
			? PortalNav::view_url( $portal_url, 'dept-dashboard' )
			: admin_url( 'admin.php?page=etwa-dept-dashboard' );
		$review_user = ! empty( $_GET['review_user'] ) ? (int) $_GET['review_user'] : 0;

		if ( $review_user > 0 ) {
			$ref = $this->get_week_reference_date( true );
			if ( $ref ) {
				$employee_weeks_url = HrReviewLinks::employee_weeks_url( $dept_base_url, $review_user );
				$result             = TimesheetViewData::prepare_hr_review_result( $review_user, get_current_user_id(), $ref, 'frontend', $employee_weeks_url, 'dept-dashboard' );
				if ( ! $result['ok'] ) {
					$this->render_hr_review_notice( $result, $employee_weeks_url, true );
					return;
				}
				$view                = $result['view'];
				$view['portal_base'] = $dept_base_url;
				include ETWA_PLUGIN_DIR . 'templates/partials/hr-week-review.php';
				return;
			}

			$data = TimesheetViewData::weeks_for_employee( $review_user, get_current_user_id() );
			if ( ! $data ) {
				echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}

			$employee         = $data['employee'];
			$week_rows        = $data['rows'];
			$current_week     = $data['current_week'];
			$hr_base_url      = $dept_base_url;
			$hr_dashboard_url = $dept_base_url;
			$dept_review      = true;
			include ETWA_PLUGIN_DIR . 'templates/partials/hr-employee-weeks.php';
			return;
		}

		$context   = 'frontend';
		$in_portal = true;
		include ETWA_PLUGIN_DIR . 'templates/admin/department-dashboard.php';
	}

	private function output_holidays(): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/holidays.php';
	}

	private function output_vacations(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_VACATION ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/vacation.php';
	}

	private function output_requests( string $initial_tab = 'vacation' ): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/my-requests.php';
	}

	private function output_requests_hr( string $initial_tab = 'vacation' ): void {
		$user_id = get_current_user_id();
		$allowed = current_user_can( RoleManager::CAP_MANAGE_VACATION )
			|| ( user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) && \ETWA\Helpers\DepartmentHelper::user_heads_department( $user_id ) );
		if ( ! $allowed ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/requests-center.php';
	}

	private function output_my_vacation(): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		include ETWA_PLUGIN_DIR . 'templates/frontend/my-vacation.php';
	}

	private function output_logs( string $portal_url ): void {
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			echo $this->denied_message(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$logs_url = PortalNav::view_url( $portal_url, 'logs' );

		$page        = max( 1, (int) ( $_GET['etwa_log_page'] ?? 1 ) );
		$entity_type = ! empty( $_GET['etwa_log_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_log_type'] ) ) : null;
		$log_view    = AuditLogViewData::prepare( $page, 50, $entity_type ?: null );
		$wp_debug_log = WpDebugLogReader::snapshot();
		$clear_url   = WpDebugLogReader::clear_url( $logs_url );

		include ETWA_PLUGIN_DIR . 'templates/frontend/audit-logs.php';
	}

	public function render_timesheet( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'timesheet';
		AssetRegistry::mark( 'employee-dashboard' );

		ob_start();
		$this->output_timesheet( get_permalink() ?: home_url( '/' ) );
		return (string) ob_get_clean();
	}

	public function render_hr( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'hr';
		AssetRegistry::mark( 'hr-dashboard' );
		if ( ! empty( $_GET['review_user'] ) && ! empty( $_GET['etwa_week'] ) ) {
			AssetRegistry::mark_hr_week_review();
		}

		ob_start();
		$this->output_hr( get_permalink() ?: home_url( '/' ) );
		return (string) ob_get_clean();
	}

	public function render_analytics( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_ADV_ANALYTICS ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'analytics';
		AssetRegistry::mark( 'owner-analytics' );

		ob_start();
		$this->output_analytics();
		return (string) ob_get_clean();
	}

	public function render_clients( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'clients';
		AssetRegistry::mark( 'clients-manager' );

		ob_start();
		$this->output_clients();
		return (string) ob_get_clean();
	}

	public function render_prospects( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'prospects';
		AssetRegistry::mark( 'prospects' );

		ob_start();
		$this->output_prospects();
		return (string) ob_get_clean();
	}

	public function render_prospect_analytics( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_ANALYTICS ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'prospect-analytics';
		AssetRegistry::mark( 'prospect-analytics' );

		ob_start();
		$this->output_prospect_analytics( get_permalink() ?: home_url( '/' ) );
		return (string) ob_get_clean();
	}

	public function render_holidays( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'holidays';
		AssetRegistry::mark( 'holidays-manager' );

		ob_start();
		$this->output_holidays();
		return (string) ob_get_clean();
	}

	public function render_vacations( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_MANAGE_VACATION ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'vacations';
		AssetRegistry::mark( 'vacation-manager' );

		ob_start();
		$this->output_vacations();
		return (string) ob_get_clean();
	}

	public function render_requests( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'requests';
		AssetRegistry::mark( 'employee-requests' );
		AssetRegistry::mark( 'employee-vacation' );

		ob_start();
		$this->output_requests();
		return (string) ob_get_clean();
	}

	public function render_requests_hr( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_MANAGE_VACATION ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'requests-hr';
		AssetRegistry::mark( 'requests-manager' );

		ob_start();
		$this->output_requests_hr();
		return (string) ob_get_clean();
	}

	public function render_my_vacation( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'my-vacation';
		AssetRegistry::mark( 'employee-vacation' );

		ob_start();
		$this->output_my_vacation();
		return (string) ob_get_clean();
	}

	public function render_client_analytics( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_ANALYTICS ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'client-analytics';
		AssetRegistry::mark( 'client-analytics' );

		ob_start();
		$this->output_client_analytics( get_permalink() ?: home_url( '/' ) );
		return (string) ob_get_clean();
	}

	public function render_reports( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! current_user_can( RoleManager::CAP_EXPORT ) ) {
			return $this->denied_message();
		}

		self::$active[] = 'reports';
		AssetRegistry::mark( 'reports-exports' );

		ob_start();
		$this->output_reports();
		return (string) ob_get_clean();
	}

	public function render_files( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! RoleManager::user_can_view_deliverables() ) {
			return $this->denied_message();
		}

		self::$active[] = 'files';
		AssetRegistry::mark( 'files' );

		ob_start();
		$this->output_files();
		return (string) ob_get_clean();
	}

	public function render_deliverables( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! RoleManager::user_can_view_deliverables() ) {
			return $this->denied_message();
		}

		self::$active[] = 'deliverables';
		AssetRegistry::mark( 'deliverables' );

		ob_start();
		$this->output_deliverables();
		return (string) ob_get_clean();
	}

	public function render_employees( $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			AuthHelper::redirect_to_login();
		}
		if ( ! EmployeeCostHelper::can_view_labor_cost() ) {
			return $this->denied_message();
		}

		self::$active[] = 'employees';
		AssetRegistry::mark( 'employees-manager' );

		ob_start();
		$this->output_employees();
		return (string) ob_get_clean();
	}

	private function get_week_reference_date( bool $hr_review = false ): ?string {
		if ( ! empty( $_GET['etwa_week'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_GET['etwa_week'] ) );
		}
		if ( ! $hr_review && ! empty( $_GET['date'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_GET['date'] ) );
		}
		return null;
	}

	/** @param array{ok:false,code:string,message:string} $result */
	private function render_hr_review_notice( array $result, string $back_url, bool $dept_review = false ): void {
		$code       = $result['code'] ?? 'forbidden';
		$type       = 'draft_not_shared' === $code ? 'info' : 'error';
		$message    = $result['message'] ?? __( 'You do not have permission to view this page.', 'etwa' );
		$back_label = __( 'Employee weeks', 'etwa' );

		include ETWA_PLUGIN_DIR . 'templates/partials/hr-review-notice.php';
	}

	private function denied_message(): string {
		return '<div class="etwa-portal-message"><p>' . esc_html__( 'You do not have permission to view this page.', 'etwa' ) . '</p></div>';
	}

	private function portal_page_detail_title( string $view ): string {
		if ( 'client-analytics' !== $view ) {
			return '';
		}

		$client_id = isset( $_GET['client_id'] ) ? (int) $_GET['client_id'] : 0;
		if ( $client_id <= 0 ) {
			return '';
		}

		$client = ( new ClientRepository() )->find( $client_id );
		return $client ? $client->name : '';
	}
}
