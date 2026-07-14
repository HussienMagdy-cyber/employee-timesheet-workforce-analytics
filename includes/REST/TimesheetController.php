<?php
/**
 * Timesheet REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\CalendarHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\TimesheetDraftHelper;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Helpers\RoleManager;
use ETWA\Services\TimesheetService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimesheetController extends RestController {

	private TimesheetService $service;

	public function __construct() {
		$this->service = new TimesheetService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/timesheets/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_my_timesheet' ),
					'permission_callback' => array( $this, 'can_view_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/timesheets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_timesheets' ),
					'permission_callback' => array( $this, 'can_list_timesheets' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/timesheets/user/(?P<user_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user_timesheet' ),
					'permission_callback' => array( $this, 'can_list_timesheets' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/timesheets/(?P<id>\d+)/submit',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit' ),
					'permission_callback' => array( $this, 'can_submit' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/timesheets/(?P<id>\d+)/save-draft',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_draft' ),
					'permission_callback' => array( $this, 'can_edit_own' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/timesheets/copy-previous',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'copy_previous' ),
					'permission_callback' => array( $this, 'can_edit_own' ),
				),
			)
		);
	}

	public function can_view_own(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_VIEW_OWN );
	}

	public function can_view_all(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_VIEW_ALL );
	}

	public function can_list_timesheets(): bool {
		return is_user_logged_in() && RoleManager::user_can_list_timesheets( get_current_user_id() );
	}

	public function can_submit(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_SUBMIT );
	}

	public function can_edit_own(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EDIT_OWN );
	}

	public function get_my_timesheet( WP_REST_Request $request ) {
		$date = $this->get_string_param( $request, 'date' );
		return $this->success( $this->build_dashboard_payload( get_current_user_id(), $date ?: null, get_current_user_id() ) );
	}

	public function get_user_timesheet( WP_REST_Request $request ) {
		$user_id   = $this->get_int_param( $request, 'user_id' );
		$date      = $this->get_string_param( $request, 'date' );
		$viewer_id = get_current_user_id();

		if ( ! RoleManager::user_can_view_timesheet( $viewer_id, $user_id ) ) {
			return $this->error(
				new \WP_Error( 'forbidden', __( 'Cannot view this timesheet.', 'etwa' ), array( 'status' => 403 ) )
			);
		}

		$payload = $this->build_dashboard_payload( $user_id, $date ?: null, $viewer_id );
		if ( is_wp_error( $payload ) ) {
			return $this->error( $payload );
		}

		return $this->success( $payload );
	}

	/** @return array<string,mixed>|\WP_Error */
	private function build_dashboard_payload( int $user_id, ?string $date, ?int $viewer_id = null ): array|\WP_Error {
		$viewer_id = $viewer_id ?? $user_id;
		$data      = $this->service->get_dashboard_data( $user_id, $date, $viewer_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$week           = $date ? DateHelper::get_work_week_for_date( $date ) : DateHelper::get_current_work_week();
		$calendar_dates = CalendarHelper::get_calendar_dates( $week['period_start'] );
		$day_meta       = CalendarHelper::build_day_meta( $calendar_dates );
		$timesheet      = $data['timesheet'];
		$entries        = $data['entries'];
		$sync_state     = TimesheetDraftHelper::draft_sync_state( $timesheet, $entries );

		return array(
			'timesheet'      => array_merge(
				$timesheet->to_array(),
				array(
					'draft_sync_state'  => $sync_state,
					'draft_sync_label'  => TimesheetDraftHelper::draft_sync_label( $sync_state ),
					'has_unsaved_changes' => TimesheetDraftHelper::has_unsaved_changes( $timesheet, $entries ),
				)
			),
			'entries'        => array_map( fn( $e ) => $e->to_api_array(), $entries ),
			'week'           => $week,
			'work_dates'     => $calendar_dates,
			'calendar_dates' => $calendar_dates,
			'day_meta'       => $day_meta,
		);
	}

	public function list_timesheets( WP_REST_Request $request ) {
		$repo      = new \ETWA\Repositories\TimesheetRepository();
		$viewer_id = get_current_user_id();
		$filters   = array(
			'status'    => $this->get_string_param( $request, 'status' ),
			'date_from' => $this->get_string_param( $request, 'date_from' ),
			'date_to'   => $this->get_string_param( $request, 'date_to' ),
		);

		$department_id = $this->get_int_param( $request, 'department_id' );
		if ( $department_id ) {
			if ( ! user_can( $viewer_id, RoleManager::CAP_VIEW_ALL ) ) {
				$headed_dept = DepartmentHelper::get_headed_department_id( $viewer_id );
				if ( ! $headed_dept || $department_id !== $headed_dept ) {
					return $this->success(
						array(
							'items' => array(),
							'total' => 0,
							'page'  => max( 1, $this->get_int_param( $request, 'page', 1 ) ),
						)
					);
				}
			}
			$employee_ids = DepartmentHelper::employee_ids_in_department( $department_id );
			if ( $employee_ids ) {
				$filters['search_user_ids'] = $employee_ids;
			} else {
				$filters['search_user_ids'] = array( 0 );
			}
		}

		$scope_ids = $this->scoped_employee_ids( $viewer_id );
		if ( null !== $scope_ids ) {
			if ( ! $scope_ids ) {
				$filters['search_user_ids'] = array( 0 );
			} elseif ( isset( $filters['search_user_ids'] ) ) {
				$filters['search_user_ids'] = array_values( array_intersect( $filters['search_user_ids'], $scope_ids ) );
				if ( ! $filters['search_user_ids'] ) {
					$filters['search_user_ids'] = array( 0 );
				}
			} else {
				$filters['search_user_ids'] = $scope_ids;
			}
		}

		$search = $this->get_string_param( $request, 'search' );
		if ( $search ) {
			$users = get_users( array( 'search' => '*' . esc_attr( $search ) . '*', 'fields' => 'ID' ) );
			$user_ids = array_map( 'intval', $users );
			if ( null !== $scope_ids ) {
				$user_ids = array_values( array_intersect( $user_ids, $scope_ids ) );
			}
			if ( isset( $filters['search_user_ids'] ) ) {
				$filters['search_user_ids'] = array_values( array_intersect( $filters['search_user_ids'], $user_ids ) );
				if ( ! $filters['search_user_ids'] ) {
					$filters['search_user_ids'] = array( 0 );
				}
			} else {
				$filters['search_user_ids'] = $user_ids ?: array( 0 );
			}
		}

		$page  = max( 1, $this->get_int_param( $request, 'page', 1 ) );
		$filters['hide_unshared_drafts'] = true;
		$items = $repo->find_by_filters( array_filter( $filters ), $page, 20 );

		$names    = UserDisplayNames::map( array_map( fn( $ts ) => $ts->user_id, $items ) );
		$enriched = array_map(
			fn( $ts ) => array_merge(
				$ts->to_array(),
				array( 'employee_name' => UserDisplayNames::get( $names, $ts->user_id ) )
			),
			$items
		);

		return $this->success(
			array(
				'items' => $enriched,
				'total' => $repo->count_by_filters( array_filter( $filters ) ),
				'page'  => $page,
			)
		);
	}

	public function submit( WP_REST_Request $request ) {
		$result = $this->service->submit( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function save_draft( WP_REST_Request $request ) {
		$result = $this->service->save_draft( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function copy_previous( WP_REST_Request $request ) {
		$date   = $this->get_string_param( $request, 'date' );
		$result = $this->service->copy_previous_week( get_current_user_id(), $date ?: null );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	/** @return int[]|null Null means no department scope (HR / view all). */
	private function scoped_employee_ids( int $viewer_id ): ?array {
		if ( user_can( $viewer_id, RoleManager::CAP_VIEW_ALL ) ) {
			return null;
		}

		$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
		if ( ! $dept_id ) {
			return array();
		}

		return DepartmentHelper::employee_ids_in_department( $dept_id );
	}
}
