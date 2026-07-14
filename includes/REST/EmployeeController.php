<?php
/**
 * Employee REST endpoints (HR/Owner).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Services\EmployeeCostService;
use ETWA\Services\EmployeeService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/employees',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_employees' ),
					'permission_callback' => array( $this, 'can_list_employees' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'include_inactive' => array(
							'type'              => 'boolean',
							'default'           => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_employee' ),
					'permission_callback' => array( $this, 'can_manage_employees' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/me/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'my_profile' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_my_profile' ),
					'permission_callback' => array( $this, 'can_update_own_profile' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_profile' ),
					'permission_callback' => array( $this, 'can_manage_employees' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/send-password-reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_password_reset' ),
					'permission_callback' => array( $this, 'can_manage_employees' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/assignment',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_assignment' ),
					'permission_callback' => array( $this, 'can_manage_employees' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/status',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_employment_status' ),
					'permission_callback' => array( $this, 'can_manage_employees' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/hourly-cost',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_hourly_cost' ),
					'permission_callback' => array( $this, 'can_view_labor_cost' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/employees/(?P<id>\d+)/department',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_department' ),
					'permission_callback' => array( $this, 'can_view_labor_cost' ),
				),
			)
		);
	}

	public function can_view_labor_cost(): bool {
		return is_user_logged_in() && EmployeeCostHelper::can_view_labor_cost();
	}

	public function can_list_employees(): bool {
		return is_user_logged_in() && (
			EmployeeProfileHelper::can_manage_employees()
			|| EmployeeCostHelper::can_view_labor_cost()
		);
	}

	public function can_manage_employees(): bool {
		return is_user_logged_in() && EmployeeProfileHelper::can_manage_employees();
	}

	public function can_update_own_profile(): bool {
		$user_id = get_current_user_id();
		return $user_id > 0 && EmployeeProfileHelper::can_edit_own_profile( $user_id, $user_id );
	}

	public function list_employees( WP_REST_Request $request ) {
		$service = new EmployeeCostService();
		$include_inactive = rest_sanitize_boolean( $request->get_param( 'include_inactive' ) );
		$result  = $service->list_employees(
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 ),
			$this->get_string_param( $request, 'search' ),
			$include_inactive
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_employee( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->create_employee( get_current_user_id(), $this->get_request_data( $request ) );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->success( $result, 201 );
	}

	public function my_profile() {
		$service = new EmployeeService();
		$result  = $service->get_profile( get_current_user_id(), get_current_user_id() );

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_my_profile( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->update_own_profile( get_current_user_id(), $this->get_request_data( $request ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function get_profile( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->get_profile( get_current_user_id(), $this->get_int_param( $request, 'id' ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_profile( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->update_profile(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_assignment( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->update_assignment(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function set_employment_status( WP_REST_Request $request ) {
		$data   = $this->get_request_data( $request );
		$status = sanitize_key( (string) ( $data['status'] ?? $request->get_param( 'status' ) ?? '' ) );
		$service = new EmployeeService();
		$result  = $service->set_employment_status(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$status
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function send_password_reset( WP_REST_Request $request ) {
		$service = new EmployeeService();
		$result  = $service->send_password_reset( get_current_user_id(), $this->get_int_param( $request, 'id' ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'sent' => true ) );
	}

	public function update_hourly_cost( WP_REST_Request $request ) {
		$employee_id = $this->get_int_param( $request, 'id' );
		$raw_cost    = $request->get_param( 'hourly_cost_egp' );

		if ( null === $raw_cost ) {
			$raw_cost = $request->get_param( 'hourly_cost' );
		}

		$sanitized = EmployeeCostHelper::sanitize_hourly_cost( $raw_cost );
		if ( is_wp_error( $sanitized ) ) {
			return $this->error( $sanitized );
		}

		$service = new EmployeeCostService();
		$result  = $service->update_hourly_cost( get_current_user_id(), $employee_id, $sanitized );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->success(
			array(
				'id'              => $employee_id,
				'hourly_cost_egp' => $sanitized,
			)
		);
	}

	public function update_department( WP_REST_Request $request ) {
		$employee_id   = $this->get_int_param( $request, 'id' );
		$department_id = $request->get_param( 'department_id' );
		$department_id = $department_id ? absint( $department_id ) : null;

		$service = new EmployeeCostService();
		$result  = $service->update_department( get_current_user_id(), $employee_id, $department_id );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->success(
			array(
				'id'            => $employee_id,
				'department_id' => $department_id,
			)
		);
	}
}
