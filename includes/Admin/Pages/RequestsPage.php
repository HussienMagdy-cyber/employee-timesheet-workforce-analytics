<?php
/**
 * Admin requests center page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RequestsPage {

	public function render(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_VACATION ) && ! ( user_can( RoleManager::CAP_APPROVE_DEPT_REQUESTS ) && \ETWA\Helpers\DepartmentHelper::user_heads_department( get_current_user_id() ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context = 'admin';
		include ETWA_PLUGIN_DIR . 'templates/admin/requests-center.php';
	}
}
