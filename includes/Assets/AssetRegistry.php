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
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;
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
		$tags = array( 'etwa_timesheet', 'etwa_hr', 'etwa_analytics', 'etwa_client_analytics', 'etwa_clients', 'etwa_employees', 'etwa_holidays', 'etwa_vacations', 'etwa_my_vacation', 'etwa_requests', 'etwa_requests_hr', 'etwa_portal', 'etwa_login', 'etwa_set_password' );
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				if ( 'etwa_portal' === $tag && is_user_logged_in() ) {
					$nav = PortalNav::items_for_user( get_current_user_id() );
					if ( $nav ) {
						self::mark( 'portal-shell' );
						self::mark( PortalNav::script_for_view( PortalNav::resolve_view( $nav ) ) );
						return true;
					}
				}
				self::mark( self::map_shortcode_to_script( $tag ) );
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
		if ( isset( self::$scripts['client-analytics'] ) ) {
			return 'client-analytics';
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
			'etwa_clients'   => 'clients-manager',
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

		if ( 'owner-analytics' === $script || 'client-analytics' === $script ) {
			wp_enqueue_style(
				'etwa-analytics',
				ETWA_PLUGIN_URL . 'assets/css/analytics.css',
				array( 'etwa-components' ),
				ETWA_VERSION
			);
		}

		if ( 'owner-analytics' === $script || 'client-analytics' === $script ) {
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

		$deps = array( 'jquery', 'etwa-ui' );
		if ( ! in_array( $script, array( 'clients-manager', 'auth' ), true ) ) {
			$deps[] = 'wp-api-fetch';
		}
		if ( 'owner-analytics' === $script || 'client-analytics' === $script ) {
			$deps[] = 'chartjs';
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
				array( 'jquery', 'etwa-ui' ),
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
				'userId'     => get_current_user_id(),
				'canExport'  => current_user_can( RoleManager::CAP_EXPORT ),
				'canApprove' => current_user_can( RoleManager::CAP_APPROVE_TS ),
				'canManage'        => current_user_can( RoleManager::CAP_MANAGE_SETTINGS ),
				'canViewLaborCost' => EmployeeCostHelper::can_view_labor_cost(),
				'canManageEmployees' => EmployeeProfileHelper::can_manage_employees(),
				'creatableRoles'     => EmployeeProfileHelper::assignable_roles(),
				'canManageHolidays'=> current_user_can( RoleManager::CAP_VIEW_ALL ),
				'canManageVacation'=> current_user_can( RoleManager::CAP_MANAGE_VACATION ),
				'canManageDepartments' => current_user_can( RoleManager::CAP_MANAGE_DEPARTMENTS ),
				'canViewDeptDashboard' => ( new \ETWA\Services\DepartmentService() )->can_view_department_dashboard(),
				'canApproveDept'   => current_user_can( RoleManager::CAP_APPROVE_DEPT_REQUESTS ),
				'canViewOwnVacation'=> current_user_can( RoleManager::CAP_VIEW_OWN_VACATION ),
				'vacationDualApproval' => VacationApprovalHelper::is_dual_approval_enabled(),
				'autosaveMs'       => (int) ( new SettingsRepository() )->get( 'autosave_interval', 30 ) * 1000,
				'breakCode'  => \ETWA\Repositories\ClientRepository::BREAK_CODE,
				'i18n'       => array(
					'saved'            => __( 'Saved', 'etwa' ),
					'saving'           => __( 'Saving…', 'etwa' ),
					'submitted'        => __( 'Submitted successfully', 'etwa' ),
					'confirmSubmit'    => __( 'Submit this timesheet for approval?', 'etwa' ),
					'confirmDelete'    => __( 'Delete this entry?', 'etwa' ),
					'confirmCopyWeek'  => __( 'Copy all entries from the previous week into this week? This week must be empty.', 'etwa' ),
					'confirmApprove'   => __( 'Approve this timesheet?', 'etwa' ),
					'confirmReject'    => __( 'Reject this timesheet?', 'etwa' ),
					'confirmTitle'     => __( 'Please confirm', 'etwa' ),
					'confirm'          => __( 'Confirm', 'etwa' ),
					'cancel'           => __( 'Cancel', 'etwa' ),
					'approved'         => __( 'Timesheet approved.', 'etwa' ),
					'rejected'         => __( 'Timesheet rejected.', 'etwa' ),
					'error'            => __( 'Something went wrong.', 'etwa' ),
					'locked'           => __( 'This period is locked.', 'etwa' ),
					'clientSaved'      => __( 'Client saved.', 'etwa' ),
					'clientUpdated'    => __( 'Client updated.', 'etwa' ),
					'clientDeleted'    => __( 'Client deleted.', 'etwa' ),
					'clientDeactivated'=> __( 'Client deactivated.', 'etwa' ),
					'confirmDeleteClient'   => __( 'Permanently delete this client? This cannot be undone.', 'etwa' ),
					'confirmDeactivateClient' => __( 'Deactivate this client? It will be hidden from the timesheet dropdown.', 'etwa' ),
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
					'notesPlaceholder' => __( 'Optional details…', 'etwa' ),
					'allClientsNotesPlaceholder' => __( 'Describe the work performed in detail (required for all-clients entries)…', 'etwa' ),
					'allClientsNotesRequired' => __( 'Please describe the work performed in detail in Notes because this entry applies to all clients.', 'etwa' ),
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
					'viewWeeks'        => __( 'View weeks', 'etwa' ),
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
					'requestApproved'  => __( 'Request approved.', 'etwa' ),
					'requestRejected'  => __( 'Request rejected.', 'etwa' ),
					'annualBalanceSaved' => __( 'Annual leave balance saved.', 'etwa' ),
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
					'sendPasswordReset' => __( 'Send password reset', 'etwa' ),
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
			array(),
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
		if ( is_admin() || ! isset( self::$scripts['employee-vacation'] ) ) {
			return;
		}

		if ( wp_script_is( 'etwa-employee-vacation', 'enqueued' ) ) {
			return;
		}

		self::ensure_vacation_calendar();
		self::enqueue_script_handle( 'employee-vacation', array( 'jquery', 'etwa-ui', 'wp-api-fetch', 'etwa-vacation-calendar' ) );
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
		wp_enqueue_script(
			'etwa-' . $script,
			ETWA_PLUGIN_URL . 'assets/js/' . $script . '.js',
			$deps,
			ETWA_VERSION,
			true
		);
	}

	private static function enqueue_extra_scripts( string $primary, bool $frontend ): void {
		foreach ( self::$scripts as $extra ) {
			if ( $extra === $primary || 'portal-shell' === $extra ) {
				continue;
			}

			$deps = array( 'jquery', 'etwa-ui' );
			if ( ! in_array( $extra, array( 'clients-manager' ), true ) ) {
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
