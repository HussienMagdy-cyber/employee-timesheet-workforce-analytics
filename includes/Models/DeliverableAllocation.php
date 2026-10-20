<?php
/**
 * Deliverable allocation model (parent header + child items).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableAllocation {

	public const IMPACT_NORMAL      = 'normal';
	public const IMPACT_REPLACEMENT = 'replacement';
	public const IMPACT_EXTRA       = 'extra';

	/**
	 * @param DeliverableAllocationItem[] $items
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly int $scope_id,
		public readonly string $task_name,
		public readonly string $completed_date,
		public readonly string $notes,
		public readonly int $allocated_by,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly array $items = array(),
	) {}

	/**
	 * @param object|array<string,mixed>  $row
	 * @param DeliverableAllocationItem[] $items
	 */
	public static function from_row( object|array $row, array $items = array() ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) ( $r['scope_id'] ?? 0 ),
			(string) ( $r['task_name'] ?? '' ),
			(string) ( $r['completed_date'] ?? '' ),
			(string) ( $r['notes'] ?? '' ),
			(int) ( $r['allocated_by'] ?? 0 ),
			(string) ( $r['created_at'] ?? '' ),
			(string) ( $r['updated_at'] ?? '' ),
			$items,
		);
	}

	public function total_quantity(): float {
		$total = 0.0;
		foreach ( $this->items as $item ) {
			$total += (float) $item->quantity;
		}
		return round( $total, 2 );
	}

	/**
	 * @param array<int,string> $type_names
	 */
	public function summary( array $type_names = array() ): string {
		$parts = array();
		foreach ( $this->items as $item ) {
			$name    = $type_names[ (int) $item->deliverable_type_id ] ?? ( 'Type #' . $item->deliverable_type_id );
			$parts[] = rtrim( rtrim( number_format( (float) $item->quantity, 2, '.', '' ), '0' ), '.' ) . ' ' . $name;
		}
		return implode( ', ', $parts );
	}

	/**
	 * @param array<int,string>|null $type_names
	 * @return array<string,mixed>
	 */
	public function to_array( ?array $type_names = null ): array {
		$out = array(
			'id'             => $this->id,
			'scope_id'       => $this->scope_id,
			'task_name'      => $this->task_name,
			'completed_date' => $this->completed_date,
			'notes'          => $this->notes,
			'allocated_by'   => $this->allocated_by,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
			'items'          => array_map( static fn( DeliverableAllocationItem $i ) => $i->to_array(), $this->items ),
			'total_quantity' => $this->total_quantity(),
		);
		if ( null !== $type_names ) {
			$out['summary'] = $this->summary( $type_names );
		}
		return $out;
	}
}
