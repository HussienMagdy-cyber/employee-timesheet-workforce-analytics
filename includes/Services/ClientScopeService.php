<?php
/**
 * Client scope service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\ProjectRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientScopeService {

	public function __construct(
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private DeliverableMetricsService $metrics = new DeliverableMetricsService(),
	) {}

	/**
	 * @param array{client_id?:int,status?:string,search?:string} $filters
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public function list_with_metrics( int $user_id, array $filters = array() ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view deliverable scopes.', 'etwa' ), array( 'status' => 403 ) );
		}

		$items = array();
		foreach ( $this->scopes->list( $filters ) as $scope ) {
			$client  = $this->clients->find( (int) $scope->client_id );
			$project = $scope->project_id ? $this->projects->find( (int) $scope->project_id ) : null;
			$metrics = $this->metrics->scope_metrics( (int) $scope->id );
			$items[] = array(
				'scope'   => $scope->to_array(),
				'client'  => $client ? $client->to_array() : null,
				'project' => $project ? $project->to_array() : null,
				'metrics' => is_wp_error( $metrics ) ? null : $metrics,
			);
		}
		return $items;
	}

	/** @return array<string,mixed>|WP_Error */
	public function details( int $user_id, int $scope_id ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view deliverable scopes.', 'etwa' ), array( 'status' => 403 ) );
		}
		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope ) {
			return new WP_Error( 'not_found', __( 'Scope not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$client  = $this->clients->find( (int) $scope->client_id );
		$project = $scope->project_id ? $this->projects->find( (int) $scope->project_id ) : null;
		$metrics = $this->metrics->scope_metrics( $scope_id );
		if ( is_wp_error( $metrics ) ) {
			return $metrics;
		}

		return array(
			'scope'                 => $scope->to_array(),
			'client'                => $client ? $client->to_array() : null,
			'project'               => $project ? $project->to_array() : null,
			'contract_deliverables' => (float) $scope->contract_deliverables,
			'patches'               => array(),
			'metrics'               => $metrics,
		);
	}

	/** @param array<string,mixed> $data */
	public function create( int $user_id, array $data ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage deliverable scopes.', 'etwa' ), array( 'status' => 403 ) );
		}

		$client_id = (int) ( $data['client_id'] ?? 0 );
		$name      = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( $client_id <= 0 || '' === $name ) {
			return new WP_Error( 'invalid', __( 'Client and name are required.', 'etwa' ), array( 'status' => 400 ) );
		}
		if ( ! $this->clients->find( $client_id ) ) {
			return new WP_Error( 'invalid', __( 'Client not found.', 'etwa' ), array( 'status' => 400 ) );
		}

		$contract = $this->parse_contract_deliverables( $data );
		if ( is_wp_error( $contract ) ) {
			return $contract;
		}

		$project_id = null;
		if ( ! empty( $data['project_id'] ) ) {
			$check = ( new ProjectService() )->validate_for_entry( (int) $data['project_id'], $client_id );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$project_id = (int) $data['project_id'];
		}

		$status = sanitize_key( (string) ( $data['status'] ?? 'active' ) );
		if ( ! in_array( $status, array( 'active', 'closed', 'archived' ), true ) ) {
			$status = 'active';
		}

		$id = $this->scopes->create(
			array(
				'client_id'             => $client_id,
				'project_id'            => $project_id,
				'name'                  => $name,
				'code'                  => null,
				'description'           => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'status'                => $status,
				'starts_on'             => $this->nullable_date( $data['starts_on'] ?? null ),
				'ends_on'               => $this->nullable_date( $data['ends_on'] ?? null ),
				'contract_deliverables' => $contract,
				'package_id'            => null,
				'package_snapshot'      => null,
				'created_by'            => $user_id,
			)
		);

		$code = $this->generate_scope_code( $id );
		$this->scopes->update( $id, array( 'code' => $code ) );

		if ( 'active' === $status ) {
			DeliverableNotificationService::notify_content_delivery_if_active( $id );
		}

		return $this->details( $user_id, $id );
	}

	/** @param array<string,mixed> $data */
	public function update( int $user_id, int $scope_id, array $data ): array|WP_Error {
		if ( ! user_can( $user_id, RoleManager::CAP_MANAGE_DELIVERABLE_SCOPES ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot manage deliverable scopes.', 'etwa' ), array( 'status' => 403 ) );
		}
		$existing = $this->scopes->find( $scope_id );
		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Scope not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$payload = array();
		if ( isset( $data['client_id'] ) ) {
			$cid = (int) $data['client_id'];
			if ( ! $this->clients->find( $cid ) ) {
				return new WP_Error( 'invalid', __( 'Client not found.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['client_id'] = $cid;
		}
		if ( array_key_exists( 'project_id', $data ) ) {
			$pid = (int) $data['project_id'];
			if ( $pid > 0 ) {
				$check = ( new ProjectService() )->validate_for_entry( $pid, (int) ( $payload['client_id'] ?? $existing->client_id ) );
				if ( is_wp_error( $check ) ) {
					return $check;
				}
				$payload['project_id'] = $pid;
			} else {
				$payload['project_id'] = null;
			}
		}
		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( (string) $data['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'invalid', __( 'Name is required.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['name'] = $name;
		}
		if ( isset( $data['description'] ) ) {
			$payload['description'] = sanitize_textarea_field( (string) $data['description'] );
		}
		if ( isset( $data['status'] ) ) {
			$status = sanitize_key( (string) $data['status'] );
			if ( ! in_array( $status, array( 'active', 'closed', 'archived' ), true ) ) {
				return new WP_Error( 'invalid', __( 'Invalid scope status.', 'etwa' ), array( 'status' => 400 ) );
			}
			$payload['status'] = $status;
		}
		if ( array_key_exists( 'starts_on', $data ) ) {
			$payload['starts_on'] = $this->nullable_date( $data['starts_on'] );
		}
		if ( array_key_exists( 'ends_on', $data ) ) {
			$payload['ends_on'] = $this->nullable_date( $data['ends_on'] );
		}
		if ( array_key_exists( 'contract_deliverables', $data ) ) {
			$contract = $this->parse_contract_deliverables( $data );
			if ( is_wp_error( $contract ) ) {
				return $contract;
			}
			$payload['contract_deliverables'] = $contract;
		}

		$payload['patch_count']        = 0;
		$payload['expected_per_patch'] = 0;

		if ( $payload ) {
			$this->scopes->update( $scope_id, $payload );
		}

		$new_status = $payload['status'] ?? $existing->status;
		if ( 'active' === $new_status && 'active' !== $existing->status ) {
			DeliverableNotificationService::notify_content_delivery_if_active( $scope_id );
		}

		return $this->details( $user_id, $scope_id );
	}

	/**
	 * @param array<int,array<string,mixed>> $lines
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_contract_lines( int $user_id, int $scope_id, array $lines ): array|WP_Error {
		unset( $user_id, $scope_id, $lines );
		return new WP_Error(
			'gone',
			__( 'Contract line distribution has been retired. Set total expected deliverables on the scope instead.', 'etwa' ),
			array( 'status' => 410 )
		);
	}

	/** @param array<string,mixed> $data */
	private function parse_contract_deliverables( array $data ): float|WP_Error {
		if ( ! array_key_exists( 'contract_deliverables', $data ) ) {
			return new WP_Error(
				'invalid',
				__( 'Total expected deliverables is required.', 'etwa' ),
				array( 'status' => 400 )
			);
		}
		$value = max( 0.0, (float) $data['contract_deliverables'] );
		return $value;
	}

	private function generate_scope_code( int $id ): string {
		return sprintf( 'SCP-%s-%04d', gmdate( 'Ymd' ), $id );
	}

	private function nullable_date( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$date = sanitize_text_field( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
	}
}
