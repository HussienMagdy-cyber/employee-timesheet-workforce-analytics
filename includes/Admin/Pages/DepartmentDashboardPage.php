<?php
/**
 * Department Head dashboard page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\HrReviewLinks;
use ETWA\Helpers\TimesheetViewData;
use ETWA\Services\DepartmentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DepartmentDashboardPage {

	public function render(): void {
		$service = new DepartmentService();
		if ( ! $service->can_view_department_dashboard() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$dept_base_url = admin_url( 'admin.php?page=etwa-dept-dashboard' );
		$review_user   = ! empty( $_GET['review_user'] ) ? (int) $_GET['review_user'] : 0;

		if ( $review_user > 0 ) {
			$ref = $this->get_week_reference_date();
			if ( $ref ) {
				$this->render_week_report( $review_user, $ref, $dept_base_url );
				return;
			}

			$this->render_employee_weeks( $review_user, $dept_base_url );
			return;
		}

		$context    = 'admin';
		$portal_url = '';
		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/department-dashboard.php';
		echo '</div>';
	}

	private function render_week_report( int $review_user, string $ref, string $dept_base_url ): void {
		$employee_weeks_url = HrReviewLinks::employee_weeks_url( $dept_base_url, $review_user );
		$result             = TimesheetViewData::prepare_hr_review_result( $review_user, get_current_user_id(), $ref, 'admin', $employee_weeks_url, 'dept-dashboard' );
		echo '<div class="wrap etwa-app">';
		if ( ! $result['ok'] ) {
			$this->render_review_notice( $result, $employee_weeks_url, true );
			echo '</div>';
			return;
		}

		$view = $result['view'];
		include ETWA_PLUGIN_DIR . 'templates/partials/hr-week-review.php';
		echo '</div>';
	}

	private function render_employee_weeks( int $review_user, string $dept_base_url ): void {
		$data = TimesheetViewData::weeks_for_employee( $review_user, get_current_user_id() );
		if ( ! $data ) {
			wp_die( esc_html__( 'Employee not found or access denied.', 'etwa' ) );
		}

		$employee         = $data['employee'];
		$week_rows        = $data['rows'];
		$current_week     = $data['current_week'];
		$hr_base_url      = $dept_base_url;
		$hr_dashboard_url = $dept_base_url;
		$dept_review      = true;

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

	/** @param array{ok:false,code:string,message:string} $result */
	private function render_review_notice( array $result, string $back_url, bool $dept_review = false ): void {
		$code       = $result['code'] ?? 'forbidden';
		$type       = 'draft_not_shared' === $code ? 'info' : 'error';
		$message    = $result['message'] ?? __( 'This timesheet is not available for review yet.', 'etwa' );
		$back_label = __( 'Employee weeks', 'etwa' );
		include ETWA_PLUGIN_DIR . 'templates/partials/hr-review-notice.php';
	}
}
