<?php
/**
 * Vacation Excel export service.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Helpers\BrandHelper;
use ETWA\Helpers\DateHelper;
use ETWA\Helpers\VacationHelper;
use ETWA\Repositories\VacationRecordRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VacationExportService {

	public function __construct(
		private VacationRecordRepository $records = new VacationRecordRepository(),
		private \ETWA\Repositories\VacationRequestRepository $requests = new \ETWA\Repositories\VacationRequestRepository(),
	) {}

	/** @param array<string,mixed> $filters @return string|WP_Error */
	public function generate( array $filters ): string|WP_Error {
		if ( ! class_exists( Spreadsheet::class ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'Excel export is not available. Install PhpSpreadsheet with Composer.', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$rows = $this->records->all( $filters, 5000, 0 );
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Vacation Report' );

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Leave Type', 'etwa' ),
			__( 'Status', 'etwa' ),
			__( 'Start Date', 'etwa' ),
			__( 'End Date', 'etwa' ),
			__( 'Days', 'etwa' ),
			__( 'Reason', 'etwa' ),
			__( 'Remaining Balance', 'etwa' ),
			__( 'Created By', 'etwa' ),
			__( 'Created Date', 'etwa' ),
		);

		$col = 1;
		foreach ( $headers as $header ) {
			$sheet->setCellValueByColumnAndRow( $col, 1, $header );
			++$col;
		}

		$row_num = 2;
		$types_cache = array();
		foreach ( $rows as $record ) {
			$user    = get_userdata( $record->user_id );
			$creator = get_userdata( $record->created_by );
			if ( ! isset( $types_cache[ $record->leave_type_id ] ) ) {
				$type = ( new \ETWA\Repositories\LeaveTypeRepository() )->find( $record->leave_type_id );
				$types_cache[ $record->leave_type_id ] = $type ? $type->name : '';
			}
			$remaining = VacationHelper::remaining_annual_balance( $record->user_id );

			$sheet->setCellValueByColumnAndRow( 1, $row_num, $user ? $user->display_name : '' );
			$sheet->setCellValueByColumnAndRow( 2, $row_num, $types_cache[ $record->leave_type_id ] );
			$sheet->setCellValueByColumnAndRow( 3, $row_num, ucfirst( $record->status ) );
			$sheet->setCellValueByColumnAndRow( 4, $row_num, $record->start_date );
			$sheet->setCellValueByColumnAndRow( 5, $row_num, $record->end_date );
			$sheet->setCellValueByColumnAndRow( 6, $row_num, $record->days );
			$sheet->setCellValueByColumnAndRow( 7, $row_num, $record->reason );
			$sheet->setCellValueByColumnAndRow( 8, $row_num, $remaining );
			$sheet->setCellValueByColumnAndRow( 9, $row_num, $creator ? $creator->display_name : '' );
			$sheet->setCellValueByColumnAndRow( 10, $row_num, DateHelper::format_datetime( (string) $record->created_at ) );
			++$row_num;
		}

		$upload   = \wp_upload_dir();
		$dir      = \trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		\wp_mkdir_p( $dir );
		$filename = sprintf( '%s-vacation-report-%s.xlsx', BrandHelper::SLUG, gmdate( 'YmdHis' ) );
		$path     = \trailingslashit( $dir ) . $filename;

		$writer = new Xlsx( $spreadsheet );
		$writer->save( $path );
		$spreadsheet->disconnectWorksheets();

		return $path;
	}

	/** @param array<string,mixed> $filters @return string|WP_Error */
	public function generate_requests( array $filters ): string|WP_Error {
		if ( ! class_exists( Spreadsheet::class ) ) {
			return new WP_Error(
				'missing_dependency',
				__( 'Excel export is not available. Install PhpSpreadsheet with Composer.', 'etwa' ),
				array( 'status' => 500 )
			);
		}

		$rows        = $this->requests->all( $filters, 5000, 0 );
		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->setTitle( 'Vacation Requests' );

		$headers = array(
			__( 'Employee', 'etwa' ),
			__( 'Leave Type', 'etwa' ),
			__( 'From', 'etwa' ),
			__( 'To', 'etwa' ),
			__( 'Days', 'etwa' ),
			__( 'Reason', 'etwa' ),
			__( 'Status', 'etwa' ),
			__( 'Submitted', 'etwa' ),
		);

		$col = 1;
		foreach ( $headers as $header ) {
			$sheet->setCellValueByColumnAndRow( $col, 1, $header );
			++$col;
		}

		$row_num     = 2;
		$types_cache = array();
		foreach ( $rows as $request ) {
			$user = get_userdata( $request->user_id );
			if ( ! isset( $types_cache[ $request->leave_type_id ] ) ) {
				$type = ( new \ETWA\Repositories\LeaveTypeRepository() )->find( $request->leave_type_id );
				$types_cache[ $request->leave_type_id ] = $type ? $type->name : '';
			}

			$sheet->setCellValueByColumnAndRow( 1, $row_num, $user ? $user->display_name : '' );
			$sheet->setCellValueByColumnAndRow( 2, $row_num, $types_cache[ $request->leave_type_id ] );
			$sheet->setCellValueByColumnAndRow( 3, $row_num, $request->start_date );
			$sheet->setCellValueByColumnAndRow( 4, $row_num, $request->end_date );
			$sheet->setCellValueByColumnAndRow( 5, $row_num, $request->days );
			$sheet->setCellValueByColumnAndRow( 6, $row_num, $request->reason );
			$sheet->setCellValueByColumnAndRow( 7, $row_num, ucfirst( $request->status ) );
			$sheet->setCellValueByColumnAndRow( 8, $row_num, DateHelper::format_datetime( (string) $request->created_at ) );
			++$row_num;
		}

		$upload = \wp_upload_dir();
		$dir    = \trailingslashit( $upload['basedir'] ) . 'etwa-exports';
		\wp_mkdir_p( $dir );
		$filename = sprintf( '%s-vacation-requests-%s.xlsx', BrandHelper::SLUG, gmdate( 'YmdHis' ) );
		$path     = \trailingslashit( $dir ) . $filename;

		$writer = new Xlsx( $spreadsheet );
		$writer->save( $path );
		$spreadsheet->disconnectWorksheets();

		return $path;
	}
}
