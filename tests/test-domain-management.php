<?php
/**
 * Domain Management tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Domain Management functions.
 */
class Test_Domain_Management extends TestCase {

	/**
	 * Test that get_domains function exists.
	 */
	public function test_get_domains_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\API\\get_domains' ),
			'get_domains function should exist'
		);
	}

	/**
	 * Test that add_domain function exists.
	 */
	public function test_add_domain_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\API\\add_domain' ),
			'add_domain function should exist'
		);
	}

	/**
	 * Test that delete_domain function exists.
	 */
	public function test_delete_domain_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\API\\delete_domain' ),
			'delete_domain function should exist'
		);
	}

	/**
	 * Test get_domains uses correct endpoint.
	 */
	public function test_get_domains_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use correct GET endpoint.
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/domains',
			$api_file_contents,
			'Should use domains endpoint'
		);

		// Check get_domains function uses ensure_site_id.
		$get_domains_pos = strpos( $api_file_contents, 'function get_domains' );
		$this->assertNotFalse( $get_domains_pos, 'Should find get_domains function' );

		$func_content = substr( $api_file_contents, $get_domains_pos, 800 );
		$this->assertStringContainsString(
			'Helpers\\ensure_site_id',
			$func_content,
			'get_domains should use ensure_site_id helper'
		);

		$this->assertStringContainsString(
			'Helpers\\ensure_environment',
			$func_content,
			'get_domains should use ensure_environment helper'
		);
	}

	/**
	 * Test add_domain uses correct endpoint and method.
	 */
	public function test_add_domain_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$add_domain_pos = strpos( $api_file_contents, 'function add_domain' );
		$this->assertNotFalse( $add_domain_pos, 'Should find add_domain function' );

		$func_content = substr( $api_file_contents, $add_domain_pos, 1000 );

		// Should use POST method.
		$this->assertStringContainsString(
			"'POST'",
			$func_content,
			'Should use POST HTTP method'
		);

		// Should have domain in request body.
		$this->assertStringContainsString(
			"'domain'",
			$func_content,
			'Should include domain in request body'
		);

		// Should validate domain parameter.
		$this->assertStringContainsString(
			'empty( $domain )',
			$func_content,
			'Should validate domain is not empty'
		);

		// Should use ensure_site_id.
		$this->assertStringContainsString(
			'Helpers\\ensure_site_id',
			$func_content,
			'Should use ensure_site_id helper'
		);
	}

	/**
	 * Test add_domain has debug logging.
	 */
	public function test_add_domain_debug_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$add_domain_pos = strpos( $api_file_contents, 'function add_domain' );
		$func_content = substr( $api_file_contents, $add_domain_pos, 1000 );

		// Should log domain addition.
		$this->assertStringContainsString(
			'Adding domain',
			$func_content,
			'Should log domain addition attempt'
		);

		// Should log success.
		$this->assertStringContainsString(
			'successfully added',
			$func_content,
			'Should log successful addition'
		);
	}

	/**
	 * Test delete_domain uses correct endpoint and method.
	 */
	public function test_delete_domain_endpoint() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$delete_domain_pos = strpos( $api_file_contents, 'function delete_domain' );
		$this->assertNotFalse( $delete_domain_pos, 'Should find delete_domain function' );

		$func_content = substr( $api_file_contents, $delete_domain_pos, 1000 );

		// Should use DELETE method.
		$this->assertStringContainsString(
			"'DELETE'",
			$func_content,
			'Should use DELETE HTTP method'
		);

		// Should validate domain parameter.
		$this->assertStringContainsString(
			'empty( $domain )',
			$func_content,
			'Should validate domain is not empty'
		);

		// Should include domain in endpoint path.
		$this->assertStringContainsString(
			'/domains/%s',
			$func_content,
			'Should include domain in endpoint path'
		);
	}

	/**
	 * Test multisite integration file exists.
	 */
	public function test_multisite_file_exists() {
		$multisite_file = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php';
		$this->assertFileExists( $multisite_file, 'multisite.php file should exist' );
	}

	/**
	 * Test multisite integration functions exist.
	 */
	public function test_multisite_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\Multisite\\init' ),
			'Multisite init function should exist'
		);

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\Multisite\\on_new_site' ),
			'on_new_site function should exist'
		);

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\Multisite\\on_new_site_legacy' ),
			'on_new_site_legacy function should exist'
		);

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\Multisite\\add_domain_to_pantheon' ),
			'add_domain_to_pantheon function should exist'
		);
	}

	/**
	 * Test multisite hooks are registered.
	 */
	public function test_multisite_hooks_registered() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php';

		$multisite_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php' );

		// Should hook into wp_initialize_site.
		$this->assertStringContainsString(
			"'wp_initialize_site'",
			$multisite_file,
			'Should register wp_initialize_site hook'
		);

		// Should hook into wpmu_new_blog for legacy support.
		$this->assertStringContainsString(
			"'wpmu_new_blog'",
			$multisite_file,
			'Should register wpmu_new_blog hook for legacy support'
		);

		// Should check for is_multisite.
		$this->assertStringContainsString(
			'is_multisite()',
			$multisite_file,
			'Should check if multisite is enabled'
		);
	}

	/**
	 * Test multisite skips local environments.
	 */
	public function test_multisite_skips_local() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php';

		$multisite_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php' );

		// Should check is_local_environment.
		$this->assertStringContainsString(
			'is_local_environment()',
			$multisite_file,
			'Should check for local environment'
		);

		// Should skip domain addition on local.
		$this->assertStringContainsString(
			'Skipping domain addition',
			$multisite_file,
			'Should skip domain addition on local'
		);
	}

	/**
	 * Test multisite stores results in transients.
	 */
	public function test_multisite_uses_transients() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php';

		$multisite_file = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/multisite.php' );

		// Should use transients for success.
		$this->assertStringContainsString(
			'ash_nazg_domain_add_success_',
			$multisite_file,
			'Should store success in transient'
		);

		// Should use transients for errors.
		$this->assertStringContainsString(
			'ash_nazg_domain_add_error_',
			$multisite_file,
			'Should store errors in transient'
		);
	}

	/**
	 * Test multisite bootstrap integration.
	 */
	public function test_multisite_bootstrap_integration() {
		$main_file = file_get_contents( ASH_NAZG_PLUGIN_FILE );

		// Should require multisite.php.
		$this->assertStringContainsString(
			"require_once ASH_NAZG_PLUGIN_DIR . 'includes/multisite.php'",
			$main_file,
			'Should load multisite.php in bootstrap'
		);

		// Should initialize multisite.
		$this->assertStringContainsString(
			'Multisite\\init()',
			$main_file,
			'Should call Multisite init in bootstrap'
		);
	}
}
