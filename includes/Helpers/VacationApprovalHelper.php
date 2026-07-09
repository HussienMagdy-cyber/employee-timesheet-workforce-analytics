<?php
/**
 * Vacation request approval workflow helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\VacationRequest;
use ETWA\Repositories\SettingsRepository;
use ETWA\Services\ApprovalService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationApprovalHelper {

	private static function approval(): ApprovalService {
		return new ApprovalService();
	}

	public static function is_dual_approval_enabled(): bool {
		return '1' === (string) ( new SettingsRepository() )->get( 'vacation_dual_approval', '1' );
	}

	public static function reviewer_slot( int $user_id ): string {
		if ( user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) && DepartmentHelper::user_heads_department( $user_id ) ) {
			return ApprovalWorkflowHelper::SLOT_DEPARTMENT;
		}
		if ( self::can_approve_as_hr( $user_id ) ) {
			return ApprovalWorkflowHelper::SLOT_HR;
		}
		return '';
	}

	public static function can_apply_slot( int $user_id, VacationRequest $request, string $slot ): bool {
		$slot = sanitize_key( $slot );
		if ( 'department' === $slot || 'dept' === $slot ) {
			return self::can_user_approve_department_slot( $user_id, $request );
		}
		if ( 'hr' === $slot ) {
			return self::can_user_approve_hr_slot( $user_id, $request );
		}
		return false;
	}

	public static function can_approve_as_hr( int $user_id ): bool {
		return user_can( $user_id, RoleManager::CAP_MANAGE_VACATION );
	}

	public static function can_approve_as_owner( int $user_id ): bool {
		return false;
	}

	public static function has_hr_approval( VacationRequest $request ): bool {
		return null !== $request->hr_approved_by && $request->hr_approved_by > 0;
	}

	public static function has_owner_approval( VacationRequest $request ): bool {
		return false;
	}

	public static function has_department_approval( VacationRequest $request ): bool {
		return null !== $request->dept_approved_by && $request->dept_approved_by > 0;
	}

	public static function is_fully_approved( VacationRequest $request ): bool {
		return self::approval()->is_fully_approved( $request );
	}

	public static function can_user_approve_hr_slot( int $user_id, VacationRequest $request ): bool {
		return self::approval()->can_user_approve_hr( $user_id, $request );
	}

	public static function can_user_approve_owner_slot( int $user_id, VacationRequest $request ): bool {
		return false;
	}

	public static function can_user_approve_department_slot( int $user_id, VacationRequest $request ): bool {
		return self::approval()->can_user_approve_department( $user_id, $request );
	}

	public static function can_user_reject( int $user_id, VacationRequest $request ): bool {
		return self::approval()->can_user_reject( $user_id, $request );
	}

	public static function status_label( VacationRequest $request ): string {
		return self::approval()->status_label( $request );
	}

	/** @return array{hr_approved_by_name:string,owner_approved_by_name:string,dept_approved_by_name:string} */
	public static function approver_names( VacationRequest $request ): array {
		$hr_user   = $request->hr_approved_by ? get_userdata( $request->hr_approved_by ) : null;
		$dept_user = $request->dept_approved_by ? get_userdata( $request->dept_approved_by ) : null;

		return array(
			'hr_approved_by_name'     => $hr_user ? $hr_user->display_name : '',
			'owner_approved_by_name'  => '',
			'dept_approved_by_name'   => $dept_user ? $dept_user->display_name : '',
		);
	}

	/** @return array<int,array<string,mixed>> */
	public static function timeline( VacationRequest $request ): array {
		return self::approval()->build_timeline( $request );
	}
}
