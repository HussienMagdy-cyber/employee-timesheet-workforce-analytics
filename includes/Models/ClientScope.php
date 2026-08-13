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
		public readonly int $patch_count,
		public readonly float $expected_per_patch,
		public readonly ?int $package_id,
		public readonly ?array $package_snapshot,
		public readonly int $created_by,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r    = (array) $row;
		$code = $r['code'] ?? null;
		$pkg  = $r['package_id'] ?? null;
		$proj = $r['project_id'] ?? null;
		$snap_raw = $r['package_snapshot'] ?? null;
		$snapshot = null;
		if ( is_string( $snap_raw ) && '' !== $snap_raw ) {
			$decoded  = json_decode( $snap_raw, true );
			$snapshot = is_array( $decoded ) ? $decoded : null;
		} elseif ( is_array( $snap_raw ) ) {
			$snapshot = $snap_raw;
		}

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
			(int) ( $r['patch_count'] ?? 0 ),
			(float) ( $r['expected_per_patch'] ?? 0 ),
			null !== $pkg && '' !== (string) $pkg && (int) $pkg > 0 ? (int) $pkg : null,
			$snapshot,
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
			'patch_count'           => $this->patch_count,
			'expected_per_patch'    => $this->expected_per_patch,
			'package_id'            => $this->package_id,
			'package_snapshot'      => $this->package_snapshot,
			'created_by'            => $this->created_by,
			'created_at'            => $this->created_at,
			'updated_at'            => $this->updated_at,
		);
	}

	/** Planned total is contract-only after batch retirement (v1.23). */
	public function planned_total(): float {
		return max( 0.0, $this->contract_deliverables );
	}
}
