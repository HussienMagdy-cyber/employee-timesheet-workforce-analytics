<?php
/**
 * HR/Owner timesheet editing with mandatory audit reasons.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\RoleManager;
use ETWA\Models\Entry;
use ETWA\Models\Timesheet;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\TimesheetRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HrTimesheetEditService {

	private const MIN_REASON_LENGTH = 3;

	private TimesheetService $timesheets;
	private TimesheetRepository $timesheet_repo;
	private AuditLogRepository $audit;

	public function __construct() {
		$this->timesheets     = new TimesheetService();
		$this->timesheet_repo = new TimesheetRepository();
		$this->audit          = new AuditLogRepository();
	}

	public function can_hr_edit( int $editor_id, int $employee_id, string $status, bool $is_locked ): bool {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) || ! user_can( $editor_id, RoleManager::CAP_APPROVE_TS ) ) {
			return false;
		}

		return RoleManager::user_can_edit_timesheet( $editor_id, $employee_id, $status, $is_locked );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function save_entry( int $editor_id, array $data, ?int $entry_id, string $edit_reason ): Entry|WP_Error {
		$reason = $this->validate_reason( $edit_reason );
		if ( is_wp_error( $reason ) ) {
			return $reason;
		}

		$timesheet = $this->timesheet_repo->find( (int) $data['timesheet_id'] );
		if ( ! $timesheet ) {
			return new WP_Error( 'not_found', __( 'Timesheet not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_hr_edit( $editor_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot edit this timesheet.', 'etwa' ), array( 'status' => 403 ) );
		}

		$context = $this->build_context( $editor_id, $timesheet->user_id, $reason, $entry_id ? 'update' : 'create' );

		return $this->timesheets->save_entry( $editor_id, $data, $entry_id, $context );
	}

	public function delete_entry( int $editor_id, int $entry_id, string $edit_reason ): bool|WP_Error {
		$reason = $this->validate_reason( $edit_reason );
		if ( is_wp_error( $reason ) ) {
			return $reason;
		}

		$entry = ( new \ETWA\Repositories\EntryRepository() )->find( $entry_id );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', __( 'Entry not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$timesheet = $this->timesheet_repo->find( $entry->timesheet_id );
		if ( ! $timesheet || ! $this->can_hr_edit( $editor_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot delete this entry.', 'etwa' ), array( 'status' => 403 ) );
		}

		$context = $this->build_context( $editor_id, $timesheet->user_id, $reason, 'delete' );

		return $this->timesheets->delete_entry( $editor_id, $entry_id, $context );
	}

	public function update_status( int $editor_id, int $timesheet_id, string $status, string $edit_reason, ?string $notes = null ): Timesheet|WP_Error {
		$reason = $this->validate_reason( $edit_reason );
		if ( is_wp_error( $reason ) ) {
			return $reason;
		}

		if ( ! user_can( $editor_id, RoleManager::CAP_APPROVE_TS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot update timesheet status.', 'etwa' ), array( 'status' => 403 ) );
		}

		$timesheet = $this->timesheet_repo->find( $timesheet_id );
		if ( ! $timesheet ) {
			return new WP_Error( 'not_found', __( 'Timesheet not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! $this->can_hr_edit( $editor_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot edit this timesheet.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! in_array( $status, array( 'draft', 'submitted', 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid timesheet status.', 'etwa' ), array( 'status' => 400 ) );
		}

		$update = array(
			'status' => $status,
		);

		if ( in_array( $status, array( 'approved', 'rejected', 'submitted' ), true ) ) {
			$update['reviewed_by']  = $editor_id;
			$update['reviewed_at']  = current_time( 'mysql' );
			$update['review_notes'] = null !== $notes ? sanitize_textarea_field( $notes ) : $timesheet->review_notes;
		}

		if ( 'submitted' === $status && ! $timesheet->submitted_at ) {
			$update['submitted_at'] = current_time( 'mysql' );
		}

		if ( 'draft' === $status ) {
			$update['is_locked'] = 0;
		} else {
			$update['is_locked'] = 'approved' === $status ? 1 : 0;
		}

		$this->timesheet_repo->update( $timesheet_id, $update );

		$context = $this->build_context( $editor_id, $timesheet->user_id, $reason, 'status' );
		$this->audit->log(
			'hr_edit',
			'timesheet',
			$timesheet_id,
			$timesheet->to_array(),
			array_merge( $update, array( 'status' => $status ) ),
			$editor_id,
			$context
		);

		do_action( 'etwa_timesheet_hr_edited', $timesheet_id, $status, $editor_id );

		return $this->timesheet_repo->find( $timesheet_id );
	}

	private function validate_reason( string $edit_reason ): string|WP_Error {
		$reason = sanitize_textarea_field( trim( $edit_reason ) );
		if ( strlen( $reason ) < self::MIN_REASON_LENGTH ) {
			return new WP_Error(
				'reason_required',
				__( 'An audit reason is required (minimum 3 characters).', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		return $reason;
	}

	/** @return array{edit_reason:string,employee_id:int,editor_role:string,operation:string} */
	private function build_context( int $editor_id, int $employee_id, string $reason, string $operation ): array {
		return array(
			'edit_reason'  => $reason,
			'employee_id'  => $employee_id,
			'editor_role'  => PortalNav::role_label_for_user( $editor_id ),
			'operation'    => $operation,
		);
	}
}
