<?php
/**
 * Role and capability management.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RoleManager {

	public const ROLES_VERSION = '1.0.0';

	public const CAP_VIEW_OWN          = 'etwa_view_own_timesheet';
	public const CAP_EDIT_OWN          = 'etwa_edit_own_timesheet';
	public const CAP_SUBMIT            = 'etwa_submit_timesheet';
	public const CAP_VIEW_ALL          = 'etwa_view_all_timesheets';
	public const CAP_APPROVE_TS        = 'etwa_approve_timesheets';
	public const CAP_APPROVE_OT        = 'etwa_approve_overtime';
	public const CAP_EXPORT            = 'etwa_export_reports';
	public const CAP_EMAIL             = 'etwa_email_reports';
	public const CAP_ANALYTICS         = 'etwa_view_analytics';
	public const CAP_ADV_ANALYTICS     = 'etwa_view_advanced_analytics';
	public const CAP_MANAGE_SETTINGS   = 'etwa_manage_settings';
	public const CAP_LOCK_PERIODS      = 'etwa_lock_periods';
	public const CAP_MANAGE_VACATION   = 'etwa_manage_vacation';
	public const CAP_VIEW_OWN_VACATION = 'etwa_view_own_vacation';
	public const CAP_MANAGE_DEPARTMENTS = 'etwa_manage_departments';
	public const CAP_APPROVE_DEPT_REQUESTS = 'etwa_approve_department_requests';
	public const CAP_VIEW_DEPT_EMPLOYEES   = 'etwa_view_department_employees';
	public const CAP_VIEW_DEPT_CALENDAR    = 'etwa_view_department_calendar';

	/** @return string[] */
	public static function all_capabilities(): array {
		return array(
			self::CAP_VIEW_OWN,
			self::CAP_EDIT_OWN,
			self::CAP_SUBMIT,
			self::CAP_VIEW_ALL,
			self::CAP_APPROVE_TS,
			self::CAP_APPROVE_OT,
			self::CAP_EXPORT,
			self::CAP_EMAIL,
			self::CAP_ANALYTICS,
			self::CAP_ADV_ANALYTICS,
			self::CAP_MANAGE_SETTINGS,
			self::CAP_LOCK_PERIODS,
			self::CAP_MANAGE_VACATION,
			self::CAP_VIEW_OWN_VACATION,
			self::CAP_MANAGE_DEPARTMENTS,
			self::CAP_APPROVE_DEPT_REQUESTS,
			self::CAP_VIEW_DEPT_EMPLOYEES,
			self::CAP_VIEW_DEPT_CALENDAR,
		);
	}

	public static function register_roles_and_caps(): void {
		$stored_version = (string) get_option( 'etwa_roles_version', '' );
		if ( self::ROLES_VERSION === $stored_version ) {
			return;
		}

		$employee_caps = array(
			self::CAP_VIEW_OWN          => true,
			self::CAP_EDIT_OWN          => true,
			self::CAP_SUBMIT            => true,
			self::CAP_VIEW_OWN_VACATION => true,
		);

		$dept_head_caps = array(
			self::CAP_VIEW_OWN          => true,
			self::CAP_EDIT_OWN          => true,
			self::CAP_SUBMIT            => true,
			self::CAP_VIEW_OWN_VACATION => true,
			self::CAP_APPROVE_DEPT_REQUESTS => true,
			self::CAP_VIEW_DEPT_EMPLOYEES   => true,
			self::CAP_VIEW_DEPT_CALENDAR    => true,
		);

		$hr_caps = array(
			self::CAP_VIEW_ALL          => true,
			self::CAP_APPROVE_TS        => true,
			self::CAP_APPROVE_OT        => true,
			self::CAP_EXPORT            => true,
			self::CAP_EMAIL             => true,
			self::CAP_ANALYTICS         => true,
			self::CAP_ADV_ANALYTICS     => true,
			self::CAP_MANAGE_SETTINGS   => true,
			self::CAP_MANAGE_VACATION   => true,
			self::CAP_VIEW_OWN_VACATION => true,
			self::CAP_MANAGE_DEPARTMENTS => true,
			self::CAP_APPROVE_DEPT_REQUESTS => true,
		);

		$owner_caps = array_merge(
			$hr_caps,
			array(
				self::CAP_ADV_ANALYTICS   => true,
				self::CAP_MANAGE_SETTINGS => true,
				self::CAP_LOCK_PERIODS    => true,
			)
		);

		self::ensure_role( 'etwa_employee', BrandHelper::role_label( __( 'Employee', 'etwa' ) ), $employee_caps );
		self::ensure_role( 'etwa_department_head', BrandHelper::role_label( __( 'Department Head', 'etwa' ) ), $dept_head_caps );
		self::ensure_role( 'etwa_hr_manager', BrandHelper::role_label( __( 'HR Manager', 'etwa' ) ), $hr_caps );
		self::ensure_role( 'etwa_owner', BrandHelper::role_label( __( 'Owner', 'etwa' ) ), $owner_caps );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::all_capabilities() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		update_option( 'etwa_roles_version', self::ROLES_VERSION );
	}

	/** @param array<string,bool> $caps */
	private static function ensure_role( string $slug, string $label, array $caps ): void {
		$role = get_role( $slug );
		if ( ! $role ) {
			add_role( $slug, $label, $caps );
		} else {
			self::update_role_label( $slug, $label );
		}

		$role = get_role( $slug );
		if ( ! $role ) {
			return;
		}

		foreach ( self::all_capabilities() as $cap ) {
			$role->remove_cap( $cap );
		}

		foreach ( $caps as $cap => $granted ) {
			if ( $granted ) {
				$role->add_cap( $cap );
			}
		}
	}

	private static function update_role_label( string $slug, string $label ): void {
		global $wp_roles;
		if ( ! isset( $wp_roles->roles[ $slug ] ) ) {
			return;
		}

		if ( $wp_roles->roles[ $slug ]['name'] === $label ) {
			return;
		}

		$wp_roles->roles[ $slug ]['name'] = $label;
		update_option( $wp_roles->role_key, $wp_roles->roles );
	}

	/** @return string[] WordPress roles treated as workforce members in lists and dropdowns. */
	public static function workforce_role_slugs(): array {
		return array( 'etwa_employee', 'etwa_department_head' );
	}

	public static function is_workforce_member( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( self::workforce_role_slugs() as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function user_can_view_timesheet( int $viewer_id, int $owner_id ): bool {
		if ( $viewer_id === $owner_id ) {
			return user_can( $viewer_id, self::CAP_VIEW_OWN );
		}
		if ( user_can( $viewer_id, self::CAP_VIEW_ALL ) ) {
			return true;
		}

		return self::user_can_view_employee( $viewer_id, $owner_id );
	}

	public static function user_can_list_timesheets( int $viewer_id ): bool {
		if ( user_can( $viewer_id, self::CAP_VIEW_ALL ) ) {
			return true;
		}

		return user_can( $viewer_id, self::CAP_VIEW_DEPT_EMPLOYEES )
			&& \ETWA\Helpers\DepartmentHelper::user_heads_department( $viewer_id );
	}

	public static function user_can_edit_timesheet( int $user_id, int $timesheet_user_id, string $status, bool $is_locked ): bool {
		if ( $is_locked || 'approved' === $status ) {
			return user_can( $user_id, self::CAP_LOCK_PERIODS );
		}
		if ( $user_id === $timesheet_user_id ) {
			return user_can( $user_id, self::CAP_EDIT_OWN );
		}
		return user_can( $user_id, self::CAP_APPROVE_TS );
	}

	public static function user_can_view_employee( int $viewer_id, int $employee_id ): bool {
		if ( user_can( $viewer_id, self::CAP_VIEW_ALL ) ) {
			return true;
		}
		if ( $viewer_id === $employee_id ) {
			return true;
		}
		if ( ! user_can( $viewer_id, self::CAP_VIEW_DEPT_EMPLOYEES ) ) {
			return false;
		}
		$viewer_dept = \ETWA\Helpers\DepartmentHelper::get_headed_department_id( $viewer_id );
		$employee_dept = \ETWA\Helpers\DepartmentHelper::get_employee_department_id( $employee_id );
		return $viewer_dept && $employee_dept && $viewer_dept === $employee_dept;
	}
}
