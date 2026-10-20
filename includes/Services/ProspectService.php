<?php
/**
 * Prospect management service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Models\Prospect;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\ProspectRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectService {

	public function __construct(
		private ProspectRepository $prospects = new ProspectRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private EntryRepository $entries = new EntryRepository(),
		private ClientService $client_service = new ClientService(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/** @return array<int,array<string,mixed>> */
	public function list_for_dropdown(): array {
		return array_map(
			fn( Prospect $prospect ) => $prospect->to_array(),
			$this->prospects->all_open_for_dropdown()
		);
	}

	/** @return array<int,array<string,mixed>>|WP_Error */
	public function list_all( int $user_id, ?string $status = null ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( $status && ! in_array( $status, Prospect::statuses(), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid prospect status.', 'etwa' ), array( 'status' => 400 ) );
		}

		return array_map(
			fn( Prospect $prospect ) => $prospect->to_array(),
			$this->prospects->all( $status )
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function get( int $user_id, int $prospect_id ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$prospect = $this->prospects->find( $prospect_id );
		if ( ! $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		return $this->detail_payload( $prospect );
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, array $data ): Prospect|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', __( 'Prospect name is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$source = $this->validated_source( $data['source'] ?? null, true );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$raw_start  = sanitize_text_field( (string) ( $data['prospect_start_date'] ?? '' ) );
		$start_date = $this->validated_start_date( '' !== $raw_start ? $raw_start : current_time( 'Y-m-d' ), true );
		if ( is_wp_error( $start_date ) ) {
			return $start_date;
		}

		$id = $this->prospects->create(
			array(
				'name'                => $name,
				'status'              => Prospect::STATUS_OPEN,
				'source'              => $source,
				'prospect_start_date' => $start_date,
			)
		);

		$this->audit->log(
			'create',
			'prospect',
			$id,
			null,
			array(
				'name'                => $name,
				'source'              => $source,
				'prospect_start_date' => $start_date,
			)
		);

		return $this->prospects->find( $id );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $prospect_id, array $data ): Prospect|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$prospect = $this->prospects->find( $prospect_id );
		if ( ! $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'invalid', __( 'Prospect name is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['name'] = $name;
		}

		if ( array_key_exists( 'source', $data ) ) {
			$source = $this->validated_source( $data['source'], true );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$payload['source'] = $source;
		}

		if ( array_key_exists( 'prospect_start_date', $data ) ) {
			$start_date = $this->validated_start_date( $data['prospect_start_date'], true );
			if ( is_wp_error( $start_date ) ) {
				return $start_date;
			}
			$payload['prospect_start_date'] = $start_date;
		}

		if ( $prospect->is_lost() && array_key_exists( 'lost_reason', $data ) ) {
			$reason = sanitize_textarea_field( (string) $data['lost_reason'] );
			if ( '' === $reason ) {
				return new WP_Error( 'invalid', __( 'Lost reason is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['lost_reason'] = $reason;
		}

		if ( $payload ) {
			$this->prospects->update( $prospect_id, $payload );
			$this->audit->log( 'update', 'prospect', $prospect_id, $prospect->to_array(), $payload );
		}

		return $this->prospects->find( $prospect_id );
	}

	public function mark_won( int $user_id, int $prospect_id ): Prospect|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$prospect = $this->prospects->find( $prospect_id );
		if ( ! $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( ! $prospect->is_open() ) {
			return new WP_Error( 'invalid', __( 'Only open prospects can be marked as won.', 'etwa' ), array( 'status' => 400 ) );
		}

		$payload = array(
			'status'      => Prospect::STATUS_WON,
			'won_lost_at' => current_time( 'mysql' ),
			'lost_reason' => '',
		);
		$this->prospects->update( $prospect_id, $payload );
		$this->audit->log( 'mark_won', 'prospect', $prospect_id, $prospect->to_array(), $payload );

		return $this->prospects->find( $prospect_id );
	}

	public function mark_lost( int $user_id, int $prospect_id, string $reason ): Prospect|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$prospect = $this->prospects->find( $prospect_id );
		if ( ! $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( ! $prospect->is_open() ) {
			return new WP_Error( 'invalid', __( 'Only open prospects can be marked as lost.', 'etwa' ), array( 'status' => 400 ) );
		}

		$reason = sanitize_textarea_field( $reason );
		if ( '' === $reason ) {
			return new WP_Error( 'invalid', __( 'Lost reason is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$payload = array(
			'status'      => Prospect::STATUS_LOST,
			'won_lost_at' => current_time( 'mysql' ),
			'lost_reason' => $reason,
		);
		$this->prospects->update( $prospect_id, $payload );
		$this->audit->log( 'mark_lost', 'prospect', $prospect_id, $prospect->to_array(), $payload );

		return $this->prospects->find( $prospect_id );
	}

	/** @param array<string,mixed> $data */
	public function convert_to_client( int $user_id, int $prospect_id, array $data ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage prospects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$prospect = $this->prospects->find( $prospect_id );
		if ( ! $prospect ) {
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( $prospect->is_converted() ) {
			return $this->detail_payload( $prospect );
		}
		if ( ! $prospect->is_won() ) {
			return new WP_Error( 'invalid', __( 'Only won prospects can be converted to a client.', 'etwa' ), array( 'status' => 400 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? $prospect->name ) );
		if ( '' === $name ) {
			$name = $prospect->name;
		}

		$department = sanitize_text_field( (string) ( $data['department'] ?? 'General' ) );
		if ( '' === $department ) {
			$department = 'General';
		}

		// Historical entries stay on the prospect (prospect_id). Conversion never rewrites them.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$locked = $this->prospects->lock_for_update( $prospect_id );
		if ( ! $locked ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'not_found', __( 'Prospect not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( $locked->is_converted() ) {
			$wpdb->query( 'ROLLBACK' );
			return $this->detail_payload( $locked );
		}
		if ( ! $locked->is_won() ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'invalid', __( 'Only won prospects can be converted to a client.', 'etwa' ), array( 'status' => 400 ) );
		}

		$client = $this->client_service->create(
			$user_id,
			array(
				'name'       => $name,
				'code'       => $this->unique_client_code( (string) ( $data['code'] ?? '' ), $name, $prospect_id ),
				'department' => $department,
			)
		);
		if ( is_wp_error( $client ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $client;
		}
		if ( ! $client->id ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', __( 'Could not create this client.', 'etwa' ), array( 'status' => 500 ) );
		}

		if ( ! $this->prospects->mark_converted( $prospect_id, (int) $client->id ) ) {
			$error = $wpdb->last_error
				? sprintf(
					/* translators: %s: database error */
					__( 'Could not link the prospect to the new client: %s', 'etwa' ),
					$wpdb->last_error
				)
				: __( 'This prospect has already been converted to a client.', 'etwa' );
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'already_converted', $error, array( 'status' => 409 ) );
		}

		$persisted = $this->clients->find( (int) $client->id );
		if ( ! $persisted || ! $persisted->is_active ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'create_failed',
				__( 'The converted client was not saved as an active Workspace client.', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$wpdb->query( 'COMMIT' );

		$updated = $this->prospects->find( $prospect_id );
		$this->audit->log(
			'prospect_converted_to_client',
			'prospect',
			$prospect_id,
			$prospect->to_array(),
			array(
				'converted_client_id' => $persisted->id,
				'client_name'         => $persisted->name,
				'client_code'         => $persisted->code,
			)
		);

		return $this->detail_payload( $updated ?? $locked );
	}

	/** Build a client code that will pass ClientService::create (unique, not reserved). */
	private function unique_client_code( string $preferred, string $fallback_name, int $prospect_id ): string {
		$normalized = $this->normalize_client_code( $preferred );
		if ( '' === $normalized ) {
			$normalized = $this->normalize_client_code( $fallback_name );
		}
		if ( '' === $normalized ) {
			$normalized = 'P' . $prospect_id;
		}

		$base = substr( $normalized, 0, 32 );
		$try  = array( $normalized, $base . '_' . $prospect_id );
		for ( $i = 2; $i <= 99; $i++ ) {
			$try[] = $base . '_' . $i;
		}

		foreach ( $try as $code ) {
			$code = strtoupper( substr( $code, 0, 50 ) );
			if ( in_array( $code, ClientRepository::system_codes(), true ) ) {
				continue;
			}
			if ( ! $this->clients->find_by_code( $code ) ) {
				return $code;
			}
		}

		return strtoupper( substr( 'P' . $prospect_id . wp_generate_password( 8, false, false ), 0, 50 ) );
	}

	private function normalize_client_code( string $value ): string {
		$code = strtoupper( sanitize_text_field( $value ) );
		$code = preg_replace( '/[^A-Z0-9]+/', '_', $code ) ?? '';
		return trim( $code, '_' );
	}

	/** @return string|WP_Error */
	private function validated_source( mixed $value, bool $required ): string|WP_Error {
		$source = sanitize_key( (string) ( $value ?? '' ) );
		if ( '' === $source ) {
			return $required
				? new WP_Error( 'invalid', __( 'Prospect source is required.', 'etwa' ), array( 'status' => 400 ) )
				: '';
		}
		if ( ! in_array( $source, Prospect::sources(), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid prospect source.', 'etwa' ), array( 'status' => 400 ) );
		}

		return $source;
	}

	/** @return string|WP_Error */
	private function validated_start_date( mixed $value, bool $required ): string|WP_Error {
		$date = sanitize_text_field( (string) ( $value ?? '' ) );
		if ( '' === $date ) {
			return $required
				? new WP_Error( 'invalid', __( 'Prospect start date is required.', 'etwa' ), array( 'status' => 400 ) )
				: '';
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid', __( 'Invalid prospect start date.', 'etwa' ), array( 'status' => 400 ) );
		}
		$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'invalid', __( 'Invalid prospect start date.', 'etwa' ), array( 'status' => 400 ) );
		}

		return $date;
	}

	/** @return array<string,mixed> */
	private function detail_payload( Prospect $prospect ): array {
		$converted = null;
		if ( $prospect->converted_client_id ) {
			$client = $this->clients->find( $prospect->converted_client_id );
			$converted = $client ? $client->to_array() : null;
		}

		$entries = $this->entries->find_by_prospect( (int) $prospect->id, 100 );
		$names   = UserDisplayNames::map( array_map( fn( $entry ) => $entry->user_id, $entries ) );

		return array_merge(
			$prospect->to_array(),
			array(
				'total_hours'      => round( $this->prospects->total_hours( (int) $prospect->id ), 2 ),
				'entry_count'      => $this->prospects->entry_count( (int) $prospect->id ),
				'converted_client' => $converted,
				'entries'          => array_map(
					static function ( $entry ) use ( $names ) {
						$data                   = $entry->to_api_array();
						$data['employee_name']  = UserDisplayNames::get( $names, $entry->user_id );
						return $data;
					},
					$entries
				),
			)
		);
	}
}
