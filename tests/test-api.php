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
			function_exists( 'Pantheon\AshNazg\API\get_machine_token' ),
			'get_machine_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\api_request' ),
			'api_request function should exist'
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
	 * Verifies the code uses the correct API version (v0).
	 */
	public function test_api_endpoint_paths() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		// Read the API file and check for correct endpoint paths.
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use v0 endpoints, not v1.
		$this->assertStringContainsString(
			'/v0/authorize/machine-token',
			$api_file_contents,
			'Should use v0 authorization endpoint'
		);
		$this->assertStringContainsString(
			'/v0/sites',
			$api_file_contents,
			'Should use v0 sites endpoint'
		);

		// Should not use v1 endpoints.
		$this->assertStringNotContainsString(
			'/v1/sites',
			$api_file_contents,
			'Should not use deprecated v1 sites endpoint'
		);
	}

	/**
	 * Test client parameter is included in authentication.
	 *
	 * The Pantheon API requires a 'client' field in authentication requests.
	 */
	public function test_client_parameter_in_auth() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should include client parameter in auth request.
		$this->assertStringContainsString(
			"'client'",
			$api_file_contents,
			'Authentication should include client parameter'
		);
		$this->assertStringContainsString(
			'ash-nazg',
			$api_file_contents,
			'Client should be identified as ash-nazg'
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
			'api_unavailable'        => '503/502 errors',
			'invalid_token'          => '401/403 errors',
			'bad_request'            => '400 errors',
			'no_token'               => 'Missing machine token',
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
		$status_codes = array( 200, 400, 401, 403, 502, 503 );

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
		$this->assertStringContainsString(
			'/v0/sites/%s/environments',
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
}
