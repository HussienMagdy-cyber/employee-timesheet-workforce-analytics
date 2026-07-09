<?php
/**
 * Login and password reset handling.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuthService {

	/** @return array{redirect:string}|WP_Error */
	public function login( string $login_or_email, string $password, bool $remember = false, ?string $redirect_to = null ): array|WP_Error {
		$user = $this->resolve_user( $login_or_email );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! EmployeeProfileHelper::is_managed_user( (int) $user->ID ) && ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have access to this portal.', 'etwa' ), array( 'status' => 403 ) );
		}

		$signed_on = wp_signon(
			array(
				'user_login'    => $user->user_login,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $signed_on ) ) {
			return new WP_Error( 'invalid_login', __( 'Incorrect email or password.', 'etwa' ), array( 'status' => 401 ) );
		}

		return array(
			'redirect' => $redirect_to
				? wp_validate_redirect( $redirect_to, AuthHelper::portal_url() )
				: AuthHelper::redirect_after_login(),
		);
	}

	/** @return true|WP_Error */
	public function request_password_reset( string $email ): bool|WP_Error {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'etwa' ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			// Do not reveal whether the account exists.
			return true;
		}

		if ( ! EmployeeProfileHelper::is_managed_user( (int) $user->ID ) && ! user_can( $user, 'manage_options' ) ) {
			return true;
		}

		$sent = retrieve_password( $user->user_login );
		if ( is_wp_error( $sent ) ) {
			return new WP_Error( 'email_failed', __( 'Could not send the password reset email. Please try again later.', 'etwa' ) );
		}

		return true;
	}

	/** @return array{redirect:string}|WP_Error */
	public function reset_password( string $key, string $login, string $password, string $password_confirm ): array|WP_Error {
		$key   = sanitize_text_field( $key );
		$login = sanitize_text_field( $login );

		if ( '' === $key || '' === $login ) {
			return new WP_Error( 'invalid_key', __( 'This password reset link is invalid.', 'etwa' ) );
		}

		if ( $password !== $password_confirm ) {
			return new WP_Error( 'password_mismatch', __( 'Passwords do not match.', 'etwa' ) );
		}

		$validation = $this->validate_password( $password );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'invalid_key', __( 'This password reset link has expired or is invalid.', 'etwa' ) );
		}

		reset_password( $user, $password );

		return array(
			'redirect' => add_query_arg( 'etwa_auth', 'password_set', AuthHelper::login_url() ),
		);
	}

	/** @return \WP_User|WP_Error */
	public function validate_reset_key( string $key, string $login ): \WP_User|WP_Error {
		$key   = sanitize_text_field( $key );
		$login = sanitize_text_field( $login );

		if ( '' === $key || '' === $login ) {
			return new WP_Error( 'invalid_key', __( 'This password reset link is invalid.', 'etwa' ) );
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'invalid_key', __( 'This password reset link has expired or is invalid.', 'etwa' ) );
		}

		return $user;
	}

	/** @return \WP_User|WP_Error */
	private function resolve_user( string $login_or_email ): \WP_User|WP_Error {
		$login_or_email = sanitize_text_field( trim( $login_or_email ) );
		if ( '' === $login_or_email ) {
			return new WP_Error( 'invalid_login', __( 'Email is required.', 'etwa' ) );
		}

		if ( is_email( $login_or_email ) ) {
			$user = get_user_by( 'email', $login_or_email );
		} else {
			$user = get_user_by( 'login', $login_or_email );
		}

		if ( ! $user ) {
			return new WP_Error( 'invalid_login', __( 'Incorrect email or password.', 'etwa' ), array( 'status' => 401 ) );
		}

		return $user;
	}

	/** @return true|WP_Error */
	private function validate_password( string $password ): bool|WP_Error {
		if ( strlen( $password ) < 8 ) {
			return new WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'etwa' ) );
		}

		return true;
	}
}
