<?php
/**
 * Shared audit log view data.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Repositories\AuditLogRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuditLogViewData {

	/** @return array<string,mixed> */
	public static function prepare( int $page = 1, int $per_page = 50, ?string $entity_type = null ): array {
		$repo   = new AuditLogRepository();
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$types  = self::entity_type_options( $repo->distinct_entity_types() );

		if ( $entity_type && ! isset( $types[ $entity_type ] ) ) {
			$entity_type = null;
		}

		return array(
			'logs'         => $repo->find( $per_page, $offset, $entity_type ),
			'total'        => $repo->count( $entity_type ),
			'page'         => $page,
			'per_page'     => $per_page,
			'entity_type'  => $entity_type,
			'enabled'      => $repo->is_enabled(),
			'entity_types'  => array_keys( $types ),
			'entity_labels' => $types,
		);
	}

	/** @param string[] $stored @return array<string,string> */
	public static function entity_type_options( array $stored = array() ): array {
		$known = self::known_entity_labels();
		foreach ( $stored as $type ) {
			$type = sanitize_key( $type );
			if ( '' === $type || isset( $known[ $type ] ) ) {
				continue;
			}
			$known[ $type ] = self::humanize( $type );
		}

		return $known;
	}

	public static function type_label( string $type ): string {
		$labels = self::known_entity_labels();
		return $labels[ $type ] ?? self::humanize( $type );
	}

	public static function action_label( string $action ): string {
		$labels = array(
			'create'                       => __( 'Created', 'etwa' ),
			'update'                       => __( 'Updated', 'etwa' ),
			'delete'                       => __( 'Deleted', 'etwa' ),
			'submit'                       => __( 'Submitted', 'etwa' ),
			'deactivate'                   => __( 'Deactivated', 'etwa' ),
			'hr_edit'                      => __( 'HR edit', 'etwa' ),
			'mark_won'                     => __( 'Marked won', 'etwa' ),
			'mark_lost'                    => __( 'Marked lost', 'etwa' ),
			'prospect_converted_to_client' => __( 'Converted to client', 'etwa' ),
			'client_converted_to_project'  => __( 'Converted to project', 'etwa' ),
			'timesheet_approved'           => __( 'Timesheet approved', 'etwa' ),
			'timesheet_rejected'           => __( 'Timesheet rejected', 'etwa' ),
		);

		return $labels[ $action ] ?? self::humanize( $action );
	}

	public static function entity_summary( object $log ): string {
		$type = (string) ( $log->entity_type ?? '' );
		$id   = (int) ( $log->entity_id ?? 0 );
		$name = self::payload_name( $log );
		$label = self::type_label( $type );

		if ( '' !== $name ) {
			return sprintf( '%s: %s (#%d)', $label, $name, $id );
		}

		return sprintf( '%s #%d', $label, $id );
	}

	private static function payload_name( object $log ): string {
		foreach ( array( $log->new_value ?? null, $log->old_value ?? null ) as $json ) {
			if ( ! is_string( $json ) || '' === $json ) {
				continue;
			}
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			foreach ( array( 'name', 'client_name', 'title', 'code' ) as $key ) {
				if ( ! empty( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
					return sanitize_text_field( (string) $data[ $key ] );
				}
			}
		}

		return '';
	}

	/** @return array<string,string> */
	private static function known_entity_labels(): array {
		return array(
			'timesheet'            => __( 'Timesheet', 'etwa' ),
			'entry'                => __( 'Entry', 'etwa' ),
			'overtime'             => __( 'Overtime', 'etwa' ),
			'client'               => __( 'Client', 'etwa' ),
			'prospect'             => __( 'Prospect', 'etwa' ),
			'employee'             => __( 'Employee', 'etwa' ),
			'department'           => __( 'Department', 'etwa' ),
			'holiday'              => __( 'Holiday', 'etwa' ),
			'calendar'             => __( 'Calendar', 'etwa' ),
			'period'               => __( 'Period', 'etwa' ),
			'vacation_record'      => __( 'Vacation record', 'etwa' ),
			'vacation_allocation'  => __( 'Vacation allocation', 'etwa' ),
			'vacation_request'     => __( 'Vacation request', 'etwa' ),
			'vacation_adjustment'  => __( 'Vacation adjustment', 'etwa' ),
			'wfh'                  => __( 'WFH', 'etwa' ),
			'permission'           => __( 'Permission', 'etwa' ),
			'holiday_work'         => __( 'Holiday work', 'etwa' ),
			'business_trip'        => __( 'Business trip', 'etwa' ),
			'transportation'       => __( 'Transportation', 'etwa' ),
			'job_request'          => __( 'Job request', 'etwa' ),
			'scope'                => __( 'Scope', 'etwa' ),
			'patch'                => __( 'Scope patch', 'etwa' ),
			'project'              => __( 'Project', 'etwa' ),
		);
	}

	private static function humanize( string $value ): string {
		$label = ucwords( str_replace( '_', ' ', sanitize_key( $value ) ) );
		return '' !== $label ? $label : $value;
	}
}
