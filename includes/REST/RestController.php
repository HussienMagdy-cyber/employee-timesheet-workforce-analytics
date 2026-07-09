<?php
/**
 * Base REST controller utilities.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class RestController {

	protected const NAMESPACE = 'etwa/v1';

	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => true, 'data' => $data ), $status );
	}

	protected function error( WP_Error $error ): WP_REST_Response {
		$status = (int) ( $error->get_error_data()['status'] ?? 400 );
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
			$status
		);
	}

	protected function get_int_param( WP_REST_Request $request, string $key, int $default = 0 ): int {
		return (int) $request->get_param( $key ) ?: $default;
	}

	protected function get_string_param( WP_REST_Request $request, string $key, string $default = '' ): string {
		$value = $request->get_param( $key );
		return null !== $value ? sanitize_text_field( (string) $value ) : $default;
	}

	/** @return array<string,mixed> */
	protected function get_request_data( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		if ( ! is_array( $json ) ) {
			$json = array();
		}

		return array_merge( $request->get_params(), $json );
	}
}
