<?php
/**
 * Set / reset password form.
 *
 * @var string|null                           $key
 * @var string|null                           $login
 * @var \WP_User|null                         $reset_user
 * @var string|null                           $reset_error
 * @var array{type:string,message:string}|null $notice
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Helpers\AuthHelper;
?>
<?php if ( $notice ) : ?>
	<div class="etwa-auth-notice etwa-auth-notice-<?php echo esc_attr( $notice['type'] ); ?>" role="status">
		<?php echo esc_html( $notice['message'] ); ?>
	</div>
<?php endif; ?>

<?php if ( $reset_user ) : ?>
	<p class="etwa-auth-lead"><?php esc_html_e( 'Choose a password for your account.', 'etwa' ); ?></p>
	<form id="etwa-reset-password-form" class="etwa-auth-form" novalidate>
		<input type="hidden" id="etwa-reset-key" value="<?php echo esc_attr( $key ); ?>" />
		<input type="hidden" id="etwa-reset-login" value="<?php echo esc_attr( $login ); ?>" />
		<label>
			<?php esc_html_e( 'New password', 'etwa' ); ?>
			<span class="etwa-auth-password-field">
				<input type="password" id="etwa-reset-password" required autocomplete="new-password" minlength="8" />
				<button
					type="button"
					class="etwa-auth-password-toggle"
					aria-controls="etwa-reset-password"
					aria-pressed="false"
					data-label-show="<?php echo esc_attr__( 'Show password', 'etwa' ); ?>"
					data-label-hide="<?php echo esc_attr__( 'Hide password', 'etwa' ); ?>"
				><?php esc_html_e( 'Show', 'etwa' ); ?></button>
			</span>
		</label>
		<label>
			<?php esc_html_e( 'Confirm password', 'etwa' ); ?>
			<span class="etwa-auth-password-field">
				<input type="password" id="etwa-reset-password-confirm" required autocomplete="new-password" minlength="8" />
				<button
					type="button"
					class="etwa-auth-password-toggle"
					aria-controls="etwa-reset-password-confirm"
					aria-pressed="false"
					data-label-show="<?php echo esc_attr__( 'Show password', 'etwa' ); ?>"
					data-label-hide="<?php echo esc_attr__( 'Hide password', 'etwa' ); ?>"
				><?php esc_html_e( 'Show', 'etwa' ); ?></button>
			</span>
		</label>
		<button type="submit" class="etwa-btn etwa-btn-primary etwa-auth-submit"><?php esc_html_e( 'Set password', 'etwa' ); ?></button>
	</form>
<?php else : ?>
	<?php if ( $reset_error ) : ?>
		<div class="etwa-auth-notice etwa-auth-notice-error" role="alert">
			<?php echo esc_html( $reset_error ); ?>
		</div>
	<?php endif; ?>

	<p class="etwa-auth-lead"><?php esc_html_e( 'Enter your email and we will send you a link to set your password.', 'etwa' ); ?></p>
	<form id="etwa-forgot-password-form" class="etwa-auth-form" novalidate>
		<label>
			<?php esc_html_e( 'Email', 'etwa' ); ?>
			<input type="email" id="etwa-forgot-email" required autocomplete="email" />
		</label>
		<button type="submit" class="etwa-btn etwa-btn-primary etwa-auth-submit"><?php esc_html_e( 'Send reset link', 'etwa' ); ?></button>
	</form>
<?php endif; ?>

<p class="etwa-auth-footnote">
	<a href="<?php echo esc_url( AuthHelper::login_url() ); ?>"><?php esc_html_e( 'Back to log in', 'etwa' ); ?></a>
</p>
