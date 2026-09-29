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
	 *
	 * Checks the source directly rather than require_once-ing the plugin
	 * file: ash-nazg.php guards itself with `if ( ! defined( 'ABSPATH' ) )
	 * { exit; }` and calls bootstrap() (which calls real WP functions like
	 * add_action()) at the bottom, neither of which this lightweight test
	 * bootstrap can satisfy - requiring it would either silently kill the
	 * whole PHPUnit process (no ABSPATH) or fatal on undefined WP
	 * functions (if ABSPATH were defined), unlike every other test in this
	 * suite, which is why they require individual includes/*.php files.
	 */
	public function test_plugin_constants() {
		$contents = file_get_contents( ASH_NAZG_PLUGIN_FILE );

		$this->assertStringContainsString( "define( 'ASH_NAZG_VERSION'", $contents, 'ASH_NAZG_VERSION constant should be defined' );
		$this->assertStringContainsString( "define( 'ASH_NAZG_PLUGIN_FILE'", $contents, 'ASH_NAZG_PLUGIN_FILE constant should be defined' );
		$this->assertStringContainsString( "define( 'ASH_NAZG_PLUGIN_DIR'", $contents, 'ASH_NAZG_PLUGIN_DIR constant should be defined' );
		$this->assertStringContainsString( "define( 'ASH_NAZG_PLUGIN_URL'", $contents, 'ASH_NAZG_PLUGIN_URL constant should be defined' );
	}

	/**
	 * Test that the plugin header version and the ASH_NAZG_VERSION constant match.
	 *
	 * Regression test for exactly the release mistake CLAUDE.md's Release
	 * Process section warns about: bumping one and forgetting the other
	 * breaks WordPress's update detection and CSS cache-busting.
	 */
	public function test_plugin_version() {
		$contents = file_get_contents( ASH_NAZG_PLUGIN_FILE );

		preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $contents, $header_match );
		preg_match( "/define\( 'ASH_NAZG_VERSION', '([0-9.]+)' \);/", $contents, $const_match );

		$this->assertNotEmpty( $header_match[1] ?? '', 'Should find a Version header in the plugin file' );
		$this->assertNotEmpty( $const_match[1] ?? '', 'Should find the ASH_NAZG_VERSION constant definition' );
		$this->assertSame(
			$header_match[1],
			$const_match[1],
			'Plugin header Version and ASH_NAZG_VERSION constant must match'
		);
	}

	/**
	 * Test that bootstrap function exists.
	 */
	public function test_bootstrap_function_exists() {
		$contents = file_get_contents( ASH_NAZG_PLUGIN_FILE );

		$this->assertStringContainsString( 'function bootstrap()', $contents, 'Bootstrap function should be defined' );
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
