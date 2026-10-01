<?php
/**
 * API tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test API functions.
 *
 * Note: These are integration-style tests that verify the code structure
 * and logic. Full API testing requires WordPress test environment.
 */
class Test_API extends TestCase {

	/**
	 * Test that API functions exist.
	 */
	public function test_api_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_api_token' ),
			'get_api_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_access_token' ),
			'get_access_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\api_request' ),
			'api_request function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\api_request_v0' ),
			'api_request_v0 function should exist (legacy fallback)'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_site_info' ),
			'get_site_info function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_environment_info' ),
			'get_environment_info function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\test_connection' ),
			'test_connection function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\clear_cache' ),
			'clear_cache function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_current_pantheon_user' ),
			'get_current_pantheon_user function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_user_organizations' ),
			'get_user_organizations function should exist'
		);
	}

	/**
	 * Test that helper functions exist.
	 */
	public function test_helper_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_pantheon_site_id' ),
			'get_pantheon_site_id function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_pantheon_environment' ),
			'get_pantheon_environment function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\is_pantheon' ),
			'is_pantheon function should exist'
		);
	}

	/**
	 * Test Pantheon environment detection.
	 */
	public function test_pantheon_environment_detection() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		// Test without Pantheon environment variables.
		$this->assertFalse(
			\Pantheon\AshNazg\API\is_pantheon(),
			'Should return false when not on Pantheon'
		);
	}

	/**
	 * Test API endpoint paths are correct.
	 *
	 * Verifies the code uses the v1 API as its primary base URL, with the
	 * legacy v0 API reachable only through api_request_v0() for the small
	 * set of hybrid-fallback functions that have no v1 equivalent yet.
	 */
	public function test_api_endpoint_paths() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// api_request() should hit the v1 base URL.
		$this->assertStringContainsString(
			"'https://api.pantheon.io/v1'",
			$api_file_contents,
			'api_request() should use the v1 API base URL'
		);

		// api_request_v0() should still exist for the hybrid-fallback functions.
		$this->assertStringContainsString(
			"'https://api.pantheon.io'",
			$api_file_contents,
			'api_request_v0() should use the legacy v0 API base URL'
		);
		$this->assertStringContainsString(
			'/v0/authorize/machine-token',
			$api_file_contents,
			'Legacy v0 session exchange should still be present for hybrid-fallback functions'
		);

		// Hybrid-fallback functions should still reference their v0 paths.
		$v0_fallback_paths = [
			'/v0/sites/%s/label',
			'/v0/sites/%s',
			'/v0/sites/%s/code-tips',
			'/v0/sites/%s/environments/%s/metrics',
			'/v0/sites/%s/environments/%s/domains',
			'/v0/sites/%s/environments/%s/workflows',
		];
		foreach ( $v0_fallback_paths as $path ) {
			$this->assertStringContainsString(
				$path,
				$api_file_contents,
				"Hybrid-fallback function should still reference v0 path {$path}"
			);
		}
	}

	/**
	 * Test client parameter is included in the legacy v0 authentication exchange.
	 *
	 * The legacy v0 machine-token exchange requires a 'client' field. v1 has
	 * no such exchange (the token is sent directly), so this only applies to
	 * get_v0_session_token(), used by the hybrid-fallback functions.
	 */
	public function test_client_parameter_in_legacy_v0_auth() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertMatchesRegularExpression(
			"/function get_v0_session_token.*'client'.*'ash-nazg'/s",
			$api_file_contents,
			'Legacy v0 exchange should include client parameter'
		);
	}

	/**
	 * Test error handling structure.
	 *
	 * Verifies that proper error codes are used for different scenarios.
	 */
	public function test_error_codes_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check for specific error codes.
		$error_codes = array(
			'authentication_failed'  => '401/403 errors',
			'no_token'               => 'Missing Personal Access Token',
			'no_v0_token'            => 'Missing legacy Machine Token for a hybrid-fallback call',
			'api_connection_failed'  => 'Connection failures',
			'environment_not_found'  => 'Invalid environment',
		);

		foreach ( $error_codes as $code => $description ) {
			$this->assertStringContainsString(
				"'" . $code . "'",
				$api_file_contents,
				"Should have error code '{$code}' for {$description}"
			);
		}
	}

	/**
	 * Test HTTP status code handling.
	 *
	 * Verifies that the code handles different HTTP status codes appropriately.
	 */
	public function test_http_status_code_handling() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should handle these HTTP status codes.
		$status_codes = array( 200, 401, 403 );

		foreach ( $status_codes as $code ) {
			$this->assertStringContainsString(
				(string) $code,
				$api_file_contents,
				"Should handle HTTP {$code} status code"
			);
		}
	}

	/**
	 * Test that environments endpoint is used correctly.
	 *
	 * Since the API doesn't have a single-environment GET endpoint,
	 * we should fetch all environments and extract the specific one.
	 */
	public function test_environments_endpoint_usage() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should fetch all environments (not a single environment endpoint).
		// Live-verified: no pager param needed - the endpoint already
		// returns every environment, and bracket-notation pager[first]=N is
		// invalid (HTTP 400) if ever added back.
		$this->assertStringContainsString(
			"'/sites/%s/environments'",
			$api_file_contents,
			'Should use environments list endpoint'
		);

		// Should handle environment not found case.
		$this->assertStringContainsString(
			'environment_not_found',
			$api_file_contents,
			'Should handle environment not found error'
		);
	}

	/**
	 * Test get_user_id resolves the Pantheon user ID via get_current_pantheon_user().
	 *
	 * v1 has no session token to parse a UUID out of - GET /current-user
	 * returns the ID directly.
	 */
	public function test_get_user_id_uses_current_pantheon_user() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_user_id.*get_current_pantheon_user/s',
			$file_contents,
			'get_user_id should resolve the ID via get_current_pantheon_user()'
		);
	}

	/**
	 * Test that get_user_organizations derives workspaces from get_current_pantheon_user().
	 */
	public function test_get_user_organizations_derives_from_current_user() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_user_organizations.*get_current_pantheon_user.*workspacesConnection/s',
			$file_contents,
			'get_user_organizations should derive workspaces from get_current_pantheon_user()'
		);
	}

	/**
	 * Test that get_current_pantheon_user uses the v1 /current-user endpoint.
	 */
	public function test_get_current_pantheon_user_endpoint() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_current_pantheon_user.*\/current-user/s',
			$file_contents,
			'get_current_pantheon_user should call /current-user'
		);
	}

	/**
	 * Test that get_current_pantheon_user caches its result.
	 */
	public function test_get_current_pantheon_user_caching() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_current_pantheon_user.*get_transient.*set_transient/s',
			$file_contents,
			'get_current_pantheon_user should use WordPress transients for caching'
		);
	}

	/**
	 * Test that get_api_token() (v1) always resolves via the access-token chain.
	 */
	public function test_api_token_uses_access_token_chain() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/function get_api_token.*get_user_access_token/s',
			$file_contents,
			'get_api_token() (v1) should resolve via get_user_access_token()'
		);
	}

	/**
	 * Test that get_v0_session_token() uses only the v1 Personal Access Token.
	 *
	 * Live-verified against a real site: a v1 Personal Access Token works as
	 * a v0 machine_token for the full v0 API surface, so the legacy Machine
	 * Token is no longer used for authentication at all - it's detected
	 * elsewhere only for the cleanup notice (see Test_User_Tokens).
	 */
	public function test_v0_session_token_uses_only_access_token() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$func_pos = strpos( $file_contents, 'function get_v0_session_token' );
		$this->assertNotFalse( $func_pos, 'Should find function get_v0_session_token' );

		$func_content = substr( $file_contents, $func_pos, 1500 );

		$this->assertStringContainsString(
			'get_user_access_token(',
			$func_content,
			'get_v0_session_token() should call get_user_access_token()'
		);
		$this->assertStringNotContainsString(
			'get_user_machine_token',
			$func_content,
			'get_v0_session_token() should no longer fall back to get_user_machine_token()'
		);
	}
}
