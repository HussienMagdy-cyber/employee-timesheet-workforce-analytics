<?php
/**
 * Employee hourly cost management.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Services\DepartmentService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeCostService {

	public function __construct(
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/**
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}|WP_Error
	 */
	public function list_employees( int $page, int $per_page, string $search = '', bool $include_inactive = false ): array|WP_Error {
		if ( ! EmployeeCostHelper::can_view_labor_cost() ) {
			return new WP_Error( 'forbidden', __( 'You cannot view employee labor costs.', 'etwa' ), array( 'status' => 403 ) );
		}

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );

		$args = array(
			'role__in' => EmployeeProfileHelper::assignable_role_slugs(),
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

		if ( ! $include_inactive ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => EmployeeProfileHelper::META_EMPLOYMENT_STATUS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => EmployeeProfileHelper::META_EMPLOYMENT_STATUS,
					'value'   => EmployeeProfileHelper::STATUS_ACTIVE,
					'compare' => '=',
				),
			);
		}

		$query = new \WP_User_Query( $args );
		$users = $query->get_results();
		$ids   = array_map( fn( $user ) => (int) $user->ID, $users );
		$rates = EmployeeCostHelper::get_rates_batch_internal( $ids );
		$depts = DepartmentHelper::get_departments_batch( $ids );

		$items = array();
		foreach ( $users as $user ) {
			$uid = (int) $user->ID;
			$items[] = array(
				'id'                => $uid,
				'display_name'      => $user->display_name,
				'email'             => $user->user_email,
				'role'              => EmployeeProfileHelper::primary_role_slug( $user ),
				'role_label'        => EmployeeProfileHelper::role_label_for_user( $user ),
				'employment_status' => EmployeeProfileHelper::get_employment_status( $uid ),
				'is_active'         => EmployeeProfileHelper::is_active( $uid ),
				'employment_label'  => EmployeeProfileHelper::employment_status_label(
					EmployeeProfileHelper::get_employment_status( $uid )
				),
				'hourly_cost_egp'   => $rates[ $uid ] ?? 0.0,
				'department_id'     => $depts[ $uid ]['id'] ?? null,
				'department_name'   => $depts[ $uid ]['name'] ?? '',
			);
		}

		return array(
			'items'    => $items,
			'total'    => (int) $query->get_total(),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	public function update_hourly_cost( int $editor_id, int $employee_id, float $hourly_cost ): true|WP_Error {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit employee labor costs.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! EmployeeProfileHelper::is_managed_user( $employee_id ) ) {
			return new WP_Error( 'invalid_employee', __( 'Invalid employee.', 'etwa' ), array( 'status' => 404 ) );
		}

		$sanitized = EmployeeCostHelper::sanitize_hourly_cost( $hourly_cost );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		$previous = EmployeeCostHelper::get_hourly_cost_internal( $employee_id );
		update_user_meta( $employee_id, EmployeeCostHelper::META_KEY, (string) $sanitized );

		$this->audit->log(
			'hourly_cost_update',
			'employee',
			$employee_id,
			array(
				'hourly_cost_egp' => $previous,
				'employee_id'     => $employee_id,
			),
			array(
				'hourly_cost_egp' => $sanitized,
				'employee_id'     => $employee_id,
			),
			$editor_id
		);

		return true;
	}

	/** @return true|WP_Error */
	public function update_department( int $editor_id, int $employee_id, ?int $department_id ): bool|WP_Error {
		return ( new DepartmentService() )->assign_employee( $editor_id, $employee_id, $department_id );
	}
}
