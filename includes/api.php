<?php
/**
 * Pantheon API client functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\API;

use Pantheon\AshNazg\Helpers;

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
		\Pantheon\AshNazg\Helpers\debug_log( 'No machine token available' );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'Failed to exchange machine token: ' . $error_message );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'Pantheon API is unavailable (503)' );
		return new \WP_Error(
			'api_unavailable',
			__( 'Pantheon API is temporarily unavailable (503 Service Unavailable). This is usually a temporary issue. Please try again later.', 'ash-nazg' )
		);
	}

	// Handle 502 Bad Gateway.
	if ( 502 === $status_code ) {
		\Pantheon\AshNazg\Helpers\debug_log( 'Pantheon API bad gateway (502)' );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'Bad request to API (400): ' . $body );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'Invalid machine token (status ' . $status_code . ')' );
		return new \WP_Error(
			'invalid_token',
			__( 'Invalid machine token. Please check your token in Settings and ensure it is valid and not expired.', 'ash-nazg' )
		);
	}

	// Handle other non-200 responses.
	if ( 200 !== $status_code ) {
		\Pantheon\AshNazg\Helpers\debug_log( 'API authentication failed with status ' . $status_code );
		\Pantheon\AshNazg\Helpers\debug_log( 'Response body: ' . substr( $body, 0, 200 ) );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'No session token in API response' );
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
		\Pantheon\AshNazg\Helpers\debug_log( 'API request failed: ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );
	$data        = json_decode( $body, true );

	// Handle authentication errors by clearing cached session token.
	if ( 401 === $status_code || 403 === $status_code ) {
		delete_transient( 'ash_nazg_session_token' );
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Authentication failed (%d), cleared cached session token', $status_code ) );
		return new \WP_Error(
			'authentication_failed',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Authentication failed (%d). Session token has been cleared. Please try again.', 'ash-nazg' ),
				$status_code
			)
		);
	}

	if ( $status_code < 200 || $status_code >= 300 ) {
		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'API error %d: %s', $status_code, $error_message ) );
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Full API response body: %s', $body ) );
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
 * @param mixed  $default_value Default value if not found.
 * @return mixed Environment variable value or default.
 */
function get_env( $key, $default_value = null ) {
	return isset( $_ENV[ $key ] ) ? sanitize_text_field( $_ENV[ $key ] ) : $default_value;
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
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
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
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to update site label for %s: %s', $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear site info cache to force refresh.
	delete_transient( sprintf( 'ash_nazg_site_info_%s', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Site label updated to "%s" for site %s', $label, $site_id ) );

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
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

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
 * Map local environment names to dev for API queries.
 *
 * Local environment names (lando, local, localhost, ddev) should map to 'dev'
 * when making API requests since the API doesn't have endpoints for local envs.
 *
 * @param string $env Environment name.
 * @return string Mapped environment name ('dev' for local envs, original otherwise).
 */
function map_local_env_to_dev( $env ) {
	$local_env_names = [ 'lando', 'local', 'localhost', 'ddev' ];
	return in_array( strtolower( $env ), $local_env_names, true ) ? 'dev' : $env;
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
		delete_transient( 'ash_nazg_upstream_updates_' . $site_id );

		$env = get_pantheon_environment();
		if ( $env ) {
			delete_transient( 'ash_nazg_env_info_' . $site_id . '_' . $env );
			delete_transient( sprintf( 'ash_nazg_endpoints_status_%s_%s', $site_id, $env ) );
			delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $env ) );
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
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
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
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to %s addon %s for site %s: %s', $enabled ? 'enable' : 'disable', $addon_id, $site_id, $result->get_error_message() ) );
		return $result;
	}

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Addon %s %s for site %s - API Response: %s', $addon_id, $enabled ? 'enabled' : 'disabled', $site_id, wp_json_encode( $result ) ) );

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
	$api_env = map_local_env_to_dev( $env );

	// Get environment info from API.
	$env_info = get_environment_info( $site_id, $api_env );

	if ( is_wp_error( $env_info ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to sync environment state - %s', $env_info->get_error_message() ) );
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
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/connection-mode', $site_id, $api_env );
	$body = [
		'mode' => $mode,
	];

	$result = api_request( $endpoint, 'PUT', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to update connection mode to %s on %s/%s - Error: %s', $mode, $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/*
	 * Clear environment info cache to force refresh.
	 * Note: We do NOT update the state here. The state should only be updated
	 * after the mode change is verified, which is done by the caller.
	 */
	delete_transient( sprintf( 'ash_nazg_all_env_info_%s', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Connection mode change to %s initiated on %s/%s', $mode, $site_id, $env ) );

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
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/workflows', $site_id, $api_env );
	$body = [
		'type' => $workflow_type,
		'params' => $params,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to trigger workflow %s on %s/%s (API env: %s) - Error: %s', $workflow_type, $site_id, $env, $api_env, $result->get_error_message() ) );
		return $result;
	}

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Triggered workflow %s on %s/%s (API env: %s) - Response: %s', $workflow_type, $site_id, $env, $api_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Get workflow status.
 *
 * @param string $site_id Site UUID.
 * @param string $workflow_id Workflow UUID.
 * @return array|WP_Error Workflow data or WP_Error on failure.
 */
function get_workflow_status( $site_id, $workflow_id ) {
	$endpoint = sprintf( '/v0/sites/%s/workflows/%s', $site_id, $workflow_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get workflow status %s on %s - Error: %s', $workflow_id, $site_id, $result->get_error_message() ) );
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
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get commits for %s/%s: %s', $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/* Cache for 5 minutes to keep commit log fresh. */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		5 * MINUTE_IN_SECONDS
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

	$endpoint = sprintf( '/v0/sites/%s/upstream-updates', $site_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get upstream updates for %s: %s', $site_id, $result->get_error_message() ) );
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
 * Apply upstream updates to an environment.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param bool   $updatedb Whether to run update.php after applying (Drupal).
 * @param bool   $xoption Whether to accept updates with merge conflicts (force theirs).
 * @return array|WP_Error Workflow response on success, WP_Error on failure.
 */
function apply_upstream_updates( $site_id, $env, $updatedb = false, $xoption = false ) {
	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/upstream/updates', $site_id, $api_env );

	$body = [
		'updatedb' => (bool) $updatedb,
		'xoption' => (bool) $xoption,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to apply upstream updates on %s.%s - Error: %s', $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear upstream updates cache after applying. */
	delete_transient( sprintf( 'ash_nazg_upstream_updates_%s', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Applied upstream updates on %s.%s - Response: %s', $site_id, $env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Deploy code to an environment.
 *
 * @param string $site_id Site UUID.
 * @param string $target_env Target environment (test or live).
 * @param string $note Optional deployment note.
 * @param bool   $clear_cache Whether to clear cache after deployment.
 * @param bool   $sync_content Whether to sync content from live (test only).
 * @param bool   $updatedb Whether to run database updates after deployment.
 * @return array|WP_Error Workflow response or WP_Error on failure.
 */
function deploy_code( $site_id, $target_env, $note = '', $clear_cache = true, $sync_content = false, $updatedb = false ) {
	/* Validate target environment. */
	if ( ! in_array( $target_env, [ 'test', 'live' ], true ) ) {
		return new \WP_Error(
			'invalid_environment',
			__( 'Can only deploy to test or live environments.', 'ash-nazg' )
		);
	}

	/* Determine source environment. */
	$source_env = ( 'test' === $target_env ) ? 'dev' : 'test';

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/deploy', $site_id, $target_env );

	$body = [
		'clear_cache' => $clear_cache,
		'updatedb' => $updatedb,
	];

	if ( ! empty( $note ) ) {
		$body['note'] = $note;
	}

	/* sync_content only applies to test → live. */
	if ( 'live' === $target_env && $sync_content ) {
		$body['sync_content'] = true;
	}

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to deploy code to %s.%s - Error: %s', $site_id, $target_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear commits cache for both source and target environments. */
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $source_env ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $target_env ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Deployed code to %s.%s - Response: %s', $site_id, $target_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Clone database from one environment to another.
 *
 * @param string $site_id Site UUID.
 * @param string $from_env Source environment to clone from.
 * @param string $to_env Target environment to clone to.
 * @param string $from_url Source URL for WordPress search-replace (optional).
 * @param string $to_url Target URL for WordPress search-replace (optional).
 * @param bool $clear_cache Whether to clear cache after cloning.
 * @param bool $updatedb Whether to run database updates after cloning.
 * @return array|WP_Error Workflow data on success, WP_Error on failure.
 */
function clone_database( $site_id, $from_env, $to_env, $from_url = '', $to_url = '', $clear_cache = true, $updatedb = false ) {
	/* Validate environments. */
	if ( empty( $from_env ) || empty( $to_env ) ) {
		return new \WP_Error(
			'invalid_environment',
			__( 'Source and target environments are required.', 'ash-nazg' )
		);
	}

	if ( $from_env === $to_env ) {
		return new \WP_Error(
			'same_environment',
			__( 'Source and target environments must be different.', 'ash-nazg' )
		);
	}

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/database/clone', $site_id, $to_env );

	$body = [
		'from_environment' => $from_env,
		'clear_cache' => $clear_cache,
		'updatedb' => $updatedb,
	];

	/* Add URLs for WordPress search-replace if provided. */
	if ( ! empty( $from_url ) && ! empty( $to_url ) ) {
		$body['from_url'] = $from_url;
		$body['to_url'] = $to_url;
	}

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to clone database from %s.%s to %s.%s - Error: %s', $site_id, $from_env, $site_id, $to_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear commits cache for both environments. */
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $from_env ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $to_env ) );

	/* Clear environment state cache. */
	delete_transient( sprintf( 'ash_nazg_env_state_%s_%s', $site_id, $to_env ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Cloned database from %s.%s to %s.%s - Response: %s', $site_id, $from_env, $site_id, $to_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Clone files from one environment to another.
 *
 * @param string $site_id Site UUID.
 * @param string $from_env Source environment to clone from.
 * @param string $to_env Target environment to clone to.
 * @return array|WP_Error Workflow data on success, WP_Error on failure.
 */
function clone_files( $site_id, $from_env, $to_env ) {
	/* Validate environments. */
	if ( empty( $from_env ) || empty( $to_env ) ) {
		return new \WP_Error(
			'invalid_environment',
			__( 'Source and target environments are required.', 'ash-nazg' )
		);
	}

	if ( $from_env === $to_env ) {
		return new \WP_Error(
			'same_environment',
			__( 'Source and target environments must be different.', 'ash-nazg' )
		);
	}

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/files/clone', $site_id, $to_env );

	$body = [
		'from_environment' => $from_env,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to clone files from %s.%s to %s.%s - Error: %s', $site_id, $from_env, $site_id, $to_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear environment state cache. */
	delete_transient( sprintf( 'ash_nazg_env_state_%s_%s', $site_id, $to_env ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Cloned files from %s.%s to %s.%s - Response: %s', $site_id, $from_env, $site_id, $to_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Delete the Pantheon site.
 *
 * WARNING: This is PERMANENT and IRREVERSIBLE. All data will be lost.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @return array|WP_Error API response or WP_Error on failure.
 */
function delete_site( $site_id = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	Helpers\debug_log( sprintf( 'CRITICAL: Site deletion initiated for %s', $site_id ) );

	$endpoint = sprintf( '/v0/sites/%s', $site_id );
	$result = api_request( $endpoint, 'DELETE' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Site deletion failed for %s - Error: %s', $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear all caches.
	delete_transient( 'ash_nazg_site_info_' . $site_id );
	delete_transient( 'ash_nazg_all_env_info_' . $site_id );
	delete_transient( 'ash_nazg_backups_' . $site_id );
	delete_transient( 'ash_nazg_code_tips_' . $site_id );

	Helpers\debug_log( sprintf( 'Site %s successfully deleted', $site_id ) );

	return $result;
}

/**
 * Get domains for an environment.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|WP_Error Array of domains or WP_Error on failure.
 */
function get_domains( $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/domains', $site_id, $api_env );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to get domains for %s/%s: %s', $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	return $result;
}

/**
 * Add a domain to an environment.
 *
 * @param string $domain Domain name to add (e.g., 'example.com').
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Defaults to 'live'.
 * @return array|WP_Error API response or WP_Error on failure.
 */
function add_domain( $domain, $site_id = null, $env = 'live' ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	if ( empty( $domain ) ) {
		return new \WP_Error( 'invalid_domain', __( 'Domain name is required.', 'ash-nazg' ) );
	}

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/domains', $site_id, $env );
	$body = [ 'domain' => $domain ];

	Helpers\debug_log( sprintf( 'Adding domain %s to %s/%s', $domain, $site_id, $env ) );

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to add domain %s to %s/%s - Error: %s', $domain, $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	Helpers\debug_log( sprintf( 'Domain %s successfully added to %s/%s', $domain, $site_id, $env ) );

	return $result;
}

/**
 * Delete a domain from an environment.
 *
 * @param string $domain Domain name to delete.
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Defaults to 'live'.
 * @return array|WP_Error API response or WP_Error on failure.
 */
function delete_domain( $domain, $site_id = null, $env = 'live' ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	if ( empty( $domain ) ) {
		return new \WP_Error( 'invalid_domain', __( 'Domain name is required.', 'ash-nazg' ) );
	}

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/domains/%s', $site_id, $env, $domain );

	Helpers\debug_log( sprintf( 'Deleting domain %s from %s/%s', $domain, $site_id, $env ) );

	$result = api_request( $endpoint, 'DELETE' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to delete domain %s from %s/%s - Error: %s', $domain, $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	Helpers\debug_log( sprintf( 'Domain %s successfully deleted from %s/%s', $domain, $site_id, $env ) );

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
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get code tips for %s: %s', $site_id, $result->get_error_message() ) );
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
 * Get diffstat for uncommitted SFTP changes.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return array|WP_Error Diffstat data or WP_Error on failure.
 */
function get_diffstat( $site_id, $env ) {
	$cache_key = sprintf( 'ash_nazg_diffstat_%s_%s', $site_id, $env );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/diffstat', $site_id, $api_env );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get diffstat for %s/%s: %s', $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/* Cache for 1 minute (changes frequently in SFTP mode). */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		MINUTE_IN_SECONDS
	);

	return $result;
}

/**
 * Commit SFTP changes.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param string $message Commit message.
 * @return array|WP_Error Commit response or WP_Error on failure.
 */
function commit_sftp_changes( $site_id, $env, $message ) {
	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/code/commit', $site_id, $api_env );
	$body = [
		'message' => $message,
		'committer_name' => wp_get_current_user()->display_name,
		'committer_email' => wp_get_current_user()->user_email,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to commit on %s/%s (API env: %s) - Error: %s', $site_id, $env, $api_env, $result->get_error_message() ) );
		return $result;
	}

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Committed changes on %s/%s (API env: %s) - Response: %s', $site_id, $env, $api_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Get all environments for a site.
 *
 * @param string $site_id Site UUID.
 * @return array|WP_Error Environments data or WP_Error on failure.
 */
function get_environments( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_environments_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	$endpoint = sprintf( '/v0/sites/%s/environments', $site_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get environments for %s: %s', $site_id, $result->get_error_message() ) );
		return $result;
	}

	/* Cache for 5 minutes. */
	set_transient(
		$cache_key,
		[
			'data' => $result,
			'cached_at' => time(),
		],
		5 * MINUTE_IN_SECONDS
	);

	return $result;
}

/**
 * Create a new multidev environment.
 *
 * @param string $site_id Site UUID.
 * @param string $env_name New environment name.
 * @param string $source_env Source environment to clone from (default: dev).
 * @return array|WP_Error Environment creation response or WP_Error on failure.
 */
function create_multidev( $site_id, $env_name, $source_env = 'dev' ) {
	$endpoint = sprintf( '/v0/sites/%s/environments', $site_id );
	$body = [
		'environment_name' => $env_name,
		'from_environment' => $source_env,
		'clone_database' => true,
		'clone_files' => true,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to create multidev %s on %s - Error: %s', $env_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear environments cache after creating new environment.
	delete_transient( sprintf( 'ash_nazg_environments_%s', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Created multidev %s on %s - Response: %s', $env_name, $site_id, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Merge a multidev environment into dev.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to merge.
 * @return array|WP_Error Merge response or WP_Error on failure.
 */
function merge_multidev_to_dev( $site_id, $multidev_name ) {
	$endpoint = sprintf( '/v0/sites/%s/environments/dev/merge', $site_id );
	$body = [
		'source_environment' => $multidev_name,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to merge multidev %s to dev on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear commits cache for dev environment.
	delete_transient( sprintf( 'ash_nazg_env_commits_%s_dev', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Merged multidev %s to dev on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Merge dev environment into a multidev environment.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to merge dev into.
 * @param bool   $updatedb Whether to run update.php after merging (Drupal).
 * @return array|WP_Error Workflow response or WP_Error on failure.
 */
function merge_dev_to_multidev( $site_id, $multidev_name, $updatedb = false ) {
	$endpoint = sprintf( '/v0/sites/%s/environments/%s/merge-from-dev', $site_id, $multidev_name );
	$body = [
		'updatedb' => (bool) $updatedb,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to merge dev to multidev %s on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear commits cache for both dev and multidev (they're now in sync).
	delete_transient( sprintf( 'ash_nazg_commits_%s_dev', $site_id ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $multidev_name ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Merged dev to multidev %s on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Delete a multidev environment.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to delete.
 * @return array|WP_Error Deletion response or WP_Error on failure.
 */
function delete_multidev( $site_id, $multidev_name ) {
	// Prevent deletion of standard environments.
	if ( in_array( $multidev_name, [ 'dev', 'test', 'live' ], true ) ) {
		return new \WP_Error(
			'invalid_environment',
			__( 'Cannot delete standard environments (dev, test, live).', 'ash-nazg' )
		);
	}

	$endpoint = sprintf( '/v0/sites/%s/environments/%s', $site_id, $multidev_name );

	$result = api_request( $endpoint, 'DELETE' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to delete multidev %s on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear environments cache after deleting environment.
	delete_transient( sprintf( 'ash_nazg_environments_%s', $site_id ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Deleted multidev %s on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Poll workflow until completion.
 *
 * @param string $site_id Site UUID.
 * @param string $workflow_id Workflow ID.
 * @param int    $max_attempts Maximum number of polling attempts (default: 60).
 * @param int    $sleep_seconds Seconds to wait between polls (default: 2).
 * @return array|WP_Error Final workflow status or WP_Error on failure/timeout.
 */
function poll_workflow( $site_id, $workflow_id, $max_attempts = 60, $sleep_seconds = 2 ) {
	$attempts = 0;

	while ( $attempts < $max_attempts ) {
		$status = get_workflow_status( $site_id, $workflow_id );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		// Check if workflow is complete.
		if ( isset( $status['result'] ) && in_array( $status['result'], [ 'succeeded', 'failed' ], true ) ) {
			return $status;
		}

		// Wait before next poll.
		sleep( $sleep_seconds );
		++$attempts;
	}

	// Timeout reached.
	return new \WP_Error(
		'workflow_timeout',
		sprintf(
			/* translators: %d: number of seconds */
			__( 'Workflow did not complete within %d seconds.', 'ash-nazg' ),
			$max_attempts * $sleep_seconds
		)
	);
}

/**
 * Get backups catalog for an environment.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Backups catalog (map of backup_id => Backup) or WP_Error on failure.
 */
function get_backups( $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/backups/catalog', $site_id, $api_env );
	$cache_key = sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env );

	// Cache for 5 minutes (backups don't change frequently).
	$backups = get_cached_endpoint( $endpoint, $cache_key, 5 * MINUTE_IN_SECONDS );

	if ( is_wp_error( $backups ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get backups for %s.%s - Error: %s', $site_id, $api_env, $backups->get_error_message() ) );
		return $backups;
	}

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Retrieved %d backups for %s.%s', count( $backups ), $site_id, $api_env ) );

	return $backups;
}

/**
 * Create a new backup.
 *
 * @param string $element Backup element type: 'all', 'code', 'database', or 'files'.
 * @param int    $keep_for Number of days to keep the backup (default: 365).
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Workflow response or WP_Error on failure.
 */
function create_backup( $element, $keep_for = 365, $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	// Validate element type.
	$valid_elements = [ 'all', 'code', 'database', 'files' ];
	if ( ! in_array( $element, $valid_elements, true ) ) {
		return new \WP_Error(
			'invalid_element',
			sprintf(
				/* translators: %s: comma-separated list of valid element types */
				__( 'Invalid backup element. Must be one of: %s', 'ash-nazg' ),
				implode( ', ', $valid_elements )
			)
		);
	}

	// Validate keep_for (minimum 1 day).
	$keep_for = max( 1, (int) $keep_for );

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/backups', $site_id, $api_env );
	$body = [
		'element' => $element,
		'keep_for' => $keep_for,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to create %s backup for %s.%s - Error: %s', $element, $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	// Clear backups cache after creating backup.
	delete_transient( sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Created %s backup for %s.%s - Workflow ID: %s', $element, $site_id, $api_env, $result['id'] ?? 'unknown' ) );

	return $result;
}

/**
 * Restore a backup.
 *
 * @param string $backup_id Backup ID (folder name from catalog).
 * @param string $element Element to restore: 'code', 'database', or 'files'.
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Workflow response or WP_Error on failure.
 */
function restore_backup( $backup_id, $element, $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	// Validate element type (note: 'all' is not valid for restore).
	$valid_elements = [ 'code', 'database', 'files' ];
	if ( ! in_array( $element, $valid_elements, true ) ) {
		return new \WP_Error(
			'invalid_element',
			sprintf(
				/* translators: %s: comma-separated list of valid element types */
				__( 'Invalid restore element. Must be one of: %s', 'ash-nazg' ),
				implode( ', ', $valid_elements )
			)
		);
	}

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/backups/%s/restore', $site_id, $api_env, $backup_id );
	$body = [
		'element' => $element,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to restore %s from backup %s on %s.%s - Error: %s', $element, $backup_id, $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	// Clear backups cache after restore.
	delete_transient( sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env ) );

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Restored %s from backup %s on %s.%s - Workflow ID: %s', $element, $backup_id, $site_id, $api_env, $result['workflow_id'] ?? 'unknown' ) );

	return $result;
}

/**
 * Get a signed download URL for a backup file.
 *
 * @param string $backup_id Backup ID (folder name from catalog).
 * @param string $element Element to download: 'code', 'database', or 'files'.
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return string|\WP_Error Download URL or WP_Error on failure.
 */
function get_backup_download_url( $backup_id, $element, $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return $env;
	}

	// Validate element type.
	$valid_elements = [ 'code', 'database', 'files' ];
	if ( ! in_array( $element, $valid_elements, true ) ) {
		return new \WP_Error(
			'invalid_element',
			sprintf(
				/* translators: %s: comma-separated list of valid element types */
				__( 'Invalid download element. Must be one of: %s', 'ash-nazg' ),
				implode( ', ', $valid_elements )
			)
		);
	}

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/backups/%s/%s/download-url', $site_id, $api_env, $backup_id, $element );

	$result = api_request( $endpoint, 'POST' );

	if ( is_wp_error( $result ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Failed to get download URL for %s backup %s on %s.%s - Error: %s', $element, $backup_id, $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	if ( ! isset( $result['url'] ) ) {
		\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Download URL response missing url field for %s backup %s on %s.%s', $element, $backup_id, $site_id, $api_env ) );
		return new \WP_Error(
			'missing_url',
			__( 'API response did not include download URL', 'ash-nazg' )
		);
	}

	\Pantheon\AshNazg\Helpers\debug_log( sprintf( 'Retrieved download URL for %s backup %s on %s.%s', $element, $backup_id, $site_id, $api_env ) );

	return $result['url'];
}
