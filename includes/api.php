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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: No machine token available' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return new \WP_Error(
			'no_token',
			__( 'No machine token configured. Please add a token in Settings.', 'ash-nazg' )
		);
	}

	// Exchange machine token for session token.
	$response = wp_remote_post(
		'https://api.pantheon.io/v0/authorize/machine-token',
		[
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode(
				[
					'machine_token' => $machine_token,
					'client'        => 'ash-nazg',
				]
			),
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: Failed to exchange machine token: ' . $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: Pantheon API is unavailable (503)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return new \WP_Error(
			'api_unavailable',
			__( 'Pantheon API is temporarily unavailable (503 Service Unavailable). This is usually a temporary issue. Please try again later.', 'ash-nazg' )
		);
	}

	// Handle 502 Bad Gateway.
	if ( 502 === $status_code ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: Pantheon API bad gateway (502)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: Bad request to API (400): ' . $body ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: Invalid machine token (status ' . $status_code . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return new \WP_Error(
			'invalid_token',
			__( 'Invalid machine token. Please check your token in Settings and ensure it is valid and not expired.', 'ash-nazg' )
		);
	}

	// Handle other non-200 responses.
	if ( 200 !== $status_code ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: API authentication failed with status ' . $status_code ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Ash-Nazg: Response body: ' . substr( $body, 0, 200 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: No session token in API response' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
function api_request( $endpoint, $method = 'GET', $body = [] ) {
	$session_token = get_api_token();
	if ( is_wp_error( $session_token ) ) {
		return $session_token;
	}

	$url  = 'https://api.pantheon.io' . $endpoint;
	$args = [
		'method'  => $method,
		'headers' => [
			'Authorization' => 'Bearer ' . $session_token,
			'Content-Type'  => 'application/json',
		],
		'timeout' => 30,
	];

	/*
	 * Add body for POST, PUT, PATCH, and DELETE.
	 * For PUT/DELETE, send empty object if no body provided (required for
	 * Content-Length header).
	 */
	if ( in_array( $method, [ 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		} elseif ( in_array( $method, [ 'PUT', 'DELETE' ], true ) ) {
			// Empty JSON object to ensure Content-Length header is set.
			$args['body'] = '{}';
		}
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: API request failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );
	$data        = json_decode( $body, true );

	if ( $status_code < 200 || $status_code >= 300 ) {
		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: API error %d: %s', $status_code, $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
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
 * Get cached API endpoint data.
 *
 * Generic helper for GET requests with caching.
 *
 * @param string $endpoint API endpoint path (e.g., '/v0/sites/{site_id}').
 * @param string $cache_key Transient cache key.
 * @param int    $ttl       Cache time-to-live in seconds. Default DAY_IN_SECONDS.
 * @return array|\WP_Error Endpoint data or WP_Error on failure.
 */
function get_cached_endpoint( $endpoint, $cache_key, $ttl = DAY_IN_SECONDS ) {
	// Check cache first.
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		// Handle both old and new cache formats.
		if ( is_array( $cached ) && isset( $cached['data'] ) ) {
			return $cached['data'];
		}
		return $cached;
	}

	// Fetch from API.
	$data = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	// Cache with timestamp.
	$cached_data = [
		'data' => $data,
		'cached_at' => time(),
	];
	set_transient( $cache_key, $cached_data, $ttl );

	return $data;
}

/**
 * Get a specific field from a cached API endpoint.
 *
 * Generic helper to retrieve individual fields from API responses.
 *
 * @param string $endpoint_type Endpoint type (e.g., 'site', 'environment', 'user').
 * @param string $field         Field name to retrieve from the response.
 * @param mixed  $default_value Default value if field doesn't exist. Default null.
 * @return mixed Field value, default value, or WP_Error on API failure.
 */
function get_api_field( $endpoint_type, $field, $default_value = null ) {
	$data = null;

	// Map endpoint type to getter function.
	switch ( $endpoint_type ) {
		case 'site':
			$data = get_site_info();
			break;

		case 'environment':
			$data = get_environment_info();
			break;

		case 'user':
			$data = get_user_info();
			break;

		default:
			return new \WP_Error(
				'invalid_endpoint_type',
				sprintf(
					/* translators: %s: endpoint type */
					__( 'Unknown endpoint type: %s', 'ash-nazg' ),
					$endpoint_type
				)
			);
	}

	// If API call failed, return the error.
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	// Return the field value or default.
	return isset( $data[ $field ] ) ? $data[ $field ] : $default_value;
}

/**
 * Get data from $_ENV if available.
 *
 * Helper function to retrieve Pantheon environment variables.
 *
 * @param string $key     Environment variable key.
 * @param mixed  $default Default value if not found.
 * @return mixed Environment variable value or default.
 */
function get_env( $key, $default = null ) {
	return isset( $_ENV[ $key ] ) ? $_ENV[ $key ] : $default;
}

/**
 * Get site information from $_ENV.
 *
 * Returns site data from environment variables when available.
 * Useful to avoid API calls for data already in $_ENV.
 *
 * @return array|null Site data from $_ENV or null if not on Pantheon.
 */
function get_site_info_from_env() {
	$site_id = get_env( 'PANTHEON_SITE' );
	$site_name = get_env( 'PANTHEON_SITE_NAME' );

	if ( ! $site_id ) {
		return null;
	}

	return [
		'id' => $site_id,
		'name' => $site_name,
		'framework' => get_env( 'FRAMEWORK', 'wordpress' ),
		'php_version' => get_env( 'php_version' ),
		'source' => 'env',
	];
}

/**
 * Get environment information from $_ENV.
 *
 * Returns environment data from environment variables when available.
 * Useful to avoid API calls for data already in $_ENV.
 *
 * @return array|null Environment data from $_ENV or null if not on Pantheon.
 */
function get_environment_info_from_env() {
	$env_name = get_env( 'PANTHEON_ENVIRONMENT' );

	if ( ! $env_name ) {
		return null;
	}

	$data = [
		'id' => $env_name,
		'environment' => $env_name,
		'domain' => get_env( 'DRUSH_OPTIONS_URI' ),
		'php_version' => get_env( 'php_version' ),
		'source' => 'env',
	];

	// Add Redis info if available.
	if ( get_env( 'CACHE_HOST' ) ) {
		$data['has_redis'] = true;
		$data['redis_host'] = get_env( 'CACHE_HOST' );
		$data['redis_port'] = get_env( 'CACHE_PORT' );
	}

	// Add Solr info if available.
	if ( get_env( 'PANTHEON_INDEX_HOST' ) ) {
		$data['has_solr'] = true;
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

	// Prefer $_ENV data if available (no API call needed).
	$env_data = get_site_info_from_env();
	if ( $env_data && $env_data['id'] === $site_id ) {
		// We have $_ENV data, but still fetch API for additional fields.
		$endpoint = '/v0/sites/' . $site_id;
		$cache_key = 'ash_nazg_site_info_' . $site_id;
		$api_data = get_cached_endpoint( $endpoint, $cache_key );

		// Merge: prefer $_ENV for fields it has, add API-only fields.
		if ( ! is_wp_error( $api_data ) ) {
			return array_merge( $api_data, $env_data );
		}

		// API failed, return $_ENV data only.
		return $env_data;
	}

	// No $_ENV data, use API only.
	$endpoint = '/v0/sites/' . $site_id;
	$cache_key = 'ash_nazg_site_info_' . $site_id;

	return get_cached_endpoint( $endpoint, $cache_key );
}

/**
 * Update site label.
 *
 * @param string $site_id Site UUID.
 * @param string $label   New site label.
 * @return array|\WP_Error Updated site data or WP_Error on failure.
 */
function update_site_label( $site_id, $label ) {
	if ( ! $site_id ) {
		return new \WP_Error(
			'missing_site_id',
			__( 'Site ID is required.', 'ash-nazg' )
		);
	}

	if ( ! $label || '' === trim( $label ) ) {
		return new \WP_Error(
			'missing_label',
			__( 'Site label cannot be empty.', 'ash-nazg' )
		);
	}

	$endpoint = sprintf( '/v0/sites/%s/label', $site_id );
	$body = [
		'label' => $label,
	];

	$result = api_request( $endpoint, 'PUT', $body );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to update site label for %s: %s', $site_id, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	// Clear site info cache to force refresh.
	delete_transient( sprintf( 'ash_nazg_site_info_%s', $site_id ) );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: Site label updated to "%s" for site %s', $label, $site_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	return $result;
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

	// Map local environments to dev for API queries.
	$api_env = in_array( $env, [ 'lando', 'local', 'localhost', 'ddev' ], true ) ? 'dev' : $env;

	// Try $_ENV data first if available.
	$env_data = get_environment_info_from_env();

	// Fetch all environments from API (there's no single-environment endpoint).
	$endpoint = sprintf( '/v0/sites/%s/environments', $site_id );
	$cache_key_all = sprintf( 'ash_nazg_all_env_info_%s', $site_id );
	$environments = get_cached_endpoint( $endpoint, $cache_key_all );

	if ( is_wp_error( $environments ) ) {
		// API failed - return $_ENV data if available.
		if ( $env_data ) {
			return $env_data;
		}
		return $environments;
	}

	// Extract the specific environment from the map.
	if ( ! isset( $environments[ $api_env ] ) ) {
		// Environment not found in API - return $_ENV data if available.
		if ( $env_data ) {
			return $env_data;
		}
		return new \WP_Error(
			'environment_not_found',
			sprintf(
				/* translators: %s: environment name */
				__( 'Environment "%s" not found on Pantheon. This may be a local development environment.', 'ash-nazg' ),
				$env
			)
		);
	}

	$api_data = $environments[ $api_env ];

	// Merge: prefer $_ENV for fields it has, add API-only fields (like on_server_development).
	if ( $env_data ) {
		return array_merge( $api_data, $env_data );
	}

	return $api_data;
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
		[
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode(
				[
					'machine_token' => $machine_token,
					'client'        => 'ash-nazg',
				]
			),
			'timeout' => 15,
		]
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
			// Handle both old and new cache formats.
			if ( is_array( $cached ) && isset( $cached['data'] ) ) {
				return $cached['data'];
			}
			return $cached;
		}
	}

	$user_id = get_user_id();

	// Load comprehensive endpoints testing.
	require_once ASH_NAZG_PLUGIN_DIR . 'includes/endpoints-status.php';

	$endpoints = get_all_endpoints_status( $site_id, $env, $user_id );

	// Cache for 24 hours with timestamp.
	$cached_data = [
		'data' => $endpoints,
		'cached_at' => time(),
	];
	set_transient( $cache_key, $cached_data, DAY_IN_SECONDS );

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
 * Returns list of known Pantheon addons with their last known state.
 * Note: The Pantheon API addon endpoints only accept PUT/DELETE, not GET.
 * We track addon state locally after each update.
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
		// Handle both old and new cache formats.
		if ( is_array( $cached ) && isset( $cached['data'] ) ) {
			return $cached['data'];
		}
		return $cached;
	}

	// Get stored addon states from environment state.
	$env_state = get_environment_state();
	$stored_states = ( $env_state && isset( $env_state['addons'] ) ) ? $env_state['addons'] : [];

	// Fallback: check old addon_states option for migration.
	if ( empty( $stored_states ) ) {
		$old_states = get_option( 'ash_nazg_addon_states', [] );
		if ( ! empty( $old_states ) ) {
			// Migrate old addon states to new environment state.
			update_environment_state( [ 'addons' => $old_states ] );
			$stored_states = $old_states;
			// Clean up old option.
			delete_option( 'ash_nazg_addon_states' );
		}
	}

	// Known Pantheon addons with their metadata.
	$known_addons = [
		'redis' => [
			'id' => 'redis',
			'name' => __( 'Redis', 'ash-nazg' ),
			'description' => __( 'Object caching with Redis for improved performance', 'ash-nazg' ),
		],
		'solr' => [
			'id' => 'solr',
			'name' => __( 'Apache Solr', 'ash-nazg' ),
			'description' => __( 'Apache Solr search service for advanced search capabilities', 'ash-nazg' ),
		],
	];

	$addons = [];

	// Build addon list with stored state information.
	foreach ( $known_addons as $addon_id => $addon_meta ) {
		$addon = $addon_meta;

		// Get stored state for this addon (defaults to false if never set).
		$addon['enabled'] = isset( $stored_states[ $addon_id ] ) ? (bool) $stored_states[ $addon_id ] : false;

		$addons[] = $addon;
	}

	// Cache for 24 hours with timestamp.
	$cached_data = [
		'data' => $addons,
		'cached_at' => time(),
	];
	set_transient( $cache_key, $cached_data, DAY_IN_SECONDS );

	return $addons;
}

/**
 * Update site addon.
 *
 * Enable or disable a specific site addon via the Pantheon API.
 * Uses PUT to enable, DELETE to disable.
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

	// Addon endpoints use PUT to enable, DELETE to disable.
	$endpoint = sprintf( '/v0/sites/%s/addons/%s', $site_id, $addon_id );
	$method = $enabled ? 'PUT' : 'DELETE';

	// Send empty body to ensure Content-Length header is set (avoid 411 errors).
	$result = api_request( $endpoint, $method, [] );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to %s addon %s for site %s: %s', $enabled ? 'enable' : 'disable', $addon_id, $site_id, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: Addon %s %s for site %s - API Response: %s', $addon_id, $enabled ? 'enabled' : 'disabled', $site_id, wp_json_encode( $result ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	// Store the new addon state in environment state.
	$env_state = get_environment_state();
	$stored_states = ( $env_state && isset( $env_state['addons'] ) ) ? $env_state['addons'] : [];
	$stored_states[ $addon_id ] = $enabled;
	update_environment_state( [ 'addons' => $stored_states ] );

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

/**
 * Get cache timestamp for a given cache key.
 *
 * @param string $cache_key The transient cache key.
 * @return int|null Timestamp when cached, or null if not cached or old format.
 */
function get_cache_timestamp( $cache_key ) {
	$cached = get_transient( $cache_key );
	if ( false === $cached ) {
		return null;
	}

	// New format includes cached_at timestamp.
	if ( is_array( $cached ) && isset( $cached['cached_at'] ) ) {
		return $cached['cached_at'];
	}

	return null;
}

/**
 * Get environment state from options.
 *
 * Returns the stored state of the current Pantheon environment including
 * connection mode, addon states, and last sync time.
 *
 * @return array|null Environment state array or null if not set.
 */
function get_environment_state() {
	$site_id = get_pantheon_site_id();
	$env = get_pantheon_environment();

	if ( ! $site_id || ! $env ) {
		return null;
	}

	// Get stored state.
	$state = get_option( 'ash_nazg_environment_state', [] );

	// Return null if state doesn't exist for this site/env.
	$state_key = "{$site_id}_{$env}";
	if ( ! isset( $state[ $state_key ] ) ) {
		return null;
	}

	return $state[ $state_key ];
}

/**
 * Update environment state in options.
 *
 * @param array $state_data State data to merge with existing state.
 * @return bool True on success, false on failure.
 */
function update_environment_state( $state_data ) {
	$site_id = get_pantheon_site_id();
	$env = get_pantheon_environment();

	if ( ! $site_id || ! $env ) {
		return false;
	}

	$state_key = "{$site_id}_{$env}";
	$all_states = get_option( 'ash_nazg_environment_state', [] );

	// Merge with existing state for this environment.
	if ( ! isset( $all_states[ $state_key ] ) ) {
		$all_states[ $state_key ] = [
			'site_id' => $site_id,
			'environment' => $env,
			'connection_mode' => null,
			'addons' => [],
			'last_synced' => null,
		];
	}

	$all_states[ $state_key ] = array_merge( $all_states[ $state_key ], $state_data );
	$all_states[ $state_key ]['last_synced'] = time();

	return update_option( 'ash_nazg_environment_state', $all_states );
}

/**
 * Sync environment state from Pantheon API.
 *
 * Queries the Pantheon API to get current connection mode and updates stored state.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return bool True on success, false on failure.
 */
function sync_environment_state( $site_id = null, $env = null ) {
	if ( null === $site_id ) {
		$site_id = get_pantheon_site_id();
	}

	if ( null === $env ) {
		$env = get_pantheon_environment();
	}

	if ( ! $site_id || ! $env ) {
		return false;
	}

	// Map local environments to dev for API queries.
	$api_env = in_array( $env, [ 'lando', 'local', 'localhost', 'ddev' ], true ) ? 'dev' : $env;

	// Get environment info from API.
	$env_info = get_environment_info( $site_id, $api_env );

	if ( is_wp_error( $env_info ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to sync environment state - %s', $env_info->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return false;
	}

	// Extract connection mode.
	$connection_mode = isset( $env_info['on_server_development'] ) && $env_info['on_server_development'] ? 'sftp' : 'git';

	// Update state.
	$state_data = [
		'connection_mode' => $connection_mode,
	];

	return update_environment_state( $state_data );
}

/**
 * Get connection mode from stored state.
 *
 * @return string|null 'git', 'sftp', or null if not known.
 */
function get_connection_mode() {
	$state = get_environment_state();

	if ( ! $state || ! isset( $state['connection_mode'] ) ) {
		return null;
	}

	return $state['connection_mode'];
}

/**
 * Update connection mode (SFTP/Git toggle).
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param string $mode Connection mode: 'sftp' or 'git'.
 * @return array|WP_Error Environment data or WP_Error on failure.
 */
function update_connection_mode( $site_id, $env, $mode ) {
	if ( ! in_array( $mode, [ 'sftp', 'git' ], true ) ) {
		return new \WP_Error(
			'invalid_mode',
			__( 'Invalid connection mode. Must be "sftp" or "git".', 'ash-nazg' )
		);
	}

	// Map local environments to dev for API queries.
	$api_env = in_array( $env, [ 'lando', 'local', 'localhost', 'ddev' ], true ) ? 'dev' : $env;

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/connection-mode', $site_id, $api_env );
	$body = [
		'mode' => $mode,
	];

	$result = api_request( $endpoint, 'PUT', $body );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to update connection mode to %s on %s/%s - Error: %s', $mode, $site_id, $env, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	/*
	 * Clear environment info cache to force refresh.
	 * Note: We do NOT update the state here. The state should only be updated
	 * after the mode change is verified, which is done by the caller.
	 */
	delete_transient( sprintf( 'ash_nazg_all_env_info_%s', $site_id ) );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: Connection mode change to %s initiated on %s/%s', $mode, $site_id, $env ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	return $result;
}

/**
 * Get available Pantheon workflows.
 *
 * Returns a list of workflows that can be triggered from the WordPress admin.
 *
 * @return array Array of workflow definitions.
 */
function get_available_workflows() {
	return [
		'install_ocp' => [
			'id' => 'install_ocp',
			'name' => __( 'Install Object Cache Pro', 'ash-nazg' ),
			'description' => __( 'Installs Object Cache Pro plugin with automated configuration.', 'ash-nazg' ),
			'workflow_type' => 'scaffold_extensions',
			'params' => [
				'job_name' => 'install_ocp',
				'with_db' => true,
			],
			'allowed_envs' => [ 'dev', 'lando' ],
		],
	];
}

/**
 * Trigger a Pantheon workflow.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param string $workflow_type Workflow type (e.g., 'scaffold_extensions').
 * @param array $params Workflow parameters.
 * @return array|WP_Error Workflow response or WP_Error on failure.
 */
function trigger_workflow( $site_id, $env, $workflow_type, $params ) {
	// Validate environment.
	$allowed_envs = [ 'dev', 'lando' ];
	if ( ! in_array( $env, $allowed_envs, true ) ) {
		return new \WP_Error(
			'invalid_environment',
			sprintf(
				/* translators: %s: environment name */
				__( 'Workflows can only be triggered on dev or local environments. Current environment: %s', 'ash-nazg' ),
				$env
			)
		);
	}

	// Map local environments to dev for API queries.
	$api_env = in_array( $env, [ 'lando', 'local', 'localhost', 'ddev' ], true ) ? 'dev' : $env;

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/workflows', $site_id, $api_env );
	$body = [
		'type' => $workflow_type,
		'params' => $params,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to trigger workflow %s on %s/%s (API env: %s) - Error: %s', $workflow_type, $site_id, $env, $api_env, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: Triggered workflow %s on %s/%s (API env: %s) - Response: %s', $workflow_type, $site_id, $env, $api_env, wp_json_encode( $result ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	return $result;
}

/**
 * Get workflow status.
 *
 * @param string $workflow_id Workflow UUID.
 * @return array|WP_Error Workflow data or WP_Error on failure.
 */
function get_workflow_status( $workflow_id ) {
	$endpoint = sprintf( '/v0/workflows/%s', $workflow_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result;
}

/**
 * Get git commit history for an environment.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return array|WP_Error Array of commit objects or WP_Error on failure.
 */
function get_environment_commits( $site_id, $env ) {
	$cache_key = sprintf( 'ash_nazg_commits_%s_%s', $site_id, $env );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/commits', $site_id, $api_env );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to get commits for %s/%s: %s', $site_id, $env, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	/* Cache for 1 hour. */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		HOUR_IN_SECONDS
	);

	return $result;
}

/**
 * Get available upstream update commits.
 *
 * @param string $site_id Site UUID.
 * @return array|WP_Error Array of upstream commit objects or WP_Error on failure.
 */
function get_upstream_updates( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_upstream_updates_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	$endpoint = sprintf( '/v0/sites/%s/code-upstream-updates', $site_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to get upstream updates for %s: %s', $site_id, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	/* Cache for 1 hour. */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		HOUR_IN_SECONDS
	);

	return $result;
}

/**
 * Get git branches and commits (code tips).
 *
 * @param string $site_id Site UUID.
 * @return array|WP_Error Array of git branch references or WP_Error on failure.
 */
function get_code_tips( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_code_tips_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	$endpoint = sprintf( '/v0/sites/%s/code-tips', $site_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: Failed to get code tips for %s: %s', $site_id, $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	/* Cache for 1 hour. */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		HOUR_IN_SECONDS
	);

	return $result;
}
