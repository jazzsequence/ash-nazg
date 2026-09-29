<?php
/**
 * API Endpoints Status Functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\API;

/**
 * Mark an endpoint as unavailable without testing.
 *
 * @param string $path        API endpoint path.
 * @param string $name        Human-readable endpoint name.
 * @param string $description Endpoint description.
 * @param string $reason      Reason for unavailability.
 * @return array Endpoint status data.
 */
function mark_endpoint_unavailable( $path, $name, $description = '', $reason = '' ) {
	return [
		'name' => $name,
		'path' => $path,
		'description' => $description,
		'status' => 'unavailable',
		'data' => null,
		'error' => $reason ?: __( 'Not available for this site', 'ash-nazg' ),
	];
}

/**
 * Test a single endpoint.
 *
 * @param string $path        API endpoint path.
 * @param string $name        Human-readable endpoint name.
 * @param string $description Endpoint description.
 * @return array Endpoint status data.
 */
function test_endpoint( $path, $name, $description = '' ) {
	$endpoint = [
		'name'        => $name,
		'path'        => $path,
		'description' => $description,
		'status'      => 'unknown',
		'data'        => null,
		'error'       => null,
	];

	$result = api_request( $path );

	if ( is_wp_error( $result ) ) {
		$error_code    = $result->get_error_code();
		$error_message = $result->get_error_message();

		// Log the specific error for debugging.
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'endpoint test [%s]: %s - %s', $path, $error_code, $error_message ) );

		if ( 'environment_not_found' === $error_code ) {
			$endpoint['status'] = 'unavailable';
			$endpoint['error']  = __( 'Not available for local environments', 'ash-nazg' );
		} else {
			$endpoint['status'] = 'error';
			$endpoint['error']  = $error_message;
		}
	} else {
		$endpoint['status'] = 'success';
		// Store count if result is an array.
		if ( is_array( $result ) ) {
			$endpoint['data'] = [ 'count' => count( $result ) ];
		}
	}

	return $endpoint;
}

/**
 * Get comprehensive API endpoints status.
 *
 * @param string $site_id Optional. Site UUID.
 * @param string $env     Optional. Environment name.
 * @param string $user_id Optional. User ID.
 * @return array Organized endpoint groups: site, user, all.
 */
function get_all_endpoints_status( $site_id = null, $env = null, $user_id = null ) {
	/*
	 * Map local environment names to their Pantheon equivalents.
	 * Local environments like 'lando' should query 'dev' environment from
	 * Pantheon.
	 */
	$api_env = map_local_env_to_dev( $env );

	$site_endpoints = [];
	$user_endpoints = [];

	// Sites & Basic Info.
	$site_endpoints['Sites'] = [];

	if ( $site_id ) {
		$site_endpoints['Sites'][] = test_endpoint(
			sprintf( '/sites/%s', $site_id ),
			__( 'Site Information', 'ash-nazg' ),
			__( 'Basic site details, settings, and addons', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = test_endpoint(
			sprintf( '/sites/%s/environments?pager[first]=50', $site_id ),
			__( 'Environments List', 'ash-nazg' ),
			__( 'All environments (dev, test, live, multidevs), including commit log, diffstat, and env variables', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = test_endpoint(
			sprintf( '/sites/%s/users', $site_id ),
			__( 'Team Members', 'ash-nazg' ),
			__( 'Site team members and their roles', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/plan', $site_id ),
			__( 'Site Plan', 'ash-nazg' ),
			__( 'Current plan and pricing information', 'ash-nazg' ),
			__( 'Merged into Site Information (settings.planName / settings.serviceLevel) in v1', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/available-plans', $site_id ),
			__( 'Available Plans', 'ash-nazg' ),
			__( 'Plans available for upgrade/downgrade', 'ash-nazg' ),
			__( 'No v1 equivalent yet', 'ash-nazg' )
		);
	}

	// Authorization & Access.
	$site_endpoints['Authorization'] = [];

	if ( $site_id ) {
		$site_endpoints['Authorization'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/authorizations', $site_id ),
			__( 'User Permissions', 'ash-nazg' ),
			__( 'Current user permissions on this site', 'ash-nazg' ),
			__( 'No v1 equivalent yet', 'ash-nazg' )
		);
	}

	// Code & Git.
	$site_endpoints['Code'] = [];

	if ( $site_id ) {
		$site_endpoints['Code'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/code-tips', $site_id ),
			__( 'Git Branches', 'ash-nazg' ),
			__( 'Available Git branches and commits', 'ash-nazg' ),
			__( 'No v1 equivalent yet', 'ash-nazg' )
		);

		$site_endpoints['Code'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/upstream-updates', $site_id ),
			__( 'Upstream Updates', 'ash-nazg' ),
			__( 'Available upstream update commits', 'ash-nazg' ),
			__( 'v1 exposes this as an async check workflow (POST upstream-updates/check), not a synchronous GET - unsuitable for this status page', 'ash-nazg' )
		);
	}

	if ( $site_id && $api_env ) {
		$site_endpoints['Code'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/commits', $site_id, $api_env ),
			__( 'Commit History', 'ash-nazg' ),
			__( 'Git commit history for environment', 'ash-nazg' ),
			__( 'Merged into Environments List (commitLog field) in v1', 'ash-nazg' )
		);

		$site_endpoints['Code'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/diffstat', $site_id, $api_env ),
			__( 'Uncommitted Changes', 'ash-nazg' ),
			__( 'Git diff for uncommitted changes (SFTP mode)', 'ash-nazg' ),
			__( 'Merged into Environments List (diffstat field) in v1', 'ash-nazg' )
		);

		$site_endpoints['Code'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/build/updates', $site_id, $api_env ),
			__( 'Composer Updates', 'ash-nazg' ),
			__( 'Composer dependencies changes', 'ash-nazg' ),
			__( 'Merged into Environments List (composerUpdates field) in v1', 'ash-nazg' )
		);
	}

	// Backups.
	$site_endpoints['Backups'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Backups'][] = test_endpoint(
			sprintf( '/sites/%s/environments/%s/backups', $site_id, $api_env ),
			__( 'Backups Catalog', 'ash-nazg' ),
			__( 'All available backups', 'ash-nazg' )
		);

		$site_endpoints['Backups'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/backups/schedule', $site_id, $api_env ),
			__( 'Backup Schedule', 'ash-nazg' ),
			__( 'Automated backup schedule configuration', 'ash-nazg' ),
			__( 'v1 only exposes PUT/DELETE for the backup schedule, no GET', 'ash-nazg' )
		);
	}

	// Domains.
	$site_endpoints['Domains'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Domains'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/domains', $site_id, $api_env ),
			__( 'Custom Domains', 'ash-nazg' ),
			__( 'Domains associated with environment', 'ash-nazg' ),
			__( 'No v1 domain-listing endpoint yet (only create/delete/set-primary/verify)', 'ash-nazg' )
		);

		$site_endpoints['Domains'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/domains/dns', $site_id, $api_env ),
			__( 'DNS Recommendations', 'ash-nazg' ),
			__( 'DNS configuration recommendations', 'ash-nazg' ),
			__( 'No v1 equivalent yet', 'ash-nazg' )
		);
	}

	// Environment Settings.
	$site_endpoints['Settings'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Settings'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/settings', $site_id, $api_env ),
			__( 'Environment Settings', 'ash-nazg' ),
			__( 'Configuration settings for environment', 'ash-nazg' ),
			__( 'Merged into Site Information (settings field) in v1', 'ash-nazg' )
		);

		$site_endpoints['Settings'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/variables', $site_id, $api_env ),
			__( 'Environment Variables', 'ash-nazg' ),
			__( 'Environment-specific variables', 'ash-nazg' ),
			__( 'Merged into Environments List (envVariables field) in v1', 'ash-nazg' )
		);
	}

	// Metrics.
	$site_endpoints['Metrics'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Metrics'][] = mark_endpoint_unavailable(
			sprintf( '/v0/sites/%s/environments/%s/metrics', $site_id, $api_env ),
			__( 'Traffic Metrics', 'ash-nazg' ),
			__( 'Pages served, visits, cache performance', 'ash-nazg' ),
			__( 'No v1 endpoint yet - falls back to legacy v0 (requires a Machine Token)', 'ash-nazg' )
		);
	}

	// Workflows.
	$site_endpoints['Workflows'] = [];

	if ( $site_id ) {
		$site_endpoints['Workflows'][] = test_endpoint(
			sprintf( '/sites/%s/workflows', $site_id ),
			__( 'Site Workflows', 'ash-nazg' ),
			__( 'All workflows for this site', 'ash-nazg' )
		);
	}

	// User Info.
	$user_endpoints['User'] = [];

	if ( $user_id ) {
		$user_endpoints['User'][] = test_endpoint(
			'/current-user?currentUser_workspacesConnection_first=50',
			__( 'User Profile', 'ash-nazg' ),
			__( 'Current user information, SSH keys, and workspace memberships', 'ash-nazg' )
		);

		$user_endpoints['User'][] = mark_endpoint_unavailable(
			sprintf( '/v0/users/%s/keys', $user_id ),
			__( 'SSH Keys', 'ash-nazg' ),
			__( 'SSH public keys', 'ash-nazg' ),
			__( 'Merged into User Profile (sshKeys field) in v1', 'ash-nazg' )
		);

		$user_endpoints['User'][] = mark_endpoint_unavailable(
			sprintf( '/v0/users/%s/machine-tokens', $user_id ),
			__( 'Machine Tokens', 'ash-nazg' ),
			__( 'Active machine tokens', 'ash-nazg' ),
			__( 'Machine tokens are a v0-only concept; v1 personal access tokens have no listing endpoint', 'ash-nazg' )
		);

		$user_endpoints['User'][] = mark_endpoint_unavailable(
			sprintf( '/v0/users/%s/memberships/sites', $user_id ),
			__( 'User Sites', 'ash-nazg' ),
			__( 'Sites where user has membership', 'ash-nazg' ),
			__( 'No direct v1 equivalent - sites are listed per-workspace via workspace sitesConnection', 'ash-nazg' )
		);

		$user_endpoints['User'][] = mark_endpoint_unavailable(
			sprintf( '/v0/users/%s/memberships/organizations', $user_id ),
			__( 'User Organizations', 'ash-nazg' ),
			__( 'Organizations where user has membership', 'ash-nazg' ),
			__( 'Merged into User Profile (workspacesConnection field) in v1', 'ash-nazg' )
		);

		$user_endpoints['User'][] = test_endpoint(
			sprintf( '/users/%s/upstreams', $user_id ),
			__( 'Available Upstreams', 'ash-nazg' ),
			__( 'Upstreams available to user', 'ash-nazg' )
		);
	}

	// Remove empty categories.
	$site_endpoints = array_filter( $site_endpoints, function ( $endpoints ) {
		return ! empty( $endpoints );
	} );

	$user_endpoints = array_filter( $user_endpoints, function ( $endpoints ) {
		return ! empty( $endpoints );
	} );

	// Merge all endpoints for "All Endpoints" tab.
	$all_endpoints = array_merge( $site_endpoints, $user_endpoints );

	return [
		'site' => $site_endpoints,
		'user' => $user_endpoints,
		'all' => $all_endpoints,
	];
}
