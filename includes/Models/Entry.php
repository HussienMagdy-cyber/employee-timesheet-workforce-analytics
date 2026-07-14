<?php
/**
 * Time entry model.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Models;

use ETWA\Helpers\DateHelper;
use ETWA\Helpers\EntryTypeHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Entry {

	public function __construct(
		public readonly ?int $id,
		public readonly int $timesheet_id,
		public readonly int $user_id,
		public readonly string $entry_date,
		public readonly string $start_time,
		public readonly string $end_time,
		public readonly int $client_id,
		public readonly string $task,
		public readonly ?string $notes,
		public readonly int $duration_minutes,
		public readonly float $regular_hours,
		public readonly float $overtime_hours,
		public readonly string $entry_type,
		public readonly ?string $created_from,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly ?string $client_name = null,
	) {}

	/** @param object|array<string,mixed> $row */
	public static function from_row( object|array $row ): self {
		$r = (array) $row;
		return new self(
			isset( $r['id'] ) ? (int) $r['id'] : null,
			(int) $r['timesheet_id'],
			(int) $r['user_id'],
			(string) $r['entry_date'],
			substr( (string) $r['start_time'], 0, 5 ),
			substr( (string) $r['end_time'], 0, 5 ),
			(int) $r['client_id'],
			(string) ( $r['task'] ?? '' ),
			$r['notes'] ?? null,
			(int) $r['duration_minutes'],
			(float) $r['regular_hours'],
			(float) $r['overtime_hours'],
			self::normalize_entry_type( (string) ( $r['entry_type'] ?? EntryTypeHelper::REGULAR ) ),
			isset( $r['created_from'] ) ? (string) $r['created_from'] : null,
			(string) $r['created_at'],
			(string) $r['updated_at'],
			$r['client_name'] ?? null,
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return array(
			'id'               => $this->id,
			'timesheet_id'     => $this->timesheet_id,
			'user_id'          => $this->user_id,
			'entry_date'       => $this->entry_date,
			'start_time'       => $this->start_time,
			'end_time'         => $this->end_time,
			'client_id'        => $this->client_id,
			'client_name'      => $this->client_name,
			'task'             => $this->task,
			'notes'            => $this->notes,
			'duration_minutes' => $this->duration_minutes,
			'regular_hours'    => $this->regular_hours,
			'overtime_hours'   => $this->overtime_hours,
			'entry_type'       => $this->entry_type,
			'entry_type_label' => EntryTypeHelper::label( $this->entry_type ),
			'created_from'     => $this->created_from,
			'created_at'       => $this->created_at,
			'updated_at'       => $this->updated_at,
		);
	}

	/** @return array<string,mixed> */
	public function to_api_array(): array {
		$data = $this->to_array();

		$data['start_time_label'] = DateHelper::format_time( $this->start_time );
		$data['end_time_label']   = DateHelper::format_time( $this->end_time );
		$data['time_range_label'] = DateHelper::format_time_range( $this->start_time, $this->end_time );

		return $data;
	}

	private static function normalize_entry_type( string $type ): string {
		$type = sanitize_key( $type );
		return in_array( $type, EntryTypeHelper::allowed(), true ) ? $type : EntryTypeHelper::REGULAR;
	}
}
