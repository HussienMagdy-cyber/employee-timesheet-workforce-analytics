<?php
/**
 * Audit log repository.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuditLogRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_audit_logs';
	}

	public function is_enabled(): bool {
		return '1' === (string) ( new SettingsRepository() )->get( 'enable_audit_logs', '1' );
	}

	/**
	 * @param array{edit_reason?:string,employee_id?:int,editor_role?:string,operation?:string}|null $context
	 */
	public function log(
		string $action,
		string $entity_type,
		int $entity_id,
		?array $old_value = null,
		?array $new_value = null,
		?int $user_id = null,
		?array $context = null
	): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		global $wpdb;

		$row = array(
			'user_id'     => $user_id ?? get_current_user_id(),
			'action'      => $action,
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
			'old_value'   => $old_value ? wp_json_encode( $old_value ) : null,
			'new_value'   => $new_value ? wp_json_encode( $new_value ) : null,
			'ip_address'  => $this->get_client_ip(),
			'created_at'  => current_time( 'mysql' ),
		);

		if ( $context ) {
			if ( ! empty( $context['employee_id'] ) ) {
				$row['employee_id'] = (int) $context['employee_id'];
			}
			if ( ! empty( $context['edit_reason'] ) ) {
				$row['edit_reason'] = sanitize_textarea_field( (string) $context['edit_reason'] );
			}
			if ( ! empty( $context['editor_role'] ) ) {
				$row['editor_role'] = sanitize_text_field( (string) $context['editor_role'] );
			}
		}

		$wpdb->insert( $this->table, $row );
	}

	/** @return object[] */
	public function find( int $limit = 50, int $offset = 0, ?string $entity_type = null ): array {
		global $wpdb;

		if ( $entity_type ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table} WHERE entity_type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$entity_type,
					$limit,
					$offset
				)
			) ?: array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		) ?: array();
	}

	public function count( ?string $entity_type = null ): int {
		global $wpdb;

		if ( $entity_type ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE entity_type = %s",
					$entity_type
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/** @return string[] */
	public function distinct_entity_types(): array {
		global $wpdb;

		$col = $wpdb->get_col( "SELECT DISTINCT entity_type FROM {$this->table} ORDER BY entity_type ASC" );

		return array_values(
			array_filter(
				array_map( 'strval', $col ?: array() ),
				static fn( string $type ) => '' !== $type
			)
		);
	}

	/** @return object[] */
	public function recent( int $limit = 50, ?string $entity_type = null ): array {
		return $this->find( $limit, 0, $entity_type );
	}

	private function get_client_ip(): string {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				return sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			}
		}
		return '';
	}
}
