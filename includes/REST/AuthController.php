<?php
/**
 * Public auth REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Services\AuthService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuthController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/auth/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => array( $this, 'verify_auth_nonce' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/forgot-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'forgot_password' ),
					'permission_callback' => array( $this, 'verify_auth_nonce' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/reset-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_password' ),
					'permission_callback' => array( $this, 'verify_auth_nonce' ),
				),
			)
		);
	}

	public function verify_auth_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-ETWA-Nonce' );
		if ( ! $nonce ) {
			$nonce = (string) $request->get_param( '_wpnonce' );
		}

		return (bool) wp_verify_nonce( $nonce, 'etwa_auth' );
	}

	public function login( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$service = new AuthService();
		$result  = $service->login(
			(string) ( $data['email'] ?? $data['login'] ?? '' ),
			(string) ( $data['password'] ?? '' ),
			! empty( $data['remember'] ),
			isset( $data['redirect_to'] ) ? (string) $data['redirect_to'] : null
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}

	public function forgot_password( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$service = new AuthService();
		$result  = $service->request_password_reset( (string) ( $data['email'] ?? '' ) );

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'sent' => true ) );
	}

	public function reset_password( WP_REST_Request $request ) {
		$data    = $this->get_request_data( $request );
		$service = new AuthService();
		$result  = $service->reset_password(
			(string) ( $data['key'] ?? '' ),
			(string) ( $data['login'] ?? '' ),
			(string) ( $data['password'] ?? '' ),
			(string) ( $data['password_confirm'] ?? '' )
		);

		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( $result );
	}
}
