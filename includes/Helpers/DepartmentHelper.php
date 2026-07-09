<?php
/**
 * Department assignment helpers for employees.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Department;
use ETWA\Repositories\DepartmentRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentHelper {

	public const META_DEPARTMENT_ID = 'etwa_department_id';

	/** @return int[] */
	public static function employee_ids_in_department( int $department_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta}
				WHERE meta_key = %s AND meta_value = %d",
				self::META_DEPARTMENT_ID,
				$department_id
			)
		);

		return array_map( 'intval', $rows ?: array() );
	}

	public static function get_employee_department_id( int $user_id ): ?int {
		$value = get_user_meta( $user_id, self::META_DEPARTMENT_ID, true );
		if ( ! $value ) {
			return null;
		}
		return (int) $value;
	}

	public static function get_employee_department( int $user_id ): ?Department {
		$dept_id = self::get_employee_department_id( $user_id );
		if ( ! $dept_id ) {
			return null;
		}
		return ( new DepartmentRepository() )->find( $dept_id );
	}

	public static function set_employee_department( int $user_id, ?int $department_id ): void {
		if ( ! $department_id ) {
			delete_user_meta( $user_id, self::META_DEPARTMENT_ID );
			return;
		}
		update_user_meta( $user_id, self::META_DEPARTMENT_ID, $department_id );
	}

	public static function department_requires_head_approval( int $user_id ): bool {
		$dept = self::get_employee_department( $user_id );
		if ( ! $dept || ! $dept->is_active() || ! $dept->approval_enabled || ! $dept->has_head() ) {
			return false;
		}
		// Department heads cannot approve their own requests — route directly to HR.
		if ( (int) $dept->head_user_id === $user_id ) {
			return false;
		}
		return true;
	}

	public static function get_department_head_id( int $user_id ): ?int {
		$dept = self::get_employee_department( $user_id );
		if ( ! $dept || ! $dept->has_head() || ! $dept->approval_enabled || ! $dept->is_active() ) {
			return null;
		}
		return $dept->head_user_id;
	}

	public static function user_heads_department( int $user_id, ?int $department_id = null ): bool {
		if ( ! user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) ) {
			return false;
		}
		$repo = new DepartmentRepository();
		if ( $department_id ) {
			$dept = $repo->find( $department_id );
			return $dept && $dept->head_user_id === $user_id;
		}
		return $repo->count_by_head( $user_id ) > 0;
	}

	public static function get_headed_department_id( int $user_id ): ?int {
		$dept = ( new DepartmentRepository() )->find_by_head( $user_id );
		return $dept ? $dept->id : null;
	}

	/** @return array{id:int,name:string}|null */
	public static function department_summary( int $user_id ): ?array {
		$dept = self::get_employee_department( $user_id );
		if ( ! $dept ) {
			return null;
		}
		$head = $dept->head_user_id ? get_userdata( $dept->head_user_id ) : null;
		return array(
			'id'               => $dept->id,
			'name'             => $dept->name,
			'head_user_id'     => $dept->head_user_id,
			'head_name'        => $head ? $head->display_name : '',
			'approval_enabled' => $dept->approval_enabled,
		);
	}

	/** @param int[] $user_ids @return array<int,array{id:int,name:string}|null> */
	public static function get_departments_batch( array $user_ids ): array {
		$result = array();
		foreach ( $user_ids as $uid ) {
			$result[ $uid ] = self::department_summary( $uid );
		}
		return $result;
	}
}
