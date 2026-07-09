<?php
/**
 * Vacation REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\VacationHelper;
use ETWA\Services\VacationAnalyticsService;
use ETWA\Services\VacationExportService;
use ETWA\Services\VacationService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationController extends RestController {

	private VacationService $service;

	public function __construct() {
		$this->service = new VacationService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/vacations/leave-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'leave_types' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/employees',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'employees' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/profile/(?P<user_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'profile' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/my',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'my_profile' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'history' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/allocations',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_allocation' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/allocations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_allocation' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_allocation' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_allocation' ),
					'permission_callback' => array( $this, 'can_delete' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/records',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_record' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/records/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_record' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_record' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_record' ),
					'permission_callback' => array( $this, 'can_delete' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/calendar',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'calendar' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'analytics' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/notifications',
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
			'/vacations/days-between',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'days_between' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_requests' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_request' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/requests/(?P<id>\d+)/review',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'review_request' ),
					'permission_callback' => array( $this, 'can_review' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_request' ),
					'permission_callback' => array( $this, 'can_access_vacation' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_request' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/vacations/requests/bulk-review',
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
			'/vacations/requests/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_requests' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && $this->service->can_manage();
	}

	public function can_review(): bool {
		return is_user_logged_in() && $this->service->can_review();
	}

	public function can_view_own(): bool {
		return is_user_logged_in() && $this->service->can_view_own();
	}

	public function can_access_vacation(): bool {
		return is_user_logged_in() && ( $this->service->can_manage() || $this->service->can_view_own() || $this->service->is_department_head() );
	}

	public function can_delete(): bool {
		return is_user_logged_in() && $this->service->can_delete( get_current_user_id() );
	}

	public function can_export(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT ) && $this->service->can_manage();
	}

	public function leave_types() {
		$result = $this->service->list_leave_types();
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( array_map( fn( $t ) => $t->to_array(), $result ) );
	}

	public function employees( WP_REST_Request $request ) {
		$data = VacationHelper::list_employees(
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 100 ),
			$this->get_string_param( $request, 'search' )
		);
		return $this->success( $data );
	}

	public function profile( WP_REST_Request $request ) {
		$result = $this->service->employee_profile(
			get_current_user_id(),
			$this->get_int_param( $request, 'user_id' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function my_profile() {
		$user_id = get_current_user_id();
		$result  = $this->service->employee_profile( $user_id, $user_id );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function history( WP_REST_Request $request ) {
		$filters = $this->parse_filters( $request );
		if ( ! $this->service->can_manage() ) {
			$filters['user_id'] = get_current_user_id();
		}
		$result = $this->service->list_history(
			get_current_user_id(),
			$filters,
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_allocation( WP_REST_Request $request ) {
		$result = $this->service->create_allocation( get_current_user_id(), $this->get_request_data( $request ) );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function get_allocation( WP_REST_Request $request ) {
		$result = $this->service->get_allocation( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_allocation( WP_REST_Request $request ) {
		$result = $this->service->update_allocation(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function delete_allocation( WP_REST_Request $request ) {
		$result = $this->service->delete_allocation( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function create_record( WP_REST_Request $request ) {
		$result = $this->service->create_record( get_current_user_id(), $this->get_request_data( $request ) );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function get_record( WP_REST_Request $request ) {
		$result = $this->service->get_record( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_record( WP_REST_Request $request ) {
		$result = $this->service->update_record(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function delete_record( WP_REST_Request $request ) {
		$result = $this->service->delete_record( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function calendar( WP_REST_Request $request ) {
		$result = $this->service->calendar(
			get_current_user_id(),
			$this->get_int_param( $request, 'year', (int) gmdate( 'Y' ) ),
			$this->get_int_param( $request, 'month', (int) gmdate( 'n' ) )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function analytics( WP_REST_Request $request ) {
		$service = new VacationAnalyticsService();
		$result  = $service->dashboard( get_current_user_id(), $request->get_params() );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function export( WP_REST_Request $request ) {
		$filters = $this->parse_filters( $request );
		$service = new VacationExportService();
		$path    = $service->generate( $filters );
		if ( is_wp_error( $path ) ) {
			return $this->error( $path );
		}

		$upload = wp_upload_dir();
		$url    = str_replace( $upload['basedir'], $upload['baseurl'], $path );

		return $this->success(
			array(
				'file' => basename( $path ),
				'url'  => $url,
			)
		);
	}

	public function export_requests( WP_REST_Request $request ) {
		$filters = array();
		foreach ( array( 'user_id', 'status', 'date_from', 'date_to', 'search' ) as $key ) {
			$val = $request->get_param( $key );
			if ( null !== $val && '' !== $val ) {
				$filters[ $key ] = is_numeric( $val ) ? (int) $val : sanitize_text_field( (string) $val );
			}
		}

		$path = ( new VacationExportService() )->generate_requests( $filters );
		if ( is_wp_error( $path ) ) {
			return $this->error( $path );
		}

		$contents = file_get_contents( $path );
		@unlink( $path );

		if ( false === $contents ) {
			return $this->error(
				new \WP_Error( 'export_failed', __( 'Could not read export file.', 'etwa' ), array( 'status' => 500 ) )
			);
		}

		return $this->success(
			array(
				'filename' => 'etwa-vacation-requests-' . gmdate( 'Y-m-d' ) . '.xlsx',
				'content'  => base64_encode( $contents ),
				'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			)
		);
	}

	public function notifications() {
		$items = $this->service->unread_notifications( get_current_user_id() );
		return $this->success( array( 'items' => $items ) );
	}

	public function dismiss_notifications() {
		$this->service->dismiss_notifications( get_current_user_id() );
		return $this->success( array( 'dismissed' => true ) );
	}

	public function days_between( WP_REST_Request $request ) {
		$start = $this->get_string_param( $request, 'start_date' );
		$end   = $this->get_string_param( $request, 'end_date' );
		return $this->success(
			array(
				'days' => VacationHelper::days_between( $start, $end ),
			)
		);
	}

	public function list_requests( WP_REST_Request $request ) {
		$filters = array();
		$status  = $this->get_string_param( $request, 'status' );
		if ( $status ) {
			$filters['status'] = $status;
		}
		if ( $this->service->can_manage() ) {
			$user_id = $this->get_int_param( $request, 'user_id' );
			if ( $user_id ) {
				$filters['user_id'] = $user_id;
			}
		}
		$date_from = $this->get_string_param( $request, 'date_from' );
		$date_to   = $this->get_string_param( $request, 'date_to' );
		$search    = $this->get_string_param( $request, 'search' );
		if ( $date_from ) {
			$filters['date_from'] = $date_from;
		}
		if ( $date_to ) {
			$filters['date_to'] = $date_to;
		}
		if ( $search ) {
			$filters['search'] = $search;
		}
		$result = $this->service->list_requests(
			get_current_user_id(),
			$filters,
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function get_request( WP_REST_Request $request ) {
		$result = $this->service->get_request( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function submit_request( WP_REST_Request $request ) {
		$result = $this->service->submit_request( get_current_user_id(), $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function review_request( WP_REST_Request $request ) {
		$result = $this->service->review_request(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'status' ),
			$this->get_string_param( $request, 'notes' ),
			$this->get_string_param( $request, 'approval_slot' )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $this->service->format_request_for_viewer( $result, get_current_user_id() ) );
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

	public function cancel_request( WP_REST_Request $request ) {
		$result = $this->service->cancel_request( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'cancelled' => true ) );
	}

	/** @return array<string,mixed> */
	private function parse_filters( WP_REST_Request $request ): array {
		$filters = array();
		$map     = array( 'user_id', 'leave_type_id', 'status', 'month', 'year', 'date_from', 'date_to', 'keyword', 'transaction_type' );
		foreach ( $map as $key ) {
			$val = $request->get_param( $key );
			if ( null !== $val && '' !== $val ) {
				$filters[ $key ] = is_numeric( $val ) ? (int) $val : sanitize_text_field( (string) $val );
			}
		}
		return $filters;
	}
}
