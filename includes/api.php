<?php
/**
 * Pantheon API client functions.
 *
 * Talks to the Pantheon Public API v1 (https://api.pantheon.io/v1) using a
 * personal access token sent directly as a Bearer token - no token exchange,
 * no session-token caching (unlike the legacy v0 API this replaces).
 *
 * A small number of features have no v1 endpoint yet (site label update,
 * metrics, domain listing, code tips, generic workflow triggering, and
 * backup restore - v1's restore endpoint exists but is non-functional).
 * Those fall back to the legacy v0 API via api_request_v0(), authenticated
 * with the same Personal Access Token - it works as a v0 machine_token for
 * the full v0 API surface. See get_v0_session_token().
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\API;

use Pantheon\AshNazg\Helpers;

/**
 * Get the v1 API token (personal access token) for a user.
 *
 * V1 has no token-exchange step - the stored token is used directly as the
 * Bearer token on every request, so this is just a lookup.
 *
 * @param int|null $user_id Optional. User ID. Defaults to current user.
 * @return string|\WP_Error Personal access token on success, WP_Error on failure.
 */
function get_api_token( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		Helpers\debug_log( 'Unable to get API token - no user ID' );
		return new \WP_Error(
			'no_user',
			__( 'Unable to authenticate - no user logged in.', 'ash-nazg' )
		);
	}

	$token = get_user_access_token( $user_id );
	if ( ! $token ) {
		Helpers\debug_log( sprintf( 'No personal access token found for user %d', $user_id ) );
		return new \WP_Error(
			'no_token',
			__( 'No Personal Access Token configured. Please add one in Settings.', 'ash-nazg' )
		);
	}

	return $token;
}

/**
 * Encrypt a token before storing in database.
 *
 * Uses WordPress salts for encryption key and AES-256-CBC encryption.
 * Tokens stored in Pantheon Secrets are not encrypted (already secure).
 * Used for both the v1 personal access token and the legacy v0 machine token.
 *
 * @param string $token Plain text token.
 * @return string Encrypted token (base64 encoded).
 */
function encrypt_token( $token ) {
	// Use WordPress AUTH_SALT for encryption key.
	$key = wp_salt( 'auth' );

	// Generate initialization vector from key (16 bytes for AES-256-CBC).
	$iv = substr( md5( $key ), 0, 16 );

	// Encrypt token.
	$encrypted = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );

	// Return base64 encoded encrypted string.
	return base64_encode( $encrypted );
}

/**
 * Decrypt a stored token from database.
 *
 * @param string $encrypted_token Encrypted token (base64 encoded).
 * @return string|false Plain text token on success, false on failure.
 */
function decrypt_token( $encrypted_token ) {
	if ( empty( $encrypted_token ) ) {
		return false;
	}

	// Use same WordPress AUTH_SALT as encryption.
	$key = wp_salt( 'auth' );

	// Generate same initialization vector.
	$iv = substr( md5( $key ), 0, 16 );

	// Decode base64 and decrypt.
	$decrypted = openssl_decrypt( base64_decode( $encrypted_token ), 'AES-256-CBC', $key, 0, $iv );

	return $decrypted !== false ? $decrypted : false;
}

/**
 * Get the v1 personal access token for a specific user.
 *
 * Checks in this order:
 * 1. Pantheon Secret with user ID suffix (pantheon_get_secret)
 * 2. User meta (encrypted, stored in database)
 * 3. Global Pantheon Secret (backward compatibility)
 * 4. Global option (backward compatibility)
 *
 * @param int|null $user_id Optional. User ID. Defaults to current user.
 * @return string|false Personal access token on success, false on failure.
 */
function get_user_access_token( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		Helpers\debug_log( 'Cannot get user access token - no user ID' );
		return false;
	}

	// Try per-user Pantheon Secret first.
	if ( function_exists( 'pantheon_get_secret' ) ) {
		$secret_key = sprintf( 'ash_nazg_access_token_%d', $user_id );
		$token = pantheon_get_secret( $secret_key );
		if ( ! empty( $token ) ) {
			Helpers\debug_log( sprintf( 'Retrieved access token from Pantheon Secret for user %d', $user_id ) );
			return $token;
		}
	}

	// Try per-user meta (encrypted).
	$encrypted_token = get_user_meta( $user_id, 'ash_nazg_user_access_token', true );
	if ( ! empty( $encrypted_token ) ) {
		$token = decrypt_token( $encrypted_token );
		if ( $token ) {
			Helpers\debug_log( sprintf( 'Retrieved access token from user meta for user %d', $user_id ) );
			return $token;
		}
	}

	// Fallback to global token (backward compatibility).
	Helpers\debug_log( sprintf( 'No per-user access token found for user %d, falling back to global token', $user_id ) );
	return get_access_token();
}

/**
 * Get the global v1 personal access token.
 *
 * Retrieves from Pantheon Secrets if available, otherwise falls back to WordPress options.
 *
 * @return string|false Personal access token or false if not found.
 */
function get_access_token() {
	// Try Pantheon Secrets first (production).
	if ( function_exists( 'pantheon_get_secret' ) ) {
		$token = pantheon_get_secret( 'ash_nazg_access_token' );
		if ( ! empty( $token ) ) {
			return $token;
		}
	}

	// Fallback to WordPress options (development/testing).
	$token = get_option( 'ash_nazg_access_token' );
	return ! empty( $token ) ? $token : false;
}

/**
 * Exchange a token for a v0 session token.
 *
 * V0-only. Cached per-user for 1 hour, same as the old exchange this
 * replaces. Only called by api_request_v0(), which is only used by the
 * handful of hybrid-fallback functions that have no v1 equivalent yet.
 * The Personal Access Token works as a v0 machine_token for the full v0
 * API surface, so it's the only credential used here.
 *
 * @param int|null $user_id Optional. User ID. Defaults to current user.
 * @return string|\WP_Error Session token on success, WP_Error on failure.
 */
function get_v0_session_token( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return new \WP_Error(
			'no_user',
			__( 'Unable to authenticate - no user logged in.', 'ash-nazg' )
		);
	}

	// Check for cached session token (per-user).
	$cache_key = sprintf( 'ash_nazg_v0_session_token_%d', $user_id );
	$cached_token = get_transient( $cache_key );
	if ( false !== $cached_token ) {
		return $cached_token;
	}

	$access_token = get_user_access_token( $user_id );
	if ( ! $access_token ) {
		Helpers\debug_log( sprintf( 'No Personal Access Token found for user %d', $user_id ) );
		return new \WP_Error(
			'no_v0_token',
			__( 'This feature requires a Pantheon Personal Access Token. Please add one in Settings.', 'ash-nazg' )
		);
	}

	$response = wp_remote_post(
		'https://api.pantheon.io/v0/authorize/machine-token',
		[
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode(
				[
					'machine_token' => $access_token,
					'client' => 'ash-nazg',
				]
			),
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		Helpers\debug_log( 'Failed to exchange legacy machine token: ' . $error_message );
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
	$body = wp_remote_retrieve_body( $response );

	if ( 401 === $status_code || 403 === $status_code ) {
		Helpers\debug_log( 'Invalid legacy machine token (status ' . $status_code . ')' );
		return new \WP_Error(
			'invalid_v0_token',
			__( 'Invalid legacy machine token. Please check your Machine Token in Settings.', 'ash-nazg' )
		);
	}

	if ( 200 !== $status_code ) {
		Helpers\debug_log( 'Legacy v0 authentication failed with status ' . $status_code );
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
		Helpers\debug_log( 'No session token in legacy v0 API response' );
		return new \WP_Error(
			'invalid_response',
			__( 'Invalid response from Pantheon API. No session token received.', 'ash-nazg' )
		);
	}

	$session_token = $data['session'];

	set_transient( $cache_key, $session_token, HOUR_IN_SECONDS );

	return $session_token;
}

/**
 * Clear the cached legacy v0 session token for a user.
 *
 * Internal only - v0 is now only used as a fallback for a handful of
 * functions, so there's no user-facing "Clear Session Token" control for it.
 *
 * @param int|null $user_id Optional. User ID. Defaults to current user.
 */
function clear_v0_session_token( $user_id = null ) {
	if ( null === $user_id ) {
		$user_id = get_current_user_id();
	}

	if ( ! $user_id ) {
		return;
	}

	delete_transient( sprintf( 'ash_nazg_v0_session_token_%d', $user_id ) );
}

/**
 * Make an authenticated v1 API request.
 *
 * @param string $endpoint API endpoint path, relative to https://api.pantheon.io/v1 (e.g., '/sites/abc123').
 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
 * @param array  $body     Optional request body.
 * @return array|\WP_Error Response data or WP_Error on failure.
 */
function api_request( $endpoint, $method = 'GET', $body = [] ) {
	$token = get_api_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	return do_api_request( 'https://api.pantheon.io/v1' . $endpoint, $token, $method, $body );
}

/**
 * Make an authenticated legacy v0 API request.
 *
 * Only used by the hybrid-fallback functions that have no v1 equivalent.
 * Uses a completely separate credential (legacy machine token, exchanged
 * for a v0 session token) from the v1 personal access token used elsewhere.
 *
 * @param string $endpoint API endpoint path, including the /v0 prefix (e.g., '/v0/sites/abc123').
 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
 * @param array  $body     Optional request body.
 * @return array|\WP_Error Response data or WP_Error on failure.
 */
function api_request_v0( $endpoint, $method = 'GET', $body = [] ) {
	$session_token = get_v0_session_token();
	if ( is_wp_error( $session_token ) ) {
		return $session_token;
	}

	$result = do_api_request( 'https://api.pantheon.io' . $endpoint, $session_token, $method, $body );

	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( isset( $data['status'] ) && in_array( (int) $data['status'], [ 401, 403 ], true ) ) {
			clear_v0_session_token();
			Helpers\debug_log( 'Legacy v0 authentication failed, cleared cached session token' );
		}
	}

	return $result;
}

/**
 * Shared HTTP request logic for both the v1 and legacy v0 API clients.
 *
 * @param string $url    Full request URL.
 * @param string $token  Bearer token to send.
 * @param string $method HTTP method (GET, POST, PUT, DELETE).
 * @param array  $body   Optional request body.
 * @return array|\WP_Error Response data or WP_Error on failure.
 */
function do_api_request( $url, $token, $method = 'GET', $body = [] ) {
	$args = [
		'method'  => $method,
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
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
		Helpers\debug_log( 'API request failed: ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
	$data = json_decode( $response_body, true );

	if ( 401 === $status_code || 403 === $status_code ) {
		Helpers\debug_log( sprintf( 'Authentication failed (%d) for %s', $status_code, $url ) );
		return new \WP_Error(
			'authentication_failed',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Authentication failed (%d). Please check your token in Settings.', 'ash-nazg' ),
				$status_code
			),
			[ 'status' => $status_code ]
		);
	}

	if ( $status_code < 200 || $status_code >= 300 ) {
		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown API error';
		Helpers\debug_log( sprintf( 'API error %d: %s', $status_code, $error_message ) );
		Helpers\debug_log( sprintf( 'Full API response body: %s', $response_body ) );
		return new \WP_Error(
			'api_error',
			sprintf(
				/* translators: %1$d: HTTP status code, %2$s: error message */
				__( 'API request failed (%1$d): %2$s', 'ash-nazg' ),
				$status_code,
				$error_message
			),
			[ 'status' => $status_code ]
		);
	}

	return $data;
}

/**
 * Add a `workflow_id` alias to a WorkflowPayload response.
 *
 * V1 write operations consistently return the workflow ID under `id`, but
 * some existing callers (carried over from v0, which was inconsistent about
 * this) read `workflow_id`. Aliasing avoids having to audit every call site.
 *
 * @param array|\WP_Error $result API response.
 * @return array|\WP_Error Same response with `workflow_id` added, or the error unchanged.
 */
function normalize_workflow_payload( $result ) {
	if ( is_wp_error( $result ) || ! is_array( $result ) ) {
		return $result;
	}

	if ( isset( $result['id'] ) && ! isset( $result['workflow_id'] ) ) {
		$result['workflow_id'] = $result['id'];
	}

	return $result;
}

/**
 * Get cached API endpoint data.
 *
 * Generic helper for GET requests with caching.
 *
 * @param string $endpoint API endpoint path.
 * @param string $cache_key Transient cache key.
 * @param int    $ttl       Cache time-to-live in seconds. Default DAY_IN_SECONDS.
 * @param bool   $use_v0    Whether to route this through the legacy v0 client. Default false.
 * @return array|\WP_Error Endpoint data or WP_Error on failure.
 */
function get_cached_endpoint( $endpoint, $cache_key, $ttl = DAY_IN_SECONDS, $use_v0 = false ) {
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
	$data = $use_v0 ? api_request_v0( $endpoint, 'GET' ) : api_request( $endpoint, 'GET' );

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
			$data = get_current_pantheon_user();
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
 * Get the current authenticated Pantheon user's profile (terminus whoami equivalent).
 *
 * Calls GET /current-user directly - v1 has no session token to parse a user
 * ID out of, so this (and get_user_id()) work from a single cached request
 * keyed by the WordPress user ID instead.
 *
 * @return array|null User data (id, email, firstName, lastName, name, workspacesConnection), or null on error.
 */
function get_current_pantheon_user() {
	$wp_user_id = get_current_user_id();
	if ( ! $wp_user_id ) {
		return null;
	}

	$cache_key = sprintf( 'ash_nazg_pantheon_user_%d', $wp_user_id );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached['data'];
	}

	/*
	 * currentUser_workspacesConnection_first must be set explicitly - it's a
	 * Relay-style connection with no documented default page size.
	 */
	$result = api_request( '/current-user?currentUser_workspacesConnection_first=50', 'GET' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to get Pantheon user profile: %s', $result->get_error_message() ) );
		return null;
	}

	/*
	 * v1 returns firstName/lastName flat; v0 nested them under `profile`.
	 * Alias into the old shape so the Dashboard view doesn't need to change.
	 */
	$result['profile'] = [
		'firstname' => $result['firstName'] ?? '',
		'lastname' => $result['lastName'] ?? '',
	];

	set_transient(
		$cache_key,
		[
			'data'      => $result,
			'cached_at' => time(),
		],
		DAY_IN_SECONDS
	);

	return $result;
}

/**
 * Get workspaces the current Pantheon user belongs to.
 *
 * V1 replaces "organizations" with "workspaces", nested directly in the
 * /current-user response rather than a separate endpoint. Reshaped back into
 * the old [{id, organization: {id, profile: {name, machine_name}}}] contract
 * so the Dashboard page's rendering loop doesn't need to change.
 *
 * @return array|null Array of org-shaped membership objects, or null on error.
 */
function get_user_organizations() {
	$user = get_current_pantheon_user();
	if ( ! $user || empty( $user['workspacesConnection']['edges'] ) ) {
		return null;
	}

	$memberships = [];
	foreach ( $user['workspacesConnection']['edges'] as $edge ) {
		$workspace = $edge['node']['workspace'] ?? null;
		if ( empty( $workspace['id'] ) ) {
			continue;
		}

		$memberships[] = [
			'id' => $workspace['id'],
			'organization' => [
				'id' => $workspace['id'],
				'profile' => [
					'name' => $workspace['name'] ?? $workspace['id'],
					'machine_name' => $workspace['id'],
				],
			],
		];
	}

	return $memberships;
}

/**
 * Get Pantheon site information.
 *
 * @param string|null $site_id Optional. Site UUID. Auto-detected if not provided.
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
		$endpoint = '/sites/' . $site_id;
		$cache_key = 'ash_nazg_site_info_' . $site_id;
		$api_data = get_cached_endpoint( $endpoint, $cache_key );

		// Merge: prefer $_ENV for fields it has, add API-only fields.
		if ( ! is_wp_error( $api_data ) ) {
			return array_merge( normalize_site_info( $api_data ), $env_data );
		}

		// API failed, return $_ENV data only.
		return $env_data;
	}

	// No $_ENV data, use API only.
	$endpoint = '/sites/' . $site_id;
	$cache_key = 'ash_nazg_site_info_' . $site_id;
	$api_data = get_cached_endpoint( $endpoint, $cache_key );

	return is_wp_error( $api_data ) ? $api_data : normalize_site_info( $api_data );
}

/**
 * Alias v1 Site fields to the v0 names some views still read directly.
 *
 * `holder_type`, `holder_id`, and `owner` have no v1 equivalent (the owning
 * workspace is exposed via `supportingWorkspaces` instead) and are not
 * backfilled.
 *
 * @param array $site Raw v1 Site data.
 * @return array Site data with v0-named aliases added.
 */
function normalize_site_info( $site ) {
	if ( ! is_array( $site ) ) {
		return $site;
	}

	if ( ! isset( $site['name'] ) && isset( $site['machineName'] ) ) {
		$site['name'] = $site['machineName'];
	}

	if ( ! isset( $site['created'] ) && ! empty( $site['createdAt'] ) ) {
		$site['created'] = strtotime( $site['createdAt'] );
	}

	if ( ! isset( $site['framework'] ) && ! empty( $site['upstream']['framework'] ) ) {
		$site['framework'] = $site['upstream']['framework'];
	}

	if ( ! isset( $site['frozen'] ) && isset( $site['isFrozen'] ) ) {
		$site['frozen'] = $site['isFrozen'];
	}

	if ( ! isset( $site['plan_name'] ) && ! empty( $site['settings']['planName'] ) ) {
		$site['plan_name'] = $site['settings']['planName'];
	}

	/*
	 * v1's regionLabel is already a human-readable label (e.g. "United
	 * States"), unlike v0's region code - stored under a distinct key so
	 * callers don't uppercase an already-formatted label.
	 */
	if ( ! isset( $site['region_label'] ) && ! empty( $site['regionLabel'] ) ) {
		$site['region_label'] = $site['regionLabel'];
	}

	// v1 has no numeric multidev limit, only a boolean "can create one more".
	if ( ! isset( $site['can_create_multidev'] ) && isset( $site['canCreateMultidev'] ) ) {
		$site['can_create_multidev'] = $site['canCreateMultidev'];
	}

	if ( isset( $site['upstream']['id'] ) && empty( $site['upstream']['product_id'] ) && empty( $site['upstream']['upstream_id'] ) ) {
		$site['upstream']['product_id'] = $site['upstream']['id'];
	}

	if ( ! isset( $site['organization'] ) && ! empty( $site['supportingWorkspaces'][0]['id'] ) ) {
		$site['organization'] = $site['supportingWorkspaces'][0]['id'];
		$site['organization_name'] = $site['supportingWorkspaces'][0]['name'] ?? '';
	}

	return $site;
}

/**
 * Update site label.
 *
 * V0 fallback: v1 has no PUT/PATCH on /sites/{id} yet - `label` is readable
 * but not writable via the public API. Requires a legacy machine token.
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

	$result = api_request_v0( $endpoint, 'PUT', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to update site label for %s: %s', $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear site info cache to force refresh.
	delete_transient( sprintf( 'ash_nazg_site_info_%s', $site_id ) );

	Helpers\debug_log( sprintf( 'Site label updated to "%s" for site %s', $label, $site_id ) );

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
	$environments = get_environments( $site_id );

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
 * Get the current git branch by reading .git/HEAD directly.
 *
 * Avoids shelling out. Returns null when not in a git repo or in detached HEAD state.
 *
 * @return string|null Branch name or null.
 */
function get_current_git_branch() {
	$head_file = ABSPATH . '.git/HEAD';
	if ( ! file_exists( $head_file ) ) {
		return null;
	}
	$head = trim( file_get_contents( $head_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( 0 === strpos( $head, 'ref: refs/heads/' ) ) {
		return substr( $head, strlen( 'ref: refs/heads/' ) );
	}
	return null;
}

/**
 * Get uncommitted git changes for local development environments.
 *
 * Runs `git status --short` to surface all modified, added, deleted, and
 * untracked files. Returns an array keyed by filename — same shape as the
 * SFTP diffstat response — so the view can reuse the same rendering logic.
 *
 * Returns null when exec() is unavailable or we are not in a git repo.
 *
 * @return array|null Array of [ 'filename' => [ 'status' => 'M' ] ] or null.
 */
function get_local_git_diffstat() {
	// Bail early if ABSPATH is not a git repository root.
	if ( ! is_dir( ABSPATH . '.git' ) ) {
		return null;
	}

	// Resolve the git binary — PHP's exec() PATH may not include /usr/bin.
	$git_bin = 'git';
	if ( function_exists( 'shell_exec' ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$which = trim( (string) shell_exec( 'which git 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( $which ) {
			$git_bin = $which;
		}
	}

	/*
	 * -c safe.directory bypasses git's ownership check which blocks access in
	 * containerised environments (e.g. Lando) where PHP runs as a different
	 * user than who owns the repository. --porcelain is stable and color-free.
	 */
	$safe_dir = rtrim( ABSPATH, '/' );
	$cmd      = escapeshellarg( $git_bin )
		. ' -c safe.directory=' . escapeshellarg( $safe_dir )
		. ' -C ' . escapeshellarg( ABSPATH )
		. ' status --porcelain 2>/dev/null';

	// Try exec() first; fall back to shell_exec() if exec() is disabled.
	if ( function_exists( 'exec' ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.exec_exec
		$output      = [];
		$return_code = 0;
		exec( $cmd, $output, $return_code ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.exec_exec
		if ( 0 !== $return_code ) {
			Helpers\debug_log( sprintf( 'get_local_git_diffstat: git returned exit code %d', $return_code ) );
			return null;
		}
	} elseif ( function_exists( 'shell_exec' ) ) {
		$raw = shell_exec( $cmd ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( null === $raw || false === $raw ) {
			return null;
		}
		$output = array_filter( explode( "\n", trim( $raw ) ) );
	} else {
		return null;
	}

	return parse_git_porcelain_lines( $output );
}

/**
 * Parse raw lines from `git status --porcelain` into a filename-keyed array.
 *
 * Extracted so it can be unit-tested independently of exec().
 *
 * @param array $lines Raw output lines from git status --porcelain.
 * @return array Map of filename => ['status' => 'XY'] for every changed file.
 */
function parse_git_porcelain_lines( $lines ) {
	$files = [];
	foreach ( (array) $lines as $line ) {
		if ( ! preg_match( '/^([ACDMRUT?! ]{2})\s(.+)$/', $line, $matches ) ) {
			continue;
		}
		$status   = trim( $matches[1] );
		$filename = trim( $matches[2] );
		if ( $filename ) {
			$files[ $filename ] = [ 'status' => $status ?: '?' ];
		}
	}
	return $files;
}

/**
 * Get commits that exist locally but have not been pushed to the remote tracking branch.
 *
 * Uses `git log @{u}..` which compares the current branch HEAD against its upstream.
 * Returns null when there is no remote tracking branch configured or git is unavailable.
 * Returns an empty array when all commits have been pushed.
 *
 * @return array[]|null Array of ['hash' => string, 'message' => string], or null on error.
 */
function get_local_git_unpushed() {
	if ( ! is_dir( ABSPATH . '.git' ) ) {
		return null;
	}

	$git_bin = 'git';
	if ( function_exists( 'shell_exec' ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		$which = trim( (string) shell_exec( 'which git 2>/dev/null' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( $which ) {
			$git_bin = $which;
		}
	}

	$safe_dir = rtrim( ABSPATH, '/' );
	$cmd      = escapeshellarg( $git_bin )
		. ' -c safe.directory=' . escapeshellarg( $safe_dir )
		. ' -C ' . escapeshellarg( ABSPATH )
		. ' log @{u}.. --format="%H|%s" 2>/dev/null';

	if ( function_exists( 'exec' ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.exec_exec
		$output      = [];
		$return_code = 0;
		exec( $cmd, $output, $return_code ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.exec_exec
		if ( 0 !== $return_code ) {
			return null;
		}
	} elseif ( function_exists( 'shell_exec' ) ) {
		$raw = shell_exec( $cmd ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
		if ( null === $raw || false === $raw ) {
			return null;
		}
		$output = array_filter( explode( "\n", trim( $raw ) ) );
	} else {
		return null;
	}

	return parse_git_log_lines( array_filter( (array) $output ) );
}

/**
 * Parse raw lines from `git log --format="%H|%s"` into structured commit data.
 *
 * The pipe separator is used with explode limit=2 so message text containing
 * pipes is preserved correctly.
 *
 * @param array $lines Raw output lines.
 * @return array[] Array of ['hash' => string, 'message' => string].
 */
function parse_git_log_lines( $lines ) {
	$commits = [];
	foreach ( (array) $lines as $line ) {
		$line = trim( $line );
		if ( ! $line ) {
			continue;
		}
		$parts = explode( '|', $line, 2 );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$commits[] = [
			'hash'    => trim( $parts[0] ),
			'message' => trim( $parts[1] ),
		];
	}
	return $commits;
}

/**
 * Get the effective Pantheon environment, resolving multidev context for local environments.
 *
 * When running locally (Lando, ddev, etc.), reads the current git branch. If that
 * branch corresponds to an existing Pantheon multidev, returns the multidev name.
 * Falls back to 'dev' when on master/main or when no matching environment exists.
 *
 * On actual Pantheon environments this is identical to get_pantheon_environment().
 *
 * @param string|null $site_id Site UUID. Auto-detected if null.
 * @return string Environment name.
 */
function get_effective_environment( $site_id = null ) {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$env = get_pantheon_environment();

	$local_env_names = [ 'lando', 'local', 'localhost', 'ddev' ];
	if ( ! $env || ! in_array( strtolower( $env ), $local_env_names, true ) ) {
		$cached = $env ?: 'dev';
		return $cached;
	}

	// Read the git branch to detect multidev context locally.
	$branch = get_current_git_branch();
	if ( ! $branch || in_array( $branch, [ 'master', 'main' ], true ) ) {
		$cached = 'dev';
		return $cached;
	}

	// Only use the branch name if the corresponding Pantheon environment actually exists.
	if ( ! $site_id ) {
		$site_id = get_pantheon_site_id();
	}
	if ( $site_id ) {
		$environments = get_environments( $site_id );
		if ( ! is_wp_error( $environments ) && isset( $environments[ $branch ] ) ) {
			$cached = $branch;
			return $cached;
		}
	}

	$cached = 'dev';
	return $cached;
}

/**
 * Clear all API caches.
 *
 * @return void
 */
function clear_cache() {
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
 * Get the Pantheon user ID for the current WordPress user.
 *
 * @return string|false User ID or false if not available.
 */
function get_user_id() {
	$user = get_current_pantheon_user();
	if ( ! $user || empty( $user['id'] ) ) {
		return false;
	}
	return $user['id'];
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
	// Force a fresh site info fetch, bypassing the cache, to verify connectivity now.
	$site_id = Helpers\ensure_site_id( null );
	if ( ! is_wp_error( $site_id ) ) {
		delete_transient( sprintf( 'ash_nazg_site_info_%s', $site_id ) );
	}

	$result = get_site_info();

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return true;
}

/**
 * Get live addon environment variables from $_ENV (Pantheon) or Terminus internal API (local).
 *
 * On Pantheon environments, reads directly from $_ENV which is always current.
 * On local environments (Lando/ddev), calls the Terminus internal variables endpoint
 * which returns the full env var set including PANTHEON_INDEX_* for Solr.
 *
 * Note: terminus.pantheon.io is a separate internal service, not part of the
 * api.pantheon.io v0/v1 versioning. It's unverified whether it accepts a v1
 * personal access token the same way it accepted v0 session tokens - flagged
 * in TODO.md since this only affects local (Lando/ddev) development.
 *
 * @param string $site_id Optional. Site UUID.
 * @param string $env     Optional. Environment name.
 * @return array|\WP_Error Array of env var key/value pairs, WP_Error on failure.
 */
function get_addon_env_variables( $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	$env = Helpers\ensure_environment( $env );
	$api_env = map_local_env_to_dev( $env );

	// On Pantheon (non-local), $_ENV is always current — no API call needed.
	if ( ! Helpers\is_local_environment( $env ) ) {
		return [
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only as truthiness check, never rendered
			'CACHE_BINDING_ID' => sanitize_text_field( wp_unslash( $_ENV['CACHE_BINDING_ID'] ?? '' ) ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only as truthiness check, never rendered
			'PANTHEON_SEARCH_VERSION' => sanitize_text_field( wp_unslash( $_ENV['PANTHEON_SEARCH_VERSION'] ?? '' ) ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only as truthiness check, never rendered
			'IS_EPIO_ENVIRONMENT' => sanitize_text_field( wp_unslash( $_ENV['IS_EPIO_ENVIRONMENT'] ?? '' ) ),
		];
	}

	// Local: check transient cache first.
	$cache_key = sprintf( 'ash_nazg_addon_env_vars_%s_%s', $site_id, $api_env );
	$cached = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	// Local: call Terminus internal variables endpoint which returns full env var set.
	$token = get_api_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$terminus_url = sprintf(
		'https://terminus.pantheon.io/api/sites/%s/environments/%s/variables',
		$site_id,
		$api_env
	);

	$response = wp_remote_get(
		$terminus_url,
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
			],
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		Helpers\debug_log( 'get_addon_env_variables: Terminus API request failed: ' . $response->get_error_message() );
		return $response;
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( 401 === $status_code || 403 === $status_code ) {
		Helpers\debug_log( sprintf( 'get_addon_env_variables: Auth failed (%d)', $status_code ) );
		return new \WP_Error(
			'authentication_failed',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Authentication failed (%d). Please check your Personal Access Token in Settings.', 'ash-nazg' ),
				$status_code
			)
		);
	}

	if ( $status_code < 200 || $status_code >= 300 ) {
		$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error';
		Helpers\debug_log( sprintf( 'get_addon_env_variables: Terminus API error %d: %s', $status_code, $error_message ) );
		return new \WP_Error(
			'api_error',
			sprintf(
				/* translators: %1$d: HTTP status code, %2$s: error message */
				__( 'Terminus variables request failed (%1$d): %2$s', 'ash-nazg' ),
				$status_code,
				$error_message
			),
			[ 'status' => $status_code ]
		);
	}

	if ( ! is_array( $data ) ) {
		return new \WP_Error( 'invalid_response', __( 'Invalid response from Terminus variables endpoint.', 'ash-nazg' ) );
	}

	$vars = [
		'CACHE_BINDING_ID' => $data['CACHE_BINDING_ID'] ?? '',
		'PANTHEON_SEARCH_VERSION' => $data['PANTHEON_SEARCH_VERSION'] ?? '',
		'IS_EPIO_ENVIRONMENT' => $data['IS_EPIO_ENVIRONMENT'] ?? '',
	];

	set_transient( $cache_key, $vars, HOUR_IN_SECONDS );

	return $vars;
}

/**
 * Get site addons.
 *
 * Returns list of known Pantheon addons with live enabled/disabled status.
 * On Pantheon, reads directly from $_ENV. On local, uses the Terminus internal
 * variables API. Falls back to stored state if the live check fails.
 *
 * @param string $site_id Optional. Site UUID. If not provided, auto-detected.
 * @return array|\WP_Error Array of addon objects on success, WP_Error on failure.
 */
function get_site_addons( $site_id = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	// get_addon_env_variables() handles its own caching — no outer transient needed.
	$env_vars = get_addon_env_variables( $site_id );
	$use_live = ! is_wp_error( $env_vars );

	// Fallback: stored addon states from environment state.
	$env_state = get_environment_state();
	$stored_states = ( $env_state && isset( $env_state['addons'] ) ) ? $env_state['addons'] : [];

	// Migrate old addon_states option if present.
	if ( empty( $stored_states ) ) {
		$old_states = get_option( 'ash_nazg_addon_states', [] );
		if ( ! empty( $old_states ) ) {
			update_environment_state( [ 'addons' => $old_states ] );
			$stored_states = $old_states;
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
		'elasticsearch' => [
			'id' => 'elasticsearch',
			'name' => __( 'Elasticsearch', 'ash-nazg' ),
			'description' => __( "Elasticsearch-powered search via Pantheon's hosted ElasticPress.io integration", 'ash-nazg' ),
		],
	];

	$addons = [];

	foreach ( $known_addons as $addon_id => $addon_meta ) {
		$addon = $addon_meta;

		if ( $use_live ) {
			if ( 'redis' === $addon_id ) {
				$addon['enabled'] = ! empty( $env_vars['CACHE_BINDING_ID'] );
			} elseif ( 'solr' === $addon_id ) {
				$addon['enabled'] = ! empty( $env_vars['PANTHEON_SEARCH_VERSION'] );
			} elseif ( 'elasticsearch' === $addon_id ) {
				$addon['enabled'] = ! empty( $env_vars['IS_EPIO_ENVIRONMENT'] );
			} else {
				$addon['enabled'] = isset( $stored_states[ $addon_id ] ) ? (bool) $stored_states[ $addon_id ] : false;
			}
		} else {
			$addon['enabled'] = isset( $stored_states[ $addon_id ] ) ? (bool) $stored_states[ $addon_id ] : false;
		}

		$addons[] = $addon;
	}

	return $addons;
}

/**
 * Update site addon.
 *
 * Enable or disable a specific site addon via the Pantheon API.
 * v1 uses a single PUT with an `enable` query param for both directions
 * (v0 used PUT to enable and DELETE to disable).
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

	// Map plugin addon IDs to the v1 AddonType enum.
	$addon_type_map = [
		'redis' => 'CACHESERVER',
		'solr' => 'INDEXSERVER',
		'elasticsearch' => 'ELASTICSEARCH',
	];
	$addon_type = $addon_type_map[ $addon_id ] ?? strtoupper( $addon_id );

	$endpoint = sprintf(
		'/sites/%s/addons/%s?enable=%s',
		$site_id,
		$addon_type,
		$enabled ? 'true' : 'false'
	);

	$result = api_request( $endpoint, 'PUT', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to %s addon %s for site %s: %s', $enabled ? 'enable' : 'disable', $addon_id, $site_id, $result->get_error_message() ) );
		return $result;
	}

	Helpers\debug_log( sprintf( 'Addon %s %s for site %s - API Response: %s', $addon_id, $enabled ? 'enabled' : 'disabled', $site_id, wp_json_encode( $result ) ) );

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
 * Clears the addon env variables transient so the next page load re-fetches
 * live status rather than serving a cached pre-toggle value.
 *
 * @param string $site_id Site UUID.
 * @return void
 */
function clear_addons_cache( $site_id ) {
	$env = get_pantheon_environment();
	$api_env = map_local_env_to_dev( $env );
	$cache_key = sprintf( 'ash_nazg_addon_env_vars_%s_%s', $site_id, $api_env );
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
		Helpers\debug_log( sprintf( 'Failed to sync environment state - %s', $env_info->get_error_message() ) );
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
 * V1 splits this into two distinct endpoints instead of v0's single PUT with
 * a `mode` body param.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param string $mode Connection mode: 'sftp' or 'git'.
 * @return array|\WP_Error Workflow payload or WP_Error on failure.
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

	$endpoint = 'sftp' === $mode
		? sprintf( '/sites/%s/environments/%s/development-mode/on-server', $site_id, $api_env )
		: sprintf( '/sites/%s/environments/%s/development-mode/git', $site_id, $api_env );

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to update connection mode to %s on %s/%s - Error: %s', $mode, $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/*
	 * Clear environment info cache to force refresh.
	 * Note: We do NOT update the state here. The state should only be updated
	 * after the mode change is verified, which is done by the caller.
	 */
	delete_transient( sprintf( 'ash_nazg_environments_%s', $site_id ) );

	Helpers\debug_log( sprintf( 'Connection mode change to %s initiated on %s/%s', $mode, $site_id, $env ) );

	return normalize_workflow_payload( $result );
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
 * V0 fallback: v1 has no generic `POST .../environments/{env}/workflows`
 * endpoint - only specific typed mutations (deploy, addon, domain, etc).
 * Requires a legacy machine token.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param string $workflow_type Workflow type (e.g., 'scaffold_extensions').
 * @param array $params Workflow parameters.
 * @return array|\WP_Error Workflow response or WP_Error on failure.
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

	$result = api_request_v0( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to trigger workflow %s on %s/%s (API env: %s) - Error: %s', $workflow_type, $site_id, $env, $api_env, $result->get_error_message() ) );
		return $result;
	}

	Helpers\debug_log( sprintf( 'Triggered workflow %s on %s/%s (API env: %s) - Response: %s', $workflow_type, $site_id, $env, $api_env, wp_json_encode( $result ) ) );

	return $result;
}

/**
 * Get workflow status.
 *
 * V1's WorkflowStatus.result is uppercase (SUCCEEDED/FAILED/...) and has no
 * `step`/`active_description` fields (v0 had both, lowercase result). Aliased
 * back to the old shape so callers (including JS-side polling) don't change.
 *
 * @param string $site_id Site UUID.
 * @param string $workflow_id Workflow UUID.
 * @return array|\WP_Error Workflow data or WP_Error on failure.
 */
function get_workflow_status( $site_id, $workflow_id ) {
	$endpoint = sprintf( '/sites/%s/workflows/%s', $site_id, $workflow_id );
	$result = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to get workflow status %s on %s - Error: %s', $workflow_id, $site_id, $result->get_error_message() ) );
		return $result;
	}

	if ( isset( $result['result'] ) ) {
		$result['result'] = strtolower( $result['result'] );
	}
	if ( isset( $result['activeDescription'] ) && ! isset( $result['active_description'] ) ) {
		$result['active_description'] = $result['activeDescription'];
	}

	return $result;
}

/**
 * Get git commit history for an environment.
 *
 * V1 returns commit history as the `commitLog` field nested on the
 * Environment object (from get_environments()) rather than a dedicated
 * endpoint. Reshaped to the old {hash, message, author, datetime} contract.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return array|\WP_Error Array of commit objects or WP_Error on failure.
 */
function get_environment_commits( $site_id, $env ) {
	$cache_key = sprintf( 'ash_nazg_commits_%s_%s', $site_id, $env );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$environments = get_environments( $site_id );

	if ( is_wp_error( $environments ) ) {
		Helpers\debug_log( sprintf( 'Failed to get commits for %s/%s: %s', $site_id, $env, $environments->get_error_message() ) );
		return $environments;
	}

	if ( ! isset( $environments[ $api_env ]['commitLog'] ) || ! is_array( $environments[ $api_env ]['commitLog'] ) ) {
		return [];
	}

	$result = array_map( __NAMESPACE__ . '\\normalize_commit', $environments[ $api_env ]['commitLog'] );

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
 * Reshape a v1 Commit object to the old v0 {hash, message, author, datetime} contract.
 *
 * @param array $commit Raw v1 Commit object.
 * @return array Reshaped commit.
 */
function normalize_commit( $commit ) {
	return [
		'hash' => $commit['sha'] ?? '',
		'message' => $commit['message'] ?? '',
		'author' => $commit['author'] ?? '',
		'datetime' => $commit['createdAt'] ?? '',
	];
}

/**
 * Get available upstream update commits.
 *
 * V1's upstream-updates/check is an async workflow (unlike v0's synchronous
 * GET) - this triggers the check, waits for it to finish, then re-reads the
 * environment's commit log to approximate the old {commits, behind, has_code,
 * environments} shape. Unverified against a live site - flagged in TODO.md.
 *
 * @param string      $site_id Site UUID.
 * @param string|null $env     Current environment name. Null falls back to dev.
 * @return array|\WP_Error Array shaped like the old public API response, or WP_Error on failure.
 */
function get_upstream_updates( $site_id, $env = null ) {
	// Use an env-specific cache key for multidevs since their branch differs from master.
	$is_multidev = $env && Helpers\is_multidev_environment( $env );
	$cache_key = $is_multidev
		? sprintf( 'ash_nazg_upstream_updates_%s_%s', $site_id, $env )
		: sprintf( 'ash_nazg_upstream_updates_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	$api_env = map_local_env_to_dev( $env ?: 'dev' );

	$endpoint = sprintf( '/sites/%s/environments/%s/upstream-updates/check', $site_id, $api_env );
	$workflow = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $workflow ) ) {
		Helpers\debug_log( sprintf( 'Failed to check upstream updates for %s: %s', $site_id, $workflow->get_error_message() ) );
		return $workflow;
	}

	// Wait for the check workflow to finish before re-reading environment state.
	if ( ! empty( $workflow['id'] ) ) {
		poll_workflow( $site_id, $workflow['id'], 15, 2 );
	}

	$environments = get_environments( $site_id );
	if ( is_wp_error( $environments ) ) {
		return $environments;
	}

	$env_data = $environments[ $api_env ] ?? [];
	$composer_updates = $env_data['composerUpdates'] ?? [];
	$updated_deps = array_merge(
		$composer_updates['addedDependencies'] ?? [],
		$composer_updates['updatedDependencies'] ?? [],
		$composer_updates['removedDependencies'] ?? []
	);

	$commits = array_map( __NAMESPACE__ . '\\normalize_commit', $env_data['commitLog'] ?? [] );

	$result = [
		'commits' => $commits,
		'behind' => count( $updated_deps ),
		'has_code' => ! empty( $updated_deps ) || ! empty( $commits ),
		'environments' => [],
	];

	/*
	 * Cache for 5 minutes.
	 * Short TTL so a revert on Pantheon doesn't leave stale data for long.
	 */
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
 * Apply upstream updates to an environment.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @param bool   $updatedb Whether to run update.php after applying (Drupal).
 * @param bool   $xoption Whether to accept updates with merge conflicts (force theirs).
 * @return array|\WP_Error Workflow response on success, WP_Error on failure.
 */
function apply_upstream_updates( $site_id, $env, $updatedb = false, $xoption = false ) {
	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf(
		'/sites/%s/environments/%s/upstream-updates/apply?updateDb=%s&xOption=%s',
		$site_id,
		$api_env,
		$updatedb ? 'true' : 'false',
		$xoption ? 'true' : 'false'
	);

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to apply upstream updates on %s.%s - Error: %s', $site_id, $env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear upstream updates cache after applying. */
	delete_transient( sprintf( 'ash_nazg_upstream_updates_%s', $site_id ) );

	Helpers\debug_log( sprintf( 'Applied upstream updates on %s.%s - Response: %s', $site_id, $env, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
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
 * @return array|\WP_Error Workflow response or WP_Error on failure.
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

	$endpoint = sprintf( '/sites/%s/environments/%s/deployments', $site_id, $target_env );

	$body = [
		'clearCache' => $clear_cache,
		'updateDb' => $updatedb,
		'deployCode' => true,
	];

	if ( ! empty( $note ) ) {
		$body['annotation'] = $note;
	}

	/* sync_content only applies to test → live. */
	if ( 'live' === $target_env && $sync_content ) {
		$body['cloneDatabase'] = [ 'fromEnvironment' => 'live' ];
	}

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to deploy code to %s.%s - Error: %s', $site_id, $target_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear commits cache for both source and target environments. */
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $source_env ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $target_env ) );

	Helpers\debug_log( sprintf( 'Deployed code to %s.%s - Response: %s', $site_id, $target_env, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Clone database from one environment to another.
 *
 * @param string $site_id Site UUID.
 * @param string $from_env Source environment to clone from.
 * @param string $to_env Target environment to clone to.
 * @param string $from_url Source URL for WordPress search-replace (optional).
 * @param string $to_url Target URL for WordPress search-replace (optional).
 * @param bool $clear_cache Unused - v1's database/clone endpoint has no clearCache param. Kept for call-site compatibility.
 * @param bool $updatedb Unused - v1's database/clone endpoint has no updateDb param. Kept for call-site compatibility.
 * @return array|\WP_Error Workflow data on success, WP_Error on failure.
 */
function clone_database( $site_id, $from_env, $to_env, $from_url = '', $to_url = '', $clear_cache = true, $updatedb = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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

	$endpoint = sprintf( '/sites/%s/environments/%s/database/clone', $site_id, $to_env );

	$body = [
		'fromEnvironment' => $from_env,
	];

	/* Add URLs for WordPress search-replace if provided. */
	if ( ! empty( $from_url ) && ! empty( $to_url ) ) {
		$body['wpReplaceSiteURL'] = [
			'fromUrl' => $from_url,
			'toUrl' => $to_url,
		];
	}

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to clone database from %s.%s to %s.%s - Error: %s', $site_id, $from_env, $site_id, $to_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear commits cache for both environments. */
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $from_env ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $to_env ) );

	/* Clear environment state cache. */
	delete_transient( sprintf( 'ash_nazg_env_state_%s_%s', $site_id, $to_env ) );

	Helpers\debug_log( sprintf( 'Cloned database from %s.%s to %s.%s - Response: %s', $site_id, $from_env, $site_id, $to_env, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Clone files from one environment to another.
 *
 * @param string $site_id Site UUID.
 * @param string $from_env Source environment to clone from.
 * @param string $to_env Target environment to clone to.
 * @return array|\WP_Error Workflow data on success, WP_Error on failure.
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

	$endpoint = sprintf( '/sites/%s/environments/%s/files/clone', $site_id, $to_env );

	$body = [
		'fromEnvironment' => $from_env,
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to clone files from %s.%s to %s.%s - Error: %s', $site_id, $from_env, $site_id, $to_env, $result->get_error_message() ) );
		return $result;
	}

	/* Clear environment state cache. */
	delete_transient( sprintf( 'ash_nazg_env_state_%s_%s', $site_id, $to_env ) );

	Helpers\debug_log( sprintf( 'Cloned files from %s.%s to %s.%s - Response: %s', $site_id, $from_env, $site_id, $to_env, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Get domains for an environment.
 *
 * V0 fallback: v1 has no domain-listing endpoint yet (only create/delete/
 * set-primary/verify). Requires a legacy machine token. Note: as of this
 * migration this function has no real callers in the admin UI - only
 * add_domain()/delete_domain() (used by Multisite automation) are exercised.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Array of domains or WP_Error on failure.
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
	$result = api_request_v0( $endpoint, 'GET' );

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
 * @return array|\WP_Error API response or WP_Error on failure.
 */
function add_domain( $domain, $site_id = null, $env = 'live' ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	if ( empty( $domain ) ) {
		return new \WP_Error( 'invalid_domain', __( 'Domain name is required.', 'ash-nazg' ) );
	}

	$endpoint = sprintf( '/sites/%s/environments/%s/domains?domain=%s', $site_id, $env, rawurlencode( $domain ) );

	Helpers\debug_log( sprintf( 'Adding domain %s to %s/%s', $domain, $site_id, $env ) );

	$result = api_request( $endpoint, 'POST', [] );

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
 * @return array|\WP_Error API response or WP_Error on failure.
 */
function delete_domain( $domain, $site_id = null, $env = 'live' ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return $site_id;
	}

	if ( empty( $domain ) ) {
		return new \WP_Error( 'invalid_domain', __( 'Domain name is required.', 'ash-nazg' ) );
	}

	$endpoint = sprintf( '/sites/%s/environments/%s/domains/%s', $site_id, $env, rawurlencode( $domain ) );

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
 * V0 fallback: no v1 equivalent found for this endpoint. Requires a legacy
 * machine token.
 *
 * @param string $site_id Site UUID.
 * @return array|\WP_Error Array of git branch references or WP_Error on failure.
 */
function get_code_tips( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_code_tips_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	$endpoint = sprintf( '/v0/sites/%s/code-tips', $site_id );
	$result = api_request_v0( $endpoint, 'GET' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to get code tips for %s: %s', $site_id, $result->get_error_message() ) );
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
 * V1 returns this as the `diffstat` field nested on the Environment object
 * (from get_environments()) rather than a dedicated endpoint.
 *
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return array|\WP_Error Diffstat data or WP_Error on failure.
 */
function get_diffstat( $site_id, $env ) {
	$cache_key = sprintf( 'ash_nazg_diffstat_%s_%s', $site_id, $env );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$environments = get_environments( $site_id );

	if ( is_wp_error( $environments ) ) {
		Helpers\debug_log( sprintf( 'Failed to get diffstat for %s/%s: %s', $site_id, $env, $environments->get_error_message() ) );
		return $environments;
	}

	$result = [];
	foreach ( $environments[ $api_env ]['diffstat'] ?? [] as $file ) {
		if ( empty( $file['path'] ) ) {
			continue;
		}
		$result[ $file['path'] ] = [ 'status' => $file['status'] ?? '?' ];
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
 * @return array|\WP_Error Commit response or WP_Error on failure.
 */
function commit_sftp_changes( $site_id, $env, $message ) {
	/* Map local environment names to dev for API queries. */
	$api_env = map_local_env_to_dev( $env );

	$current_user = wp_get_current_user();

	$endpoint = sprintf(
		'/sites/%s/environments/%s/commits?message=%s&committerName=%s&committerEmail=%s',
		$site_id,
		$api_env,
		rawurlencode( $message ),
		rawurlencode( $current_user->display_name ),
		rawurlencode( $current_user->user_email )
	);

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to commit on %s/%s (API env: %s) - Error: %s', $site_id, $env, $api_env, $result->get_error_message() ) );
		return $result;
	}

	delete_transient( sprintf( 'ash_nazg_diffstat_%s_%s', $site_id, $env ) );

	Helpers\debug_log( sprintf( 'Committed changes on %s/%s (API env: %s) - Response: %s', $site_id, $env, $api_env, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Get all environments for a site.
 *
 * V1 returns a Relay-style EnvironmentConnection; reshaped into the same
 * name-keyed map (`$environments['dev'] => {...}`) that v0 returned, since
 * several other functions (get_environment_info, get_effective_environment,
 * get_environment_commits, get_diffstat) index into it by environment name.
 *
 * @param string $site_id Site UUID.
 * @return array|\WP_Error Environments data or WP_Error on failure.
 */
function get_environments( $site_id ) {
	$cache_key = sprintf( 'ash_nazg_environments_%s', $site_id );
	$cached = get_transient( $cache_key );

	if ( false !== $cached ) {
		return $cached['data'];
	}

	/*
	 * `pager[first]=N` (bracket query param syntax) is invalid here (HTTP
	 * 400). This endpoint already returns every environment with
	 * totalCount and no pager param at all, so omit it.
	 */
	$endpoint = sprintf( '/sites/%s/environments', $site_id );
	$connection = api_request( $endpoint, 'GET' );

	if ( is_wp_error( $connection ) ) {
		Helpers\debug_log( sprintf( 'Failed to get environments for %s: %s', $site_id, $connection->get_error_message() ) );
		return $connection;
	}

	$result = [];
	foreach ( $connection['edges'] ?? [] as $edge ) {
		$node = $edge['node'] ?? null;
		if ( empty( $node['id'] ) ) {
			continue;
		}
		// Preserve v0 field names some views still read directly.
		$is_sftp = isset( $node['developmentMode'] ) && 'SFTP' === $node['developmentMode'];
		$node['on_server_development'] = $is_sftp;
		$node['connection_mode'] = $is_sftp ? 'sftp' : 'git';
		if ( ! isset( $node['dns_zone'] ) && isset( $node['dnsZone'] ) ) {
			$node['dns_zone'] = $node['dnsZone'];
		}
		if ( ! isset( $node['initialized'] ) && isset( $node['isInitialized'] ) ) {
			$node['initialized'] = $node['isInitialized'];
		}
		$result[ $node['id'] ] = $node;
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
 * @return array|\WP_Error Environment creation response or WP_Error on failure.
 */
function create_multidev( $site_id, $env_name, $source_env = 'dev' ) {
	$endpoint = sprintf(
		'/sites/%s/multidevs?name=%s&cloneFromEnv=%s',
		$site_id,
		rawurlencode( $env_name ),
		rawurlencode( $source_env )
	);

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to create multidev %s on %s - Error: %s', $env_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear environments cache after creating new environment.
	delete_transient( sprintf( 'ash_nazg_environments_%s', $site_id ) );

	Helpers\debug_log( sprintf( 'Created multidev %s on %s - Response: %s', $env_name, $site_id, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Merge a multidev environment into dev.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to merge.
 * @return array|\WP_Error Merge response or WP_Error on failure.
 */
function merge_multidev_to_dev( $site_id, $multidev_name ) {
	$endpoint = sprintf( '/sites/%s/environments/%s/merges/cloud-to-dev', $site_id, $multidev_name );

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to merge multidev %s to dev on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear commits cache for dev environment.
	delete_transient( sprintf( 'ash_nazg_commits_%s_dev', $site_id ) );

	Helpers\debug_log( sprintf( 'Merged multidev %s to dev on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Merge dev environment into a multidev environment.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to merge dev into.
 * @param bool   $updatedb Whether to run update.php after merging (Drupal).
 * @return array|\WP_Error Workflow response or WP_Error on failure.
 */
function merge_dev_to_multidev( $site_id, $multidev_name, $updatedb = false ) {
	$endpoint = sprintf( '/sites/%s/environments/%s/merges/dev-to-cloud', $site_id, $multidev_name );
	$body = [
		'params' => [
			'fromEnvironment' => 'dev',
			'updatedb' => (bool) $updatedb,
		],
	];

	$result = api_request( $endpoint, 'POST', $body );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to merge dev to multidev %s on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear commits cache for both dev and multidev (they're now in sync).
	delete_transient( sprintf( 'ash_nazg_commits_%s_dev', $site_id ) );
	delete_transient( sprintf( 'ash_nazg_commits_%s_%s', $site_id, $multidev_name ) );

	Helpers\debug_log( sprintf( 'Merged dev to multidev %s on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Delete a multidev environment.
 *
 * @param string $site_id Site UUID.
 * @param string $multidev_name Multidev environment name to delete.
 * @return array|\WP_Error Deletion response or WP_Error on failure.
 */
function delete_multidev( $site_id, $multidev_name ) {
	// Prevent deletion of standard environments.
	if ( in_array( $multidev_name, [ 'dev', 'test', 'live' ], true ) ) {
		return new \WP_Error(
			'invalid_environment',
			__( 'Cannot delete standard environments (dev, test, live).', 'ash-nazg' )
		);
	}

	$endpoint = sprintf( '/sites/%s/multidevs/%s', $site_id, $multidev_name );

	$result = api_request( $endpoint, 'DELETE' );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to delete multidev %s on %s - Error: %s', $multidev_name, $site_id, $result->get_error_message() ) );
		return $result;
	}

	// Clear environments cache after deleting environment.
	delete_transient( sprintf( 'ash_nazg_environments_%s', $site_id ) );

	Helpers\debug_log( sprintf( 'Deleted multidev %s on %s - Response: %s', $multidev_name, $site_id, wp_json_encode( $result ) ) );

	return normalize_workflow_payload( $result );
}

/**
 * Poll workflow until completion.
 *
 * @param string $site_id Site UUID.
 * @param string $workflow_id Workflow ID.
 * @param int    $max_attempts Maximum number of polling attempts (default: 60).
 * @param int    $sleep_seconds Seconds to wait between polls (default: 2).
 * @return array|\WP_Error Final workflow status or WP_Error on failure/timeout.
 */
function poll_workflow( $site_id, $workflow_id, $max_attempts = 60, $sleep_seconds = 2 ) {
	$attempts = 0;

	while ( $attempts < $max_attempts ) {
		$status = get_workflow_status( $site_id, $workflow_id );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		// Check if workflow is complete.
		if ( isset( $status['result'] ) && in_array( $status['result'], [ 'SUCCEEDED', 'FAILED', 'succeeded', 'failed' ], true ) ) {
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
 * Get the raw v1 Backup[] response for an environment, cached.
 *
 * Shared by get_backups() (reshapes into the old flat v0 catalog contract)
 * and get_backup_download_url() (needs the raw per-element `timestamp`,
 * which the reshaped catalog doesn't carry).
 *
 * @param string $site_id Site UUID.
 * @param string $api_env Environment name, already mapped via map_local_env_to_dev().
 * @return array|\WP_Error Raw Backup[] array or WP_Error on failure.
 */
function get_raw_backups( $site_id, $api_env ) {
	$endpoint = sprintf( '/sites/%s/environments/%s/backups', $site_id, $api_env );
	$cache_key = sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env );

	// Cache for 5 minutes (backups don't change frequently).
	return get_cached_endpoint( $endpoint, $cache_key, 5 * MINUTE_IN_SECONDS );
}

/**
 * Get backups for an environment.
 *
 * V1 groups each backup's code/database/files archives under one Backup
 * object (`{id, date, code, database, files}`); v0 instead returned one flat
 * catalog entry per archive, keyed by a synthetic id, with a `folder` field
 * (shared across the 3 entries for one backup event) that the Backups page
 * uses to group them back into a set. Reshaped into that flat contract so
 * the view's grouping/rendering logic doesn't need to change.
 *
 * `Backup.date` is a numeric-string Unix timestamp, not an ISO date string.
 * Each element sub-object (code/database/files) carries real `size` and
 * `ttl` fields plus a complete `name` (already including the file
 * extension), all used here.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Backups catalog (map of backup_id => flat archive entry) or WP_Error on failure.
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

	$backups = get_raw_backups( $site_id, $api_env );

	if ( is_wp_error( $backups ) ) {
		Helpers\debug_log( sprintf( 'Failed to get backups for %s.%s - Error: %s', $site_id, $api_env, $backups->get_error_message() ) );
		return $backups;
	}

	$element_suffixes = [
		'code' => '_code.tar.gz',
		'database' => '_database.sql.gz',
		'files' => '_files.tar.gz',
	];

	$catalog = [];
	foreach ( (array) $backups as $backup ) {
		if ( empty( $backup['id'] ) ) {
			continue;
		}
		// Backup.date is a numeric-string Unix timestamp, not an ISO date.
		$timestamp = is_numeric( $backup['date'] ?? null ) ? (int) $backup['date'] : 0;

		foreach ( $element_suffixes as $element => $suffix ) {
			if ( empty( $backup[ $element ] ) ) {
				continue;
			}
			$entry_id = sprintf( '%s_%s', $backup['id'], $element );

			/*
			 * name already includes the file extension - only synthesize
			 * one from the suffix map if it's ever missing.
			 */
			$filename = $backup[ $element ]['name'] ?? ( $backup['id'] . $suffix );
			$catalog[ $entry_id ] = [
				'folder' => $backup['id'],
				'filename' => $filename,
				'timestamp' => $timestamp,
				'size' => (int) ( $backup[ $element ]['size'] ?? 0 ),
				'ttl' => (int) ( $backup[ $element ]['ttl'] ?? 0 ),
			];
		}
	}

	Helpers\debug_log( sprintf( 'Retrieved %d backups for %s.%s', count( $catalog ), $site_id, $api_env ) );

	return $catalog;
}

/**
 * Create a new backup.
 *
 * Note: v1's createSiteBackup has no per-element selection like v0 did
 * ('code', 'database', 'files') - it always creates a full code+database+
 * files combo backup. $element is kept for call-site compatibility but is
 * not sent to the API; this is a behavior change from v0, flagged in TODO.md.
 *
 * @param string $element Backup element type: 'all', 'code', 'database', or 'files'. Not sent to v1 - see note above.
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

	// Validate element type (for signature/call-site compatibility only - see docblock).
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

	// Validate keep_for (minimum 1 day), convert to seconds for v1's ttl param.
	$keep_for = max( 1, (int) $keep_for );
	$ttl_seconds = $keep_for * DAY_IN_SECONDS;

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	$endpoint = sprintf( '/sites/%s/backups?environment=%s&ttl=%d', $site_id, $api_env, $ttl_seconds );

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to create backup for %s.%s - Error: %s', $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	// Clear backups cache after creating backup.
	delete_transient( sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env ) );

	Helpers\debug_log( sprintf( 'Created backup for %s.%s - Workflow ID: %s', $site_id, $api_env, $result['id'] ?? 'unknown' ) );

	return normalize_workflow_payload( $result );
}

/**
 * Restore a backup.
 *
 * V0 fallback: v1's restore endpoint is confirmed broken platform-wide
 * (fails regardless of parameters). Using v0 so this works as soon as
 * Pantheon fixes it. See TODO.md.
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

	$endpoint = sprintf(
		'/v0/sites/%s/environments/%s/backups/%s/restore',
		$site_id,
		$api_env,
		rawurlencode( $backup_id )
	);

	$result = api_request_v0( $endpoint, 'POST', [ 'element' => $element ] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to restore %s from backup %s on %s.%s - Error: %s', $element, $backup_id, $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	// Clear backups cache after restore.
	delete_transient( sprintf( 'ash_nazg_backups_%s_%s', $site_id, $api_env ) );

	Helpers\debug_log( sprintf( 'Restored %s from backup %s on %s.%s - Workflow ID: %s', $element, $backup_id, $site_id, $api_env, $result['id'] ?? 'unknown' ) );

	return normalize_workflow_payload( $result );
}

/**
 * Get a signed download URL for a backup file.
 *
 * V1 identifies the backup archive by element `type` + `timestamp`, not
 * v0's opaque backup_id/folder name. That opaque id is a hash (e.g.
 * "kWrk7cFXaq0...="), not a timestamp, so it can't be parsed out of
 * $backup_id directly - instead, re-look up the matching Backup from the
 * (cached) backups list by its id and read the element's own `timestamp`.
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
	$backup_type = strtoupper( $element );

	$raw_backups = get_raw_backups( $site_id, $api_env );

	if ( is_wp_error( $raw_backups ) ) {
		return $raw_backups;
	}

	$timestamp = null;
	foreach ( (array) $raw_backups as $backup ) {
		if ( ( $backup['id'] ?? null ) === $backup_id && ! empty( $backup[ $element ]['timestamp'] ) ) {
			$timestamp = (int) $backup[ $element ]['timestamp'];
			break;
		}
	}

	if ( null === $timestamp ) {
		return new \WP_Error(
			'backup_not_found',
			__( 'Could not find the requested backup. It may have expired or been removed.', 'ash-nazg' )
		);
	}

	$endpoint = sprintf(
		'/sites/%s/environments/%s/backup-download-urls?type=%s&timestamp=%d',
		$site_id,
		$api_env,
		$backup_type,
		$timestamp
	);

	$result = api_request( $endpoint, 'POST', [] );

	if ( is_wp_error( $result ) ) {
		Helpers\debug_log( sprintf( 'Failed to get download URL for %s backup %s on %s.%s - Error: %s', $element, $backup_id, $site_id, $api_env, $result->get_error_message() ) );
		return $result;
	}

	if ( ! isset( $result['url'] ) ) {
		Helpers\debug_log( sprintf( 'Download URL response missing url field for %s backup %s on %s.%s', $element, $backup_id, $site_id, $api_env ) );
		return new \WP_Error(
			'missing_url',
			__( 'API response did not include download URL', 'ash-nazg' )
		);
	}

	Helpers\debug_log( sprintf( 'Retrieved download URL for %s backup %s on %s.%s', $element, $backup_id, $site_id, $api_env ) );

	return $result['url'];
}

/**
 * Get environment metrics for a specific time period.
 *
 * V0 fallback: v1 has no metrics endpoint yet - the TimeseriesMetricsPoint/
 * MetricsDuration schema types exist but nothing serves them. Requires a
 * legacy machine token.
 *
 * @param string $duration Time period: "7d", "28d", "12w", or "12m".
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return array|\WP_Error Metrics data or WP_Error on failure.
 */
function get_environment_metrics( $duration, $site_id = null, $env = null ) {
	// Validate duration parameter.
	$valid_durations = [ '7d', '28d', '12w', '12m' ];
	if ( ! in_array( $duration, $valid_durations, true ) ) {
		return new \WP_Error(
			'invalid_duration',
			sprintf(
				/* translators: %s: comma-separated list of valid durations */
				__( 'Invalid duration. Must be one of: %s', 'ash-nazg' ),
				implode( ', ', $valid_durations )
			)
		);
	}

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

	$endpoint = sprintf( '/v0/sites/%s/environments/%s/metrics?duration=%s', $site_id, $api_env, $duration );
	$cache_key = sprintf( 'ash_nazg_metrics_%s_%s_%s', $site_id, $api_env, $duration );

	// Cache for 1 hour (metrics update periodically).
	$metrics = get_cached_endpoint( $endpoint, $cache_key, HOUR_IN_SECONDS, true );

	if ( is_wp_error( $metrics ) ) {
		Helpers\debug_log( sprintf( 'Failed to get metrics for %s.%s (%s) - Error: %s', $site_id, $api_env, $duration, $metrics->get_error_message() ) );
		return $metrics;
	}

	Helpers\debug_log( sprintf( 'Retrieved metrics for %s.%s (%s)', $site_id, $api_env, $duration ) );

	return $metrics;
}

/**
 * Clear cached metrics data for an environment.
 *
 * Clears all duration caches for the specified environment.
 *
 * @param string $site_id Optional. Site UUID. Auto-detected if not provided.
 * @param string $env Optional. Environment name. Auto-detected if not provided.
 * @return void
 */
function clear_metrics_cache( $site_id = null, $env = null ) {
	$site_id = Helpers\ensure_site_id( $site_id );
	if ( is_wp_error( $site_id ) ) {
		return;
	}

	$env = Helpers\ensure_environment( $env );
	if ( is_wp_error( $env ) ) {
		return;
	}

	// Map local environments to dev for API queries.
	$api_env = map_local_env_to_dev( $env );

	// Clear all duration caches for this environment.
	$durations = [ '7d', '28d', '12w', '12m' ];
	foreach ( $durations as $duration ) {
		$cache_key = sprintf( 'ash_nazg_metrics_%s_%s_%s', $site_id, $api_env, $duration );
		delete_transient( $cache_key );
	}

	Helpers\debug_log( sprintf( 'Cleared metrics cache for %s.%s', $site_id, $api_env ) );
}
