<?php
/**
 * Department management service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Models\Department;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\DepartmentRepository;
use ETWA\Repositories\SuperClientUnitRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentService {

	public function __construct(
		private DepartmentRepository $departments = new DepartmentRepository(),
		private SuperClientUnitRepository $product_tabs = new SuperClientUnitRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function can_manage( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_MANAGE_DEPARTMENTS );
	}

	public function can_view_department_dashboard( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS )
			&& DepartmentHelper::user_heads_department( $user_id );
	}

	/** @return array{items:array<int,array<string,mixed>>}|WP_Error */
	public function list_departments( int $viewer_id ): array|WP_Error {
		if (
			! $this->can_manage( $viewer_id )
			&& ! $this->can_view_department_dashboard( $viewer_id )
			&& ! user_can( $viewer_id, RoleManager::CAP_VIEW_ALL )
		) {
			return new WP_Error( 'forbidden', __( 'You cannot view departments.', 'etwa' ), array( 'status' => 403 ) );
		}

		$items = array_map(
			function ( Department $dept ) {
				$this->ensure_head_is_assigned( $dept );
				return $this->format_department( $dept );
			},
			$this->departments->all()
		);

		return array( 'items' => $items );
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_department( int $viewer_id, int $id ): array|WP_Error {
		$dept = $this->departments->find( $id );
		if ( ! $dept ) {
			return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		if ( ! $this->can_manage( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot view this department.', 'etwa' ), array( 'status' => 403 ) );
		}
		$this->ensure_head_is_assigned( $dept );
		return $this->format_department( $dept, true );
	}

	/** @param array<string,mixed> $data @return Department|WP_Error */
	public function create( int $editor_id, array $data ): Department|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot create departments.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_payload( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$id   = $this->departments->create( $validated );
		$dept = $this->departments->find( $id );

		if ( ! empty( $validated['head_user_id'] ) ) {
			$this->sync_head_assignment( $id, (int) $validated['head_user_id'] );
		}

		$this->audit->log(
			'create',
			'department',
			$id,
			null,
			$dept?->to_array(),
			$editor_id
		);

		return $dept ?: new WP_Error( 'create_failed', __( 'Could not create department.', 'etwa' ) );
	}

	/** @param array<string,mixed> $data @return Department|WP_Error */
	public function update( int $editor_id, int $id, array $data ): Department|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit departments.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->departments->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$validated = $this->validate_payload( $data, $id );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$old_head = $existing->head_user_id;
		$this->departments->update( $id, $validated );
		$updated = $this->departments->find( $id );

		$new_head = $updated?->head_user_id;
		if ( $old_head && $old_head !== $new_head ) {
			$this->maybe_remove_head_role( $old_head );
		}
		if ( $new_head ) {
			$this->sync_head_assignment( $id, $new_head );
		}

		$this->audit->log(
			'update',
			'department',
			$id,
			$existing->to_array(),
			$updated?->to_array(),
			$editor_id,
			array( 'edit_reason' => __( 'Department updated', 'etwa' ) )
		);

		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update department.', 'etwa' ) );
	}

	/** @return true|WP_Error */
	public function delete( int $editor_id, int $id ): bool|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot delete departments.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->departments->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$employee_ids = DepartmentHelper::employee_ids_in_department( $id );
		foreach ( $employee_ids as $employee_id ) {
			DepartmentHelper::set_employee_department( $employee_id, null );
		}

		if ( $existing->head_user_id ) {
			$this->maybe_remove_head_role( $existing->head_user_id );
		}

		$this->departments->delete( $id );
		$this->product_tabs->delete_for_department( $id );

		$this->audit->log(
			'delete',
			'department',
			$id,
			$existing->to_array(),
			null,
			$editor_id
		);

		return true;
	}

	/**
	 * Product Tab options for department configuration UI.
	 *
	 * @return array{items:array<int,array{id:int,name:string,is_active:bool}>}|WP_Error
	 */
	public function list_product_tab_options( int $viewer_id ): array|WP_Error {
		if ( ! $this->can_manage( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage department product access.', 'etwa' ), array( 'status' => 403 ) );
		}

		$items = array();
		foreach ( $this->clients->list_super_clients() as $client ) {
			if ( ! $client->is_super() ) {
				continue;
			}
			$items[] = array(
				'id'        => (int) $client->id,
				'name'      => $client->name,
				'is_active' => (bool) $client->is_active,
			);
		}

		return array( 'items' => $items );
	}

	/**
	 * @return array{product_tab_ids:int[]}|WP_Error
	 */
	public function get_product_tab_access( int $viewer_id, int $department_id ): array|WP_Error {
		if ( ! $this->can_manage( $viewer_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage department product access.', 'etwa' ), array( 'status' => 403 ) );
		}

		$dept = $this->departments->find( $department_id );
		if ( ! $dept ) {
			return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		return array(
			'product_tab_ids' => $this->product_tabs->list_ids_for_department( $department_id ),
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array{product_tab_ids:int[]}|WP_Error
	 */
	public function set_product_tab_access( int $editor_id, int $department_id, array $data ): array|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage department product access.', 'etwa' ), array( 'status' => 403 ) );
		}

		$dept = $this->departments->find( $department_id );
		if ( ! $dept ) {
			return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$raw = $data['product_tab_ids'] ?? $data['allowed_product_tab_ids'] ?? array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$ids = array();
		foreach ( $raw as $value ) {
			$id = (int) $value;
			if ( $id <= 0 ) {
				continue;
			}
			$client = $this->clients->find( $id );
			if ( ! $client || ! $client->is_super() || ! $client->is_active ) {
				return new WP_Error( 'invalid', __( 'Invalid Product Tab.', 'etwa' ), array( 'status' => 400 ) );
			}
			$ids[] = $id;
		}
		$ids = array_values( array_unique( $ids ) );

		$previous = $this->product_tabs->list_ids_for_department( $department_id );
		$this->product_tabs->replace_for_department( $department_id, $ids );
		$this->audit->log(
			'update',
			'department_product_tabs',
			$department_id,
			array( 'product_tab_ids' => $previous ),
			array( 'product_tab_ids' => $ids ),
			$editor_id
		);

		return array( 'product_tab_ids' => $ids );
	}

	/** @return true|WP_Error */
	public function assign_employee( int $editor_id, int $employee_id, ?int $department_id ): bool|WP_Error {
		if ( ! $this->can_manage( $editor_id ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot assign departments.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! \ETWA\Helpers\EmployeeProfileHelper::is_managed_user( $employee_id ) ) {
			return new WP_Error( 'invalid_employee', __( 'Invalid employee.', 'etwa' ), array( 'status' => 404 ) );
		}

		$old_dept_id = DepartmentHelper::get_employee_department_id( $employee_id );

		if ( $department_id ) {
			$dept = $this->departments->find( $department_id );
			if ( ! $dept ) {
				return new WP_Error( 'not_found', __( 'Department not found.', 'etwa' ), array( 'status' => 404 ) );
			}
		}

		DepartmentHelper::set_employee_department( $employee_id, $department_id );

		$this->audit->log(
			'update',
			'employee',
			$employee_id,
			array( 'department_id' => $old_dept_id ),
			array( 'department_id' => $department_id ),
			$editor_id,
			array(
				'employee_id' => $employee_id,
				'edit_reason' => __( 'Department changed', 'etwa' ),
			)
		);

		return true;
	}

	/** @return array<string,mixed> */
	private function format_department( Department $dept, bool $detailed = false ): array {
		$head = $dept->head_user_id ? get_userdata( $dept->head_user_id ) : null;
		$data = array_merge(
			$dept->to_array(),
			array(
				'head_name'       => $head ? $head->display_name : '',
				'head_email'      => $head ? $head->user_email : '',
				'employee_count'  => count( DepartmentHelper::employee_ids_in_department( $dept->id ) ),
				'status_label'    => 'active' === $dept->status ? __( 'Active', 'etwa' ) : __( 'Inactive', 'etwa' ),
			)
		);

		if ( $detailed ) {
			$employees = array();
			foreach ( DepartmentHelper::employee_ids_in_department( $dept->id ) as $uid ) {
				$user = get_userdata( $uid );
				if ( $user ) {
					$employees[] = array(
						'id'           => $uid,
						'display_name' => $user->display_name,
						'email'        => $user->user_email,
					);
				}
			}
			$data['employees'] = $employees;
		}

		return $data;
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|WP_Error */
	private function validate_payload( array $data, ?int $exclude_id = null ): array|WP_Error {
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === trim( $name ) ) {
			return new WP_Error( 'invalid_name', __( 'Department name is required.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->departments->name_exists( $name, $exclude_id ) ) {
			return new WP_Error( 'duplicate', __( 'A department with this name already exists.', 'etwa' ), array( 'status' => 409 ) );
		}

		$head_user_id = ! empty( $data['head_user_id'] ) ? absint( $data['head_user_id'] ) : null;
		if ( $head_user_id ) {
			$user = get_userdata( $head_user_id );
			if ( ! $user ) {
				return new WP_Error( 'invalid_head', __( 'Invalid department head.', 'etwa' ), array( 'status' => 400 ) );
			}
		}

		$status = sanitize_key( (string) ( $data['status'] ?? 'active' ) );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}

		return array(
			'name'                      => $name,
			'description'               => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'head_user_id'              => $head_user_id,
			'approval_enabled'          => ! empty( $data['approval_enabled'] ) ? 1 : 0,
			'can_allocate_deliverables' => ! empty( $data['can_allocate_deliverables'] ) ? 1 : 0,
			'status'                    => $status,
		);
	}

	private function sync_head_assignment( int $department_id, int $head_user_id ): void {
		DepartmentHelper::set_employee_department( $head_user_id, $department_id );
		$this->sync_head_role( $head_user_id );
	}

	private function ensure_head_is_assigned( Department $dept ): void {
		if ( ! $dept->head_user_id ) {
			return;
		}
		if ( DepartmentHelper::get_employee_department_id( $dept->head_user_id ) !== $dept->id ) {
			DepartmentHelper::set_employee_department( $dept->head_user_id, $dept->id );
		}
	}

	private function sync_head_role( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		if ( ! in_array( 'etwa_department_head', $user->roles, true ) ) {
			$user->add_role( 'etwa_department_head' );
		}
	}

	private function maybe_remove_head_role( int $user_id ): void {
		if ( $this->departments->count_by_head( $user_id ) > 0 ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'etwa_department_head', $user->roles, true ) ) {
			$user->remove_role( 'etwa_department_head' );
		}
	}
}
