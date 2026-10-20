<?php
/**
 * Prospects list and detail.
 *
 * @var string $context admin|frontend
 *
 * @package ETWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ETWA\Models\Prospect;

$context      = $context ?? 'frontend';
$in_portal    = 'frontend' === $context;
$prospect_id  = isset( $_GET['prospect_id'] ) ? absint( wp_unslash( (string) $_GET['prospect_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$mode         = $prospect_id > 0 ? 'detail' : 'list';
$today        = current_time( 'Y-m-d' );
$source_labels = Prospect::source_labels();
?>
<div
	class="etwa-page-shell etwa-app<?php echo $in_portal ? ' etwa-in-portal' : ''; ?>"
	id="etwa-prospects-app"
	data-mode="<?php echo esc_attr( $mode ); ?>"
	data-prospect-id="<?php echo esc_attr( (string) $prospect_id ); ?>"
>
	<?php if ( ! $in_portal ) : ?>
		<div class="etwa-hero">
			<div class="etwa-hero-copy">
				<p class="etwa-eyebrow"><?php esc_html_e( 'Workspace', 'etwa' ); ?></p>
				<h1><?php esc_html_e( 'Prospects', 'etwa' ); ?></h1>
				<p class="etwa-subtitle"><?php esc_html_e( 'Track potential clients, record work, then mark won or lost and convert to a client.', 'etwa' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<section class="etwa-panel" id="etwa-prospects-list" <?php echo 'detail' === $mode ? 'hidden' : ''; ?>>
		<form id="etwa-prospect-form" class="etwa-form-grid" method="post" action="">
			<label><?php esc_html_e( 'Prospect name', 'etwa' ); ?><input type="text" name="name" required placeholder="<?php esc_attr_e( 'e.g. Acme Co.', 'etwa' ); ?>" /></label>
			<label>
				<?php esc_html_e( 'Source', 'etwa' ); ?>
				<select name="source" class="etwa-input" required>
					<option value=""><?php esc_html_e( 'Select Source', 'etwa' ); ?></option>
					<?php foreach ( $source_labels as $source_key => $source_label ) : ?>
						<option value="<?php echo esc_attr( $source_key ); ?>"><?php echo esc_html( $source_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Start Date', 'etwa' ); ?>
				<input type="date" name="prospect_start_date" class="etwa-input" required value="<?php echo esc_attr( $today ); ?>" />
			</label>
			<div class="etwa-span-2">
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Add Prospect', 'etwa' ); ?></button>
			</div>
		</form>

		<div class="etwa-toolbar">
			<h2><?php esc_html_e( 'Prospects', 'etwa' ); ?></h2>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'etwa' ); ?></span>
				<select id="etwa-prospect-status" class="etwa-input">
					<option value=""><?php esc_html_e( 'All statuses', 'etwa' ); ?></option>
					<option value="open"><?php esc_html_e( 'Open', 'etwa' ); ?></option>
					<option value="won"><?php esc_html_e( 'Won', 'etwa' ); ?></option>
					<option value="lost"><?php esc_html_e( 'Lost', 'etwa' ); ?></option>
				</select>
			</label>
		</div>
		<div class="etwa-table-wrap">
			<table class="etwa-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Prospect', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Source', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Start Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Created Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Won/Lost Date', 'etwa' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'etwa' ); ?></th>
					</tr>
				</thead>
				<tbody id="etwa-prospects-body">
					<tr class="etwa-skeleton-row"><td colspan="7"><div class="etwa-skeleton" style="height:40px"></div></td></tr>
				</tbody>
			</table>
		</div>
	</section>

	<section class="etwa-panel" id="etwa-prospects-detail" <?php echo 'list' === $mode ? 'hidden' : ''; ?>>
		<p><a href="#" class="etwa-link" id="etwa-prospect-back"><?php esc_html_e( '← Back to prospects', 'etwa' ); ?></a></p>
		<div id="etwa-prospect-detail-body">
			<div class="etwa-skeleton" style="height:120px"></div>
		</div>
	</section>
</div>

<div class="etwa-modal" id="etwa-prospect-edit-modal" hidden>
	<div class="etwa-modal-content">
		<h2 id="etwa-prospect-edit-title"><?php esc_html_e( 'Edit Prospect', 'etwa' ); ?></h2>
		<form id="etwa-prospect-edit-form" class="etwa-form-grid" method="post" action="">
			<input type="hidden" name="id" id="etwa-prospect-edit-id" />
			<label class="etwa-span-2"><?php esc_html_e( 'Name', 'etwa' ); ?><input type="text" name="name" id="etwa-prospect-edit-name" required /></label>
			<label>
				<?php esc_html_e( 'Source', 'etwa' ); ?>
				<select name="source" id="etwa-prospect-edit-source" class="etwa-input" required>
					<option value=""><?php esc_html_e( 'Select Source', 'etwa' ); ?></option>
					<?php foreach ( $source_labels as $source_key => $source_label ) : ?>
						<option value="<?php echo esc_attr( $source_key ); ?>"><?php echo esc_html( $source_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Start Date', 'etwa' ); ?>
				<input type="date" name="prospect_start_date" id="etwa-prospect-edit-start-date" class="etwa-input" required />
			</label>
			<label class="etwa-span-2" id="etwa-prospect-edit-reason-wrap" hidden>
				<?php esc_html_e( 'Lost reason', 'etwa' ); ?>
				<textarea name="lost_reason" id="etwa-prospect-edit-reason" rows="3"></textarea>
			</label>
			<div class="etwa-span-2 etwa-modal-actions">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Save', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>

<div class="etwa-modal" id="etwa-prospect-lost-modal" hidden>
	<div class="etwa-modal-content">
		<h2><?php esc_html_e( 'Mark Lost', 'etwa' ); ?></h2>
		<form id="etwa-prospect-lost-form" class="etwa-form-grid">
			<input type="hidden" name="id" id="etwa-prospect-lost-id" />
			<p class="etwa-span-2 etwa-muted" id="etwa-prospect-lost-name"></p>
			<label class="etwa-span-2">
				<?php esc_html_e( 'Lost reason', 'etwa' ); ?>
				<textarea name="lost_reason" id="etwa-prospect-lost-reason" rows="4" required></textarea>
			</label>
			<div class="etwa-span-2 etwa-modal-actions">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Mark Lost', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>

<div class="etwa-modal" id="etwa-prospect-reason-modal" hidden>
	<div class="etwa-modal-content">
		<h2><?php esc_html_e( 'Lost Reason', 'etwa' ); ?></h2>
		<p id="etwa-prospect-reason-name" class="etwa-muted"></p>
		<p id="etwa-prospect-reason-text"></p>
		<div class="etwa-modal-actions">
			<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-modal-close"><?php esc_html_e( 'Close', 'etwa' ); ?></button>
		</div>
	</div>
</div>

<div class="etwa-modal" id="etwa-prospect-convert-modal" hidden>
	<div class="etwa-modal-content">
		<h2><?php esc_html_e( 'Convert to Client', 'etwa' ); ?></h2>
		<form id="etwa-prospect-convert-form" class="etwa-form-grid">
			<input type="hidden" name="id" id="etwa-prospect-convert-id" />
			<label class="etwa-span-2"><?php esc_html_e( 'Client name', 'etwa' ); ?><input type="text" name="name" id="etwa-prospect-convert-name" required /></label>
			<label><?php esc_html_e( 'Code', 'etwa' ); ?><input type="text" name="code" id="etwa-prospect-convert-code" required /></label>
			<label><?php esc_html_e( 'Department', 'etwa' ); ?><input type="text" name="department" id="etwa-prospect-convert-department" value="General" /></label>
			<div class="etwa-span-2 etwa-modal-actions">
				<button type="button" class="etwa-btn etwa-btn-secondary etwa-prospect-modal-close"><?php esc_html_e( 'Cancel', 'etwa' ); ?></button>
				<button type="submit" class="etwa-btn etwa-btn-primary"><?php esc_html_e( 'Convert to Client', 'etwa' ); ?></button>
			</div>
		</form>
	</div>
</div>
