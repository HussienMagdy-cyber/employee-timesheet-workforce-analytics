<?php
/**
 * Product analytics service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SuperClientAccountRepository;
use ETWA\Repositories\SuperClientRequestRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientAnalyticsService {

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private SuperClientAccountRepository $accounts = new SuperClientAccountRepository(),
		private SuperClientRequestRepository $requests = new SuperClientRequestRepository(),
	) {}

	/** @return array<string,mixed>|WP_Error */
	public function get_summary( int $user_id, int $super_client_id, ?string $date_from = null, ?string $date_to = null ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view Product analytics.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client = $this->clients->find( $super_client_id );
		if ( ! $client ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $client->is_super() ) {
			return new WP_Error( 'invalid', __( 'Client is not a Product.', 'etwa' ), array( 'status' => 400 ) );
		}

		$by_type     = $this->requests->count_grouped( $super_client_id, 'request_type', $date_from, $date_to );
		$by_category = $this->requests->count_grouped( $super_client_id, 'service_category', $date_from, $date_to );
		$by_service  = $this->requests->count_grouped( $super_client_id, 'service', $date_from, $date_to );
		$by_agency   = $this->accounts->count_by_agency( $super_client_id );
		$by_industry = $this->accounts->count_by_industry( $super_client_id );
		$by_industry_requests = $this->requests->count_by_brand_industry( $super_client_id, $date_from, $date_to );
		$by_brand_raw = $this->requests->count_by_brands( $super_client_id, $date_from, $date_to );

		$by_brand = array();
		foreach ( $this->accounts->list_by_super_client( $super_client_id ) as $account ) {
			$by_brand[] = array(
				'brand_id'   => $account->id,
				'brand_name' => $account->name,
				'count'      => $by_brand_raw[ (int) $account->id ] ?? 0,
			);
		}

		return array(
			'client'              => $client->to_array(),
			'total_brands'        => $this->accounts->count_by_super_client( $super_client_id ),
			'active_brands'       => $this->accounts->count_by_super_client( $super_client_id, true ),
			'total_requests'      => $this->requests->count_by_super_client( $super_client_id, $date_from, $date_to ),
			'by_request_type'     => $this->label_map( $by_type, SuperClientRequestHelper::request_type_labels() ),
			'by_service_category' => $this->label_map( $by_category, SuperClientRequestHelper::service_category_labels() ),
			'by_service'          => $this->free_text_map( $by_service ),
			'by_agency'           => $this->label_map( $by_agency, SuperClientRequestHelper::agency_labels() ),
			'by_industry'         => $this->label_map( $by_industry, SuperClientRequestHelper::industry_labels() ),
			'by_industry_requests' => $this->label_map( $by_industry_requests, SuperClientRequestHelper::industry_labels() ),
			'by_brand'            => $by_brand,
		);
	}

	/**
	 * @param array<string,int> $counts
	 * @param array<string,string> $labels
	 * @return array<int,array<string,mixed>>
	 */
	private function label_map( array $counts, array $labels ): array {
		$items = array();
		foreach ( $counts as $key => $count ) {
			$items[] = array(
				'key'   => $key,
				'label' => $labels[ $key ] ?? $key,
				'count' => $count,
			);
		}
		return $items;
	}

	/**
	 * @param array<string,int> $counts
	 * @return array<int,array<string,mixed>>
	 */
	private function free_text_map( array $counts ): array {
		$items = array();
		foreach ( $counts as $key => $count ) {
			$items[] = array(
				'key'   => $key,
				'label' => $key,
				'count' => $count,
			);
		}
		return $items;
	}
}
