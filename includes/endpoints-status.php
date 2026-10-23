<?php
/**
 * API Endpoints Status Functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\API;

/**
 * Test a single endpoint.
 *
 * @param string $path        API endpoint path.
 * @param string $name        Human-readable endpoint name.
 * @param string $description Endpoint description.
 * @param bool   $use_v0      Whether to route this through the legacy v0 client. Default false.
 * @return array Endpoint status data.
 */
function test_endpoint( $path, $name, $description = '', $use_v0 = false ) {
	$endpoint = [
		'name'        => $name,
		'path'        => ( $use_v0 ? '/v0' : '/v1' ) . $path,
		'description' => $description,
		'status'      => 'unknown',
		'data'        => null,
		'error'       => null,
	];

	$result = $use_v0 ? api_request_v0( $endpoint['path'] ) : api_request( $path );

	if ( is_wp_error( $result ) ) {
		$error_code    = $result->get_error_code();
		$error_message = $result->get_error_message();

		// Log the specific error for debugging.
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'endpoint test [%s]: %s - %s', $endpoint['path'], $error_code, $error_message ) );

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
 * Test the upstream update status lookup.
 *
 * Doesn't fit test_endpoint()'s single-GET pattern since the real check reads
 * the site's status from its workspace's sitesConnection, not a direct
 * per-site endpoint.
 *
 * @param string $site_id Site UUID.
 * @return array Endpoint status data, shaped like test_endpoint()'s return.
 */
function test_upstream_status_endpoint( $site_id ) {
	$endpoint = [
		'name'        => __( 'Upstream Updates', 'ash-nazg' ),
		'path'        => '/v1/workspaces/{workspaceId}',
		'description' => __( 'Upstream update status for this site', 'ash-nazg' ),
		'status'      => 'unknown',
		'data'        => null,
		'error'       => null,
	];

	$status = get_site_upstream_status( $site_id );

	if ( is_wp_error( $status ) ) {
		$endpoint['status'] = 'error';
		$endpoint['error']  = $status->get_error_message();
	} else {
		$endpoint['status'] = 'success';
		$endpoint['data']   = [ 'status' => $status ];
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
			sprintf( '/sites/%s/environments', $site_id ),
			__( 'Environments List', 'ash-nazg' ),
			__( 'All environments (dev, test, live, multidevs), including commit log, diffstat, and env variables', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = test_endpoint(
			sprintf( '/sites/%s/users', $site_id ),
			__( 'Team Members', 'ash-nazg' ),
			__( 'Site team members and their roles', 'ash-nazg' )
		);

		$site_endpoints['Sites'][] = test_endpoint(
			sprintf( '/sites/%s/available-plans', $site_id ),
			__( 'Available Plans', 'ash-nazg' ),
			__( 'Plans available for upgrade/downgrade', 'ash-nazg' ),
			true
		);
	}

	// Authorization & Access.
	$site_endpoints['Authorization'] = [];

	if ( $site_id && $user_id ) {
		$site_endpoints['Authorization'][] = test_endpoint(
			sprintf( '/sites/%s/users/%s/memberships?first=10', $site_id, $user_id ),
			__( 'User Permissions', 'ash-nazg' ),
			__( 'Current user permissions on this site', 'ash-nazg' )
		);
	}

	// Code & Git.
	$site_endpoints['Code'] = [];

	if ( $site_id ) {
		$site_endpoints['Code'][] = test_endpoint(
			sprintf( '/sites/%s/code-tips', $site_id ),
			__( 'Git Branches', 'ash-nazg' ),
			__( 'Available Git branches and commits', 'ash-nazg' ),
			true
		);

		$site_endpoints['Code'][] = test_upstream_status_endpoint( $site_id );
	}

	// Backups.
	$site_endpoints['Backups'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Backups'][] = test_endpoint(
			sprintf( '/sites/%s/environments/%s/backups', $site_id, $api_env ),
			__( 'Backups Catalog', 'ash-nazg' ),
			__( 'All available backups', 'ash-nazg' )
		);
	}

	// Domains.
	$site_endpoints['Domains'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Domains'][] = test_endpoint(
			sprintf( '/sites/%s/environments/%s/domains', $site_id, $api_env ),
			__( 'Custom Domains', 'ash-nazg' ),
			__( 'Domains associated with environment', 'ash-nazg' ),
			true
		);

		$site_endpoints['Domains'][] = test_endpoint(
			sprintf( '/sites/%s/environments/%s/domains/dns', $site_id, $api_env ),
			__( 'DNS Recommendations', 'ash-nazg' ),
			__( 'DNS configuration recommendations', 'ash-nazg' ),
			true
		);
	}

	// Metrics.
	$site_endpoints['Metrics'] = [];

	if ( $site_id && $api_env ) {
		$site_endpoints['Metrics'][] = test_endpoint(
			sprintf( '/sites/%s/environments/%s/metrics?duration=7d', $site_id, $api_env ),
			__( 'Traffic Metrics', 'ash-nazg' ),
			__( 'Pages served, visits, cache performance', 'ash-nazg' ),
			true
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
			'/current-user',
			__( 'User Profile', 'ash-nazg' ),
			__( 'Current user information and SSH keys', 'ash-nazg' )
		);

		$user_endpoints['User'][] = test_endpoint(
			sprintf( '/users/%s/memberships/sites', $user_id ),
			__( 'User Sites', 'ash-nazg' ),
			__( 'Sites where user has membership', 'ash-nazg' ),
			true
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
