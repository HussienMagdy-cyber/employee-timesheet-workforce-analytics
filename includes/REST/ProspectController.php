<?php
/**
 * Prospect REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\ProspectService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/prospects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_prospects' ),
					'permission_callback' => static function () {
						return is_user_logged_in();
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_prospect' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/prospects/all',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_all_prospects' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/prospects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_prospect' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_prospect' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/prospects/(?P<id>\d+)/won',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_won' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/prospects/(?P<id>\d+)/lost',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_lost' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/prospects/(?P<id>\d+)/convert',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'convert_to_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_MANAGE_SETTINGS );
	}

	public function list_prospects() {
		$service = new ProspectService();
		return $this->success( $service->list_for_dropdown() );
	}

	public function list_all_prospects( WP_REST_Request $request ) {
		$service = new ProspectService();
		$status  = $this->get_string_param( $request, 'status' );
		$result  = $service->list_all( get_current_user_id(), $status ?: null );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function get_prospect( WP_REST_Request $request ) {
		$service = new ProspectService();
		$result  = $service->get( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_prospect( WP_REST_Request $request ) {
		$service = new ProspectService();
		$result  = $service->create(
			get_current_user_id(),
			array(
				'name'                => $this->get_string_param( $request, 'name' ),
				'source'              => $this->get_string_param( $request, 'source' ),
				'prospect_start_date' => $this->get_string_param( $request, 'prospect_start_date' ),
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function update_prospect( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$payload = array(
			'name'        => $request->get_param( 'name' ),
			'lost_reason' => $request->get_param( 'lost_reason' ),
		);
		if ( array_key_exists( 'source', $data ) ) {
			$payload['source'] = $data['source'];
		}
		if ( array_key_exists( 'prospect_start_date', $data ) ) {
			$payload['prospect_start_date'] = $data['prospect_start_date'];
		}

		$service = new ProspectService();
		$result  = $service->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$payload
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function mark_won( WP_REST_Request $request ) {
		$service = new ProspectService();
		$result  = $service->mark_won( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function mark_lost( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$service = new ProspectService();
		$result  = $service->mark_lost(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			(string) ( $data['lost_reason'] ?? '' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function convert_to_client( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$service = new ProspectService();
		$result  = $service->convert_to_client(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			array(
				'name'       => $data['name'] ?? '',
				'code'       => $data['code'] ?? '',
				'department' => $data['department'] ?? 'General',
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}
}
