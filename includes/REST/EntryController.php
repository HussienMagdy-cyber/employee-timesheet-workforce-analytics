<?php
/**
 * Entry REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\TimeCalculator;
use ETWA\Services\TimesheetService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EntryController extends RestController {

	private TimesheetService $service;

	public function __construct() {
		$this->service = new TimesheetService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/entries',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/entries/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/time-slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'time_slots' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	public function can_edit(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EDIT_OWN );
	}

	public function save( WP_REST_Request $request ) {
		$data = array(
			'timesheet_id' => $this->get_int_param( $request, 'timesheet_id' ),
			'entry_date'   => $this->get_string_param( $request, 'entry_date' ),
			'start_time'   => $this->get_string_param( $request, 'start_time' ),
			'end_time'     => $this->get_string_param( $request, 'end_time' ),
			'client_id'    => $this->get_int_param( $request, 'client_id' ),
			'project_id'   => $request->get_param( 'project_id' ),
			'task'         => $this->get_string_param( $request, 'task' ),
			'notes'        => $this->get_string_param( $request, 'notes' ),
			'entry_type'   => $this->get_string_param( $request, 'entry_type' ),
		);

		$result = $this->service->save_entry( get_current_user_id(), $data );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_api_array() );
	}

	public function update( WP_REST_Request $request ) {
		$id   = $this->get_int_param( $request, 'id' );
		$data = array(
			'timesheet_id' => $this->get_int_param( $request, 'timesheet_id' ),
			'entry_date'   => $this->get_string_param( $request, 'entry_date' ),
			'start_time'   => $this->get_string_param( $request, 'start_time' ),
			'end_time'     => $this->get_string_param( $request, 'end_time' ),
			'client_id'    => $this->get_int_param( $request, 'client_id' ),
			'project_id'   => $request->get_param( 'project_id' ),
			'task'         => $this->get_string_param( $request, 'task' ),
			'notes'        => $this->get_string_param( $request, 'notes' ),
			'entry_type'   => $this->get_string_param( $request, 'entry_type' ),
		);

		$result = $this->service->save_entry( get_current_user_id(), $data, $id );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_api_array() );
	}

	public function delete( WP_REST_Request $request ) {
		$result = $this->service->delete_entry( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'deleted' => true ) );
	}

	public function time_slots() {
		$calc     = new TimeCalculator();
		$defaults = $calc->get_default_entry_times();
		return $this->success(
			array(
				'slots'          => $calc->get_time_slots(),
				'slot_options'   => $calc->get_time_slot_options(),
				'default_start'  => $defaults['start'],
				'default_end'    => $defaults['end'],
			)
		);
	}
}
