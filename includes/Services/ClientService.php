<?php
/**
 * Client management service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\Client;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientService {

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, array $data ): Client|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$code = strtoupper( sanitize_text_field( (string) ( $data['code'] ?? '' ) ) );

		if ( '' === $name || '' === $code ) {
			return new WP_Error( 'invalid', __( 'Name and code are required.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( in_array( $code, ClientRepository::system_codes(), true ) ) {
			return new WP_Error( 'reserved', __( 'This code is reserved for system use.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->clients->find_by_code( $code ) ) {
			return new WP_Error( 'duplicate', __( 'Client code already exists.', 'etwa' ), array( 'status' => 409 ) );
		}

		$id = $this->clients->create(
			array(
				'name'        => $name,
				'code'        => $code,
				'department'  => sanitize_text_field( (string) ( $data['department'] ?? 'General' ) ),
				'is_active'   => 1,
				'client_type' => Client::TYPE_STANDARD,
			)
		);
		if ( $id <= 0 ) {
			return new WP_Error( 'create_failed', __( 'Could not create this client.', 'etwa' ), array( 'status' => 500 ) );
		}

		$client = $this->clients->find( $id );
		if ( ! $client ) {
			return new WP_Error( 'create_failed', __( 'Could not create this client.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'create', 'client', $id, null, array( 'name' => $name, 'code' => $code ) );
		return $client;
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $client_id, array $data ): Client|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( ! $this->clients->is_system_client( $client ) ) {
			if ( isset( $data['name'] ) ) {
				$payload['name'] = sanitize_text_field( (string) $data['name'] );
			}
			if ( isset( $data['code'] ) ) {
				$code = strtoupper( sanitize_text_field( (string) $data['code'] ) );
				if ( in_array( $code, ClientRepository::system_codes(), true ) ) {
					return new WP_Error( 'reserved', __( 'This code is reserved for system use.', 'etwa' ), array( 'status' => 400 ) );
				}
				$existing = $this->clients->find_by_code( $code );
				if ( $existing && (int) $existing->id !== $client_id ) {
					return new WP_Error( 'duplicate', __( 'Client code already exists.', 'etwa' ), array( 'status' => 409 ) );
				}
				$payload['code'] = $code;
			}
		}
		if ( isset( $data['department'] ) ) {
			$payload['department'] = sanitize_text_field( (string) $data['department'] );
		}
		if ( isset( $data['is_active'] ) && ! $this->clients->is_system_client( $client ) ) {
			$payload['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( $payload ) {
			$this->clients->update( $client_id, $payload );
			$this->audit->log( 'update', 'client', $client_id, $client->to_array(), $payload );
		}

		return $this->clients->find( $client_id );
	}

	public function deactivate( int $user_id, int $client_id ): bool|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( $client && $client->is_super() ) {
			return new WP_Error(
				'invalid',
				__( 'Products cannot be deactivated directly. Demote to a standard client first.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->clients->deactivate( $client_id ) ) {
			return new WP_Error( 'invalid', __( 'Cannot deactivate this client.', 'etwa' ), array( 'status' => 400 ) );
		}
		$this->audit->log( 'deactivate', 'client', $client_id, null, null );
		return true;
	}

	public function delete( int $user_id, int $client_id ): bool|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( $this->clients->is_system_client( $client ) ) {
			return new WP_Error( 'invalid', __( 'System clients cannot be deleted.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $client->is_super() ) {
			return new WP_Error(
				'invalid',
				__( 'Products cannot be deleted. Demote to a standard client first.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( $this->clients->entry_count( $client_id ) > 0 ) {
			return new WP_Error(
				'has_entries',
				__( 'This client has timesheet entries and cannot be deleted. Deactivate it instead.', 'etwa' ),
				array( 'status' => 409 )
			);
		}

		if ( ! $this->clients->delete( $client_id ) ) {
			return new WP_Error( 'invalid', __( 'Could not delete this client.', 'etwa' ), array( 'status' => 400 ) );
		}

		$this->audit->log( 'delete', 'client', $client_id, $client->to_array(), null );
		return true;
	}
}
