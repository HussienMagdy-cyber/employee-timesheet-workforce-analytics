<?php
/**
 * Client Files — list and client file detail.
 *
 * @var string $context admin|frontend
 * @var bool   $can_view_analytics
 * @var bool   $can_allocate
 * @var int    $client_id
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\AuthHelper;

$context            = $context ?? 'frontend';
$in_portal          = 'frontend' === $context;
$can_view_analytics = ! empty( $can_view_analytics );
$can_allocate       = ! empty( $can_allocate );
$client_id          = isset( $client_id ) ? (int) $client_id : 0;
if ( $client_id <= 0 && isset( $_GET['client_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$client_id = absint( wp_unslash( (string) $_GET['client_id'] ) );
}

$portal_base = AuthHelper::portal_url();
$mode        = $client_id > 0 ? 'file' : 'list';

$files_list_url = $in_portal
	? PortalNav::view_url( $portal_base, 'files' )
	: admin_url( 'admin.php?page=etwa-files' );
$deliverables_base = $in_portal
	? PortalNav::view_url( $portal_base, 'deliverables' )
	: admin_url( 'admin.php?page=etwa-deliverables' );
$client_analytics_base = $in_portal
	? PortalNav::view_url( $portal_base, 'client-analytics' )
	: admin_url( 'admin.php?page=etwa-client-analytics' );
?>
<div
	class="etwa-app etwa-files-page<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>"
	id="etwa-files-app"
	data-mode="<?php echo esc_attr( $mode ); ?>"
	data-client-id="<?php echo esc_attr( (string) $client_id ); ?>"
	data-can-analytics="<?php echo $can_view_analytics ? '1' : '0'; ?>"
	data-can-allocate="<?php echo $can_allocate ? '1' : '0'; ?>"
	data-files-url="<?php echo esc_url( $files_list_url ); ?>"
	data-deliverables-url="<?php echo esc_url( $deliverables_base ); ?>"
	data-client-analytics-url="<?php echo esc_url( $client_analytics_base ); ?>"
>
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero" id="etwa-files-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Deliverables', 'etwa' ); ?></p>
				<h1 id="etwa-files-title"><?php echo 'file' === $mode ? esc_html__( 'Client File', 'etwa' ) : esc_html__( 'Files', 'etwa' ); ?></h1>
				<p class="etwa-subtitle" id="etwa-files-subtitle"><?php esc_html_e( 'Client files and scopes at a glance', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div id="etwa-files-list" class="<?php echo 'file' === $mode ? 'etwa-hidden' : ''; ?>">
		<div class="etwa-toolbar etwa-analytics-filter">
			<label class="etwa-search-field">
				<span class="screen-reader-text"><?php esc_html_e( 'Search files', 'etwa' ); ?></span>
				<input type="search" id="etwa-files-search" class="etwa-input" placeholder="<?php esc_attr_e( 'Search clients or scopes…', 'etwa' ); ?>" autocomplete="off" />
			</label>
			<p class="etwa-muted" id="etwa-files-count"></p>
		</div>
		<div id="etwa-files-grid" class="etwa-files-card-grid" aria-live="polite"></div>
		<div class="etwa-empty-state" id="etwa-files-empty" hidden>
			<p class="etwa-muted"><?php esc_html_e( 'No client files yet. Create a scope under Deliverables to open a file.', 'etwa' ); ?></p>
		</div>
	</div>

	<div id="etwa-files-detail" class="<?php echo 'file' === $mode ? '' : 'etwa-hidden'; ?>">
		<div class="etwa-files-detail-head">
			<div>
				<a href="<?php echo esc_url( $files_list_url ); ?>" class="etwa-btn etwa-btn-ghost etwa-btn-small" id="etwa-files-back">&larr; <?php esc_html_e( 'Back to Files', 'etwa' ); ?></a>
				<h2 id="etwa-file-client-name"><?php esc_html_e( 'Client File', 'etwa' ); ?></h2>
				<p class="etwa-muted" id="etwa-file-client-meta"></p>
			</div>
			<div class="etwa-hero-actions" id="etwa-file-actions"></div>
		</div>

		<div class="etwa-analytics-kpi-grid" id="etwa-file-kpis">
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Scopes', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="scope_count">—</strong></div></article>
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Active', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="active_scope_count">—</strong></div></article>
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Contract', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="contract_deliverables">—</strong></div></article>
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Delivered', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="delivered_qty">—</strong></div></article>
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Completion', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="completion_pct">—</strong></div></article>
			<article class="etwa-analytics-kpi"><div class="etwa-analytics-kpi-body"><span class="etwa-kpi-label"><?php esc_html_e( 'Hours', 'etwa' ); ?></span><strong class="etwa-kpi-value" data-file-kpi="effort_score">—</strong></div></article>
		</div>

		<div id="etwa-file-scopes-grid" class="etwa-files-card-grid" aria-live="polite"></div>
		<div class="etwa-empty-state" id="etwa-file-scopes-empty" hidden>
			<p class="etwa-muted"><?php esc_html_e( 'No scopes on this client file.', 'etwa' ); ?></p>
		</div>
	</div>
</div>
