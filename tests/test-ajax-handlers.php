<?php
/**
 * AJAX handler tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test AJAX handler functions.
 */
class Test_AJAX_Handlers extends TestCase {

	/**
	 * Test that AJAX handler functions exist.
	 */
	public function test_ajax_handlers_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_fetch_logs' ),
			'ajax_fetch_logs function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_clear_logs' ),
			'ajax_clear_logs function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_toggle_connection_mode' ),
			'ajax_toggle_connection_mode function should exist'
		);
	}

	/**
	 * Test AJAX actions are registered.
	 */
	public function test_ajax_actions_registered() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		$ajax_actions = array(
			'wp_ajax_ash_nazg_fetch_logs',
			'wp_ajax_ash_nazg_clear_logs',
			'wp_ajax_ash_nazg_toggle_connection_mode',
		);

		foreach ( $ajax_actions as $action ) {
			$this->assertStringContainsString(
				"'{$action}'",
				$admin_file_contents,
				"Should register {$action} action"
			);
		}
	}

	/**
	 * Test that AJAX handlers check nonces.
	 */
	public function test_ajax_nonce_verification() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use check_ajax_referer in AJAX handlers.
		$this->assertGreaterThan(
			2,
			substr_count( $admin_file_contents, 'check_ajax_referer' ),
			'Should verify nonce in all AJAX handlers'
		);

		// Should check for specific nonces.
		$nonces = array(
			'ash_nazg_fetch_logs',
			'ash_nazg_clear_logs',
			'ash_nazg_toggle_connection_mode',
		);

		foreach ( $nonces as $nonce ) {
			$this->assertStringContainsString(
				"'{$nonce}'",
				$admin_file_contents,
				"Should verify {$nonce} nonce"
			);
		}
	}

	/**
	 * Test that AJAX handlers check capabilities.
	 */
	public function test_ajax_capability_checks() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should check manage_options capability.
		$this->assertGreaterThan(
			2,
			substr_count( $admin_file_contents, 'current_user_can( \'manage_options\' )' ),
			'Should check manage_options capability in AJAX handlers'
		);

		// Should deny access without capability.
		$this->assertStringContainsString(
			'wp_send_json_error',
			$admin_file_contents,
			'Should return error for unauthorized users'
		);
	}

	/**
	 * Test fetch logs handler switches modes.
	 */
	public function test_fetch_logs_mode_switching() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should check original mode.
		$this->assertStringContainsString(
			'get_connection_mode',
			$admin_file_contents,
			'Should get current connection mode'
		);

		// Should switch to SFTP if in Git mode.
		$this->assertStringContainsString(
			"'git' === \$original_mode",
			$admin_file_contents,
			'Should check if in Git mode'
		);

		// Should switch back after operation.
		$this->assertStringContainsString(
			'$switched_mode',
			$admin_file_contents,
			'Should track whether mode was switched'
		);
	}

	/**
	 * Test clear logs handler deletes file.
	 */
	public function test_clear_logs_deletes_file() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use unlink to delete file.
		$this->assertStringContainsString(
			'unlink',
			$admin_file_contents,
			'Should delete debug.log file'
		);

		// Should verify deletion.
		$this->assertStringContainsString(
			'file_exists( $log_path )',
			$admin_file_contents,
			'Should verify file was deleted'
		);

		// Should update transient with empty logs.
		$this->assertStringContainsString(
			"set_transient( 'ash_nazg_debug_logs', ''",
			$admin_file_contents,
			'Should store empty logs in transient'
		);
	}

	/**
	 * Test toggle connection mode handler verifies mode changed.
	 */
	public function test_toggle_mode_verification() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should return workflow ID for client-side polling.
		$this->assertStringContainsString(
			'workflow_id',
			$admin_file_contents,
			'Should return workflow_id in AJAX response'
		);

		// Should NOT do server-side polling (client polls instead).
		$this->assertStringNotContainsString(
			'$expected_on_server_dev',
			$admin_file_contents,
			'Should not verify mode server-side (client polls workflow instead)'
		);

		// AJAX handler should exist.
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_toggle_connection_mode' ),
			'ajax_toggle_connection_mode handler should exist'
		);
	}

	/**
	 * Test JavaScript files are enqueued.
	 */
	public function test_javascript_enqueued() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should enqueue dashboard.js.
		$this->assertStringContainsString(
			'ash-nazg-dashboard',
			$admin_file_contents,
			'Should enqueue dashboard JavaScript'
		);

		// Should enqueue logs.js.
		$this->assertStringContainsString(
			'ash-nazg-logs',
			$admin_file_contents,
			'Should enqueue logs JavaScript'
		);

		// Should use wp_localize_script.
		$this->assertStringContainsString(
			'wp_localize_script',
			$admin_file_contents,
			'Should localize scripts with data'
		);

		// Should pass nonces to JavaScript.
		$this->assertStringContainsString(
			'toggleModeNonce',
			$admin_file_contents,
			'Should pass toggle mode nonce'
		);
		$this->assertStringContainsString(
			'fetchLogsNonce',
			$admin_file_contents,
			'Should pass fetch logs nonce'
		);
		$this->assertStringContainsString(
			'clearLogsNonce',
			$admin_file_contents,
			'Should pass clear logs nonce'
		);
	}

	/**
	 * Test proper error logging.
	 */
	public function test_error_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use debug_log helper for debugging.
		$this->assertGreaterThan(
			5,
			substr_count( $admin_file_contents, 'debug_log' ),
			'Should log operations for debugging'
		);

		// Should log AJAX operations.
		$this->assertStringContainsString(
			'AJAX',
			$admin_file_contents,
			'Should log AJAX operations'
		);
	}

	/**
	 * Test that dashboard screen options hook is registered.
	 */
	public function test_dashboard_screen_options_registered() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString(
			'load-toplevel_page_ash-nazg',
			$admin_file_contents,
			'Dashboard screen options hook should be registered on load-toplevel_page_ash-nazg'
		);
		$this->assertStringContainsString(
			'dashboard_screen_options',
			$admin_file_contents,
			'dashboard_screen_options function should be referenced in hooks'
		);
	}

	/**
	 * Test that screen_settings filter is registered for the dashboard.
	 */
	public function test_dashboard_screen_settings_filter_exists() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString(
			'dashboard_screen_settings',
			$admin_file_contents,
			'dashboard_screen_settings filter function should exist'
		);
	}

	/**
	 * Test that set_screen_option handles endpoint visibility options.
	 */
	public function test_set_screen_option_handles_endpoint_visibility() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertMatchesRegularExpression(
			'/function set_screen_option.*ash_nazg_dashboard_show_endpoints/s',
			$admin_file_contents,
			'set_screen_option should handle ash_nazg_dashboard_show_endpoints option'
		);
		$this->assertMatchesRegularExpression(
			'/function set_screen_option.*ash_nazg_dashboard_hidden_groups/s',
			$admin_file_contents,
			'set_screen_option should handle ash_nazg_dashboard_hidden_groups option'
		);
	}

	/**
	 * Test AJAX handlers for org selection and machine token copy are registered.
	 */
	public function test_org_and_token_ajax_handlers_exist() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_save_primary_org',
			$admin_file_contents,
			'save_primary_org AJAX action should be registered'
		);
		$this->assertStringContainsString(
			'ash_nazg_copy_machine_token',
			$admin_file_contents,
			'copy_machine_token AJAX action should be registered'
		);
	}

	/**
	 * Test that machine token copy handler has nonce and capability checks.
	 */
	public function test_copy_machine_token_security() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function ajax_copy_machine_token.*check_ajax_referer/s',
			$admin_file_contents,
			'ajax_copy_machine_token must verify nonce'
		);
		$this->assertMatchesRegularExpression(
			'/function ajax_copy_machine_token.*current_user_can/s',
			$admin_file_contents,
			'ajax_copy_machine_token must check capabilities'
		);
	}

	/**
	 * Test that primary org handler has nonce and capability checks.
	 */
	public function test_save_primary_org_security() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function ajax_save_primary_org.*check_ajax_referer/s',
			$admin_file_contents,
			'ajax_save_primary_org must verify nonce'
		);
		$this->assertMatchesRegularExpression(
			'/function ajax_save_primary_org.*current_user_can/s',
			$admin_file_contents,
			'ajax_save_primary_org must check capabilities'
		);
	}
}
