<?php
/**
 * Delete Site API tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Delete Site API functions.
 *
 * Note: These are integration-style tests that verify the code structure
 * and logic. Full API testing requires WordPress test environment.
 */
class Test_Delete_Site_API extends TestCase {

	/**
	 * Test that delete_site API function exists.
	 */
	public function test_delete_site_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\API\\delete_site' ),
			'delete_site function should exist'
		);
	}

	/**
	 * Test delete_site API endpoint path.
	 */
	public function test_delete_site_endpoint_path() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use correct DELETE endpoint.
		$this->assertStringContainsString(
			'/v0/sites/%s',
			$api_file_contents,
			'Should use site deletion endpoint'
		);

		// Should use DELETE method.
		$delete_site_pos = strpos( $api_file_contents, 'function delete_site' );
		$this->assertNotFalse( $delete_site_pos, 'Should find delete_site function' );

		$func_content = substr( $api_file_contents, $delete_site_pos, 1000 );
		$this->assertStringContainsString(
			"'DELETE'",
			$func_content,
			'Should use DELETE HTTP method'
		);
	}

	/**
	 * Test delete_site uses ensure_site_id helper.
	 */
	public function test_delete_site_uses_ensure_site_id() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/helpers.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$delete_site_pos = strpos( $api_file_contents, 'function delete_site' );
		$this->assertNotFalse( $delete_site_pos, 'Should find delete_site function' );

		$func_content = substr( $api_file_contents, $delete_site_pos, 1000 );

		// Should use ensure_site_id() helper for validation.
		$this->assertStringContainsString(
			'Helpers\\ensure_site_id',
			$func_content,
			'delete_site should use ensure_site_id() helper'
		);
	}

	/**
	 * Test delete_site clears caches.
	 */
	public function test_delete_site_clears_caches() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$delete_site_pos = strpos( $api_file_contents, 'function delete_site' );
		$this->assertNotFalse( $delete_site_pos, 'Should find delete_site function' );

		$func_content = substr( $api_file_contents, $delete_site_pos, 1000 );

		// Should clear site info cache.
		$this->assertStringContainsString(
			'ash_nazg_site_info_',
			$func_content,
			'Should clear site info cache'
		);

		// Should clear environment info cache.
		$this->assertStringContainsString(
			'ash_nazg_all_env_info_',
			$func_content,
			'Should clear environment info cache'
		);
	}

	/**
	 * Test delete_site has debug logging.
	 */
	public function test_delete_site_debug_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$delete_site_pos = strpos( $api_file_contents, 'function delete_site' );
		$this->assertNotFalse( $delete_site_pos, 'Should find delete_site function' );

		$func_content = substr( $api_file_contents, $delete_site_pos, 1000 );

		// Should log critical deletion attempt.
		$this->assertStringContainsString(
			'CRITICAL',
			$func_content,
			'Should log deletion as CRITICAL'
		);

		// Should log on success.
		$this->assertStringContainsString(
			'successfully deleted',
			$func_content,
			'Should log successful deletion'
		);
	}

	/**
	 * Test AJAX handler function exists.
	 */
	public function test_ajax_delete_site_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\\AshNazg\\Admin\\ajax_delete_site' ),
			'ajax_delete_site function should exist'
		);
	}

	/**
	 * Test AJAX handler has security checks.
	 */
	public function test_ajax_handler_security() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		$ajax_pos = strpos( $admin_file_contents, 'function ajax_delete_site' );
		$this->assertNotFalse( $ajax_pos, 'Should find ajax_delete_site function' );

		$func_content = substr( $admin_file_contents, $ajax_pos, 1500 );

		// Should check nonce.
		$this->assertStringContainsString(
			'check_ajax_referer',
			$func_content,
			'Should verify nonce'
		);

		$this->assertStringContainsString(
			'ash_nazg_delete_site',
			$func_content,
			'Should use correct nonce action'
		);

		// Should check capabilities.
		$this->assertStringContainsString(
			'manage_options',
			$func_content,
			'Should check manage_options capability'
		);
	}

	/**
	 * Test AJAX handler validates confirmation text.
	 */
	public function test_ajax_handler_validates_confirmation() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		$ajax_pos = strpos( $admin_file_contents, 'function ajax_delete_site' );
		$this->assertNotFalse( $ajax_pos, 'Should find ajax_delete_site function' );

		$func_content = substr( $admin_file_contents, $ajax_pos, 1500 );

		// Should verify "DELETE" text.
		$this->assertStringContainsString(
			"'DELETE'",
			$func_content,
			'Should verify user typed DELETE'
		);

		// Should have confirmation error message.
		$this->assertStringContainsString(
			'Confirmation',
			$func_content,
			'Should have confirmation error message'
		);
	}

	/**
	 * Test AJAX handler registered in init.
	 */
	public function test_ajax_handler_registered() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should register AJAX action.
		$this->assertStringContainsString(
			"add_action( 'wp_ajax_ash_nazg_delete_site'",
			$admin_file_contents,
			'Should register wp_ajax_ash_nazg_delete_site action'
		);
	}
}
