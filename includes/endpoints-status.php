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
 * Get the generated registry of every Pantheon API endpoint.
 *
 * @return array List of [ version, method, path, summary, group ] entries.
 */
function get_endpoint_registry() {
	static $registry = null;

	if ( null === $registry ) {
		$registry = include ASH_NAZG_PLUGIN_DIR . 'includes/endpoints-registry.php';
	}

	return $registry;
}

/**
 * Get the registry's groups, in display order.
 *
 * @return string[] Group names.
 */
function get_endpoint_groups() {
	return array_values( array_unique( array_column( get_endpoint_registry(), 4 ) ) );
}

/**
 * Build the values used to fill in registry path placeholders.
 *
 * Placeholders with no value here (a build ID, a backup ID, a secret name)
 * can't be supplied automatically, so the endpoints that need them are
 * listed but not probed.
 *
 * @param string|null $site_id Site UUID.
 * @param string|null $env     Environment name.
 * @param string|null $user_id Pantheon user ID.
 * @return array<string, string> Placeholder name => value.
 */
function get_endpoint_probe_context( $site_id, $env, $user_id ) {
	$site_info = $site_id ? get_site_info( $site_id ) : null;
	$site_info = is_array( $site_info ) ? $site_info : [];

	$context = [
		'site_id' => $site_id,
		'env' => $env ? map_local_env_to_dev( $env ) : null,
		'user_id' => $user_id,
		'workspace_id' => $site_info['organization'] ?? null,
		'upstream_id' => $site_info['upstream']['id'] ?? null,
		'site_name' => $site_info['name'] ?? null,
		// Read-only validation lookup; the name never gets created.
		'multidev_name' => 'ash-nazg-probe',
		'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', time() - HOUR_IN_SECONDS ),
	];

	return array_filter(
		$context,
		function ( $value ) {
			return null !== $value && '' !== $value;
		}
	);
}

/**
 * Fill in a registry path's placeholders.
 *
 * @param string $template Registry path, e.g. /sites/{site_id}/environments/{env}/backups.
 * @param array  $context  Placeholder values from get_endpoint_probe_context().
 * @return array {path: string, missing: string[]}. Unfilled placeholders are left as-is and listed in `missing`.
 */
function resolve_endpoint_path( $template, $context ) {
	$missing = [];

	$path = preg_replace_callback(
		'/\{(\w+)\}/',
		function ( $matches ) use ( $context, &$missing ) {
			if ( isset( $context[ $matches[1] ] ) ) {
				return rawurlencode( $context[ $matches[1] ] );
			}

			$missing[] = $matches[0];

			return $matches[0];
		},
		$template
	);

	return [
		'path' => $path,
		'missing' => $missing,
	];
}

/**
 * Probe one registry entry, or explain why it is only listed.
 *
 * Only GET requests are ever sent. Writes and deletes are listed with
 * status 'skipped' because probing them would change data, as are reads that
 * need an ID this plugin can't supply on its own.
 *
 * @param array $entry   Registry entry: [ version, method, path, summary, group ].
 * @param array $context Placeholder values from get_endpoint_probe_context().
 * @return array Endpoint result shaped like test_endpoint()'s return, plus `method` and `version`.
 */
function probe_registry_entry( $entry, $context ) {
	list( $version, $method, $template, $summary ) = $entry;

	$resolved = resolve_endpoint_path( $template, $context );
	$prefix = 'v0' === $version ? '/v0' : '/v1';

	if ( 'GET' === $method && empty( $resolved['missing'] ) ) {
		$result = test_endpoint( $resolved['path'], $summary, '', 'v0' === $version );
	} else {
		$result = [
			'name' => $summary,
			'path' => $prefix . $resolved['path'],
			'description' => '',
			'status' => 'skipped',
			'data' => null,
			'error' => 'GET' === $method
				/* translators: %s: comma-separated list of placeholders, e.g. {build_id} */
				? sprintf( __( 'Not probed: needs %s', 'ash-nazg' ), implode( ', ', $resolved['missing'] ) )
				: __( 'Not probed: changes data', 'ash-nazg' ),
		];
	}

	$result['method'] = $method;
	$result['version'] = $version;

	return $result;
}

/**
 * Get the endpoint groups keyed by name.
 *
 * Compatibility shim for callers written when groups were hand-written and
 * tagged site or user; the registry has no such scope. Callers move to
 * get_endpoint_groups() in the next commit and this is then removed.
 *
 * @return array<string, string> Group name => 'api'.
 */
function get_endpoint_group_scopes() {
	return array_fill_keys( get_endpoint_groups(), 'api' );
}

/**
 * Probe every endpoint in a single registry group.
 *
 * Groups are probed one at a time so a caller can spread the live requests
 * across several page requests instead of running them all in one. Reads are
 * probed; everything else is listed as 'skipped' (see probe_registry_entry()).
 *
 * @param string      $group   Group name, one of get_endpoint_groups().
 * @param string|null $site_id Site UUID.
 * @param string|null $env     Environment name.
 * @param string|null $user_id Pantheon user ID.
 * @return array List of endpoint results shaped like test_endpoint()'s return. Empty for an unknown group.
 */
function probe_endpoint_group( $group, $site_id = null, $env = null, $user_id = null ) {
	$context = get_endpoint_probe_context( $site_id, $env, $user_id );
	$endpoints = [];

	foreach ( get_endpoint_registry() as $entry ) {
		if ( $group === $entry[4] ) {
			$endpoints[] = probe_registry_entry( $entry, $context );
		}
	}

	return $endpoints;
}
