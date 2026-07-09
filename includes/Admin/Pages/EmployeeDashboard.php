<?php
/**
 * Employee dashboard page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\TimesheetViewData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeDashboard {

	public function render(): void {
		$ref_date = null;
		if ( ! empty( $_GET['etwa_week'] ) ) {
			$ref_date = sanitize_text_field( wp_unslash( (string) $_GET['etwa_week'] ) );
		} elseif ( ! empty( $_GET['date'] ) ) {
			$ref_date = sanitize_text_field( wp_unslash( (string) $_GET['date'] ) );
		}

		$view = TimesheetViewData::prepare( get_current_user_id(), $ref_date, 'admin' );
		include ETWA_PLUGIN_DIR . 'templates/admin/employee-dashboard.php';
	}
}
