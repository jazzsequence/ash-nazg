<?php
/**
 * Pantheon API client functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\API;

/**
 * Get API session token.
 *
 * Retrieves machine token from Pantheon Secrets, exchanges it for a session token,
 * and caches the result. Auto-refreshes when expired.
 *
 * @return string|\WP_Error Session token on success, WP_Error on failure.
 */
function get_api_token() {
	// Check for cached session token.
	$cached_token = get_transient( 'ash_nazg_session_token' );
	if ( false !== $cached_token ) {
		return $cached_token;
	}

	// Get machine token from Pantheon Secrets or settings.
	$machine_token = get_machine_token();
	if ( ! $machine_token ) {
		error_log( 'Ash-Nazg: No machine token available' );
		return new \WP_Error(
			'no_token',
			__( 'No machine token configured. Please add a token in Settings.', 'ash-nazg' )
		);
	}

	// Exchange machine token for session token.
	$response = wp_remote_post(
		'https://api.pantheon.io/v0/authorize/machine-token',
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'machine_token' => $machine_token,
					'client'        => 'ash-nazg',
				)
			),
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		error_log( 'Ash-Nazg: Failed to exchange machine token: ' . $error_message );
		return new \WP_Error(
			'api_connection_failed',
			sprintf(
				/* translators: %s: error message */
				__( 'Cannot connect to Pantheon API: %s', 'ash-nazg' ),
				$error_message
			)
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );

	// Handle 503 Service Unavailable.
	if ( 503 === $status_code ) {
		error_log( 'Ash-Nazg: Pantheon API is unavailable (503)' );
		return new \WP_Error(
			'api_unavailable',
			__( 'Pantheon API is temporarily unavailable (503 Service Unavailable). This is usually a temporary issue. Please try again later.', 'ash-nazg' )
		);
	}

	// Handle 502 Bad Gateway.
	if ( 502 === $status_code ) {
		error_log( 'Ash-Nazg: Pantheon API bad gateway (502)' );
		return new \WP_Error(
			'api_unavailable',
			__( 'Pantheon API is experiencing issues (502 Bad Gateway). Please try again later.', 'ash-nazg' )
		);
	}

	// Handle Bad Request (malformed request).
	if ( 400 === $status_code ) {
		$data          = json_decode( $body, true );
		$error_details = '';
		if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			$error_messages = array_map(
				function ( $error ) {
					return isset( $error['message'] ) ? $error['message'] : '';
				},
				$data['errors']
			);
			$error_details = ' (' . implode( ', ', array_filter( $error_messages ) ) . ')';
		}
		error_log( 'Ash-Nazg: Bad request to API (400): ' . $body );
		return new \WP_Error(
			'bad_request',
			sprintf(
				/* translators: %s: error details from API */
				__( 'Invalid request to Pantheon API%s. This may be a plugin bug - please report it.', 'ash-nazg' ),
				$error_details
			)
		);
	}

	// Handle authentication errors.
	if ( 401 === $status_code || 403 === $status_code ) {
		error_log( 'Ash-Nazg: Invalid machine token (status ' . $status_code . ')' );
		return new \WP_Error(
			'invalid_token',
			__( 'Invalid machine token. Please check your token in Settings and ensure it is valid and not expired.', 'ash-nazg' )
		);
	}

	// Handle other non-200 responses.
	if ( 200 !== $status_code ) {
		error_log( 'Ash-Nazg: API authentication failed with status ' . $status_code );
		error_log( 'Ash-Nazg: Response body: ' . substr( $body, 0, 200 ) );
		return new \WP_Error(
			'api_error',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Pantheon API returned an error (HTTP %d). Please try again or check API status.', 'ash-nazg' ),
				$status_code
			)
		);
	}

	$data = json_decode( $body, true );
	if ( empty( $data['session'] ) ) {
		error_log( 'Ash-Nazg: No session token in API response' );
		return new \WP_Error(
			'invalid_response',
			__( 'Invalid response from Pantheon API. No session token received.', 'ash-nazg' )
		);
	}

	$session_token = $data['session'];

	// Cache for 1 hour (tokens are valid for longer, but we refresh regularly for security).
	set_transient( 'ash_nazg_session_token', $session_token, HOUR_IN_SECONDS );

	return $session_token;
}

/**
 * Get machine token.
 *
 * Retrieves from Pantheon Secrets if available, otherwise falls back to WordPress options.
 *
 * @return string|false Machine token or false if not found.
 */
function get_machine_token() {
	// Try Pantheon Secrets first (production).
	if ( function_exists( 'pantheon_get_secret' ) ) {
		$token = pantheon_get_secret( 'ash_nazg_machine_token' );
		if ( ! empty( $token ) ) {
			return $token;
		}
	}

	// Fallback to WordPress options (development/testing).
	$token = get_option( 'ash_nazg_machine_token' );
	return ! empty( $token ) ? $token : false;
}

/**
 * Make authenticated API request.
 *
 * @param string $endpoint API endpoint path (e.g., '/v0/sites/abc123').
 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
 * @param array  $body     Optional request body.
 * @return array|\WP_Error Response data or WP_Error on failure.
 */
function api_request( $endpoint, $method = 'GET', $body = array() ) {
	$session_token = get_api_token();
	if ( is_wp_error( $session_token ) ) {
		return $session_token;
	}

	$url  = 'https://api.pantheon.io' . $endpoint;
	$args = array(
		'method'  => $method,
		'headers' => array(
			'Authorization' => 'Bearer ' . $session_token,
			'Content-Type'  => 'application/json',
		),
		'timeout' => 30,
	);

	if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		error_log( 'Ash-Nazg: API request failed: ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );
	$data        = json_decode( $body, true );

	if ( $status_code < 200 || $status_code >= 300 ) {
		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		error_log( sprintf( 'Ash-Nazg: API error %d: %s', $status_code, $error_message ) );
		return new \WP_Error(
			'api_error',
			sprintf(
				/* translators: %1$d: HTTP status code, %2$s: error message */
				__( 'API request failed (%1$d): %2$s', 'ash-nazg' ),
				$status_code,
				$error_message
			)
		);
	}

	return $data;
}

/**
 * Get site information.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @return array|\WP_Error Site data or WP_Error on failure.
 */
function get_site_info( $site_id = null ) {
	if ( null === $site_id ) {
		$site_id = get_pantheon_site_id();
		if ( ! $site_id ) {
			return new \WP_Error(
				'no_site_id',
				__( 'Unable to detect Pantheon site ID', 'ash-nazg' )
			);
		}
	}

	// Check cache first.
	$cache_key = 'ash_nazg_site_info_' . $site_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// Fetch from API.
	$data = api_request( '/v0/sites/' . $site_id );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	// Cache for 5 minutes.
	set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

	return $data;
}

/**
 * Get environment information.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env     Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Environment data or WP_Error on failure.
 */
function get_environment_info( $site_id = null, $env = null ) {
	if ( null === $site_id ) {
		$site_id = get_pantheon_site_id();
		if ( ! $site_id ) {
			return new \WP_Error(
				'no_site_id',
				__( 'Unable to detect Pantheon site ID', 'ash-nazg' )
			);
		}
	}

	if ( null === $env ) {
		$env = get_pantheon_environment();
		if ( ! $env ) {
			return new \WP_Error(
				'no_environment',
				__( 'Unable to detect Pantheon environment', 'ash-nazg' )
			);
		}
	}

	// Check cache first.
	$cache_key = 'ash_nazg_env_info_' . $site_id . '_' . $env;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// Fetch all environments from API (there's no single-environment endpoint).
	$environments = api_request( sprintf( '/v0/sites/%s/environments', $site_id ) );

	if ( is_wp_error( $environments ) ) {
		return $environments;
	}

	// Extract the specific environment from the map.
	if ( ! isset( $environments[ $env ] ) ) {
		return new \WP_Error(
			'environment_not_found',
			sprintf(
				/* translators: %s: environment name */
				__( 'Environment "%s" not found on Pantheon. This may be a local development environment.', 'ash-nazg' ),
				$env
			)
		);
	}

	$data = $environments[ $env ];

	// Cache for 2 minutes.
	set_transient( $cache_key, $data, 2 * MINUTE_IN_SECONDS );

	return $data;
}

/**
 * Get Pantheon site ID from environment.
 *
 * @return string|false Site UUID or false if not on Pantheon.
 */
function get_pantheon_site_id() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return isset( $_ENV['PANTHEON_SITE'] ) ? sanitize_text_field( wp_unslash( $_ENV['PANTHEON_SITE'] ) ) : false;
}

/**
 * Get Pantheon environment name.
 *
 * @return string|false Environment name (dev, test, live, or multidev name) or false if not on Pantheon.
 */
function get_pantheon_environment() {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return isset( $_ENV['PANTHEON_ENVIRONMENT'] ) ? sanitize_text_field( wp_unslash( $_ENV['PANTHEON_ENVIRONMENT'] ) ) : false;
}

/**
 * Check if running on Pantheon.
 *
 * @return bool True if on Pantheon, false otherwise.
 */
function is_pantheon() {
	return false !== get_pantheon_site_id();
}

/**
 * Clear all API caches.
 *
 * @return void
 */
function clear_cache() {
	delete_transient( 'ash_nazg_session_token' );
	delete_transient( 'ash_nazg_user_id' );

	// Clear site-specific caches if we can detect site ID.
	$site_id = get_pantheon_site_id();
	if ( $site_id ) {
		delete_transient( 'ash_nazg_site_info_' . $site_id );

		$env = get_pantheon_environment();
		if ( $env ) {
			delete_transient( 'ash_nazg_env_info_' . $site_id . '_' . $env );
			delete_transient( sprintf( 'ash_nazg_endpoints_status_%s_%s', $site_id, $env ) );
		}
	}
}

/**
 * Get user ID from current session.
 *
 * @return string|false User ID or false if not available.
 */
function get_user_id() {
	$token = get_api_token();
	if ( is_wp_error( $token ) ) {
		return false;
	}

	// Try to get from cached session token response.
	$cached_user_id = get_transient( 'ash_nazg_user_id' );
	if ( false !== $cached_user_id ) {
		return $cached_user_id;
	}

	// Get from fresh auth to extract user_id.
	delete_transient( 'ash_nazg_session_token' );
	$machine_token = get_machine_token();
	if ( ! $machine_token ) {
		return false;
	}

	$response = wp_remote_post(
		'https://api.pantheon.io/v0/authorize/machine-token',
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'machine_token' => $machine_token,
					'client'        => 'ash-nazg',
				)
			),
			'timeout' => 15,
		)
	);

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['user_id'] ) ) {
			set_transient( 'ash_nazg_user_id', $body['user_id'], HOUR_IN_SECONDS );
			return $body['user_id'];
		}
	}

	return false;
}

/**
 * Get available API endpoints and their status.
 *
 * Tests all available Pantheon API endpoints and returns their status organized by category.
 * Results are cached for 10 minutes to improve performance.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env     Optional. Environment name. Auto-detected if not provided.
 * @param bool   $refresh Optional. Force refresh of cached data. Default false.
 * @return array Array of endpoint categories with their endpoints and status.
 */
function get_endpoints_status( $site_id = null, $env = null, $refresh = false ) {
	if ( null === $site_id ) {
		$site_id = get_pantheon_site_id();
	}

	if ( null === $env ) {
		$env = get_pantheon_environment();
	}

	// Create a unique cache key based on site and environment.
	$cache_key = sprintf( 'ash_nazg_endpoints_status_%s_%s', $site_id, $env );

	// Check cache first unless refresh is requested.
	if ( ! $refresh ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$user_id = get_user_id();

	// Load comprehensive endpoints testing.
	require_once ASH_NAZG_PLUGIN_DIR . 'includes/endpoints-status.php';

	$endpoints = get_all_endpoints_status( $site_id, $env, $user_id );

	// Cache for 10 minutes.
	set_transient( $cache_key, $endpoints, 10 * MINUTE_IN_SECONDS );

	return $endpoints;
}

/**
 * Test API connection.
 *
 * Attempts to authenticate and fetch site info to verify connectivity.
 *
 * @return true|\WP_Error True on success, WP_Error on failure.
 */
function test_connection() {
	// Clear any cached tokens to force fresh authentication.
	delete_transient( 'ash_nazg_session_token' );

	// Try to get site info.
	$result = get_site_info();

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return true;
}

/**
 * Get site addons.
 *
 * Fetches list of available addons and their current state from the Pantheon API.
 *
 * @param string $site_id Optional. Site UUID. If not provided, auto-detected.
 * @return array|\WP_Error Array of addon objects on success, WP_Error on failure.
 */
function get_site_addons( $site_id = null ) {
	if ( ! $site_id ) {
		$site_id = get_pantheon_site_id();
	}

	if ( ! $site_id ) {
		return new \WP_Error(
			'missing_site_id',
			__( 'Site ID could not be determined.', 'ash-nazg' )
		);
	}

	// Check cache first.
	$cache_key = sprintf( 'ash_nazg_site_addons_%s', $site_id );
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// Fetch from API.
	$endpoint = sprintf( '/v0/sites/%s/addons', $site_id );
	$addons = api_request( $endpoint );

	if ( is_wp_error( $addons ) ) {
		error_log( sprintf( 'Ash-Nazg: Failed to fetch site addons for %s: %s', $site_id, $addons->get_error_message() ) );
		return $addons;
	}

	// Cache for 5 minutes.
	set_transient( $cache_key, $addons, 5 * MINUTE_IN_SECONDS );

	return $addons;
}

/**
 * Update site addon.
 *
 * Enable or disable a specific site addon via the Pantheon API.
 *
 * @param string $site_id Site UUID.
 * @param string $addon_id Addon identifier (e.g., 'redis', 'solr').
 * @param bool   $enabled Whether to enable or disable the addon.
 * @return true|\WP_Error True on success, WP_Error on failure.
 */
function update_site_addon( $site_id, $addon_id, $enabled ) {
	if ( ! $site_id ) {
		return new \WP_Error(
			'missing_site_id',
			__( 'Site ID is required.', 'ash-nazg' )
		);
	}

	if ( ! $addon_id ) {
		return new \WP_Error(
			'missing_addon_id',
			__( 'Addon ID is required.', 'ash-nazg' )
		);
	}

	// Make API request to update addon.
	$endpoint = sprintf( '/v0/sites/%s/addons/%s', $site_id, $addon_id );
	$body = array( 'enabled' => (bool) $enabled );

	$result = api_request( $endpoint, 'PUT', $body );

	if ( is_wp_error( $result ) ) {
		error_log( sprintf( 'Ash-Nazg: Failed to update addon %s for site %s: %s', $addon_id, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Log the addon state change.
	error_log( sprintf( 'Ash-Nazg: Addon %s %s for site %s', $addon_id, $enabled ? 'enabled' : 'disabled', $site_id ) );

	// Clear addon cache.
	clear_addons_cache( $site_id );

	// Clear endpoint status cache to reflect the updated addon state.
	$env = get_pantheon_environment();
	clear_addon_endpoint_cache( $site_id, $env );

	return true;
}

/**
 * Clear site addons cache.
 *
 * @param string $site_id Site UUID.
 * @return void
 */
function clear_addons_cache( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_site_addons_%s', $site_id );
	delete_transient( $cache_key );
}

/**
 * Clear addon endpoint cache.
 *
 * Clears the endpoint status cache that includes the addons endpoint,
 * ensuring the dashboard reflects current addon state.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return void
 */
function clear_addon_endpoint_cache( $site_id, $env ) {
	$cache_key = sprintf( 'ash_nazg_endpoints_status_%s_%s', $site_id, $env );
	delete_transient( $cache_key );
}
