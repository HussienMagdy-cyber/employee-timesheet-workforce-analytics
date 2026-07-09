<?php
/**
 * Client analytics REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Services\ClientAnalyticsService;
use ETWA\Services\ExportService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientAnalyticsController extends RestController {

	private ClientAnalyticsService $service;

	public function __construct() {
		$this->service = new ClientAnalyticsService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/client-analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_clients' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/client-analytics/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'client_detail' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/client-analytics/(?P<id>\d+)/export/(?P<format>excel|csv)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_client' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);
	}

	public function can_view(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_ANALYTICS );
	}

	public function can_export(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT );
	}

	public function list_clients( WP_REST_Request $request ) {
		$range  = $this->service->resolve_range(
			$this->get_string_param( $request, 'preset' ),
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);
		$result = $this->service->list_clients(
			$range['date_from'],
			$range['date_to'],
			$this->get_int_param( $request, 'page', 1 ),
			$this->get_int_param( $request, 'per_page', 25 ),
			$this->get_string_param( $request, 'search' )
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$result['range_meta'] = $range;
		$result['presets']    = DateRangeHelper::preset_labels();

		return $this->success( $result );
	}

	public function client_detail( WP_REST_Request $request ) {
		$range  = $this->service->resolve_range(
			$this->get_string_param( $request, 'preset' ),
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);
		$result = $this->service->get_client_detail(
			$this->get_int_param( $request, 'id' ),
			$range['date_from'],
			$range['date_to']
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result );
		}

		$result['range_meta'] = $range;
		return $this->success( $result );
	}

	public function export_client( WP_REST_Request $request ) {
		$client_id = $this->get_int_param( $request, 'id' );
		$format    = sanitize_key( (string) $request->get_param( 'format' ) );
		$range     = $this->service->resolve_range(
			$this->get_string_param( $request, 'preset' ),
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);

		$export = new ExportService();
		$file   = 'csv' === $format
			? $export->generate_client_csv( $client_id, $range['date_from'], $range['date_to'] )
			: $export->generate_client_excel( $client_id, $range['date_from'], $range['date_to'] );

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
