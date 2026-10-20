<?php
/**
 * Client scope model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientScope {

	public function __construct(
		public readonly ?int $id,
		public readonly int $client_id,
		public readonly ?int $project_id,
		public readonly string $name,
		public readonly ?string $code,
		public readonly string $description,
		public readonly string $status,
		public readonly ?string $starts_on,
		public readonly ?string $ends_on,
		public readonly float $contract_deliverables,
		public readonly int $created_by,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r    = (array) $row;
		$code = $r['code'] ?? null;
		$proj = $r['project_id'] ?? null;

		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['client_id'] ?? 0 ),
			null !== $proj && '' !== (string) $proj && (int) $proj > 0 ? (int) $proj : null,
			(string) ( $r['name'] ?? '' ),
			null !== $code && '' !== (string) $code ? (string) $code : null,
			(string) ( $r['description'] ?? '' ),
			(string) ( $r['status'] ?? 'active' ),
			! empty( $r['starts_on'] ) ? (string) $r['starts_on'] : null,
			! empty( $r['ends_on'] ) ? (string) $r['ends_on'] : null,
			(float) ( $r['contract_deliverables'] ?? 0 ),
			(int) ( $r['created_by'] ?? 0 ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                    => $this->id,
			'client_id'             => $this->client_id,
			'project_id'            => $this->project_id,
			'name'                  => $this->name,
			'code'                  => $this->code,
			'description'           => $this->description,
			'status'                => $this->status,
			'starts_on'             => $this->starts_on,
			'ends_on'               => $this->ends_on,
			'contract_deliverables' => $this->contract_deliverables,
			'created_by'            => $this->created_by,
			'created_at'            => $this->created_at,
			'updated_at'            => $this->updated_at,
		);
	}

	public function planned_total(): float {
		return max( 0.0, $this->contract_deliverables );
	}
}
