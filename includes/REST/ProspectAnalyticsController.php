<?php
/**
 * Prospect analytics REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Services\ProspectAnalyticsService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectAnalyticsController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/prospect-analytics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'dashboard' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);
	}

	public function can_view(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_ANALYTICS );
	}

	public function dashboard( WP_REST_Request $request ) {
		$service = new ProspectAnalyticsService();
		$preset  = $this->get_string_param( $request, 'preset' ) ?: DateRangeHelper::PRESET_ALL_TIME;
		$range   = $service->resolve_range(
			$preset,
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);
		$data    = $service->dashboard(
			$range['date_from'],
			$range['date_to'],
			$this->get_string_param( $request, 'status' ),
			$this->get_string_param( $request, 'search' ),
			$this->get_string_param( $request, 'interval' )
		);
		$data['range_meta'] = $range;
		$data['presets']    = DateRangeHelper::prospect_analytics_preset_labels();

		return $this->success( $data );
	}
}
