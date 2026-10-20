<?php
/**
 * Products list and hub.
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\AuthHelper;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Helpers\SuperClientRequestHelper;
use ETWA\Repositories\ClientRepository;

$portal_base = PortalNav::view_url( AuthHelper::portal_url(), 'super-clients' );
$view        = isset( $_GET['etwa_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['etwa_view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$client_id   = SuperClientAccessHelper::super_client_id_from_view( $view );
if ( ! $client_id && isset( $_GET['client_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$client_id = (int) $_GET['client_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( 'clients' === $tab ) {
	$tab = 'brands';
}
$allowed_tabs = array( 'overview', 'brands', 'requests', 'analytics' );
$can_manage_products = SuperClientAccessHelper::can_manage_products( get_current_user_id() );
if ( ! $can_manage_products ) {
	$allowed_tabs = array( 'overview', 'brands', 'requests' );
}
if ( ! in_array( $tab, $allowed_tabs, true ) ) {
	$tab = 'overview';
}

$hub_client = null;
if ( $client_id > 0 ) {
	$hub_client = ( new ClientRepository() )->find( $client_id );
	if ( ! $hub_client || ! $hub_client->is_super() ) {
		$hub_client = null;
		$client_id  = 0;
	} elseif ( ! SuperClientAccessHelper::can_view_product_tab( get_current_user_id(), $client_id ) ) {
		echo '<div class="etwa-portal-message"><p>' . esc_html__( 'This Product Tab is not available for your department.', 'etwa' ) . '</p></div>';
		return;
	}
}

$request_type_labels     = SuperClientRequestHelper::request_type_labels();
$service_category_labels = SuperClientRequestHelper::service_category_labels();
$agency_labels           = SuperClientRequestHelper::agency_labels();
$industry_labels         = SuperClientRequestHelper::industry_labels();
$status_labels           = SuperClientRequestHelper::status_labels();
$analytics_presets       = DateRangeHelper::prospect_analytics_preset_labels();
$analytics_range         = DateRangeHelper::resolve( DateRangeHelper::PRESET_ALL_TIME, null, null );
$hub_subtitle            = $hub_client
	? ( '' !== $hub_client->department ? $hub_client->department : $hub_client->code )
	: '';
?>
<div class="etwa-page-shell etwa-app etwa-portal etwa-super-client" id="etwa-super-clients-app"
	data-mode="<?php echo esc_attr( $client_id ? 'hub' : 'list' ); ?>"
	data-client-id="<?php echo esc_attr( (string) $client_id ); ?>"
	data-tab="<?php echo esc_attr( $tab ); ?>"
	data-portal-base="<?php echo esc_url( $portal_base ); ?>"
	data-can-manage="<?php echo $can_manage_products ? '1' : '0'; ?>"
	data-can-complete-submission="<?php echo SuperClientAccessHelper::can_complete_submission( get_current_user_id() ) ? '1' : '0'; ?>"
	<?php if ( $hub_client ) : ?>
	data-client-name="<?php echo esc_attr( $hub_client->name ); ?>"
	data-client-active="<?php echo esc_attr( $hub_client->is_active ? '1' : '0' ); ?>"
	<?php endif; ?>
>
	<?php if ( ! $client_id ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Settings', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Products', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Manage products, their brands, and requests.', 'etwa' ); ?></p>
			</div>
		</div>
		<div class="etwa-card">
			<div class="etwa-card-header">
				<h2><?php esc_html_e( 'Products', 'etwa' ); ?></h2>
			</div>
			<div class="etwa-card-body">
				<div class="etwa-table-wrap">
					<table class="etwa-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Client', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Code', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Brands', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Requests', 'etwa' ); ?></th>
								<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody id="etwa-sc-directory-body">
							<tr><td colspan="6"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php else : ?>
		<header class="etwa-super-client__header">
			<div class="etwa-super-client__header-main">
				<p class="etwa-super-client__breadcrumb">
					<a class="etwa-link" href="<?php echo esc_url( $portal_base ); ?>"><?php esc_html_e( 'Products', 'etwa' ); ?></a>
				</p>
				<div class="etwa-super-client__title-row">
					<h1 class="etwa-super-client__title"><?php echo esc_html( $hub_client->name ); ?></h1>
					<span class="etwa-badge" id="etwa-sc-hub-pending-badge" hidden></span>
					<span class="etwa-badge etwa-badge-super"><?php esc_html_e( 'PRODUCT', 'etwa' ); ?></span>
					<?php if ( ! $hub_client->is_active ) : ?>
						<span class="etwa-status etwa-status-rejected"><?php esc_html_e( 'Inactive', 'etwa' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( '' !== $hub_subtitle ) : ?>
					<p class="etwa-super-client__subtitle"><?php echo esc_html( $hub_subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<div class="etwa-super-client__header-actions">
				<?php if ( $can_manage_products ) : ?>
					<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-sc-demote"><?php esc_html_e( 'Demote to Standard Client', 'etwa' ); ?></button>
				<?php endif; ?>
			</div>
		</header>

		<nav class="etwa-tabs etwa-super-client__tabs etwa-sc-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Product sections', 'etwa' ); ?>">
			<?php foreach ( $allowed_tabs as $tab_id ) : ?>
				<button type="button" class="etwa-tab<?php echo $tab === $tab_id ? ' active' : ''; ?>" data-tab="<?php echo esc_attr( $tab_id ); ?>" role="tab" aria-selected="<?php echo $tab === $tab_id ? 'true' : 'false'; ?>">
					<?php
					echo esc_html(
						match ( $tab_id ) {
							'overview'  => __( 'Overview', 'etwa' ),
							'brands'    => __( 'Brands', 'etwa' ),
							'requests'  => __( 'Requests', 'etwa' ),
							'analytics' => __( 'Analytics', 'etwa' ),
							default     => ucfirst( $tab_id ),
						}
					);
					?>
					<?php if ( 'requests' === $tab_id ) : ?>
						<span class="etwa-badge" id="etwa-sc-requests-tab-badge" hidden></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</nav>

		<div id="etwa-sc-tab-overview" class="etwa-tab-panel<?php echo 'overview' === $tab ? ' active' : ''; ?>" role="tabpanel">
			<div class="etwa-card">
				<div class="etwa-card-header">
					<h2><?php esc_html_e( 'Overview', 'etwa' ); ?></h2>
				</div>
				<div class="etwa-card-body">
					<div class="etwa-kpi-grid" id="etwa-sc-overview-stats"></div>
					<div id="etwa-sc-overview-recent"></div>
				</div>
			</div>
		</div>

		<div id="etwa-sc-tab-brands" class="etwa-tab-panel<?php echo 'brands' === $tab ? ' active' : ''; ?>" role="tabpanel">
			<div class="etwa-card">
				<div class="etwa-card-header">
					<h2><?php esc_html_e( 'Brands', 'etwa' ); ?></h2>
					<?php if ( $can_manage_products ) : ?>
						<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-sc-add-brand"><?php esc_html_e( '+ Add Brand', 'etwa' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="etwa-card-body">
					<div class="etwa-super-client__toolbar">
						<label class="etwa-super-client__search">
							<span class="screen-reader-text"><?php esc_html_e( 'Search brands', 'etwa' ); ?></span>
							<input type="search" class="etwa-input" id="etwa-sc-brands-search" placeholder="<?php esc_attr_e( 'Search brands…', 'etwa' ); ?>" autocomplete="off">
						</label>
					</div>
					<div class="etwa-table-wrap">
						<table class="etwa-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Brand', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Industry', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Agency', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Requests', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="etwa-sc-brands-body">
								<tr><td colspan="6"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div id="etwa-sc-tab-requests" class="etwa-tab-panel<?php echo 'requests' === $tab ? ' active' : ''; ?>" role="tabpanel">
			<div class="etwa-card">
				<div class="etwa-card-header">
					<h2><?php esc_html_e( 'Requests', 'etwa' ); ?></h2>
					<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-sc-add-request"><?php esc_html_e( '+ Create Request', 'etwa' ); ?></button>
				</div>
				<div class="etwa-card-body">
					<div class="etwa-super-client__toolbar">
						<label class="etwa-super-client__search">
							<span class="screen-reader-text"><?php esc_html_e( 'Search requests', 'etwa' ); ?></span>
							<input type="search" class="etwa-input" id="etwa-sc-requests-search" placeholder="<?php esc_attr_e( 'Search requests…', 'etwa' ); ?>" autocomplete="off">
						</label>
					</div>
					<div class="etwa-table-wrap">
						<table class="etwa-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Brand', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Type', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Category', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Service', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Contact', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Invoice #', 'etwa' ); ?></th>
									<th><?php esc_html_e( 'Date', 'etwa' ); ?></th>
									<th></th>
								</tr>
							</thead>
							<tbody id="etwa-sc-requests-body">
								<tr><td colspan="9"><?php esc_html_e( 'Loading…', 'etwa' ); ?></td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $can_manage_products ) : ?>
		<div id="etwa-sc-tab-analytics" class="etwa-tab-panel<?php echo 'analytics' === $tab ? ' active' : ''; ?>" role="tabpanel">
			<div class="etwa-card">
				<div class="etwa-card-header">
					<h2><?php esc_html_e( 'Analytics', 'etwa' ); ?></h2>
				</div>
				<div class="etwa-card-body">
					<div id="etwa-sc-analytics-filter" class="etwa-analytics-filter etwa-date-range-filter">
						<div class="etwa-tabs etwa-date-preset-row" role="tablist" aria-label="<?php esc_attr_e( 'Date range', 'etwa' ); ?>">
							<?php foreach ( DateRangeHelper::prospect_analytics_preset_labels() as $key => $label ) : ?>
								<button
									type="button"
									class="etwa-tab<?php echo DateRangeHelper::PRESET_ALL_TIME === $key ? ' active' : ''; ?>"
									data-preset="<?php echo esc_attr( $key ); ?>"
								><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
						<div class="etwa-analytics-filter-fields etwa-custom-range-fields">
							<label class="etwa-analytics-field">
								<span><?php esc_html_e( 'From', 'etwa' ); ?></span>
								<input type="date" class="etwa-input" id="etwa-sc-analytics-from" />
							</label>
							<label class="etwa-analytics-field">
								<span><?php esc_html_e( 'To', 'etwa' ); ?></span>
								<input type="date" class="etwa-input" id="etwa-sc-analytics-to" />
							</label>
							<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-sc-analytics-apply">
								<?php esc_html_e( 'Apply custom range', 'etwa' ); ?>
							</button>
						</div>
					</div>
					<p id="etwa-sc-analytics-range-label" class="etwa-sc-analytics-range-label" hidden></p>
					<div id="etwa-sc-analytics-panel">
						<p class="etwa-muted"><?php esc_html_e( 'Loading analytics…', 'etwa' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<div class="etwa-modal etwa-sc-modal" id="etwa-sc-brand-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-sc-brand-modal-title" aria-describedby="etwa-sc-brand-modal-subtitle">
	<div class="etwa-modal-content etwa-sc-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<header class="etwa-sc-modal-header">
			<h2 id="etwa-sc-brand-modal-title"><?php esc_html_e( 'Add Brand', 'etwa' ); ?></h2>
			<p id="etwa-sc-brand-modal-subtitle" class="etwa-sc-modal-subtitle"><?php esc_html_e( 'Add a brand profile for this Product', 'etwa' ); ?></p>
		</header>
		<form id="etwa-sc-brand-form" class="etwa-sc-modal-form" novalidate>
			<input type="hidden" name="id" value="">
			<div class="etwa-sc-modal-body">
				<div class="etwa-form-grid">
					<label>
						<?php esc_html_e( 'Brand', 'etwa' ); ?>
						<input type="text" name="name" required class="etwa-input" autocomplete="organization">
						<span class="etwa-field-error" data-for="name" hidden></span>
					</label>
					<label>
						<?php esc_html_e( 'Industry', 'etwa' ); ?>
						<select name="industry" required class="etwa-input">
							<option value=""><?php esc_html_e( 'Select industry…', 'etwa' ); ?></option>
							<?php foreach ( $industry_labels as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="etwa-field-error" data-for="industry" hidden></span>
					</label>
				</div>
				<div class="etwa-sc-field-group">
					<span class="etwa-field-label" id="etwa-sc-brand-agency-label"><?php esc_html_e( 'Agency', 'etwa' ); ?></span>
					<div class="etwa-sc-radio-cards" role="radiogroup" aria-labelledby="etwa-sc-brand-agency-label">
						<?php foreach ( $agency_labels as $value => $label ) : ?>
							<label class="etwa-sc-radio-card">
								<input type="radio" name="agency" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, SuperClientRequestHelper::AGENCY_DIRECT ); ?>>
								<span class="etwa-sc-radio-card-label"><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<span class="etwa-field-error" data-for="agency" hidden></span>
				</div>
				<label id="etwa-sc-agency-name-wrap" class="etwa-span-2" hidden>
					<?php esc_html_e( 'Agency Name', 'etwa' ); ?>
					<input type="text" name="agency_name" class="etwa-input" autocomplete="organization">
					<span class="etwa-field-error" data-for="agency_name" hidden></span>
				</label>
				<label class="etwa-checkbox-row">
					<input type="checkbox" name="is_active" value="1" checked>
					<?php esc_html_e( 'Active', 'etwa' ); ?>
				</label>
			</div>
			<footer class="etwa-sc-modal-footer">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary" id="etwa-sc-brand-submit"><?php esc_html_e( 'Save Brand', 'etwa' ); ?></button>
			</footer>
		</form>
	</div>
</div>

<div class="etwa-modal etwa-sc-modal" id="etwa-sc-request-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-sc-request-modal-title" aria-describedby="etwa-sc-request-modal-subtitle">
	<div class="etwa-modal-content etwa-sc-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<header class="etwa-sc-modal-header">
			<h2 id="etwa-sc-request-modal-title"><?php esc_html_e( 'Create Request', 'etwa' ); ?></h2>
			<p id="etwa-sc-request-modal-subtitle" class="etwa-sc-modal-subtitle"><?php esc_html_e( 'Add a new request for a Product brand', 'etwa' ); ?></p>
		</header>
		<form id="etwa-sc-request-form" class="etwa-sc-modal-form" novalidate>
			<input type="hidden" name="id" value="">
			<div class="etwa-sc-modal-body">
				<section class="etwa-form-section" id="etwa-sc-request-step1" aria-labelledby="etwa-sc-request-details-heading">
					<h3 id="etwa-sc-request-details-heading" class="etwa-form-section-title"><?php esc_html_e( 'Request', 'etwa' ); ?></h3>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'Brand', 'etwa' ); ?>
							<select name="brand_id" required id="etwa-sc-request-brand-select" class="etwa-input">
								<option value=""><?php esc_html_e( 'Select brand…', 'etwa' ); ?></option>
							</select>
							<span class="etwa-field-error" data-for="brand_id" hidden></span>
						</label>
						<label>
							<?php esc_html_e( 'Request Type', 'etwa' ); ?>
							<select name="request_type" required class="etwa-input">
								<?php foreach ( $request_type_labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
					<div class="etwa-sc-field-group">
						<span class="etwa-field-label" id="etwa-sc-service-category-label"><?php esc_html_e( 'Service Category', 'etwa' ); ?></span>
						<div class="etwa-sc-radio-cards" role="radiogroup" aria-labelledby="etwa-sc-service-category-label">
							<?php foreach ( $service_category_labels as $value => $label ) : ?>
								<label class="etwa-sc-radio-card">
									<input type="radio" name="service_category" value="<?php echo esc_attr( $value ); ?>" required>
									<span class="etwa-sc-radio-card-label"><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<span class="etwa-field-error" data-for="service_category" hidden></span>
					</div>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'Service', 'etwa' ); ?>
							<input type="text" name="service" required class="etwa-input" placeholder="<?php esc_attr_e( 'Enter service description…', 'etwa' ); ?>">
							<span class="etwa-field-error" data-for="service" hidden></span>
						</label>
						<label>
							<?php esc_html_e( 'Status', 'etwa' ); ?>
							<select name="status" required class="etwa-input">
								<?php foreach ( $status_labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, SuperClientRequestHelper::STATUS_PENDING ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</section>
				<section class="etwa-form-section etwa-sc-submission-section" id="etwa-sc-request-step2" aria-labelledby="etwa-sc-request-contact-heading" hidden>
					<h3 id="etwa-sc-request-contact-heading" class="etwa-form-section-title"><?php esc_html_e( 'Submission / Receipt', 'etwa' ); ?></h3>
					<div class="etwa-form-grid">
						<label>
							<?php esc_html_e( 'Contact Name', 'etwa' ); ?>
							<input type="text" name="contact_name" class="etwa-input" autocomplete="name">
						</label>
						<label>
							<?php esc_html_e( 'Contact Phone', 'etwa' ); ?>
							<input type="tel" name="contact_phone" class="etwa-input" autocomplete="tel">
						</label>
						<label class="etwa-span-2">
							<?php esc_html_e( 'Contact Email', 'etwa' ); ?>
							<input type="email" name="contact_email" class="etwa-input" autocomplete="email">
							<span class="etwa-field-error" data-for="contact_email" hidden></span>
						</label>
						<label>
							<?php esc_html_e( 'Invoice #', 'etwa' ); ?>
							<input type="text" name="invoice_number" class="etwa-input" placeholder="<?php esc_attr_e( 'Enter invoice number…', 'etwa' ); ?>">
						</label>
					</div>
				</section>
			</div>
			<footer class="etwa-sc-modal-footer">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary" id="etwa-sc-request-submit"><?php esc_html_e( 'Save Request', 'etwa' ); ?></button>
			</footer>
		</form>
	</div>
</div>

<div class="etwa-modal etwa-sc-modal" id="etwa-sc-request-detail-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="etwa-sc-request-detail-title">
	<div class="etwa-modal-content etwa-sc-modal-content">
		<button type="button" class="etwa-modal-close-btn etwa-modal-close" aria-label="<?php esc_attr_e( 'Close', 'etwa' ); ?>">&times;</button>
		<header class="etwa-sc-modal-header">
			<h2 id="etwa-sc-request-detail-title"><?php esc_html_e( 'Request', 'etwa' ); ?></h2>
			<p class="etwa-sc-modal-subtitle" id="etwa-sc-request-detail-subtitle"></p>
		</header>
		<div class="etwa-sc-modal-body">
			<div id="etwa-sc-request-detail-body"></div>
		</div>
		<footer class="etwa-sc-modal-footer">
			<button type="button" class="etwa-btn etwa-btn-secondary etwa-modal-close"><?php esc_html_e( 'Close', 'etwa' ); ?></button>
			<button type="button" class="etwa-btn etwa-btn-secondary" id="etwa-sc-request-detail-edit"><?php esc_html_e( 'Edit Request', 'etwa' ); ?></button>
			<?php if ( SuperClientAccessHelper::can_complete_submission( get_current_user_id() ) ) : ?>
				<button type="button" class="etwa-btn etwa-btn-primary" id="etwa-sc-request-detail-submit"><?php esc_html_e( 'Complete Submission', 'etwa' ); ?></button>
			<?php endif; ?>
		</footer>
	</div>
</div>
