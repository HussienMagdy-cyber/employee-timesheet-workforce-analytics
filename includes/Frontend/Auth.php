<?php
/**
 * Auth shortcodes and frontend hooks.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Frontend;

use ETWA\Assets\AssetRegistry;
use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\BrandHelper;
use ETWA\Services\AuthService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Auth {

	public function __construct() {
		add_shortcode( 'etwa_login', array( $this, 'render_login' ) );
		add_shortcode( 'etwa_set_password', array( $this, 'render_set_password' ) );

		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
		add_action( 'admin_init', array( $this, 'block_wp_admin' ) );
		add_action( 'template_redirect', array( $this, 'redirect_guest_from_protected_pages' ), 5 );
		add_action( 'template_redirect', array( $this, 'redirect_logged_in_from_auth_pages' ) );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'retrieve_password_message', array( $this, 'filter_password_reset_message' ), 10, 4 );
		add_filter( 'retrieve_password_title', array( $this, 'filter_password_reset_subject' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'language_attributes', array( $this, 'html_class' ) );
		add_filter( 'template_include', array( $this, 'auth_page_template' ) );
		add_filter( 'rest_authentication_errors', array( $this, 'bypass_rest_cookie_check_on_auth_routes' ), 101 );
		add_action( 'login_init', array( $this, 'redirect_wp_login' ) );
	}

	public function render_login(): string {
		AssetRegistry::mark( 'auth' );

		$notice = $this->auth_notice();
		ob_start();
		$this->include_template(
			'auth-login.php',
			array(
				'notice' => $notice,
			)
		);

		return $this->wrap( (string) ob_get_clean(), __( 'Log in', 'etwa' ) );
	}

	public function render_set_password(): string {
		AssetRegistry::mark( 'auth' );

		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$reset_user  = null;
		$reset_error = null;

		if ( $key && $login ) {
			$validated = ( new AuthService() )->validate_reset_key( $key, $login );
			if ( is_wp_error( $validated ) ) {
				$reset_error = $validated->get_error_message();
			} else {
				$reset_user = $validated;
			}
		}

		$notice = $this->auth_notice();

		ob_start();
		$this->include_template(
			'auth-set-password.php',
			array(
				'key'         => $key,
				'login'       => $login,
				'reset_user'  => $reset_user,
				'reset_error' => $reset_error,
				'notice'      => $notice,
			)
		);

		return $this->wrap( (string) ob_get_clean(), $reset_user ? __( 'Set your password', 'etwa' ) : __( 'Reset password', 'etwa' ) );
	}

	public function redirect_wp_login(): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'rp' === $action || 'resetpass' === $action ) {
			$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( AuthHelper::set_password_url( $key ?: null, $login ?: null ) );
			exit;
		}

		if ( 'lostpassword' === $action ) {
			wp_safe_redirect( AuthHelper::set_password_url() );
			exit;
		}

		if ( in_array( $action, array( 'logout', 'postpass', 'confirm_admin_email' ), true ) ) {
			return;
		}

		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( (string) $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( AuthHelper::login_url( $redirect ?: null ) );
		exit;
	}

	public function hide_admin_bar( bool $show ): bool {
		if ( is_admin() ) {
			return $show;
		}

		return false;
	}

	public function block_wp_admin(): void {
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( AuthHelper::portal_url() );
		exit;
	}

	public function redirect_guest_from_protected_pages(): void {
		if ( is_user_logged_in() || ! AuthHelper::is_protected_page() ) {
			return;
		}

		AuthHelper::redirect_to_login();
	}

	public function redirect_logged_in_from_auth_pages(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( AuthHelper::is_login_page() || ( AuthHelper::is_set_password_page() && empty( $_GET['key'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( AuthHelper::portal_url() );
			exit;
		}
	}

	public function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
		$url = AuthHelper::login_url( $redirect ?: null );
		if ( $force_reauth ) {
			$url = add_query_arg( 'reauth', '1', $url );
		}

		return $url;
	}

	public function filter_lostpassword_url( string $lostpassword_url, string $redirect ): string {
		unset( $lostpassword_url, $redirect );
		return AuthHelper::set_password_url();
	}

	public function filter_password_reset_message( string $message, string $key, string $user_login, $user_data ): string {
		if ( ! $user_data instanceof \WP_User ) {
			return $message;
		}

		return AuthHelper::password_reset_email_message( $message, $key, $user_login, $user_data );
	}

	public function filter_password_reset_subject( string $subject ): string {
		return AuthHelper::password_reset_email_subject( $subject );
	}

	/** @param string[] $classes */
	public function body_class( array $classes ): array {
		if ( AuthHelper::is_login_page() || AuthHelper::is_set_password_page() ) {
			$classes[] = 'etwa-auth-page';
		}

		return $classes;
	}

	public function html_class( string $output ): string {
		if ( AuthHelper::is_login_page() || AuthHelper::is_set_password_page() ) {
			return $output . ' class="etwa-auth-page"';
		}

		return $output;
	}

	public function auth_page_template( string $template ): string {
		if ( AuthHelper::is_login_page() || AuthHelper::is_set_password_page() ) {
			$auth_template = ETWA_PLUGIN_DIR . 'templates/frontend/auth-page.php';
			if ( is_readable( $auth_template ) ) {
				return $auth_template;
			}
		}

		return $template;
	}

	/**
	 * WordPress REST treats X-WP-Nonce as a logged-in cookie check and rejects
	 * non-wp_rest nonces with "Cookie check failed". Auth routes use X-ETWA-Nonce.
	 *
	 * @param \WP_Error|true|null $result
	 * @return \WP_Error|true|null
	 */
	public function bypass_rest_cookie_check_on_auth_routes( $result ) {
		if ( ! $result instanceof \WP_Error || 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
			? (string) $GLOBALS['wp']->query_vars['rest_route']
			: '';

		if ( str_starts_with( $route, '/etwa/v1/auth/' ) ) {
			return null;
		}

		return $result;
	}

	private function auth_notice(): ?array {
		if ( empty( $_GET['etwa_auth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}

		$code = sanitize_key( wp_unslash( (string) $_GET['etwa_auth'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return match ( $code ) {
			'password_set' => array(
				'type'    => 'success',
				'message' => __( 'Your password has been set. You can log in now.', 'etwa' ),
			),
			'logged_out'   => array(
				'type'    => 'info',
				'message' => __( 'You have been logged out.', 'etwa' ),
			),
			default        => null,
		};
	}

	private function wrap( string $content, string $title ): string {
		ob_start();
		$this->include_template(
			'auth-shell.php',
			array(
				'content'    => $content,
				'page_title' => $title,
				'brand_name' => BrandHelper::name(),
				'brand_mark' => strtoupper( mb_substr( BrandHelper::name(), 0, 1 ) ),
			)
		);

		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $vars */
	private function include_template( string $file, array $vars = array() ): void {
		$path = ETWA_PLUGIN_DIR . 'templates/frontend/' . $file;
		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		include $path;
	}
}
