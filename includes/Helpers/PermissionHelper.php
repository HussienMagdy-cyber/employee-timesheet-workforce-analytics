<?php
/**
 * Permission request helpers (monthly allowance, leave linkage).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Request;
use ETWA\Repositories\RequestRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PermissionHelper {

	public const LEAVE_DAYS          = 0.25;
	public const FREE_PER_MONTH      = 2;
	public const RECORD_SOURCE       = 'permission';
	public const EXTRA_VACATION_RECORD_ID        = 'vacation_record_id';
	public const EXTRA_BALANCE_DEDUCTED          = 'balance_deducted';
	public const EXTRA_UNPAID_DEDUCTED           = 'unpaid_deducted';
	public const EXTRA_TOTAL_DEDUCTED            = 'total_deducted';
	public const EXTRA_MONTHLY_POSITION          = 'monthly_position';
	public const EXTRA_PERMISSION_TRANSACTION_ID = 'permission_transaction_id';
	public const EXTRA_UNPAID_TRANSACTION_ID     = 'unpaid_transaction_id';
	public const EXTRA_BALANCE_SYNCED            = 'balance_synced';

	public static function permission_date( Request $request ): string {
		return $request->work_date ?: $request->request_date;
	}

	/** @return array{year:int,month:int,start:string,end:string} */
	public static function month_bounds_from_date( string $date ): array {
		$dt = new \DateTimeImmutable( $date );

		return array(
			'year'  => (int) $dt->format( 'Y' ),
			'month' => (int) $dt->format( 'n' ),
			'start' => $dt->format( 'Y-m-01' ),
			'end'   => $dt->modify( 'last day of this month' )->format( 'Y-m-d' ),
		);
	}

	public static function count_approved_in_month( int $user_id, int $year, int $month, ?int $exclude_request_id = null ): int {
		return ( new RequestRepository() )->count_approved_permissions_in_month( $user_id, $year, $month, $exclude_request_id );
	}

	public static function monthly_position( int $user_id, string $permission_date, int $request_id ): int {
		$bounds = self::month_bounds_from_date( $permission_date );

		return self::count_approved_in_month( $user_id, $bounds['year'], $bounds['month'], $request_id ) + 1;
	}

	public static function balance_deduction_for_position( int $position ): float {
		return self::requires_deduction_for_position( $position ) ? self::default_deduction_days() : 0.0;
	}

	public static function requires_deduction_for_position( int $position ): bool {
		return $position > self::FREE_PER_MONTH;
	}

	public static function default_deduction_days(): float {
		return self::LEAVE_DAYS;
	}

	/**
	 * Resolve the total deduction for a permission approval.
	 *
	 * Position determines whether a deduction applies; the HR-entered amount is the total.
	 *
	 * @return float|WP_Error Total days to deduct (0 for free permissions).
	 */
	public static function resolve_total_deduction( int $position, ?float $hr_deduction_days ): float|WP_Error {
		if ( ! self::requires_deduction_for_position( $position ) ) {
			return 0.0;
		}

		if ( null === $hr_deduction_days ) {
			return new WP_Error(
				'missing_days',
				__( 'Deduction days are required for this permission.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		return self::normalize_hr_deduction_days( $hr_deduction_days );
	}

	/** @return float|WP_Error */
	public static function normalize_hr_deduction_days( float $hr_days ): float|WP_Error {
		if ( ! is_numeric( $hr_days ) || (float) $hr_days <= 0 ) {
			return new WP_Error(
				'invalid_days',
				__( 'Deduction days must be greater than zero.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$normalized = VacationHelper::normalize_days( (float) $hr_days );
		if ( $normalized <= 0 ) {
			return new WP_Error(
				'invalid_days',
				__( 'Enter a valid deduction amount in 0.25-day increments.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		return $normalized;
	}

	/** @return float|WP_Error @deprecated Use resolve_total_deduction() instead. */
	public static function resolve_hr_deduction( int $position, ?float $hr_days ): float|WP_Error {
		return self::resolve_total_deduction( $position, $hr_days );
	}

	/**
	 * Split a permission deduction between annual leave and unpaid based on available balance.
	 *
	 * @return array{annual:float,unpaid:float}
	 */
	public static function split_deduction_by_balance( float $total_deduction, float $remaining_annual ): array {
		$annual = min( $total_deduction, max( 0.0, $remaining_annual ) );

		return array(
			'annual' => round( $annual, 2 ),
			'unpaid' => round( $total_deduction - $annual, 2 ),
		);
	}

	/** @param array<string,mixed> $extra */
	public static function annual_deducted_from_extra( array $extra ): float {
		return round( (float) ( $extra[ self::EXTRA_BALANCE_DEDUCTED ] ?? 0 ), 2 );
	}

	/** @param array<string,mixed> $extra */
	public static function unpaid_deducted_from_extra( array $extra ): float {
		return round( (float) ( $extra[ self::EXTRA_UNPAID_DEDUCTED ] ?? 0 ), 2 );
	}

	/** @param array<string,mixed> $extra */
	public static function total_deducted_from_extra( array $extra ): float {
		if ( isset( $extra[ self::EXTRA_TOTAL_DEDUCTED ] ) ) {
			return round( (float) $extra[ self::EXTRA_TOTAL_DEDUCTED ], 2 );
		}

		return round(
			self::annual_deducted_from_extra( $extra ) + self::unpaid_deducted_from_extra( $extra ),
			2
		);
	}

	/** @return array<string,mixed> */
	public static function monthly_usage_summary( int $user_id, ?string $reference_date = null ): array {
		$reference_date = $reference_date ?: current_time( 'Y-m-d' );
		$bounds         = self::month_bounds_from_date( $reference_date );
		$approved_count = self::count_approved_in_month( $user_id, $bounds['year'], $bounds['month'] );
		$free_used      = min( $approved_count, self::FREE_PER_MONTH );
		$free_remaining = max( 0, self::FREE_PER_MONTH - $approved_count );
		$vacation_deducted = ( new RequestRepository() )->sum_permission_balance_deducted_in_month(
			$user_id,
			$bounds['year'],
			$bounds['month']
		);

		return array(
			'year'               => $bounds['year'],
			'month'              => $bounds['month'],
			'approved_count'     => $approved_count,
			'free_allowance'     => self::FREE_PER_MONTH,
			'free_used'          => $free_used,
			'free_remaining'     => $free_remaining,
			'vacation_deducted'  => round( $vacation_deducted, 2 ),
			'permissions_label'  => sprintf(
				/* translators: 1: approved count, 2: monthly allowance */
				__( 'Permissions This Month: %1$d / %2$d', 'etwa' ),
				$approved_count,
				self::FREE_PER_MONTH
			),
			'free_remaining_label' => sprintf(
				/* translators: %d: remaining permissions */
				__( 'Permissions Remaining: %d', 'etwa' ),
				$free_remaining
			),
			'vacation_deducted_label' => sprintf(
				/* translators: %s: days deducted */
				__( 'Vacation Deducted: %s day', 'etwa' ),
				number_format( $vacation_deducted, 2 )
			),
		);
	}

	public static function leave_reason_label( Request $request ): string {
		$type_label = RequestHelper::permission_type_label( $request->permission_type );

		return sprintf(
			/* translators: 1: permission type, 2: request id */
			__( 'Permission (%1$s) — Request #%2$d', 'etwa' ),
			$type_label,
			$request->id
		);
	}
}
