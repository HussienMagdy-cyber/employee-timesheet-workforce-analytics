<?php
/**
 * Vacation helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\LeaveType;
use ETWA\Repositories\LeaveTypeRepository;
use ETWA\Repositories\VacationTransactionRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationHelper {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	public const ANNUAL_SLUG = 'annual';
	public const UNPAID_SLUG = 'unpaid';

	public const DAY_INCREMENT = 0.25;

	public static function normalize_days( float $days ): float {
		if ( 0.0 === $days ) {
			return 0.0;
		}

		$sign = $days < 0 ? -1 : 1;
		return $sign * round( round( abs( $days ) / self::DAY_INCREMENT ) * self::DAY_INCREMENT, 2 );
	}

	/** @return string[] */
	public static function valid_statuses(): array {
		return array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED );
	}

	public static function days_between( string $start_date, string $end_date ): float {
		$start = \DateTimeImmutable::createFromFormat( 'Y-m-d', $start_date );
		$end   = \DateTimeImmutable::createFromFormat( 'Y-m-d', $end_date );
		if ( ! $start || ! $end || $end < $start ) {
			return 0.0;
		}
		return (float) ( $start->diff( $end )->days + 1 );
	}

	public static function validate_date( string $date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	public static function is_etwa_employee( int $user_id ): bool {
		return RoleManager::is_workforce_member( $user_id );
	}

	public static function annual_leave_type(): ?LeaveType {
		return ( new LeaveTypeRepository() )->find_by_slug( self::ANNUAL_SLUG );
	}

	public static function unpaid_leave_type(): ?LeaveType {
		return ( new LeaveTypeRepository() )->find_by_slug( self::UNPAID_SLUG );
	}

	public static function is_unpaid_type( int $leave_type_id ): bool {
		$unpaid = self::unpaid_leave_type();
		return $unpaid && (int) $unpaid->id === $leave_type_id;
	}

	public static function deducts_from_annual_pool( int $leave_type_id ): bool {
		return ! self::is_unpaid_type( $leave_type_id );
	}

	/**
	 * Annual leave pool: allocated on annual type minus all approved usage (any type except unpaid).
	 *
	 * @return array{allocated:float,used:float,remaining:float,leave_type_id:int|null}
	 */
	public static function annual_pool( int $user_id ): array {
		$annual     = self::annual_leave_type();
		$annual_id  = $annual ? (int) $annual->id : 0;
		$unpaid     = self::unpaid_leave_type();
		$unpaid_id  = $unpaid ? (int) $unpaid->id : 0;
		$transactions = new VacationTransactionRepository();

		$allocations = $transactions->sum_by_type( $user_id, 'allocation' );
		$adjustments = $transactions->sum_by_type( $user_id, 'adjustment' );
		$usage       = $transactions->sum_by_type( $user_id, 'usage' );

		$allocated = $annual_id ? max( 0, (float) ( $allocations[ $annual_id ] ?? 0 ) ) : 0.0;
		$allocated += $annual_id ? (float) ( $adjustments[ $annual_id ] ?? 0 ) : 0.0;

		$total_used = 0.0;
		foreach ( $usage as $type_id => $days ) {
			if ( $unpaid_id && (int) $type_id === $unpaid_id ) {
				continue;
			}
			$total_used += abs( (float) $days );
		}

		$remaining = $allocated - $total_used;

		return array(
			'leave_type_id' => $annual_id ?: null,
			'allocated'     => round( $allocated, 2 ),
			'used'          => round( $total_used, 2 ),
			'remaining'     => round( $remaining, 2 ),
		);
	}

	public static function remaining_annual_balance( int $user_id ): float {
		return (float) self::annual_pool( $user_id )['remaining'];
	}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function list_employees( int $page = 1, int $per_page = 100, string $search = '' ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );

		$args = array(
			'role__in' => RoleManager::workforce_role_slugs(),
			'number'   => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
			'fields'   => 'all',
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . esc_attr( $search ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$query = new \WP_User_Query( $args );
		$items = array();
		foreach ( $query->get_results() as $user ) {
			if ( ! EmployeeProfileHelper::is_active( (int) $user->ID ) ) {
				continue;
			}
			$items[] = array(
				'id'           => (int) $user->ID,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'roles'        => EmployeeProfileHelper::role_label_for_user( $user ),
			);
		}

		return array(
			'items'    => $items,
			'total'    => (int) $query->get_total(),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function employee_balances( int $user_id ): array {
		$types        = ( new LeaveTypeRepository() )->all_active();
		$transactions = new VacationTransactionRepository();
		$usage        = $transactions->sum_by_type( $user_id, 'usage' );
		$pool         = self::annual_pool( $user_id );
		$annual       = self::annual_leave_type();

		$balances = array();

		if ( $annual ) {
			$balances[] = array(
				'leave_type_id'     => (int) $annual->id,
				'leave_type_name'   => $annual->name,
				'leave_type_slug'   => $annual->slug,
				'tracks_allocation' => true,
				'is_primary_pool'   => true,
				'allocated'         => $pool['allocated'],
				'used'              => $pool['used'],
				'remaining'         => $pool['remaining'],
				'pool_note'         => __( 'All leave types (except unpaid) deduct from this balance.', 'etwa' ),
			);
		}

		foreach ( $types as $type ) {
			if ( $annual && (int) $type->id === (int) $annual->id ) {
				continue;
			}

			$type_used = abs( (float) ( $usage[ $type->id ] ?? 0 ) );
			$balances[] = array(
				'leave_type_id'     => $type->id,
				'leave_type_name'   => $type->name,
				'leave_type_slug'   => $type->slug,
				'tracks_allocation' => false,
				'is_primary_pool'   => false,
				'allocated'         => null,
				'used'              => round( $type_used, 2 ),
				'remaining'         => null,
				'usage_only'        => true,
			);
		}

		return $balances;
	}

	public static function remaining_for_type( int $user_id, int $leave_type_id ): float {
		if ( self::is_unpaid_type( $leave_type_id ) ) {
			return 0.0;
		}
		return self::remaining_annual_balance( $user_id );
	}

	public static function transaction_type_label( string $type ): string {
		return match ( $type ) {
			'allocation' => __( 'Allocation', 'etwa' ),
			'usage'      => __( 'Vacation', 'etwa' ),
			'adjustment' => __( 'Adjustment', 'etwa' ),
			'vacation'   => __( 'Vacation', 'etwa' ),
			default      => ucfirst( $type ),
		};
	}
}
