<?php
/**
 * Generic request approval workflow helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Request;
use ETWA\Services\ApprovalService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestApprovalHelper {

	private static function approval(): ApprovalService {
		return new ApprovalService();
	}

	public static function is_dual_approval_enabled(): bool {
		return VacationApprovalHelper::is_dual_approval_enabled();
	}

	public static function reviewer_slot( int $user_id ): string {
		return VacationApprovalHelper::reviewer_slot( $user_id );
	}

	public static function can_apply_slot( int $user_id, Request $request, string $slot ): bool {
		return VacationApprovalHelper::can_apply_slot( $user_id, self::as_vacation_shape( $request ), $slot );
	}

	public static function can_approve_as_hr( int $user_id ): bool {
		return VacationApprovalHelper::can_approve_as_hr( $user_id );
	}

	public static function can_approve_as_owner( int $user_id ): bool {
		return false;
	}

	public static function has_hr_approval( Request $request ): bool {
		return null !== $request->hr_approved_by && $request->hr_approved_by > 0;
	}

	public static function has_owner_approval( Request $request ): bool {
		return false;
	}

	public static function has_department_approval( Request $request ): bool {
		return null !== $request->dept_approved_by && $request->dept_approved_by > 0;
	}

	public static function is_fully_approved( Request $request ): bool {
		return self::approval()->is_fully_approved( $request );
	}

	public static function can_user_approve_hr_slot( int $user_id, Request $request ): bool {
		return self::approval()->can_user_approve_hr( $user_id, $request );
	}

	public static function can_user_approve_owner_slot( int $user_id, Request $request ): bool {
		return false;
	}

	public static function can_user_approve_department_slot( int $user_id, Request $request ): bool {
		return self::approval()->can_user_approve_department( $user_id, $request );
	}

	public static function can_user_reject( int $user_id, Request $request ): bool {
		return self::approval()->can_user_reject( $user_id, $request );
	}

	public static function status_label( Request $request ): string {
		return self::approval()->status_label( $request );
	}

	/** @return array{hr_approved_by_name:string,owner_approved_by_name:string,dept_approved_by_name:string} */
	public static function approver_names( Request $request ): array {
		$hr_user   = $request->hr_approved_by ? get_userdata( $request->hr_approved_by ) : null;
		$dept_user = $request->dept_approved_by ? get_userdata( $request->dept_approved_by ) : null;

		return array(
			'hr_approved_by_name'    => $hr_user ? $hr_user->display_name : '',
			'owner_approved_by_name' => '',
			'dept_approved_by_name'  => $dept_user ? $dept_user->display_name : '',
		);
	}

	public static function approved_by_label( Request $request ): string {
		if ( RequestHelper::STATUS_APPROVED !== $request->status ) {
			return '';
		}

		$names = self::approver_names( $request );
		$parts = array_filter( array( $names['dept_approved_by_name'], $names['hr_approved_by_name'] ) );
		if ( $parts ) {
			return implode( ', ', $parts );
		}

		if ( $request->reviewed_by ) {
			$user = get_userdata( $request->reviewed_by );
			return $user ? $user->display_name : '';
		}

		return '';
	}

	public static function approved_at_label( Request $request ): string {
		if ( RequestHelper::STATUS_APPROVED !== $request->status ) {
			return '';
		}

		if ( $request->hr_approved_at ) {
			return DateHelper::format_datetime( (string) $request->hr_approved_at );
		}
		if ( $request->dept_approved_at ) {
			return DateHelper::format_datetime( (string) $request->dept_approved_at );
		}

		return $request->reviewed_at ? DateHelper::format_datetime( (string) $request->reviewed_at ) : '';
	}

	/** @return array<int,array<string,mixed>> */
	public static function timeline( Request $request ): array {
		return self::approval()->build_timeline( $request );
	}

	private static function as_vacation_shape( Request $request ): \ETWA\Models\VacationRequest {
		return new \ETWA\Models\VacationRequest(
			$request->id,
			$request->user_id,
			0,
			'',
			'',
			0,
			'',
			$request->status,
			$request->notes,
			$request->reviewed_by,
			$request->reviewed_at,
			$request->hr_approved_by,
			$request->hr_approved_at,
			null,
			null,
			$request->dept_approved_by,
			$request->dept_approved_at,
			$request->workflow_status,
			$request->created_at,
			$request->updated_at,
		);
	}
}
