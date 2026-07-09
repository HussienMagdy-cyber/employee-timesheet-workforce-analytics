<?php
/**
 * Excel export service using PhpSpreadsheet.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\BrandHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\EmployeeCostHelper;
use ETWA\Helpers\UserDisplayNames;
use ETWA\Models\Entry;
use ETWA\Models\Overtime;
use ETWA\Repositories\EntryRepository;
use ETWA\Repositories\OvertimeRepository;
use ETWA\Repositories\SettingsRepository;
use ETWA\Services\LaborCostCalculator;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExportService {

	private const CHUNK_SIZE = 500;

	public function __construct(
		private EntryRepository $entries = new EntryRepository(),
		private AnalyticsService $analytics = new AnalyticsService(),
		private ClientAnalyticsService $client_analytics = new ClientAnalyticsService(),
		private OvertimeRepository $overtime = new OvertimeRepository(),
		private SettingsRepository $settings = new SettingsRepository(),
	) {}

	/**
	 * @return string|WP_Error File path to generated XLSX.
	 */
	public function generate( string $date_from, string $date_to, ?int $user_id = null ): string|WP_Error {
		if ( ! class_exists( Spreadsheet::class ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'Excel export is not available. Install PhpSpreadsheet with Composer (see the admin notice on the dashboard).', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$spreadsheet = new Spreadsheet();
		$bundle      = $this->analytics->get_dashboard_bundle( $date_from, $date_to );

		$this->build_raw_sheet_chunked( $spreadsheet, $date_from, $date_to, $user_id );
		$this->build_client_sheet( $spreadsheet, $bundle['top_clients'] ?? array() );
		$this->build_employee_sheet( $spreadsheet, $bundle['employees'] ?? array() );
		$this->build_chart_sheet( $spreadsheet, $bundle['trends'] ?? array() );
		$this->build_overtime_sheet_chunked( $spreadsheet, $date_from, $date_to, $user_id );

		unset( $bundle );

		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		wp_mkdir_p( $dir );

		$filename = sprintf( '%s-report-%s-to-%s-%s.xlsx', BrandHelper::SLUG, $date_from, $date_to, gmdate( 'YmdHis' ) );
		$path     = trailingslashit( $dir ) . $filename;

		$writer = new Xlsx( $spreadsheet );
		$writer->setIncludeCharts( true );
		$writer->save( $path );

		$spreadsheet->disconnectWorksheets();
		unset( $spreadsheet, $writer );

		return $path;
	}

	/**
	 * @return string|WP_Error
	 */
	public function generate_client_excel( int $client_id, string $date_from, string $date_to ): string|WP_Error {
		if ( ! class_exists( Spreadsheet::class ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'Excel export is not available. Install PhpSpreadsheet with Composer (see the admin notice on the dashboard).', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$detail = $this->client_analytics->get_client_detail( $client_id, $date_from, $date_to, false );
		if ( is_wp_error( $detail ) ) {
			return $detail;
		}

		$spreadsheet = new Spreadsheet();
		$this->build_client_summary_sheet( $spreadsheet, $detail );
		$this->build_client_employee_sheet( $spreadsheet, $detail );
		$this->build_client_trend_sheet( $spreadsheet, $detail['trend'] ?? array() );
		$this->build_client_entries_sheet_chunked( $spreadsheet, $client_id, $date_from, $date_to );

		$code     = sanitize_file_name( (string) ( $detail['client']['code'] ?? 'client' ) );
		$upload   = wp_upload_dir();
		$dir      = trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		wp_mkdir_p( $dir );
		$filename = sprintf( '%s-client-%s-%s-to-%s-%s.xlsx', BrandHelper::SLUG, $code, $date_from, $date_to, gmdate( 'YmdHis' ) );
		$path     = trailingslashit( $dir ) . $filename;

		$writer = new Xlsx( $spreadsheet );
		$writer->save( $path );
		$spreadsheet->disconnectWorksheets();

		return $path;
	}

	/**
	 * @return string|WP_Error
	 */
	public function generate_client_csv( int $client_id, string $date_from, string $date_to ): string|WP_Error {
		$detail = $this->client_analytics->get_client_detail( $client_id, $date_from, $date_to, false );
		if ( is_wp_error( $detail ) ) {
			return $detail;
		}

		$code     = sanitize_file_name( (string) ( $detail['client']['code'] ?? 'client' ) );
		$upload   = wp_upload_dir();
		$dir      = trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		wp_mkdir_p( $dir );
		$filename = sprintf( '%s-client-%s-%s-to-%s-%s.csv', BrandHelper::SLUG, $code, $date_from, $date_to, gmdate( 'YmdHis' ) );
		$path     = trailingslashit( $dir ) . $filename;

		$handle = fopen( $path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'export_failed', __( 'Could not create export file.', 'etwa' ) );
		}

		fputcsv( $handle, array( __( 'Client Report', 'etwa' ) ) );
		fputcsv( $handle, array( __( 'Client', 'etwa' ), $detail['client']['name'] ?? '' ) );
		fputcsv( $handle, array( __( 'Period', 'etwa' ), "{$date_from} — {$date_to}" ) );
		fputcsv( $handle, array() );

		$summary = $detail['summary'] ?? array();
		fputcsv( $handle, array( __( 'Summary', 'etwa' ) ) );
		fputcsv( $handle, array( __( 'Total Hours', 'etwa' ), $summary['total_hours'] ?? 0 ) );
		fputcsv( $handle, array( __( 'Overtime Hours', 'etwa' ), $summary['overtime_hours'] ?? 0 ) );
		fputcsv( $handle, array( __( 'Employees', 'etwa' ), $summary['employee_count'] ?? 0 ) );
		if ( isset( $summary['labor_cost_egp'] ) && null !== $summary['labor_cost_egp'] ) {
			fputcsv( $handle, array( __( 'Labor Cost (EGP)', 'etwa' ), $summary['labor_cost_egp'] ) );
		}
		fputcsv( $handle, array() );

		fputcsv( $handle, array( __( 'Employee Breakdown', 'etwa' ) ) );
		$emp_headers = array( __( 'Employee', 'etwa' ), __( 'Hours', 'etwa' ), __( 'OT Hours', 'etwa' ) );
		if ( EmployeeCostHelper::can_view_labor_cost() ) {
			$emp_headers[] = __( 'Labor Cost (EGP)', 'etwa' );
		}
		fputcsv( $handle, $emp_headers );
		foreach ( $detail['employees'] ?? array() as $emp ) {
			$row = array( $emp['display_name'], $emp['total_hours'], $emp['overtime_hours'] );
			if ( EmployeeCostHelper::can_view_labor_cost() ) {
				$row[] = $emp['labor_cost_egp'] ?? 0;
			}
			fputcsv( $handle, $row );
		}
		fputcsv( $handle, array() );

		fputcsv( $handle, array( __( 'Entries', 'etwa' ) ) );
		$entry_headers = array( 'Employee', 'Date', 'Start', 'End', 'Task', 'Regular Hrs', 'OT Hrs' );
		if ( EmployeeCostHelper::can_view_labor_cost() ) {
			$entry_headers[] = 'Labor Cost (EGP)';
		}
		fputcsv( $handle, $entry_headers );

		$offset  = 0;
		do {
			$entries = $this->entries->find_by_date_range_page(
				$date_from,
				$date_to,
				self::CHUNK_SIZE,
				$offset,
				null,
				$client_id
			);
			if ( ! $entries ) {
				break;
			}
			$names = UserDisplayNames::map( array_map( fn( Entry $e ) => $e->user_id, $entries ) );
			$rates = EmployeeCostHelper::can_view_labor_cost()
				? EmployeeCostHelper::get_rates_batch_internal( array_map( fn( Entry $e ) => $e->user_id, $entries ) )
				: array();
			foreach ( $entries as $entry ) {
				$row = array(
					UserDisplayNames::get( $names, $entry->user_id ),
					$entry->entry_date,
					DateHelper::format_time( $entry->start_time ),
					DateHelper::format_time( $entry->end_time ),
					$entry->task,
					$entry->regular_hours,
					$entry->overtime_hours,
				);
				if ( EmployeeCostHelper::can_view_labor_cost() ) {
					$row[] = LaborCostCalculator::from_hours(
						(float) $entry->regular_hours,
						(float) $entry->overtime_hours,
						$rates[ $entry->user_id ] ?? 0.0
					);
				}
				fputcsv( $handle, $row );
			}
			$offset += self::CHUNK_SIZE;
		} while ( count( $entries ) === self::CHUNK_SIZE );

		fclose( $handle );
		return $path;
	}

	/** @param array<string,mixed> $detail */
	private function build_client_summary_sheet( Spreadsheet $spreadsheet, array $detail ): void {
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Summary' );
		$client  = $detail['client'] ?? array();
		$summary = $detail['summary'] ?? array();

		$rows = array(
			array( 'Client', $client['name'] ?? '' ),
			array( 'Code', $client['code'] ?? '' ),
			array( 'Department', $client['department'] ?? '' ),
			array( 'Period From', $detail['range']['date_from'] ?? '' ),
			array( 'Period To', $detail['range']['date_to'] ?? '' ),
			array( 'Total Hours', $summary['total_hours'] ?? 0 ),
			array( 'Regular Hours', $summary['regular_hours'] ?? 0 ),
			array( 'Overtime Hours', $summary['overtime_hours'] ?? 0 ),
			array( 'Employees', $summary['employee_count'] ?? 0 ),
			array( 'Last Activity', $summary['last_activity'] ?? '' ),
		);
		if ( isset( $summary['labor_cost_egp'] ) && null !== $summary['labor_cost_egp'] ) {
			$rows[] = array( 'Labor Cost (EGP)', $summary['labor_cost_egp'] );
		}
		$sheet->fromArray( array( 'Metric', 'Value' ), null, 'A1' );
		$sheet->fromArray( $rows, null, 'A2' );
	}

	/** @param array<string,mixed> $detail */
	private function build_client_employee_sheet( Spreadsheet $spreadsheet, array $detail ): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Employees' );
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$headers      = array( 'Employee', 'Total Hours', 'Regular Hours', 'OT Hours' );
		if ( $include_cost ) {
			$headers[] = 'Labor Cost (EGP)';
		}
		$sheet->fromArray( $headers, null, 'A1' );
		$row = 2;
		foreach ( $detail['employees'] ?? array() as $emp ) {
			$row_data = array(
				$emp['display_name'],
				$emp['total_hours'],
				$emp['regular_hours'],
				$emp['overtime_hours'],
			);
			if ( $include_cost ) {
				$row_data[] = $emp['labor_cost_egp'] ?? 0;
			}
			$sheet->fromArray( $row_data, null, "A{$row}" );
			++$row;
		}
	}

	/** @param array<int,array<string,mixed>> $trend */
	private function build_client_trend_sheet( Spreadsheet $spreadsheet, array $trend ): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Hours Trend' );
		$sheet->fromArray( array( 'Month', 'Total Hours', 'OT Hours' ), null, 'A1' );
		$row = 2;
		foreach ( $trend as $point ) {
			$sheet->fromArray(
				array( $point['period'], $point['total_hours'], $point['overtime_hours'] ),
				null,
				"A{$row}"
			);
			++$row;
		}
	}

	private function build_client_entries_sheet_chunked(
		Spreadsheet $spreadsheet,
		int $client_id,
		string $date_from,
		string $date_to
	): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Entries' );
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$headers      = array( 'Employee', 'Date', 'Start', 'End', 'Task', 'Regular Hrs', 'OT Hrs' );
		if ( $include_cost ) {
			$headers[] = 'Labor Cost (EGP)';
		}
		$sheet->fromArray( $headers, null, 'A1' );

		$row     = 2;
		$offset  = 0;

		do {
			$entries = $this->entries->find_by_date_range_page(
				$date_from,
				$date_to,
				self::CHUNK_SIZE,
				$offset,
				null,
				$client_id
			);
			if ( ! $entries ) {
				break;
			}
			$names = UserDisplayNames::map( array_map( fn( Entry $e ) => $e->user_id, $entries ) );
			$rates = $include_cost
				? EmployeeCostHelper::get_rates_batch_internal( array_map( fn( Entry $e ) => $e->user_id, $entries ) )
				: array();
			foreach ( $entries as $entry ) {
				$row_data = array(
					UserDisplayNames::get( $names, $entry->user_id ),
					$entry->entry_date,
					DateHelper::format_time( $entry->start_time ),
					DateHelper::format_time( $entry->end_time ),
					$entry->task,
					$entry->regular_hours,
					$entry->overtime_hours,
				);
				if ( $include_cost ) {
					$row_data[] = LaborCostCalculator::from_hours(
						(float) $entry->regular_hours,
						(float) $entry->overtime_hours,
						$rates[ $entry->user_id ] ?? 0.0
					);
				}
				$sheet->fromArray( $row_data, null, "A{$row}" );
				++$row;
			}
			$offset += self::CHUNK_SIZE;
		} while ( count( $entries ) === self::CHUNK_SIZE );
	}

	private function build_raw_sheet_chunked(
		Spreadsheet $spreadsheet,
		string $date_from,
		string $date_to,
		?int $user_id
	): void {
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Raw Timesheet' );

		$headers = array( 'Employee', 'Date', 'Start', 'End', 'Client', 'Task', 'Notes', 'Duration (min)', 'Regular Hrs', 'OT Hrs' );
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		if ( $include_cost ) {
			$headers[] = 'Labor Cost (EGP)';
		}
		$sheet->fromArray( $headers, null, 'A1' );

		$row     = 2;
		$offset  = 0;

		do {
			$entries = $this->entries->find_by_date_range_page(
				$date_from,
				$date_to,
				self::CHUNK_SIZE,
				$offset,
				$user_id
			);

			if ( ! $entries ) {
				break;
			}

			$names = UserDisplayNames::map( array_map( fn( Entry $entry ) => $entry->user_id, $entries ) );
			$rates = $include_cost
				? EmployeeCostHelper::get_rates_batch_internal( array_map( fn( Entry $entry ) => $entry->user_id, $entries ) )
				: array();

			foreach ( $entries as $entry ) {
				$row_data = array(
					UserDisplayNames::get( $names, $entry->user_id ),
					$entry->entry_date,
					DateHelper::format_time( $entry->start_time ),
					DateHelper::format_time( $entry->end_time ),
					$entry->client_name,
					$entry->task,
					$entry->notes,
					$entry->duration_minutes,
					$entry->regular_hours,
					$entry->overtime_hours,
				);
				if ( $include_cost ) {
					$rate = $rates[ $entry->user_id ] ?? 0.0;
					$row_data[] = LaborCostCalculator::from_hours(
						(float) $entry->regular_hours,
						(float) $entry->overtime_hours,
						$rate
					);
				}
				$sheet->fromArray( $row_data, null, "A{$row}" );
				++$row;
			}

			$offset += self::CHUNK_SIZE;
		} while ( count( $entries ) === self::CHUNK_SIZE );
	}

	/** @param array<int,array<string,mixed>> $clients */
	private function build_client_sheet( Spreadsheet $spreadsheet, array $clients ): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Client Analytics' );
		$sheet->fromArray( array( 'Client', 'Department', 'Total Hours', 'OT Hours', 'Consumption %', 'OT Ratio %' ), null, 'A1' );

		$row = 2;
		foreach ( $clients as $client ) {
			$sheet->fromArray(
				array(
					$client['name'],
					$client['department'],
					$client['total_hours'],
					$client['overtime_hours'],
					$client['consumption_pct'],
					$client['overtime_ratio_pct'],
				),
				null,
				"A{$row}"
			);
			++$row;
		}
	}

	/** @param array<int,array<string,mixed>> $employees */
	private function build_employee_sheet( Spreadsheet $spreadsheet, array $employees ): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Employee Summary' );
		$include_cost = EmployeeCostHelper::can_view_labor_cost();
		$headers      = array( 'Employee', 'Worked Hours', 'OT Hours', 'Utilization %', 'Status' );
		if ( $include_cost ) {
			$headers[] = 'Labor Cost (EGP)';
		}
		$sheet->fromArray( $headers, null, 'A1' );

		$row = 2;
		foreach ( $employees as $emp ) {
			$row_data = array(
				$emp['display_name'],
				$emp['worked_hours'],
				$emp['overtime_hours'],
				$emp['utilization_pct'],
				$emp['status'],
			);
			if ( $include_cost ) {
				$row_data[] = $emp['labor_cost_egp'] ?? 0;
			}
			$sheet->fromArray( $row_data, null, "A{$row}" );
			++$row;
		}
	}

	/** @param array<int,array<string,mixed>> $trends */
	private function build_chart_sheet( Spreadsheet $spreadsheet, array $trends ): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Charts' );

		$sheet->fromArray( array( 'Month', 'Regular Hours', 'Overtime Hours' ), null, 'A1' );
		$row = 2;
		foreach ( $trends as $t ) {
			$sheet->fromArray( array( $t['month'], $t['regular_hours'], $t['overtime_hours'] ), null, "A{$row}" );
			++$row;
		}

		if ( count( $trends ) > 0 ) {
			$last   = $row - 1;
			$series = new DataSeries(
				DataSeries::TYPE_BARCHART,
				DataSeries::GROUPING_CLUSTERED,
				range( 0, 1 ),
				array( new Title( 'Hours' ) ),
				array( new Title( 'Month' ) ),
				array(
					new DataSeriesValues( DataSeriesValues::DATASIZE_NUMBER, "'Charts'!\$B\$2:\$B\${$last}" ),
					new DataSeriesValues( DataSeriesValues::DATASIZE_NUMBER, "'Charts'!\$C\$2:\$C\${$last}" ),
				),
				array( new DataSeriesValues( DataSeriesValues::DATASIZE_STRING, "'Charts'!\$A\$2:\$A\${$last}" ) )
			);
			$plot  = new PlotArea( null, array( $series ) );
			$chart = new Chart( 'trend_chart', new Title( 'Monthly Trends' ), new Legend(), $plot );
			$chart->setTopLeftPosition( 'E2' );
			$chart->setBottomRightPosition( 'M18' );
			$sheet->addChart( $chart );
		}
	}

	private function build_overtime_sheet_chunked(
		Spreadsheet $spreadsheet,
		string $date_from,
		string $date_to,
		?int $user_id
	): void {
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle( 'Overtime' );
		$sheet->fromArray( array( 'Employee', 'Date', 'OT Hours', 'Status', 'Approved Hours', 'Notes' ), null, 'A1' );

		$row    = 2;
		$offset = 0;

		do {
			$records = $this->overtime->find_by_date_range_page(
				$date_from,
				$date_to,
				self::CHUNK_SIZE,
				$offset,
				$user_id
			);

			if ( ! $records ) {
				break;
			}

			$names = UserDisplayNames::map( array_map( fn( Overtime $record ) => $record->user_id, $records ) );

			foreach ( $records as $record ) {
				$sheet->fromArray(
					array(
						UserDisplayNames::get( $names, $record->user_id ),
						$record->entry_date,
						$record->overtime_hours,
						$record->status,
						$record->approved_hours,
						$record->review_notes,
					),
					null,
					"A{$row}"
				);
				++$row;
			}

			$offset += self::CHUNK_SIZE;
		} while ( count( $records ) === self::CHUNK_SIZE );
	}
}
