<?php
/**
 * Client REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\ClientRepository;
use ETWA\Services\ClientService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/clients',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_clients' ),
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<id>\d+)/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/all',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_all_clients' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_MANAGE_SETTINGS );
	}

	public function list_clients( WP_REST_Request $request ) {
		$repo    = new ClientRepository();
		$search  = $this->get_string_param( $request, 'search' );
		$clients = $repo->all_for_dropdown( $search ?: null );
		return $this->success( array_map( fn( $c ) => $c->to_array(), $clients ) );
	}

	public function list_all_clients() {
		$repo = new ClientRepository();
		return $this->success( array_map( fn( $c ) => $c->to_array(), $repo->all() ) );
	}

	public function create_client( WP_REST_Request $request ) {
		$service = new ClientService();
		$result  = $service->create(
			get_current_user_id(),
			array(
				'name'       => $this->get_string_param( $request, 'name' ),
				'code'       => $this->get_string_param( $request, 'code' ),
				'department' => $this->get_string_param( $request, 'department', 'General' ),
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function update_client( WP_REST_Request $request ) {
		$service = new ClientService();
		$result  = $service->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			array(
				'name'       => $request->get_param( 'name' ),
				'code'       => $request->get_param( 'code' ),
				'department' => $request->get_param( 'department' ),
				'is_active'  => $request->get_param( 'is_active' ),
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function deactivate_client( WP_REST_Request $request ) {
		$service = new ClientService();
		$result  = $service->deactivate( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deactivated' => true ) );
	}

	public function delete_client( WP_REST_Request $request ) {
		$service = new ClientService();
		$result  = $service->delete( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}
}
