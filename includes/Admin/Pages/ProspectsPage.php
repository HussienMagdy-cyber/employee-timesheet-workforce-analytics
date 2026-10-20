<?php
/**
 * Prospects admin page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectsPage {

	public function render(): void {
		if ( ! current_user_can( RoleManager::CAP_MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context = 'admin';
		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/prospects.php';
		echo '</div>';
	}
}
