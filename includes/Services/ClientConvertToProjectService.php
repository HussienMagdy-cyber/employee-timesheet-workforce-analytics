<?php
/**
 * Transactional client-to-project conversion service.
 *
 * Dependency audit (client_id / project_id usage):
 * - etwa_entries: client_id + optional project_id — direct hours get parent client + new project;
 *   hours on source child projects keep project_id, client_id moves to parent.
 * - etwa_client_scopes: same pattern; allocations/patches/addons chain via scope_id only (no row updates).
 * - etwa_projects: source child projects reassign client_id to parent (siblings, not nested).
 * - etwa_deliverable_allocations / items: no client FK; preserved via scope migration.
 * - Analytics: client totals by entries.client_id; project breakdown by entries.project_id under client.
 * - etwa_requests extra JSON may reference client_id — not auto-migrated (listed in preview warnings).
 * - Deactivated source client remains in DB for audit; excluded from active dropdowns via is_active = 0.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\Project;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ProjectRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientConvertToProjectService {

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function preview( int $user_id, int $source_client_id, int $parent_client_id ): array|WP_Error {
		$validation = $this->validate( $user_id, $source_client_id, $parent_client_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$source = $this->clients->find( $source_client_id );
		$parent = $this->clients->find( $parent_client_id );
		if ( ! $source || ! $parent ) {
			return new WP_Error( 'invalid', __( 'Client not found.', 'etwa' ), array( 'status' => 400 ) );
		}

		$child_projects = $this->projects->list_for_client( $source_client_id, false );
		$conflicts      = $this->duplicate_project_conflicts( $parent_client_id, $source->name, $child_projects );
		$counts         = $this->build_counts( $source_client_id );

		return array(
			'source_client'        => $source->to_array(),
			'parent_client'        => $parent->to_array(),
			'new_project_name'     => $source->name,
			'counts'               => $counts,
			'child_projects'       => array_map(
				static fn( Project $p ) => array(
					'id'     => $p->id,
					'name'   => $p->name,
					'status' => $p->status,
				),
				$child_projects
			),
			'duplicate_conflicts'  => $conflicts,
			'cannot_migrate'       => $this->unmigratable_notes( $source_client_id ),
			'can_convert'          => empty( $conflicts ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function convert( int $user_id, int $source_client_id, int $parent_client_id ): array|WP_Error {
		$preview = $this->preview( $user_id, $source_client_id, $parent_client_id );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		if ( empty( $preview['can_convert'] ) ) {
			return new WP_Error(
				'duplicate_project',
				__( 'Resolve duplicate project name conflicts before converting.', 'etwa' ),
				array( 'status' => 400 )
			);
		}

		$source = $this->clients->find( $source_client_id );
		if ( ! $source ) {
			return new WP_Error( 'invalid', __( 'Source client not found.', 'etwa' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$stats = array(
			'project_id'              => 0,
			'entries_direct_moved'    => 0,
			'entries_project_moved'     => 0,
			'scopes_direct_moved'     => 0,
			'scopes_project_moved'      => 0,
			'child_projects_moved'      => 0,
			'allocations_preserved'     => $this->clients->allocation_count_for_client( $source_client_id ),
		);

		try {
			$project_id = $this->projects->create(
				array(
					'client_id'   => $parent_client_id,
					'name'        => $source->name,
					'description' => sprintf(
						/* translators: %s: former client code */
						__( 'Converted from client %s', 'etwa' ),
						$source->code
					),
					'status'      => Project::STATUS_ACTIVE,
				)
			);
			if ( $project_id <= 0 ) {
				throw new \RuntimeException( 'Failed to create project from client.' );
			}
			$stats['project_id'] = $project_id;

			$stats['child_projects_moved'] = $this->projects->reassign_client( $source_client_id, $parent_client_id );

			$stats['entries_direct_moved'] = $this->clients->convert_reassign_entries_direct(
				$source_client_id,
				$parent_client_id,
				$project_id
			);
			$stats['entries_project_moved'] = $this->clients->convert_reassign_entries_with_project(
				$source_client_id,
				$parent_client_id
			);

			$stats['scopes_direct_moved'] = $this->clients->convert_reassign_scopes_direct(
				$source_client_id,
				$parent_client_id,
				$project_id
			);
			$stats['scopes_project_moved'] = $this->clients->convert_reassign_scopes_with_project(
				$source_client_id,
				$parent_client_id
			);

			if ( ! $this->clients->deactivate( $source_client_id ) ) {
				throw new \RuntimeException( 'Failed to deactivate source client.' );
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'convert_failed',
				__( 'Client conversion failed and was rolled back.', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		$this->audit->log(
			'client_converted_to_project',
			'client',
			$parent_client_id,
			array(
				'source_client_id' => $source_client_id,
				'source_name'      => $source->name,
				'source_code'      => $source->code,
			),
			array_merge(
				$stats,
				array(
					'parent_client_id' => $parent_client_id,
					'project_id'       => $stats['project_id'],
				)
			)
		);

		ClientAnalyticsService::flush_cache();
		AnalyticsService::flush_dashboard_cache();

		return array_merge(
			$stats,
			array(
				'source_client_id' => $source_client_id,
				'parent_client_id' => $parent_client_id,
				'project'          => $this->projects->find( $stats['project_id'] )?->to_array(),
				'source_deactivated' => true,
			)
		);
	}

	private function validate( int $user_id, int $source_client_id, int $parent_client_id ): true|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot convert clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		if ( $source_client_id <= 0 || $parent_client_id <= 0 ) {
			return new WP_Error( 'invalid', __( 'Source and parent clients are required.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( $source_client_id === $parent_client_id ) {
			return new WP_Error( 'invalid', __( 'Source client cannot be converted into itself.', 'etwa' ), array( 'status' => 400 ) );
		}

		$source = $this->clients->find( $source_client_id );
		if ( ! $source || ! $source->is_active ) {
			return new WP_Error( 'invalid', __( 'Source client not found or inactive.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->clients->is_system_client( $source ) ) {
			return new WP_Error( 'invalid', __( 'System clients cannot be converted.', 'etwa' ), array( 'status' => 400 ) );
		}

		$parent = $this->clients->find( $parent_client_id );
		if ( ! $parent || ! $parent->is_active ) {
			return new WP_Error( 'invalid', __( 'Parent client not found or inactive.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->clients->is_system_client( $parent ) ) {
			return new WP_Error( 'invalid', __( 'System clients cannot be conversion targets.', 'etwa' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/** @return array<string,int> */
	private function build_counts( int $source_client_id ): array {
		$entries_total  = $this->clients->entry_count( $source_client_id );
		$entries_direct = $this->clients->entry_count_direct( $source_client_id );
		$scopes_total   = $this->clients->scope_count( $source_client_id );
		$scopes_direct  = $this->clients->scope_count_direct( $source_client_id );

		return array(
			'entries'                   => $entries_total,
			'entries_direct'            => $entries_direct,
			'entries_on_child_projects' => max( 0, $entries_total - $entries_direct ),
			'scopes'                    => $scopes_total,
			'scopes_direct'             => $scopes_direct,
			'scopes_on_child_projects'  => max( 0, $scopes_total - $scopes_direct ),
			'allocations'               => $this->clients->allocation_count_for_client( $source_client_id ),
			'child_projects'            => $this->projects->count_for_client( $source_client_id ),
		);
	}

	/**
	 * @param Project[] $child_projects
	 * @return array<int,array{name:string,reason:string}>
	 */
	private function duplicate_project_conflicts( int $parent_client_id, string $new_project_name, array $child_projects ): array {
		$parent_names = $this->projects->active_name_keys_for_client( $parent_client_id );
		$conflicts    = array();

		$new_key = strtolower( trim( $new_project_name ) );
		if ( '' !== $new_key && isset( $parent_names[ $new_key ] ) ) {
			$conflicts[] = array(
				'name'   => $new_project_name,
				'reason' => __( 'Parent client already has an active project with this name.', 'etwa' ),
			);
		}

		foreach ( $child_projects as $child ) {
			if ( ! $child->is_active() ) {
				continue;
			}
			$key = strtolower( trim( $child->name ) );
			if ( '' === $key || ! isset( $parent_names[ $key ] ) ) {
				continue;
			}
			$conflicts[] = array(
				'name'   => $child->name,
				'reason' => __( 'A child project name already exists under the parent client.', 'etwa' ),
			);
		}

		return $conflicts;
	}

	/** @return array<int,array{entity:string,note:string,count?:int}> */
	private function unmigratable_notes( int $source_client_id ): array {
		$notes = array();

		$request_count = $this->clients->request_count_referencing_client( $source_client_id );
		if ( $request_count > 0 ) {
			$notes[] = array(
				'entity' => 'requests',
				'note'   => __( 'Pending or historical requests store client_id in JSON metadata and are not updated automatically.', 'etwa' ),
				'count'  => $request_count,
			);
		}

		$draft_count = $this->clients->draft_snapshot_count_referencing_client( $source_client_id );
		if ( $draft_count > 0 ) {
			$notes[] = array(
				'entity' => 'timesheet_drafts',
				'note'   => __( 'Draft/rejected timesheet snapshots may still reference the source client until edited and resubmitted.', 'etwa' ),
				'count'  => $draft_count,
			);
		}

		$notes[] = array(
			'entity' => 'source_client_record',
			'note'   => __( 'The source client record is deactivated, not deleted, for audit history.', 'etwa' ),
		);

		return $notes;
	}
}
