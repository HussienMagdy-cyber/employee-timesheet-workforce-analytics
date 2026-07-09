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

	/** @return array<int,array{id:string,label:string,cap:string,icon:string}> */
	public static function items_for_user( int $user_id ): array {
		$items = array();

		if ( user_can( $user_id, RoleManager::CAP_VIEW_OWN ) && ! user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
			$items[] = array(
				'id'    => 'timesheet',
				'label' => __( 'My Timesheet', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_OWN,
				'icon'  => 'dashicons-clock',
			);
			$items[] = array(
				'id'    => 'history',
				'label' => __( 'History', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_OWN,
				'icon'  => 'dashicons-backup',
			);
			$items[] = array(
				'id'    => 'requests',
				'label' => __( 'Requests', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_OWN_VACATION,
				'icon'  => 'dashicons-clipboard',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) && \ETWA\Helpers\DepartmentHelper::user_heads_department( $user_id ) ) {
			$items[] = array(
				'id'    => 'dept-dashboard',
				'label' => __( 'Department Dashboard', 'etwa' ),
				'cap'   => RoleManager::CAP_APPROVE_DEPT_REQUESTS,
				'icon'  => 'dashicons-networking',
			);
			$items[] = array(
				'id'    => 'requests-hr',
				'label' => __( 'Team Requests', 'etwa' ),
				'cap'   => RoleManager::CAP_APPROVE_DEPT_REQUESTS,
				'icon'  => 'dashicons-clipboard',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
			$items[] = array(
				'id'    => 'hr',
				'label' => __( 'HR Dashboard', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-groups',
			);
			$items[] = array(
				'id'    => 'client-analytics',
				'label' => __( 'Client Analytics', 'etwa' ),
				'cap'   => RoleManager::CAP_ANALYTICS,
				'icon'  => 'dashicons-chart-bar',
			);
			$items[] = array(
				'id'    => 'employees',
				'label' => __( 'Employees', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-id',
			);
			$items[] = array(
				'id'    => 'departments',
				'label' => __( 'Departments', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_DEPARTMENTS,
				'icon'  => 'dashicons-networking',
			);
			$items[] = array(
				'id'    => 'holidays',
				'label' => __( 'Holidays', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-calendar-alt',
			);
			$items[] = array(
				'id'    => 'requests-hr',
				'label' => __( 'Requests', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_VACATION,
				'icon'  => 'dashicons-clipboard',
			);
			$items[] = array(
				'id'    => 'vacations',
				'label' => __( 'Vacations', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_VACATION,
				'icon'  => 'dashicons-palmtree',
			);
			$items[] = array(
				'id'    => 'logs',
				'label' => __( 'Logs', 'etwa' ),
				'cap'   => RoleManager::CAP_VIEW_ALL,
				'icon'  => 'dashicons-list-view',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_ADV_ANALYTICS ) ) {
			$items[] = array(
				'id'    => 'analytics',
				'label' => __( 'Analytics', 'etwa' ),
				'cap'   => RoleManager::CAP_ADV_ANALYTICS,
				'icon'  => 'dashicons-chart-area',
			);
		}

		if ( user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			$items[] = array(
				'id'    => 'clients',
				'label' => __( 'Clients', 'etwa' ),
				'cap'   => RoleManager::CAP_MANAGE_SETTINGS,
				'icon'  => 'dashicons-building',
			);
		}

		$items[] = array(
			'id'    => 'my-profile',
			'label' => __( 'My Profile', 'etwa' ),
			'cap'   => '',
			'icon'  => 'dashicons-admin-users',
		);

		return $items;
	}

	public static function script_for_view( string $view ): string {
		return match ( $view ) {
			'hr'               => 'hr-dashboard',
			'employees'        => 'employees-manager',
			'departments'      => 'departments-manager',
			'dept-dashboard'   => 'department-dashboard',
			'holidays'         => 'holidays-manager',
			'vacations'        => 'vacation-manager',
			'requests-hr'      => 'requests-manager',
			'requests'         => 'employee-requests',
			'my-vacation'      => 'employee-vacation',
			'client-analytics' => 'client-analytics',
			'analytics'        => 'owner-analytics',
			'clients'   => 'clients-manager',
			'logs'      => 'admin',
			'my-profile'=> 'my-profile',
			default     => 'employee-dashboard',
		};
	}

	public static function resolve_view( array $nav_items ): string {
		$allowed = array_column( $nav_items, 'id' );
		$requested = isset( $_GET['etwa_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) ) : '';

		if ( $requested && in_array( $requested, $allowed, true ) ) {
			return $requested;
		}

		// Backward compatibility: my-vacation → requests center.
		if ( 'my-vacation' === $requested && in_array( 'requests', $allowed, true ) ) {
			return 'requests';
		}

		if ( ! empty( $_GET['client_id'] ) && in_array( 'client-analytics', $allowed, true ) ) {
			return 'client-analytics';
		}

		if ( ! empty( $_GET['review_user'] ) && in_array( 'hr', $allowed, true ) ) {
			return 'hr';
		}

		if ( ! empty( $_GET['review_user'] ) && in_array( 'dept-dashboard', $allowed, true ) ) {
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
			'holidays'  => array(
				'title'    => __( 'Holidays', 'etwa' ),
				'subtitle' => __( 'Company holidays and seven-day calendar', 'etwa' ),
			),
			'vacations' => array(
				'title'    => __( 'Vacations', 'etwa' ),
				'subtitle' => __( 'Leave allocation, usage, calendar, and analytics', 'etwa' ),
			),
			'requests-hr' => array(
				'title'    => __( 'Requests', 'etwa' ),
				'subtitle' => __( 'Review vacation, WFH, and permission requests', 'etwa' ),
			),
			'requests' => array(
				'title'    => __( 'Requests', 'etwa' ),
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
