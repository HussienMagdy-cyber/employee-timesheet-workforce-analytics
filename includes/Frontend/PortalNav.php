<?php
/**
 * Role-based portal navigation.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Frontend;

use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PortalNav {

	/** @return array<string,array{label:string,icon:string,tab:string}> */
	public static function request_type_definitions(): array {
		return array(
			'vacation'     => array(
				'label' => __( 'Vacation', 'etwa' ),
				'icon'  => 'dashicons-palmtree',
				'tab'   => 'vacation',
			),
			'wfh'          => array(
				'label' => __( 'Work From Home', 'etwa' ),
				'icon'  => 'dashicons-admin-home',
				'tab'   => 'wfh',
			),
			'permission'   => array(
				'label' => __( 'Permissions', 'etwa' ),
				'icon'  => 'dashicons-unlock',
				'tab'   => 'permission',
			),
			'holiday_work' => array(
				'label' => __( 'Work on Holiday', 'etwa' ),
				'icon'  => 'dashicons-star-filled',
				'tab'   => 'holiday_work',
			),
		);
	}

	public static function employee_request_view_id( string $tab ): string {
		return 'requests-' . str_replace( '_', '-', $tab );
	}

	public static function hr_request_view_id( string $tab ): string {
		return 'requests-hr-' . str_replace( '_', '-', $tab );
	}

	public static function is_employee_requests_view( string $view ): bool {
		return 'requests' === $view
			|| ( str_starts_with( $view, 'requests-' ) && ! str_starts_with( $view, 'requests-hr' ) );
	}

	public static function is_hr_requests_view( string $view ): bool {
		return 'requests-hr' === $view || str_starts_with( $view, 'requests-hr-' );
	}

	public static function request_tab_for_view( string $view ): string {
		$defs = self::request_type_definitions();
		foreach ( $defs as $tab => $def ) {
			if ( $view === self::employee_request_view_id( $tab ) || $view === self::hr_request_view_id( $tab ) ) {
				return $def['tab'];
			}
		}

		$from_query = isset( $_GET['etwa_request_tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_request_tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $defs[ $from_query ] ) ) {
			return $from_query;
		}

		return 'vacation';
	}

	public static function request_page_title( string $view, bool $is_reviewer ): string {
		unset( $is_reviewer );
		$tab   = self::request_tab_for_view( $view );
		$defs  = self::request_type_definitions();

		return $defs[ $tab ]['label'] ?? __( 'Requests', 'etwa' );
	}

	/** @return array<int,array{id:string,label:string,cap:string,icon:string,group:string}> */
	public static function items_for_user( int $user_id ): array {
		$items = array();

		if ( user_can( $user_id, RoleManager::CAP_VIEW_OWN ) && ! user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
			$items[] = array(
				'id'    => 'timesheet',
				'label' => __( 'My Timesheet', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_OWN,
				'icon'  => 'dashicons-clock',
				'group' => 'my-work',
			);
			$items[] = array(
				'id'    => 'history',
				'label' => __( 'History', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_OWN,
				'icon'  => 'dashicons-backup',
				'group' => 'my-work',
			);
			if ( user_can( $user_id, RoleManager::CAP_VIEW_OWN_VACATION ) ) {
				foreach ( self::request_type_definitions() as $tab => $def ) {
					$items[] = array(
						'id'    => self::employee_request_view_id( $tab ),
						'label' => $def['label'],
						'cap'   => RoleManager::CAP_VIEW_OWN_VACATION,
						'icon'  => $def['icon'],
						'group' => 'requests',
					);
				}
			}
			// Employees in a department flagged for deliverables (runtime access, not WP cap).
			if (
				! user_can( $user_id, RoleManager::CAP_VIEW_DELIVERABLES )
				&& RoleManager::user_can_view_deliverables( $user_id )
			) {
				$items[] = array(
					'id'    => 'deliverables',
					'label' => __( 'Deliverables', 'etwa' ),
					'cap'   => RoleManager::CAP_VIEW_OWN,
					'icon'  => 'dashicons-clipboard',
					'group' => 'deliverables',
				);
				$items[] = array(
					'id'    => 'files',
					'label' => __( 'Files', 'etwa' ),
					'cap'   => RoleManager::CAP_VIEW_OWN,
					'icon'  => 'dashicons-portfolio',
					'group' => 'deliverables',
				);
			}
		}

		if ( user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) && \ETWA\Helpers\DepartmentHelper::user_heads_department( $user_id ) ) {
			$items[] = array(
				'id'    => 'dept-dashboard',
				'label' => __( 'Department Dashboard', 'etwa' ),
				'cap'   => RoleManager::CAP_APPROVE_DEPT_REQUESTS,
				'icon'  => 'dashicons-networking',
				'group' => 'team',
			);
			if ( RoleManager::user_can_view_deliverables( $user_id ) && ! user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
				$items[] = array(
					'id'    => 'deliverables',
					'label' => __( 'Deliverables', 'etwa' ),
					'cap'   => RoleManager::CAP_VIEW_DELIVERABLES,
					'icon'  => 'dashicons-clipboard',
					'group' => 'deliverables',
				);
				$items[] = array(
					'id'    => 'files',
					'label' => __( 'Files', 'etwa' ),
					'cap'   => RoleManager::CAP_VIEW_DELIVERABLES,
					'icon'  => 'dashicons-portfolio',
					'group' => 'deliverables',
				);
			}
			// Dept heads without full HR access review team requests under Team.
			if ( ! user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
				foreach ( self::request_type_definitions() as $tab => $def ) {
					$items[] = array(
						'id'    => self::hr_request_view_id( $tab ),
						'label' => $def['label'],
						'cap'   => RoleManager::CAP_APPROVE_DEPT_REQUESTS,
						'icon'  => $def['icon'],
						'group' => 'team',
					);
				}
			}
		}

		if ( user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
			$items[] = array(
				'id'    => 'hr',
				'label' => __( 'HR Dashboard', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-groups',
				'group' => 'dashboard',
			);
			$items[] = array(
				'id'    => 'reports',
				'label' => __( 'Reports & Exports', 'etwa' ),
				'cap'   => RoleManager::CAP_EXPORT,
				'icon'  => 'dashicons-media-spreadsheet',
				'group' => 'reports',
			);
			$items[] = array(
				'id'    => 'deliverables',
				'label' => __( 'Deliverables', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_DELIVERABLES,
				'icon'  => 'dashicons-clipboard',
				'group' => 'deliverables',
			);
			$items[] = array(
				'id'    => 'files',
				'label' => __( 'Files', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_DELIVERABLES,
				'icon'  => 'dashicons-portfolio',
				'group' => 'deliverables',
			);
			$items[] = array(
				'id'    => 'client-analytics',
				'label' => __( 'Client Analytics', 'etwa' ),
				'cap'   => RoleManager::CAP_ANALYTICS,
				'icon'  => 'dashicons-chart-bar',
				'group' => 'reports',
			);
			$items[] = array(
				'id'    => 'employees',
				'label' => __( 'Employees', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-id',
				'group' => 'people',
			);
			$items[] = array(
				'id'    => 'departments',
				'label' => __( 'Departments', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_DEPARTMENTS,
				'icon'  => 'dashicons-networking',
				'group' => 'people',
			);
			if ( user_can( $user_id, RoleManager::CAP_MANAGE_VACATION ) ) {
				foreach ( self::request_type_definitions() as $tab => $def ) {
					$items[] = array(
						'id'    => self::hr_request_view_id( $tab ),
						'label' => $def['label'],
						'cap'   => RoleManager::CAP_MANAGE_VACATION,
						'icon'  => $def['icon'],
						'group' => 'requests',
					);
				}
			}
			$items[] = array(
				'id'    => 'holidays',
				'label' => __( 'Holidays', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-calendar-alt',
				'group' => 'time-off',
			);
			$items[] = array(
				'id'    => 'vacations',
				'label' => __( 'Vacations', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_VACATION,
				'icon'  => 'dashicons-palmtree',
				'group' => 'time-off',
			);
			$items[] = array(
				'id'    => 'logs',
				'label' => __( 'Logs', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-list-view',
				'group' => 'administration',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_ADV_ANALYTICS ) ) {
			$items[] = array(
				'id'    => 'analytics',
				'label' => __( 'Analytics', 'etwa' ),
				'cap'   => RoleManager::CAP_ADV_ANALYTICS,
				'icon'  => 'dashicons-chart-area',
				'group' => 'dashboard',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			$items[] = array(
				'id'    => 'clients',
				'label' => __( 'Clients', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_SETTINGS,
				'icon'  => 'dashicons-building',
				'group' => 'administration',
			);
		}

		$items[] = array(
			'id'    => 'my-profile',
			'label' => __( 'My Profile', 'etwa' ),
			'cap'   => '',
			'icon'  => 'dashicons-admin-users',
			'group' => 'account',
		);

		return $items;
	}

	/**
	 * Sidebar groups with nested nav items for the current user.
	 *
	 * @return array<int,array{id:string,label:string,icon:string,items:array<int,array{id:string,label:string,cap:string,icon:string,group:string}>}>
	 */
	public static function groups_for_user( int $user_id ): array {
		$items     = self::items_for_user( $user_id );
		$group_defs = self::group_definitions();
		/** @var array<string,array{id:string,label:string,icon:string,items:array<int,array<string,string>>>} $grouped */
		$grouped   = array();
		/** @var string[] $order */
		$order     = array();
		/** @var string[] $seen_ids */
		$seen_ids  = array();

		foreach ( $items as $item ) {
			if ( in_array( $item['id'], $seen_ids, true ) ) {
				continue;
			}
			$seen_ids[] = $item['id'];

			$group_id = (string) ( $item['group'] ?? 'general' );
			if ( ! isset( $grouped[ $group_id ] ) ) {
				$def = $group_defs[ $group_id ] ?? array(
					'label' => ucfirst( str_replace( '-', ' ', $group_id ) ),
					'icon'  => 'dashicons-menu',
				);
				$grouped[ $group_id ] = array(
					'id'    => $group_id,
					'label' => $def['label'],
					'icon'  => $def['icon'],
					'items' => array(),
				);
				$order[]              = $group_id;
			}

			$grouped[ $group_id ]['items'][] = $item;
		}

		$result = array();
		foreach ( $order as $group_id ) {
			if ( empty( $grouped[ $group_id ]['items'] ) ) {
				continue;
			}
			$result[] = $grouped[ $group_id ];
		}

		return $result;
	}

	/** @return array<string,array{label:string,icon:string}> */
	private static function group_definitions(): array {
		return array(
			'my-work'        => array(
				'label' => __( 'My work', 'etwa' ),
				'icon'  => 'dashicons-portfolio',
			),
			'requests'       => array(
				'label' => __( 'Requests', 'etwa' ),
				'icon'  => 'dashicons-clipboard',
			),
			'team'           => array(
				'label' => __( 'Team', 'etwa' ),
				'icon'  => 'dashicons-groups',
			),
			'dashboard'      => array(
				'label' => __( 'Dashboard', 'etwa' ),
				'icon'  => 'dashicons-dashboard',
			),
			'people'         => array(
				'label' => __( 'People', 'etwa' ),
				'icon'  => 'dashicons-id',
			),
			'time-off'       => array(
				'label' => __( 'Time & leave', 'etwa' ),
				'icon'  => 'dashicons-calendar-alt',
			),
			'reports'        => array(
				'label' => __( 'Reports', 'etwa' ),
				'icon'  => 'dashicons-chart-bar',
			),
			'deliverables'   => array(
				'label' => __( 'Deliverables', 'etwa' ),
				'icon'  => 'dashicons-portfolio',
			),
			'administration' => array(
				'label' => __( 'Administration', 'etwa' ),
				'icon'  => 'dashicons-admin-generic',
			),
			'account'        => array(
				'label' => __( 'Account', 'etwa' ),
				'icon'  => 'dashicons-admin-users',
			),
		);
	}

	public static function script_for_view( string $view ): string {
		if ( self::is_hr_requests_view( $view ) ) {
			return 'requests-manager';
		}
		if ( self::is_employee_requests_view( $view ) ) {
			return 'employee-requests';
		}

		return match ( $view ) {
			'hr'               => 'hr-dashboard',
			'employees'        => 'employees-manager',
			'departments'      => 'departments-manager',
			'dept-dashboard'   => 'department-dashboard',
			'holidays'         => 'holidays-manager',
			'vacations'        => 'vacation-manager',
			'my-vacation'      => 'employee-vacation',
			'client-analytics' => 'client-analytics',
			'reports'          => 'reports-exports',
			'files'            => 'files',
			'deliverables'     => 'deliverables',
			'analytics'        => 'owner-analytics',
			'clients'   => 'clients-manager',
			'logs'      => 'admin',
			'my-profile'=> 'my-profile',
			default     => 'employee-dashboard',
		};
	}

	public static function resolve_view( array $nav_items ): string {
		$allowed = array_column( $nav_items, 'id' );
		$requested = isset( $_GET['etwa_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $requested && in_array( $requested, $allowed, true ) ) {
			return $requested;
		}

		// Legacy: bare requests / requests-hr (+ optional tab) → typed view ids.
		$tab = self::request_tab_for_view( $requested ?: 'requests' );
		if ( in_array( $requested, array( 'requests', 'my-vacation' ), true ) ) {
			$mapped = self::employee_request_view_id( $tab );
			if ( in_array( $mapped, $allowed, true ) ) {
				return $mapped;
			}
		}
		if ( 'requests-hr' === $requested ) {
			$mapped = self::hr_request_view_id( $tab );
			if ( in_array( $mapped, $allowed, true ) ) {
				return $mapped;
			}
		}

		if ( ! empty( $_GET['client_id'] ) && in_array( 'client-analytics', $allowed, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'client-analytics';
		}

		if ( ! empty( $_GET['review_user'] ) && in_array( 'hr', $allowed, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'hr';
		}

		if ( ! empty( $_GET['review_user'] ) && in_array( 'dept-dashboard', $allowed, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 'dept-dashboard';
		}

		return $allowed[0] ?? 'timesheet';
	}

	/** @param array<string,mixed> $extra */
	/** @param array<string,mixed> $extra */
	public static function view_url( string $base_url, string $view, array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array( 'etwa_view' => $view ),
				$extra
			),
			$base_url
		);
	}

	/** @return array{title:string,subtitle:string} */
	public static function view_meta( string $view ): array {
		return match ( $view ) {
			'history'   => array(
				'title'    => __( 'History', 'etwa' ),
				'subtitle' => __( 'Past work weeks and submission status', 'etwa' ),
			),
			'hr'        => array(
				'title'    => __( 'HR Dashboard', 'etwa' ),
				'subtitle' => __( 'Approvals, reports, and exports', 'etwa' ),
			),
			'employees' => array(
				'title'    => __( 'Employees', 'etwa' ),
				'subtitle' => __( 'Create employees, profiles, and department assignments', 'etwa' ),
			),
			'departments' => array(
				'title'    => __( 'Departments', 'etwa' ),
				'subtitle' => __( 'Manage departments and department heads', 'etwa' ),
			),
			'dept-dashboard' => array(
				'title'    => __( 'Department Dashboard', 'etwa' ),
				'subtitle' => __( 'Team requests, employees, and upcoming leave', 'etwa' ),
			),
			'client-analytics' => array(
				'title'    => __( 'Client Analytics', 'etwa' ),
				'subtitle' => __( 'Hours, labor cost, and trends by client', 'etwa' ),
			),
			'reports' => array(
				'title'    => __( 'Reports & Exports', 'etwa' ),
				'subtitle' => __( 'Generate Excel reports with custom date ranges', 'etwa' ),
			),
			'files' => array(
				'title'    => __( 'Files', 'etwa' ),
				'subtitle' => __( 'Client files and scopes at a glance', 'etwa' ),
			),
			'deliverables' => array(
				'title'    => __( 'Deliverables', 'etwa' ),
				'subtitle' => __( 'Client scopes, allocations, and progress', 'etwa' ),
			),
			'holidays'  => array(
				'title'    => __( 'Holidays', 'etwa' ),
				'subtitle' => __( 'Company holidays and seven-day calendar', 'etwa' ),
			),
			'vacations' => array(
				'title'    => __( 'Vacations', 'etwa' ),
				'subtitle' => __( 'Leave allocation, usage, calendar, and analytics', 'etwa' ),
			),
			'requests-hr', 'requests-hr-vacation', 'requests-hr-wfh', 'requests-hr-permission', 'requests-hr-holiday-work' => array(
				'title'    => self::request_page_title( $view, true ),
				'subtitle' => __( 'Review vacation, WFH, and permission requests', 'etwa' ),
			),
			'requests', 'requests-vacation', 'requests-wfh', 'requests-permission', 'requests-holiday-work' => array(
				'title'    => self::request_page_title( $view, false ),
				'subtitle' => __( 'Submit vacation, work from home, and permission requests', 'etwa' ),
			),
			'my-vacation' => array(
				'title'    => __( 'My Vacation', 'etwa' ),
				'subtitle' => __( 'Submit leave requests and view your balances', 'etwa' ),
			),
			'analytics' => array(
				'title'    => __( 'Analytics', 'etwa' ),
				'subtitle' => __( 'Workforce insights and trends', 'etwa' ),
			),
			'clients'   => array(
				'title'    => __( 'Clients', 'etwa' ),
				'subtitle' => __( 'Manage client codes and departments', 'etwa' ),
			),
			'logs'      => array(
				'title'    => __( 'Logs', 'etwa' ),
				'subtitle' => __( 'Audit trail and system debug log', 'etwa' ),
			),
			'my-profile' => array(
				'title'    => __( 'My Profile', 'etwa' ),
				'subtitle' => __( 'Your employee information and account details', 'etwa' ),
			),
			default     => array(
				'title'    => __( 'My Timesheet', 'etwa' ),
				'subtitle' => __( 'Track your work week day by day', 'etwa' ),
			),
		};
	}

	public static function role_label_for_user( int $user_id ): string {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'User', 'etwa' );
		}

		return EmployeeProfileHelper::role_label_for_user( $user );
	}
}
