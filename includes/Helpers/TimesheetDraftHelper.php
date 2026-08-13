<?php
/**
 * Timesheet draft snapshot helpers for reviewer visibility.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Helpers\EntryTypeHelper;
use ETWA\Models\Entry;
use ETWA\Models\Timesheet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TimesheetDraftHelper {

	public static function uses_draft_snapshot( Timesheet $timesheet ): bool {
		return in_array( $timesheet->status, array( 'draft', 'rejected' ), true );
	}

	public static function is_visible_to_reviewers( Timesheet $timesheet ): bool {
		if ( ! self::uses_draft_snapshot( $timesheet ) ) {
			return true;
		}

		return null !== $timesheet->draft_saved_at && '' !== trim( (string) $timesheet->draft_snapshot );
	}

	/** @param Entry[] $entries */
	public static function encode_snapshot( array $entries ): string {
		$rows = array_map(
			static fn( Entry $entry ) => self::normalize_entry( $entry ),
			$entries
		);

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return array( $a['entry_date'], $a['start_time'], $a['client_id'], $a['task'] )
					<=> array( $b['entry_date'], $b['start_time'], $b['client_id'], $b['task'] );
			}
		);

		return (string) wp_json_encode( $rows );
	}

	/** @return Entry[] */
	public static function decode_snapshot( Timesheet $timesheet, ?string $snapshot = null ): array {
		$json = null !== $snapshot ? $snapshot : (string) ( $timesheet->draft_snapshot ?? '' );
		if ( '' === trim( $json ) ) {
			return array();
		}

		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$entries = array();
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$entries[] = Entry::from_row(
				array_merge(
					$row,
					array(
						'id'           => -1 * ( $index + 1 ),
						'timesheet_id' => (int) $timesheet->id,
						'user_id'      => $timesheet->user_id,
						'created_at'   => $timesheet->draft_saved_at ?? current_time( 'mysql' ),
						'updated_at'   => $timesheet->draft_saved_at ?? current_time( 'mysql' ),
					)
				)
			);
		}

		return $entries;
	}

	/** @param Entry[] $entries */
	public static function has_unsaved_changes( Timesheet $timesheet, array $entries ): bool {
		if ( ! self::uses_draft_snapshot( $timesheet ) ) {
			return false;
		}

		if ( null === $timesheet->draft_saved_at || '' === trim( (string) $timesheet->draft_snapshot ) ) {
			return ! empty( $entries );
		}

		return self::encode_snapshot( $entries ) !== self::normalize_snapshot_json( (string) $timesheet->draft_snapshot );
	}

	/** @param Entry[] $entries */
	public static function draft_sync_state( Timesheet $timesheet, array $entries ): string {
		if ( ! self::uses_draft_snapshot( $timesheet ) ) {
			return 'submitted';
		}

		if ( self::has_unsaved_changes( $timesheet, $entries ) ) {
			return 'unsaved';
		}

		return null !== $timesheet->draft_saved_at ? 'saved' : 'never_saved';
	}

	public static function draft_sync_label( string $state ): string {
		return match ( $state ) {
			'saved'       => __( 'Draft Saved', 'etwa' ),
			'unsaved'     => __( 'Unsaved changes — not visible to HR yet', 'etwa' ),
			'never_saved' => __( 'Not saved for review yet', 'etwa' ),
			default       => '',
		};
	}

	public static function reviewer_snapshot_label( ?string $draft_saved_at ): string {
		if ( ! $draft_saved_at ) {
			return '';
		}

		return sprintf(
			/* translators: %s: formatted date/time */
			__( 'Last saved %s', 'etwa' ),
			DateHelper::format_datetime( $draft_saved_at )
		);
	}

	private static function normalize_snapshot_json( string $json ): string {
		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) {
			return '[]';
		}

		$normalized = array_map(
			static fn( $row ) => is_array( $row ) ? self::normalize_row( $row ) : array(),
			$rows
		);

		usort(
			$normalized,
			static function ( array $a, array $b ): int {
				return array( $a['entry_date'] ?? '', $a['start_time'] ?? '', $a['client_id'] ?? 0, $a['task'] ?? '' )
					<=> array( $b['entry_date'] ?? '', $b['start_time'] ?? '', $b['client_id'] ?? 0, $b['task'] ?? '' );
			}
		);

		return (string) wp_json_encode( $normalized );
	}

	private static function normalize_entry( Entry $entry ): array {
		return self::normalize_row(
			array(
				'entry_date'       => $entry->entry_date,
				'start_time'       => self::normalize_time_value( $entry->start_time ),
				'end_time'         => self::normalize_time_value( $entry->end_time ),
				'client_id'        => $entry->client_id,
				'project_id'       => $entry->project_id,
				'task'             => $entry->task,
				'notes'            => (string) ( $entry->notes ?? '' ),
				'duration_minutes' => $entry->duration_minutes,
				'regular_hours'    => $entry->regular_hours,
				'overtime_hours'   => $entry->overtime_hours,
				'entry_type'       => $entry->entry_type,
				'created_from'     => $entry->created_from,
			)
		);
	}

	/** @param array<string,mixed> $row */
	private static function normalize_row( array $row ): array {
		$entry_type = sanitize_key( (string) ( $row['entry_type'] ?? EntryTypeHelper::REGULAR ) );
		if ( ! in_array( $entry_type, EntryTypeHelper::allowed(), true ) ) {
			$entry_type = EntryTypeHelper::REGULAR;
		}

		return array(
			'entry_date'       => (string) ( $row['entry_date'] ?? '' ),
			'start_time'       => self::normalize_time_value( (string) ( $row['start_time'] ?? '' ) ),
			'end_time'         => self::normalize_time_value( (string) ( $row['end_time'] ?? '' ) ),
			'client_id'        => (int) ( $row['client_id'] ?? 0 ),
			'project_id'       => ! empty( $row['project_id'] ) ? (int) $row['project_id'] : null,
			'task'             => (string) ( $row['task'] ?? '' ),
			'notes'            => (string) ( $row['notes'] ?? '' ),
			'duration_minutes' => (int) ( $row['duration_minutes'] ?? 0 ),
			'regular_hours'    => (float) ( $row['regular_hours'] ?? 0 ),
			'overtime_hours'   => (float) ( $row['overtime_hours'] ?? 0 ),
			'entry_type'       => $entry_type,
			'created_from'     => isset( $row['created_from'] ) && '' !== (string) $row['created_from']
				? (string) $row['created_from']
				: null,
		);
	}

	private static function normalize_time_value( string $time ): string {
		$time = trim( $time );
		if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			return $time . ':00';
		}
		if ( preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			return $time;
		}

		return $time;
	}
}
