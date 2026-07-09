<?php
/**
 * Holiday and calendar configuration service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\CalendarHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Models\Holiday;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\HolidayRepository;
use ETWA\Repositories\SettingsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HolidayService {

	public function __construct(
		private HolidayRepository $holidays = new HolidayRepository(),
		private SettingsRepository $settings = new SettingsRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	public function can_manage( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return user_can( $user_id, RoleManager::CAP_VIEW_ALL );
	}

	/** @return array{items:array<int,array<string,mixed>>,total:int,weekdays:array<int,string>}|WP_Error */
	public function list_holidays( int $page = 1, int $per_page = 25, ?int $year = null ): array|WP_Error {
		if ( ! $this->can_manage() ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage holidays.', 'etwa' ), array( 'status' => 403 ) );
		}

		$page     = max( 1, $page );
		$per_page = min( 100, max( 10, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;
		$items    = $this->holidays->all( $per_page, $offset, $year );

		return array(
			'items'    => array_map( fn( Holiday $h ) => $h->to_array(), $items ),
			'total'    => $this->holidays->count( $year ),
			'page'     => $page,
			'per_page' => $per_page,
			'weekdays' => CalendarHelper::weekday_config(),
			'labels'   => CalendarHelper::weekday_labels(),
		);
	}

	/** @return array{weekdays:array<int,string>,labels:array<int,string>}|WP_Error */
	public function get_calendar_config(): array|WP_Error {
		if ( ! $this->can_manage() ) {
			return new WP_Error( 'forbidden', __( 'You cannot view calendar settings.', 'etwa' ), array( 'status' => 403 ) );
		}

		return array(
			'weekdays' => CalendarHelper::weekday_config(),
			'labels'   => CalendarHelper::weekday_labels(),
		);
	}

	/**
	 * @param array<int,string> $weekdays
	 * @return true|WP_Error
	 */
	public function save_calendar_config( int $editor_id, array $weekdays ): true|WP_Error {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage the calendar.', 'etwa' ), array( 'status' => 403 ) );
		}

		$normalized = CalendarHelper::normalize_weekday_config( $weekdays );
		$previous   = CalendarHelper::weekday_config();
		$this->settings->set( 'calendar_weekdays', wp_json_encode( $normalized ) );

		$this->audit->log(
			'calendar_update',
			'calendar',
			0,
			array( 'weekdays' => $previous ),
			array( 'weekdays' => $normalized ),
			$editor_id
		);

		return true;
	}

	/** @return Holiday|WP_Error */
	public function create( int $editor_id, string $name, string $date, bool $recurring ): Holiday|WP_Error {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage holidays.', 'etwa' ), array( 'status' => 403 ) );
		}

		$validated = $this->validate_holiday( $name, $date );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$id = $this->holidays->create(
			array(
				'name'          => $validated['name'],
				'holiday_date'  => $validated['date'],
				'is_recurring'  => $recurring ? 1 : 0,
			)
		);

		$holiday = $this->holidays->find( $id );
		$this->audit->log( 'create', 'holiday', $id, null, $holiday?->to_array(), $editor_id );

		return $holiday ?: new WP_Error( 'create_failed', __( 'Could not create holiday.', 'etwa' ) );
	}

	/** @return Holiday|WP_Error */
	public function update( int $editor_id, int $id, string $name, string $date, bool $recurring ): Holiday|WP_Error {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage holidays.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->holidays->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Holiday not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$validated = $this->validate_holiday( $name, $date );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$this->holidays->update(
			$id,
			array(
				'name'          => $validated['name'],
				'holiday_date'  => $validated['date'],
				'is_recurring'  => $recurring ? 1 : 0,
			)
		);

		$holiday = $this->holidays->find( $id );
		$this->audit->log( 'update', 'holiday', $id, $existing->to_array(), $holiday?->to_array(), $editor_id );

		return $holiday ?: new WP_Error( 'update_failed', __( 'Could not update holiday.', 'etwa' ) );
	}

	public function delete( int $editor_id, int $id ): true|WP_Error {
		if ( ! user_can( $editor_id, RoleManager::CAP_VIEW_ALL ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage holidays.', 'etwa' ), array( 'status' => 403 ) );
		}

		$existing = $this->holidays->find( $id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Holiday not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$this->holidays->delete( $id );
		$this->audit->log( 'delete', 'holiday', $id, $existing->to_array(), null, $editor_id );

		return true;
	}

	/** @return array{name:string,date:string}|WP_Error */
	private function validate_holiday( string $name, string $date ): array|WP_Error {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Holiday name is required.', 'etwa' ) );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid_date', __( 'Invalid holiday date.', 'etwa' ) );
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $date ) {
			return new WP_Error( 'invalid_date', __( 'Invalid holiday date.', 'etwa' ) );
		}

		return array(
			'name' => $name,
			'date' => $date,
		);
	}
}
