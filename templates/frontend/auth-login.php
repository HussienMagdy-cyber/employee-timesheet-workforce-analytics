<?php
/**
 * Login form.
 *
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

<form id="etwa-login-form" class="etwa-auth-form" novalidate>
	<label>
		<?php esc_html_e( 'Email', 'etwa' ); ?>
		<input type="email" id="etwa-login-email" name="email" required autocomplete="username" />
	</label>
	<label>
		<?php esc_html_e( 'Password', 'etwa' ); ?>
		<span class="etwa-auth-password-field">
			<input type="password" id="etwa-login-password" name="password" required autocomplete="current-password" />
			<button
				type="button"
				class="etwa-auth-password-toggle"
				aria-controls="etwa-login-password"
				aria-pressed="false"
				data-label-show="<?php echo esc_attr__( 'Show password', 'etwa' ); ?>"
				data-label-hide="<?php echo esc_attr__( 'Hide password', 'etwa' ); ?>"
			><?php esc_html_e( 'Show', 'etwa' ); ?></button>
		</span>
	</label>
	<label class="etwa-auth-remember">
		<input type="checkbox" id="etwa-login-remember" name="remember" value="1" />
		<span><?php esc_html_e( 'Remember me', 'etwa' ); ?></span>
	</label>
	<button type="submit" class="etwa-btn etwa-btn-primary etwa-auth-submit"><?php esc_html_e( 'Log in', 'etwa' ); ?></button>
</form>

<p class="etwa-auth-footnote">
	<a href="<?php echo esc_url( AuthHelper::set_password_url() ); ?>"><?php esc_html_e( 'Forgot your password?', 'etwa' ); ?></a>
</p>
