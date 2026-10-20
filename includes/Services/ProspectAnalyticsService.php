<?php
/**
 * Prospect analytics aggregations from existing prospect + conversion data.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\DateHelper;
use ETWA\Helpers\DateRangeHelper;
use ETWA\Models\Prospect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProspectAnalyticsService {

	public const INTERVAL_DAY   = 'day';
	public const INTERVAL_WEEK  = 'week';
	public const INTERVAL_MONTH = 'month';

	/**
	 * @return array{preset:string,date_from:string,date_to:string,label:string}
	 */
	public function resolve_range( ?string $preset, ?string $date_from, ?string $date_to ): array {
		return DateRangeHelper::resolve( $preset, $date_from, $date_to );
	}

	/** @return string[] */
	public static function intervals(): array {
		return array( self::INTERVAL_DAY, self::INTERVAL_WEEK, self::INTERVAL_MONTH );
	}

	/** @return array<string,string> */
	public static function interval_labels(): array {
		return array(
			self::INTERVAL_DAY   => __( 'Daily', 'etwa' ),
			self::INTERVAL_WEEK  => __( 'Weekly', 'etwa' ),
			self::INTERVAL_MONTH => __( 'Monthly', 'etwa' ),
		);
	}

	public function sanitize_interval( string $interval ): string {
		$interval = sanitize_key( $interval );
		return in_array( $interval, self::intervals(), true ) ? $interval : self::INTERVAL_MONTH;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function dashboard( string $date_from, string $date_to, string $status = '', string $search = '', string $interval = self::INTERVAL_MONTH ): array {
		$status   = $this->sanitize_status( $status );
		$search   = sanitize_text_field( $search );
		$interval = $this->sanitize_interval( $interval );

		$kpis = $this->kpis( $date_from, $date_to );
		$new  = (int) $kpis['new'];

		$by_source = $this->source_breakdown( $date_from, $date_to, $new );

		return array(
			'has_prospects'     => (int) $kpis['total'] > 0,
			'has_range_data'    => $new > 0,
			'has_source_data'   => $this->has_defined_source_counts( $by_source ),
			'has_conversions'   => (int) $kpis['converted'] > 0,
			'kpis'              => $kpis,
			'sources'           => $by_source,
			'acquisition'       => $this->acquisition( $date_from, $date_to, $interval ),
			'interval'          => $interval,
			'interval_labels'   => self::interval_labels(),
			'rows'              => $this->table_rows( $date_from, $date_to, $status, $search ),
		);
	}

	/**
	 * @return array{total:int,new:int,converted:int,conversion_rate:?float,avg_conversion_days:?float}
	 */
	private function kpis( string $date_from, string $date_to ): array {
		global $wpdb;

		$prospects = $wpdb->prefix . 'etwa_prospects';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_all,
					SUM(CASE WHEN prospect_start_date BETWEEN %s AND %s THEN 1 ELSE 0 END) AS new_count,
					SUM(CASE
						WHEN prospect_start_date BETWEEN %s AND %s
							AND converted_client_id IS NOT NULL AND converted_client_id > 0
						THEN 1 ELSE 0 END) AS converted_count,
					AVG(CASE
						WHEN prospect_start_date BETWEEN %s AND %s
							AND converted_client_id IS NOT NULL AND converted_client_id > 0
							AND converted_at IS NOT NULL
						THEN GREATEST(DATEDIFF(DATE(converted_at), prospect_start_date), 0)
						END) AS avg_conversion_days
				FROM {$prospects}",
				$date_from,
				$date_to,
				$date_from,
				$date_to,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$total     = (int) ( $row['total_all'] ?? 0 );
		$new       = (int) ( $row['new_count'] ?? 0 );
		$converted = (int) ( $row['converted_count'] ?? 0 );
		$avg_days  = isset( $row['avg_conversion_days'] ) && null !== $row['avg_conversion_days'] && '' !== $row['avg_conversion_days']
			? round( (float) $row['avg_conversion_days'], 1 )
			: null;

		$rate = ( $new > 0 && $converted > 0 ) ? round( ( $converted / $new ) * 100, 1 ) : null;

		return array(
			'total'                => $total,
			'new'                  => $new,
			'converted'            => $converted,
			'conversion_rate'      => $rate,
			'avg_conversion_days'  => $converted > 0 ? $avg_days : null,
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function source_breakdown( string $date_from, string $date_to, int $new_total ): array {
		global $wpdb;

		$prospects = $wpdb->prefix . 'etwa_prospects';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source,
					COUNT(*) AS total,
					SUM(CASE WHEN converted_client_id IS NOT NULL AND converted_client_id > 0 THEN 1 ELSE 0 END) AS converted
				FROM {$prospects}
				WHERE prospect_start_date BETWEEN %s AND %s
				GROUP BY source",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( $rows ?: array() as $row ) {
			$key = Prospect::normalize_source_key( $row['source'] ?? null );
			$counts[ $key ] = array(
				'total'     => (int) ( $row['total'] ?? 0 ),
				'converted' => (int) ( $row['converted'] ?? 0 ),
			);
		}

		$labels = Prospect::source_labels();
		$out    = array();
		foreach ( $labels as $slug => $label ) {
			$total     = (int) ( $counts[ $slug ]['total'] ?? 0 );
			$converted = (int) ( $counts[ $slug ]['converted'] ?? 0 );
			$out[]     = $this->source_row( $slug, $label, $total, $converted, $new_total );
		}

		$unspecified = $counts[''] ?? null;
		if ( $unspecified && ( $unspecified['total'] ?? 0 ) > 0 ) {
			$out[] = $this->source_row(
				'',
				__( 'Unspecified', 'etwa' ),
				(int) $unspecified['total'],
				(int) $unspecified['converted'],
				$new_total
			);
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return ( $b['total'] <=> $a['total'] ) ?: strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function source_row( string $source, string $label, int $total, int $converted, int $new_total ): array {
		return array(
			'source'          => $source,
			'label'           => $label,
			'total'           => $total,
			'converted'       => $converted,
			'share'           => $new_total > 0 ? round( ( $total / $new_total ) * 100, 1 ) : null,
			'conversion_rate' => $total > 0 ? round( ( $converted / $total ) * 100, 1 ) : null,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $sources
	 */
	private function has_defined_source_counts( array $sources ): bool {
		foreach ( $sources as $row ) {
			if ( '' !== (string) ( $row['source'] ?? '' ) && (int) ( $row['total'] ?? 0 ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int,array{period:string,label:string,count:int}>
	 */
	private function acquisition( string $date_from, string $date_to, string $interval ): array {
		global $wpdb;

		$prospects = $wpdb->prefix . 'etwa_prospects';
		$expr      = $this->period_sql_expr( $interval );

		$first = $wpdb->get_var( "SELECT MIN(prospect_start_date) FROM {$prospects} WHERE prospect_start_date IS NOT NULL" );
		$chart_from = $date_from;
		if ( $first && (string) $first > $chart_from ) {
			$chart_from = (string) $first;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- period expression is a fixed whitelist.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$expr} AS period, COUNT(*) AS total
				FROM {$prospects}
				WHERE prospect_start_date BETWEEN %s AND %s
				GROUP BY period",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$map = array();
		foreach ( $rows ?: array() as $row ) {
			$period = (string) ( $row['period'] ?? '' );
			if ( '' === $period ) {
				continue;
			}
			$map[ $period ] = (int) $row['total'];
		}

		$out = array();
		foreach ( $this->period_keys( $chart_from, $date_to, $interval ) as $period ) {
			$out[] = array(
				'period' => $period,
				'label'  => $this->period_label( $period, $interval ),
				'count'  => $map[ $period ] ?? 0,
			);
		}

		return $out;
	}

	private function period_sql_expr( string $interval ): string {
		if ( self::INTERVAL_DAY === $interval ) {
			return 'prospect_start_date';
		}
		if ( self::INTERVAL_WEEK === $interval ) {
			return 'DATE_SUB(prospect_start_date, INTERVAL DAYOFWEEK(prospect_start_date) - 1 DAY)';
		}
		return "DATE_FORMAT(prospect_start_date, '%%Y-%%m')";
	}

	/** @return string[] */
	private function period_keys( string $from, string $to, string $interval ): array {
		try {
			$start = new \DateTimeImmutable( $from );
			$end   = new \DateTimeImmutable( $to );
		} catch ( \Exception $e ) {
			return array();
		}

		if ( self::INTERVAL_WEEK === $interval ) {
			$week  = DateHelper::get_work_week_for_date( $start->format( 'Y-m-d' ) );
			$start = new \DateTimeImmutable( $week['period_start'] );
			$step  = '+7 days';
			$format = 'Y-m-d';
		} elseif ( self::INTERVAL_DAY === $interval ) {
			$step   = '+1 day';
			$format = 'Y-m-d';
		} else {
			$start  = new \DateTimeImmutable( $start->format( 'Y-m-01' ) );
			$end    = new \DateTimeImmutable( $end->format( 'Y-m-01' ) );
			$step   = '+1 month';
			$format = 'Y-m';
		}

		$span   = 0;
		$cursor = $start;
		while ( $cursor <= $end ) {
			++$span;
			$cursor = $cursor->modify( $step );
			if ( $span > 400 ) {
				break;
			}
		}
		if ( $span > 120 ) {
			$start = $end->modify( '-' . ( 119 ) . ( self::INTERVAL_MONTH === $interval ? ' months' : ( self::INTERVAL_WEEK === $interval ? ' weeks' : ' days' ) ) );
			if ( self::INTERVAL_MONTH === $interval ) {
				$start = new \DateTimeImmutable( $start->format( 'Y-m-01' ) );
			} elseif ( self::INTERVAL_WEEK === $interval ) {
				$week  = DateHelper::get_work_week_for_date( $start->format( 'Y-m-d' ) );
				$start = new \DateTimeImmutable( $week['period_start'] );
			}
		}

		$out = array();
		while ( $start <= $end ) {
			$out[] = $start->format( $format );
			$start = $start->modify( $step );
		}

		return $out;
	}

	private function period_label( string $period, string $interval ): string {
		if ( self::INTERVAL_MONTH === $interval ) {
			$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $period . '-01' );
			return $dt ? wp_date( 'M Y', $dt->getTimestamp() ) : $period;
		}

		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $period );
		if ( ! $dt ) {
			return $period;
		}

		if ( self::INTERVAL_WEEK === $interval ) {
			$week = DateHelper::get_work_week_for_date( $period );
			return DateHelper::format_week_range( $week['period_start'], $week['period_end'] );
		}

		return wp_date( 'M j', $dt->getTimestamp() );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function table_rows( string $date_from, string $date_to, string $status, string $search ): array {
		global $wpdb;

		$prospects = $wpdb->prefix . 'etwa_prospects';
		$entries   = $wpdb->prefix . 'etwa_entries';
		$clients   = $wpdb->prefix . 'etwa_clients';

		$where  = 'p.prospect_start_date BETWEEN %s AND %s';
		$params = array( $date_from, $date_to, $date_from, $date_to );

		if ( $status ) {
			$where   .= ' AND p.status = %s';
			$params[] = $status;
		}
		if ( '' !== $search ) {
			$where   .= ' AND p.name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$sql = "SELECT p.id, p.name, p.status, p.source, p.prospect_start_date, p.created_at, p.won_lost_at,
				p.converted_client_id, p.converted_at,
				c.name AS converted_client_name,
				COALESCE(h.hours, 0) AS hours
			FROM {$prospects} p
			LEFT JOIN {$clients} c ON c.id = p.converted_client_id
			LEFT JOIN (
				SELECT prospect_id, SUM(regular_hours + overtime_hours) AS hours
				FROM {$entries}
				WHERE prospect_id IS NOT NULL AND entry_date BETWEEN %s AND %s
				GROUP BY prospect_id
			) h ON h.prospect_id = p.id
			WHERE {$where}
			ORDER BY p.prospect_start_date DESC, p.name ASC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		$labels = Prospect::source_labels();
		$out    = array();
		foreach ( $rows ?: array() as $row ) {
			$source     = Prospect::normalize_source_key( $row['source'] ?? null );
			$start      = substr( (string) ( $row['prospect_start_date'] ?? '' ), 0, 10 );
			$converted  = (int) ( $row['converted_client_id'] ?? 0 ) > 0;
			$out[]      = array(
				'id'                     => (int) $row['id'],
				'name'                   => (string) $row['name'],
				'status'                 => (string) $row['status'],
				'source'                 => $source ?: null,
				'source_label'           => $source ? ( $labels[ $source ] ?? $source ) : '',
				'prospect_start_date'    => $start ?: null,
				'start_label'            => $start ? DateHelper::format_date( $start ) : '—',
				'created_at'             => substr( (string) $row['created_at'], 0, 10 ),
				'created_label'          => DateHelper::format_date( substr( (string) $row['created_at'], 0, 10 ) ),
				'hours'                  => round( (float) $row['hours'], 2 ),
				'converted'              => $converted,
				'converted_at'           => $converted ? substr( (string) ( $row['converted_at'] ?? '' ), 0, 10 ) : null,
				'converted_label'        => $converted ? ( DateHelper::format_date( substr( (string) ( $row['converted_at'] ?? '' ), 0, 10 ) ) ?: '—' ) : '—',
				'converted_client_id'    => (int) ( $row['converted_client_id'] ?? 0 ),
				'converted_client_name'  => (string) ( $row['converted_client_name'] ?? '' ),
			);
		}

		return $out;
	}

	private function sanitize_status( string $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, Prospect::statuses(), true ) ? $status : '';
	}
}
