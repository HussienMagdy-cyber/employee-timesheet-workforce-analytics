<?php
/**
 * Super Client request model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

use ETWA\Helpers\SuperClientRequestHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientRequest {

	public function __construct(
		public readonly ?int $id,
		public readonly int $super_client_id,
		public readonly int $brand_id,
		public readonly string $request_type,
		public readonly string $service_category,
		public readonly string $service,
		public readonly string $contact_name,
		public readonly string $contact_email,
		public readonly string $contact_phone,
		public readonly string $invoice_number,
		public readonly string $status,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		$brand_id = (int) ( $r['brand_id'] ?? $r['child_client_id'] ?? 0 );
		$status   = sanitize_key( (string) ( $r['status'] ?? SuperClientRequestHelper::STATUS_PENDING ) );
		if ( ! in_array( $status, SuperClientRequestHelper::statuses(), true ) ) {
			$status = SuperClientRequestHelper::STATUS_PENDING;
		}

		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['super_client_id'] ?? 0 ),
			$brand_id,
			(string) ( $r['request_type'] ?? '' ),
			(string) ( $r['service_category'] ?? '' ),
			(string) ( $r['service'] ?? $r['service_type'] ?? '' ),
			(string) ( $r['contact_name'] ?? '' ),
			(string) ( $r['contact_email'] ?? '' ),
			(string) ( $r['contact_phone'] ?? '' ),
			(string) ( $r['invoice_number'] ?? '' ),
			$status,
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'super_client_id'  => $this->super_client_id,
			'brand_id'         => $this->brand_id,
			'request_type'     => $this->request_type,
			'service_category' => $this->service_category,
			'service'          => $this->service,
			'contact_name'     => $this->contact_name,
			'contact_email'    => $this->contact_email,
			'contact_phone'    => $this->contact_phone,
			'invoice_number'   => $this->invoice_number,
			'status'           => $this->status,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		);
	}
}
