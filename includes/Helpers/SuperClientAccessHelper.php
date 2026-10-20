<?php
/**
 * Product Hub access: Owner/HR manage all tabs; employees inherit tabs from their department.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

use ETWA\Models\Client;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\SuperClientUnitRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SuperClientAccessHelper {

	public static function can_manage_products( int $user_id ): bool {
		return $user_id > 0 && user_can( $user_id, RoleManager::CAP_MANAGE_SETTINGS );
	}

	public static function can_complete_submission( int $user_id ): bool {
		return self::can_manage_products( $user_id );
	}

	public static function can_access_product_hub( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( self::can_manage_products( $user_id ) ) {
			return true;
		}

		return null !== DepartmentHelper::get_employee_department_id( $user_id );
	}

	public static function user_unit_id( int $user_id ): ?int {
		return DepartmentHelper::get_employee_department_id( $user_id );
	}

	public static function super_client_id_from_view( string $view ): ?int {
		if ( preg_match( '/^super-client-(\d+)$/', $view, $matches ) ) {
			$id = (int) $matches[1];
			return $id > 0 ? $id : null;
		}

		return null;
	}

	public static function is_super_client_view( string $view ): bool {
		return 'super-clients' === $view || null !== self::super_client_id_from_view( $view );
	}

	/**
	 * Product Tabs enabled for the user's department (non-managers only).
	 *
	 * @return Client[]
	 */
	public static function accessible_product_tabs_for_user( int $user_id ): array {
		if ( self::can_manage_products( $user_id ) ) {
			return array();
		}

		$unit_id = self::user_unit_id( $user_id );
		if ( ! $unit_id ) {
			return array();
		}

		$allowed_ids = ( new SuperClientUnitRepository() )->list_ids_for_department( $unit_id );
		if ( ! $allowed_ids ) {
			return array();
		}

		$tabs = array();
		foreach ( ( new ClientRepository() )->list_super_clients() as $client ) {
			if ( ! $client->is_active || ! $client->is_super() ) {
				continue;
			}
			if ( in_array( (int) $client->id, $allowed_ids, true ) ) {
				$tabs[] = $client;
			}
		}

		return $tabs;
	}

	/**
	 * @param int[]|null $allowed_product_tab_ids Department's enabled Product Tab IDs (optional cache).
	 */
	public static function can_view_product_tab( int $user_id, int $super_client_id, ?array $allowed_product_tab_ids = null ): bool {
		if ( self::can_manage_products( $user_id ) ) {
			return true;
		}
		if ( ! self::can_access_product_hub( $user_id ) ) {
			return false;
		}

		$unit_id = self::user_unit_id( $user_id );
		if ( ! $unit_id ) {
			return false;
		}

		if ( null === $allowed_product_tab_ids ) {
			$allowed_product_tab_ids = ( new SuperClientUnitRepository() )->list_ids_for_department( $unit_id );
		}

		return in_array( $super_client_id, $allowed_product_tab_ids, true );
	}

	/**
	 * Product Tab IDs the user may see pending counts and hub content for.
	 *
	 * @return int[]
	 */
	public static function accessible_product_tab_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		if ( self::can_manage_products( $user_id ) ) {
			$ids = array();
			foreach ( ( new ClientRepository() )->list_super_clients() as $client ) {
				if ( $client->is_super() && $client->is_active ) {
					$ids[] = (int) $client->id;
				}
			}

			return $ids;
		}

		$unit_id = self::user_unit_id( $user_id );
		if ( ! $unit_id ) {
			return array();
		}

		return ( new SuperClientUnitRepository() )->list_ids_for_department( $unit_id );
	}
}
