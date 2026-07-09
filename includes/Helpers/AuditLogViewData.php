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

		return array(
			'logs'         => $repo->find( $per_page, $offset, $entity_type ),
			'total'        => $repo->count( $entity_type ),
			'page'         => $page,
			'per_page'     => $per_page,
			'entity_type'  => $entity_type,
			'enabled'      => $repo->is_enabled(),
			'entity_types' => array( 'timesheet', 'entry', 'overtime', 'client', 'employee', 'holiday', 'calendar', 'period', 'vacation_record', 'vacation_allocation', 'vacation_request' ),
		);
	}
}
