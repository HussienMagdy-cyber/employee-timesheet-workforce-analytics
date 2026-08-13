<?php
/**
 * Project REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\ClientAnalyticsService;
use ETWA\Services\ExportService;
use ETWA\Services\ProjectService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProjectController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/clients/(?P<client_id>\d+)/projects',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_projects' ),
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_project' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project' ),
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_project' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<id>\d+)/archive',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'archive_project' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/projects/(?P<id>\d+)/export/(?P<format>excel|csv)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_project' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);
	}

	public function can_export(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT );
	}

	public function can_manage(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_MANAGE_SETTINGS );
	}

	public function list_projects( WP_REST_Request $request ) {
		$service = new ProjectService();
		$result  = $service->list_for_client(
			get_current_user_id(),
			$this->get_int_param( $request, 'client_id' ),
			! $request->get_param( 'include_archived' )
		);
		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}
		return $this->success( array_map( fn( $p ) => $p->to_array(), $result ) );
	}

	public function create_project( WP_REST_Request $request ) {
		$service = new ProjectService();
		$result  = $service->create(
			get_current_user_id(),
			$this->get_int_param( $request, 'client_id' ),
			array(
				'name'        => $this->get_string_param( $request, 'name' ),
				'description' => $this->get_string_param( $request, 'description' ),
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function get_project( WP_REST_Request $request ) {
		$analytics = new ClientAnalyticsService();
		$detail    = $analytics->get_project_detail(
			$this->get_int_param( $request, 'id' ),
			$this->get_string_param( $request, 'date_from', gmdate( 'Y-m-01' ) ),
			$this->get_string_param( $request, 'date_to', gmdate( 'Y-m-d' ) )
		);
		return is_wp_error( $detail ) ? $this->error( $detail ) : $this->success( $detail );
	}

	public function update_project( WP_REST_Request $request ) {
		$service = new ProjectService();
		$result  = $service->update(
			get_current_user_id(),
			$this->get_int_param( $request, 'id' ),
			array(
				'name'        => $request->get_param( 'name' ),
				'description' => $request->get_param( 'description' ),
			)
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result->to_array() );
	}

	public function archive_project( WP_REST_Request $request ) {
		$service = new ProjectService();
		$result  = $service->archive( get_current_user_id(), $this->get_int_param( $request, 'id' ) );
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'archived' => true ) );
	}

	public function export_project( WP_REST_Request $request ) {
		$project_id = $this->get_int_param( $request, 'id' );
		$format     = sanitize_key( (string) $request->get_param( 'format' ) );
		$analytics  = new ClientAnalyticsService();
		$range      = $analytics->resolve_range(
			$this->get_string_param( $request, 'preset' ),
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);

		$export = new ExportService();
		$file   = 'csv' === $format
			? $export->generate_project_csv( $project_id, $range['date_from'], $range['date_to'] )
			: $export->generate_project_excel( $project_id, $range['date_from'], $range['date_to'] );

		if ( is_wp_error( $file ) ) {
			return $this->error( $file );
		}

		$upload = wp_upload_dir();
		$url    = str_replace( $upload['basedir'], $upload['baseurl'], $file );

		return $this->success(
			array(
				'file_path' => $file,
				'file_url'  => $url,
				'filename'  => basename( $file ),
			)
		);
	}
}
