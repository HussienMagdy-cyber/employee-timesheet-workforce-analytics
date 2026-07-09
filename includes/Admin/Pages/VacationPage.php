<?php
/**
 * Vacation management admin page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationPage {

	public function render(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_VACATION ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context = 'admin';
		include ETWA_PLUGIN_DIR . 'templates/admin/vacation.php';
	}
}
