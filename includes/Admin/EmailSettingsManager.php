<?php
/**
 * Email Settings admin registration, AJAX handlers, and Settings API.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\EmailSettingsRepository;
use ETWA\Services\MailService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailSettingsManager {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_etwa_email_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_etwa_send_test_email', array( $this, 'ajax_send_test' ) );
	}

	public function register_settings(): void {
		register_setting(
			EmailSettingsRepository::SETTINGS_GROUP,
			EmailSettingsRepository::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( EmailSettingsRepository::class, 'sanitize' ),
				'default'           => EmailSettingsRepository::defaults(),
			)
		);
	}

	public function ajax_preview(): void {
		check_ajax_referer( 'etwa_ajax', 'nonce' );

		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'etwa' ) ), 403 );
		}

		$settings = $this->parse_settings_from_request();
		$mail     = ( new MailService() )->with_settings( $settings );
		$html     = $mail->render_html( $mail->sample_placeholders() );

		wp_send_json_success( array( 'html' => $html ) );
	}

	public function ajax_send_test(): void {
		check_ajax_referer( 'etwa_ajax', 'nonce' );

		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'etwa' ) ), 403 );
		}

		$recipient = sanitize_email( wp_unslash( (string) ( $_POST['recipient_email'] ?? '' ) ) );
		if ( ! is_email( $recipient ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid recipient email address.', 'etwa' ) ), 400 );
		}

		$settings = $this->parse_settings_from_request();
		$mail     = ( new MailService() )->with_settings( $settings );
		$result   = $mail->send(
			array(
				'to'             => $recipient,
				'subject'        => __( 'ETWA Test Email', 'etwa' ),
				'content'        => __( 'This is a test email from Employee Timesheet & Workforce Analytics. If you received this message, your email configuration is working correctly.', 'etwa' ),
				'sender_user_id' => get_current_user_id(),
				'placeholders'   => array(
					'employee_name'  => __( 'Test Recipient', 'etwa' ),
					'employee_email' => $recipient,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		wp_send_json_success( array( 'message' => __( 'Test email sent successfully.', 'etwa' ) ) );
	}

	/** @return array<string,mixed> */
	private function parse_settings_from_request(): array {
		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return EmailSettingsRepository::sanitize( $raw );
	}
}
