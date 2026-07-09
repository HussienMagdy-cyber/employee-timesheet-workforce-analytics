<?php
/**
 * Admin audit logs page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Helpers\AuditLogViewData;
use ETWA\Helpers\WpDebugLogReader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuditLogsPage {

	public function render(): void {
		$base_url = admin_url( 'admin.php?page=etwa-logs' );

		$page        = max( 1, (int) ( $_GET['etwa_log_page'] ?? 1 ) );
		$entity_type = ! empty( $_GET['etwa_log_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_log_type'] ) ) : null;
		$log_view    = AuditLogViewData::prepare( $page, 50, $entity_type ?: null );
		$wp_debug_log = WpDebugLogReader::snapshot();
		$clear_url   = WpDebugLogReader::clear_url( $base_url );
		$show_filter = true;

		include ETWA_PLUGIN_DIR . 'templates/admin/audit-logs.php';
	}
}
