<?php
/**
 * Employee hourly cost (user meta) — HR/Owner only.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeCostHelper {

	public const META_KEY = 'etwa_hourly_cost_egp';

	public static function can_view_labor_cost( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return $user_id > 0 && user_can( $user_id, RoleManager::CAP_VIEW_ALL );
	}

	public static function can_edit_labor_cost( ?int $user_id = null ): bool {
		return self::can_view_labor_cost( $user_id );
	}

	/**
	 * Safe read for authorized viewers only. Returns 0 for unauthorized callers.
	 */
	public static function get_hourly_cost( int $employee_user_id ): float {
		if ( ! self::can_view_labor_cost() ) {
			return 0.0;
		}

		return self::get_hourly_cost_internal( $employee_user_id );
	}

	/**
	 * Internal read for server-side labor calculations (export, analytics services).
	 * Call only after verifying the acting user may view labor cost.
	 */
	public static function get_hourly_cost_internal( int $employee_user_id ): float {
		if ( $employee_user_id <= 0 ) {
			return 0.0;
		}

		$value = get_user_meta( $employee_user_id, self::META_KEY, true );
		if ( '' === $value || false === $value ) {
			return 0.0;
		}

		return max( 0.0, round( (float) $value, 2 ) );
	}

	/**
	 * @param int[] $user_ids
	 * @return array<int,float>
	 */
	public static function get_rates_batch_internal( array $user_ids ): array {
		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( ! $user_ids ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built from count.
		$sql = "SELECT user_id, meta_value FROM {$wpdb->usermeta}
			WHERE meta_key = %s AND user_id IN ({$placeholders})";

		$params = array_merge( array( self::META_KEY ), $user_ids );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic IN list.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		$rates = array_fill_keys( $user_ids, 0.0 );
		foreach ( $rows ?: array() as $row ) {
			$rates[ (int) $row->user_id ] = max( 0.0, round( (float) $row->meta_value, 2 ) );
		}

		return $rates;
	}

	public static function is_etwa_employee( int $user_id ): bool {
		return RoleManager::is_workforce_member( $user_id );
	}

	public static function sanitize_hourly_cost( mixed $value ): float|\WP_Error {
		if ( ! is_numeric( $value ) ) {
			return new \WP_Error( 'invalid_cost', __( 'Hourly cost must be a number.', 'etwa' ) );
		}

		$cost = round( (float) $value, 2 );
		if ( $cost < 0 ) {
			return new \WP_Error( 'invalid_cost', __( 'Hourly cost cannot be negative.', 'etwa' ) );
		}

		if ( $cost > 999999.99 ) {
			return new \WP_Error( 'invalid_cost', __( 'Hourly cost exceeds the allowed maximum.', 'etwa' ) );
		}

		return $cost;
	}
}
