<?php
/**
 * Centralized HTML email delivery for ETWA notifications.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\BrandHelper;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Repositories\EmailSettingsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MailService {

	/** @param array<string,mixed>|null $settings_override */
	public function __construct(
		private EmailSettingsRepository $settings_repo = new EmailSettingsRepository(),
		private ?array $settings_override = null,
	) {}

	/** @param array<string,mixed> $settings */
	public function with_settings( array $settings ): self {
		$clone = clone $this;
		$clone->settings_override = $settings;
		return $clone;
	}

	/** @return array<string,mixed> */
	private function get_settings(): array {
		if ( null !== $this->settings_override ) {
			return array_merge( $this->settings_repo->all(), $this->settings_override );
		}

		return $this->settings_repo->all();
	}

	/**
	 * Send a branded HTML email through the configured ETWA template.
	 *
	 * @param array{
	 *   to: string|string[],
	 *   subject: string,
	 *   content: string,
	 *   placeholders?: array<string,string>,
	 *   sender_user_id?: int|null,
	 *   recipient_user_id?: int|null,
	 *   headers?: string[],
	 *   attachments?: string[],
	 *   content_is_html?: bool,
	 * } $args
	 * @return bool|WP_Error
	 */
	public function send( array $args ): bool|WP_Error {
		$to = $args['to'] ?? '';
		if ( is_array( $to ) ) {
			$to = array_filter( array_map( 'sanitize_email', $to ) );
		} else {
			$to = sanitize_email( (string) $to );
		}

		if ( empty( $to ) ) {
			return new WP_Error( 'invalid_recipient', __( 'Email recipient is required.', 'etwa' ) );
		}

		$subject = sanitize_text_field( (string) ( $args['subject'] ?? '' ) );
		if ( '' === $subject ) {
			return new WP_Error( 'invalid_subject', __( 'Email subject is required.', 'etwa' ) );
		}

		$content         = (string) ( $args['content'] ?? '' );
		$content_is_html = ! empty( $args['content_is_html'] );
		$placeholders    = is_array( $args['placeholders'] ?? null ) ? $args['placeholders'] : array();
		$sender_user_id  = isset( $args['sender_user_id'] ) ? absint( $args['sender_user_id'] ) : null;
		$recipient_id    = isset( $args['recipient_user_id'] ) ? absint( $args['recipient_user_id'] ) : null;
		$attachments     = is_array( $args['attachments'] ?? null ) ? $args['attachments'] : array();
		$extra_headers   = is_array( $args['headers'] ?? null ) ? $args['headers'] : array();

		$email_content = $content_is_html
			? wp_kses_post( $content )
			: nl2br( esc_html( $content ) );

		$merged = $this->build_placeholders(
			$email_content,
			$placeholders,
			$sender_user_id,
			$recipient_id,
			$to
		);

		$body    = $this->render_html( $merged );
		$headers = $this->build_headers( $extra_headers );
		$settings = $this->get_settings();

		$from_name_filter = null;
		$from_email = sanitize_email( (string) ( $settings['from_email'] ?? '' ) );
		$from_name  = sanitize_text_field( (string) ( $settings['from_name'] ?? '' ) );

		if ( ( '' === $from_email || ! is_email( $from_email ) ) && '' !== $from_name ) {
			$from_name_filter = static function () use ( $from_name ): string {
				return $from_name;
			};
			add_filter( 'wp_mail_from_name', $from_name_filter, 999 );
		}

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		if ( null !== $from_name_filter ) {
			remove_filter( 'wp_mail_from_name', $from_name_filter, 999 );
		}

		if ( ! $sent ) {
			return new WP_Error( 'email_failed', __( 'Failed to send email.', 'etwa' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Render the complete email HTML for preview or delivery.
	 *
	 * @param array<string,string> $placeholders
	 */
	public function render_html( array $placeholders, ?array $settings_override = null ): string {
		$settings = null !== $settings_override
			? array_merge( $this->settings_repo->all(), $settings_override )
			: $this->get_settings();

		$greeting   = $this->replace_placeholders( (string) ( $settings['greeting'] ?? '' ), $placeholders );
		$body       = $this->replace_placeholders( (string) ( $settings['email_body'] ?? '' ), $placeholders );
		$signature  = $this->render_signature( $placeholders, $settings );

		$text_style = 'margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;';
		$parts      = array();

		if ( '' !== trim( wp_strip_all_tags( $greeting ) ) ) {
			$parts[] = '<p style="' . $text_style . '">' . wp_kses_post( nl2br( esc_html( $greeting ) ) ) . '</p>';
		}

		if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
			$parts[] = '<div style="' . $text_style . '">' . wp_kses_post( $body ) . '</div>';
		}

		$content_html = implode( '', $parts );

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="margin:0;padding:24px;background:#f3f4f6;">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">'
			. '<tr><td style="padding:32px 32px 24px;">'
			. $content_html
			. '<hr style="border:none;border-top:1px solid #d1d5db;margin:28px 0 24px;" />'
			. $signature
			. '</td></tr></table></body></html>';
	}

	/**
	 * Build placeholder map for preview/test emails.
	 *
	 * @param array<string,string> $extra
	 * @return array<string,string>
	 */
	public function sample_placeholders( array $extra = array() ): array {
		$settings = $this->get_settings();

		$defaults = array(
			'employee_name'   => 'Jane Doe',
			'employee_email'  => 'jane.doe@example.com',
			'sender_name'     => (string) ( $settings['default_sender_name'] ?? '' ),
			'sender_role'     => (string) ( $settings['default_sender_role'] ?? '' ),
			'sender_email'    => (string) ( $settings['default_sender_email'] ?: get_option( 'admin_email' ) ),
			'sender_phone'    => (string) ( $settings['default_sender_phone'] ?? '' ),
			'company_name'    => (string) ( $settings['company_name'] ?? BrandHelper::NAME ),
			'company_tagline' => (string) ( $settings['company_tagline'] ?? '' ),
			'office_phone'    => (string) ( $settings['office_phone'] ?? '' ),
			'office_address'  => (string) ( $settings['office_address'] ?? '' ),
			'current_date'    => wp_date( get_option( 'date_format' ) ),
			'request_type'    => __( 'Vacation Request', 'etwa' ),
			'request_status'  => __( 'Pending Approval', 'etwa' ),
			'department'      => __( 'Marketing', 'etwa' ),
			'approval_link'   => admin_url( 'admin.php?page=etwa-requests' ),
			'system_name'     => BrandHelper::name(),
			'email_content'   => '<p>' . esc_html__( 'This is a sample notification message. Your actual email content will appear here.', 'etwa' ) . '</p>',
		);

		return array_merge( $defaults, $extra );
	}

	/**
	 * @param array<string,string> $extra
	 * @param string|string[]      $to
	 * @return array<string,string>
	 */
	private function build_placeholders(
		string $email_content,
		array $extra,
		?int $sender_user_id,
		?int $recipient_user_id,
		string|array $to
	): array {
		$settings = $this->get_settings();
		$base     = array(
			'email_content'   => $email_content,
			'company_name'    => (string) ( $settings['company_name'] ?? '' ),
			'company_tagline' => (string) ( $settings['company_tagline'] ?? '' ),
			'office_phone'    => (string) ( $settings['office_phone'] ?? '' ),
			'office_address'  => (string) ( $settings['office_address'] ?? '' ),
			'current_date'    => wp_date( get_option( 'date_format' ) ),
			'system_name'     => BrandHelper::name(),
			'employee_name'   => '',
			'employee_email'  => is_string( $to ) ? $to : (string) ( $to[0] ?? '' ),
			'sender_name'     => (string) ( $settings['default_sender_name'] ?? '' ),
			'sender_role'     => (string) ( $settings['default_sender_role'] ?? '' ),
			'sender_email'    => (string) ( $settings['default_sender_email'] ?: get_option( 'admin_email' ) ),
			'sender_phone'    => (string) ( $settings['default_sender_phone'] ?? '' ),
			'request_type'    => '',
			'request_status'  => '',
			'department'      => '',
			'approval_link'   => '',
		);

		if ( $recipient_user_id ) {
			$recipient = get_userdata( $recipient_user_id );
			if ( $recipient ) {
				$base['employee_name']  = $recipient->display_name;
				$base['employee_email'] = $recipient->user_email;
				$dept                   = DepartmentHelper::get_employee_department( $recipient_user_id );
				$base['department']     = $dept?->name ?? '';
			}
		}

		if ( $sender_user_id && $sender_user_id > 0 ) {
			$sender = get_userdata( $sender_user_id );
			if ( $sender ) {
				$meta                 = EmployeeProfileHelper::read_meta( $sender_user_id );
				$base['sender_name']  = $sender->display_name;
				$base['sender_role']  = (string) ( $meta['job_title'] ?? '' );
				$base['sender_email'] = $sender->user_email;
				$base['sender_phone'] = (string) ( $meta['phone'] ?? '' );
			}
		}

		return $this->resolve_sender_placeholders( array_merge( $base, $extra ) );
	}

	/** @param array<string,string> $placeholders @return array<string,string> */
	private function resolve_sender_placeholders( array $placeholders ): array {
		foreach ( array( 'sender_name', 'sender_role', 'sender_email', 'sender_phone' ) as $key ) {
			$value = (string) ( $placeholders[ $key ] ?? '' );
			if ( '' === $value || ! str_contains( $value, '{' ) ) {
				continue;
			}

			$resolved = $this->replace_placeholders( $value, $placeholders );
			if ( ! preg_match( '/\{[a-z_]+\}/', $resolved ) ) {
				$placeholders[ $key ] = $resolved;
			}
		}

		return $placeholders;
	}

	/** @param array<string,string> $placeholders @param array<string,mixed> $settings */
	private function render_signature( array $placeholders, array $settings ): string {
		$html = '';

		$appreciation = trim( $this->replace_placeholders( (string) ( $settings['appreciation'] ?? '' ), $placeholders ) );
		$closing      = trim( $this->replace_placeholders( (string) ( $settings['closing_text'] ?? '' ), $placeholders ) );
		$muted_style  = 'margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;color:#6b7280;';

		if ( '' !== $appreciation ) {
			$html .= '<p style="' . $muted_style . '">' . wp_kses_post( nl2br( esc_html( $appreciation ) ) ) . '</p>';
		}
		if ( '' !== $closing ) {
			$html .= '<p style="' . $muted_style . 'margin-bottom:20px;">' . wp_kses_post( nl2br( esc_html( $closing ) ) ) . '</p>';
		} elseif ( '' !== $appreciation ) {
			$html .= '<p style="margin:0 0 20px;"></p>';
		}

		$logo_id = absint( $settings['logo_id'] ?? 0 );
		if ( $logo_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$width = absint( $settings['logo_width'] ?? 180 );
				$height = absint( $settings['logo_height'] ?? 60 );
				$html  .= sprintf(
					'<p style="margin:0 0 20px;"><img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" style="display:block;max-width:100%%;height:auto;" /></p>',
					esc_url( $logo_url ),
					esc_attr( (string) ( $settings['company_name'] ?? '' ) ),
					$width,
					$height
				);
			}
		}

		$rows = $settings['signature_rows'] ?? EmailSettingsRepository::default_signature_rows();
		if ( is_string( $rows ) ) {
			$decoded = json_decode( $rows, true );
			$rows    = is_array( $decoded ) ? $decoded : EmailSettingsRepository::default_signature_rows();
		}

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$line = $this->resolve_signature_row_text( $row, $settings, $placeholders );
			$line = trim( $this->replace_placeholders( $line, $placeholders ) );
			if ( '' === $line ) {
				continue;
			}

			$html .= $this->render_signature_row_html( $line, $row, $placeholders );
		}

		return $html;
	}

	/** @param array<string,mixed> $row @param array<string,mixed> $settings @param array<string,string> $placeholders */
	private function resolve_signature_row_text( array $row, array $settings, array $placeholders ): string {
		$text   = (string) ( $row['text'] ?? '' );
		$source = (string) ( $row['source'] ?? 'custom' );

		if ( '' !== $text ) {
			return $text;
		}

		return match ( $source ) {
			'dedication'  => (string) ( $settings['signature_dedication'] ?? '' ),
			'sender_name' => (string) ( $placeholders['sender_name'] ?? '' ),
			'sender_role' => (string) ( $placeholders['sender_role'] ?? '' ),
			default       => '',
		};
	}

	/** @param array<string,mixed> $row @param array<string,string> $placeholders */
	private function render_signature_row_html( string $line, array $row, array $placeholders ): string {
		$size    = max( 11, min( 24, absint( $row['size'] ?? 14 ) ) );
		$weight  = (string) ( $row['weight'] ?? 'normal' );
		$color   = match ( (string) ( $row['color'] ?? 'default' ) ) {
			'muted' => '#6b7280',
			'dark'  => '#111827',
			default => '#374151',
		};
		$spacing = max( 0, min( 32, absint( $row['spacing'] ?? 4 ) ) );
		$style   = sprintf(
			'margin:0 0 %1$dpx;font-family:Arial,Helvetica,sans-serif;font-size:%2$dpx;font-weight:%3$s;line-height:1.5;color:%4$s;',
			$spacing,
			$size,
			'bold' === $weight || '600' === $weight ? '600' : '400',
			$color
		);

		$output = esc_html( $line );
		if ( ! empty( $row['link_email'] ) ) {
			$email = sanitize_email( (string) ( $placeholders['sender_email'] ?? '' ) );
			if ( $email && str_contains( $line, $email ) ) {
				$link    = '<a href="mailto:' . esc_attr( $email ) . '" style="color:#2563eb;text-decoration:none;">' . esc_html( $email ) . '</a>';
				$output  = str_replace( esc_html( $email ), $link, $output );
			}
		}

		if ( str_starts_with( $line, 'A:' ) || str_contains( (string) ( $row['text'] ?? '' ), '{office_address}' ) ) {
			$output = nl2br( $output );
		}

		return '<p style="' . esc_attr( $style ) . '">' . wp_kses_post( $output ) . '</p>';
	}

	/** @param string[] $extra_headers @return string[] */
	private function build_headers( array $extra_headers ): array {
		$settings = $this->get_settings();
		$headers  = array( 'Content-Type: text/html; charset=UTF-8' );

		$from_name  = sanitize_text_field( (string) ( $settings['from_name'] ?? '' ) );
		$from_email = sanitize_email( (string) ( $settings['from_email'] ?? '' ) );
		$reply_to   = sanitize_email( (string) ( $settings['reply_to'] ?? '' ) );

		if ( '' !== $from_email && is_email( $from_email ) ) {
			$from_header = $from_name ? sprintf( '%s <%s>', $from_name, $from_email ) : $from_email;
			$headers[]   = 'From: ' . $from_header;
		}

		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		foreach ( $extra_headers as $header ) {
			$header = trim( (string) $header );
			if ( '' !== $header ) {
				$headers[] = $header;
			}
		}

		return $headers;
	}

	/** @param array<string,string> $placeholders */
	private function replace_placeholders( string $template, array $placeholders ): string {
		$search  = array();
		$replace = array();

		foreach ( $placeholders as $key => $value ) {
			$search[]  = '{' . $key . '}';
			$replace[] = (string) $value;
		}

		return str_replace( $search, $replace, $template );
	}
}
