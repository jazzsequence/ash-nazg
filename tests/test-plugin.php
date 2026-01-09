<?php
/**
 * Basic plugin tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test plugin initialization.
 */
class Test_Plugin extends TestCase {

	/**
	 * Test that plugin constants are defined.
	 */
	public function test_plugin_constants() {
		require_once ASH_NAZG_PLUGIN_FILE;

		$this->assertTrue( defined( 'ASH_NAZG_VERSION' ), 'ASH_NAZG_VERSION constant should be defined' );
		$this->assertTrue( defined( 'ASH_NAZG_PLUGIN_FILE' ), 'ASH_NAZG_PLUGIN_FILE constant should be defined' );
		$this->assertTrue( defined( 'ASH_NAZG_PLUGIN_DIR' ), 'ASH_NAZG_PLUGIN_DIR constant should be defined' );
		$this->assertTrue( defined( 'ASH_NAZG_PLUGIN_URL' ), 'ASH_NAZG_PLUGIN_URL constant should be defined' );
	}

	/**
	 * Test that plugin version is correct.
	 */
	public function test_plugin_version() {
		require_once ASH_NAZG_PLUGIN_FILE;

		$this->assertEquals( '0.1.0', ASH_NAZG_VERSION );
	}

	/**
	 * Test that bootstrap function exists.
	 */
	public function test_bootstrap_function_exists() {
		require_once ASH_NAZG_PLUGIN_FILE;

		$this->assertTrue( function_exists( 'Pantheon\AshNazg\bootstrap' ), 'Bootstrap function should exist' );
	}

	/**
	 * Test that API functions are loaded.
	 */
	public function test_api_functions_exist() {
		// Load the API file directly since we're not in WordPress context.
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue( function_exists( 'Pantheon\AshNazg\API\get_api_token' ), 'get_api_token function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\API\get_machine_token' ), 'get_machine_token function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\API\api_request' ), 'api_request function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\API\get_site_info' ), 'get_site_info function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\API\get_environment_info' ), 'get_environment_info function should exist' );
	}

	/**
	 * Test that admin functions are loaded.
	 */
	public function test_admin_functions_exist() {
		// Load the admin file directly since we're not in WordPress context.
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Admin\init' ), 'Admin init function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Admin\add_admin_menu' ), 'add_admin_menu function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Admin\render_dashboard_page' ), 'render_dashboard_page function should exist' );
	}

	/**
	 * Test that settings functions are loaded.
	 */
	public function test_settings_functions_exist() {
		// Load the settings file directly since we're not in WordPress context.
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/settings.php';

		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Settings\init' ), 'Settings init function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Settings\register_settings' ), 'register_settings function should exist' );
		$this->assertTrue( function_exists( 'Pantheon\AshNazg\Settings\render_settings_page' ), 'render_settings_page function should exist' );
	}
}
