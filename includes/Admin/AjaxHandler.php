<?php
/**
 * AJAX handlers for autosave and quick actions.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\SettingsRepository;
use ETWA\Services\PeriodLockService;
use ETWA\Services\TimesheetService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AjaxHandler {

	public function __construct() {
		add_action( 'wp_ajax_etwa_autosave_entry', array( $this, 'autosave_entry' ) );
		add_action( 'wp_ajax_etwa_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_etwa_toggle_dark_mode', array( $this, 'toggle_dark_mode' ) );
	}

	public function autosave_entry(): void {
		check_ajax_referer( 'etwa_ajax', 'nonce' );

		if ( ! current_user_can( RoleManager::CAP_EDIT_OWN ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'etwa' ) ), 403 );
		}

		$service = new TimesheetService();
		$data    = array(
			'timesheet_id' => (int) ( $_POST['timesheet_id'] ?? 0 ),
			'entry_date'   => sanitize_text_field( wp_unslash( (string) ( $_POST['entry_date'] ?? '' ) ) ),
			'start_time'   => sanitize_text_field( wp_unslash( (string) ( $_POST['start_time'] ?? '' ) ) ),
			'end_time'     => sanitize_text_field( wp_unslash( (string) ( $_POST['end_time'] ?? '' ) ) ),
			'client_id'    => (int) ( $_POST['client_id'] ?? 0 ),
			'task'         => sanitize_text_field( wp_unslash( (string) ( $_POST['task'] ?? '' ) ) ),
			'notes'        => sanitize_textarea_field( wp_unslash( (string) ( $_POST['notes'] ?? '' ) ) ),
			'entry_type'   => sanitize_key( wp_unslash( (string) ( $_POST['entry_type'] ?? '' ) ) ),
		);
		$entry_id = ! empty( $_POST['entry_id'] ) ? (int) $_POST['entry_id'] : null;

		$result = $service->save_entry( get_current_user_id(), $data, $entry_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'entry' => $result->to_array() ) );
	}

	public function save_settings(): void {
		check_ajax_referer( 'etwa_ajax', 'nonce' );

		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden', 'etwa' ) ), 403 );
		}

		$repo = new SettingsRepository();
		$keys = array(
			'work_day_start', 'work_day_end', 'time_interval_minutes',
			'available_hours_week',
			'hr_email', 'owner_email', 'email_cc_owner', 'autosave_interval',
		);

		$settings = array();
		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$settings[ $key ] = sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
			}
		}

		$settings['enable_audit_logs'] = isset( $_POST['enable_audit_logs'] ) ? '1' : '0';
		$settings['vacation_dual_approval'] = isset( $_POST['vacation_dual_approval'] ) ? '1' : '0';

		$repo->set_many( $settings );

		if ( ! empty( $_POST['lock_period_start'] ) && ! empty( $_POST['lock_period_end'] ) ) {
			$lock = new PeriodLockService();
			$lock->lock_period(
				get_current_user_id(),
				sanitize_text_field( wp_unslash( (string) $_POST['lock_period_start'] ) ),
				sanitize_text_field( wp_unslash( (string) $_POST['lock_period_end'] ) )
			);
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'etwa' ) ) );
	}

	public function toggle_dark_mode(): void {
		check_ajax_referer( 'etwa_ajax', 'nonce' );
		$pref = get_user_meta( get_current_user_id(), 'etwa_dark_mode', true );
		$new  = '1' === $pref ? '0' : '1';
		update_user_meta( get_current_user_id(), 'etwa_dark_mode', $new );
		wp_send_json_success( array( 'dark_mode' => '1' === $new ) );
	}
}
