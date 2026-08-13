<?php
/**
 * Isolated integration test for ClientConvertToProjectService (rolls back all changes).
 *
 * Usage (from site public root):
 *   php wp-content/plugins/employee-timesheet-workforce-analytics/scripts/test-convert-to-project.php
 *
 * @package ETWA
 */

declare(strict_types=1);

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
	exit( 1 );
}

require $wp_load;

use ETWA\Models\Project;
use ETWA\Repositories\ClientRepository;
use ETWA\Repositories\ClientScopeRepository;
use ETWA\Repositories\DeliverableAllocationRepository;
use ETWA\Repositories\ProjectRepository;
use ETWA\Services\ClientConvertToProjectService;

global $wpdb;

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'ID' ) ) );
if ( ! $admin ) {
	fwrite( STDERR, "No administrator user found.\n" );
	exit( 1 );
}
$user_id = (int) $admin[0]->ID;

$clients   = new ClientRepository();
$projects  = new ProjectRepository();
$scopes    = new ClientScopeRepository();
$service   = new ClientConvertToProjectService();
$entries_t = $wpdb->prefix . 'etwa_entries';

$suffix = 'T' . wp_generate_password( 6, false, false );

$parent_id = $clients->create(
	array(
		'name'       => "Convert Parent {$suffix}",
		'code'       => "CP{$suffix}",
		'department' => 'General',
		'is_active'  => 1,
	)
);
$source_id = $clients->create(
	array(
		'name'       => "Convert Source {$suffix}",
		'code'       => "CS{$suffix}",
		'department' => 'General',
		'is_active'  => 1,
	)
);

$child_project_id = $projects->create(
	array(
		'client_id'   => $source_id,
		'name'        => "Child Project {$suffix}",
		'description' => '',
		'status'      => Project::STATUS_ACTIVE,
	)
);

$scope_direct_id = $scopes->create(
	array(
		'client_id'   => $source_id,
		'name'        => "Scope Direct {$suffix}",
		'status'      => 'active',
	)
);
$scope_child_id = $scopes->create(
	array(
		'client_id'   => $source_id,
		'project_id'  => $child_project_id,
		'name'        => "Scope Child {$suffix}",
		'status'      => 'active',
	)
);

$timesheet_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}etwa_timesheets LIMIT 1" );
if ( $timesheet_id <= 0 ) {
	$wpdb->insert(
		$wpdb->prefix . 'etwa_timesheets',
		array(
			'user_id'    => $user_id,
			'week_start' => gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
			'status'     => 'approved',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		)
	);
	$timesheet_id = (int) $wpdb->insert_id;
}

$now = current_time( 'mysql' );
$wpdb->insert(
	$entries_t,
	array(
		'timesheet_id'   => $timesheet_id,
		'user_id'        => $user_id,
		'entry_date'     => gmdate( 'Y-m-d' ),
		'start_time'     => '09:00:00',
		'end_time'       => '10:00:00',
		'client_id'      => $source_id,
		'project_id'     => null,
		'task'           => 'Direct hours',
		'regular_hours'  => 1,
		'overtime_hours' => 0,
		'created_at'     => $now,
		'updated_at'     => $now,
	)
);
$entry_direct_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$entries_t,
	array(
		'timesheet_id'   => $timesheet_id,
		'user_id'        => $user_id,
		'entry_date'     => gmdate( 'Y-m-d' ),
		'start_time'     => '10:00:00',
		'end_time'       => '11:00:00',
		'client_id'      => $source_id,
		'project_id'     => $child_project_id,
		'task'           => 'Child project hours',
		'regular_hours'  => 1,
		'overtime_hours' => 0,
		'created_at'     => $now,
		'updated_at'     => $now,
	)
);
$entry_child_id = (int) $wpdb->insert_id;

$preview = $service->preview( $user_id, $source_id, $parent_id );
if ( is_wp_error( $preview ) ) {
	cleanup( $parent_id, $source_id, $entry_direct_id, $entry_child_id, $scope_direct_id, $scope_child_id, $child_project_id );
	fwrite( STDERR, 'Preview failed: ' . $preview->get_error_message() . "\n" );
	exit( 1 );
}

if ( empty( $preview['can_convert'] ) ) {
	cleanup( $parent_id, $source_id, $entry_direct_id, $entry_child_id, $scope_direct_id, $scope_child_id, $child_project_id );
	fwrite( STDERR, "Preview blocked conversion unexpectedly.\n" );
	exit( 1 );
}

$result = $service->convert( $user_id, $source_id, $parent_id );
if ( is_wp_error( $result ) ) {
	cleanup( $parent_id, $source_id, $entry_direct_id, $entry_child_id, $scope_direct_id, $scope_child_id, $child_project_id );
	fwrite( STDERR, 'Convert failed: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

$new_project_id = (int) ( $result['project_id'] ?? 0 );
$errors         = array();

$source_client = $clients->find( $source_id );
if ( ! $source_client || $source_client->is_active ) {
	$errors[] = 'Source client should be inactive.';
}

$direct_entry = $wpdb->get_row( $wpdb->prepare( "SELECT client_id, project_id FROM {$entries_t} WHERE id = %d", $entry_direct_id ) );
if ( (int) $direct_entry->client_id !== $parent_id || (int) $direct_entry->project_id !== $new_project_id ) {
	$errors[] = 'Direct entry not reassigned to parent + new project.';
}

$child_entry = $wpdb->get_row( $wpdb->prepare( "SELECT client_id, project_id FROM {$entries_t} WHERE id = %d", $entry_child_id ) );
if ( (int) $child_entry->client_id !== $parent_id || (int) $child_entry->project_id !== $child_project_id ) {
	$errors[] = 'Child-project entry client_id not moved to parent.';
}

$direct_scope = $scopes->find( $scope_direct_id );
if ( ! $direct_scope || (int) $direct_scope->client_id !== $parent_id || (int) $direct_scope->project_id !== $new_project_id ) {
	$errors[] = 'Direct scope not reassigned to parent + new project.';
}

$child_scope = $scopes->find( $scope_child_id );
if ( ! $child_scope || (int) $child_scope->client_id !== $parent_id || (int) $child_scope->project_id !== $child_project_id ) {
	$errors[] = 'Child scope client_id not moved to parent.';
}

$moved_child = $projects->find( $child_project_id );
if ( ! $moved_child || (int) $moved_child->client_id !== $parent_id ) {
	$errors[] = 'Child project not moved to parent as sibling.';
}

$new_project = $projects->find( $new_project_id );
if ( ! $new_project || (int) $new_project->client_id !== $parent_id ) {
	$errors[] = 'New project not created under parent.';
}

if ( $clients->entry_count( $source_id ) > 0 || $clients->scope_count( $source_id ) > 0 ) {
	$errors[] = 'Source client still has entries or scopes.';
}

cleanup( $parent_id, $source_id, $entry_direct_id, $entry_child_id, $scope_direct_id, $scope_child_id, $child_project_id, $new_project_id );

if ( $errors ) {
	fwrite( STDERR, "FAILED:\n- " . implode( "\n- ", $errors ) . "\n" );
	exit( 1 );
}

echo "PASS: convert-to-project integration test ({$suffix})\n";
exit( 0 );

function cleanup(
	int $parent_id,
	int $source_id,
	int $entry_direct_id,
	int $entry_child_id,
	int $scope_direct_id,
	int $scope_child_id,
	int $child_project_id,
	int $new_project_id = 0
): void {
	global $wpdb;
	$entries_t = $wpdb->prefix . 'etwa_entries';
	$scopes_t  = $wpdb->prefix . 'etwa_client_scopes';
	$projects_t = $wpdb->prefix . 'etwa_projects';
	$clients_t = $wpdb->prefix . 'etwa_clients';

	foreach ( array( $entry_direct_id, $entry_child_id ) as $id ) {
		if ( $id > 0 ) {
			$wpdb->delete( $entries_t, array( 'id' => $id ), array( '%d' ) );
		}
	}
	foreach ( array( $scope_direct_id, $scope_child_id ) as $id ) {
		if ( $id > 0 ) {
			$wpdb->delete( $scopes_t, array( 'id' => $id ), array( '%d' ) );
		}
	}
	foreach ( array_filter( array( $child_project_id, $new_project_id ) ) as $id ) {
		$wpdb->delete( $projects_t, array( 'id' => $id ), array( '%d' ) );
	}
	foreach ( array( $parent_id, $source_id ) as $id ) {
		if ( $id > 0 ) {
			$wpdb->delete( $clients_t, array( 'id' => $id ), array( '%d' ) );
		}
	}
}
