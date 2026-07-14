<?php
/**
 * Overtime business logic service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\Overtime;
use ETWA\Models\Timesheet;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\OvertimeRepository;
use ETWA\Repositories\TimesheetRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OvertimeService {

	public function __construct(
		private OvertimeRepository $overtime = new OvertimeRepository(),
		private EntryRepository $entries = new EntryRepository(),
		private TimesheetRepository $timesheets = new TimesheetRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function sync_from_entry( int $entry_id ): void {
		$entry = $this->entries->find( $entry_id );
		if ( ! $entry ) {
			return;
		}

		if ( $entry->overtime_hours <= 0 ) {
			$this->overtime->delete_by_entry( $entry_id );
			return;
		}

		$existing = $this->overtime->find_by_entry( $entry_id );
		if ( $existing && abs( $existing->overtime_hours - $entry->overtime_hours ) < 0.001 ) {
			return;
		}

		$payload = array(
			'user_id'        => $entry->user_id,
			'timesheet_id'   => $entry->timesheet_id,
			'entry_id'       => $entry_id,
			'entry_date'     => $entry->entry_date,
			'overtime_hours' => $entry->overtime_hours,
			'status'         => 'pending',
			'approved_hours' => 0,
			'reviewed_by'    => null,
			'reviewed_at'    => null,
			'review_notes'   => null,
		);

		if ( $existing && 'approved' === $existing->status ) {
			$this->audit->log(
				'overtime_reset',
				'overtime',
				(int) $existing->id,
				$existing->to_array(),
				array( 'reason' => 'hours_changed', 'new_hours' => $entry->overtime_hours )
			);
		}

		$this->overtime->upsert_for_entry( $payload );
	}

	/** @param int[] $entry_ids */
	public function sync_from_entries( array $entry_ids ): void {
		foreach ( $entry_ids as $entry_id ) {
			$this->sync_from_entry( (int) $entry_id );
		}
	}

	public function review( int $reviewer_id, int $overtime_id, string $status, ?string $notes = null, ?float $approved_hours = null ): Overtime|WP_Error {
		if ( ! user_can( $reviewer_id, RoleManager::CAP_APPROVE_OT ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot review overtime.', 'etwa' ), array( 'status' => 403 ) );
		}

		$record = $this->overtime->find( $overtime_id );
		if ( ! $record ) {
			return new WP_Error( 'not_found', __( 'Overtime record not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( $status, array( 'approved', 'rejected', 'pending' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid overtime status.', 'etwa' ), array( 'status' => 400 ) );
		}

		$approved = 'approved' === $status ? ( $approved_hours ?? $record->overtime_hours ) : 0;

		$this->overtime->update(
			$overtime_id,
			array(
				'status'         => $status,
				'approved_hours' => $approved,
				'reviewed_by'    => $reviewer_id,
				'reviewed_at'    => current_time( 'mysql' ),
				'review_notes'   => $notes,
			)
		);

		$this->audit->log(
			"overtime_{$status}",
			'overtime',
			$overtime_id,
			$record->to_array(),
			array( 'status' => $status, 'approved_hours' => $approved )
		);

		do_action( 'etwa_overtime_reviewed', $overtime_id, $status, $reviewer_id );

		AnalyticsService::flush_dashboard_cache();

		return $this->overtime->find( $overtime_id );
	}

	public function review_timesheet( int $reviewer_id, int $timesheet_id, string $status, ?string $notes = null ): Timesheet|WP_Error {
		if ( ! user_can( $reviewer_id, RoleManager::CAP_APPROVE_TS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot review timesheets.', 'etwa' ), array( 'status' => 403 ) );
		}

		$timesheet = $this->timesheets->find( $timesheet_id );
		if ( ! $timesheet ) {
			return new WP_Error( 'not_found', __( 'Timesheet not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( $status, array( 'approved', 'rejected', 'submitted' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Invalid timesheet status.', 'etwa' ), array( 'status' => 400 ) );
		}

		$update = array(
			'status'       => $status,
			'reviewed_by'  => $reviewer_id,
			'reviewed_at'  => current_time( 'mysql' ),
			'review_notes' => $notes,
			'is_locked'    => 'approved' === $status ? 1 : 0,
		);

		$this->timesheets->update( $timesheet_id, $update );
		$this->audit->log( "timesheet_{$status}", 'timesheet', $timesheet_id, $timesheet->to_array(), $update );

		if ( 'rejected' === $status ) {
			( new \ETWA\Services\TimesheetService() )->clear_draft_visibility( $timesheet_id );
		}

		do_action( 'etwa_timesheet_reviewed', $timesheet_id, $status, $reviewer_id );

		AnalyticsService::flush_dashboard_cache();

		return $this->timesheets->find( $timesheet_id );
	}
}
