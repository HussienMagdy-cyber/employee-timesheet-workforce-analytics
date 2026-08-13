<?php
/**
 * Deliverable allocation line item model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableAllocationItem {

	public function __construct(
		public readonly ?int $id,
		public readonly int $allocation_id,
		public readonly int $deliverable_type_id,
		public readonly float $quantity,
		public readonly string $scope_impact,
		public readonly ?int $replaced_type_id,
		public readonly ?float $replacement_qty,
		public readonly int $sort_order,
		public readonly string $created_at,
		public readonly string $updated_at,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['allocation_id'] ?? 0 ),
			(int) ( $r['deliverable_type_id'] ?? 0 ),
			(float) ( $r['quantity'] ?? 0 ),
			(string) ( $r['scope_impact'] ?? DeliverableAllocation::IMPACT_NORMAL ),
			isset( $r['replaced_type_id'] ) && null !== $r['replaced_type_id'] && '' !== (string) $r['replaced_type_id'] ? (int) $r['replaced_type_id'] : null,
			isset( $r['replacement_qty'] ) && null !== $r['replacement_qty'] && '' !== (string) $r['replacement_qty'] ? (float) $r['replacement_qty'] : null,
			(int) ( $r['sort_order'] ?? 0 ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'                  => $this->id,
			'allocation_id'       => $this->allocation_id,
			'deliverable_type_id' => $this->deliverable_type_id,
			'quantity'            => $this->quantity,
			'scope_impact'        => $this->scope_impact,
			'replaced_type_id'    => $this->replaced_type_id,
			'replacement_qty'     => $this->replacement_qty,
			'sort_order'          => $this->sort_order,
			'created_at'          => $this->created_at,
			'updated_at'          => $this->updated_at,
		);
	}
}
