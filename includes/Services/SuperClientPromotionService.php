<?php
/**
 * Product promotion service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\Client;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SuperClientUnitRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientPromotionService {

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private SuperClientUnitRepository $units = new SuperClientUnitRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function promote( int $user_id, int $client_id ): Client|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( $this->clients->is_system_client( $client ) ) {
			return new WP_Error( 'invalid', __( 'System clients cannot be promoted.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $client->is_super() ) {
			return new WP_Error( 'invalid', __( 'Client is already a Product.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! $this->clients->set_client_type( $client_id, Client::TYPE_SUPER ) ) {
			return new WP_Error( 'failed', __( 'Could not promote client.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'promote_super', 'client', $client_id, $client->to_array(), array( 'client_type' => Client::TYPE_SUPER ) );

		$updated = $this->clients->find( $client_id );
		return $updated ?? $client;
	}

	public function demote( int $user_id, int $client_id ): Client|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $client->is_super() ) {
			return new WP_Error( 'invalid', __( 'Client is not a Product.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! $this->clients->set_client_type( $client_id, Client::TYPE_STANDARD ) ) {
			return new WP_Error( 'failed', __( 'Could not demote client.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->units->delete_for_super_client( $client_id );

		$this->audit->log( 'demote_super', 'client', $client_id, $client->to_array(), array( 'client_type' => Client::TYPE_STANDARD ) );

		$updated = $this->clients->find( $client_id );
		return $updated ?? $client;
	}
}
