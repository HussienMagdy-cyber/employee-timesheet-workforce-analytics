<?php
/**
 * Deliverable type catalog service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\DeliverableType;
use ETWA\Repositories\DeliverableTypeRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableTypeService {

	public function __construct(
		private DeliverableTypeRepository $types = new DeliverableTypeRepository(),
	) {}

	/** @return DeliverableType[]|WP_Error */
	public function list( int $user_id, ?bool $active_only = null ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view deliverable types.', 'etwa' ), array( 'status' => 403 ) );
		}
		return $this->types->all( $active_only );
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, array $data ): DeliverableType|WP_Error {
		if ( ! RoleManager::user_can_manage_deliverable_types( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage deliverable types.', 'etwa' ), array( 'status' => 403 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		$slug = sanitize_title( (string) ( $data['slug'] ?? $name ) );
		if ( '' === $name || '' === $slug ) {
			return new WP_Error( 'invalid', __( 'Name and slug are required.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->types->find_by_slug( $slug ) ) {
			return new WP_Error( 'duplicate', __( 'Deliverable type slug already exists.', 'etwa' ), array( 'status' => 409 ) );
		}

		$effort = (float) ( $data['effort_score'] ?? 1 );
		if ( $effort < 0 ) {
			return new WP_Error( 'invalid', __( 'Effort score must be zero or greater.', 'etwa' ), array( 'status' => 400 ) );
		}

		$id = $this->types->create(
			array(
				'slug'         => $slug,
				'name'         => $name,
				'description'  => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'effort_score' => $effort,
				'sort_order'   => (int) ( $data['sort_order'] ?? 0 ),
				'is_active'    => 1,
			)
		);

		$type = $this->types->find( $id );
		return $type ?: new WP_Error( 'create_failed', __( 'Could not create deliverable type.', 'etwa' ), array( 'status' => 500 ) );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $id, array $data ): DeliverableType|WP_Error {
		if ( ! RoleManager::user_can_manage_deliverable_types( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage deliverable types.', 'etwa' ), array( 'status' => 403 ) );
		}
		$type = $this->types->find( $id );
		if ( ! $type ) {
			return new WP_Error( 'not_found', __( 'Deliverable type not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['name'] ) ) {
			$payload['name'] = sanitize_text_field( (string) $data['name'] );
		}
		if ( isset( $data['slug'] ) ) {
			$slug = sanitize_title( (string) $data['slug'] );
			$existing = $this->types->find_by_slug( $slug );
			if ( $existing && (int) $existing->id !== $id ) {
				return new WP_Error( 'duplicate', __( 'Deliverable type slug already exists.', 'etwa' ), array( 'status' => 409 ) );
			}
			$payload['slug'] = $slug;
		}
		if ( isset( $data['description'] ) ) {
			$payload['description'] = sanitize_textarea_field( (string) $data['description'] );
		}
		if ( isset( $data['effort_score'] ) ) {
			$effort = (float) $data['effort_score'];
			if ( $effort < 0 ) {
				return new WP_Error( 'invalid', __( 'Effort score must be zero or greater.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['effort_score'] = $effort;
		}
		if ( isset( $data['sort_order'] ) ) {
			$payload['sort_order'] = (int) $data['sort_order'];
		}
		if ( isset( $data['is_active'] ) ) {
			$payload['is_active'] = (int) (bool) $data['is_active'];
		}

		$this->types->update( $id, $payload );
		$updated = $this->types->find( $id );
		return $updated ?: new WP_Error( 'update_failed', __( 'Could not update deliverable type.', 'etwa' ), array( 'status' => 500 ) );
	}

	public function deactivate( int $user_id, int $id ): DeliverableType|WP_Error {
		return $this->update( $user_id, $id, array( 'is_active' => 0 ) );
	}
}
