<?php
/**
 * Batch user display name resolution.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UserDisplayNames {

	/**
	 * @param int[] $user_ids
	 * @return array<int,string>
	 */
	public static function map( array $user_ids ): array {
		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( ! $user_ids ) {
			return array();
		}

		$users = get_users(
			array(
				'include' => $user_ids,
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$map = array();
		foreach ( $users as $user ) {
			$map[ (int) $user->ID ] = $user->display_name;
		}

		return $map;
	}

	public static function get( array $map, int $user_id ): string {
		return $map[ $user_id ] ?? (string) $user_id;
	}
}
