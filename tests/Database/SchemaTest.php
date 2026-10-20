<?php
/**
 * Schema migration guard unit tests.
 *
 * @package ETWA
 */

declare(strict_types=1);

namespace ETWA\Tests\Database;

use ETWA\Tests\Support\SchemaTestHelper;
use ETWA\Tests\Support\WpdbMigrationTestDouble;
use ETWA\Tests\TestCase;

final class SchemaTest extends TestCase {

	private WpdbMigrationTestDouble $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb = new WpdbMigrationTestDouble();
		$this->wpdb->installGlobal();
	}

	public function test_upgrade_deliverables_contract_totals_v1190_noops_when_tables_missing(): void {
		$scopes = $this->wpdb->prefix . 'etwa_client_scopes';

		$this->wpdb->tables  = array( $scopes );
		$this->wpdb->columns = array(
			$scopes => array( 'contract_deliverables', 'patch_count', 'expected_per_patch' ),
		);

		SchemaTestHelper::invokeUpgrade( 'upgrade_deliverables_contract_totals_v1190' );

		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'etwa_scope_patches' ) );
		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'etwa_scope_patch_lines' ) );
		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'etwa_scope_contract_lines' ) );
	}

	public function test_upgrade_deliverables_contract_totals_v1190_still_runs_normally_when_tables_exist(): void {
		$scopes      = $this->wpdb->prefix . 'etwa_client_scopes';
		$patches     = $this->wpdb->prefix . 'etwa_scope_patches';
		$patch_lines = $this->wpdb->prefix . 'etwa_scope_patch_lines';
		$lines       = $this->wpdb->prefix . 'etwa_scope_contract_lines';

		$this->wpdb->tables  = array( $scopes, $patches, $patch_lines, $lines );
		$this->wpdb->columns = array(
			$scopes      => array( 'contract_deliverables', 'patch_count', 'expected_per_patch' ),
			$patches     => array( 'expected_qty' ),
			$patch_lines => array( 'qty_delta' ),
			$lines       => array( 'planned_qty' ),
		);
		$this->wpdb->get_results_responses = array(
			array( (object) array( 'id' => 1 ) ),
			array(
				(object) array(
					'id'                    => 10,
					'contract_deliverables' => 0,
					'patch_count'           => 0,
					'expected_per_patch'    => 0,
				),
			),
		);

		SchemaTestHelper::invokeUpgrade( 'upgrade_deliverables_contract_totals_v1190' );

		$this->assertNotEmpty( $this->wpdb->queriesReferencing( 'etwa_scope_patches' ) );
		$this->assertNotEmpty( $this->wpdb->queriesReferencing( 'etwa_scope_patch_lines' ) );
	}

	public function test_upgrade_multi_deliverable_allocations_v1210_noops_when_columns_missing(): void {
		$allocs = $this->wpdb->prefix . 'etwa_deliverable_allocations';
		$items  = $this->wpdb->prefix . 'etwa_deliverable_allocation_items';

		$this->wpdb->tables  = array( $allocs, $items );
		$this->wpdb->columns = array(
			$allocs => array( 'task_name' ),
			$items  => array( 'allocation_id' ),
		);

		SchemaTestHelper::invokeUpgrade( 'upgrade_multi_deliverable_allocations_v1210' );

		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'deliverable_type_id' ) );
		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'etwa_deliverable_allocation_items' ) );
	}

	public function test_upgrade_multi_deliverable_allocations_v1210_still_runs_normally_when_columns_exist(): void {
		$allocs = $this->wpdb->prefix . 'etwa_deliverable_allocations';
		$items  = $this->wpdb->prefix . 'etwa_deliverable_allocation_items';

		$this->wpdb->tables  = array( $allocs, $items );
		$this->wpdb->columns = array(
			$allocs => array( 'task_name', 'deliverable_type_id', 'quantity', 'scope_impact' ),
			$items  => array( 'allocation_id', 'deliverable_type_id', 'quantity', 'scope_impact' ),
		);

		SchemaTestHelper::invokeUpgrade( 'upgrade_multi_deliverable_allocations_v1210' );

		$this->assertNotEmpty( $this->wpdb->queriesReferencing( 'MODIFY deliverable_type_id' ) );
		$this->assertNotEmpty( $this->wpdb->queriesReferencing( 'INSERT INTO' ) );
		$this->assertNotEmpty( $this->wpdb->queriesReferencing( 'etwa_deliverable_allocation_items' ) );
	}

	public function test_upgrade_packages_and_retire_batches_v1230_noops_when_patches_table_missing(): void {
		$scopes = $this->wpdb->prefix . 'etwa_client_scopes';

		$this->wpdb->tables  = array( $scopes );
		$this->wpdb->columns = array(
			$scopes => array( 'package_id', 'package_snapshot', 'contract_deliverables', 'patch_count', 'expected_per_patch' ),
		);
		$this->wpdb->get_results_responses = array( array() );

		SchemaTestHelper::invokeUpgrade( 'upgrade_packages_and_retire_batches_v1230' );

		$this->assertSame( array(), $this->wpdb->queriesReferencing( 'etwa_scope_patches' ) );
	}

	public function test_upgrade_packages_and_retire_batches_v1230_still_runs_normally_when_patches_table_exists(): void {
		$scopes  = $this->wpdb->prefix . 'etwa_client_scopes';
		$patches = $this->wpdb->prefix . 'etwa_scope_patches';

		$this->wpdb->tables  = array( $scopes, $patches );
		$this->wpdb->columns = array(
			$scopes  => array( 'package_id', 'package_snapshot', 'contract_deliverables', 'patch_count', 'expected_per_patch' ),
			$patches => array( 'status' ),
		);
		$this->wpdb->get_results_responses = array( array() );

		SchemaTestHelper::invokeUpgrade( 'upgrade_packages_and_retire_batches_v1230' );

		$this->assertNotEmpty( $this->wpdb->queriesReferencing( "UPDATE {$patches}" ) );
	}
}
