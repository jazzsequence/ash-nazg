<?php
/**
 * Addons API tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test addon status detection via live environment variables.
 */
class Test_Addons_API extends TestCase {

	/**
	 * Test that addon-related functions exist.
	 */
	public function test_addon_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_addon_env_variables' ),
			'get_addon_env_variables function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_site_addons' ),
			'get_site_addons function should exist'
		);
	}

	/**
	 * Test that get_addon_env_variables uses $_ENV on non-local environments.
	 */
	public function test_addon_env_variables_reads_env_on_pantheon() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/function get_addon_env_variables.*is_local_environment.*\$_ENV/s',
			$api_contents,
			'get_addon_env_variables should read $_ENV when not on a local environment'
		);
	}

	/**
	 * Test that get_addon_env_variables calls the Terminus internal API for local envs.
	 */
	public function test_addon_env_variables_uses_terminus_api_locally() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertStringContainsString(
			'terminus.pantheon.io/api/sites',
			$api_contents,
			'get_addon_env_variables should call Terminus internal API for local environments'
		);
		$this->assertStringContainsString(
			'/environments/%s/variables',
			$api_contents,
			'Terminus variables endpoint path should include environments and variables segments'
		);
	}

	/**
	 * Test that get_site_addons uses CACHE_BINDING_ID to determine Redis status.
	 */
	public function test_get_site_addons_uses_cache_binding_id_for_redis() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertStringContainsString(
			'CACHE_BINDING_ID',
			$api_contents,
			'get_site_addons should use CACHE_BINDING_ID to determine Redis status'
		);
	}

	/**
	 * Test that get_site_addons uses PANTHEON_SEARCH_VERSION to determine Solr status.
	 */
	public function test_get_site_addons_uses_search_version_for_solr() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertStringContainsString(
			'PANTHEON_SEARCH_VERSION',
			$api_contents,
			'get_site_addons should use PANTHEON_SEARCH_VERSION to determine Solr status'
		);
	}

	/**
	 * Test that addon status uses a short cache TTL appropriate for live state.
	 */
	public function test_addon_env_variables_uses_short_cache_ttl() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/get_addon_env_variables.*HOUR_IN_SECONDS|ash_nazg_addon_env_vars.*HOUR_IN_SECONDS/s',
			$api_contents,
			'Addon env variables should be cached with a short TTL (HOUR_IN_SECONDS)'
		);
	}

	/**
	 * Test that Terminus variables endpoint uses correct auth header.
	 */
	public function test_terminus_variables_uses_bearer_auth() {
		$api_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/terminus\.pantheon\.io.*Authorization.*Bearer|Authorization.*Bearer.*terminus\.pantheon\.io/s',
			$api_contents,
			'Terminus variables endpoint should use Bearer token authentication'
		);
	}
}
