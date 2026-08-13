<?php
/**
 * Client Files — scopes grouped by client for the Files module.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableFilesService {

	public function __construct(
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private DeliverableMetricsService $metrics = new DeliverableMetricsService(),
	) {}

	/**
	 * List client files (clients with ≥1 scope), or a single file when client_id is set.
	 *
	 * @param array{search?:string,client_id?:int} $filters
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_files( int $user_id, array $filters = array() ): array|WP_Error {
		if ( ! RoleManager::user_can_view_deliverables( $user_id ) ) {
			return new WP_Error( 'forbidden', __( 'Cannot view deliverable files.', 'etwa' ), array( 'status' => 403 ) );
		}

		$scope_filters = array();
		if ( ! empty( $filters['client_id'] ) ) {
			$scope_filters['client_id'] = (int) $filters['client_id'];
		}

		$grouped = array();
		foreach ( $this->scopes->list( $scope_filters ) as $scope ) {
			$cid = (int) $scope->client_id;
			if ( $cid <= 0 ) {
				continue;
			}
			if ( ! isset( $grouped[ $cid ] ) ) {
				$grouped[ $cid ] = array();
			}
			$metrics = $this->metrics->scope_metrics( (int) $scope->id );
			$grouped[ $cid ][] = array(
				'scope'   => $scope->to_array(),
				'metrics' => is_wp_error( $metrics ) ? $this->empty_metrics() : $metrics,
			);
		}

		$search = isset( $filters['search'] ) ? strtolower( trim( (string) $filters['search'] ) ) : '';
		$files  = array();

		foreach ( $grouped as $client_id => $scope_items ) {
			$client = $this->clients->find( (int) $client_id );
			if ( ! $client ) {
				continue;
			}

			$file = $this->build_file_payload( $client->to_array(), $scope_items );

			if ( '' !== $search ) {
				$hay = strtolower(
					(string) ( $file['client']['name'] ?? '' ) . ' ' .
					(string) ( $file['client']['code'] ?? '' )
				);
				foreach ( $scope_items as $row ) {
					$s = $row['scope'] ?? array();
					$hay .= ' ' . strtolower( (string) ( $s['name'] ?? '' ) . ' ' . (string) ( $s['code'] ?? '' ) );
				}
				if ( ! str_contains( $hay, $search ) ) {
					continue;
				}
			}

			$files[] = $file;
		}

		usort(
			$files,
			static function ( array $a, array $b ): int {
				return strcasecmp( (string) ( $a['client']['name'] ?? '' ), (string) ( $b['client']['name'] ?? '' ) );
			}
		);

		if ( ! empty( $filters['client_id'] ) ) {
			if ( ! $files ) {
				return new WP_Error( 'not_found', __( 'Client file not found or has no scopes.', 'etwa' ), array( 'status' => 404 ) );
			}
			return array(
				'file' => $files[0],
			);
		}

		return array(
			'items' => $files,
			'total' => count( $files ),
		);
	}

	/**
	 * @param array<string,mixed>              $client
	 * @param array<int,array<string,mixed>>   $scope_items
	 * @return array<string,mixed>
	 */
	private function build_file_payload( array $client, array $scope_items ): array {
		$scope_count        = count( $scope_items );
		$active_scope_count = 0;
		$contract_total     = 0.0;
		$planned            = 0.0;
		$delivered          = 0.0;
		$remaining          = 0.0;
		$extra              = 0.0;
		$effort             = 0.0;
		$scopes_out         = array();

		foreach ( $scope_items as $row ) {
			$scope   = $row['scope'] ?? array();
			$metrics = $row['metrics'] ?? $this->empty_metrics();
			if ( ( $scope['status'] ?? '' ) === 'active' ) {
				++$active_scope_count;
			}
			$contract_total += (float) ( $scope['contract_deliverables'] ?? 0 );
			$planned        += (float) ( $metrics['planned_qty'] ?? 0 );
			$delivered      += (float) ( $metrics['completed_qty'] ?? 0 );
			$remaining      += (float) ( $metrics['remaining_qty'] ?? 0 );
			$extra          += (float) ( $metrics['extra_qty'] ?? 0 );
			$effort         += (float) ( $metrics['effort_score'] ?? 0 );

			$completion = (float) ( $metrics['completion_pct'] ?? 0 );
			$scopes_out[] = array(
				'scope'   => $scope,
				'metrics' => $metrics,
				// Convenience aliases for Files UI.
				'planned_effort'   => round( (float) ( $metrics['planned_qty'] ?? 0 ), 2 ),
				'completed_effort' => round( (float) ( $metrics['effort_score'] ?? 0 ), 2 ),
				'effort_score'     => round( (float) ( $metrics['effort_score'] ?? 0 ), 2 ),
				'exceeded'         => $completion > 100,
			);
		}

		$completion_pct = $planned > 0 ? round( ( $delivered / $planned ) * 100, 2 ) : 0.0;

		return array(
			'client' => $client,
			'totals' => array(
				'scope_count'           => $scope_count,
				'active_scope_count'    => $active_scope_count,
				'contract_deliverables' => round( $contract_total, 2 ),
				'planned_qty'           => round( $planned, 2 ),
				'delivered_qty'         => round( $delivered, 2 ),
				'remaining_qty'         => round( $remaining, 2 ),
				'extra_qty'             => round( $extra, 2 ),
				'completion_pct'        => $completion_pct,
				'effort_score'          => round( $effort, 2 ),
				'exceeded'              => $completion_pct > 100,
			),
			'scopes' => $scopes_out,
		);
	}

	/** @return array<string,float|int> */
	private function empty_metrics(): array {
		return array(
			'planned_qty'     => 0.0,
			'completed_qty'   => 0.0,
			'normal_qty'      => 0.0,
			'replacement_qty' => 0.0,
			'extra_qty'       => 0.0,
			'remaining_qty'   => 0.0,
			'effort_score'    => 0.0,
			'completion_pct'  => 0.0,
		);
	}
}
