<?php
/**
 * Excel export for centralized ETWA reports.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\BrandHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\EmployeeCostHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReportExportService {

	public function __construct(
		private ReportingService $reporting = new ReportingService(),
	) {}

	/**
	 * @param array<string,mixed> $params
	 * @return string|WP_Error
	 */
	public function export( string $report_type, array $params ): string|WP_Error {
		if ( ! class_exists( Spreadsheet::class ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'Excel export is not available. Install PhpSpreadsheet with Composer (see the admin notice on the dashboard).', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$range = $this->reporting->resolve_date_range(
			$params['preset'] ?? null,
			$params['date_from'] ?? null,
			$params['date_to'] ?? null
		);
		if ( is_wp_error( $range ) ) {
			return $range;
		}

		$from = $range['date_from'];
		$to   = $range['date_to'];

		$dataset = match ( $report_type ) {
			ReportingService::REPORT_EMPLOYEE_TIMESHEET => $this->reporting->get_employee_timesheet_report(
				(int) ( $params['user_id'] ?? 0 ),
				$from,
				$to,
				! empty( $params['client_id'] ) ? (int) $params['client_id'] : null
			),
			ReportingService::REPORT_CLIENT => $this->reporting->get_client_report(
				(int) ( $params['client_id'] ?? 0 ),
				$from,
				$to
			),
			ReportingService::REPORT_CLIENT_LABOR_COST => $this->reporting->get_client_labor_cost_report(
				(int) ( $params['client_id'] ?? 0 ),
				$from,
				$to
			),
			ReportingService::REPORT_EMPLOYEE_HOURS_BY_CLIENT => $this->reporting->get_employee_hours_by_client_report(
				$from,
				$to,
				! empty( $params['user_id'] ) ? (int) $params['user_id'] : null,
				! empty( $params['client_id'] ) ? (int) $params['client_id'] : null
			),
			ReportingService::REPORT_ALL_CLIENTS_COMPARISON => $this->reporting->get_all_clients_comparison_report(
				$from,
				$to,
				! empty( $params['department_id'] ) ? (int) $params['department_id'] : null,
				! empty( $params['user_id'] ) ? (int) $params['user_id'] : null
			),
			default => new WP_Error( 'invalid_report', __( 'Unknown report type.', 'etwa' ), array( 'status' => 400 ) ),
		};

		if ( is_wp_error( $dataset ) ) {
			return $dataset;
		}

		$spreadsheet = match ( $report_type ) {
			ReportingService::REPORT_EMPLOYEE_TIMESHEET => $this->build_employee_timesheet_workbook( $dataset ),
			ReportingService::REPORT_CLIENT => $this->build_client_workbook( $dataset ),
			ReportingService::REPORT_CLIENT_LABOR_COST => $this->build_client_labor_cost_workbook( $dataset ),
			ReportingService::REPORT_EMPLOYEE_HOURS_BY_CLIENT => $this->build_employee_hours_by_client_workbook( $dataset ),
			ReportingService::REPORT_ALL_CLIENTS_COMPARISON => $this->build_all_clients_comparison_workbook( $dataset ),
			default => null,
		};

		if ( ! $spreadsheet ) {
			return new WP_Error( 'export_failed', __( 'Could not build export.', 'etwa' ) );
		}

		return $this->save_workbook( $spreadsheet, $this->filename_for_report( $report_type, $dataset ) );
	}

	/** @param array<string,mixed> $dataset */
	private function filename_for_report( string $report_type, array $dataset ): string {
		$range = $dataset['range'] ?? array();
		$from  = $range['date_from'] ?? 'from';
		$to    = $range['date_to'] ?? 'to';
		$stamp = gmdate( 'YmdHis' );

		return match ( $report_type ) {
			ReportingService::REPORT_EMPLOYEE_TIMESHEET => sprintf(
				'%s-employee-timesheet-%s-to-%s-%s.xlsx',
				BrandHelper::SLUG,
				$from,
				$to,
				$stamp
			),
			ReportingService::REPORT_CLIENT => sprintf(
				'%s-client-report-%s-%s-to-%s-%s.xlsx',
				BrandHelper::SLUG,
				sanitize_file_name( (string) ( $dataset['client']['code'] ?? 'client' ) ),
				$from,
				$to,
				$stamp
			),
			ReportingService::REPORT_CLIENT_LABOR_COST => sprintf(
				'%s-client-labor-cost-%s-%s-to-%s-%s.xlsx',
				BrandHelper::SLUG,
				sanitize_file_name( (string) ( $dataset['client']['code'] ?? 'client' ) ),
				$from,
				$to,
				$stamp
			),
			ReportingService::REPORT_ALL_CLIENTS_COMPARISON => sprintf(
				'%s-all-clients-comparison-%s-to-%s-%s.xlsx',
				BrandHelper::SLUG,
				$from,
				$to,
				$stamp
			),
			default => sprintf(
				'%s-employee-hours-by-client-%s-to-%s-%s.xlsx',
				BrandHelper::SLUG,
				$from,
				$to,
				$stamp
			),
		};
	}

	/** @param array<string,mixed> $dataset */
	private function build_employee_timesheet_workbook( array $dataset ): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle( __( 'Timesheet', 'etwa' ) );

		$employee = $dataset['employee']['display_name'] ?? '';
		$range    = $dataset['range'] ?? array();

		$this->write_report_header(
			$sheet,
			__( 'Employee Timesheet Report', 'etwa' ),
			array(
				array( __( 'Employee', 'etwa' ), $employee ),
				array( __( 'Period From', 'etwa' ), $range['date_from'] ?? '' ),
				array( __( 'Period To', 'etwa' ), $range['date_to'] ?? '' ),
			)
		);

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Date', 'etwa' ),
			__( 'Day', 'etwa' ),
			__( 'Client', 'etwa' ),
			__( 'Activity', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
			__( 'Timesheet Status', 'etwa' ),
		);
		$sheet->fromArray( $headers, null, 'A6' );

		$row = 7;
		foreach ( $dataset['rows'] ?? array() as $entry ) {
			$sheet->fromArray(
				array(
					$entry['employee_name'],
					$entry['date'],
					$entry['day'],
					$entry['client_name'],
					$entry['activity'],
					$entry['regular_hours'],
					$entry['overtime_hours'],
					$entry['total_hours'],
					ucfirst( (string) $entry['timesheet_status'] ),
				),
				null,
				"A{$row}"
			);
			++$row;
		}

		$this->auto_size_columns( $sheet, count( $headers ) );
		return $spreadsheet;
	}

	/** @param array<string,mixed> $dataset */
	private function build_client_workbook( array $dataset ): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle( __( 'Client Report', 'etwa' ) );

		$client = $dataset['client'] ?? array();
		$range  = $dataset['range'] ?? array();

		$this->write_report_header(
			$sheet,
			__( 'Client Report', 'etwa' ),
			array(
				array( __( 'Client', 'etwa' ), $client['name'] ?? '' ),
				array( __( 'Code', 'etwa' ), $client['code'] ?? '' ),
				array( __( 'Period From', 'etwa' ), $range['date_from'] ?? '' ),
				array( __( 'Period To', 'etwa' ), $range['date_to'] ?? '' ),
			)
		);

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Date', 'etwa' ),
			__( 'Client', 'etwa' ),
			__( 'Activity', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
		);
		$sheet->fromArray( $headers, null, 'A7' );

		$row = 8;
		foreach ( $dataset['rows'] ?? array() as $entry ) {
			$sheet->fromArray(
				array(
					$entry['employee_name'],
					$entry['date'],
					$entry['client_name'],
					$entry['activity'],
					$entry['regular_hours'],
					$entry['overtime_hours'],
					$entry['total_hours'],
				),
				null,
				"A{$row}"
			);
			++$row;
		}

		$summary = $dataset['summary'] ?? array();
		++$row;
		$sheet->setCellValue( "A{$row}", __( 'Summary', 'etwa' ) );
		++$row;
		$summary_rows = array(
			array( __( 'Total Employees', 'etwa' ), $summary['total_employees'] ?? 0 ),
			array( __( 'Total Regular Hours', 'etwa' ), $summary['total_regular_hours'] ?? 0 ),
			array( __( 'Total Overtime Hours', 'etwa' ), $summary['total_overtime_hours'] ?? 0 ),
			array( __( 'Total Hours', 'etwa' ), $summary['total_hours'] ?? 0 ),
		);
		if ( isset( $summary['total_labor_cost_egp'] ) && EmployeeCostHelper::can_view_labor_cost() ) {
			$summary_rows[] = array( __( 'Total Labor Cost (EGP)', 'etwa' ), $summary['total_labor_cost_egp'] );
		}
		foreach ( $summary_rows as $summary_row ) {
			$sheet->fromArray( $summary_row, null, "A{$row}" );
			++$row;
		}

		$this->auto_size_columns( $sheet, count( $headers ) );
		return $spreadsheet;
	}

	/** @param array<string,mixed> $dataset */
	private function build_client_labor_cost_workbook( array $dataset ): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$detail      = $spreadsheet->getActiveSheet();
		$detail->setTitle( __( 'Employee Labor Cost', 'etwa' ) );

		$client = $dataset['client'] ?? array();
		$range  = $dataset['range'] ?? array();

		$this->write_report_header(
			$detail,
			__( 'Client Labor Cost Report', 'etwa' ),
			array(
				array( __( 'Client', 'etwa' ), $client['name'] ?? '' ),
				array( __( 'Code', 'etwa' ), $client['code'] ?? '' ),
				array( __( 'Period From', 'etwa' ), $range['date_from'] ?? '' ),
				array( __( 'Period To', 'etwa' ), $range['date_to'] ?? '' ),
			)
		);

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
			__( 'Hourly Cost (EGP)', 'etwa' ),
			__( 'Total Labor Cost (EGP)', 'etwa' ),
		);
		$detail->fromArray( $headers, null, 'A7' );

		$row = 8;
		foreach ( $dataset['rows'] ?? array() as $entry ) {
			$detail->fromArray(
				array(
					$entry['employee_name'],
					$entry['regular_hours'],
					$entry['overtime_hours'],
					$entry['total_hours'],
					$entry['hourly_cost_egp'],
					$entry['total_labor_cost_egp'],
				),
				null,
				"A{$row}"
			);
			++$row;
		}

		$summary_sheet = $spreadsheet->createSheet();
		$summary_sheet->setTitle( __( 'Summary', 'etwa' ) );
		$summary = $dataset['summary'] ?? array();
		$summary_sheet->fromArray( array( __( 'Metric', 'etwa' ), __( 'Value', 'etwa' ) ), null, 'A1' );
		$summary_sheet->fromArray(
			array(
				array( __( 'Total Regular Hours', 'etwa' ), $summary['total_regular_hours'] ?? 0 ),
				array( __( 'Total Overtime Hours', 'etwa' ), $summary['total_overtime_hours'] ?? 0 ),
				array( __( 'Total Hours', 'etwa' ), $summary['total_hours'] ?? 0 ),
				array( __( 'Total Labor Cost (EGP)', 'etwa' ), $summary['total_labor_cost_egp'] ?? 0 ),
			),
			null,
			'A2'
		);

		$this->auto_size_columns( $detail, count( $headers ) );
		$this->auto_size_columns( $summary_sheet, 2 );
		return $spreadsheet;
	}

	/** @param array<string,mixed> $dataset */
	private function build_employee_hours_by_client_workbook( array $dataset ): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$detail      = $spreadsheet->getActiveSheet();
		$detail->setTitle( __( 'Detailed Breakdown', 'etwa' ) );

		$range = $dataset['range'] ?? array();
		$this->write_report_header(
			$detail,
			__( 'Employee Hours by Client', 'etwa' ),
			array(
				array( __( 'Period From', 'etwa' ), $range['date_from'] ?? '' ),
				array( __( 'Period To', 'etwa' ), $range['date_to'] ?? '' ),
			)
		);

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Client', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
		);
		$detail->fromArray( $headers, null, 'A6' );

		$row = 7;
		foreach ( $dataset['rows'] ?? array() as $entry ) {
			$detail->fromArray(
				array(
					$entry['employee_name'],
					$entry['client_name'],
					$entry['regular_hours'],
					$entry['overtime_hours'],
					$entry['total_hours'],
				),
				null,
				"A{$row}"
			);
			++$row;
		}

		$emp_sheet = $spreadsheet->createSheet();
		$emp_sheet->setTitle( __( 'Employee Summary', 'etwa' ) );
		$client_columns = $dataset['client_columns'] ?? array();
		$emp_headers    = array_merge(
			array( __( 'Employee', 'etwa' ), __( 'Total Hours', 'etwa' ) ),
			$client_columns
		);
		$emp_sheet->fromArray( $emp_headers, null, 'A1' );

		$emp_row = 2;
		foreach ( $dataset['employee_summary'] ?? array() as $entry ) {
			$row_data = array( $entry['employee_name'], $entry['total_hours'] );
			foreach ( $client_columns as $client_name ) {
				$row_data[] = $entry[ $client_name ] ?? 0;
			}
			$emp_sheet->fromArray( $row_data, null, "A{$emp_row}" );
			++$emp_row;
		}

		$this->auto_size_columns( $detail, count( $headers ) );
		$this->auto_size_columns( $emp_sheet, count( $emp_headers ) );
		return $spreadsheet;
	}

	/** @param array<string,mixed> $dataset */
	private function build_all_clients_comparison_workbook( array $dataset ): Spreadsheet {
		$spreadsheet = new Spreadsheet();
		$comparison  = $spreadsheet->getActiveSheet();
		$comparison->setTitle( __( 'Client Comparison', 'etwa' ) );

		$range         = $dataset['range'] ?? array();
		$include_cost  = EmployeeCostHelper::can_view_labor_cost();
		$date_label    = sprintf(
			'%s — %s',
			$range['date_from'] ?? '',
			$range['date_to'] ?? ''
		);

		$this->write_report_header(
			$comparison,
			__( 'All Clients Comparison', 'etwa' ),
			array(
				array( __( 'Date Range', 'etwa' ), $date_label ),
			)
		);

		$headers = array(
			__( 'Client', 'etwa' ),
			__( 'Employee Count', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
		);
		if ( $include_cost ) {
			$headers[] = __( 'Labor Cost (EGP)', 'etwa' );
		}
		$headers[] = __( '% of Total Hours', 'etwa' );
		if ( $include_cost ) {
			$headers[] = __( '% of Total Labor Cost', 'etwa' );
		}

		$comparison->fromArray( $headers, null, 'A5' );

		$row = 6;
		foreach ( $dataset['rows'] ?? array() as $client ) {
			$row_data = array(
				$client['client_name'],
				$client['employee_count'],
				$client['regular_hours'],
				$client['overtime_hours'],
				$client['total_hours'],
			);
			if ( $include_cost ) {
				$row_data[] = $client['labor_cost_egp'] ?? 0;
			}
			$row_data[] = ( $client['pct_total_hours'] ?? 0 ) . '%';
			if ( $include_cost ) {
				$row_data[] = ( $client['pct_labor_cost'] ?? 0 ) . '%';
			}
			$comparison->fromArray( $row_data, null, "A{$row}" );
			++$row;
		}

		$summary = $dataset['summary'] ?? array();
		++$row;
		$comparison->setCellValue( "A{$row}", __( 'Grand Total', 'etwa' ) );
		++$row;
		$grand_rows = array(
			array( __( 'Total Employees', 'etwa' ), $summary['total_employees'] ?? 0 ),
			array( __( 'Total Regular Hours', 'etwa' ), $summary['total_regular_hours'] ?? 0 ),
			array( __( 'Total Overtime Hours', 'etwa' ), $summary['total_overtime_hours'] ?? 0 ),
			array( __( 'Total Hours', 'etwa' ), $summary['total_hours'] ?? 0 ),
		);
		if ( $include_cost ) {
			$grand_rows[] = array( __( 'Total Labor Cost (EGP)', 'etwa' ), $summary['total_labor_cost_egp'] ?? 0 );
		}
		foreach ( $grand_rows as $grand_row ) {
			$comparison->fromArray( $grand_row, null, "A{$row}" );
			++$row;
		}

		$allocation_sheet = $spreadsheet->createSheet();
		$allocation_sheet->setTitle( __( 'Employee Allocation', 'etwa' ) );
		$alloc_headers = array(
			__( 'Client', 'etwa' ),
			__( 'Employee', 'etwa' ),
			__( 'Regular Hours', 'etwa' ),
			__( 'Overtime Hours', 'etwa' ),
			__( 'Total Hours', 'etwa' ),
		);
		if ( $include_cost ) {
			$alloc_headers[] = __( 'Hourly Cost (EGP)', 'etwa' );
			$alloc_headers[] = __( 'Labor Cost (EGP)', 'etwa' );
		}
		$allocation_sheet->fromArray( $alloc_headers, null, 'A1' );

		$alloc_row = 2;
		foreach ( $dataset['employee_allocations'] ?? array() as $client_block ) {
			foreach ( $client_block['employees'] ?? array() as $employee ) {
				$alloc_data = array(
					$client_block['client_name'],
					$employee['employee_name'],
					$employee['regular_hours'],
					$employee['overtime_hours'],
					$employee['total_hours'],
				);
				if ( $include_cost ) {
					$alloc_data[] = $employee['hourly_cost_egp'] ?? 0;
					$alloc_data[] = $employee['labor_cost_egp'] ?? 0;
				}
				$allocation_sheet->fromArray( $alloc_data, null, "A{$alloc_row}" );
				++$alloc_row;
			}
		}

		$summary_sheet = $spreadsheet->createSheet();
		$summary_sheet->setTitle( __( 'Summary', 'etwa' ) );
		$summary_sheet->fromArray( array( __( 'Metric', 'etwa' ), __( 'Value', 'etwa' ) ), null, 'A1' );

		$summary_rows = array(
			array( __( 'Date Range', 'etwa' ), $date_label ),
			array( __( 'Total Clients', 'etwa' ), $summary['total_clients'] ?? 0 ),
			array( __( 'Total Employees', 'etwa' ), $summary['total_employees'] ?? 0 ),
			array( __( 'Total Regular Hours', 'etwa' ), $summary['total_regular_hours'] ?? 0 ),
			array( __( 'Total Overtime Hours', 'etwa' ), $summary['total_overtime_hours'] ?? 0 ),
			array( __( 'Total Hours', 'etwa' ), $summary['total_hours'] ?? 0 ),
		);
		if ( $include_cost ) {
			$summary_rows[] = array( __( 'Total Labor Cost (EGP)', 'etwa' ), $summary['total_labor_cost_egp'] ?? 0 );
		}
		$summary_sheet->fromArray( $summary_rows, null, 'A2' );

		$rank_row = count( $summary_rows ) + 3;
		$summary_sheet->setCellValue( "A{$rank_row}", __( 'Ranking by Total Hours', 'etwa' ) );
		++$rank_row;
		$summary_sheet->fromArray(
			array( __( 'Rank', 'etwa' ), __( 'Client', 'etwa' ), __( 'Total Hours', 'etwa' ) ),
			null,
			"A{$rank_row}"
		);
		++$rank_row;

		$rank = 1;
		foreach ( $dataset['rows'] ?? array() as $client ) {
			$summary_sheet->fromArray(
				array( $rank, $client['client_name'], $client['total_hours'] ),
				null,
				"A{$rank_row}"
			);
			++$rank;
			++$rank_row;
		}

		if ( $include_cost ) {
			++$rank_row;
			$summary_sheet->setCellValue( "A{$rank_row}", __( 'Ranking by Labor Cost', 'etwa' ) );
			++$rank_row;
			$summary_sheet->fromArray(
				array( __( 'Rank', 'etwa' ), __( 'Client', 'etwa' ), __( 'Labor Cost (EGP)', 'etwa' ) ),
				null,
				"A{$rank_row}"
			);
			++$rank_row;

			$by_cost = $dataset['rows'] ?? array();
			usort(
				$by_cost,
				fn( $a, $b ) => ( $b['labor_cost_egp'] ?? 0 ) <=> ( $a['labor_cost_egp'] ?? 0 )
			);
			$rank = 1;
			foreach ( $by_cost as $client ) {
				$summary_sheet->fromArray(
					array( $rank, $client['client_name'], $client['labor_cost_egp'] ?? 0 ),
					null,
					"A{$rank_row}"
				);
				++$rank;
				++$rank_row;
			}
		}

		$this->auto_size_columns( $comparison, count( $headers ) );
		$this->auto_size_columns( $allocation_sheet, count( $alloc_headers ) );
		$this->auto_size_columns( $summary_sheet, 2 );
		return $spreadsheet;
	}

	/**
	 * @param array<int,array{0:string,1:string}> $meta_rows
	 */
	private function write_report_header( Worksheet $sheet, string $title, array $meta_rows ): void {
		$sheet->setCellValue( 'A1', $title );
		$row = 2;
		foreach ( $meta_rows as $meta ) {
			$sheet->fromArray( $meta, null, "A{$row}" );
			++$row;
		}
	}

	private function auto_size_columns( Worksheet $sheet, int $column_count ): void {
		for ( $i = 1; $i <= $column_count; ++$i ) {
			$sheet->getColumnDimensionByColumn( $i )->setAutoSize( true );
		}
	}

	private function save_workbook( Spreadsheet $spreadsheet, string $filename ): string|WP_Error {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		wp_mkdir_p( $dir );

		$path = trailingslashit( $dir ) . $filename;

		$writer = new Xlsx( $spreadsheet );
		$writer->save( $path );
		$spreadsheet->disconnectWorksheets();

		return $path;
	}
}
