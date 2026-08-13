<?php
/**
 * Deliverable email notifications.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\RoleManager;
use ETWA\Models\ClientScope;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\NotificationLogRepository;
use ETWA\Repositories\ProjectRepository;
use ETWA\Repositories\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DeliverableNotificationService {

	public const TYPE_CONTENT_DELIVERY = 'content_delivery';
	public const TYPE_SCOPE_EXPIRATION   = 'scope_expiration';
	public const TYPE_SCOPE_COMPLETED    = 'scope_completed';

	public function __construct(
		private MailService $mail = new MailService(),
		private SettingsRepository $settings = new SettingsRepository(),
		private NotificationLogRepository $log = new NotificationLogRepository(),
		private ClientScopeRepository $scopes = new ClientScopeRepository(),
		private ClientRepository $clients = new ClientRepository(),
		private ProjectRepository $projects = new ProjectRepository(),
		private DeliverableMetricsService $metrics = new DeliverableMetricsService(),
	) {
		add_action( 'etwa_scope_content_delivery_required', array( $this, 'on_content_delivery_required' ), 10, 1 );
	}

	public function register_hooks(): void {
		// Constructor registers content delivery hook; cron/completion called directly.
	}

	public function on_content_delivery_required( int $scope_id ): void {
		if ( $this->log->was_sent( self::TYPE_CONTENT_DELIVERY, 'scope', $scope_id ) ) {
			return;
		}

		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope || 'active' !== $scope->status || $scope->contract_deliverables <= 0 ) {
			return;
		}

		$recipients = $this->allocator_recipients();
		if ( ! $recipients ) {
			return;
		}

		$context = $this->scope_context( $scope );
		$content = sprintf(
			/* translators: %s: scope name */
			__( '<strong>Content Delivery Required</strong><br><br>Client: %1$s<br>Project: %2$s<br>Scope: %3$s<br>Expected Delivery: %4$s<br>Contract: %5$s deliverables<br><br>Please record the delivered work in ETWA.', 'etwa' ),
			esc_html( $context['client_name'] ),
			esc_html( $context['project_name'] ?: '—' ),
			esc_html( $scope->name ),
			esc_html( $context['expected_delivery'] ?: '—' ),
			esc_html( (string) $scope->contract_deliverables )
		);

		$sent = false;
		foreach ( $recipients as $email ) {
			$sent = $this->mail->send(
				array(
					'to'      => $email,
					'subject' => __( 'Content Delivery Required', 'etwa' ),
					'content' => $content,
				)
			) || $sent;
		}

		if ( $sent ) {
			$this->log->log_sent( self::TYPE_CONTENT_DELIVERY, 'scope', $scope_id );
		}
	}

	public function process_expiration_alerts(): void {
		$thresholds = $this->expiration_thresholds();
		$today      = gmdate( 'Y-m-d' );

		foreach ( $this->scopes->list( array( 'status' => 'active' ) ) as $scope ) {
			if ( ! $scope->ends_on ) {
				continue;
			}
			$metrics = $this->metrics->scope_metrics( (int) $scope->id );
			if ( is_wp_error( $metrics ) || (float) ( $metrics['remaining_qty'] ?? 0 ) <= 0 ) {
				continue;
			}

			$days_until = (int) floor( ( strtotime( $scope->ends_on . ' UTC' ) - strtotime( $today . ' UTC' ) ) / DAY_IN_SECONDS );
			if ( ! in_array( $days_until, $thresholds, true ) ) {
				continue;
			}

			$key = (string) $days_until;
			if ( $this->log->was_sent( self::TYPE_SCOPE_EXPIRATION, 'scope', (int) $scope->id, $key ) ) {
				continue;
			}

			$context = $this->scope_context( $scope );
			$content = sprintf(
				__( '<strong>Scope Expiring Soon</strong> (%1$d days)<br><br>Client: %2$s<br>Project: %3$s<br>Scope: %4$s<br>Expiration: %5$s<br>Completion: %6$s%%<br>Delivered: %7$s<br>Remaining: %8$s<br>Extra: %9$s', 'etwa' ),
				$days_until,
				esc_html( $context['client_name'] ),
				esc_html( $context['project_name'] ?: '—' ),
				esc_html( $scope->name ),
				esc_html( $scope->ends_on ),
				esc_html( (string) ( $metrics['completion_pct'] ?? 0 ) ),
				esc_html( (string) ( $metrics['completed_qty'] ?? 0 ) ),
				esc_html( (string) ( $metrics['remaining_qty'] ?? 0 ) ),
				esc_html( (string) ( $metrics['extra_qty'] ?? 0 ) )
			);

			$sent = $this->send_hr_owner(
				sprintf(
					/* translators: %d: days until expiration */
					__( 'Scope Expiring in %d Days', 'etwa' ),
					$days_until
				),
				$content
			);

			if ( $sent ) {
				$this->log->log_sent( self::TYPE_SCOPE_EXPIRATION, 'scope', (int) $scope->id, $key );
			}
		}
	}

	public function maybe_notify_scope_completed( int $scope_id ): void {
		if ( $this->log->was_sent( self::TYPE_SCOPE_COMPLETED, 'scope', $scope_id ) ) {
			return;
		}

		$scope = $this->scopes->find( $scope_id );
		if ( ! $scope ) {
			return;
		}

		$metrics = $this->metrics->scope_metrics( $scope_id );
		if ( is_wp_error( $metrics ) ) {
			return;
		}

		$planned   = (float) ( $metrics['planned_qty'] ?? 0 );
		$remaining = (float) ( $metrics['remaining_qty'] ?? 0 );
		if ( $planned <= 0 || $remaining > 0 ) {
			return;
		}

		$context    = $this->scope_context( $scope );
		$completed  = (float) ( $metrics['completed_qty'] ?? 0 );
		$pct        = (float) ( $metrics['completion_pct'] ?? 0 );
		$extra      = (float) ( $metrics['extra_qty'] ?? 0 );
		$extra_line = $extra > 0
			? sprintf( '<br>Extra: +%s', esc_html( (string) $extra ) )
			: '';

		$content = sprintf(
			__( '<strong>Scope Completed</strong><br><br>Client: %1$s<br>Project: %2$s<br>Scope: %3$s<br>Contract: %4$s<br>Delivered: %5$s<br>Completion: %6$s%%%7$s', 'etwa' ),
			esc_html( $context['client_name'] ),
			esc_html( $context['project_name'] ?: '—' ),
			esc_html( $scope->name ),
			esc_html( (string) $planned ),
			esc_html( (string) $completed ),
			esc_html( (string) $pct ),
			$extra_line
		);

		if ( $this->send_hr_owner( __( 'Scope Completed', 'etwa' ), $content ) ) {
			$this->log->log_sent( self::TYPE_SCOPE_COMPLETED, 'scope', $scope_id );
		}
	}

	/** @return string[] */
	private function allocator_recipients(): array {
		$emails = array();
		$users  = get_users( array( 'fields' => array( 'ID', 'user_email' ) ) );
		foreach ( $users as $user ) {
			if ( ! RoleManager::user_can_allocate_deliverables( (int) $user->ID ) ) {
				continue;
			}
			$email = sanitize_email( (string) $user->user_email );
			if ( $email ) {
				$emails[ $email ] = $email;
			}
		}
		return array_values( $emails );
	}

	private function send_hr_owner( string $subject, string $content ): bool {
		$hr    = sanitize_email( (string) $this->settings->get( 'hr_email', get_option( 'admin_email' ) ) );
		$owner = sanitize_email( (string) $this->settings->get( 'owner_email', get_option( 'admin_email' ) ) );
		$sent  = false;

		if ( $hr ) {
			$sent = $this->mail->send(
				array(
					'to'      => $hr,
					'subject' => $subject,
					'content' => $content,
				)
			) || $sent;
		}
		if ( $owner && $owner !== $hr ) {
			$sent = $this->mail->send(
				array(
					'to'      => $owner,
					'subject' => $subject,
					'content' => $content,
				)
			) || $sent;
		}

		return $sent;
	}

	/** @return int[] */
	private function expiration_thresholds(): array {
		$raw = $this->settings->get( 'deliverable_expiration_thresholds', array( 7, 3, 1 ) );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array( 7, 3, 1 );
		}
		if ( ! is_array( $raw ) ) {
			return array( 7, 3, 1 );
		}
		return array_values( array_filter( array_map( 'intval', $raw ) ) );
	}

	/** @return array{client_name:string,project_name:string,expected_delivery:string} */
	private function scope_context( ClientScope $scope ): array {
		$client = $this->clients->find( (int) $scope->client_id );
		$project_name = '';
		if ( ! empty( $scope->project_id ) ) {
			$project = $this->projects->find( (int) $scope->project_id );
			$project_name = $project ? $project->name : '';
		}
		$expected = $scope->ends_on ?: ( $scope->starts_on ?? '' );

		return array(
			'client_name'       => $client ? $client->name : '',
			'project_name'      => $project_name,
			'expected_delivery' => $expected,
		);
	}

	public static function notify_content_delivery_if_active( int $scope_id ): void {
		do_action( 'etwa_scope_content_delivery_required', $scope_id );
	}
}
