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
 * Get a short, stable ID for a registry entry.
 *
 * Derived from the version, method and path so it survives regenerating the
 * registry, unlike an array position.
 *
 * @param array $entry Registry entry: [ version, method, path, summary, group ].
 * @return string 12-character ID.
 */
function get_endpoint_entry_id( $entry ) {
	return substr( md5( $entry[0] . ' ' . $entry[1] . ' ' . $entry[2] ), 0, 12 );
}

/**
 * Find a registry entry by its ID.
 *
 * @param string $id Entry ID from get_endpoint_entry_id().
 * @return array|null The registry entry, or null if there is none with that ID.
 */
function find_endpoint_entry( $id ) {
	foreach ( get_endpoint_registry() as $entry ) {
		if ( get_endpoint_entry_id( $entry ) === $id ) {
			return $entry;
		}
	}

	return null;
}

/**
 * List the placeholders the plugin can fill in on its own.
 *
 * @return string[] Placeholder names, matching the keys of get_endpoint_probe_context().
 */
function get_resolvable_placeholders() {
	return [ 'site_id', 'env', 'user_id', 'workspace_id', 'upstream_id', 'site_name', 'multidev_name', 'start_time' ];
}

/**
 * Say, without any API call, how a registry entry will be handled.
 *
 * Lets the page render every row immediately: only entries whose state is
 * 'probe' need a request, and the rest can be shown as final straight away.
 *
 * @param array $entry Registry entry: [ version, method, path, summary, group ].
 * @return string 'write' (changes data, never probed), 'needs-id' (a read needing an ID this
 *                plugin can't supply), or 'probe' (a read that can be probed).
 */
function get_endpoint_entry_state( $entry ) {
	if ( 'GET' !== $entry[1] ) {
		return 'write';
	}

	preg_match_all( '/\{(\w+)\}/', $entry[2], $matches );

	return array_diff( $matches[1], get_resolvable_placeholders() ) ? 'needs-id' : 'probe';
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
 * Build the result for an endpoint that is listed but not probed.
 *
 * @param array  $entry   Registry entry: [ version, method, path, summary, group ].
 * @param string $path    Path to show, with the placeholders that could be filled in.
 * @param array  $missing Placeholders that couldn't be filled (reads only).
 * @return array Endpoint result with status 'skipped'.
 */
function build_skipped_endpoint_result( $entry, $path, $missing ) {
	return [
		'name' => $entry[3],
		'path' => ( 'v0' === $entry[0] ? '/v0' : '/v1' ) . $path,
		'description' => '',
		'status' => 'skipped',
		'data' => null,
		'error' => 'GET' === $entry[1]
			/* translators: %s: comma-separated list of placeholders, e.g. {build_id} */
			? sprintf( __( 'Not probed: needs %s', 'ash-nazg' ), implode( ', ', $missing ) )
			: __( 'Not probed: changes data', 'ash-nazg' ),
		'method' => $entry[1],
		'version' => $entry[0],
	];
}

/**
 * Get the row to show for an endpoint before any request has been made.
 *
 * Needs no API call, so a page can render every row immediately: writes and
 * reads needing an ID are already final, and probe-able reads show as
 * 'pending' until the loader fills them in.
 *
 * @param array $entry Registry entry: [ version, method, path, summary, group ].
 * @return array Endpoint result with an `id`.
 */
function get_endpoint_row_placeholder( $entry ) {
	$state = get_endpoint_entry_state( $entry );

	if ( 'probe' === $state ) {
		$row = [
			'name' => $entry[3],
			'path' => ( 'v0' === $entry[0] ? '/v0' : '/v1' ) . $entry[2],
			'description' => '',
			'status' => 'pending',
			'data' => null,
			'error' => null,
			'method' => $entry[1],
			'version' => $entry[0],
		];
	} else {
		preg_match_all( '/\{(\w+)\}/', $entry[2], $matches );

		$missing = [];
		foreach ( array_diff( $matches[1], get_resolvable_placeholders() ) as $name ) {
			$missing[] = '{' . $name . '}';
		}

		$row = build_skipped_endpoint_result( $entry, $entry[2], $missing );
	}

	$row['id'] = get_endpoint_entry_id( $entry );

	return $row;
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

	if ( 'GET' === $method && empty( $resolved['missing'] ) ) {
		$result = test_endpoint( $resolved['path'], $summary, '', 'v0' === $version );
		$result['method'] = $method;
		$result['version'] = $version;

		return $result;
	}

	return build_skipped_endpoint_result( $entry, $resolved['path'], $resolved['missing'] );
}
