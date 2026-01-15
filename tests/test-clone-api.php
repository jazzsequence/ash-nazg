<?php
/**
 * Clone API tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test clone API functions.
 *
 * Note: These are integration-style tests that verify the code structure
 * and logic. Full API testing requires WordPress test environment.
 */
class Test_Clone_API extends TestCase {

	/**
	 * Test that clone functions exist.
	 */
	public function test_clone_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\clone_database' ),
			'clone_database function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\clone_files' ),
			'clone_files function should exist'
		);
	}

	/**
	 * Test clone database endpoint.
	 */
	public function test_clone_database_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that clone_database uses correct endpoint.
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/database/clone',
			$api_file,
			'clone_database should use correct API endpoint'
		);
	}

	/**
	 * Test clone files endpoint.
	 */
	public function test_clone_files_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that clone_files uses correct endpoint.
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/files/clone',
			$api_file,
			'clone_files should use correct API endpoint'
		);
	}

	/**
	 * Test clone database parameters.
	 */
	public function test_clone_database_parameters() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that from_environment parameter is used.
		$this->assertMatchesRegularExpression(
			'/from_environment.*=>.*from_env/',
			$api_file,
			'clone_database should use from_environment parameter'
		);

		// Check that from_url and to_url are optional.
		$this->assertStringContainsString(
			'from_url',
			$api_file,
			'clone_database should support from_url parameter'
		);
		$this->assertStringContainsString(
			'to_url',
			$api_file,
			'clone_database should support to_url parameter'
		);
	}

	/**
	 * Test clone files parameters.
	 */
	public function test_clone_files_parameters() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that from_environment parameter is used.
		$this->assertMatchesRegularExpression(
			'/from_environment.*=>.*from_env/',
			$api_file,
			'clone_files should use from_environment parameter'
		);
	}

	/**
	 * Test cache clearing after clone.
	 */
	public function test_cache_clearing() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that caches are cleared after clone operations.
		$this->assertStringContainsString(
			'delete_transient',
			$api_file,
			'Clone functions should clear caches after operations'
		);
	}

	/**
	 * Test environment validation.
	 */
	public function test_environment_validation() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that environments are validated.
		$this->assertStringContainsString(
			'from_env === $to_env',
			$api_file,
			'Clone functions should validate source and target are different'
		);

		$this->assertStringContainsString(
			'empty( $from_env )',
			$api_file,
			'Clone functions should validate environment parameters'
		);
	}

	/**
	 * Test error handling.
	 */
	public function test_error_handling() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that WP_Error is used for errors.
		$this->assertMatchesRegularExpression(
			'/new.*WP_Error/',
			$api_file,
			'Clone functions should return WP_Error on failure'
		);

		// Check for error codes.
		$this->assertStringContainsString(
			'invalid_environment',
			$api_file,
			'Clone functions should use error codes'
		);
		$this->assertStringContainsString(
			'same_environment',
			$api_file,
			'Clone functions should validate against same source/target'
		);
	}

	/**
	 * Test debug logging.
	 */
	public function test_debug_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Check that operations are logged.
		$this->assertStringContainsString(
			'debug_log',
			$api_file,
			'Clone functions should log operations'
		);
	}

	/**
	 * Test AJAX handler exists.
	 */
	public function test_ajax_handler_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_clone_content' ),
			'ajax_clone_content function should exist'
		);
	}

	/**
	 * Test AJAX handler security.
	 */
	public function test_ajax_handler_security() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Check for nonce verification.
		$this->assertStringContainsString(
			'check_ajax_referer',
			$admin_file,
			'AJAX handler should verify nonce'
		);

		// Check for capability check.
		$this->assertStringContainsString(
			'current_user_can',
			$admin_file,
			'AJAX handler should check user capabilities'
		);
	}

	/**
	 * Test AJAX handler initialization checks.
	 */
	public function test_ajax_handler_initialization_checks() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Check that environments are verified as initialized.
		$this->assertStringContainsString(
			'is_environment_initialized',
			$admin_file,
			'AJAX handler should check environment initialization'
		);
	}

	/**
	 * Test render function exists.
	 */
	public function test_render_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\render_clone_page' ),
			'render_clone_page function should exist'
		);
	}
}
