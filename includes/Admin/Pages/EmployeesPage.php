<?php
/**
 * Employees admin page (hourly cost management).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\EmployeeCostHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeesPage {

	public function render(): void {
		if ( ! EmployeeCostHelper::can_view_labor_cost() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context = 'admin';
		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/employees.php';
		echo '</div>';
	}
}
