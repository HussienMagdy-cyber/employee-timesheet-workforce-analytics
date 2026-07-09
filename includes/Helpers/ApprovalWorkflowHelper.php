<?php
/**
 * Hierarchical approval workflow constants and labels.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ApprovalWorkflowHelper {

	public const STAGE_PENDING_DEPARTMENT = 'pending_department';
	public const STAGE_PENDING_HR         = 'pending_hr';
	public const STAGE_APPROVED           = 'approved';
	public const STAGE_REJECTED_DEPT      = 'rejected_department';
	public const STAGE_REJECTED_HR        = 'rejected_hr';
	public const STAGE_CANCELLED          = 'cancelled';

	public const SLOT_DEPARTMENT = 'department';
	public const SLOT_HR         = 'hr';

	/** @return string[] */
	public static function valid_stages(): array {
		return array(
			self::STAGE_PENDING_DEPARTMENT,
			self::STAGE_PENDING_HR,
			self::STAGE_APPROVED,
			self::STAGE_REJECTED_DEPT,
			self::STAGE_REJECTED_HR,
			self::STAGE_CANCELLED,
		);
	}

	public static function stage_label( string $stage ): string {
		return match ( $stage ) {
			self::STAGE_PENDING_DEPARTMENT => __( 'Pending Department Approval', 'etwa' ),
			self::STAGE_PENDING_HR         => __( 'Pending HR Approval', 'etwa' ),
			self::STAGE_APPROVED           => __( 'Approved', 'etwa' ),
			self::STAGE_REJECTED_DEPT      => __( 'Rejected by Department', 'etwa' ),
			self::STAGE_REJECTED_HR        => __( 'Rejected by HR', 'etwa' ),
			self::STAGE_CANCELLED          => __( 'Cancelled', 'etwa' ),
			default                        => ucfirst( str_replace( '_', ' ', $stage ) ),
		};
	}

	public static function dept_status_label( ?int $dept_approved_by, string $workflow_status ): string {
		if ( self::STAGE_REJECTED_DEPT === $workflow_status ) {
			return __( 'Rejected', 'etwa' );
		}
		if ( $dept_approved_by ) {
			return __( 'Approved by Department', 'etwa' );
		}
		if ( self::STAGE_PENDING_DEPARTMENT === $workflow_status ) {
			return __( 'Pending', 'etwa' );
		}
		if ( in_array( $workflow_status, array( self::STAGE_PENDING_HR, self::STAGE_APPROVED, self::STAGE_REJECTED_HR ), true ) ) {
			return __( 'Approved by Department', 'etwa' );
		}
		return __( 'N/A', 'etwa' );
	}

	public static function hr_status_label( ?int $hr_approved_by, string $status, string $workflow_status ): string {
		if ( self::STAGE_REJECTED_HR === $workflow_status || ( 'rejected' === $status && self::STAGE_REJECTED_HR === $workflow_status ) ) {
			return __( 'Rejected', 'etwa' );
		}
		if ( 'approved' === $status || $hr_approved_by ) {
			return __( 'Approved', 'etwa' );
		}
		if ( self::STAGE_PENDING_HR === $workflow_status ) {
			return __( 'Pending', 'etwa' );
		}
		if ( self::STAGE_PENDING_DEPARTMENT === $workflow_status ) {
			return __( 'Waiting', 'etwa' );
		}
		return __( 'N/A', 'etwa' );
	}
}
