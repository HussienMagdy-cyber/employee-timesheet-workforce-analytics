<?php
/**
 * Shared frontend/admin asset loader.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Assets;

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\AuthHelper;
use ETWA\Models\Prospect;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\EntryTypeHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\VacationApprovalHelper;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetRegistry {

	/** @var string[] */
	private static array $scripts = array();

	public static function mark( string $script ): void {
		self::$scripts[ $script ] = $script;

		if ( 'employees-manager' === $script ) {
			self::$scripts['employee-profile'] = 'employee-profile';
		}

		if ( 'my-profile' === $script ) {
			self::$scripts['employee-profile'] = 'employee-profile';
		}

		// Requests center embeds the full vacation UI; always load its script bundle.
		if ( 'employee-requests' === $script ) {
			self::$scripts['employee-vacation'] = 'employee-vacation';
		}
	}

	public static function mark_hr_week_review(): void {
		self::mark( 'employee-dashboard' );
	}

	private static function maybe_mark_hr_week_review_for_request(): void {
		if ( empty( $_GET['review_user'] ) || empty( $_GET['etwa_week'] ) ) {
			return;
		}

		self::mark_hr_week_review();
	}

	public static function boot(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend' ), 20 );
		add_action( 'wp_footer', array( self::class, 'enqueue_portal_shell_late' ), 1 );
		add_action( 'wp_footer', array( self::class, 'enqueue_scripts_late' ), 5 );
	}

	public static function enqueue_frontend(): void {
		if ( ! self::should_load_frontend() ) {
			return;
		}

		self::enqueue_base( self::pick_script(), true );
	}

	public static function enqueue_admin( string $hook, string $script ): void {
		if ( ! str_contains( $hook, 'etwa' ) ) {
			return;
		}
		self::enqueue_base( $script, false );
	}

	private static function should_load_frontend(): bool {
		if ( self::$scripts ) {
			return true;
		}
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( ! $post ) {
			return false;
		}
		$tags = array( 'etwa_timesheet', 'etwa_hr', 'etwa_analytics', 'etwa_client_analytics', 'etwa_prospect_analytics', 'etwa_clients', 'etwa_prospects', 'etwa_employees', 'etwa_reports', 'etwa_holidays', 'etwa_vacations', 'etwa_my_vacation', 'etwa_requests', 'etwa_requests_hr', 'etwa_portal', 'etwa_login', 'etwa_set_password' );
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				if ( 'etwa_portal' === $tag && is_user_logged_in() ) {
					$nav = PortalNav::items_for_user( get_current_user_id() );
					if ( $nav ) {
						self::mark( 'portal-shell' );
						$view = PortalNav::resolve_view( $nav );
						self::mark( PortalNav::script_for_view( $view ) );
						if ( in_array( $view, array( 'hr', 'dept-dashboard' ), true ) ) {
							self::maybe_mark_hr_week_review_for_request();
						}
						return true;
					}
				}
				self::mark( self::map_shortcode_to_script( $tag ) );
				if ( 'etwa_hr' === $tag ) {
					self::maybe_mark_hr_week_review_for_request();
				}
				return true;
			}
		}
		return false;
	}

	private static function pick_script(): string {
		if ( isset( self::$scripts['requests-manager'] ) ) {
			return 'requests-manager';
		}
		if ( isset( self::$scripts['employee-requests'] ) ) {
			return 'employee-requests';
		}
		if ( isset( self::$scripts['holidays-manager'] ) ) {
			return 'holidays-manager';
		}
		if ( isset( self::$scripts['vacation-manager'] ) ) {
			return 'vacation-manager';
		}
		if ( isset( self::$scripts['employee-vacation'] ) ) {
			return 'employee-vacation';
		}
		if ( isset( self::$scripts['reports-exports'] ) ) {
			return 'reports-exports';
		}
		if ( isset( self::$scripts['files'] ) ) {
			return 'files';
		}
		if ( isset( self::$scripts['deliverables'] ) ) {
			return 'deliverables';
		}
		if ( isset( self::$scripts['client-analytics'] ) ) {
			return 'client-analytics';
		}
		if ( isset( self::$scripts['prospect-analytics'] ) ) {
			return 'prospect-analytics';
		}
		if ( isset( self::$scripts['owner-analytics'] ) ) {
			return 'owner-analytics';
		}
		if ( isset( self::$scripts['hr-dashboard'] ) ) {
			return 'hr-dashboard';
		}
		if ( isset( self::$scripts['employees-manager'] ) ) {
			return 'employees-manager';
		}
		if ( isset( self::$scripts['my-profile'] ) ) {
			return 'my-profile';
		}
		if ( isset( self::$scripts['auth'] ) ) {
			return 'auth';
		}
		if ( isset( self::$scripts['clients-manager'] ) ) {
			return 'clients-manager';
		}
		if ( isset( self::$scripts['prospects'] ) ) {
			return 'prospects';
		}
		if ( isset( self::$scripts['super-client-manager'] ) ) {
			return 'super-client-manager';
		}
		if ( isset( self::$scripts['admin'] ) ) {
			return 'admin';
		}
		return 'employee-dashboard';
	}

	private static function map_shortcode_to_script( string $tag ): string {
		return match ( $tag ) {
			'etwa_hr'        => 'hr-dashboard',
			'etwa_analytics' => 'owner-analytics',
			'etwa_client_analytics' => 'client-analytics',
			'etwa_prospect_analytics' => 'prospect-analytics',
			'etwa_reports'   => 'reports-exports',
			'etwa_deliverables' => 'deliverables',
			'etwa_files'     => 'files',
			'etwa_clients'   => 'clients-manager',
			'etwa_prospects' => 'prospects',
			'etwa_super_clients' => 'super-client-manager',
			'etwa_employees' => 'employees-manager',
			'etwa_holidays'  => 'holidays-manager',
			'etwa_vacations' => 'vacation-manager',
			'etwa_my_vacation' => 'employee-vacation',
			'etwa_requests' => 'employee-requests',
			'etwa_requests_hr' => 'requests-manager',
			'etwa_portal'    => 'employee-dashboard',
			'etwa_login'     => 'auth',
			'etwa_set_password' => 'auth',
			default          => 'employee-dashboard',
		};
	}

	private static function enqueue_base( string $script, bool $frontend ): void {
		wp_enqueue_style(
			'etwa-tokens',
			ETWA_PLUGIN_URL . 'assets/css/tokens.css',
			array(),
			ETWA_VERSION
		);
		wp_enqueue_style(
			'etwa-admin',
			ETWA_PLUGIN_URL . 'assets/css/admin.css',
			array( 'etwa-tokens' ),
			ETWA_VERSION
		);
		wp_enqueue_style(
			'etwa-components',
			ETWA_PLUGIN_URL . 'assets/css/components.css',
			array( 'etwa-admin' ),
			ETWA_VERSION
		);
		if ( $frontend ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'etwa-frontend',
				ETWA_PLUGIN_URL . 'assets/css/frontend.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
		}

		if ( 'auth' === $script ) {
			wp_enqueue_style(
				'etwa-auth',
				ETWA_PLUGIN_URL . 'assets/css/auth.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
		}

		if ( 'email-settings' === $script ) {
			wp_enqueue_style(
				'etwa-email-settings',
				ETWA_PLUGIN_URL . 'assets/css/email-settings.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
			wp_enqueue_media();
		}

		if ( 'owner-analytics' === $script || 'client-analytics' === $script || 'prospect-analytics' === $script || 'reports-exports' === $script || 'deliverables' === $script || 'files' === $script ) {
			wp_enqueue_style(
				'etwa-analytics',
				ETWA_PLUGIN_URL . 'assets/css/analytics.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
		}

		if ( 'super-client-manager' === $script || 'departments-manager' === $script ) {
			wp_enqueue_style(
				'etwa-analytics',
				ETWA_PLUGIN_URL . 'assets/css/analytics.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
			wp_enqueue_style(
				'etwa-super-client',
				ETWA_PLUGIN_URL . 'assets/css/super-client.css',
				array( 'etwa-frontend', 'etwa-analytics' ),
				ETWA_VERSION
			);
		}

		if ( in_array( $script, array( 'reports-exports', 'client-analytics', 'prospect-analytics', 'employees-manager', 'owner-analytics', 'hr-dashboard' ), true ) ) {
			wp_enqueue_script(
				'etwa-date-range',
				ETWA_PLUGIN_URL . 'assets/js/etwa-date-range.js',
				array( 'etwa-ui', 'etwa-api' ),
				ETWA_VERSION,
				true
			);
		}

		if ( 'owner-analytics' === $script || 'client-analytics' === $script || 'prospect-analytics' === $script || 'reports-exports' === $script || 'deliverables' === $script ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				array(),
				'4.4.1',
				true
			);
			if ( ! $frontend ) {
				wp_enqueue_style( 'dashicons' );
			}
		}

		wp_enqueue_script(
			'etwa-ui',
			ETWA_PLUGIN_URL . 'assets/js/etwa-ui.js',
			array(),
			ETWA_VERSION,
			true
		);

		wp_enqueue_script(
			'etwa-api',
			ETWA_PLUGIN_URL . 'assets/js/etwa-api.js',
			array( 'etwa-ui' ),
			ETWA_VERSION,
			true
		);

		$deps = array( 'jquery', 'etwa-ui', 'etwa-api' );
		if ( 'auth' === $script ) {
			$deps = array( 'jquery', 'etwa-ui' );
		}
		if ( ! in_array( $script, array( 'clients-manager', 'auth' ), true ) ) {
			$deps[] = 'wp-api-fetch';
		}
		if ( 'owner-analytics' === $script || 'client-analytics' === $script || 'prospect-analytics' === $script || 'reports-exports' === $script || 'deliverables' === $script ) {
			$deps[] = 'chartjs';
		}
		if ( wp_script_is( 'etwa-date-range', 'enqueued' ) ) {
			$deps[] = 'etwa-date-range';
		}
		if ( 'vacation-manager' === $script || 'employee-vacation' === $script || 'employee-requests' === $script ) {
			wp_enqueue_script(
				'etwa-vacation-calendar',
				ETWA_PLUGIN_URL . 'assets/js/vacation-calendar.js',
				array( 'jquery' ),
				ETWA_VERSION,
				true
			);
			$deps[] = 'etwa-vacation-calendar';
		}
		if ( in_array( $script, array( 'employees-manager', 'my-profile', 'employee-profile' ), true ) ) {
			wp_enqueue_script(
				'etwa-employee-profile',
				ETWA_PLUGIN_URL . 'assets/js/employee-profile.js',
				array( 'jquery', 'etwa-ui', 'etwa-api' ),
				ETWA_VERSION,
				true
			);
			$deps[] = 'etwa-employee-profile';
		}

		wp_enqueue_script(
			'etwa-' . $script,
			ETWA_PLUGIN_URL . 'assets/js/' . $script . '.js',
			$deps,
			ETWA_VERSION,
			true
		);

		self::enqueue_extra_scripts( $script, $frontend );

		if ( $frontend && 'auth' !== $script ) {
			self::enqueue_portal_shell();
		}

		if ( 'auth' === $script ) {
			wp_localize_script(
				'etwa-auth',
				'etwaAuth',
				array(
					'root'      => esc_url_raw( rest_url( 'etwa/v1/' ) ),
					'nonce'     => wp_create_nonce( 'etwa_auth' ),
					'loginUrl'  => AuthHelper::login_url(),
					'portalUrl' => AuthHelper::portal_url(),
					'i18n'      => array(
						'error'          => __( 'Something went wrong.', 'etwa' ),
						'loggingIn'      => __( 'Logging in…', 'etwa' ),
						'sending'        => __( 'Sending…', 'etwa' ),
						'saving'         => __( 'Saving…', 'etwa' ),
						'resetEmailSent' => __( 'If an account exists for that email, a reset link has been sent.', 'etwa' ),
						'show'           => __( 'Show', 'etwa' ),
						'hide'           => __( 'Hide', 'etwa' ),
					),
				)
			);
		} else {
			$app_config = self::app_config();
			wp_localize_script( 'etwa-' . $script, 'etwaApp', $app_config );
			if ( wp_script_is( 'etwa-employee-profile', 'enqueued' ) && 'employee-profile' !== $script ) {
				wp_localize_script( 'etwa-employee-profile', 'etwaApp', $app_config );
			}
		}
	}

	/** @return array<string,mixed> */
	private static function app_config(): array {
		return array(
				'root'       => esc_url_raw( rest_url( 'etwa/v1/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'  => wp_create_nonce( 'etwa_ajax' ),
				'loginUrl'   => AuthHelper::login_url(),
				'userId'     => get_current_user_id(),
				'canExport'  => current_user_can( RoleManager::CAP_EXPORT ),
				'canViewAnalytics' => current_user_can( RoleManager::CAP_ANALYTICS ),
				'clientAnalyticsUrl' => is_admin()
					? admin_url( 'admin.php?page=etwa-client-analytics' )
					: PortalNav::view_url( AuthHelper::portal_url(), 'client-analytics' ),
				'canApprove' => current_user_can( RoleManager::CAP_APPROVE_TS ),
				'canManage'        => SuperClientAccessHelper::can_manage_products( get_current_user_id() ),
				'canAccessProducts' => SuperClientAccessHelper::can_access_product_hub( get_current_user_id() ),
				'canCompleteProductSubmission' => SuperClientAccessHelper::can_complete_submission( get_current_user_id() ),
				'today'            => current_time( 'Y-m-d' ),
				'sourceLabels'     => Prospect::source_labels(),
				'prospectsUrl'     => is_admin()
					? admin_url( 'admin.php?page=etwa-prospects' )
					: PortalNav::view_url( AuthHelper::portal_url(), 'prospects' ),
				'workspaceUrl'     => is_admin()
					? ''
					: PortalNav::view_url( AuthHelper::portal_url(), 'clients' ),
				'superClientsUrl'  => is_admin()
					? ''
					: PortalNav::view_url( AuthHelper::portal_url(), 'super-clients' ),
				'superClientRequestTypeLabels'     => SuperClientRequestHelper::request_type_labels(),
				'superClientServiceCategoryLabels' => SuperClientRequestHelper::service_category_labels(),
				'superClientAgencyLabels'          => SuperClientRequestHelper::agency_labels(),
				'superClientIndustryLabels'        => SuperClientRequestHelper::industry_labels(),
				'superClientStatusLabels'          => SuperClientRequestHelper::status_labels(),
				'dateRangePresets'                 => DateRangeHelper::prospect_analytics_preset_labels(),
				'canViewLaborCost' => EmployeeCostHelper::can_view_labor_cost(),
				'canManageEmployees' => EmployeeProfileHelper::can_manage_employees(),
				'creatableRoles'     => EmployeeProfileHelper::assignable_roles(),
				'canManageHolidays'=> current_user_can( RoleManager::CAP_VIEW_ALL ),
				'canManageVacation'=> current_user_can( RoleManager::CAP_MANAGE_VACATION ),
				'canManageDepartments' => current_user_can( RoleManager::CAP_MANAGE_DEPARTMENTS ),
				'canViewDeptDashboard' => ( new \ETWA\Services\DepartmentService() )->can_view_department_dashboard(),
				'canApproveDept'   => current_user_can( RoleManager::CAP_APPROVE_DEPT_REQUESTS ),
				'canViewOwnVacation'=> current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ),
				'canViewDeliverables' => RoleManager::user_can_view_deliverables(),
				'canManageDeliverableScopes' => current_user_can( RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES ),
				'canAllocateDeliverables' => RoleManager::user_can_allocate_deliverables(),
				'canManageDeliverableTypes' => RoleManager::user_can_manage_deliverable_types(),
				'vacationDualApproval' => VacationApprovalHelper::is_dual_approval_enabled(),
				'breakCode'  => \ETWA\Repositories\ClientRepository::BREAK_CODE,
				'workDayEnd' => EntryTypeHelper::work_day_end(),
				'i18n'       => array(
					'saved'            => __( 'Saved', 'etwa' ),
					'saving'           => __( 'Saving…', 'etwa' ),
					'submitted'        => __( 'Submitted successfully', 'etwa' ),
					'confirmSubmit'    => __( 'Submit this timesheet for approval?', 'etwa' ),
					'confirmDelete'    => __( 'Delete this entry?', 'etwa' ),
					'confirmDeleteAllocation' => __( 'Delete this allocation and all of its deliverable items? Scope metrics will recalculate. This cannot be undone.', 'etwa' ),
					'confirmDeactivateDeliverableType' => __( 'Delete this deliverable type? It will be deactivated and no longer appear when allocating. Existing allocations keep their history.', 'etwa' ),
					'confirmCopyWeek'  => __( 'Copy all entries from the previous week into this week? This week must be empty.', 'etwa' ),
					'confirmApprove'   => __( 'Approve this timesheet?', 'etwa' ),
					'confirmReject'    => __( 'Reject this timesheet?', 'etwa' ),
					'confirmTitle'     => __( 'Please confirm', 'etwa' ),
					'confirm'          => __( 'Confirm', 'etwa' ),
					'cancel'           => __( 'Cancel', 'etwa' ),
					'approved'         => __( 'Timesheet approved.', 'etwa' ),
					'rejected'         => __( 'Timesheet rejected.', 'etwa' ),
					'error'            => __( 'Something went wrong.', 'etwa' ),
					'sessionExpired'   => __( 'Your session expired. Please refresh the page and log in again.', 'etwa' ),
					'locked'           => __( 'This period is locked.', 'etwa' ),
					'clientSaved'      => __( 'Client saved.', 'etwa' ),
					'clientUpdated'    => __( 'Client updated.', 'etwa' ),
					'clientDeleted'    => __( 'Client deleted.', 'etwa' ),
					'clientDeactivated'=> __( 'Client deactivated.', 'etwa' ),
					'convertToProject'   => __( 'Convert to Project', 'etwa' ),
					'convertSelectionRequired' => __( 'Select a parent client or parent project.', 'etwa' ),
					'convertPreviewRequired' => __( 'Preview the conversion and resolve conflicts first.', 'etwa' ),
					'confirmConvert'     => __( 'Convert this client into a project under the selected parent?', 'etwa' ),
					'convertComplete'    => __( 'Client converted to project successfully.', 'etwa' ),
					'convertSourceLabel' => __( 'Converting client:', 'etwa' ),
					'convertPreviewSource' => __( 'Source', 'etwa' ),
					'convertPreviewParent' => __( 'Parent', 'etwa' ),
					'convertPreviewProject' => __( 'New project', 'etwa' ),
					'convertPreviewExistingProject' => __( 'Existing project', 'etwa' ),
					'convertNewProjectOption' => __( 'New project (use client name)', 'etwa' ),
					'convertNewSubProjectOption' => __( 'New sub-project (use client name)', 'etwa' ),
					'convertParentProjectOption' => __( '%s (parent project)', 'etwa' ),
					'convertEntries'     => __( 'entries', 'etwa' ),
					'convertDirect'      => __( 'social media', 'etwa' ),
					'convertOnChildProjects' => __( 'on child projects', 'etwa' ),
					'convertScopes'      => __( 'scopes', 'etwa' ),
					'convertAllocations' => __( 'allocations (preserved via scopes)', 'etwa' ),
					'convertChildProjects' => __( 'child projects will become siblings under the parent', 'etwa' ),
					'convertChildProjectNames' => __( 'Child projects', 'etwa' ),
					'convertConflicts'   => __( 'Conflicts', 'etwa' ),
					'convertWarnings'    => __( 'Notes', 'etwa' ),
					'confirmDeleteClient'   => __( 'Permanently delete this client? This cannot be undone.', 'etwa' ),
					'confirmDeactivateClient' => __( 'Deactivate this client? It will be hidden from the timesheet dropdown.', 'etwa' ),
					'promoteSuperClient'      => __( 'Promote to Product', 'etwa' ),
					'demoteSuperClient'       => __( 'Demote Product', 'etwa' ),
					'manageSuperClient'       => __( 'Manage', 'etwa' ),
					'superClientBadge'        => __( 'PRODUCT', 'etwa' ),
					'confirmPromoteSuper'     => __( 'Promote this client to a Product?', 'etwa' ),
					'confirmDemoteSuper'      => __( 'Demote this Product to a standard client? Child data will be preserved.', 'etwa' ),
					'promotedSuper'           => __( 'Client promoted to Product.', 'etwa' ),
					'demotedSuper'            => __( 'Client demoted to standard client.', 'etwa' ),
					'noSuperClients'          => __( 'No Products yet. Promote a client from Workspace.', 'etwa' ),
					'noProductTabsForUnit'    => __( 'No Product Tabs are available for your department.', 'etwa' ),
					'noBrands'                => __( 'No brands yet.', 'etwa' ),
					'noBrandsHint'            => __( 'Create your first brand to get started.', 'etwa' ),
					'noRequestsHint'          => __( 'Create a request for one of your brands.', 'etwa' ),
					'noRecentRequests'        => __( 'No requests yet.', 'etwa' ),
					'noSearchResults'         => __( 'No matching results.', 'etwa' ),
					'noAnalyticsData'         => __( 'No analytics data yet.', 'etwa' ),
					'noAnalyticsHint'         => __( 'Add brands and requests to see analytics here.', 'etwa' ),
					'recentRequests'          => __( 'Recent Requests', 'etwa' ),
					'activeBrands'            => __( 'Active Brands', 'etwa' ),
					'loading'                 => __( 'Loading…', 'etwa' ),
					'view'                    => __( 'View', 'etwa' ),
					'date'                    => __( 'Date', 'etwa' ),
					'brandLabel'              => __( 'Brand', 'etwa' ),
					'requestTypeLabel'        => __( 'Request Type', 'etwa' ),
					'invoiceLabel'            => __( 'Invoice #', 'etwa' ),
					'createdLabel'            => __( 'Created', 'etwa' ),
					'requestDateAuto'         => __( 'Set automatically when saved', 'etwa' ),
					'applyCustomRange'        => __( 'Apply custom range', 'etwa' ),
					'requestsInPeriod'        => __( 'Total Requests (in period)', 'etwa' ),
					'brandsAllTime'           => __( 'Total Brands', 'etwa' ),
					'brandNameRequired'       => __( 'Brand is required.', 'etwa' ),
					'agencyRequired'          => __( 'Agency type is required.', 'etwa' ),
					'addBrandSubtitle'        => __( 'Add a brand profile for this Product', 'etwa' ),
					'editBrandSubtitle'       => __( 'Update brand profile details', 'etwa' ),
					'editRequestLabel'        => __( 'Edit Request', 'etwa' ),
					'noRequests'              => __( 'No requests yet.', 'etwa' ),
					'brandSaved'              => __( 'Brand saved.', 'etwa' ),
					'brandDeactivated'        => __( 'Brand deactivated.', 'etwa' ),
					'requestSaved'            => __( 'Request saved.', 'etwa' ),
					'requestDeleted'          => __( 'Request deleted.', 'etwa' ),
					'serviceCategoryRequired' => __( 'Service category is required.', 'etwa' ),
					'serviceRequired'         => __( 'Service is required.', 'etwa' ),
					'confirmDeactivateBrand'  => __( 'Deactivate this brand?', 'etwa' ),
					'confirmDeleteRequest'    => __( 'Delete this request?', 'etwa' ),
					'confirmArchiveProject'   => __( 'Archive this project?', 'etwa' ),
					'projectSaved'            => __( 'Project created.', 'etwa' ),
					'totalBrands'             => __( 'Total Brands', 'etwa' ),
					'totalRequests'           => __( 'Total Requests', 'etwa' ),
					'requestsByType'          => __( 'Requests by Type', 'etwa' ),
					'serviceCategory'         => __( 'Service Category', 'etwa' ),
					'byService'               => __( 'Service', 'etwa' ),
					'byAgency'                => __( 'Brands by Agency', 'etwa' ),
					'byIndustry'              => __( 'Brands by Industry', 'etwa' ),
					'requestsByIndustry'      => __( 'Requests by Industry', 'etwa' ),
					'industryRequired'        => __( 'Industry is required.', 'etwa' ),
					'selectIndustry'          => __( 'Select industry…', 'etwa' ),
					'byBrand'                 => __( 'By Brand', 'etwa' ),
					'agencyName'              => __( 'Agency Name', 'etwa' ),
					'agencyNameRequired'      => __( 'Agency name is required when Agency is selected.', 'etwa' ),
					'addBrand'                => __( 'Add Brand', 'etwa' ),
					'editBrand'               => __( 'Edit Brand', 'etwa' ),
					'createRequest'           => __( 'Create Request', 'etwa' ),
					'createRequestSubtitle'   => __( 'Add a new request for a Product brand', 'etwa' ),
					'editRequestSubtitle'     => __( 'Update request details for this brand', 'etwa' ),
					'completeSubmissionSubtitle' => __( 'Complete submission and receipt information', 'etwa' ),
					'saveRequest'             => __( 'Save Request', 'etwa' ),
					'requestDetails'          => __( 'Request Details', 'etwa' ),
					'contactInformation'      => __( 'Contact Information', 'etwa' ),
					'submissionReceipt'       => __( 'Submission / Receipt', 'etwa' ),
					'completeSubmission'      => __( 'Complete Submission', 'etwa' ),
					'editSubmission'          => __( 'Edit Submission', 'etwa' ),
					'statusLabel'             => __( 'Status', 'etwa' ),
					'statusPending'           => __( 'Pending', 'etwa' ),
					'statusPublished'         => __( 'Published', 'etwa' ),
					'statusCancelled'         => __( 'Cancelled', 'etwa' ),
					'allowedUnits'            => __( 'Allowed Units', 'etwa' ),
					'allowedUnitsHint'        => __( 'Choose which units can see this Product Tab. Products inside the tab inherit this access.', 'etwa' ),
					'allowedUnitsSaved'       => __( 'Allowed units saved.', 'etwa' ),
					'noUnitsConfigured'       => __( 'No units yet. Add departments in Administration.', 'etwa' ),
					'enterServiceDescription' => __( 'Enter service description…', 'etwa' ),
					'enterInvoiceNumber'      => __( 'Enter invoice number…', 'etwa' ),
					'brandRequired'           => __( 'Brand is required.', 'etwa' ),
					'invalidEmail'            => __( 'Enter a valid email address.', 'etwa' ),
					'editRequest'             => __( 'Edit Request', 'etwa' ),
					'selectBrand'             => __( 'Select brand…', 'etwa' ),
					'viewRequests'            => __( 'Requests', 'etwa' ),
					'manage'                  => __( 'Manage', 'etwa' ),
					'deactivate'       => __( 'Deactivate', 'etwa' ),
					'editClient'       => __( 'Edit Client', 'etwa' ),
					'entrySaved'       => __( 'Entry saved.', 'etwa' ),
					'entryDeleted'     => __( 'Entry deleted.', 'etwa' ),
					'copiedWeek'       => __( 'Previous week copied.', 'etwa' ),
					'addEntry'         => __( 'Add Time Entry', 'etwa' ),
					'editEntry'        => __( 'Edit Time Entry', 'etwa' ),
					'deleteEntry'      => __( 'Delete entry', 'etwa' ),
					'submitReport'     => __( 'Submit report', 'etwa' ),
					'copyWeek'         => __( 'Copy previous week', 'etwa' ),
					'durationPreview'  => __( 'Duration: %s hours', 'etwa' ),
					'invalidDuration'  => __( 'End time must be after start time.', 'etwa' ),
					'noEntries'        => __( 'No entries', 'etwa' ),
					'edit'             => __( 'Edit', 'etwa' ),
					'delete'           => __( 'Delete', 'etwa' ),
					'breakLabel'       => __( 'Break', 'etwa' ),
					'taskPlaceholder'  => __( 'e.g. Website', 'etwa' ),
					'directClientWork' => __( 'Social Media', 'etwa' ),
					'prospectsGroup'   => __( 'Prospects', 'etwa' ),
					'directParentProject' => __( 'Direct on parent project', 'etwa' ),
					'selectProject'    => __( 'Select a project…', 'etwa' ),
					'projectRequired'  => __( 'Please select a project for this client.', 'etwa' ),
					'noProjectsDirect' => __( 'No projects — Social Media', 'etwa' ),
					'addSubProject'    => __( 'Add Sub-project', 'etwa' ),
					'subProjectName'   => __( 'Sub-project name', 'etwa' ),
					'addProject'       => __( 'Add Project', 'etwa' ),
					'addingParentProject' => __( 'Creating a new Parent Project.', 'etwa' ),
					'addingSubProject' => __( 'Adding a Sub-project under %s.', 'etwa' ),
					'parentProject'    => __( 'Parent Project', 'etwa' ),
					'subProject'       => __( 'Sub-project', 'etwa' ),
					'viewHours'        => __( 'View hours', 'etwa' ),
					'active'           => __( 'active', 'etwa' ),
					'inactive'         => __( 'inactive', 'etwa' ),
					'notesPlaceholder' => __( 'Optional details…', 'etwa' ),
					'allClientsNotesPlaceholder' => __( 'Describe the work performed in detail (required for Moderation entries)…', 'etwa' ),
					'allClientsNotesRequired' => __( 'Please describe the work performed in detail in Notes because this entry applies to Moderation.', 'etwa' ),
					'entryTypeRequired'       => __( 'Please select an entry type for entries after 6:00 PM.', 'etwa' ),
					'overtimeShort'           => __( 'OT', 'etwa' ),
					'compensationShort'       => __( 'Comp', 'etwa' ),
					'costSaved'        => __( 'Hourly cost saved.', 'etwa' ),
					'invalidCost'      => __( 'Enter a valid hourly cost.', 'etwa' ),
					'noEmployees'      => __( 'No employees found.', 'etwa' ),
					'save'             => __( 'Save', 'etwa' ),
					'hourlyCostLabel'  => __( 'Hourly cost for %s', 'etwa' ),
					'employeesCount'   => __( '%1$d employees · page %2$d of %3$d', 'etwa' ),
					'exporting'        => __( 'Exporting…', 'etwa' ),
					'exportReady'      => __( 'Export ready.', 'etwa' ),
					'noTrendData'      => __( 'No trend data for this period.', 'etwa' ),
					'totalHours'       => __( 'Total Hours', 'etwa' ),
					'overtimeHours'    => __( 'Overtime', 'etwa' ),
					'holidaySaved'     => __( 'Holiday saved.', 'etwa' ),
					'holidayDeleted'   => __( 'Holiday deleted.', 'etwa' ),
					'calendarSaved'    => __( 'Calendar saved.', 'etwa' ),
					'noHolidays'       => __( 'No holidays found.', 'etwa' ),
					'confirmDeleteHoliday' => __( 'Delete this holiday?', 'etwa' ),
					'noProspects'      => __( 'No prospects found.', 'etwa' ),
					'prospectSaved'    => __( 'Prospect saved.', 'etwa' ),
					'prospectUpdated'  => __( 'Prospect updated.', 'etwa' ),
					'prospectWon'      => __( 'Prospect marked as won.', 'etwa' ),
					'prospectLost'     => __( 'Prospect marked as lost.', 'etwa' ),
					'prospectConverted'=> __( 'Prospect converted to client.', 'etwa' ),
					'confirmMarkWon'   => __( 'Mark this prospect as won?', 'etwa' ),
					'markWon'          => __( 'Mark Won', 'etwa' ),
					'markLost'         => __( 'Mark Lost', 'etwa' ),
					'convertToClient'  => __( 'Convert to Client', 'etwa' ),
					'viewConvertedClient' => __( 'View converted Client', 'etwa' ),
					'viewLostReason'   => __( 'View Lost Reason', 'etwa' ),
					'lostReason'       => __( 'Lost reason', 'etwa' ),
					'statusOpen'       => __( 'Open', 'etwa' ),
					'statusWon'        => __( 'Won', 'etwa' ),
					'statusLost'       => __( 'Lost', 'etwa' ),
					'notConvertedYet'  => __( 'Not converted yet', 'etwa' ),
					'notConverted'     => __( 'Not converted', 'etwa' ),
					'converted'        => __( 'Converted', 'etwa' ),
					'conversionStatus' => __( 'Conversion status', 'etwa' ),
					'convertedClient'  => __( 'Converted client', 'etwa' ),
					'createdDate'      => __( 'Created date', 'etwa' ),
					'wonLostDate'      => __( 'Won/Lost date', 'etwa' ),
					'source'           => __( 'Source', 'etwa' ),
					'startDate'        => __( 'Start Date', 'etwa' ),
					'selectSource'     => __( 'Select Source', 'etwa' ),
					'sourceReferrals'  => __( 'Referrals', 'etwa' ),
					'sourceSocialMedia'=> __( 'Social Media', 'etwa' ),
					'sourceDirectContact' => __( 'Direct Contact', 'etwa' ),
					'sourceCampaigns'  => __( 'Campaigns', 'etwa' ),
					'sourceEvents'     => __( 'Events', 'etwa' ),
					'relatedEntries'   => __( 'Related entries / work', 'etwa' ),
					'noProspectEntries'=> __( 'No working hours recorded against this prospect.', 'etwa' ),
					'entries'          => __( 'entries', 'etwa' ),
					'hours'            => __( 'Hours', 'etwa' ),
					'yes'              => __( 'Yes', 'etwa' ),
					'no'               => __( 'No', 'etwa' ),
					'auditReasonTitle' => __( 'Audit reason required', 'etwa' ),
					'auditReasonLabel' => __( 'Why are you making this change?', 'etwa' ),
					'reasonRequired'   => __( 'An audit reason is required (minimum 3 characters).', 'etwa' ),
					'updateStatus'     => __( 'Update status', 'etwa' ),
					'statusUpdated'    => __( 'Status updated.', 'etwa' ),
					'allocated'        => __( 'Allocated', 'etwa' ),
					'used'             => __( 'Used', 'etwa' ),
					'remaining'        => __( 'Remaining', 'etwa' ),
					'allocationSaved'  => __( 'Allocation saved.', 'etwa' ),
					'recordSaved'      => __( 'Leave record saved.', 'etwa' ),
					'confirmDeleteVacation' => __( 'Delete this vacation record?', 'etwa' ),
					'allEmployees'     => __( 'All employees', 'etwa' ),
					'allLeaveTypes'    => __( 'All leave types', 'etwa' ),
					'noHistory'        => __( 'No records found.', 'etwa' ),
					'totalAllocated'   => __( 'Total Allocated', 'etwa' ),
					'totalUsed'        => __( 'Total Used', 'etwa' ),
					'remainingLeave'   => __( 'Remaining Leave', 'etwa' ),
					'pendingRequests'  => __( 'Pending Requests', 'etwa' ),
					'upcomingVacations'=> __( 'Upcoming Vacations', 'etwa' ),
					'onLeaveNow'       => __( 'On Leave Now', 'etwa' ),
					'remainingBalance' => __( 'Remaining annual balance', 'etwa' ),
					'days'             => __( 'days', 'etwa' ),
					'pending'          => __( 'Pending', 'etwa' ),
					'view'             => __( 'View', 'etwa' ),
					'viewWeeks'        => __( 'View', 'etwa' ),
					'regularHours'     => __( 'Regular Hours', 'etwa' ),
					'weekReport'       => __( 'Week report', 'etwa' ),
					'role'             => __( 'Role', 'etwa' ),
					'editAllocation'   => __( 'Edit Allocation', 'etwa' ),
					'editRecord'       => __( 'Edit Leave Record', 'etwa' ),
					'leaveType'        => __( 'Leave Type', 'etwa' ),
					'effectiveDate'    => __( 'Effective Date', 'etwa' ),
					'startDate'        => __( 'Start Date', 'etwa' ),
					'endDate'          => __( 'End Date', 'etwa' ),
					'status'           => __( 'Status', 'etwa' ),
					'reason'           => __( 'Reason', 'etwa' ),
					'notes'            => __( 'Notes', 'etwa' ),
					'noUpcoming'       => __( 'No upcoming vacations.', 'etwa' ),
					'deleted'          => __( 'Deleted.', 'etwa' ),
					'noData'           => __( 'No data.', 'etwa' ),
					'requestSubmitted' => __( 'Request submitted for HR review.', 'etwa' ),
					'requestCancelled' => __( 'Request cancelled.', 'etwa' ),
					'confirmCancelRequest' => __( 'Cancel this request?', 'etwa' ),
					'noRequests'       => __( 'No requests found.', 'etwa' ),
					'approve'          => __( 'Approve', 'etwa' ),
					'reject'           => __( 'Reject', 'etwa' ),
					'confirmApproveRequest' => __( 'Approve this vacation request?', 'etwa' ),
					'confirmRejectRequest'  => __( 'Reject this vacation request?', 'etwa' ),
					'pendingReview'         => __( 'Pending review', 'etwa' ),
					'daysToDeductTitle'     => __( 'Days to deduct', 'etwa' ),
					'daysToDeductHelp'      => __( 'Enter how many days to deduct from the employee balance.', 'etwa' ),
					'daysToDeduct'          => __( 'Days to deduct', 'etwa' ),
					'requestDates'          => __( 'Request dates', 'etwa' ),
					'invalidDays'           => __( 'Enter a valid number of days.', 'etwa' ),
					'vacationBulkHrDays'    => __( 'Approve vacation requests individually so you can enter the days to deduct.', 'etwa' ),
					'permissionBulkHrDays'  => __( 'Approve permission requests individually so you can enter deduction days when required.', 'etwa' ),
					'permissionDeductionTitle' => __( 'Permission Deduction', 'etwa' ),
					'permissionDeductionHelp'  => __( 'This permission exceeds the monthly allowance. Enter how many vacation days to deduct.', 'etwa' ),
					'deductionDays'         => __( 'Deduction Days', 'etwa' ),
					'freePermission'        => __( 'Permission — No vacation deduction', 'etwa' ),
					'deductedLabel'         => __( 'Deducted', 'etwa' ),
					'monthlyPermissionUsage' => __( 'Monthly Permission Usage', 'etwa' ),
					'freePermissions'       => __( 'Permissions', 'etwa' ),
					'defaultLabel'          => __( 'default', 'etwa' ),
					'active'                => __( 'Active', 'etwa' ),
					'inactive'              => __( 'Inactive', 'etwa' ),
					'deactivate'            => __( 'Deactivate', 'etwa' ),
					'reactivate'            => __( 'Reactivate', 'etwa' ),
					'confirmDeactivate'     => __( 'Deactivate this employee? They will lose portal access.', 'etwa' ),
					'confirmReactivate'     => __( 'Reactivate this employee?', 'etwa' ),
					'employeeDeactivated'   => __( 'Employee deactivated.', 'etwa' ),
					'employeeReactivated'   => __( 'Employee reactivated.', 'etwa' ),
					'requestApproved'  => __( 'Request approved.', 'etwa' ),
					'requestRejected'  => __( 'Request rejected.', 'etwa' ),
					'annualBalanceSaved' => __( 'Annual leave balance saved.', 'etwa' ),
					'balanceAdjusted'    => __( 'Vacation balance updated.', 'etwa' ),
					'editBalance'        => __( 'Edit Balance', 'etwa' ),
					'editAdjustment'     => __( 'Edit Balance Adjustment', 'etwa' ),
					'adjustmentAdd'      => __( 'Add days', 'etwa' ),
					'adjustmentSubtract' => __( 'Subtract days', 'etwa' ),
					'adjustmentSetRemaining' => __( 'Set remaining to', 'etwa' ),
					'adjustmentDays'     => __( 'Days', 'etwa' ),
					'remainingTarget'    => __( 'Remaining days', 'etwa' ),
					'applyAdjustment'    => __( 'Apply Adjustment', 'etwa' ),
					'adjustmentEditHint' => __( 'Use positive values to add days and negative values to subtract.', 'etwa' ),
					'annualPoolNote'   => __( 'All leave types (except unpaid) deduct from this balance.', 'etwa' ),
					'annualLeave'      => __( 'Annual Leave', 'etwa' ),
					'usageBreakdown'   => __( 'Usage by type:', 'etwa' ),
					'noUsageYet'       => __( 'No leave used yet.', 'etwa' ),
					'selectEmployee'   => __( 'Please select an employee.', 'etwa' ),
					'annualTypeMissing'=> __( 'Annual leave type is not configured.', 'etwa' ),
					'companyHoliday' => __( 'Company holiday', 'etwa' ),
					'approveAsHr'    => __( 'Approve (HR)', 'etwa' ),
					'approveAsDepartment' => __( 'Approve (Department)', 'etwa' ),
					'deptApproval'   => __( 'Department', 'etwa' ),
					'department'     => __( 'Department', 'etwa' ),
					'deptStatus'     => __( 'Dept Status', 'etwa' ),
					'hrStatus'       => __( 'HR Status', 'etwa' ),
					'overallStatus'  => __( 'Overall Status', 'etwa' ),
					'approvalTimeline'=> __( 'Approval Timeline', 'etwa' ),
					'noDepartments'  => __( 'No departments yet.', 'etwa' ),
					'departmentSaved'=> __( 'Department saved.', 'etwa' ),
					'departmentDeleted'=> __( 'Department deleted.', 'etwa' ),
					'addDepartment'  => __( 'Add Department', 'etwa' ),
					'editDepartment' => __( 'Edit Department', 'etwa' ),
					'noDepartmentHead'=> __( 'No Department Head', 'etwa' ),
					'selectDepartment'=> __( '— No department —', 'etwa' ),
					'confirmDeleteDepartment' => __( 'Delete department "%s"?', 'etwa' ),
					'productHubAccess'        => __( 'Product Hub Access', 'etwa' ),
					'productHubAccessHint'    => __( 'Select which Product Tabs are available to employees in this department.', 'etwa' ),
					'noProductTabsConfigured' => __( 'No Product Tabs yet. Promote a client to a Product first.', 'etwa' ),
					'approveAsOwner' => __( 'Approve (Owner)', 'etwa' ),
					'confirmApproveHr' => __( 'Record your HR approval for this request?', 'etwa' ),
					'confirmApproveOwner' => __( 'Record your Owner approval for this request?', 'etwa' ),
					'requestPartiallyApproved' => __( 'Approval recorded. Waiting for the other reviewer.', 'etwa' ),
					'hrApproval'     => __( 'HR', 'etwa' ),
					'ownerApproval'  => __( 'Owner', 'etwa' ),
					'requestType'    => __( 'Request Type', 'etwa' ),
					'submittedDate'  => __( 'Submitted Date', 'etwa' ),
					'approvedBy'     => __( 'Approved by', 'etwa' ),
					'approvedDate'   => __( 'Approved Date', 'etwa' ),
					'approvalHistory'=> __( 'Approval History', 'etwa' ),
					'aging'          => __( '>48h', 'etwa' ),
					'agingHint'      => __( 'Pending over 48 hours', 'etwa' ),
					'rejectReasonRequired' => __( 'Rejection reason (required)', 'etwa' ),
					'rejectReasonOptional' => __( 'Rejection reason (optional)', 'etwa' ),
					'rejectionReason'  => __( 'Rejection reason', 'etwa' ),
					'rejectedBy'       => __( 'Rejected by', 'etwa' ),
					'requestDetails'   => __( 'Request Details', 'etwa' ),
					'confirmApproveDepartment' => __( 'Record your department approval for this request?', 'etwa' ),
					'selectedCount'  => __( '%d selected', 'etwa' ),
					'allDepartments' => __( 'All departments', 'etwa' ),
					'attachedFilesFuture' => __( 'Attached files — coming soon.', 'etwa' ),
					'workDate'       => __( 'Work Date', 'etwa' ),
					'time'           => __( 'Time', 'etwa' ),
					'permissionType' => __( 'Permission Type', 'etwa' ),
					'monthlyUsage'   => __( 'Monthly Usage', 'etwa' ),
					'monthlyPosition'  => __( 'Monthly Position', 'etwa' ),
					'leaveRecord'      => __( 'Leave Record', 'etwa' ),
					'vacationDeducted' => __( 'Vacation Deducted', 'etwa' ),
					'annualLeave'      => __( 'Annual', 'etwa' ),
					'unpaidLeave'      => __( 'Unpaid', 'etwa' ),
					'permissionLeaveDuration' => __( '0.25 day', 'etwa' ),
					'revoke'           => __( 'Revoke', 'etwa' ),
					'confirmRevokePermission' => __( 'Revoke this approved permission? The linked leave record will be removed and any vacation deduction will be restored.', 'etwa' ),
					'permissionRevoked' => __( 'Permission revoked.', 'etwa' ),
					'employee'       => __( 'Employee', 'etwa' ),
					'date'           => __( 'Date', 'etwa' ),
					'addEmployee'    => __( 'Add Employee', 'etwa' ),
					'createEmployee' => __( 'Create Employee', 'etwa' ),
					'employeeCreated'=> __( 'Employee created. Password setup email sent.', 'etwa' ),
					'passwordEmailFailed' => __( 'Employee created, but the password email could not be sent.', 'etwa' ),
					'passwordResetSent'=> __( 'Password reset email sent.', 'etwa' ),
					'profileSaved'   => __( 'Profile saved.', 'etwa' ),
					'employeeSaved'  => __( 'Employee updated.', 'etwa' ),
					'editEmployee'   => __( 'Edit Employee', 'etwa' ),
					'editProfile'    => __( 'Edit Profile', 'etwa' ),
					'saveChanges'    => __( 'Save Changes', 'etwa' ),
					'saving'         => __( 'Saving…', 'etwa' ),
					'role'           => __( 'Role', 'etwa' ),
					'actions'        => __( 'Actions', 'etwa' ),
					'joinDate'       => __( 'Join date', 'etwa' ),
					'birthday'       => __( 'Birthday', 'etwa' ),
					'tenure'         => __( 'Tenure', 'etwa' ),
					'notSet'         => __( 'Not set', 'etwa' ),
					'hourlyCost'     => __( 'Hourly Cost (EGP)', 'etwa' ),
					'creating'       => __( 'Creating…', 'etwa' ),
					'fullName'       => __( 'Full name', 'etwa' ),
					'jobTitle'       => __( 'Job title', 'etwa' ),
					'phone'          => __( 'Phone number', 'etwa' ),
					'draftSaved'     => __( 'Draft Saved', 'etwa' ),
					'unsavedChanges' => __( 'Unsaved changes — not visible to HR yet', 'etwa' ),
					'notSavedForReview' => __( 'Not saved for review yet', 'etwa' ),
					'draftSavedSuccess' => __( 'Draft saved for review.', 'etwa' ),
					'selectLogo'     => __( 'Select Logo', 'etwa' ),
					'useLogo'        => __( 'Use this logo', 'etwa' ),
					'noLogo'         => __( 'No logo selected', 'etwa' ),
					'sendTest'       => __( 'Send Test Email', 'etwa' ),
					'testSent'       => __( 'Test email sent successfully.', 'etwa' ),
					'invalidRecipient' => __( 'Enter a valid recipient email address.', 'etwa' ),
					'sending'        => __( 'Sending…', 'etwa' ),
					'sendPasswordReset' => __( 'Send password reset', 'etwa' ),
					'exportReport'      => __( 'Export Report', 'etwa' ),
					'exportToExcel'     => __( 'Export to Excel', 'etwa' ),
					'exportClientReport'=> __( 'Export Client Report', 'etwa' ),
					'exportLaborCostReport' => __( 'Export Labor Cost Report', 'etwa' ),
					'exportTimesheet'   => __( 'Export Timesheet', 'etwa' ),
					'employeeTimesheetReport' => __( 'Employee Timesheet Report', 'etwa' ),
					'clientReport'      => __( 'Client Report', 'etwa' ),
					'prospectReport'    => __( 'Prospect Report', 'etwa' ),
					'clientLaborCostReport' => __( 'Client Labor Cost Report', 'etwa' ),
					'employeeHoursByClientReport' => __( 'Employee Hours by Client', 'etwa' ),
					'allClientsComparisonReport' => __( 'All Clients Comparison', 'etwa' ),
					'parentProjectReport' => __( 'Parent Project Report', 'etwa' ),
					'allParentProjectsComparisonReport' => __( 'All Parent Projects Comparison', 'etwa' ),
					'parentProject'     => __( 'Parent Project', 'etwa' ),
					'selectParentProject' => __( 'Please select a parent project.', 'etwa' ),
					'prospect'          => __( 'Prospect', 'etwa' ),
					'selectProspect'    => __( 'Please select a prospect.', 'etwa' ),
					'generateComparison' => __( 'Generate Comparison', 'etwa' ),
					'generating'       => __( 'Generating…', 'etwa' ),
					'exportExcel'      => __( 'Export Excel', 'etwa' ),
					'grandTotal'       => __( 'Grand Total', 'etwa' ),
					'noComparisonData' => __( 'No client activity for the selected filters.', 'etwa' ),
					'employeeAllocation' => __( 'Employee Allocation Breakdown', 'etwa' ),
					'department'       => __( 'Department', 'etwa' ),
					'allDepartments'   => __( 'All Departments', 'etwa' ),
					'allEmployees'     => __( 'All Employees', 'etwa' ),
					'startDate'        => __( 'Start Date', 'etwa' ),
					'endDate'          => __( 'End Date', 'etwa' ),
					'regularHours'     => __( 'Regular Hours', 'etwa' ),
					'laborCost'        => __( 'Labor Cost', 'etwa' ),
					'employees'        => __( 'Employees', 'etwa' ),
					'allClients'        => __( 'All Clients', 'etwa' ),
					'selectClient'      => __( 'Please select a client.', 'etwa' ),
					'invalidDateRange'  => __( 'Please select a valid date range.', 'etwa' ),
					'client'            => __( 'Client', 'etwa' ),
				),
		);
	}

	private static function enqueue_portal_shell(): void {
		if ( ! isset( self::$scripts['portal-shell'] ) || wp_script_is( 'etwa-portal-shell', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'etwa-portal-shell',
			ETWA_PLUGIN_URL . 'assets/js/portal-shell.js',
			array( 'etwa-api' ),
			ETWA_VERSION,
			true
		);
		wp_localize_script(
			'etwa-portal-shell',
			'etwaPortalShell',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'etwa_ajax' ),
			)
		);
	}

	public static function enqueue_portal_shell_late(): void {
		if ( ! is_admin() ) {
			self::enqueue_portal_shell();
		}
	}

	/** Enqueue scripts marked during shortcode render (after wp_enqueue_scripts). */
	public static function enqueue_scripts_late(): void {
		if ( is_admin() ) {
			return;
		}

		if ( isset( self::$scripts['employee-vacation'] ) && ! wp_script_is( 'etwa-employee-vacation', 'enqueued' ) ) {
			self::ensure_vacation_calendar();
			self::enqueue_script_handle( 'employee-vacation', array( 'jquery', 'etwa-ui', 'wp-api-fetch', 'etwa-vacation-calendar' ) );
		}

		if ( isset( self::$scripts['employee-dashboard'] ) && ! wp_script_is( 'etwa-employee-dashboard', 'enqueued' ) ) {
			self::enqueue_script_handle( 'employee-dashboard', array( 'jquery', 'etwa-ui', 'wp-api-fetch' ) );
		}
	}

	private static function ensure_vacation_calendar(): void {
		if ( wp_script_is( 'etwa-vacation-calendar', 'registered' ) ) {
			return;
		}

		wp_enqueue_script(
			'etwa-vacation-calendar',
			ETWA_PLUGIN_URL . 'assets/js/vacation-calendar.js',
			array( 'jquery' ),
			ETWA_VERSION,
			true
		);
	}

	/** @param string[] $deps */
	private static function enqueue_script_handle( string $script, array $deps ): void {
		$relative = 'assets/js/' . $script . '.js';
		$path     = ETWA_PLUGIN_DIR . $relative;
		$version  = file_exists( $path ) ? (string) filemtime( $path ) : ETWA_VERSION;

		wp_enqueue_script(
			'etwa-' . $script,
			ETWA_PLUGIN_URL . $relative,
			$deps,
			$version,
			true
		);
	}

	private static function enqueue_extra_scripts( string $primary, bool $frontend ): void {
		foreach ( self::$scripts as $extra ) {
			if ( $extra === $primary || 'portal-shell' === $extra ) {
				continue;
			}

			$deps = array( 'jquery', 'etwa-ui' );
			if ( ! in_array( $extra, array( 'clients-manager', 'super-client-manager' ), true ) ) {
				$deps[] = 'wp-api-fetch';
			}

			if ( 'employee-vacation' === $extra ) {
				self::ensure_vacation_calendar();
				$deps[] = 'etwa-vacation-calendar';
				if ( wp_script_is( 'etwa-employee-requests', 'registered' ) ) {
					$deps[] = 'etwa-employee-requests';
				}
			}

			self::enqueue_script_handle( $extra, $deps );
		}
	}

}
