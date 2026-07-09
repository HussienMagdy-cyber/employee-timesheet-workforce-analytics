<?php
/**
 * Employee creation and profile management.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Repositories\AuditLogRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeService {

	public function __construct(
		private AuditLogRepository $audit = new AuditLogRepository(),
		private EmployeeCostService $cost_service = new EmployeeCostService(),
		private DepartmentService $department_service = new DepartmentService(),
	) {}

	/** @param array<string,mixed> $data @return array<string,mixed>|WP_Error */
	public function create_employee( int $creator_id, array $data ): array|WP_Error {
		if ( ! EmployeeProfileHelper::can_manage_employees( $creator_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot create employees.', 'etwa' ), array( 'status' => 403 ) );
		}

		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'etwa' ) );
		}
		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'An account with this email already exists.', 'etwa' ), array( 'status' => 409 ) );
		}

		$display_name = sanitize_text_field( (string) ( $data['display_name'] ?? '' ) );
		if ( '' === $display_name ) {
			return new WP_Error( 'invalid_name', __( 'Full name is required.', 'etwa' ) );
		}

		$role = EmployeeProfileHelper::sanitize_role( $data['role'] ?? '' );
		if ( is_wp_error( $role ) ) {
			return $role;
		}

		$job_title = sanitize_text_field( (string) ( $data['job_title'] ?? '' ) );
		if ( '' === $job_title ) {
			return new WP_Error( 'invalid_job_title', __( 'Job title is required.', 'etwa' ) );
		}

		$join_date = EmployeeProfileHelper::sanitize_required_date( $data['join_date'] ?? '' );
		if ( is_wp_error( $join_date ) ) {
			return $join_date;
		}

		$birthday = EmployeeProfileHelper::sanitize_optional_date( $data['birthday'] ?? null );
		if ( null !== $data['birthday'] && '' !== $data['birthday'] && null === $birthday ) {
			return new WP_Error( 'invalid_birthday', __( 'Enter a valid birthday or leave it empty.', 'etwa' ) );
		}

		$department_id = ! empty( $data['department_id'] ) ? absint( $data['department_id'] ) : null;

		$user_id = wp_insert_user(
			array(
				'user_login'   => EmployeeProfileHelper::unique_login_from_email( $email ),
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $display_name,
				'first_name'   => $display_name,
				'role'         => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		EmployeeProfileHelper::save_meta( (int) $user_id, $job_title, $join_date, $birthday );

		if ( $department_id ) {
			$assigned = $this->department_service->assign_employee( $creator_id, (int) $user_id, $department_id );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
		}

		$password_sent = $this->send_password_reset_email( (int) $user_id );

		$this->audit->log(
			'employee_created',
			'employee',
			(int) $user_id,
			null,
			array(
				'email'       => $email,
				'display_name'=> $display_name,
				'role'        => $role,
				'job_title'   => $job_title,
				'join_date'   => $join_date,
			),
			$creator_id,
			array( 'employee_id' => (int) $user_id )
		);

		$profile = EmployeeProfileHelper::build_profile_payload( (int) $user_id, $creator_id );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		$profile['password_email_sent'] = $password_sent;

		return $profile;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_profile( int $viewer_id, int $employee_id ): array|WP_Error {
		if ( ! RoleManager::user_can_view_employee( $viewer_id, $employee_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this employee profile.', 'etwa' ), array( 'status' => 403 ) );
		}

		return EmployeeProfileHelper::build_profile_payload( $employee_id, $viewer_id );
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|WP_Error */
	public function update_profile( int $editor_id, int $employee_id, array $data ): array|WP_Error {
		if ( ! EmployeeProfileHelper::can_manage_employees( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit employee profiles.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! EmployeeProfileHelper::is_managed_user( $employee_id ) ) {
			return new WP_Error( 'invalid_employee', __( 'Invalid employee.', 'etwa' ), array( 'status' => 404 ) );
		}

		$user = get_userdata( $employee_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$old_meta = EmployeeProfileHelper::read_meta( $employee_id );

		if ( isset( $data['display_name'] ) ) {
			$display_name = sanitize_text_field( (string) $data['display_name'] );
			if ( '' === $display_name ) {
				return new WP_Error( 'invalid_name', __( 'Full name is required.', 'etwa' ) );
			}
			wp_update_user(
				array(
					'ID'           => $employee_id,
					'display_name' => $display_name,
					'first_name'   => $display_name,
				)
			);
		}

		if ( isset( $data['email'] ) ) {
			$email = sanitize_email( (string) $data['email'] );
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'etwa' ) );
			}
			$existing = email_exists( $email );
			if ( $existing && (int) $existing !== $employee_id ) {
				return new WP_Error( 'email_exists', __( 'An account with this email already exists.', 'etwa' ), array( 'status' => 409 ) );
			}
			wp_update_user(
				array(
					'ID'         => $employee_id,
					'user_email' => $email,
				)
			);
		}

		if ( isset( $data['role'] ) ) {
			$role = EmployeeProfileHelper::sanitize_role( $data['role'] );
			if ( is_wp_error( $role ) ) {
				return $role;
			}
			$updated_user = new \WP_User( $employee_id );
			$updated_user->set_role( $role );
		}

		$job_title = array_key_exists( 'job_title', $data )
			? sanitize_text_field( (string) $data['job_title'] )
			: $old_meta['job_title'];
		if ( '' === $job_title ) {
			return new WP_Error( 'invalid_job_title', __( 'Job title is required.', 'etwa' ) );
		}

		$join_date = array_key_exists( 'join_date', $data )
			? EmployeeProfileHelper::sanitize_required_date( $data['join_date'] )
			: $old_meta['join_date'];
		if ( is_wp_error( $join_date ) ) {
			return $join_date;
		}

		$birthday = array_key_exists( 'birthday', $data )
			? EmployeeProfileHelper::sanitize_optional_date( $data['birthday'] )
			: ( $old_meta['birthday'] ?: null );
		if ( array_key_exists( 'birthday', $data ) && null !== $data['birthday'] && '' !== $data['birthday'] && null === $birthday ) {
			return new WP_Error( 'invalid_birthday', __( 'Enter a valid birthday or leave it empty.', 'etwa' ) );
		}

		EmployeeProfileHelper::save_meta( $employee_id, $job_title, $join_date, $birthday );

		if ( array_key_exists( 'department_id', $data ) ) {
			$department_id = ! empty( $data['department_id'] ) ? absint( $data['department_id'] ) : null;
			$assigned      = $this->department_service->assign_employee( $editor_id, $employee_id, $department_id );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
		}

		if ( array_key_exists( 'hourly_cost_egp', $data ) && EmployeeCostHelper::can_view_labor_cost( $editor_id ) ) {
			$sanitized = EmployeeCostHelper::sanitize_hourly_cost( $data['hourly_cost_egp'] );
			if ( is_wp_error( $sanitized ) ) {
				return $sanitized;
			}
			$this->cost_service->update_hourly_cost( $editor_id, $employee_id, $sanitized );
		}

		$this->audit->log(
			'employee_profile_update',
			'employee',
			$employee_id,
			$old_meta,
			EmployeeProfileHelper::read_meta( $employee_id ),
			$editor_id,
			array( 'employee_id' => $employee_id )
		);

		return EmployeeProfileHelper::build_profile_payload( $employee_id, $editor_id );
	}

	/**
	 * Update role and/or department assignment from the employees list.
	 *
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_assignment( int $editor_id, int $employee_id, array $data ): array|WP_Error {
		if ( ! EmployeeProfileHelper::can_manage_employees( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit employee assignments.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! EmployeeProfileHelper::is_managed_user( $employee_id ) ) {
			return new WP_Error( 'invalid_employee', __( 'Invalid employee.', 'etwa' ), array( 'status' => 404 ) );
		}

		$user = get_userdata( $employee_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$old_role = EmployeeProfileHelper::primary_role_slug( $user );
		$old_dept = DepartmentHelper::get_employee_department_id( $employee_id );

		if ( array_key_exists( 'role', $data ) ) {
			$role = EmployeeProfileHelper::sanitize_role( $data['role'] );
			if ( is_wp_error( $role ) ) {
				return $role;
			}
			$updated_user = new \WP_User( $employee_id );
			$updated_user->set_role( $role );
		}

		if ( array_key_exists( 'department_id', $data ) ) {
			$department_id = ! empty( $data['department_id'] ) ? absint( $data['department_id'] ) : null;
			$assigned      = $this->department_service->assign_employee( $editor_id, $employee_id, $department_id );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
		}

		if ( array_key_exists( 'hourly_cost_egp', $data ) && EmployeeCostHelper::can_view_labor_cost( $editor_id ) ) {
			$sanitized = EmployeeCostHelper::sanitize_hourly_cost( $data['hourly_cost_egp'] );
			if ( is_wp_error( $sanitized ) ) {
				return $sanitized;
			}
			$updated_cost = $this->cost_service->update_hourly_cost( $editor_id, $employee_id, $sanitized );
			if ( is_wp_error( $updated_cost ) ) {
				return $updated_cost;
			}
		}

		$user = get_userdata( $employee_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$new_role = EmployeeProfileHelper::primary_role_slug( $user );
		$new_dept = DepartmentHelper::get_employee_department_id( $employee_id );

		$this->audit->log(
			'employee_assignment_update',
			'employee',
			$employee_id,
			array(
				'role'          => $old_role,
				'department_id' => $old_dept,
			),
			array(
				'role'          => $new_role,
				'department_id' => $new_dept ?: null,
			),
			$editor_id,
			array( 'employee_id' => $employee_id )
		);

		$dept_name = '';
		if ( $new_dept ) {
			$dept_row  = DepartmentHelper::get_departments_batch( array( $employee_id ) );
			$dept_name = $dept_row[ $employee_id ]['name'] ?? '';
		}

		return array(
			'id'              => $employee_id,
			'display_name'    => $user->display_name,
			'email'           => $user->user_email,
			'role'            => $new_role,
			'role_label'      => EmployeeProfileHelper::role_label_for_user( $user ),
			'department_id'   => $new_dept ?: null,
			'department_name' => $dept_name,
			'hourly_cost_egp' => EmployeeCostHelper::can_view_labor_cost( $editor_id )
				? EmployeeCostHelper::get_hourly_cost_internal( $employee_id )
				: null,
		);
	}

	/** @return true|WP_Error */
	public function send_password_reset( int $editor_id, int $employee_id ): bool|WP_Error {
		if ( ! EmployeeProfileHelper::can_manage_employees( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot send password reset emails.', 'etwa' ), array( 'status' => 403 ) );
		}

		$user = get_userdata( $employee_id );
		if ( ! $user ) {
			return new WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $this->send_password_reset_email( $employee_id ) ) {
			return new WP_Error( 'email_failed', __( 'Could not send the password reset email.', 'etwa' ) );
		}

		return true;
	}

	private function send_password_reset_email( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		return (bool) retrieve_password( $user->user_login );
	}
}
