<?php
/**
 * Generic employee request REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RequestHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Services\RequestExportService;
use ETWA\Services\RequestService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestController extends RestController {

	private RequestService $service;

	public function __construct() {
		$this->service = new RequestService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_requests' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/permission-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'permission_types' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/permission-reasons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'permission_reasons' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/permission-usage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'permission_usage' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/holiday-work-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'holiday_work_types' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/pending-counts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'pending_counts' ),
					'permission_callback' => array( $this, 'can_review' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/bulk-review',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_review' ),
					'permission_callback' => array( $this, 'can_review' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/notifications',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'notifications' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'dismiss_notifications' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/(?P<id>\d+)',
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
					'permission_callback' => array( $this, 'can_access' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/requests/(?P<id>\d+)/review',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'review_request' ),
					'permission_callback' => array( $this, 'can_review' ),
				),
			)
		);
	}

	public function can_access(): bool {
		$service = $this->service;
		$user_id = get_current_user_id();
		return $service->can_manage( $user_id ) || $service->can_view_own( $user_id ) || $service->is_department_head( $user_id );
	}

	public function can_view_own(): bool {
		return current_user_can( RoleManager::CAP_VIEW_OWN_VACATION );
	}

	public function can_manage(): bool {
		return current_user_can( RoleManager::CAP_MANAGE_VACATION );
	}

	public function can_review(): bool {
		$user_id = get_current_user_id();
		return $this->service->can_review( $user_id );
	}

	public function can_export(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT );
	}

	public function list_requests( WP_REST_Request $request ) {
		$filters = array(
			'request_type'    => $this->get_string_param( $request, 'request_type' ),
			'status'          => $this->get_string_param( $request, 'status' ),
			'user_id'         => $this->get_int_param( $request, 'user_id' ),
			'permission_type' => $this->get_string_param( $request, 'permission_type' ),
			'date_from'       => $this->get_string_param( $request, 'date_from' ),
			'date_to'         => $this->get_string_param( $request, 'date_to' ),
			'search'          => $this->get_string_param( $request, 'search' ),
		);
		$filters = array_filter( $filters, fn( $v ) => '' !== $v && 0 !== $v );

		$result = $this->service->list_requests(
			get_current_user_id(),
			$filters,
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 )
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		return $this->success( $result );
	}

	public function create_request( WP_REST_Request $request ) {
		$result = $this->service->submit( get_current_user_id(), $this->get_request_data( $request ) );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $this->service->format_request_for_viewer( $result, get_current_user_id() ), 201 );
	}

	public function get_request( WP_REST_Request $request ) {
		$result = $this->service->get_request( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result );
	}

	public function update_request( WP_REST_Request $request ) {
		$result = $this->service->update_request(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $this->service->format_request_for_viewer( $result, get_current_user_id() ) );
	}

	public function delete_request( WP_REST_Request $request ) {
		$result = $this->service->cancel_request( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( array( 'deleted' => true ) );
	}

	public function review_request( WP_REST_Request $request ) {
		$data   = $this->get_request_data( $request );
		$days   = isset( $data['days'] ) ? (float) $data['days'] : null;
		$result = $this->service->review_request(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'status', (string) ( $data['status'] ?? '' ) ),
			$this->get_string_param( $request, 'notes', (string) ( $data['notes'] ?? '' ) ),
			$this->get_string_param( $request, 'approval_slot', (string) ( $data['approval_slot'] ?? '' ) ),
			null !== $days && $days > 0 ? $days : null
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $this->service->format_request_for_viewer( $result, get_current_user_id() ) );
	}

	public function permission_types() {
		return $this->success( array( 'items' => $this->service->permission_types() ) );
	}

	public function permission_reasons() {
		return $this->success( array( 'items' => $this->service->permission_reasons() ) );
	}

	public function permission_usage( WP_REST_Request $request ) {
		$user_id = $this->get_int_param( $request, 'user_id' );
		$result  = $this->service->permission_monthly_usage( get_current_user_id(), $user_id ?: null );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result );
	}

	public function holiday_work_types() {
		return $this->success( array( 'items' => $this->service->holiday_work_types() ) );
	}

	public function pending_counts() {
		return $this->success( $this->service->pending_counts( get_current_user_id() ) );
	}

	public function bulk_review( WP_REST_Request $request ) {
		$data   = $this->get_request_data( $request );
		$items  = is_array( $data['items'] ?? null ) ? $data['items'] : array();
		$result = $this->service->bulk_review(
			get_current_user_id(),
			$items,
			$this->get_string_param( $request, 'status', (string) ( $data['status'] ?? '' ) ),
			$this->get_string_param( $request, 'notes', (string) ( $data['notes'] ?? '' ) ),
			$this->get_string_param( $request, 'approval_slot', (string) ( $data['approval_slot'] ?? '' ) )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function notifications() {
		return $this->success( $this->service->unread_notifications( get_current_user_id() ) );
	}

	public function dismiss_notifications() {
		$this->service->dismiss_notifications( get_current_user_id() );
		return $this->success( array( 'dismissed' => true ) );
	}

	public function export( WP_REST_Request $request ) {
		if ( ! current_user_can( RoleManager::CAP_EXPORT ) ) {
			return $this->error(
				new \WP_Error( 'forbidden', __( 'You cannot export requests.', 'etwa' ), array( 'status' => 403 ) )
			);
		}

		$type = $this->get_string_param( $request, 'request_type' );
		if ( ! in_array( $type, RequestHelper::exposed_types(), true ) ) {
			return $this->error(
				new \WP_Error( 'invalid_type', __( 'Invalid request type for export.', 'etwa' ), array( 'status' => 400 ) )
			);
		}

		$filters = array(
			'request_type' => $type,
			'status'       => $this->get_string_param( $request, 'status' ),
			'user_id'      => $this->get_int_param( $request, 'user_id' ),
			'date_from'    => $this->get_string_param( $request, 'date_from' ),
			'date_to'      => $this->get_string_param( $request, 'date_to' ),
			'search'       => $this->get_string_param( $request, 'search' ),
		);
		$filters = array_filter( $filters, fn( $v ) => '' !== $v && 0 !== $v );

		$file = ( new RequestExportService() )->generate( $filters );
		if ( is_wp_error( $file ) ) {
			return $this->error( $file );
		}

		$upload = wp_upload_dir();
		$url    = str_replace( $upload['basedir'], $upload['baseurl'], $file );

		return $this->success(
			array(
				'filename' => basename( $file ),
				'file_url' => $url,
			)
		);
	}
}
