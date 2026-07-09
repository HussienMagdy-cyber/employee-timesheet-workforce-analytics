<?php
/**
 * Employee profile user-meta helpers.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmployeeProfileHelper {

	public const META_JOIN_DATE = 'etwa_join_date';
	public const META_JOB_TITLE = 'etwa_job_title';
	public const META_BIRTHDAY  = 'etwa_birthday';

	/** @return string[] ETWA roles HR/Owner may assign (priority order). */
	public static function assignable_role_slugs(): array {
		return array( 'etwa_owner', 'etwa_hr_manager', 'etwa_department_head', 'etwa_employee' );
	}

	/** @return string[] */
	public static function creatable_role_slugs(): array {
		return self::assignable_role_slugs();
	}

	public static function is_managed_user( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( self::assignable_role_slugs() as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	public static function can_manage_employees( ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		return $user_id > 0 && user_can( $user_id, RoleManager::CAP_VIEW_ALL );
	}

	public static function sanitize_optional_date( mixed $value ): ?string {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$date = sanitize_text_field( (string) $value );
		if ( ! self::is_valid_date( $date ) ) {
			return null;
		}

		return $date;
	}

	public static function sanitize_required_date( mixed $value ): string|\WP_Error {
		if ( null === $value || '' === $value ) {
			return new \WP_Error( 'invalid_date', __( 'Date is required.', 'etwa' ) );
		}

		$date = sanitize_text_field( (string) $value );
		if ( ! self::is_valid_date( $date ) ) {
			return new \WP_Error( 'invalid_date', __( 'Enter a valid date.', 'etwa' ) );
		}

		return $date;
	}

	public static function is_valid_date( string $date ): bool {
		$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	public static function sanitize_role( mixed $value ): string|\WP_Error {
		$role = sanitize_key( (string) $value );
		if ( ! in_array( $role, self::assignable_role_slugs(), true ) ) {
			return new \WP_Error( 'invalid_role', __( 'Invalid employee role.', 'etwa' ) );
		}

		return $role;
	}

	public static function role_label_for_user( \WP_User $user ): string {
		return self::role_label_for_slug( self::primary_role_slug( $user ) );
	}

	public static function primary_role_slug( \WP_User $user ): string {
		foreach ( self::assignable_role_slugs() as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return $role;
			}
		}

		$user_id = (int) $user->ID;

		if ( user_can( $user_id, RoleManager::CAP_ADV_ANALYTICS ) ) {
			return 'etwa_owner';
		}

		if ( user_can( $user_id, RoleManager::CAP_APPROVE_DEPT_REQUESTS ) ) {
			return 'etwa_department_head';
		}

		if ( user_can( $user_id, RoleManager::CAP_VIEW_ALL ) ) {
			return 'etwa_hr_manager';
		}

		return 'etwa_employee';
	}

	public static function get_initials( string $display_name ): string {
		$parts = preg_split( '/\s+/', trim( $display_name ) ) ?: array();
		if ( ! $parts ) {
			return '?';
		}

		if ( count( $parts ) === 1 ) {
			return strtoupper( mb_substr( $parts[0], 0, 1 ) );
		}

		return strtoupper( mb_substr( $parts[0], 0, 1 ) . mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
	}

	public static function format_date( ?string $date ): ?string {
		if ( ! $date || ! self::is_valid_date( $date ) ) {
			return null;
		}

		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}

		return wp_date( get_option( 'date_format' ), $ts );
	}

	public static function format_tenure( ?string $join_date ): string {
		if ( ! $join_date || ! self::is_valid_date( $join_date ) ) {
			return '';
		}

		$join = new \DateTimeImmutable( $join_date );
		$now  = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
		if ( $join > $now ) {
			return __( 'Starts in the future', 'etwa' );
		}

		$diff = $join->diff( $now );
		$parts = array();

		if ( $diff->y > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of years */
				_n( '%d year', '%d years', $diff->y, 'etwa' ),
				$diff->y
			);
		}
		if ( $diff->m > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of months */
				_n( '%d month', '%d months', $diff->m, 'etwa' ),
				$diff->m
			);
		}
		if ( ! $parts ) {
			$days = max( 0, (int) $diff->days );
			return sprintf(
				/* translators: %d: number of days */
				_n( '%d day', '%d days', $days, 'etwa' ),
				$days
			);
		}

		return implode( ', ', $parts );
	}

	public static function unique_login_from_email( string $email ): string {
		$local = sanitize_user( (string) current( explode( '@', $email ) ), true );
		if ( '' === $local ) {
			$local = 'employee';
		}

		$login = $local;
		$suffix = 1;
		while ( username_exists( $login ) ) {
			$login = $local . $suffix;
			++$suffix;
		}

		return $login;
	}

	/** @return array<string,mixed> */
	public static function read_meta( int $user_id ): array {
		return array(
			'job_title' => (string) get_user_meta( $user_id, self::META_JOB_TITLE, true ),
			'join_date' => (string) get_user_meta( $user_id, self::META_JOIN_DATE, true ),
			'birthday'  => (string) get_user_meta( $user_id, self::META_BIRTHDAY, true ),
		);
	}

	public static function save_meta( int $user_id, string $job_title, string $join_date, ?string $birthday ): void {
		update_user_meta( $user_id, self::META_JOB_TITLE, sanitize_text_field( $job_title ) );
		update_user_meta( $user_id, self::META_JOIN_DATE, $join_date );

		if ( $birthday ) {
			update_user_meta( $user_id, self::META_BIRTHDAY, $birthday );
		} else {
			delete_user_meta( $user_id, self::META_BIRTHDAY );
		}
	}

	/** @return array<string,mixed> */
	public static function build_profile_payload( int $user_id, int $viewer_id ): array|\WP_Error {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'Employee not found.', 'etwa' ), array( 'status' => 404 ) );
		}

		$meta       = self::read_meta( $user_id );
		$dept       = DepartmentHelper::get_employee_department( $user_id );
		$can_edit   = self::can_manage_employees( $viewer_id );
		$can_cost   = EmployeeCostHelper::can_view_labor_cost( $viewer_id );
		$join_date  = $meta['join_date'] ?: null;
		$birthday   = $meta['birthday'] ?: null;

		$payload = array(
			'id'                  => $user_id,
			'display_name'        => $user->display_name,
			'email'               => $user->user_email,
			'role'                => self::primary_role_slug( $user ),
			'role_label'          => self::role_label_for_user( $user ),
			'job_title'           => $meta['job_title'],
			'join_date'           => $join_date,
			'join_date_formatted' => self::format_date( $join_date ),
			'tenure'              => self::format_tenure( $join_date ),
			'birthday'            => $birthday,
			'birthday_formatted'  => self::format_date( $birthday ),
			'department_id'       => $dept?->id,
			'department_name'     => $dept?->name ?? '',
			'initials'            => self::get_initials( $user->display_name ),
			'can_edit'            => $can_edit,
			'can_view_labor_cost' => $can_cost,
		);

		if ( $can_cost ) {
			$payload['hourly_cost_egp'] = EmployeeCostHelper::get_hourly_cost_internal( $user_id );
		}

		return $payload;
	}

	public static function role_label_for_slug( string $slug ): string {
		return match ( $slug ) {
			'etwa_owner'           => __( 'Owner', 'etwa' ),
			'etwa_hr_manager'      => __( 'HR Manager', 'etwa' ),
			'etwa_department_head' => __( 'Department Head', 'etwa' ),
			default                => __( 'Employee', 'etwa' ),
		};
	}

	/** @return array<int,array{slug:string,label:string}> */
	public static function assignable_roles(): array {
		$roles = array();
		foreach ( self::assignable_role_slugs() as $slug ) {
			$roles[] = array(
				'slug'  => $slug,
				'label' => self::role_label_for_slug( $slug ),
			);
		}

		return $roles;
	}

	/** @return array<int,array{id:int,name:string}> */
	public static function department_choices( int $viewer_id ): array {
		if ( ! self::can_manage_employees( $viewer_id ) ) {
			return array();
		}

		$result = ( new \ETWA\Services\DepartmentService() )->list_departments( $viewer_id );
		if ( is_wp_error( $result ) ) {
			return array();
		}

		return array_map(
			static fn( array $item ) => array(
				'id'   => (int) ( $item['id'] ?? 0 ),
				'name' => (string) ( $item['name'] ?? '' ),
			),
			$result['items'] ?? array()
		);
	}

	public static function role_options_html( ?string $selected = null ): string {
		$html = '';
		foreach ( self::assignable_role_slugs() as $slug ) {
			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $selected, $slug, false ),
				esc_html( self::role_label_for_slug( $slug ) )
			);
		}

		return $html;
	}

	public static function department_options_html( int $viewer_id, int|string|null $selected = null ): string {
		$html = sprintf(
			'<option value="">%s</option>',
			esc_html__( '— No department —', 'etwa' )
		);

		foreach ( self::department_choices( $viewer_id ) as $department ) {
			$html .= sprintf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $department['id'],
				selected( (string) $selected, (string) $department['id'], false ),
				esc_html( $department['name'] )
			);
		}

		return $html;
	}
}
