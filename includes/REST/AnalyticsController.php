<?php
/**
 * Analytics REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\AnalyticsService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AnalyticsController extends RestController {

	private AnalyticsService $service;

	public function __construct() {
		$this->service = new AnalyticsService();
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analytics/dashboard',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => array( $this, 'can_view_analytics' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/analytics/advanced',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'advanced' ),
					'permission_callback' => array( $this, 'can_view_advanced' ),
				),
			)
		);
	}

	public function can_view_analytics(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_ANALYTICS );
	}

	public function can_view_advanced(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_ADV_ANALYTICS );
	}

	public function dashboard( WP_REST_Request $request ) {
		$from = $this->get_string_param( $request, 'date_from', gmdate( 'Y-m-01' ) );
		$to   = $this->get_string_param( $request, 'date_to', gmdate( 'Y-m-d' ) );

		return $this->success( $this->service->get_dashboard_bundle( $from, $to ) );
	}

	public function advanced( WP_REST_Request $request ) {
		$from = $this->get_string_param( $request, 'date_from', gmdate( 'Y-m-01' ) );
		$to   = $this->get_string_param( $request, 'date_to', gmdate( 'Y-m-d' ) );

		$filters = array_filter(
			array(
				'user_id'    => $this->get_int_param( $request, 'user_id' ),
				'client_id'  => $this->get_int_param( $request, 'client_id' ),
				'department' => $this->get_string_param( $request, 'department' ),
			),
			fn( $v ) => '' !== $v && 0 !== $v
		);

		$data = $this->service->get_advanced_dashboard( $from, $to );
		if ( ! empty( $filters ) ) {
			$data['extended_metrics'] = $this->service->get_extended_metrics( $from, $to, $filters );
		}

		return $this->success( $data );
	}
}
