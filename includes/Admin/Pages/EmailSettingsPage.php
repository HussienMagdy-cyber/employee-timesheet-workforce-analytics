<?php
/**
 * Email Settings page controller.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Admin\Pages;

use ETWA\Repositories\EmailSettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailSettingsPage {

	public function render(): void {
		$settings     = ( new EmailSettingsRepository() )->all();
		$placeholders = EmailSettingsRepository::placeholder_keys();
		$logo_url     = '';

		if ( ! empty( $settings['logo_id'] ) ) {
			$logo_url = (string) wp_get_attachment_image_url( absint( $settings['logo_id'] ), 'medium' );
		}

		include ETWA_PLUGIN_DIR . 'templates/admin/email-settings.php';
	}
}
