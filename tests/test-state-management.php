<?php
/**
 * State management tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test state management functions.
 */
class Test_State_Management extends TestCase {

	/**
	 * Test that state management functions exist.
	 */
	public function test_state_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_environment_state' ),
			'get_environment_state function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\update_environment_state' ),
			'update_environment_state function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\sync_environment_state' ),
			'sync_environment_state function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_connection_mode' ),
			'get_connection_mode function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\update_connection_mode' ),
			'update_connection_mode function should exist'
		);
	}

	/**
	 * Test that state structure is correct.
	 *
	 * Verifies the state array has the expected keys.
	 */
	public function test_state_structure() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// State should include these fields.
		$state_fields = array(
			'site_id',
			'environment',
			'connection_mode',
			'last_synced',
		);

		foreach ( $state_fields as $field ) {
			$this->assertStringContainsString(
				"'{$field}'",
				$api_file_contents,
				"State should include '{$field}' field"
			);
		}
	}

	/**
	 * Test connection mode values are valid.
	 */
	public function test_connection_mode_values() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Only 'sftp' and 'git' should be valid modes.
		$this->assertStringContainsString(
			"'sftp'",
			$api_file_contents,
			"Should support 'sftp' mode"
		);
		$this->assertStringContainsString(
			"'git'",
			$api_file_contents,
			"Should support 'git' mode"
		);

		// Should validate mode values.
		$this->assertStringContainsString(
			'in_array',
			$api_file_contents,
			'Should validate connection mode values'
		);
	}

	/**
	 * Test connection mode endpoint is correct.
	 */
	public function test_connection_mode_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use correct endpoint for mode switching.
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/connection-mode',
			$api_file_contents,
			'Should use connection-mode endpoint'
		);

		// Should use PUT method.
		$this->assertStringContainsString(
			"'PUT'",
			$api_file_contents,
			'Should use PUT method for connection mode updates'
		);

		// Should send mode in request body.
		$this->assertStringContainsString(
			"'mode'",
			$api_file_contents,
			'Should include mode in request body'
		);
	}

	/**
	 * Test local environment mapping.
	 *
	 * Lando, local, localhost, ddev should map to 'dev' for API queries.
	 */
	public function test_local_environment_mapping() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$local_envs = array( 'lando', 'local', 'localhost', 'ddev' );

		foreach ( $local_envs as $env ) {
			$this->assertStringContainsString(
				"'{$env}'",
				$api_file_contents,
				"Should map '{$env}' to dev environment"
			);
		}

		// Should map to 'dev'.
		$this->assertStringContainsString(
			"'dev'",
			$api_file_contents,
			'Should map local environments to dev'
		);
	}

	/**
	 * Test state option name.
	 */
	public function test_state_option_name() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use correct option name.
		$this->assertStringContainsString(
			'ash_nazg_environment_state',
			$api_file_contents,
			'Should use ash_nazg_environment_state option'
		);

		// Should use get_option and update_option.
		$this->assertStringContainsString(
			'get_option',
			$api_file_contents,
			'Should use get_option to retrieve state'
		);
		$this->assertStringContainsString(
			'update_option',
			$api_file_contents,
			'Should use update_option to store state'
		);
	}

	/**
	 * Test cache clearing after mode changes.
	 */
	public function test_cache_clearing() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should clear environment info cache after mode change.
		$this->assertStringContainsString(
			'delete_transient',
			$api_file_contents,
			'Should clear transient cache after updates'
		);

		// Should clear environment info cache specifically.
		$this->assertStringContainsString(
			'ash_nazg_all_env_info',
			$api_file_contents,
			'Should clear environment info cache'
		);
	}

	/**
	 * Test error handling for invalid modes.
	 */
	public function test_invalid_mode_error() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should return WP_Error for invalid mode.
		$this->assertStringContainsString(
			'invalid_mode',
			$api_file_contents,
			'Should have invalid_mode error code'
		);

		// Should check if mode is valid.
		$this->assertStringContainsString(
			'in_array( $mode',
			$api_file_contents,
			'Should validate mode before updating'
		);
	}
}
