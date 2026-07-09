<?php
/**
 * Dynamic labor cost calculations.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LaborCostCalculator {

	public static function from_hours(
		float $regular_hours,
		float $overtime_hours,
		float $hourly_rate,
		?float $ot_multiplier = null
	): float {
		unset( $ot_multiplier );

		if ( $hourly_rate <= 0 ) {
			return 0.0;
		}

		$total_hours = max( 0.0, $regular_hours ) + max( 0.0, $overtime_hours );

		return round( $total_hours * $hourly_rate, 2 );
	}
}
