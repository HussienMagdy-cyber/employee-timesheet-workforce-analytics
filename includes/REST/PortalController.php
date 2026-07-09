<?php
/**
 * Portal REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Services\ReviewerCountsService;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PortalController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/portal/nav-badges',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'nav_badges' ),
					'permission_callback' => static fn() => is_user_logged_in(),
				),
			)
		);
	}

	public function nav_badges() {
		return $this->success( ( new ReviewerCountsService() )->nav_badges( get_current_user_id() ) );
	}
}
