<?php
/**
 * Product Brand service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Models\Client;
use ETWA\Models\SuperClientAccount;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SuperClientAccountRepository;
use ETWA\Repositories\SuperClientRequestRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientAccountService {

	public function __construct(
		private SuperClientAccountRepository $accounts = new SuperClientAccountRepository(),
		private SuperClientRequestRepository $requests = new SuperClientRequestRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/**
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list_directory( int $user_id ): array|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$clients = $this->clients->list_super_clients();

		$items = array();
		foreach ( $clients as $client ) {
			if ( ! SuperClientAccessHelper::can_view_product_tab( $user_id, (int) $client->id ) ) {
				continue;
			}
			$items[] = $this->directory_payload( $client );
		}
		return $items;
	}

	/**
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list_for_super_client( int $user_id, int $super_client_id ): array|WP_Error {
		$guard = $this->guard_super_client( $user_id, $super_client_id, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$request_counts = $this->requests->count_by_brands( $super_client_id );
		$items          = array();

		foreach ( $this->accounts->list_by_super_client( $super_client_id ) as $account ) {
			$row                  = $account->to_array();
			$row['request_count'] = $request_counts[ (int) $account->id ] ?? 0;
			$items[]              = $row;
		}

		return $items;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get( int $user_id, int $account_id ): array|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$account = $this->accounts->find( $account_id );
		if ( ! $account ) {
			return new WP_Error( 'not_found', __( 'Brand not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $account->super_client_id, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$row                  = $account->to_array();
		$row['request_count'] = $this->accounts->request_count( $account_id );
		return $row;
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, int $super_client_id, array $data ): SuperClientAccount|WP_Error {
		$guard = $this->guard_super_client( $user_id, $super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$payload = $this->validate_payload( $super_client_id, $data );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$id = $this->accounts->create( $payload );
		if ( $id <= 0 ) {
			return new WP_Error( 'failed', __( 'Could not create brand.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'create', 'super_client_brand', $id, null, $payload );

		$account = $this->accounts->find( $id );
		return $account ?? new WP_Error( 'failed', __( 'Could not load brand.', 'etwa' ), array( 'status' => 500 ) );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $account_id, array $data ): SuperClientAccount|WP_Error {
		if ( ! SuperClientAccessHelper::can_manage_products( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$account = $this->accounts->find( $account_id );
		if ( ! $account ) {
			return new WP_Error( 'not_found', __( 'Brand not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $account->super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$payload = $this->validate_payload( $account->super_client_id, $data, $account );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( ! $this->accounts->update( $account_id, $payload ) ) {
			return new WP_Error( 'failed', __( 'Could not update brand.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'update', 'super_client_brand', $account_id, $account->to_array(), $payload );

		$updated = $this->accounts->find( $account_id );
		return $updated ?? $account;
	}

	public function deactivate( int $user_id, int $account_id ): bool|WP_Error {
		if ( ! SuperClientAccessHelper::can_manage_products( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$account = $this->accounts->find( $account_id );
		if ( ! $account ) {
			return new WP_Error( 'not_found', __( 'Brand not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $account->super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( ! $this->accounts->deactivate( $account_id ) ) {
			return new WP_Error( 'failed', __( 'Could not deactivate brand.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'deactivate', 'super_client_brand', $account_id, $account->to_array(), null );
		return true;
	}

	public function delete( int $user_id, int $account_id ): bool|WP_Error {
		if ( ! SuperClientAccessHelper::can_manage_products( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$account = $this->accounts->find( $account_id );
		if ( ! $account ) {
			return new WP_Error( 'not_found', __( 'Brand not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( $this->accounts->request_count( $account_id ) > 0 ) {
			return new WP_Error(
				'invalid',
				__( 'Cannot delete a brand with existing requests. Deactivate instead.', 'etwa' ),
				array( 'status' => 409 )
			);
		}

		if ( ! $this->accounts->delete( $account_id ) ) {
			return new WP_Error( 'failed', __( 'Could not delete brand.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'delete', 'super_client_brand', $account_id, $account->to_array(), null );
		return true;
	}

	/** @return array<string,mixed> */
	private function directory_payload( Client $client ): array {
		$id = (int) $client->id;
		return array(
			'client'        => $client->to_array(),
			'brand_count'   => $this->accounts->count_by_super_client( $id ),
			'request_count' => $this->requests->count_by_super_client( $id ),
		);
	}

	private function guard_super_client( int $user_id, int $super_client_id, bool $require_write ): Client|WP_Error {
		if ( $require_write && ! SuperClientAccessHelper::can_manage_products( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Products.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $super_client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $client->is_super() ) {
			return new WP_Error( 'invalid', __( 'Client is not a Product.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! SuperClientAccessHelper::can_view_product_tab( $user_id, $super_client_id ) ) {
			return new WP_Error( 'forbidden', __( 'This Product Tab is not available for your department.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( $require_write && ! $client->is_active ) {
			return new WP_Error( 'invalid', __( 'Product is inactive.', 'etwa' ), array( 'status' => 400 ) );
		}

		return $client;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_payload( int $super_client_id, array $data, ?SuperClientAccount $existing = null ): array|WP_Error {
		$name = sanitize_text_field( (string) ( $data['name'] ?? ( $existing?->name ?? '' ) ) );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', __( 'Brand is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$agency = sanitize_key( (string) ( $data['agency'] ?? ( $existing?->agency ?? SuperClientRequestHelper::AGENCY_DIRECT ) ) );
		if ( ! in_array( $agency, SuperClientRequestHelper::agency_types(), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid agency.', 'etwa' ), array( 'status' => 400 ) );
		}

		$agency_name = sanitize_text_field( (string) ( $data['agency_name'] ?? ( $existing?->agency_name ?? '' ) ) );
		if ( SuperClientRequestHelper::AGENCY_AGENCY === $agency && '' === $agency_name ) {
			return new WP_Error( 'invalid', __( 'Agency name is required when Agency is selected.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( SuperClientRequestHelper::AGENCY_DIRECT === $agency ) {
			$agency_name = '';
		}

		$industry = sanitize_key( (string) ( $data['industry'] ?? ( $existing?->industry ?? '' ) ) );
		if ( ! in_array( $industry, SuperClientRequestHelper::industries(), true ) ) {
			return new WP_Error( 'invalid', __( 'Industry is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$is_active = array_key_exists( 'is_active', $data )
			? (bool) $data['is_active']
			: ( $existing?->is_active ?? true );

		return array(
			'super_client_id' => $super_client_id,
			'name'            => $name,
			'industry'        => $industry,
			'agency'          => $agency,
			'agency_name'     => $agency_name,
			'is_active'       => $is_active ? 1 : 0,
		);
	}
}
