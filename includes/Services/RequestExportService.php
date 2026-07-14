<?php

/**

 * Generic request Excel export service.

 *

 * @package ETWA

 */



declare(strict_types=1);



namespace ETWA\Services;



use ETWA\Helpers\DateHelper;
use ETWA\Helpers\RequestHelper;

use ETWA\Repositories\ClientRepository;

use ETWA\Repositories\RequestRepository;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use WP_Error;



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



final class RequestExportService {



	public function __construct(

		private RequestRepository $requests = new RequestRepository(),

		private ClientRepository $clients = new ClientRepository(),

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



		$type = sanitize_key( (string) ( $filters['request_type'] ?? '' ) );

		$rows = $this->requests->all( $filters, 5000, 0 );



		$spreadsheet = new Spreadsheet();

		$sheet       = $spreadsheet->getActiveSheet();

		$sheet->setTitle( RequestHelper::type_label( $type ) ?: 'Requests' );



		$headers = $this->headers_for_type( $type );

		$col     = 1;

		foreach ( $headers as $header ) {

			$sheet->setCellValueByColumnAndRow( $col, 1, $header );

			++$col;

		}



		$row_num = 2;

		foreach ( $rows as $request ) {

			$user = get_userdata( $request->user_id );

			$values = $this->row_values_for_type( $type, $request, $user ? $user->display_name : '' );

			$col = 1;

			foreach ( $values as $value ) {

				$sheet->setCellValueByColumnAndRow( $col, $row_num, $value );

				++$col;

			}

			++$row_num;

		}



		$writer   = new Xlsx( $spreadsheet );

		$filename = wp_tempnam( 'etwa-requests-' . $type );

		if ( ! $filename ) {

			return new WP_Error( 'export_failed', __( 'Could not create export file.', 'etwa' ), array( 'status' => 500 ) );

		}

		$writer->save( $filename );

		return $filename;

	}



	/** @return string[] */

	private function headers_for_type( string $type ): array {

		if ( RequestHelper::TYPE_WFH === $type ) {

			return array(

				__( 'Employee', 'etwa' ),

				__( 'Request Date', 'etwa' ),

				__( 'Work Date', 'etwa' ),

				__( 'Start Time', 'etwa' ),

				__( 'End Time', 'etwa' ),

				__( 'Reason', 'etwa' ),

				__( 'Notes', 'etwa' ),

				__( 'Status', 'etwa' ),

				__( 'Submitted', 'etwa' ),

			);

		}



		if ( RequestHelper::TYPE_HOLIDAY_WORK === $type ) {

			return array(

				__( 'Employee', 'etwa' ),

				__( 'Work Date', 'etwa' ),

				__( 'Start Time', 'etwa' ),

				__( 'End Time', 'etwa' ),

				__( 'Work Type', 'etwa' ),

				__( 'Client', 'etwa' ),

				__( 'Task', 'etwa' ),

				__( 'Reason', 'etwa' ),

				__( 'Notes', 'etwa' ),

				__( 'Status', 'etwa' ),

				__( 'Submitted', 'etwa' ),

			);

		}



		return array(

			__( 'Employee', 'etwa' ),

			__( 'Date', 'etwa' ),

			__( 'Time', 'etwa' ),

			__( 'Permission Type', 'etwa' ),

			__( 'Reason', 'etwa' ),

			__( 'Notes', 'etwa' ),

			__( 'Status', 'etwa' ),

			__( 'Submitted', 'etwa' ),

		);

	}



	/** @return array<int,string|float|null> */

	private function row_values_for_type( string $type, \ETWA\Models\Request $request, string $employee_name ): array {

		$extra = RequestHelper::decode_extra_data( $request->extra_data );



		if ( RequestHelper::TYPE_WFH === $type ) {

			return array(

				$employee_name,

				$request->request_date,

				$request->work_date ?? '',

				$request->start_time ? DateHelper::format_time( $request->start_time ) : '',

				$request->end_time ? DateHelper::format_time( $request->end_time ) : '',

				$request->reason,

				$request->notes,

				ucfirst( $request->status ),

				DateHelper::format_datetime( $request->created_at ),

			);

		}



		if ( RequestHelper::TYPE_HOLIDAY_WORK === $type ) {

			$client_id = (int) ( $extra['client_id'] ?? 0 );

			$client    = $client_id ? $this->clients->find( $client_id ) : null;



			return array(

				$employee_name,

				$request->work_date ?? '',

				$request->start_time ? DateHelper::format_time( $request->start_time ) : '',

				$request->end_time ? DateHelper::format_time( $request->end_time ) : '',

				RequestHelper::holiday_work_type_label( $extra['work_type'] ?? null ),

				$client ? $client->name : '',

				(string) ( $extra['task'] ?? '' ),

				$request->reason,

				$request->notes,

				ucfirst( $request->status ),

				DateHelper::format_datetime( $request->created_at ),

			);

		}



		return array(

			$employee_name,

			$request->work_date ?? $request->request_date,

			RequestHelper::permission_duration_label( $request->permission_type, $request->start_time, $request->end_time, $extra ),

			RequestHelper::permission_type_label( $request->permission_type ),

			RequestHelper::permission_reason_display( $request, $extra ),

			$request->notes,

			ucfirst( $request->status ),

			DateHelper::format_datetime( $request->created_at ),

		);

	}

}


