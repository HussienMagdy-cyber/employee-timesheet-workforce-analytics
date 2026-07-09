<?php
/**
 * Holiday and calendar REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Services\HolidayService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HolidayController extends RestController {

	private HolidayService $service;

	public function __construct() {
		$this->service = new HolidayService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/holidays',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_holidays' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_holiday' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/holidays/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_holiday' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_holiday' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/calendar/weekdays',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_calendar' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_calendar' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return is_user_logged_in() && $this->service->can_manage();
	}

	public function list_holidays( WP_REST_Request $request ) {
		$result = $this->service->list_holidays(
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 ),
			$this->get_int_param( $request, 'year' ) ?: null
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_holiday( WP_REST_Request $request ) {
		$result = $this->service->create(
			get_current_user_id(),
			$this->get_string_param( $request, 'name' ),
			$this->get_string_param( $request, 'holiday_date' ),
			(bool) $request->get_param( 'is_recurring' )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function update_holiday( WP_REST_Request $request ) {
		$result = $this->service->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'name' ),
			$this->get_string_param( $request, 'holiday_date' ),
			(bool) $request->get_param( 'is_recurring' )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( $result->to_array() );
	}

	public function delete_holiday( WP_REST_Request $request ) {
		$result = $this->service->delete( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function get_calendar() {
		$result = $this->service->get_calendar_config();
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function save_calendar( WP_REST_Request $request ) {
		$weekdays = $request->get_param( 'weekdays' );
		if ( ! is_array( $weekdays ) ) {
			return $this->error( new \WP_Error( 'invalid', __( 'Invalid weekday configuration.', 'etwa' ) ) );
		}
		$result = $this->service->save_calendar_config( get_current_user_id(), $weekdays );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'saved' => true ) );
	}
}
