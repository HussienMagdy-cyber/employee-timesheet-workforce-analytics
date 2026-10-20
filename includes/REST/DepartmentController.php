<?php
/**
 * Department REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\DepartmentAnalyticsService;
use ETWA\Services\DepartmentService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/departments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_departments' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_department' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/departments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_department' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_department' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_department' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/departments/product-tab-options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'product_tab_options' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/departments/(?P<id>\d+)/product-tabs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_tabs' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_product_tabs' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/departments/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => array( $this, 'can_view_dashboard' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/departments/analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'analytics' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'date_from' => array( 'type' => 'string', 'default' => '' ),
						'date_to'   => array( 'type' => 'string', 'default' => '' ),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && user_can( get_current_user_id(), RoleManager::CAP_MANAGE_DEPARTMENTS );
	}

	public function can_access(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( user_can( get_current_user_id(), RoleManager::CAP_VIEW_ALL ) ) {
			return true;
		}
		$service = new DepartmentService();
		return $service->can_manage() || $service->can_view_department_dashboard();
	}

	public function can_view_dashboard(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return ( new DepartmentService() )->can_view_department_dashboard();
	}

	public function list_departments( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->list_departments( get_current_user_id() );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function get_department( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->get_department( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_department( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->create(
			get_current_user_id(),
			array(
				'name'                      => $this->get_string_param( $request, 'name' ),
				'description'               => $this->get_string_param( $request, 'description' ),
				'head_user_id'              => $request->get_param( 'head_user_id' ),
				'approval_enabled'          => $request->get_param( 'approval_enabled' ),
				'can_allocate_deliverables' => $request->get_param( 'can_allocate_deliverables' ),
				'status'                    => $this->get_string_param( $request, 'status', 'active' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		$service = new DepartmentService();
		return $this->success( $service->get_department( get_current_user_id(), $result->id ) );
	}

	public function update_department( WP_REST_Request $request ) {
		$id     = $this->get_int_param( $request, 'id' );
		$result = ( new DepartmentService() )->update(
			get_current_user_id(),
			$id,
			array(
				'name'                      => $this->get_string_param( $request, 'name' ),
				'description'               => $this->get_string_param( $request, 'description' ),
				'head_user_id'              => $request->get_param( 'head_user_id' ),
				'approval_enabled'          => $request->get_param( 'approval_enabled' ),
				'can_allocate_deliverables' => $request->get_param( 'can_allocate_deliverables' ),
				'status'                    => $this->get_string_param( $request, 'status' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( ( new DepartmentService() )->get_department( get_current_user_id(), $id ) );
	}

	public function delete_department( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->delete( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function product_tab_options( WP_REST_Request $request ) {
		unset( $request );
		$result = ( new DepartmentService() )->list_product_tab_options( get_current_user_id() );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function get_product_tabs( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->get_product_tab_access(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_product_tabs( WP_REST_Request $request ) {
		$result = ( new DepartmentService() )->set_product_tab_access(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function dashboard( WP_REST_Request $request ) {
		$result = ( new DepartmentAnalyticsService() )->dashboard( get_current_user_id() );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function analytics( WP_REST_Request $request ) {
		$from = $this->get_string_param( $request, 'date_from' );
		$to   = $this->get_string_param( $request, 'date_to' );
		if ( ! $from || ! $to ) {
			$to   = gmdate( 'Y-m-d' );
			$from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		}
		$result = ( new DepartmentAnalyticsService() )->get_analytics( $from, $to );
		return $this->success( $result );
	}
}
