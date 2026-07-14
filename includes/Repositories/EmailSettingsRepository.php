<?php
/**
 * Email settings storage (WordPress option + Settings API).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

use ETWA\Helpers\BrandHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailSettingsRepository {

	public const OPTION_KEY = 'etwa_email_settings';

	public const SETTINGS_GROUP = 'etwa_email_settings_group';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'from_name'              => 'Think Marketing Workforce',
			'from_email'             => '',
			'reply_to'               => '',
			'logo_id'                => 0,
			'logo_width'             => 180,
			'logo_height'            => 60,
			'greeting'               => 'Hello {employee_name},',
			'email_body'             => '{email_content}',
			'appreciation'           => 'With Appreciation,',
			'closing_text'           => 'We always stand available to provide any clarifications or assistance whenever needed.',
			'signature_dedication'   => 'Dedicated to your success,',
			'signature_rows'         => self::default_signature_rows(),
			'default_sender_name'      => 'Think Marketing Workforce',
			'default_sender_role'    => 'HR Team',
			'default_sender_email'     => '',
			'default_sender_phone'   => '',
			'company_name'           => BrandHelper::NAME,
			'company_tagline'        => '',
			'office_phone'           => '',
			'office_address'         => '',
		);
	}

	/** @return array<string,mixed> */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = array_merge( self::defaults(), $stored );

		$merged = self::normalize_sender_defaults( $merged );
		$merged['signature_rows'] = self::normalize_signature_rows( $merged );

		return $merged;
	}

	/** @return array<int,array<string,mixed>> */
	public static function default_signature_rows(): array {
		return array(
			array(
				'text'       => '',
				'source'     => 'dedication',
				'size'       => 15,
				'weight'     => 'normal',
				'color'      => 'default',
				'spacing'    => 4,
				'link_email' => false,
			),
			array(
				'text'       => '',
				'source'     => 'sender_name',
				'size'       => 16,
				'weight'     => 'bold',
				'color'      => 'dark',
				'spacing'    => 2,
				'link_email' => false,
			),
			array(
				'text'       => '',
				'source'     => 'sender_role',
				'size'       => 14,
				'weight'     => 'normal',
				'color'      => 'muted',
				'spacing'    => 12,
				'link_email' => false,
			),
			array(
				'text'       => 'M: {sender_phone} | E: {sender_email}',
				'source'     => 'custom',
				'size'       => 14,
				'weight'     => 'normal',
				'color'      => 'default',
				'spacing'    => 12,
				'link_email' => true,
			),
			array(
				'text'       => '{company_name} | {company_tagline}',
				'source'     => 'custom',
				'size'       => 14,
				'weight'     => 'bold',
				'color'      => 'dark',
				'spacing'    => 4,
				'link_email' => false,
			),
			array(
				'text'       => 'T: {office_phone}',
				'source'     => 'custom',
				'size'       => 14,
				'weight'     => 'normal',
				'color'      => 'default',
				'spacing'    => 4,
				'link_email' => false,
			),
			array(
				'text'       => 'A: {office_address}',
				'source'     => 'custom',
				'size'       => 14,
				'weight'     => 'normal',
				'color'      => 'default',
				'spacing'    => 0,
				'link_email' => false,
			),
		);
	}

	/** @param array<string,mixed> $settings @return array<int,array<string,mixed>> */
	private static function normalize_signature_rows( array $settings ): array {
		$rows = $settings['signature_rows'] ?? array();
		if ( is_string( $rows ) ) {
			$decoded = json_decode( $rows, true );
			$rows    = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $rows ) || ! $rows ) {
			return self::default_signature_rows();
		}

		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = array(
				'text'       => sanitize_text_field( (string) ( $row['text'] ?? '' ) ),
				'source'     => sanitize_key( (string) ( $row['source'] ?? 'custom' ) ),
				'size'       => max( 11, min( 24, absint( $row['size'] ?? 14 ) ) ),
				'weight'     => in_array( (string) ( $row['weight'] ?? 'normal' ), array( 'normal', 'bold', '600' ), true )
					? (string) $row['weight']
					: 'normal',
				'color'      => in_array( (string) ( $row['color'] ?? 'default' ), array( 'default', 'muted', 'dark' ), true )
					? (string) $row['color']
					: 'default',
				'spacing'    => max( 0, min( 32, absint( $row['spacing'] ?? 4 ) ) ),
				'link_email' => ! empty( $row['link_email'] ),
			);
		}

		return $clean ?: self::default_signature_rows();
	}

	/** @param array<string,mixed> $settings @return array<string,mixed> */
	private static function normalize_sender_defaults( array $settings ): array {
		$defaults  = self::defaults();
		$self_refs = array(
			'default_sender_name'  => '{sender_name}',
			'default_sender_role'  => '{sender_role}',
			'default_sender_email' => '{sender_email}',
			'default_sender_phone' => '{sender_phone}',
		);

		foreach ( $self_refs as $field => $token ) {
			if ( trim( (string) ( $settings[ $field ] ?? '' ) ) === $token ) {
				$settings[ $field ] = (string) ( $defaults[ $field ] ?? '' );
			}
		}

		return $settings;
	}

	/** @return mixed */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/** @param array<string,mixed> $settings */
	public function save( array $settings ): void {
		update_option( self::OPTION_KEY, $settings, false );
	}

	/** @param array<string,mixed> $input @return array<string,mixed> */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$clean    = array();

		$clean['from_name'] = sanitize_text_field( (string) ( $input['from_name'] ?? $defaults['from_name'] ) );

		$from_email = sanitize_email( (string) ( $input['from_email'] ?? '' ) );
		$clean['from_email'] = ( '' !== $from_email && is_email( $from_email ) ) ? $from_email : '';

		$reply_to = sanitize_email( (string) ( $input['reply_to'] ?? '' ) );
		$clean['reply_to'] = ( '' !== $reply_to && is_email( $reply_to ) ) ? $reply_to : '';

		$clean['logo_id']     = absint( $input['logo_id'] ?? 0 );
		$clean['logo_width']  = max( 40, min( 600, absint( $input['logo_width'] ?? $defaults['logo_width'] ) ) );
		$clean['logo_height'] = max( 20, min( 400, absint( $input['logo_height'] ?? $defaults['logo_height'] ) ) );

		$text_fields = array(
			'greeting',
			'email_body',
			'appreciation',
			'closing_text',
			'signature_dedication',
			'default_sender_name',
			'default_sender_role',
			'default_sender_phone',
			'company_name',
			'company_tagline',
			'office_phone',
			'office_address',
		);

		foreach ( $text_fields as $field ) {
			$value = (string) ( $input[ $field ] ?? $defaults[ $field ] );
			$clean[ $field ] = 'office_address' === $field || 'closing_text' === $field
				? sanitize_textarea_field( $value )
				: sanitize_text_field( $value );
		}

		$rows_input = $input['signature_rows'] ?? ( $input['signature_rows_json'] ?? array() );
		if ( is_string( $rows_input ) ) {
			$decoded    = json_decode( wp_unslash( $rows_input ), true );
			$rows_input = is_array( $decoded ) ? $decoded : array();
		}
		$clean['signature_rows'] = self::normalize_signature_rows(
			array( 'signature_rows' => is_array( $rows_input ) ? $rows_input : array() )
		);

		$default_sender_email = sanitize_email( (string) ( $input['default_sender_email'] ?? '' ) );
		$clean['default_sender_email'] = ( '' !== $default_sender_email && is_email( $default_sender_email ) )
			? $default_sender_email
			: '';

		return self::normalize_sender_defaults( $clean );
	}

	public static function seed_defaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			update_option( self::OPTION_KEY, self::defaults(), false );
		}
	}

	/** @return string[] */
	public static function placeholder_keys(): array {
		return array(
			'employee_name',
			'employee_email',
			'sender_name',
			'sender_role',
			'sender_email',
			'sender_phone',
			'company_name',
			'company_tagline',
			'office_phone',
			'office_address',
			'current_date',
			'request_type',
			'request_status',
			'department',
			'approval_link',
			'system_name',
			'email_content',
		);
	}
}
