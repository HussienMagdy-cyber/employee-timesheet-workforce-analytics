<?php
/**
 * Super Client REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Services\SuperClientAccountService;
use ETWA\Services\SuperClientAnalyticsService;
use ETWA\Services\SuperClientRequestService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientController extends RestController {

	private SuperClientAccountService $accounts;
	private SuperClientRequestService $requests;
	private SuperClientAnalyticsService $analytics;

	public function __construct() {
		$this->accounts  = new SuperClientAccountService();
		$this->requests  = new SuperClientRequestService();
		$this->analytics = new SuperClientAnalyticsService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/super-clients',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_directory' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<id>\d+)/super-clients',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_accounts' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_account' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-clients/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_account' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_account' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-clients/(?P<id>\d+)/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_account' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<id>\d+)/super-client-requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_requests' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-client-requests/pending-count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'pending_count' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/super-client-requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_request' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_request' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_request' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<id>\d+)/super-client-analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_analytics' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && SuperClientAccessHelper::can_manage_products( get_current_user_id() );
	}

	public function can_access(): bool {
		return is_user_logged_in() && SuperClientAccessHelper::can_access_product_hub( get_current_user_id() );
	}

	public function list_directory() {
		$result = $this->accounts->list_directory( get_current_user_id() );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function list_accounts( WP_REST_Request $request ) {
		$result = $this->accounts->list_for_super_client(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_account( WP_REST_Request $request ) {
		$result = $this->accounts->create(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function get_account( WP_REST_Request $request ) {
		$result = $this->accounts->get( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_account( WP_REST_Request $request ) {
		$result = $this->accounts->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function deactivate_account( WP_REST_Request $request ) {
		$result = $this->accounts->deactivate( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deactivated' => true ) );
	}

	public function pending_count() {
		$result = $this->requests->pending_counts_for_user( get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$result['pending'] = (int) ( $result['total'] ?? 0 );

		return $this->success( $result );
	}

	public function list_requests( WP_REST_Request $request ) {
		$filters = array();
		if ( $request->get_param( 'brand_id' ) ) {
			$filters['brand_id'] = $this->get_int_param( $request, 'brand_id' );
		} elseif ( $request->get_param( 'child_client_id' ) ) {
			$filters['brand_id'] = $this->get_int_param( $request, 'child_client_id' );
		}
		if ( $request->get_param( 'request_type' ) ) {
			$filters['request_type'] = $this->get_string_param( $request, 'request_type' );
		}
		if ( $request->get_param( 'service_category' ) ) {
			$filters['service_category'] = $this->get_string_param( $request, 'service_category' );
		}

		$result = $this->requests->list_for_super_client(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$filters
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_request( WP_REST_Request $request ) {
		$result = $this->requests->create(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		$payload = $this->requests->get( get_current_user_id(), (int) $result->id );
		return is_wp_error( $payload ) ? $this->success( $result->to_array() ) : $this->success( $payload );
	}

	public function get_request( WP_REST_Request $request ) {
		$result = $this->requests->get( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_request( WP_REST_Request $request ) {
		$result = $this->requests->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		$payload = $this->requests->get( get_current_user_id(), (int) $result->id );
		return is_wp_error( $payload ) ? $this->success( $result->to_array() ) : $this->success( $payload );
	}

	public function delete_request( WP_REST_Request $request ) {
		$result = $this->requests->delete( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function get_analytics( WP_REST_Request $request ) {
		$preset = $this->get_string_param( $request, 'preset' ) ?: DateRangeHelper::PRESET_ALL_TIME;
		$range  = DateRangeHelper::resolve(
			$preset,
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);

		$has_date_filter = null !== $request->get_param( 'preset' )
			|| null !== $request->get_param( 'date_from' )
			|| null !== $request->get_param( 'date_to' );

		$result = $this->analytics->get_summary(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$has_date_filter ? $range['date_from'] : null,
			$has_date_filter ? $range['date_to'] : null
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$result['range_meta'] = $range;
		$result['presets']    = DateRangeHelper::prospect_analytics_preset_labels();

		return $this->success( $result );
	}
}
