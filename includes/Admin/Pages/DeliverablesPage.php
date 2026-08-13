<?php
/**
 * Deliverables admin page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverablesPage {

	public function render(): void {
		if ( ! RoleManager::user_can_view_deliverables() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context                   = 'admin';
		$can_manage_scopes         = current_user_can( RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES );
		$can_allocate_deliverables = RoleManager::user_can_allocate_deliverables();
		$can_manage_types          = RoleManager::user_can_manage_deliverable_types();

		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/deliverables.php';
		echo '</div>';
	}
}
