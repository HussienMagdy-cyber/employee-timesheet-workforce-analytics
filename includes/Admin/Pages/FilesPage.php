<?php
/**
 * Client Files admin page.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\RoleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FilesPage {

	public function render(): void {
		if ( ! RoleManager::user_can_view_deliverables() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'etwa' ) );
		}

		$context               = 'admin';
		$can_view_analytics    = current_user_can( RoleManager::CAP_ANALYTICS );
		$can_allocate          = RoleManager::user_can_allocate_deliverables();
		$client_id             = isset( $_GET['client_id'] ) ? absint( wp_unslash( (string) $_GET['client_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		include ETWA_PLUGIN_DIR . 'templates/admin/files.php';
		echo '</div>';
	}
}
