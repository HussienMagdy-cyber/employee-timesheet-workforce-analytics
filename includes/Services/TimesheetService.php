<?php
/**
 * Timesheet business logic service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\CalendarHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\EmployeeProfileHelper;
use ETWA\Helpers\EntryTypeHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\TimeCalculator;
use ETWA\Helpers\TimesheetDraftHelper;
use ETWA\Models\Entry;
use ETWA\Models\Timesheet;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\OvertimeRepository;
use ETWA\Repositories\TimesheetRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimesheetService {

	public const CREATED_FROM_HOLIDAY_REQUEST = 'Approved Holiday Request';

	public function __construct(
		private TimesheetRepository $timesheets = new TimesheetRepository(),
		private EntryRepository $entries = new EntryRepository(),
		private OvertimeRepository $overtime = new OvertimeRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private TimeCalculator $calculator = new TimeCalculator(),
	) {}

	public function get_or_create_for_week( int $user_id, ?string $reference_date = null ): Timesheet {
		$week = $reference_date
			? DateHelper::get_work_week_for_date( $reference_date )
			: DateHelper::get_current_work_week();

		$existing = $this->timesheets->find_by_user_period( $user_id, $week['period_start'], $week['period_end'] );
		if ( $existing ) {
			return $existing;
		}

		$id = $this->timesheets->create(
			array(
				'user_id'              => $user_id,
				'period_start'         => $week['period_start'],
				'period_end'           => $week['period_end'],
				'status'               => 'draft',
				'total_regular_hours'  => 0,
				'total_overtime_hours' => 0,
				'is_locked'            => 0,
			)
		);

		$this->audit->log( 'create', 'timesheet', $id, null, array( 'user_id' => $user_id, 'week' => $week ) );

		return $this->timesheets->find( $id );
	}

	/** @return array{timesheet:Timesheet,entries:Entry[]} */
	public function get_dashboard_data( int $user_id, ?string $reference_date = null, ?int $viewer_id = null ): array|WP_Error {
		$timesheet = $this->get_or_create_for_week( $user_id, $reference_date );
		$viewer_id = $viewer_id ?? $user_id;
		$entries   = $this->entries->find_by_timesheet( (int) $timesheet->id );

		if ( $viewer_id !== $user_id && TimesheetDraftHelper::uses_draft_snapshot( $timesheet ) ) {
			$entries = TimesheetDraftHelper::is_visible_to_reviewers( $timesheet )
				? TimesheetDraftHelper::decode_snapshot( $timesheet )
				: array();
		}

		return array(
			'timesheet' => $this->with_display_totals( $timesheet, $entries, $viewer_id !== $user_id ),
			'entries'   => $entries,
		);
	}

	public function save_draft( int $user_id, int $timesheet_id ): Timesheet|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_EDIT_OWN ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot save draft timesheets.', 'etwa' ), array( 'status' => 403 ) );
		}

		$timesheet = $this->timesheets->find( $timesheet_id );
		if ( ! $timesheet || $timesheet->user_id !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'Cannot save this timesheet draft.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! TimesheetDraftHelper::uses_draft_snapshot( $timesheet ) ) {
			return new WP_Error( 'invalid_state', __( 'Only draft or rejected timesheets can be saved as draft.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( ! RoleManager::user_can_edit_timesheet( $user_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'locked', __( 'Timesheet is locked.', 'etwa' ), array( 'status' => 423 ) );
		}

		$entries  = $this->entries->find_by_timesheet( $timesheet_id );
		$snapshot = TimesheetDraftHelper::encode_snapshot( $entries );

		$this->timesheets->update(
			$timesheet_id,
			array(
				'draft_saved_at' => current_time( 'mysql' ),
				'draft_snapshot' => $snapshot,
			)
		);

		$this->audit->log(
			'save_draft',
			'timesheet',
			$timesheet_id,
			$timesheet->to_array(),
			array(
				'draft_saved_at' => current_time( 'mysql' ),
				'entry_count'    => count( $entries ),
			)
		);

		AnalyticsService::flush_dashboard_cache();

		return $this->timesheets->find( $timesheet_id );
	}

	public function clear_draft_visibility( int $timesheet_id ): void {
		$this->timesheets->update(
			$timesheet_id,
			array(
				'draft_saved_at' => null,
				'draft_snapshot' => null,
			)
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array{edit_reason?:string,employee_id?:int,editor_role?:string,operation?:string}|null $audit_context
	 * @return Entry|WP_Error
	 */
	public function save_entry( int $user_id, array $data, ?int $entry_id = null, ?array $audit_context = null ): Entry|WP_Error {
		$timesheet = $this->timesheets->find( (int) $data['timesheet_id'] );
		if ( ! $timesheet ) {
			return new WP_Error( 'not_found', __( 'Timesheet not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		if ( ! RoleManager::user_can_edit_timesheet( $user_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot edit this timesheet.', 'etwa' ), array( 'status' => 403 ) );
		}

		$entry_date = sanitize_text_field( (string) $data['entry_date'] );
		$start_time = $this->normalize_time( (string) $data['start_time'] );
		$end_time   = $this->normalize_time( (string) $data['end_time'] );
		$client_id  = (int) $data['client_id'];

		$validation = $this->validate_entry_data(
			$timesheet,
			$entry_date,
			$start_time,
			$end_time,
			$client_id,
			(string) ( $data['task'] ?? '' ),
			$entry_id,
			false,
			(string) ( $data['notes'] ?? '' ),
			(string) ( $data['entry_type'] ?? EntryTypeHelper::REGULAR )
		);
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$client   = $this->clients->find( $client_id );
		$is_break = $this->clients->is_break_client( $client_id, $client );
		$task     = sanitize_text_field( (string) ( $data['task'] ?? '' ) );
		if ( $is_break && '' === $task ) {
			$task = __( 'Break', 'etwa' );
		}

		$entry_type = EntryTypeHelper::resolve_entry_type(
			$start_time,
			(string) ( $data['entry_type'] ?? EntryTypeHelper::REGULAR ),
			$is_break
		);
		if ( is_wp_error( $entry_type ) ) {
			return $entry_type;
		}

		$normalized = EntryTypeHelper::normalize_stored_hours(
			$entry_type,
			$entry_date,
			$start_time,
			$end_time,
			$is_break,
			$this->calculator
		);

		$payload = array(
			'timesheet_id'     => (int) $data['timesheet_id'],
			'user_id'          => $timesheet->user_id,
			'entry_date'       => $entry_date,
			'start_time'       => $start_time,
			'end_time'         => $end_time,
			'client_id'        => $client_id,
			'task'             => $task,
			'notes'            => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'duration_minutes' => $normalized['duration_minutes'],
			'regular_hours'    => $normalized['regular_hours'],
			'overtime_hours'   => $normalized['overtime_hours'],
			'entry_type'       => $normalized['entry_type'],
		);

		if ( ! empty( $data['created_from'] ) ) {
			$payload['created_from'] = sanitize_text_field( (string) $data['created_from'] );
		}

		if ( $entry_id ) {
			$old = $this->entries->find( $entry_id );
			$this->entries->update( $entry_id, $payload );
			if ( $audit_context ) {
				$this->audit->log( 'hr_edit', 'entry', $entry_id, $old?->to_array(), $payload, $user_id, $audit_context );
			} else {
				$this->audit->log( 'update', 'entry', $entry_id, $old?->to_array(), $payload );
			}
			$id = $entry_id;
		} else {
			$id = $this->entries->create( $payload );
			if ( $audit_context ) {
				$create_context = array_merge( $audit_context, array( 'operation' => 'create' ) );
				$this->audit->log( 'hr_edit', 'entry', $id, null, $payload, $user_id, $create_context );
			} else {
				$this->audit->log( 'create', 'entry', $id, null, $payload );
			}
		}

		if ( $is_break ) {
			$this->overtime->delete_by_entry( $id );
		} else {
			( new OvertimeService() )->sync_from_entry( $id );
		}
		$this->recalculate_totals( (int) $timesheet->id, true );

		return $this->entries->find( $id );
	}

	/**
	 * Create a timesheet entry from an approved holiday work request (bypasses day-off/holiday calendar rules).
	 *
	 * @param array<string,mixed> $data
	 */
	public function create_from_holiday_request( int $user_id, array $data ): Entry|WP_Error {
		$timesheet = $this->timesheets->find( (int) $data['timesheet_id'] );
		if ( ! $timesheet ) {
			return new WP_Error( 'not_found', __( 'Timesheet not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$entry_date = sanitize_text_field( (string) $data['entry_date'] );
		$start_time = $this->normalize_time( (string) $data['start_time'] );
		$end_time   = $this->normalize_time( (string) $data['end_time'] );
		$client_id  = (int) $data['client_id'];
		$task       = sanitize_text_field( (string) ( $data['task'] ?? '' ) );

		$validation = $this->validate_entry_data(
			$timesheet,
			$entry_date,
			$start_time,
			$end_time,
			$client_id,
			$task,
			null,
			true,
			(string) ( $data['notes'] ?? '' )
		);
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$calc   = $this->calculator->calculate( $entry_date, $start_time, $end_time );
		$client = $this->clients->find( $client_id );
		$is_break = $this->clients->is_break_client( $client_id, $client );

		$payload = array(
			'timesheet_id'     => (int) $data['timesheet_id'],
			'user_id'          => $timesheet->user_id,
			'entry_date'       => $entry_date,
			'start_time'       => $start_time,
			'end_time'         => $end_time,
			'client_id'        => $client_id,
			'task'             => $task,
			'notes'            => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'duration_minutes' => $calc['duration_minutes'],
			'regular_hours'    => $is_break ? 0.0 : $calc['regular_hours'],
			'overtime_hours'   => 0.0,
			'entry_type'       => EntryTypeHelper::REGULAR,
			'created_from'     => self::CREATED_FROM_HOLIDAY_REQUEST,
		);

		$id = $this->entries->create( $payload );
		$this->audit->log(
			'create',
			'entry',
			$id,
			null,
			$payload,
			$user_id,
			array(
				'employee_id' => $timesheet->user_id,
				'edit_reason' => (string) ( $data['audit_reason'] ?? __( 'Auto-created from approved holiday work request', 'etwa' ) ),
			)
		);

		$this->recalculate_totals( (int) $timesheet->id, true );

		return $this->entries->find( $id );
	}

	public function delete_entry( int $user_id, int $entry_id, ?array $audit_context = null ): bool|WP_Error {
		$entry = $this->entries->find( $entry_id );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', __( 'Entry not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$timesheet = $this->timesheets->find( $entry->timesheet_id );
		if ( ! $timesheet || ! RoleManager::user_can_edit_timesheet( $user_id, $timesheet->user_id, $timesheet->status, $timesheet->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot delete this entry.', 'etwa' ), array( 'status' => 403 ) );
		}

		if (
			$user_id === $timesheet->user_id
			&& CalendarHelper::is_approved_vacation_day( $entry->entry_date, (int) $timesheet->user_id )
		) {
			return new WP_Error(
				'invalid_date',
				__( 'Entries cannot be deleted on approved vacation days.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$this->overtime->delete_by_entry( $entry_id );
		$this->entries->delete( $entry_id );
		if ( $audit_context ) {
			$delete_context = array_merge( $audit_context, array( 'operation' => 'delete' ) );
			$this->audit->log( 'hr_edit', 'entry', $entry_id, $entry->to_array(), null, $user_id, $delete_context );
		} else {
			$this->audit->log( 'delete', 'entry', $entry_id, $entry->to_array(), null );
		}
		$this->recalculate_totals( (int) $timesheet->id, true );
		return true;
	}

	public function submit( int $user_id, int $timesheet_id ): Timesheet|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_SUBMIT ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot submit timesheets.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( ! EmployeeProfileHelper::is_active( $user_id ) ) {
			return new WP_Error( 'inactive', __( 'Your account is inactive. You cannot submit timesheets.', 'etwa' ), array( 'status' => 403 ) );
		}

		$timesheet = $this->timesheets->find( $timesheet_id );
		if ( ! $timesheet || $timesheet->user_id !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'Cannot submit this timesheet.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( 'approved' === $timesheet->status || $timesheet->is_locked ) {
			return new WP_Error( 'locked', __( 'Timesheet is locked.', 'etwa' ), array( 'status' => 423 ) );
		}

		$entries = $this->entries->find_by_timesheet( $timesheet_id );
		if ( empty( $entries ) ) {
			return new WP_Error( 'empty', __( 'Cannot submit an empty timesheet.', 'etwa' ), array( 'status' => 400 ) );
		}

		// Persist current live entries as the draft snapshot so submit matches on-screen edits.
		$saved = $this->save_draft( $user_id, $timesheet_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$timesheet = $saved;

		$restored = $this->restore_entries_from_snapshot( $timesheet );
		if ( is_wp_error( $restored ) ) {
			return $restored;
		}

		$entries = $this->entries->find_by_timesheet( $timesheet_id );
		if ( empty( $entries ) ) {
			return new WP_Error( 'empty', __( 'Cannot submit an empty timesheet.', 'etwa' ), array( 'status' => 400 ) );
		}

		$this->timesheets->update(
			$timesheet_id,
			array(
				'status'       => 'submitted',
				'submitted_at' => current_time( 'mysql' ),
			)
		);
		$this->audit->log( 'submit', 'timesheet', $timesheet_id, $timesheet->to_array(), array( 'status' => 'submitted' ) );

		do_action( 'etwa_timesheet_submitted', $timesheet_id, $user_id );

		AnalyticsService::flush_dashboard_cache();

		return $this->timesheets->find( $timesheet_id );
	}

	public function copy_previous_week( int $user_id, ?string $reference_date = null ): Timesheet|WP_Error {
		$target_week = $reference_date
			? DateHelper::get_work_week_for_date( $reference_date )
			: DateHelper::get_current_work_week();
		$prev_week = DateHelper::get_adjacent_work_week( $target_week['period_start'], -1 );
		$prev      = $this->timesheets->find_by_user_period( $user_id, $prev_week['period_start'], $prev_week['period_end'] );
		if ( ! $prev ) {
			return new WP_Error( 'no_previous', __( 'No previous week timesheet found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$current = $this->get_or_create_for_week( $user_id, $reference_date );
		if ( ! RoleManager::user_can_edit_timesheet( $user_id, $current->user_id, $current->status, $current->is_locked ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot copy to locked timesheet.', 'etwa' ), array( 'status' => 403 ) );
		}

		$current_entries = $this->entries->find_by_timesheet( (int) $current->id );
		if ( ! empty( $current_entries ) ) {
			return new WP_Error(
				'not_empty',
				__( 'This week already has entries. Remove them before copying the previous week.', 'etwa' ),
				array( 'status' => 409 )
			);
		}

		$prev_entries = $this->entries->find_by_timesheet( (int) $prev->id );
		$date_map     = $this->build_date_map( $prev_week, $target_week );

		$payloads = $this->build_copy_payloads( $current, $prev_entries, $date_map );
		if ( is_wp_error( $payloads ) ) {
			return $payloads;
		}

		if ( empty( $payloads ) ) {
			return new WP_Error(
				'nothing_to_copy',
				__( 'No entries to copy from the previous week.', 'etwa' ),
				array( 'status' => 404 )
			);
		}

		$entry_ids = $this->entries->create_many( $payloads );
		if ( ! $entry_ids ) {
			return new WP_Error(
				'copy_failed',
				__( 'Could not copy entries. Please try again.', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$this->recalculate_totals( (int) $current->id, true );

		$this->audit->log(
			'copy_week',
			'timesheet',
			(int) $current->id,
			null,
			array(
				'from'         => $prev->id,
				'entries'      => count( $entry_ids ),
				'entry_ids'    => $entry_ids,
			)
		);

		return $this->timesheets->find( (int) $current->id );
	}

	/**
	 * @param Entry[] $source_entries
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function build_copy_payloads( Timesheet $timesheet, array $source_entries, array $date_map ): array|WP_Error {
		/** @var array<string,array<int,array{start:string,end:string}>> $day_slots */
		$day_slots = array();
		$payloads  = array();
		/** @var array<int,\ETWA\Models\Client|null> $client_cache */
		$client_cache = array();

		foreach ( $source_entries as $entry ) {
			if ( ! isset( $date_map[ $entry->entry_date ] ) ) {
				continue;
			}

			$new_date   = $date_map[ $entry->entry_date ];
			$start_time = $this->normalize_time( $entry->start_time );
			$end_time   = $this->normalize_time( $entry->end_time );
			$client_id  = $entry->client_id;

			$validation = $this->validate_entry_data(
				$timesheet,
				$new_date,
				$start_time,
				$end_time,
				$client_id,
				$entry->task,
				null,
				false,
				(string) ( $entry->notes ?? '' ),
				$entry->entry_type
			);
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			if ( $this->has_in_memory_overlap( $day_slots, $new_date, $start_time, $end_time ) ) {
				return new WP_Error(
					'overlap',
					__( 'Copied entries would overlap on the same day.', 'etwa' ),
					array( 'status' => 409 )
				);
			}

			if ( ! array_key_exists( $client_id, $client_cache ) ) {
				$client_cache[ $client_id ] = $this->clients->find( $client_id );
			}
			$client   = $client_cache[ $client_id ];
			$is_break = $this->clients->is_break_client( $client_id, $client );
			$calc     = $this->calculator->calculate( $new_date, $start_time, $end_time );
			$task     = sanitize_text_field( $entry->task );
			if ( $is_break && '' === $task ) {
				$task = __( 'Break', 'etwa' );
			}

			$entry_type = $entry->entry_type;
			$hours      = EntryTypeHelper::split_hours( $entry_type, (float) $calc['regular_hours'], $is_break );

			$payloads[] = array(
				'timesheet_id'     => (int) $timesheet->id,
				'user_id'          => $timesheet->user_id,
				'entry_date'       => $new_date,
				'start_time'       => $start_time,
				'end_time'         => $end_time,
				'client_id'        => $client_id,
				'task'             => $task,
				'notes'            => sanitize_textarea_field( (string) $entry->notes ),
				'duration_minutes' => $calc['duration_minutes'],
				'regular_hours'    => $hours['regular'],
				'overtime_hours'   => $hours['overtime'],
				'entry_type'       => $entry_type,
			);

			$day_slots[ $new_date ][] = array(
				'start' => $start_time,
				'end'   => $end_time,
			);
		}

		return $payloads;
	}

	/**
	 * @param array<string,array<int,array{start:string,end:string}>> $day_slots
	 */
	private function has_in_memory_overlap( array $day_slots, string $entry_date, string $start_time, string $end_time ): bool {
		foreach ( $day_slots[ $entry_date ] ?? array() as $slot ) {
			if ( $slot['start'] < $end_time && $slot['end'] > $start_time ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<string,string> */
	private function build_date_map( array $from_week, array $to_week ): array {
		$from_dates = CalendarHelper::get_calendar_dates( $from_week['period_start'] );
		$to_dates   = CalendarHelper::get_calendar_dates( $to_week['period_start'] );
		$map        = array();
		foreach ( $from_dates as $i => $from_date ) {
			if ( isset( $to_dates[ $i ] ) ) {
				$map[ $from_date ] = $to_dates[ $i ];
			}
		}
		return $map;
	}

	private function recalculate_totals( int $timesheet_id, bool $flush_cache = false ): void {
		$sums = $this->entries->sum_hours_by_timesheet( $timesheet_id );
		$this->timesheets->update(
			$timesheet_id,
			array(
				'total_regular_hours'  => $sums['regular'],
				'total_overtime_hours' => $sums['overtime'],
			)
		);
		if ( $flush_cache ) {
			AnalyticsService::flush_dashboard_cache();
			ClientAnalyticsService::flush_cache();
		}
	}

	/** @param Entry[] $entries */
	private function with_display_totals( Timesheet $timesheet, array $entries, bool $reviewer_view ): Timesheet {
		if ( ! $reviewer_view || ! TimesheetDraftHelper::uses_draft_snapshot( $timesheet ) ) {
			return $timesheet;
		}

		$regular  = 0.0;
		$overtime = 0.0;
		foreach ( $entries as $entry ) {
			$regular  += $entry->regular_hours;
			$overtime += $entry->overtime_hours;
		}

		return Timesheet::from_row(
			array_merge(
				$timesheet->to_array(),
				array(
					'draft_snapshot'       => $timesheet->draft_snapshot,
					'total_regular_hours'  => $regular + $overtime,
					'total_overtime_hours' => 0,
				)
			)
		);
	}

	private function restore_entries_from_snapshot( Timesheet $timesheet ): bool|WP_Error {
		$snapshot_entries = TimesheetDraftHelper::decode_snapshot( $timesheet );
		if ( ! $snapshot_entries ) {
			return new WP_Error( 'empty_draft', __( 'Saved draft is empty.', 'etwa' ), array( 'status' => 400 ) );
		}

		$live_entries = $this->entries->find_by_timesheet( (int) $timesheet->id );
		foreach ( $live_entries as $entry ) {
			$this->overtime->delete_by_entry( (int) $entry->id );
		}

		$this->entries->delete_by_timesheet( (int) $timesheet->id );

		$payloads = array();
		foreach ( $snapshot_entries as $entry ) {
			$entry_type = $entry->entry_type;
			if ( EntryTypeHelper::REGULAR === $entry_type && $entry->overtime_hours > 0 ) {
				$entry_type = EntryTypeHelper::OVERTIME;
			}

			$entry_date = $entry->entry_date;
			$start_time = $this->normalize_time( $entry->start_time );
			$end_time   = $this->normalize_time( $entry->end_time );
			$client     = $this->clients->find( $entry->client_id );
			$is_break   = $this->clients->is_break_client( $entry->client_id, $client );
			$normalized = EntryTypeHelper::normalize_stored_hours(
				$entry_type,
				$entry_date,
				$start_time,
				$end_time,
				$is_break,
				$this->calculator
			);

			$payloads[] = array(
				'timesheet_id'     => (int) $timesheet->id,
				'user_id'          => $timesheet->user_id,
				'entry_date'       => $entry_date,
				'start_time'       => $start_time,
				'end_time'         => $end_time,
				'client_id'        => $entry->client_id,
				'task'             => $entry->task,
				'notes'            => (string) ( $entry->notes ?? '' ),
				'duration_minutes' => $normalized['duration_minutes'],
				'regular_hours'    => $normalized['regular_hours'],
				'overtime_hours'   => $normalized['overtime_hours'],
				'entry_type'       => $normalized['entry_type'],
				'created_from'     => $entry->created_from,
			);
		}

		$ids = $this->entries->create_many( $payloads );
		if ( ! $ids ) {
			return new WP_Error( 'restore_failed', __( 'Could not restore the saved draft for submission.', 'etwa' ), array( 'status' => 500 ) );
		}

		$overtime_service = new OvertimeService();
		foreach ( $ids as $entry_id ) {
			$overtime_service->sync_from_entry( (int) $entry_id );
		}

		$this->recalculate_totals( (int) $timesheet->id, true );

		return true;
	}

	private function normalize_time( string $time ): string {
		$time = sanitize_text_field( $time );
		if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			return $time;
		}
		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return $time . ':00';
		}
		return $time;
	}

	private function validate_entry_data(
		Timesheet $timesheet,
		string $entry_date,
		string $start_time,
		string $end_time,
		int $client_id,
		string $task,
		?int $entry_id = null,
		bool $skip_calendar_check = false,
		string $notes = '',
		string $entry_type = EntryTypeHelper::REGULAR
	): bool|WP_Error {
		$calendar = CalendarHelper::get_calendar_dates( $timesheet->period_start );
		if ( ! in_array( $entry_date, $calendar, true ) ) {
			return new WP_Error(
				'invalid_date',
				__( 'Entry date must fall within the timesheet work week.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $skip_calendar_check && ! CalendarHelper::can_employee_log_entry( $entry_date, (int) $timesheet->user_id ) ) {
			if ( CalendarHelper::is_approved_vacation_day( $entry_date, (int) $timesheet->user_id ) ) {
				return new WP_Error(
					'invalid_date',
					__( 'Entries cannot be logged on approved vacation days.', 'etwa' ),
					array( 'status' => 400 )
				);
			}

			$status  = CalendarHelper::get_day_status( $entry_date );
			$message = CalendarHelper::STATUS_HOLIDAY === $status
				? __( 'Entries cannot be logged on holidays.', 'etwa' )
				: __( 'Entries cannot be logged on days off.', 'etwa' );
			return new WP_Error(
				'invalid_date',
				$message,
				array( 'status' => 400 )
			);
		}

		if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $start_time ) || ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $end_time ) ) {
			return new WP_Error(
				'invalid_time',
				__( 'Start and end times must be in HH:MM format.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$start_dt = DateHelper::parse_local_datetime( $entry_date, $start_time );
		$end_dt   = DateHelper::parse_local_datetime( $entry_date, $end_time );
		if ( ! $start_dt || ! $end_dt || $end_dt <= $start_dt ) {
			return new WP_Error(
				'invalid_time',
				__( 'End time must be after start time.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$client = $this->clients->find( $client_id );
		if ( ! $client || ! $client->is_active ) {
			return new WP_Error(
				'invalid_client',
				__( 'Please select a valid active client.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$is_break       = $this->clients->is_break_client( $client_id, $client );
		$is_all_clients = $this->clients->is_all_clients_client( $client_id, $client );

		if ( ! $is_break && '' === trim( $task ) ) {
			return new WP_Error(
				'missing_task',
				__( 'Task description is required for work entries.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( $is_all_clients && '' === trim( $notes ) ) {
			return new WP_Error(
				'missing_notes',
				__( 'Please describe the work performed in detail in Notes because this entry applies to Moderation.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $is_break && EntryTypeHelper::is_after_work_hours( $start_time ) ) {
			$resolved = EntryTypeHelper::resolve_entry_type( $start_time, $entry_type, false );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
		}

		if ( $this->entries->has_overlap( (int) $timesheet->id, $entry_date, $start_time, $end_time, $entry_id ) ) {
			return new WP_Error(
				'overlap',
				__( 'This entry overlaps with an existing entry on the same day.', 'etwa' ),
				array( 'status' => 409 )
			);
		}

		return true;
	}
}
