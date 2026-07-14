<?php
/**
 * Auth page URLs and password-reset email helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuthHelper {

	public const OPTION_LOGIN_PAGE          = 'etwa_auth_login_page_id';
	public const OPTION_SET_PASSWORD_PAGE = 'etwa_auth_set_password_page_id';

	/** @var array<string, array{title:string,slug:string,shortcode:string,option:string}> */
	private const PAGES = array(
		'login'         => array(
			'title'     => 'Log In',
			'slug'      => 'login',
			'shortcode' => 'etwa_login',
			'option'    => self::OPTION_LOGIN_PAGE,
		),
		'set-password'  => array(
			'title'     => 'Set Password',
			'slug'      => 'set-password',
			'shortcode' => 'etwa_set_password',
			'option'    => self::OPTION_SET_PASSWORD_PAGE,
		),
	);

	public static function login_url( ?string $redirect = null ): string {
		$url = self::page_url( 'login' );
		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}

		return $url;
	}

	public static function set_password_url( ?string $key = null, ?string $login = null ): string {
		$url = self::page_url( 'set-password' );
		if ( $key && $login ) {
			$url = add_query_arg(
				array(
					'key'   => $key,
					'login' => rawurlencode( $login ),
				),
				$url
			);
		}

		return $url;
	}

	public static function portal_url(): string {
		$page_id = self::find_page_id_by_shortcode( 'etwa_portal' );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	public static function redirect_after_login(): string {
		if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = wp_validate_redirect(
				esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				self::portal_url()
			);
			if ( $redirect ) {
				return $redirect;
			}
		}

		return self::portal_url();
	}

	public static function is_login_page(): bool {
		return self::is_auth_page( 'login' );
	}

	public static function is_set_password_page(): bool {
		return self::is_auth_page( 'set-password' );
	}

	/** @return string[] */
	public static function protected_shortcodes(): array {
		return array(
			'etwa_portal',
			'etwa_timesheet',
			'etwa_hr',
			'etwa_analytics',
			'etwa_clients',
			'etwa_employees',
			'etwa_client_analytics',
			'etwa_holidays',
			'etwa_vacations',
			'etwa_my_vacation',
			'etwa_requests',
			'etwa_requests_hr',
		);
	}

	public static function is_protected_page(): bool {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		if ( self::is_login_page() || self::is_set_password_page() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		foreach ( self::protected_shortcodes() as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	public static function current_request_url(): string {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$url  = home_url( $path );

		return wp_validate_redirect( $url, home_url( '/' ) ) ?: home_url( '/' );
	}

	public static function redirect_to_login( ?string $redirect_to = null ): never {
		$target = self::login_url( $redirect_to ?? self::current_request_url() );
		wp_safe_redirect( $target );
		exit;
	}

	public static function is_auth_page( string $key ): bool {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		$page_id = (int) get_queried_object_id();
		$stored  = (int) get_option( self::PAGES[ $key ]['option'] ?? '', 0 );

		if ( $stored > 0 && $page_id === $stored ) {
			return true;
		}

		$post = get_post( $page_id );
		if ( ! $post ) {
			return false;
		}

		$shortcode = self::PAGES[ $key ]['shortcode'] ?? '';
		return $shortcode && has_shortcode( $post->post_content, $shortcode );
	}

	public static function ensure_pages(): void {
		foreach ( self::PAGES as $config ) {
			self::ensure_page( $config );
		}
	}

	/**
	 * @param array{title:string,slug:string,shortcode:string,option:string} $config
	 */
	private static function ensure_page( array $config ): void {
		$page_id = (int) get_option( $config['option'], 0 );
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( $config['slug'] );
		if ( $existing instanceof \WP_Post && 'publish' === $existing->post_status ) {
			update_option( $config['option'], (int) $existing->ID );
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $config['title'],
				'post_name'    => $config['slug'],
				'post_content' => '[' . $config['shortcode'] . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
			),
			true
		);

		if ( ! is_wp_error( $new_id ) ) {
			update_option( $config['option'], (int) $new_id );
		}
	}

	private static function page_url( string $key ): string {
		$config  = self::PAGES[ $key ] ?? null;
		if ( ! $config ) {
			return home_url( '/' );
		}

		$page_id = (int) get_option( $config['option'], 0 );
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		$found = self::find_page_id_by_shortcode( $config['shortcode'] );
		if ( $found ) {
			$url = get_permalink( $found );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' . $config['slug'] . '/' );
	}

	private static function find_page_id_by_shortcode( string $shortcode ): int {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[' . $shortcode,
				'fields'         => 'ids',
			)
		);

		if ( $pages ) {
			return (int) $pages[0];
		}

		return 0;
	}

	public static function password_reset_email_message( string $message, string $key, string $user_login, \WP_User $user_data ): string {
		$url  = self::set_password_url( $key, $user_login );
		$site = BrandHelper::name();

		$lines = array(
			sprintf(
				/* translators: %s: site name */
				__( 'Someone requested a password reset for your account on %s.', 'etwa' ),
				$site
			),
			'',
			__( 'If this was you, use the link below to choose a new password:', 'etwa' ),
			$url,
			'',
			__( 'If you did not request this, you can ignore this email.', 'etwa' ),
		);

		return implode( "\n", $lines );
	}

	public static function password_reset_email_subject( string $subject ): string {
		return sprintf(
			/* translators: %s: site name */
			__( '[%s] Set your password', 'etwa' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
	}
}
