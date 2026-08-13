<?php
/**
 * Notification log repository (dedup store).
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NotificationLogRepository {

	private string $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'etwa_notification_log';
	}

	public function was_sent( string $type, string $entity_type, int $entity_id, string $threshold_key = '' ): bool {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE notification_type = %s AND entity_type = %s AND entity_id = %d AND threshold_key = %s",
				$type,
				$entity_type,
				$entity_id,
				$threshold_key
			)
		) > 0;
	}

	public function log_sent( string $type, string $entity_type, int $entity_id, string $threshold_key = '', string $recipient_hash = '' ): bool {
		global $wpdb;
		$result = $wpdb->insert(
			$this->table,
			array(
				'notification_type' => $type,
				'entity_type'         => $entity_type,
				'entity_id'           => $entity_id,
				'threshold_key'       => $threshold_key,
				'recipient_hash'      => $recipient_hash,
				'sent_at'             => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return false !== $result;
	}
}
