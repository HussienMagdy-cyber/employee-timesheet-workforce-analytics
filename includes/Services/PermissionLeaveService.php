<?php
/**
 * Permission approval → vacation balance deduction (no leave record / no day lock).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\PermissionHelper;
use ETWA\Helpers\RequestHelper;
use ETWA\Helpers\VacationHelper;
use ETWA\Models\Request;
use ETWA\Repositories\RequestRepository;
use ETWA\Repositories\VacationRecordRepository;
use ETWA\Repositories\VacationTransactionRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PermissionLeaveService {

	public function __construct(
		private RequestRepository $requests = new RequestRepository(),
		private VacationRecordRepository $records = new VacationRecordRepository(),
		private VacationTransactionRepository $transactions = new VacationTransactionRepository(),
	) {}

	/** @return true|WP_Error */
	public function ensure_leave_on_hr_approval( Request $request, int $reviewer_id, ?float $hr_deduction_days = null ): bool|WP_Error {
		if ( RequestHelper::TYPE_PERMISSION !== $request->request_type || RequestHelper::STATUS_APPROVED !== $request->status ) {
			return new WP_Error( 'invalid', __( 'Permission is not approved.', 'etwa' ), array( 'status' => 400 ) );
		}

		$extra = RequestHelper::decode_extra_data( $request->extra_data );

		if ( ! empty( $extra[ PermissionHelper::EXTRA_BALANCE_SYNCED ] ) ) {
			return true;
		}

		$extra = $this->remove_legacy_vacation_record( $request, $extra );

		$permission_date = PermissionHelper::permission_date( $request );
		$position        = PermissionHelper::monthly_position( (int) $request->user_id, $permission_date, $request->id );
		$total_deduction = PermissionHelper::resolve_total_deduction( $position, $hr_deduction_days );
		if ( is_wp_error( $total_deduction ) ) {
			return $total_deduction;
		}

		$annual = VacationHelper::annual_leave_type();
		if ( ! $annual ) {
			return new WP_Error( 'invalid_type', __( 'Annual leave type is not configured.', 'etwa' ), array( 'status' => 400 ) );
		}

		$split = PermissionHelper::split_deduction_by_balance(
			$total_deduction,
			VacationHelper::remaining_annual_balance( (int) $request->user_id )
		);

		$annual_deducted = $split['annual'];
		$unpaid_deducted = $split['unpaid'];

		$unpaid_type = null;
		if ( $unpaid_deducted > 0 ) {
			$unpaid_type = VacationHelper::unpaid_leave_type();
			if ( ! $unpaid_type ) {
				return new WP_Error( 'invalid_type', __( 'Unpaid leave type is not configured.', 'etwa' ), array( 'status' => 400 ) );
			}
		}

		$sync = $this->sync_permission_deductions(
			$request,
			$reviewer_id,
			$annual_deducted,
			$unpaid_deducted,
			$permission_date,
			(int) $annual->id,
			$unpaid_type ? (int) $unpaid_type->id : null,
			$extra
		);
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}

		$fresh = $this->requests->find( $request->id );
		$extra = RequestHelper::decode_extra_data( (string) ( $fresh?->extra_data ?? '' ) );

		$extra[ PermissionHelper::EXTRA_TOTAL_DEDUCTED ]   = $total_deduction;
		$extra[ PermissionHelper::EXTRA_BALANCE_DEDUCTED ] = $annual_deducted;
		$extra[ PermissionHelper::EXTRA_UNPAID_DEDUCTED ]  = $unpaid_deducted;
		$extra[ PermissionHelper::EXTRA_MONTHLY_POSITION ] = $position;
		$extra[ PermissionHelper::EXTRA_BALANCE_SYNCED ]   = true;

		unset( $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ] );

		$this->requests->update(
			$request->id,
			array( 'extra_data' => wp_json_encode( $extra ) )
		);

		return true;
	}

	/** @return true|WP_Error */
	public function revoke_permission_leave( Request $request, int $editor_id ): bool|WP_Error {
		$extra = RequestHelper::decode_extra_data( $request->extra_data );

		$this->reverse_permission_balance( $request, $editor_id, $extra );

		$record_id = isset( $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ]
			: 0;

		if ( $record_id <= 0 ) {
			$linked = $this->records->find_by_source_request( $request->id );
			$record_id = $linked ? $linked->id : 0;
		}

		if ( $record_id > 0 ) {
			$record = $this->records->find( $record_id );
			if ( $record ) {
				$this->records->delete( $record_id );
			}
		}

		$this->clear_request_leave_link( $request->id, $extra );

		return true;
	}

	/** @param array<string,mixed> $extra */
	public function clear_request_leave_link( int $request_id, ?array $extra = null ): void {
		$extra = null !== $extra ? $extra : RequestHelper::decode_extra_data(
			(string) ( $this->requests->find( $request_id )?->extra_data ?? '' )
		);

		unset(
			$extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ],
			$extra[ PermissionHelper::EXTRA_TOTAL_DEDUCTED ],
			$extra[ PermissionHelper::EXTRA_BALANCE_DEDUCTED ],
			$extra[ PermissionHelper::EXTRA_UNPAID_DEDUCTED ],
			$extra[ PermissionHelper::EXTRA_MONTHLY_POSITION ],
			$extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ],
			$extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ],
			$extra[ PermissionHelper::EXTRA_BALANCE_SYNCED ]
		);

		$this->requests->update(
			$request_id,
			array( 'extra_data' => $extra ? wp_json_encode( $extra ) : null )
		);
	}

	/**
	 * Remove legacy permission-linked vacation records that incorrectly locked days.
	 *
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	private function remove_legacy_vacation_record( Request $request, array $extra ): array {
		$record_id = isset( $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ]
			: 0;

		if ( $record_id <= 0 ) {
			$linked = $this->records->find_by_source_request( $request->id );
			$record_id = $linked ? $linked->id : 0;
		}

		if ( $record_id <= 0 ) {
			return $extra;
		}

		$record = $this->records->find( $record_id );
		if ( ! $record || PermissionHelper::RECORD_SOURCE !== ( $record->source ?? '' ) ) {
			return $extra;
		}

		$usage = $this->transactions->find_by_record( $record_id );
		if ( $usage && empty( $extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] ) ) {
			$legacy_deducted = abs( (float) $usage->days );
			$extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] = $usage->id;
			$extra[ PermissionHelper::EXTRA_BALANCE_DEDUCTED ]         = $legacy_deducted;
			$extra[ PermissionHelper::EXTRA_TOTAL_DEDUCTED ]           = $legacy_deducted;
		}

		$this->records->delete( $record_id );
		unset( $extra[ PermissionHelper::EXTRA_VACATION_RECORD_ID ] );

		return $extra;
	}

	/**
	 * @param array<string,mixed> $extra
	 * @return true|WP_Error
	 */
	private function sync_permission_deductions(
		Request $request,
		int $reviewer_id,
		float $annual_deducted,
		float $unpaid_deducted,
		string $effective_date,
		int $annual_leave_type_id,
		?int $unpaid_leave_type_id,
		array $extra
	): bool|WP_Error {
		if ( $annual_deducted <= 0 && $unpaid_deducted <= 0 ) {
			$this->reverse_permission_balance( $request, $reviewer_id, $extra );
			return true;
		}

		if ( $annual_deducted > 0 ) {
			$annual_sync = $this->sync_annual_deduction(
				$request,
				$reviewer_id,
				$annual_deducted,
				$effective_date,
				$annual_leave_type_id,
				$extra
			);
			if ( is_wp_error( $annual_sync ) ) {
				return $annual_sync;
			}
		} else {
			$this->reverse_annual_permission_deduction( $request, $reviewer_id, $extra );
		}

		if ( $unpaid_deducted > 0 ) {
			if ( ! $unpaid_leave_type_id ) {
				return new WP_Error( 'invalid_type', __( 'Unpaid leave type is not configured.', 'etwa' ), array( 'status' => 400 ) );
			}

			$unpaid_sync = $this->sync_unpaid_deduction(
				$request,
				$reviewer_id,
				$unpaid_deducted,
				$effective_date,
				$unpaid_leave_type_id,
				$extra
			);
			if ( is_wp_error( $unpaid_sync ) ) {
				return $unpaid_sync;
			}
		} else {
			$this->reverse_unpaid_permission_deduction( $request, $reviewer_id, $extra );
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $extra
	 * @return true|WP_Error
	 */
	private function sync_annual_deduction(
		Request $request,
		int $reviewer_id,
		float $annual_deducted,
		string $effective_date,
		int $leave_type_id,
		array $extra
	): bool|WP_Error {
		$transaction_id = isset( $extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ]
			: 0;

		if ( $transaction_id > 0 ) {
			$existing = $this->transactions->find( $transaction_id );
			if ( $existing && 'usage' === $existing->transaction_type ) {
				$existing_deducted = abs( (float) $existing->days );
				if ( abs( $existing_deducted - $annual_deducted ) < 0.001 ) {
					return true;
				}
			}
		}

		if ( $transaction_id > 0 && $this->transactions->find( $transaction_id ) ) {
			return true;
		}

		$transaction_id = $this->transactions->create(
			array(
				'user_id'          => (int) $request->user_id,
				'leave_type_id'    => $leave_type_id,
				'transaction_type' => 'usage',
				'days'             => -1 * abs( $annual_deducted ),
				'record_id'        => null,
				'effective_date'   => $effective_date,
				'notes'            => $this->permission_transaction_note( $request ),
				'created_by'       => $reviewer_id,
			)
		);

		$extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] = $transaction_id;
		$this->requests->update(
			$request->id,
			array( 'extra_data' => wp_json_encode( $extra ) )
		);

		return true;
	}

	/**
	 * @param array<string,mixed> $extra
	 * @return true|WP_Error
	 */
	private function sync_unpaid_deduction(
		Request $request,
		int $reviewer_id,
		float $unpaid_deducted,
		string $effective_date,
		int $unpaid_leave_type_id,
		array $extra
	): bool|WP_Error {
		$transaction_id = isset( $extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ]
			: 0;

		if ( $transaction_id > 0 ) {
			$existing = $this->transactions->find( $transaction_id );
			if ( $existing && 'usage' === $existing->transaction_type ) {
				$existing_deducted = abs( (float) $existing->days );
				if ( abs( $existing_deducted - $unpaid_deducted ) < 0.001 ) {
					return true;
				}
			}
		}

		if ( $transaction_id > 0 && $this->transactions->find( $transaction_id ) ) {
			return true;
		}

		$transaction_id = $this->transactions->create(
			array(
				'user_id'          => (int) $request->user_id,
				'leave_type_id'    => $unpaid_leave_type_id,
				'transaction_type' => 'usage',
				'days'             => -1 * abs( $unpaid_deducted ),
				'record_id'        => null,
				'effective_date'   => $effective_date,
				'notes'            => $this->permission_transaction_note( $request ),
				'created_by'       => $reviewer_id,
			)
		);

		$extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ] = $transaction_id;
		$this->requests->update(
			$request->id,
			array( 'extra_data' => wp_json_encode( $extra ) )
		);

		return true;
	}

	/** @param array<string,mixed> $extra */
	private function reverse_permission_balance( Request $request, int $editor_id, array $extra ): void {
		$this->reverse_annual_permission_deduction( $request, $editor_id, $extra );
		$this->reverse_unpaid_permission_deduction( $request, $editor_id, $extra );
	}

	/** @param array<string,mixed> $extra */
	private function reverse_annual_permission_deduction( Request $request, int $editor_id, array $extra ): void {
		$deducted = PermissionHelper::annual_deducted_from_extra( $extra );

		$transaction_id = isset( $extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_PERMISSION_TRANSACTION_ID ]
			: 0;

		if ( $transaction_id > 0 ) {
			$usage = $this->transactions->find( $transaction_id );
			if ( $usage && $deducted <= 0 ) {
				$deducted = abs( (float) $usage->days );
			}
		}

		if ( $deducted <= 0 || $this->transactions->reversal_exists_for_permission_request( $request->id ) ) {
			return;
		}

		$annual = VacationHelper::annual_leave_type();
		if ( ! $annual ) {
			return;
		}

		$this->transactions->create(
			array(
				'user_id'          => (int) $request->user_id,
				'leave_type_id'    => (int) $annual->id,
				'transaction_type' => 'adjustment',
				'days'             => $deducted,
				'effective_date'   => PermissionHelper::permission_date( $request ),
				'notes'            => sprintf(
					/* translators: %d: permission request id */
					__( 'Reversal for revoked permission request #%d', 'etwa' ),
					$request->id
				),
				'created_by'       => $editor_id,
			)
		);
	}

	/** @param array<string,mixed> $extra */
	private function reverse_unpaid_permission_deduction( Request $request, int $editor_id, array $extra ): void {
		$deducted = PermissionHelper::unpaid_deducted_from_extra( $extra );

		$transaction_id = isset( $extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ] )
			? (int) $extra[ PermissionHelper::EXTRA_UNPAID_TRANSACTION_ID ]
			: 0;

		if ( $transaction_id > 0 ) {
			$usage = $this->transactions->find( $transaction_id );
			if ( $usage && $deducted <= 0 ) {
				$deducted = abs( (float) $usage->days );
			}
		}

		if ( $deducted <= 0 || $this->transactions->reversal_exists_for_permission_unpaid_request( $request->id ) ) {
			return;
		}

		$unpaid = VacationHelper::unpaid_leave_type();
		if ( ! $unpaid ) {
			return;
		}

		$this->transactions->create(
			array(
				'user_id'          => (int) $request->user_id,
				'leave_type_id'    => (int) $unpaid->id,
				'transaction_type' => 'usage',
				'days'             => abs( $deducted ),
				'effective_date'   => PermissionHelper::permission_date( $request ),
				'notes'            => sprintf(
					/* translators: %d: permission request id */
					__( 'Reversal for revoked permission request #%d (unpaid)', 'etwa' ),
					$request->id
				),
				'created_by'       => $editor_id,
			)
		);
	}

	private function permission_transaction_note( Request $request ): string {
		return sprintf(
			/* translators: 1: permission type label, 2: request id */
			__( 'Permission (%1$s) — Request #%2$d', 'etwa' ),
			RequestHelper::permission_type_label( $request->permission_type ),
			$request->id
		);
	}
}
