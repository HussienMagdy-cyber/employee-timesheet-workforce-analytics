<?php
/**
 * Super Client Brand model (stored in wp_etwa_super_clients).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

use ETWA\Helpers\SuperClientRequestHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientAccount {

	public function __construct(
		public readonly ?int $id,
		public readonly int $super_client_id,
		public readonly string $name,
		public readonly string $industry,
		public readonly string $agency,
		public readonly string $agency_name,
		public readonly bool $is_active,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		$agency = sanitize_key( (string) ( $r['agency'] ?? SuperClientRequestHelper::AGENCY_DIRECT ) );
		if ( ! in_array( $agency, SuperClientRequestHelper::agency_types(), true ) ) {
			$agency = SuperClientRequestHelper::AGENCY_DIRECT;
		}

		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['super_client_id'] ?? 0 ),
			(string) ( $r['name'] ?? '' ),
			(string) ( $r['industry'] ?? '' ),
			$agency,
			(string) ( $r['agency_name'] ?? '' ),
			(bool) ( $r['is_active'] ?? true ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'super_client_id' => $this->super_client_id,
			'name'            => $this->name,
			'industry'        => $this->industry,
			'agency'          => $this->agency,
			'agency_name'     => $this->agency_name,
			'is_active'       => $this->is_active,
			'created_at'      => $this->created_at,
			'updated_at'      => $this->updated_at,
		);
	}
}
