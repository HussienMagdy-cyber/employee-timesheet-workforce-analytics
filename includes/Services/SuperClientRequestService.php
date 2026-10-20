<?php
/**
 * Product request service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Models\SuperClientRequest;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SuperClientAccountRepository;
use ETWA\Repositories\SuperClientRequestRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientRequestService {

	public function __construct(
		private SuperClientRequestRepository $requests = new SuperClientRequestRepository(),
		private SuperClientAccountRepository $accounts = new SuperClientAccountRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/**
	 * @param array{brand_id?:int,child_client_id?:int,request_type?:string,service_category?:string} $filters
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list_for_super_client( int $user_id, int $super_client_id, array $filters = array() ): array|WP_Error {
		$guard = $this->guard_super_client( $user_id, $super_client_id, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$items = array();
		foreach ( $this->requests->list_by_super_client( $super_client_id, $filters ) as $request ) {
			$items[] = $this->payload( $request );
		}
		return $items;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get( int $user_id, int $request_id ): array|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Product requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$request = $this->requests->find( $request_id );
		if ( ! $request ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $request->super_client_id, false );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		return $this->payload( $request );
	}

	/**
	 * Pending Product Request counts scoped to the viewer's accessible Product Tabs.
	 *
	 * @return array{total:int,tabs:array<int,array{id:int,pending_count:int}>}|WP_Error
	 */
	public function pending_counts_for_user( int $user_id ): array|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Product requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$tab_ids = SuperClientAccessHelper::accessible_product_tab_ids( $user_id );
		if ( ! $tab_ids ) {
			return array(
				'total' => 0,
				'tabs'  => array(),
			);
		}

		$grouped = $this->requests->count_pending_grouped_by_super_client( $tab_ids );
		$tabs    = array();
		$total   = 0;

		foreach ( $tab_ids as $tab_id ) {
			$count   = (int) ( $grouped[ $tab_id ] ?? 0 );
			$tabs[]  = array(
				'id'            => $tab_id,
				'pending_count' => $count,
			);
			$total += $count;
		}

		return array(
			'total' => $total,
			'tabs'  => $tabs,
		);
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, int $super_client_id, array $data ): SuperClientRequest|WP_Error {
		$guard = $this->guard_super_client( $user_id, $super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$can_edit_submission = SuperClientAccessHelper::can_complete_submission( $user_id );
		$payload             = $this->validate_payload( $super_client_id, $data, null, $can_edit_submission );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$id = $this->requests->create( $payload );
		if ( $id <= 0 ) {
			return new WP_Error( 'failed', __( 'Could not create request.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'create', 'super_client_request', $id, null, $payload );

		$request = $this->requests->find( $id );
		return $request ?? new WP_Error( 'failed', __( 'Could not load request.', 'etwa' ), array( 'status' => 500 ) );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $request_id, array $data ): SuperClientRequest|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Product requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->requests->find( $request_id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $existing->super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$can_edit_submission = SuperClientAccessHelper::can_complete_submission( $user_id );
		$payload             = $this->validate_payload( $existing->super_client_id, $data, $existing, $can_edit_submission );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( ! $this->requests->update( $request_id, $payload ) ) {
			return new WP_Error( 'failed', __( 'Could not update request.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'update', 'super_client_request', $request_id, $existing->to_array(), $payload );

		$updated = $this->requests->find( $request_id );
		return $updated ?? $existing;
	}

	public function delete( int $user_id, int $request_id ): bool|WP_Error {
		if ( ! SuperClientAccessHelper::can_manage_products( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage Product requests.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->requests->find( $request_id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Request not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$guard = $this->guard_super_client( $user_id, $existing->super_client_id, true );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( ! $this->requests->delete( $request_id ) ) {
			return new WP_Error( 'failed', __( 'Could not delete request.', 'etwa' ), array( 'status' => 500 ) );
		}

		$this->audit->log( 'delete', 'super_client_request', $request_id, $existing->to_array(), null );
		return true;
	}

	/** @return array<string,mixed> */
	private function payload( SuperClientRequest $request ): array {
		$row          = $request->to_array();
		$brand        = $this->accounts->find( $request->brand_id );
		$brand_payload = $brand ? $brand->to_array() : null;
		$row['brand'] = $brand_payload;
		$row['request'] = array(
			'brand'            => $brand_payload,
			'requestType'      => $request->request_type,
			'request_type'     => $request->request_type,
			'serviceCategory'  => $request->service_category,
			'service_category' => $request->service_category,
			'service'          => $request->service,
			'status'           => $request->status,
		);
		$row['submission'] = array(
			'contactFields' => array(
				'name'  => $request->contact_name,
				'email' => $request->contact_email,
				'phone' => $request->contact_phone,
			),
			'contact_name'    => $request->contact_name,
			'contact_email'   => $request->contact_email,
			'contact_phone'   => $request->contact_phone,
			'invoiceNo'       => $request->invoice_number,
			'invoice_number'  => $request->invoice_number,
		);
		return $row;
	}

	private function guard_super_client( int $user_id, int $super_client_id, bool $require_write ): \ETWA\Models\Client|WP_Error {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot access Product requests.', 'etwa' ), array( 'status' => 403 ) );
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
	private function validate_payload( int $super_client_id, array $data, ?SuperClientRequest $existing = null, bool $can_edit_submission = false ): array|WP_Error {
		$request_data    = is_array( $data['request'] ?? null ) ? $data['request'] : array();
		$submission_data = is_array( $data['submission'] ?? null ) ? $data['submission'] : array();
		$contact_fields  = is_array( $submission_data['contactFields'] ?? null )
			? $submission_data['contactFields']
			: ( is_array( $submission_data['contact_fields'] ?? null ) ? $submission_data['contact_fields'] : array() );

		$brand_id = (int) ( $data['brand_id'] ?? $data['child_client_id'] ?? $request_data['brand_id'] ?? ( $existing?->brand_id ?? 0 ) );
		if ( $brand_id <= 0 ) {
			return new WP_Error( 'invalid', __( 'Brand is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$brand = $this->accounts->find( $brand_id );
		if ( ! $brand || $brand->super_client_id !== $super_client_id ) {
			return new WP_Error( 'invalid', __( 'Invalid brand.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! $existing && ! $brand->is_active ) {
			return new WP_Error( 'invalid', __( 'Brand is inactive.', 'etwa' ), array( 'status' => 400 ) );
		}

		$request_type = sanitize_key( (string) ( $data['request_type'] ?? $request_data['requestType'] ?? $request_data['request_type'] ?? ( $existing?->request_type ?? '' ) ) );
		if ( ! in_array( $request_type, SuperClientRequestHelper::request_types(), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid request type.', 'etwa' ), array( 'status' => 400 ) );
		}

		$service_category = sanitize_key( (string) ( $data['service_category'] ?? $request_data['serviceCategory'] ?? $request_data['service_category'] ?? ( $existing?->service_category ?? '' ) ) );
		if ( ! in_array( $service_category, SuperClientRequestHelper::service_categories(), true ) ) {
			return new WP_Error( 'invalid', __( 'Service category is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$service = sanitize_text_field( (string) ( $data['service'] ?? $request_data['service'] ?? ( $existing?->service ?? '' ) ) );
		if ( '' === $service ) {
			return new WP_Error( 'invalid', __( 'Service is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$status = sanitize_key( (string) ( $data['status'] ?? $request_data['status'] ?? ( $existing?->status ?? SuperClientRequestHelper::STATUS_PENDING ) ) );
		if ( '' === $status ) {
			$status = SuperClientRequestHelper::STATUS_PENDING;
		}
		if ( ! in_array( $status, SuperClientRequestHelper::statuses(), true ) ) {
			return new WP_Error( 'invalid', __( 'Invalid status.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $can_edit_submission ) {
			$contact_name  = sanitize_text_field( (string) ( $data['contact_name'] ?? $contact_fields['name'] ?? $submission_data['contact_name'] ?? ( $existing?->contact_name ?? '' ) ) );
			$contact_email = sanitize_email( (string) ( $data['contact_email'] ?? $contact_fields['email'] ?? $submission_data['contact_email'] ?? ( $existing?->contact_email ?? '' ) ) );
			$contact_phone = sanitize_text_field( (string) ( $data['contact_phone'] ?? $contact_fields['phone'] ?? $submission_data['contact_phone'] ?? ( $existing?->contact_phone ?? '' ) ) );
			$invoice_number = sanitize_text_field( (string) ( $data['invoice_number'] ?? $submission_data['invoiceNo'] ?? $submission_data['invoice_number'] ?? ( $existing?->invoice_number ?? '' ) ) );
		} else {
			$contact_name   = $existing?->contact_name ?? '';
			$contact_email  = $existing?->contact_email ?? '';
			$contact_phone  = $existing?->contact_phone ?? '';
			$invoice_number = $existing?->invoice_number ?? '';
		}

		if ( ! SuperClientRequestHelper::validate_email( $contact_email ) ) {
			return new WP_Error( 'invalid', __( 'Invalid contact email.', 'etwa' ), array( 'status' => 400 ) );
		}

		return array(
			'super_client_id'  => $super_client_id,
			'brand_id'         => $brand_id,
			'request_type'     => $request_type,
			'service_category' => $service_category,
			'service'          => $service,
			'status'           => $status,
			'contact_name'     => $contact_name,
			'contact_email'    => $contact_email,
			'contact_phone'    => $contact_phone,
			'invoice_number'   => $invoice_number,
		);
	}
}
