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

		register_rest_route(
			self::NAMESPACE,
			'/portal/session',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'session' ),
					'permission_callback' => static fn() => is_user_logged_in(),
				),
			)
		);
	}

	public function nav_badges() {
		return $this->success( ( new ReviewerCountsService() )->nav_badges( get_current_user_id() ) );
	}

	public function session() {
		// #region agent log
		$log_entry = wp_json_encode(
			array(
				'sessionId'    => '154178',
				'timestamp'    => (int) round( microtime( true ) * 1000 ),
				'location'     => 'PortalController.php:session',
				'message'      => 'portal/session controller reached',
				'data'         => array(
					'user_id' => get_current_user_id(),
				),
				'hypothesisId' => 'H3',
				'runId'        => 'pre-fix',
			)
		);
		if ( $log_entry ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( ETWA_PLUGIN_DIR . 'debug-154178.log', $log_entry . "\n", FILE_APPEND | LOCK_EX );
		}
		// #endregion

		return $this->success(
			array(
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce' => wp_create_nonce( 'etwa_ajax' ),
			)
		);
	}
}
