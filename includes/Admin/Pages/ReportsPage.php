<?php
/**
 * Reports & Exports admin page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportsPage {

	public function render(): void {
		if ( ! current_user_can( RoleManager::CAP_EXPORT ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context             = 'admin';
		$can_view_labor_cost = EmployeeCostHelper::can_view_labor_cost();
		$can_view_analytics  = current_user_can( RoleManager::CAP_ANALYTICS );

		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/reports-exports.php';
		echo '</div>';
	}
}
