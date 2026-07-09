<?php
/**
 * Vacation ledger transaction model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationTransaction {

	public function __construct(
		public int $id,
		public int $user_id,
		public int $leave_type_id,
		public string $transaction_type,
		public float $days,
		public ?int $record_id,
		public string $effective_date,
		public string $notes,
		public int $created_by,
		public string $created_at,
	) {}

	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->user_id,
			(int) $row->leave_type_id,
			(string) $row->transaction_type,
			(float) $row->days,
			isset( $row->record_id ) && $row->record_id ? (int) $row->record_id : null,
			(string) $row->effective_date,
			(string) ( $row->notes ?? '' ),
			(int) $row->created_by,
			(string) $row->created_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'user_id'          => $this->user_id,
			'leave_type_id'    => $this->leave_type_id,
			'transaction_type' => $this->transaction_type,
			'days'             => $this->days,
			'record_id'        => $this->record_id,
			'effective_date'   => $this->effective_date,
			'notes'            => $this->notes,
			'created_by'       => $this->created_by,
			'created_at'       => $this->created_at,
		);
	}
}
