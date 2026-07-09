<?php
/**
 * HR timesheet edit REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\TimeCalculator;
use ETWA\Services\HrTimesheetEditService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HrTimesheetController extends RestController {

	private HrTimesheetEditService $service;

	public function __construct() {
		$this->service = new HrTimesheetEditService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/hr/entries',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_hr_edit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/hr/entries/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_hr_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'can_hr_edit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/hr/timesheets/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_status' ),
					'permission_callback' => array( $this, 'can_hr_edit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/hr/time-slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'time_slots' ),
					'permission_callback' => array( $this, 'can_hr_edit' ),
				),
			)
		);
	}

	public function can_hr_edit(): bool {
		return is_user_logged_in()
			&& current_user_can( RoleManager::CAP_VIEW_ALL )
			&& current_user_can( RoleManager::CAP_APPROVE_TS );
	}

	public function save( WP_REST_Request $request ) {
		$data = $this->entry_payload( $request );
		$result = $this->service->save_entry(
			get_current_user_id(),
			$data,
			null,
			$this->get_string_param( $request, 'edit_reason' )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_api_array() );
	}

	public function update( WP_REST_Request $request ) {
		$id   = $this->get_int_param( $request, 'id' );
		$data = $this->entry_payload( $request );
		$result = $this->service->save_entry(
			get_current_user_id(),
			$data,
			$id,
			$this->get_string_param( $request, 'edit_reason' )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_api_array() );
	}

	public function delete( WP_REST_Request $request ) {
		$body   = $request->get_json_params();
		$reason = is_array( $body ) ? sanitize_textarea_field( (string) ( $body['edit_reason'] ?? '' ) ) : '';

		$result = $this->service->delete_entry(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$reason
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function update_status( WP_REST_Request $request ) {
		$result = $this->service->update_status(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'status' ),
			$this->get_string_param( $request, 'edit_reason' ),
			$this->get_string_param( $request, 'review_notes' ) ?: null
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_api_array() );
	}

	public function time_slots() {
		$calc = new TimeCalculator();
		return $this->success(
			array(
				'slots'        => $calc->get_time_slots(),
				'slot_options' => $calc->get_time_slot_options(),
			)
		);
	}

	/** @return array<string,mixed> */
	private function entry_payload( WP_REST_Request $request ): array {
		$payload = array(
			'timesheet_id' => $this->get_int_param( $request, 'timesheet_id' ),
			'entry_date'   => $this->get_string_param( $request, 'entry_date' ),
			'start_time'   => $this->get_string_param( $request, 'start_time' ),
			'end_time'     => $this->get_string_param( $request, 'end_time' ),
			'client_id'    => $this->get_int_param( $request, 'client_id' ),
			'task'         => $this->get_string_param( $request, 'task' ),
			'notes'        => $this->get_string_param( $request, 'notes' ),
		);

		return $payload;
	}
}
