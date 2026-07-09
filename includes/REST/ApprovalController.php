<?php
/**
 * Approval REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Services\OvertimeService;
use ETWA\Services\PeriodLockService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApprovalController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/approvals/timesheets/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'review_timesheet' ),
					'permission_callback' => array( $this, 'can_approve_ts' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/approvals/overtime/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'review_overtime' ),
					'permission_callback' => array( $this, 'can_approve_ot' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/periods/lock',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'lock_period' ),
					'permission_callback' => array( $this, 'can_lock' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/overtime',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_overtime' ),
					'permission_callback' => array( $this, 'can_approve_ot' ),
				),
			)
		);
	}

	public function can_approve_ts(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_APPROVE_TS );
	}

	public function can_approve_ot(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_APPROVE_OT );
	}

	public function can_lock(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_LOCK_PERIODS );
	}

	public function review_timesheet( WP_REST_Request $request ) {
		$service = new OvertimeService();
		$result  = $service->review_timesheet(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'status', 'approved' ),
			$this->get_string_param( $request, 'notes' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function review_overtime( WP_REST_Request $request ) {
		$service = new OvertimeService();
		$result  = $service->review(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'status', 'approved' ),
			$this->get_string_param( $request, 'notes' ),
			$request->get_param( 'approved_hours' ) ? (float) $request->get_param( 'approved_hours' ) : null
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function lock_period( WP_REST_Request $request ) {
		$service = new PeriodLockService();
		$result  = $service->lock_period(
			get_current_user_id(),
			$this->get_string_param( $request, 'period_start' ),
			$this->get_string_param( $request, 'period_end' )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'locked' => true ) );
	}

	public function list_overtime( WP_REST_Request $request ) {
		$repo  = new \ETWA\Repositories\OvertimeRepository();
		$per   = min( 100, max( 1, $this->get_int_param( $request, 'per_page', 20 ) ) );
		$items = $repo->find_by_filters(
			array(
				'status'  => $this->get_string_param( $request, 'status' ),
				'user_id' => $this->get_int_param( $request, 'user_id' ) ?: null,
			),
			max( 1, $this->get_int_param( $request, 'page', 1 ) ),
			$per
		);

		$names    = UserDisplayNames::map( array_map( fn( $o ) => $o->user_id, $items ) );
		$enriched = array_map(
			fn( $o ) => array_merge(
				$o->to_array(),
				array( 'employee_name' => UserDisplayNames::get( $names, $o->user_id ) )
			),
			$items
		);

		return $this->success( array( 'items' => $enriched ) );
	}
}
