<?php
/**
 * Email Settings admin page template.
 *
 * @var array<string,mixed> $settings
 * @var string[]            $placeholders
 * @var string              $logo_url
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Repositories\EmailSettingsRepository;

$option_key = EmailSettingsRepository::OPTION_KEY;
?>
<div class="wrap etwa-app" id="etwa-email-settings-app">
	<h1><?php esc_html_e( 'Email Settings', 'etwa' ); ?></h1>
	<p class="etwa-muted"><?php esc_html_e( 'Configure branding, templates, and signatures for all ETWA email notifications.', 'etwa' ); ?></p>

	<div class="etwa-email-settings-layout">
		<div class="etwa-email-settings-main">
			<form method="post" action="options.php" id="etwa-email-settings-form">
				<?php
				settings_fields( EmailSettingsRepository::SETTINGS_GROUP );
				?>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'General Settings', 'etwa' ); ?></h2>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'From Name', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[from_name]" value="<?php echo esc_attr( (string) ( $settings['from_name'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'From Email', 'etwa' ); ?>
							<input type="email" name="<?php echo esc_attr( $option_key ); ?>[from_email]" value="<?php echo esc_attr( (string) ( $settings['from_email'] ?? '' ) ); ?>" placeholder="no-reply@company.com" data-etwa-preview />
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Reply-To Email', 'etwa' ); ?>
							<input type="email" name="<?php echo esc_attr( $option_key ); ?>[reply_to]" value="<?php echo esc_attr( (string) ( $settings['reply_to'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
					</div>
				</div>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'Branding', 'etwa' ); ?></h2>
					<div class="etwa-email-logo-field">
						<input type="hidden" id="etwa-email-logo-id" name="<?php echo esc_attr( $option_key ); ?>[logo_id]" value="<?php echo esc_attr( (string) ( $settings['logo_id'] ?? 0 ) ); ?>" data-etwa-preview />
						<div class="etwa-email-logo-preview" id="etwa-email-logo-preview">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
							<?php else : ?>
								<span class="etwa-muted"><?php esc_html_e( 'No logo selected', 'etwa' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="etwa-email-logo-actions">
							<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-email-logo-upload"><?php esc_html_e( 'Select Logo', 'etwa' ); ?></button>
							<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-email-logo-remove" <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'etwa' ); ?></button>
						</div>
					</div>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'Logo Width (px)', 'etwa' ); ?>
							<input type="number" min="40" max="600" name="<?php echo esc_attr( $option_key ); ?>[logo_width]" value="<?php echo esc_attr( (string) ( $settings['logo_width'] ?? 180 ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Logo Height (px)', 'etwa' ); ?>
							<input type="number" min="20" max="400" name="<?php echo esc_attr( $option_key ); ?>[logo_height]" value="<?php echo esc_attr( (string) ( $settings['logo_height'] ?? 60 ) ); ?>" data-etwa-preview />
						</label>
					</div>
				</div>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'Email Template', 'etwa' ); ?></h2>
					<div class="etwa-form-grid">
						<label class="etwa-span-2">
							<?php esc_html_e( 'Greeting', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[greeting]" value="<?php echo esc_attr( (string) ( $settings['greeting'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Main Email Content', 'etwa' ); ?>
							<textarea rows="3" name="<?php echo esc_attr( $option_key ); ?>[email_body]" data-etwa-preview><?php echo esc_textarea( (string) ( $settings['email_body'] ?? '' ) ); ?></textarea>
							<span class="etwa-muted"><?php esc_html_e( 'Use {email_content} where the notification message should appear.', 'etwa' ); ?></span>
						</label>
					</div>
				</div>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'Signature', 'etwa' ); ?></h2>
					<p class="etwa-muted"><?php esc_html_e( 'The signature block appears below a divider in every ETWA email. Appreciation and closing text are rendered in a smaller muted font above the signature rows. Sender details use the reviewer when available; automated emails use the default sender values below.', 'etwa' ); ?></p>
					<div class="etwa-form-grid">
						<label class="etwa-span-2">
							<?php esc_html_e( 'Appreciation Line', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[appreciation]" value="<?php echo esc_attr( (string) ( $settings['appreciation'] ?? '' ) ); ?>" data-etwa-preview placeholder="<?php esc_attr_e( 'With Appreciation,', 'etwa' ); ?>" />
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Closing / Assistance Text', 'etwa' ); ?>
							<textarea rows="3" name="<?php echo esc_attr( $option_key ); ?>[closing_text]" data-etwa-preview><?php echo esc_textarea( (string) ( $settings['closing_text'] ?? '' ) ); ?></textarea>
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Dedication Line (fallback for signature rows)', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[signature_dedication]" value="<?php echo esc_attr( (string) ( $settings['signature_dedication'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Default Sender Name', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[default_sender_name]" value="<?php echo esc_attr( (string) ( $settings['default_sender_name'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Default Sender Role', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[default_sender_role]" value="<?php echo esc_attr( (string) ( $settings['default_sender_role'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Default Sender Email', 'etwa' ); ?>
							<input type="email" name="<?php echo esc_attr( $option_key ); ?>[default_sender_email]" value="<?php echo esc_attr( (string) ( $settings['default_sender_email'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Default Sender Phone', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[default_sender_phone]" value="<?php echo esc_attr( (string) ( $settings['default_sender_phone'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
					</div>

					<div class="etwa-signature-builder">
						<div class="etwa-signature-builder-head">
							<h3><?php esc_html_e( 'Signature Rows', 'etwa' ); ?></h3>
							<button type="button" class="button" id="etwa-signature-add-row"><?php esc_html_e( 'Add Row', 'etwa' ); ?></button>
						</div>
						<p class="etwa-muted"><?php esc_html_e( 'Configure styled text lines below the appreciation block. Use placeholders like {sender_name}, {sender_email}, {company_name}.', 'etwa' ); ?></p>
						<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[signature_rows_json]" id="etwa-signature-rows-json" value="<?php echo esc_attr( wp_json_encode( $settings['signature_rows'] ?? EmailSettingsRepository::default_signature_rows() ) ); ?>" />
						<div id="etwa-signature-rows-list" class="etwa-signature-rows-list"></div>
					</div>
				</div>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'Company Information', 'etwa' ); ?></h2>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'Company Name', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[company_name]" value="<?php echo esc_attr( (string) ( $settings['company_name'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Company Tagline', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[company_tagline]" value="<?php echo esc_attr( (string) ( $settings['company_tagline'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label>
							<?php esc_html_e( 'Office Phone', 'etwa' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[office_phone]" value="<?php echo esc_attr( (string) ( $settings['office_phone'] ?? '' ) ); ?>" data-etwa-preview />
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Office Address', 'etwa' ); ?>
							<textarea rows="3" name="<?php echo esc_attr( $option_key ); ?>[office_address]" data-etwa-preview><?php echo esc_textarea( (string) ( $settings['office_address'] ?? '' ) ); ?></textarea>
						</label>
					</div>
				</div>

				<div class="etwa-panel">
					<h2><?php esc_html_e( 'Available Placeholders', 'etwa' ); ?></h2>
					<ul class="etwa-email-placeholder-list">
						<?php foreach ( $placeholders as $key ) : ?>
							<li><code>{<?php echo esc_html( $key ); ?>}</code></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<p>
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save Settings', 'etwa' ); ?></button>
				</p>
			</form>

			<div class="etwa-panel">
				<h2><?php esc_html_e( 'Send Test Email', 'etwa' ); ?></h2>
				<div class="etwa-form-grid">
					<label class="etwa-span-2">
						<?php esc_html_e( 'Recipient Email', 'etwa' ); ?>
						<input type="email" id="etwa-test-email-recipient" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
					</label>
				</div>
				<p>
					<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-send-test-email"><?php esc_html_e( 'Send Test Email', 'etwa' ); ?></button>
				</p>
				<div id="etwa-test-email-notice" class="etwa-email-notice" hidden></div>
			</div>
		</div>

		<aside class="etwa-email-settings-preview-wrap">
			<div class="etwa-panel etwa-email-preview-panel">
				<h2><?php esc_html_e( 'Email Preview', 'etwa' ); ?></h2>
				<p class="etwa-muted"><?php esc_html_e( 'Live preview of the complete email as recipients will receive it.', 'etwa' ); ?></p>
				<div class="etwa-email-preview-frame-wrap">
					<iframe id="etwa-email-preview-frame" title="<?php esc_attr_e( 'Email preview', 'etwa' ); ?>" sandbox="allow-same-origin"></iframe>
				</div>
			</div>
		</aside>
	</div>
</div>
