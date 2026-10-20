<?php
/**
 * Pending review counts for portal navigation badges.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Services;

use ETWA\Frontend\PortalNav;
use ETWA\Helpers\DepartmentHelper;
use ETWA\Helpers\RoleManager;
use ETWA\Helpers\SuperClientAccessHelper;
use ETWA\Repositories\RequestRepository;
use ETWA\Repositories\TimesheetRepository;
use ETWA\Repositories\VacationRequestRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewerCountsService {

	public function __construct(
		private RequestRepository $requests = new RequestRepository(),
		private VacationRequestRepository $vacation_requests = new VacationRequestRepository(),
		private TimesheetRepository $timesheets = new TimesheetRepository(),
		private RequestService $request_service = new RequestService(),
		private VacationService $vacation_service = new VacationService(),
	) {}

	/** @return array<string,int> */
	public function nav_badges( int $viewer_id ): array {
		$badges = array();

		if ( user_can( $viewer_id, RoleManager::CAP_VIEW_ALL ) ) {
			$badges['hr'] = $this->timesheets->count_by_filters( array( 'status' => 'submitted' ) );
		}

		if ( $this->request_service->can_review( $viewer_id ) ) {
			$counts = $this->request_service->pending_counts( $viewer_id );
			$vac    = $this->scoped_vacation_pending( $viewer_id );

			$badges[ PortalNav::hr_request_view_id( 'vacation' ) ]     = $vac;
			$badges[ PortalNav::hr_request_view_id( 'wfh' ) ]          = (int) ( $counts['wfh'] ?? 0 );
			$badges[ PortalNav::hr_request_view_id( 'permission' ) ]   = (int) ( $counts['permission'] ?? 0 );
			$badges[ PortalNav::hr_request_view_id( 'holiday_work' ) ] = (int) ( $counts['holiday_work'] ?? 0 );

			// Legacy aggregate key (dept dashboard / older links).
			$badges['requests-hr'] = $vac
				+ (int) ( $counts['wfh'] ?? 0 )
				+ (int) ( $counts['permission'] ?? 0 )
				+ (int) ( $counts['holiday_work'] ?? 0 );
		}

		if ( ( new DepartmentService() )->can_view_department_dashboard( $viewer_id ) ) {
			$badges['dept-dashboard'] = $this->reviewer_pending_total( $viewer_id );
		}

		$badges = array_merge( $badges, $this->product_request_nav_badges( $viewer_id ) );

		return $badges;
	}

	/** @return array<string,int> */
	private function product_request_nav_badges( int $viewer_id ): array {
		if ( ! SuperClientAccessHelper::can_access_product_hub( $viewer_id ) ) {
			return array();
		}

		$counts = ( new SuperClientRequestService() )->pending_counts_for_user( $viewer_id );
		if ( is_wp_error( $counts ) ) {
			return array();
		}

		$badges = array();
		$total  = (int) ( $counts['total'] ?? 0 );

		if ( SuperClientAccessHelper::can_manage_products( $viewer_id ) ) {
			if ( $total > 0 ) {
				$badges['super-clients'] = $total;
			}
		} elseif ( $total > 0 ) {
			$badges['product-hub'] = $total;
		}

		foreach ( $counts['tabs'] ?? array() as $tab ) {
			$tab_id = (int) ( $tab['id'] ?? 0 );
			$count  = (int) ( $tab['pending_count'] ?? 0 );
			if ( $tab_id <= 0 || $count <= 0 ) {
				continue;
			}
			$badges[ 'super-client-' . $tab_id ] = $count;
		}

		return $badges;
	}

	public function reviewer_pending_total( int $viewer_id ): int {
		$counts = $this->request_service->pending_counts( $viewer_id );
		$vac    = $this->scoped_vacation_pending( $viewer_id );
		return (int) ( $counts['total'] ?? 0 ) + $vac;
	}

	private function scoped_vacation_pending( int $viewer_id ): int {
		$filters = array( 'status' => 'pending' );
		if ( $this->vacation_service->is_department_head( $viewer_id ) && ! $this->vacation_service->can_manage( $viewer_id ) ) {
			$dept_id = DepartmentHelper::get_headed_department_id( $viewer_id );
			if ( ! $dept_id ) {
				return 0;
			}
			$filters['user_ids'] = DepartmentHelper::employee_ids_in_department( $dept_id );
		}
		return $this->vacation_requests->count( $filters );
	}
}
