<?php
/**
 * Scope patch service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\ScopePatchLineRepository;
use ETWA\Repositories\ScopePatchRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScopePatchService {

	public function __construct(
		private ScopePatchRepository $patches = new ScopePatchRepository(),
		private ScopePatchLineRepository $lines = new ScopePatchLineRepository(),
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private DeliverableMetricsService $metrics = new DeliverableMetricsService(),
	) {}

	/** @return array<string,mixed>|WP_Error */
	public function details( int $user_id, int $patch_id ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view batches.', 'etwa' ), array( 'status' => 403 ) );
		}
		$progress = $this->metrics->patch_progress( $patch_id );
		if ( is_wp_error( $progress ) ) {
			return $progress;
		}
		// Legacy type lines kept for backward-compatible reads only.
		$lines = array();
		foreach ( $this->lines->for_patch( $patch_id ) as $line ) {
			$lines[] = $line->to_array();
		}
		$progress['lines'] = $lines;
		return $progress;
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, int $scope_id, array $data ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage batches.', 'etwa' ), array( 'status' => 403 ) );
		}
		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope ) {
			return new WP_Error( 'not_found', __( 'Scope not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', __( 'Batch name is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$status = sanitize_key( (string) ( $data['status'] ?? 'active' ) );
		if ( ! in_array( $status, array( 'draft', 'active', 'void' ), true ) ) {
			$status = 'active';
		}

		$expected_qty = array_key_exists( 'expected_qty', $data )
			? max( 0.0, (float) $data['expected_qty'] )
			: max( 0.0, (float) $scope->expected_per_patch );

		$id = $this->patches->create(
			array(
				'scope_id'     => $scope_id,
				'name'         => $name,
				'code'         => sanitize_text_field( (string) ( $data['code'] ?? '' ) ) ?: null,
				'description'  => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'status'       => $status,
				'expected_qty' => $expected_qty,
				'effective_on' => $this->nullable_date( $data['effective_on'] ?? null ),
				'created_by'   => $user_id,
			)
		);

		return $this->details( $user_id, $id );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $patch_id, array $data ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage batches.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( ! $this->patches->find( $patch_id ) ) {
			return new WP_Error( 'not_found', __( 'Batch not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'invalid', __( 'Batch name is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['name'] = $name;
		}
		if ( array_key_exists( 'code', $data ) ) {
			$payload['code'] = sanitize_text_field( (string) $data['code'] ) ?: null;
		}
		if ( isset( $data['description'] ) ) {
			$payload['description'] = sanitize_textarea_field( (string) $data['description'] );
		}
		if ( isset( $data['status'] ) ) {
			$status = sanitize_key( (string) $data['status'] );
			if ( ! in_array( $status, array( 'draft', 'active', 'void' ), true ) ) {
				return new WP_Error( 'invalid', __( 'Invalid batch status.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['status'] = $status;
		}
		if ( array_key_exists( 'expected_qty', $data ) ) {
			$payload['expected_qty'] = max( 0.0, (float) $data['expected_qty'] );
		}
		if ( array_key_exists( 'effective_on', $data ) ) {
			$payload['effective_on'] = $this->nullable_date( $data['effective_on'] );
		}

		if ( $payload ) {
			$this->patches->update( $patch_id, $payload );
		}

		return $this->details( $user_id, $patch_id );
	}

	private function nullable_date( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$date = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
	}
}
