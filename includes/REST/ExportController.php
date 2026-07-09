<?php
/**
 * Export REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\RoleManager;
use ETWA\Services\EmailService;
use ETWA\Services\ExportService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExportController extends RestController {

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/export/excel',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_excel' ),
					'permission_callback' => array( $this, 'can_export' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export/email',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'email_report' ),
					'permission_callback' => array( $this, 'can_email' ),
				),
			)
		);
	}

	public function can_export(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT );
	}

	public function can_email(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EMAIL );
	}

	public function export_excel( WP_REST_Request $request ) {
		$service = new ExportService();
		$from    = $this->get_string_param( $request, 'date_from' );
		$to      = $this->get_string_param( $request, 'date_to' );
		$user_id = $this->get_int_param( $request, 'user_id' ) ?: null;

		$file = $service->generate( $from, $to, $user_id );
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

	public function email_report( WP_REST_Request $request ) {
		$service = new EmailService();
		$result  = $service->send_report(
			$this->get_string_param( $request, 'date_from' ),
			$this->get_string_param( $request, 'date_to' ),
			$this->get_int_param( $request, 'user_id' ) ?: null
		);
		return is_wp_error( $result ) ? $this->error( $result ) : $this->success( array( 'sent' => true ) );
	}
}
