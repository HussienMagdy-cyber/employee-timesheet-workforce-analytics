<?php
/**
 * Transactional client merge service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\AuditLogRepository;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ProjectRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientMergeService {

	public function __construct(
		private ClientRepository $clients = new ClientRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private AuditLogRepository $audit = new AuditLogRepository(),
	) {}

	/**
	 * @param int[] $merge_ids
	 * @return array<string,mixed>|WP_Error
	 */
	public function preview( int $user_id, int $primary_id, array $merge_ids ): array|WP_Error {
		$validation = $this->validate_merge( $user_id, $primary_id, $merge_ids );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$counts = array();
		foreach ( $merge_ids as $mid ) {
			$counts[] = array(
				'client_id'   => $mid,
				'client_name' => $this->clients->find( $mid )?->name ?? '',
				'entries'     => $this->clients->entry_count( $mid ),
				'scopes'      => $this->clients->scope_count( $mid ),
				'projects'    => $this->projects->count_for_client( $mid ),
			);
		}

		$primary = $this->clients->find( $primary_id );

		return array(
			'primary' => $primary ? $primary->to_array() : null,
			'merge'   => $counts,
		);
	}

	/**
	 * @param int[] $merge_ids
	 * @return array<string,mixed>|WP_Error
	 */
	public function merge( int $user_id, int $primary_id, array $merge_ids ): array|WP_Error {
		$validation = $this->validate_merge( $user_id, $primary_id, $merge_ids );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		$stats = array(
			'entries_moved'  => 0,
			'scopes_moved'   => 0,
			'projects_moved' => 0,
			'clients_merged' => count( $merge_ids ),
		);

		try {
			foreach ( $merge_ids as $mid ) {
				$stats['entries_moved']  += $this->clients->reassign_client( $mid, $primary_id );
				$stats['scopes_moved']   += $this->clients->reassign_scopes( $mid, $primary_id );
				$stats['projects_moved'] += $this->projects->reassign_client( $mid, $primary_id );
				if ( ! $this->clients->update( $mid, array( 'is_active' => 0 ) ) ) {
					throw new \RuntimeException( 'Failed to deactivate merged client.' );
				}
			}
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'merge_failed', __( 'Client merge failed and was rolled back.', 'etwa' ), array( 'status' => 500 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );

		$this->audit->log(
			'client_merged',
			'client',
			$primary_id,
			null,
			array(
				'primary_id' => $primary_id,
				'merge_ids'  => $merge_ids,
				'stats'      => $stats,
			)
		);

		ClientAnalyticsService::flush_cache();
		AnalyticsService::flush_dashboard_cache();

		return array_merge( $stats, array( 'primary_id' => $primary_id ) );
	}

	/**
	 * @param int[] $merge_ids
	 */
	private function validate_merge( int $user_id, int $primary_id, array $merge_ids ): true|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot merge clients.', 'etwa' ), array( 'status' => 403 ) );
		}

		$merge_ids = array_values( array_unique( array_filter( array_map( 'intval', $merge_ids ) ) ) );
		if ( ! $merge_ids ) {
			return new WP_Error( 'invalid', __( 'Select at least one client to merge.', 'etwa' ), array( 'status' => 400 ) );
		}

		if ( in_array( $primary_id, $merge_ids, true ) ) {
			return new WP_Error( 'invalid', __( 'Primary client cannot be in the merge list.', 'etwa' ), array( 'status' => 400 ) );
		}

		$primary = $this->clients->find( $primary_id );
		if ( ! $primary || ! $primary->is_active ) {
			return new WP_Error( 'invalid', __( 'Primary client not found or inactive.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $this->clients->is_system_client( $primary ) ) {
			return new WP_Error( 'invalid', __( 'System clients cannot be merge targets.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( $primary->is_super() ) {
			return new WP_Error( 'invalid', __( 'Products cannot be merge targets.', 'etwa' ), array( 'status' => 400 ) );
		}

		foreach ( $merge_ids as $mid ) {
			$client = $this->clients->find( $mid );
			if ( ! $client ) {
				return new WP_Error( 'invalid', __( 'One or more merge clients not found.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( $this->clients->is_system_client( $client ) ) {
				return new WP_Error( 'invalid', __( 'System clients cannot be merged.', 'etwa' ), array( 'status' => 400 ) );
			}
			if ( $client->is_super() ) {
				return new WP_Error( 'invalid', __( 'Products cannot be merged.', 'etwa' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}
}
