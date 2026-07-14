<?php
/**
 * Dynamic labor cost calculations.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Repositories\SettingsRepository;

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
		if ( $hourly_rate <= 0 ) {
			return 0.0;
		}

		$multiplier = self::overtime_multiplier( $ot_multiplier );

		$regular_cost = max( 0.0, $regular_hours ) * $hourly_rate;
		$overtime_cost = max( 0.0, $overtime_hours ) * $hourly_rate * $multiplier;

		return round( $regular_cost + $overtime_cost, 2 );
	}

	public static function overtime_cost_only(
		float $overtime_hours,
		float $hourly_rate,
		?float $ot_multiplier = null
	): float {
		if ( $hourly_rate <= 0 || $overtime_hours <= 0 ) {
			return 0.0;
		}

		$multiplier = self::overtime_multiplier( $ot_multiplier );

		return round( max( 0.0, $overtime_hours ) * $hourly_rate * $multiplier, 2 );
	}

	private static function overtime_multiplier( ?float $override ): float {
		if ( null !== $override && $override > 0 ) {
			return $override;
		}

		$settings = new SettingsRepository();
		$value    = (float) $settings->get( 'overtime_cost_multiplier', 1 );

		return $value > 0 ? $value : 1;
	}
}
