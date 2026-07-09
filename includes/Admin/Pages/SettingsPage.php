<?php
/**
 * Settings page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	public function render(): void {
		$settings = ( new SettingsRepository() )->all();
		$audit    = ( new AuditLogRepository() )->recent( 30 );

		include ETWA_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
