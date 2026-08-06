<?php
/**
 * Centralized reports REST endpoints.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\REST;

use ETWA\Helpers\DateRangeHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Repositories\ClientRepository;
use ETWA\Services\DepartmentService;
use ETWA\Services\EmployeeCostService;
use ETWA\Services\ReportExportService;
use ETWA\Services\ReportingService;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportsController extends RestController {

	public function register_routes(): void {
		$types = implode(
			'|',
			array(
				ReportingService::REPORT_EMPLOYEE_TIMESHEET,
				ReportingService::REPORT_CLIENT,
				ReportingService::REPORT_CLIENT_LABOR_COST,
				ReportingService::REPORT_EMPLOYEE_HOURS_BY_CLIENT,
				ReportingService::REPORT_ALL_CLIENTS_COMPARISON,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/all-clients-comparison',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'all_clients_comparison' ),
					'permission_callback' => array( $this, 'can_view_comparison_report' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/export/(?P<type>' . $types . ')',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_report' ),
					'permission_callback' => array( $this, 'can_export_report' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/reports/filters',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'filter_options' ),
					'permission_callback' => array( $this, 'can_access_reports' ),
				),
			)
		);
	}

	public function can_access_reports(): bool {
		return is_user_logged_in() && current_user_can( RoleManager::CAP_EXPORT );
	}

	public function can_view_comparison_report(): bool {
		return $this->can_access_reports() && current_user_can( RoleManager::CAP_ANALYTICS );
	}

	public function can_export_report( WP_REST_Request $request ): bool {
		if ( ! $this->can_access_reports() ) {
			return false;
		}

		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( ReportingService::REPORT_CLIENT_LABOR_COST === $type ) {
			return EmployeeCostHelper::can_view_labor_cost();
		}

		if ( in_array( $type, array( ReportingService::REPORT_CLIENT, ReportingService::REPORT_ALL_CLIENTS_COMPARISON ), true ) ) {
			return current_user_can( RoleManager::CAP_ANALYTICS );
		}

		return true;
	}

	public function export_report( WP_REST_Request $request ) {
		$type   = sanitize_key( (string) $request->get_param( 'type' ) );
		$params = array(
			'preset'     => $this->get_string_param( $request, 'preset' ) ?: null,
			'date_from'  => $this->get_string_param( $request, 'date_from' ) ?: null,
			'date_to'    => $this->get_string_param( $request, 'date_to' ) ?: null,
			'user_id'       => $this->get_int_param( $request, 'user_id' ) ?: null,
			'client_id'     => $this->get_int_param( $request, 'client_id' ) ?: null,
			'department_id' => $this->get_int_param( $request, 'department_id' ) ?: null,
		);

		$export = new ReportExportService();
		$file   = $export->export( $type, $params );

		if ( is_wp_error( $file ) ) {
			return $this->error( $file );
		}

		$upload = wp_upload_dir();
		$url    = str_replace( $upload['basedir'], $upload['baseurl'], $file );

		return $this->success(
			array(
				'file_path' => $file,
				'file_url'  => $url,
				'filename'  => basename( $file ),
			)
		);
	}

	public function filter_options( WP_REST_Request $request ) {
		$employees = $this->report_employee_options();
		$clients   = $this->report_client_options();
		$departments = $this->report_department_options();

		return $this->success(
			array(
				'employees'           => $employees,
				'clients'             => $clients,
				'departments'         => $departments,
				'presets'             => DateRangeHelper::preset_labels(),
				'can_view_labor_cost' => EmployeeCostHelper::can_view_labor_cost(),
			)
		);
	}

	public function all_clients_comparison( WP_REST_Request $request ) {
		$range = ( new ReportingService() )->resolve_date_range(
			$this->get_string_param( $request, 'preset' ) ?: null,
			$this->get_string_param( $request, 'date_from' ) ?: null,
			$this->get_string_param( $request, 'date_to' ) ?: null
		);
		if ( is_wp_error( $range ) ) {
			return $this->error( $range );
		}

		$report = ( new ReportingService() )->get_all_clients_comparison_report(
			$range['date_from'],
			$range['date_to'],
			$this->get_int_param( $request, 'department_id' ) ?: null,
			$this->get_int_param( $request, 'user_id' ) ?: null
		);

		if ( is_wp_error( $report ) ) {
			return $this->error( $report );
		}

		return $this->success( $report );
	}

	/** @return array<int,array{id:int,display_name:string}> */
	private function report_employee_options(): array {
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			return array();
		}

		$service = new EmployeeCostService();
		$list    = $service->list_employees( 1, 100, '', false );
		if ( is_wp_error( $list ) ) {
			return array();
		}

		$employees = array();
		foreach ( $list['items'] ?? array() as $emp ) {
			$employees[] = array(
				'id'           => (int) $emp['id'],
				'display_name' => (string) $emp['display_name'],
			);
		}

		return $employees;
	}

	/** @return array<int,array{id:int,name:string,code:string}> */
	private function report_client_options(): array {
		$repo    = new ClientRepository();
		$clients = array();

		foreach ( $repo->all_for_dropdown() as $client ) {
			if ( ClientRepository::BREAK_CODE === strtoupper( $client->code ) ) {
				continue;
			}
			$clients[] = array(
				'id'   => (int) $client->id,
				'name' => $client->name,
				'code' => $client->code,
			);
		}

		return $clients;
	}

	/** @return array<int,array{id:int,name:string}> */
	private function report_department_options(): array {
		if ( ! current_user_can( RoleManager::CAP_VIEW_ALL ) ) {
			return array();
		}

		$result = ( new DepartmentService() )->list_departments( get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return array();
		}

		$departments = array();
		foreach ( $result['items'] ?? array() as $dept ) {
			$departments[] = array(
				'id'   => (int) $dept['id'],
				'name' => (string) $dept['name'],
			);
		}

		return $departments;
	}
}
