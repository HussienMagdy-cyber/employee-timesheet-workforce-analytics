<?php
/**
 * Unified portal layout — SaaS-style app shell.
 *
 * @var string           $portal_content
 * @var string           $current_view
 * @var string           $portal_url
 * @var array<int,array> $nav_items
 * @var array<int,array{id:string,label:string,icon:string,items:array<int,array>}> $nav_groups
 * @var string           $page_title
 * @var string           $page_subtitle
 * @var string           $page_detail_title
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Assets\AssetRegistry;
use ETWA\Frontend\PortalNav;
use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\BrandHelper;

$user        = wp_get_current_user();
$role_label  = PortalNav::role_label_for_user( (int) $user->ID );
$initials    = strtoupper( mb_substr( $user->display_name, 0, 1 ) );
$logout_url  = wp_logout_url( add_query_arg( 'etwa_auth', 'logged_out', AuthHelper::login_url() ) );
$brand_name  = BrandHelper::name();
$brand_mark  = BrandHelper::mark_letter();
$brand_icon  = BrandHelper::icon_url( 64 );
?>
<div class="etwa-portal-layout etwa-app<?php echo ! empty( $dark_mode ) ? ' etwa-dark' : ''; ?>" id="etwa-portal-root" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-ajax-nonce="<?php echo esc_attr( wp_create_nonce( 'etwa_ajax' ) ); ?>">
	<div class="etwa-portal-overlay" id="etwa-portal-overlay" hidden aria-hidden="true"></div>

	<aside class="etwa-portal-sidebar" id="etwa-portal-sidebar" aria-label="<?php esc_attr_e( 'Portal navigation', 'etwa' ); ?>">
		<div class="etwa-portal-brand">
			<?php if ( $brand_icon !== '' ) : ?>
				<img class="etwa-portal-brand-mark etwa-portal-brand-mark--icon" src="<?php echo esc_url( $brand_icon ); ?>" alt="" width="36" height="36" />
			<?php else : ?>
				<span class="etwa-portal-brand-mark" aria-hidden="true"><?php echo esc_html( $brand_mark ); ?></span>
			<?php endif; ?>
			<div class="etwa-portal-brand-text">
				<strong><?php echo esc_html( $brand_name ); ?></strong>
				<span><?php esc_html_e( 'Workforce', 'etwa' ); ?></span>
			</div>
		</div>

		<nav class="etwa-portal-nav" aria-label="<?php esc_attr_e( 'Main', 'etwa' ); ?>">
			<ul class="etwa-portal-nav-groups">
				<?php foreach ( ( $nav_groups ?? array() ) as $group ) : ?>
					<?php
					$group_item_ids = array_column( $group['items'], 'id' );
					$group_active   = in_array( $current_view, $group_item_ids, true );
					$panel_id       = 'etwa-nav-group-' . sanitize_html_class( $group['id'] );
					?>
					<li class="etwa-portal-nav-group<?php echo $group_active ? ' is-open has-active' : ''; ?>">
						<button
							type="button"
							class="etwa-portal-nav-group-toggle"
							aria-expanded="<?php echo $group_active ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $panel_id ); ?>"
						>
							<span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
							<span class="etwa-portal-nav-group-label"><?php echo esc_html( $group['label'] ); ?></span>
							<span class="dashicons dashicons-arrow-down-alt2 etwa-portal-nav-chevron" aria-hidden="true"></span>
						</button>
						<ul class="etwa-portal-nav-group-items" id="<?php echo esc_attr( $panel_id ); ?>"<?php echo $group_active ? '' : ' hidden'; ?>>
							<?php foreach ( $group['items'] as $item ) : ?>
								<li>
									<a
										class="etwa-portal-nav-link<?php echo $current_view === $item['id'] ? ' is-active' : ''; ?>"
										href="<?php echo esc_url( PortalNav::view_url( $portal_url, $item['id'] ) ); ?>"
										<?php echo $current_view === $item['id'] ? 'aria-current="page"' : ''; ?>
									>
										<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
										<span class="etwa-portal-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
										<span class="etwa-badge etwa-portal-nav-badge" data-nav-badge="<?php echo esc_attr( $item['id'] ); ?>" hidden></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="etwa-portal-sidebar-foot">
			<div class="etwa-portal-user-card">
				<span class="etwa-portal-avatar" aria-hidden="true"><?php echo esc_html( $initials ); ?></span>
				<div class="etwa-portal-user-meta">
					<strong><?php echo esc_html( $user->display_name ); ?></strong>
					<span class="etwa-portal-role"><?php echo esc_html( $role_label ); ?></span>
				</div>
			</div>
		</div>
	</aside>

	<div class="etwa-portal-frame">
		<header class="etwa-portal-header">
			<div class="etwa-portal-header-start">
				<button type="button" class="etwa-portal-nav-toggle" id="etwa-portal-nav-toggle" aria-expanded="false" aria-controls="etwa-portal-sidebar">
					<span class="dashicons dashicons-menu" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'etwa' ); ?></span>
				</button>
				<nav class="etwa-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'etwa' ); ?>">
					<span class="etwa-breadcrumb-root"><?php echo esc_html( $brand_name ); ?></span>
					<span class="etwa-breadcrumb-sep" aria-hidden="true">/</span>
					<span class="etwa-breadcrumb-current"><?php echo esc_html( $page_title ?? '' ); ?></span>
				</nav>
			</div>
			<div class="etwa-portal-header-actions">
				<button type="button" class="etwa-btn etwa-btn-ghost etwa-dark-toggle" id="etwa-dark-toggle" aria-pressed="<?php echo ! empty( $dark_mode ) ? 'true' : 'false'; ?>">
					<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
					<span class="etwa-dark-toggle-label"><?php esc_html_e( 'Theme', 'etwa' ); ?></span>
				</button>
				<a class="etwa-btn etwa-btn-ghost etwa-portal-logout" href="<?php echo esc_url( $logout_url ); ?>">
					<?php esc_html_e( 'Log out', 'etwa' ); ?>
				</a>
			</div>
		</header>

		<div class="etwa-portal-page-head">
			<h1 class="etwa-portal-page-title"><?php echo esc_html( $page_title ?? '' ); ?></h1>
			<?php if ( ! empty( $page_detail_title ) ) : ?>
				<p class="etwa-portal-page-detail-title"><?php echo esc_html( $page_detail_title ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $page_subtitle ) ) : ?>
				<p class="etwa-portal-page-subtitle"><?php echo esc_html( $page_subtitle ); ?></p>
			<?php endif; ?>
		</div>

		<main class="etwa-portal-main" id="etwa-portal-main">
			<?php echo $portal_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</main>
	</div>
</div>
<script>
(function () {
	var root = document.getElementById('etwa-portal-root');
	if (!root) return;

	function bindThemeToggle() {
		if (root.dataset.etwaThemeBound === '1') return;
		var ajaxUrl = root.dataset.ajaxUrl;
		var ajaxNonce = root.dataset.ajaxNonce;
		if (!ajaxUrl || !ajaxNonce) return;
		root.dataset.etwaThemeBound = '1';

		root.addEventListener('click', function (event) {
			var btn = event.target.closest('.etwa-dark-toggle, #etwa-dark-toggle');
			if (!btn || !root.contains(btn)) return;
			event.preventDefault();
			var next = !root.classList.contains('etwa-dark');
			root.classList.toggle('etwa-dark', next);
			root.querySelectorAll('.etwa-app').forEach(function (el) {
				el.classList.toggle('etwa-dark', next);
			});
			btn.setAttribute('aria-pressed', next ? 'true' : 'false');
			var body = new URLSearchParams({ action: 'etwa_toggle_dark_mode', nonce: ajaxNonce });
			fetch(ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
			}).catch(function () {
				root.classList.toggle('etwa-dark', !next);
				root.querySelectorAll('.etwa-app').forEach(function (el) {
					el.classList.toggle('etwa-dark', !next);
				});
				btn.setAttribute('aria-pressed', !next ? 'true' : 'false');
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bindThemeToggle);
	} else {
		bindThemeToggle();
	}
	window.addEventListener('load', bindThemeToggle);
})();
</script>
