<?php
/**
 * Deliverables REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\ClientScopeService;
use ETWA\Services\DeliverableAllocationService;
use ETWA\Services\DeliverableDashboardService;
use ETWA\Services\DeliverableFilesService;
use ETWA\Services\DeliverableMetricsService;
use ETWA\Services\DeliverableTypeService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverablesController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/deliverable-types',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_types' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_type' ),
					'permission_callback' => array( $this, 'can_manage_types' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverable-types/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_type' ),
					'permission_callback' => array( $this, 'can_manage_types' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverable-types/(?P<id>\d+)/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_type' ),
					'permission_callback' => array( $this, 'can_manage_types' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/dashboard/analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dashboard_analytics' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/files',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_files' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/scopes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_scopes' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_scope' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/scopes/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scope' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_scope' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/scopes/(?P<id>\d+)/metrics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'scope_metrics' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		// Deprecated: legacy type-based contract lines. Prefer contract_deliverables on scope create/update.
		register_rest_route(
			self::NAMESPACE,
			'/deliverables/scopes/(?P<id>\d+)/contract-lines',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_contract_lines' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/scopes/(?P<scope_id>\d+)/patches',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_patch' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/patches/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_patch' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_patch' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/allocations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_allocations' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_allocation' ),
					'permission_callback' => array( $this, 'can_allocate' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/deliverables/allocations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_allocation' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_allocation' ),
					'permission_callback' => array( $this, 'can_allocate' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_allocation' ),
					'permission_callback' => array( $this, 'can_allocate' ),
				),
			)
		);

	}

	public function can_view(): bool {
		return is_user_logged_in() && RoleManager::user_can_view_deliverables();
	}

	public function can_manage(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES );
	}

	public function can_manage_types(): bool {
		return is_user_logged_in() && RoleManager::user_can_manage_deliverable_types();
	}

	public function can_allocate(): bool {
		return is_user_logged_in() && RoleManager::user_can_allocate_deliverables();
	}

	public function list_types( WP_REST_Request $request ) {
		$active = $request->get_param( 'active' );
		$active_only = null;
		if ( null !== $active && '' !== $active ) {
			$active_only = (bool) (int) $active;
		}
		$result = ( new DeliverableTypeService() )->list( get_current_user_id(), $active_only );
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( array_map( static fn( $t ) => $t->to_array(), $result ) );
	}

	public function create_type( WP_REST_Request $request ) {
		$result = ( new DeliverableTypeService() )->create( get_current_user_id(), $this->get_request_data( $request ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array(), 201 );
	}

	public function update_type( WP_REST_Request $request ) {
		$result = ( new DeliverableTypeService() )->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function deactivate_type( WP_REST_Request $request ) {
		$result = ( new DeliverableTypeService() )->deactivate( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function dashboard( WP_REST_Request $request ) {
		$filters = array();
		$client  = $this->get_int_param( $request, 'client_id' );
		$status  = $this->get_string_param( $request, 'status' );
		if ( $client > 0 ) {
			$filters['client_id'] = $client;
		}
		if ( '' !== $status ) {
			$filters['status'] = $status;
		}
		return $this->success( ( new DeliverableMetricsService() )->dashboard( $filters ) );
	}

	public function dashboard_analytics( WP_REST_Request $request ) {
		$filters = array();
		$client  = $this->get_int_param( $request, 'client_id' );
		$scope   = $this->get_int_param( $request, 'scope_id' );
		$type    = $this->get_int_param( $request, 'deliverable_type_id' );
		$status  = $this->get_string_param( $request, 'status' );
		$from    = $this->get_string_param( $request, 'date_from' );
		$to      = $this->get_string_param( $request, 'date_to' );
		$trend   = $this->get_string_param( $request, 'trend' );
		$over    = $this->get_string_param( $request, 'over_time' );

		if ( $client > 0 ) {
			$filters['client_id'] = $client;
		}
		if ( $scope > 0 ) {
			$filters['scope_id'] = $scope;
		}
		if ( $type > 0 ) {
			$filters['deliverable_type_id'] = $type;
		}
		if ( '' !== $status ) {
			$filters['status'] = $status;
		}
		if ( '' !== $from ) {
			$filters['date_from'] = $from;
		}
		if ( '' !== $to ) {
			$filters['date_to'] = $to;
		}
		if ( '' !== $trend ) {
			$filters['trend'] = $trend;
		}
		if ( '' !== $over ) {
			$filters['over_time'] = $over;
		}

		return $this->success( ( new DeliverableDashboardService() )->analytics( $filters ) );
	}

	public function list_files( WP_REST_Request $request ) {
		$filters = array();
		$client  = $this->get_int_param( $request, 'client_id' );
		$search  = $this->get_string_param( $request, 'search' );
		if ( $client > 0 ) {
			$filters['client_id'] = $client;
		}
		if ( '' !== $search ) {
			$filters['search'] = $search;
		}
		$result = ( new DeliverableFilesService() )->list_files( get_current_user_id(), $filters );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function list_scopes( WP_REST_Request $request ) {
		$filters = array();
		$client  = $this->get_int_param( $request, 'client_id' );
		$status  = $this->get_string_param( $request, 'status' );
		$search  = $this->get_string_param( $request, 'search' );
		if ( $client > 0 ) {
			$filters['client_id'] = $client;
		}
		if ( '' !== $status ) {
			$filters['status'] = $status;
		}
		if ( '' !== $search ) {
			$filters['search'] = $search;
		}
		$result = ( new ClientScopeService() )->list_with_metrics( get_current_user_id(), $filters );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'items' => $result ) );
	}

	public function create_scope( WP_REST_Request $request ) {
		$result = ( new ClientScopeService() )->create( get_current_user_id(), $this->get_request_data( $request ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result, 201 );
	}

	public function get_scope( WP_REST_Request $request ) {
		$result = ( new ClientScopeService() )->details( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_scope( WP_REST_Request $request ) {
		$result = ( new ClientScopeService() )->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function scope_metrics( WP_REST_Request $request ) {
		$result = ( new DeliverableMetricsService() )->scope_metrics( $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function set_contract_lines( WP_REST_Request $request ) {
		$data  = $this->get_request_data( $request );
		$lines = $data['contract_lines'] ?? $data['lines'] ?? $data;
		if ( ! is_array( $lines ) ) {
			$lines = array();
		}
		$result = ( new ClientScopeService() )->set_contract_lines(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$lines
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function create_patch( WP_REST_Request $request ) {
		return $this->error(
			new \WP_Error(
				'gone',
				__( 'Batches have been retired from deliverables planning.', 'etwa' ),
				array( 'status' => 410 )
			)
		);
	}

	public function get_patch( WP_REST_Request $request ) {
		return $this->create_patch( $request );
	}

	public function update_patch( WP_REST_Request $request ) {
		return $this->create_patch( $request );
	}

	public function list_allocations( WP_REST_Request $request ) {
		$filters = array(
			'scope_id' => $this->get_int_param( $request, 'scope_id' ),
		);
		$patch = $request->get_param( 'patch_id' );
		if ( null !== $patch && '' !== $patch ) {
			$filters['patch_id'] = (int) $patch;
		}
		$impact = $this->get_string_param( $request, 'scope_impact' );
		if ( '' !== $impact ) {
			$filters['scope_impact'] = $impact;
		}
		$type = $this->get_int_param( $request, 'deliverable_type_id' );
		if ( $type > 0 ) {
			$filters['deliverable_type_id'] = $type;
		}
		$result = ( new DeliverableAllocationService() )->list( get_current_user_id(), $filters );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'items' => $result ) );
	}

	public function create_allocation( WP_REST_Request $request ) {
		$result = ( new DeliverableAllocationService() )->create( get_current_user_id(), $this->get_request_data( $request ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result, 201 );
	}

	public function get_allocation( WP_REST_Request $request ) {
		$result = ( new DeliverableAllocationService() )->get( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function update_allocation( WP_REST_Request $request ) {
		$result = ( new DeliverableAllocationService() )->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			$this->get_request_data( $request )
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function delete_allocation( WP_REST_Request $request ) {
		$result = ( new DeliverableAllocationService() )->delete( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}
}
