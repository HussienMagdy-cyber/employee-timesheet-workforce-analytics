<?php
/**
 * Project management service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\Project;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ProjectRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProjectService {

	public static function unassigned_label(): string {
		return __( 'Social Media', 'etwa' );
	}

	public function __construct(
		private ProjectRepository $projects = new ProjectRepository(),
		private ClientRepository $clients = new ClientRepository(),
	) {}

	/** @return Project[]|WP_Error */
	public function list_for_client( int $user_id, int $client_id, bool $active_only = true ): array|WP_Error {
		if ( ! is_user_logged_in() && $user_id <= 0 ) {
			return new WP_Error( 'forbidden', __( 'Not allowed.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( ! $this->clients->find( $client_id ) ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		return $this->projects->list_for_client( $client_id, $active_only );
	}

	/** @return Project[]|WP_Error */
	public function list_standalone( int $user_id, bool $active_only = true ): array|WP_Error {
		if ( ! is_user_logged_in() && $user_id <= 0 ) {
			return new WP_Error( 'forbidden', __( 'Not allowed.', 'etwa' ), array( 'status' => 403 ) );
		}
		return $this->projects->list_standalone( $active_only );
	}

	/**
	 * Nested tree of standalone projects (parents with children).
	 *
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list_standalone_tree( int $user_id, bool $active_only = true ): array|WP_Error {
		$projects = $this->list_standalone( $user_id, $active_only );
		if ( is_wp_error( $projects ) ) {
			return $projects;
		}

		$children = array();
		$parents  = array();
		foreach ( $projects as $project ) {
			$row = $project->to_array();
			if ( $project->parent_project_id ) {
				$pid = (int) $project->parent_project_id;
				if ( ! isset( $children[ $pid ] ) ) {
					$children[ $pid ] = array();
				}
				$children[ $pid ][] = $row;
			} else {
				$parents[] = $row;
			}
		}

		$tree = array();
		foreach ( $parents as $parent ) {
			$parent['children'] = $children[ (int) $parent['id'] ] ?? array();
			$tree[]             = $parent;
		}

		return $tree;
	}

	/** @param array<string,mixed> $data */
	public function create_standalone( int $user_id, array $data ): Project|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage projects.', 'etwa' ), array( 'status' => 403 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', __( 'Project name is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$parent_id = isset( $data['parent_project_id'] ) ? (int) $data['parent_project_id'] : 0;
		if ( $parent_id > 0 ) {
			$parent = $this->projects->find( $parent_id );
			if ( ! $parent || ! $parent->is_standalone() ) {
				return new WP_Error( 'invalid', __( 'Parent project not found.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( $parent->parent_project_id ) {
				return new WP_Error( 'invalid', __( 'Sub-projects cannot have children.', 'etwa' ), array( 'status' => 400 ) );
			}
		} else {
			$parent_id = 0;
		}

		$id = $this->projects->create(
			array(
				'client_id'         => null,
				'parent_project_id' => $parent_id > 0 ? $parent_id : null,
				'name'              => $name,
				'description'       => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'status'            => Project::STATUS_ACTIVE,
			)
		);

		return $this->projects->find( $id );
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, int $client_id, array $data ): Project|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage projects.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( ! $this->clients->find( $client_id ) ) {
			return new WP_Error( 'not_found', __( 'Client not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', __( 'Project name is required.', 'etwa' ), array( 'status' => 400 ) );
		}

		$id = $this->projects->create(
			array(
				'client_id'         => $client_id,
				'parent_project_id' => null,
				'name'              => $name,
				'description'       => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'status'            => Project::STATUS_ACTIVE,
			)
		);

		return $this->projects->find( $id );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $project_id, array $data ): Project|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage projects.', 'etwa' ), array( 'status' => 403 ) );
		}
		$project = $this->projects->find( $project_id );
		if ( ! $project ) {
			return new WP_Error( 'not_found', __( 'Project not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'invalid', __( 'Project name is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['name'] = $name;
		}
		if ( isset( $data['description'] ) ) {
			$payload['description'] = sanitize_textarea_field( (string) $data['description'] );
		}

		if ( $payload ) {
			$this->projects->update( $project_id, $payload );
		}

		return $this->projects->find( $project_id );
	}

	public function archive( int $user_id, int $project_id ): true|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage projects.', 'etwa' ), array( 'status' => 403 ) );
		}
		if ( ! $this->projects->find( $project_id ) ) {
			return new WP_Error( 'not_found', __( 'Project not found.', 'etwa' ), array( 'status' => 404 ) );
		}
		$this->projects->archive( $project_id );
		return true;
	}

	public function validate_for_entry( int $project_id, ?int $client_id ): true|WP_Error {
		if ( $project_id <= 0 ) {
			return true;
		}
		$project = $this->projects->find( $project_id );
		if ( ! $project || ! $project->is_active() ) {
			return new WP_Error( 'invalid', __( 'Project not found or inactive.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $project->is_standalone() ) {
			if ( $client_id && $client_id > 0 ) {
				return new WP_Error( 'invalid', __( 'Internal projects cannot be assigned to a client.', 'etwa' ), array( 'status' => 400 ) );
			}
			return true;
		}
		if ( ! $client_id || (int) $project->client_id !== $client_id ) {
			return new WP_Error( 'invalid', __( 'Project does not belong to the selected client.', 'etwa' ), array( 'status' => 400 ) );
		}
		return true;
	}
}
