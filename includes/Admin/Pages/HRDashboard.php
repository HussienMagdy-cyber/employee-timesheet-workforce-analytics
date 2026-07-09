<?php
/**
 * HR dashboard page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\HrDashboardData;
use ETWA\Helpers\HrReviewLinks;
use ETWA\Helpers\TimesheetViewData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HRDashboard {

	public function render(): void {
		$hr_base_url     = admin_url( 'admin.php?page=etwa-hr' );
		$hr_dashboard_url = $hr_base_url;
		$review_user     = ! empty( $_GET['review_user'] ) ? (int) $_GET['review_user'] : 0;

		if ( $review_user > 0 ) {
			$ref = $this->get_week_reference_date();
			if ( $ref ) {
				$this->render_week_report( $review_user, $ref, $hr_base_url, $hr_dashboard_url );
				return;
			}

			$this->render_employee_weeks( $review_user, $hr_base_url, $hr_dashboard_url );
			return;
		}

		$dashboard       = HrDashboardData::initial();
		$employees       = $dashboard['employees'];
		$counts          = $dashboard['counts'];
		$review_base_url = $hr_base_url;

		include ETWA_PLUGIN_DIR . 'templates/admin/hr-dashboard.php';
	}

	private function render_week_report( int $review_user, string $ref, string $hr_base_url, string $hr_dashboard_url ): void {
		$employee_weeks_url = HrReviewLinks::employee_weeks_url( $hr_base_url, $review_user );
		$view               = TimesheetViewData::prepare_hr_review( $review_user, get_current_user_id(), $ref, 'admin', $employee_weeks_url );
		if ( ! $view ) {
			wp_die( esc_html__( 'You do not have permission to review this timesheet.', 'etwa' ) );
		}

		echo '<div class="wrap etwa-app">';
		include ETWA_PLUGIN_DIR . 'templates/partials/hr-week-review.php';
		echo '</div>';
	}

	private function render_employee_weeks( int $review_user, string $hr_base_url, string $hr_dashboard_url ): void {
		$data = TimesheetViewData::weeks_for_employee( $review_user, get_current_user_id() );
		if ( ! $data ) {
			wp_die( esc_html__( 'Employee not found or access denied.', 'etwa' ) );
		}

		$employee          = $data['employee'];
		$week_rows         = $data['rows'];
		$current_week      = $data['current_week'];

		echo '<div class="wrap etwa-app">';
		include ETWA_PLUGIN_DIR . 'templates/partials/hr-employee-weeks.php';
		echo '</div>';
	}

	private function get_week_reference_date(): ?string {
		if ( ! empty( $_GET['etwa_week'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_GET['etwa_week'] ) );
		}
		return null;
	}
}
