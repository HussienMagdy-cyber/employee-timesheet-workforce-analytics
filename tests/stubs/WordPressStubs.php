<?php
/**
 * Minimal WordPress stubs for unit tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var string */
		public $code;

		/** @var string */
		public $message;

		/** @var mixed */
		public $data;

		/**
		 * @param string|int $code
		 * @param string     $message
		 * @param mixed      $data
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		/** @return mixed */
		public function get_error_data( ?string $code = null ) {
			unset( $code );
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
