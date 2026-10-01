<?php
/**
 * User token tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test per-user token storage and retrieval functions.
 *
 * Note: These are structural tests that verify the code exists and contains
 * required patterns. Full integration testing requires WordPress test environment.
 */
class Test_User_Tokens extends TestCase {

	/**
	 * Test that user token functions exist.
	 */
	public function test_user_token_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\encrypt_token' ),
			'encrypt_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\decrypt_token' ),
			'decrypt_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_user_access_token' ),
			'get_user_access_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\clear_v0_session_token' ),
			'clear_v0_session_token function should exist (legacy v0 fallback only)'
		);
		$this->assertFalse(
			function_exists( 'Pantheon\AshNazg\API\get_user_machine_token' ),
			'get_user_machine_token should not exist - the Machine Token is no longer a usable credential'
		);
		$this->assertFalse(
			function_exists( 'Pantheon\AshNazg\API\get_machine_token' ),
			'get_machine_token should not exist - the Machine Token is no longer a usable credential'
		);
	}

	/**
	 * Test that get_user_access_token checks per-user sources.
	 */
	public function test_get_user_access_token_checks_user_sources() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should check per-user Pantheon Secret.
		$this->assertStringContainsString(
			'ash_nazg_access_token_%d',
			$contents,
			'get_user_access_token should check per-user Pantheon Secret with user ID suffix'
		);

		// Should check per-user meta.
		$this->assertStringContainsString(
			'ash_nazg_user_access_token',
			$contents,
			'get_user_access_token should check per-user meta'
		);

		// Should call decrypt_token for user meta.
		$this->assertStringContainsString(
			'decrypt_token',
			$contents,
			'get_user_access_token should decrypt tokens from user meta'
		);

		// Should fallback to global token.
		$this->assertStringContainsString(
			'get_access_token()',
			$contents,
			'get_user_access_token should fallback to global token for backward compatibility'
		);
	}

	/**
	 * Test that encryption uses WordPress salts.
	 */
	public function test_encryption_uses_wp_salts() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should use wp_salt for encryption key.
		$this->assertStringContainsString(
			"wp_salt( 'auth' )",
			$contents,
			'encrypt_token should use WordPress AUTH_SALT'
		);

		// Should use AES-256-CBC encryption.
		$this->assertStringContainsString(
			'AES-256-CBC',
			$contents,
			'encrypt_token should use AES-256-CBC encryption'
		);

		// Should use openssl_encrypt.
		$this->assertStringContainsString(
			'openssl_encrypt',
			$contents,
			'encrypt_token should use openssl_encrypt'
		);

		// Should use openssl_decrypt.
		$this->assertStringContainsString(
			'openssl_decrypt',
			$contents,
			'decrypt_token should use openssl_decrypt'
		);
	}

	/**
	 * Test that get_api_token resolves the v1 Personal Access Token per-user.
	 */
	public function test_get_api_token_uses_per_user_access_token() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		$this->assertMatchesRegularExpression(
			'/function get_api_token.*get_user_access_token/s',
			$contents,
			'get_api_token should call get_user_access_token for the v1 Personal Access Token'
		);
	}

	/**
	 * Test that clear_v0_session_token clears the per-user legacy v0 cache.
	 */
	public function test_clear_v0_session_token_clears_user_cache() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		$this->assertMatchesRegularExpression(
			'/function clear_v0_session_token.*delete_transient.*sprintf/s',
			$contents,
			'clear_v0_session_token should format a per-user cache key and delete the transient'
		);
	}

	/**
	 * Test that settings page handles the per-user Personal Access Token only.
	 */
	public function test_settings_page_handles_per_user_tokens() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/settings.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should encrypt token before storing.
		$this->assertStringContainsString(
			'encrypt_token',
			$contents,
			'Settings page should encrypt token before storing'
		);

		// Should store in user meta.
		$this->assertStringContainsString(
			'update_user_meta',
			$contents,
			'Settings page should store token in user meta'
		);

		// Should use ash_nazg_user_access_token meta key.
		$this->assertStringContainsString(
			'ash_nazg_user_access_token',
			$contents,
			'Settings page should use ash_nazg_user_access_token meta key'
		);

		// Should no longer accept a machine token field.
		$this->assertStringNotContainsString(
			'ash_nazg_machine_token',
			$contents,
			'Settings page should no longer let admins set a new legacy Machine Token'
		);
	}

	/**
	 * Test that settings view displays user ID for Pantheon Secrets.
	 */
	public function test_settings_view_displays_user_id() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/views/settings.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should display user ID.
		$this->assertStringContainsString(
			'ash-nazg-user-id',
			$contents,
			'Settings view should display user ID with CSS class'
		);

		// Should show Terminus command with user ID suffix.
		$this->assertStringContainsString(
			'ash_nazg_access_token_',
			$contents,
			'Settings view should show Terminus command with user ID suffix'
		);

		// Should explain the user ID suffix.
		$this->assertStringContainsString(
			'user ID',
			$contents,
			'Settings view should explain the user ID suffix'
		);
	}

	/**
	 * Test that CSS includes styles for user ID and code block.
	 */
	public function test_css_includes_user_token_styles() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/assets/css/admin.css';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should have user ID styling.
		$this->assertStringContainsString(
			'.ash-nazg-user-id',
			$contents,
			'CSS should include user ID styling'
		);

		// Should have code block styling.
		$this->assertStringContainsString(
			'.ash-nazg-code-block',
			$contents,
			'CSS should include code block styling'
		);
	}

	/**
	 * Test that the legacy Machine Token notice detects a stored token from any source.
	 */
	public function test_legacy_machine_token_notice_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\display_legacy_machine_token_notice' ),
			'display_legacy_machine_token_notice should exist'
		);
	}

	/**
	 * Test that the notice checks per-user secret, per-user meta, global secret, and global option.
	 */
	public function test_legacy_machine_token_notice_checks_all_sources() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$func_pos = strpos( $admin_contents, 'function display_legacy_machine_token_notice' );
		$this->assertNotFalse( $func_pos, 'Should find function display_legacy_machine_token_notice' );

		$func_content = substr( $admin_contents, $func_pos, 2500 );

		$this->assertStringContainsString( 'ash_nazg_machine_token_%d', $func_content, 'Notice should check per-user secret' );
		$this->assertStringContainsString( 'ash_nazg_user_machine_token', $func_content, 'Notice should check per-user meta' );
		$this->assertStringContainsString( "pantheon_get_secret( 'ash_nazg_machine_token' )", $func_content, 'Notice should check global secret' );
		$this->assertStringContainsString( "get_option( 'ash_nazg_machine_token' )", $func_content, 'Notice should check global option' );
	}

	/**
	 * Test that the removal button is only offered when an access token also exists,
	 * to avoid locking an admin out of the v0-only fallback features.
	 */
	public function test_legacy_machine_token_notice_gates_removal_on_access_token() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$func_pos = strpos( $admin_contents, 'function display_legacy_machine_token_notice' );
		$func_content = substr( $admin_contents, $func_pos, 2500 );

		$this->assertStringContainsString(
			'$has_access_token && $has_db_token',
			$func_content,
			'Notice should only show the remove button when an access token exists alongside a database-stored machine token'
		);
	}

	/**
	 * Test that the delete handler exists, is secured, and refuses to run without an access token.
	 */
	public function test_legacy_machine_token_delete_handler_is_safe() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\handle_delete_legacy_machine_token' ),
			'handle_delete_legacy_machine_token should exist'
		);

		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function handle_delete_legacy_machine_token.*wp_verify_nonce/s',
			$admin_contents,
			'handler must verify nonce'
		);
		$this->assertMatchesRegularExpression(
			'/function handle_delete_legacy_machine_token.*current_user_can/s',
			$admin_contents,
			'handler must check capabilities'
		);
		$this->assertMatchesRegularExpression(
			'/function handle_delete_legacy_machine_token.*get_user_access_token.*wp_die/s',
			$admin_contents,
			'handler must refuse to delete the Machine Token when no access token is configured, to avoid lockout'
		);
		$this->assertMatchesRegularExpression(
			'/function handle_delete_legacy_machine_token.*delete_user_meta.*delete_option.*clear_v0_session_token/s',
			$admin_contents,
			'handler must delete both database-stored copies and clear the cached v0 session'
		);
	}

	/**
	 * Test that both hooks are registered.
	 */
	public function test_legacy_machine_token_hooks_registered() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			"add_action( 'admin_notices', __NAMESPACE__ . '\\\\display_legacy_machine_token_notice' )",
			$admin_contents,
			'display_legacy_machine_token_notice should be registered on admin_notices'
		);
		$this->assertStringContainsString(
			"add_action( 'admin_init', __NAMESPACE__ . '\\\\handle_delete_legacy_machine_token' )",
			$admin_contents,
			'handle_delete_legacy_machine_token should be registered on admin_init'
		);
		$this->assertStringContainsString(
			"add_action( 'admin_init', __NAMESPACE__ . '\\\\handle_dismiss_legacy_machine_token_notice' )",
			$admin_contents,
			'handle_dismiss_legacy_machine_token_notice should be registered on admin_init'
		);
	}

	/**
	 * Test that the old per-user-vs-global machine token migration system is gone.
	 */
	public function test_old_migration_system_removed() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$removed_identifiers = [
			'display_token_migration_notice',
			'display_global_secret_cleanup_notice',
			'display_redundant_user_meta_notice',
			'handle_token_migration',
			'handle_global_secret_cleanup_dismissal',
			'handle_delete_global_token',
			'handle_delete_redundant_user_meta_token',
		];

		foreach ( $removed_identifiers as $identifier ) {
			$this->assertStringNotContainsString(
				$identifier,
				$admin_contents,
				"{$identifier} should no longer exist - superseded by the legacy Machine Token notice"
			);
		}
	}

	/**
	 * Test that the Dashboard no longer offers to copy a Machine Token.
	 */
	public function test_dashboard_copy_machine_token_removed() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$dashboard_contents = file_get_contents( __DIR__ . '/../includes/views/dashboard.php' );
		$dashboard_js = file_get_contents( __DIR__ . '/../assets/js/dashboard.js' );

		$this->assertStringNotContainsString( 'ajax_copy_machine_token', $admin_contents, 'ajax_copy_machine_token handler should be removed' );
		$this->assertStringNotContainsString( 'ash_nazg_copy_machine_token', $admin_contents, 'copy-machine-token AJAX action should be deregistered' );
		$this->assertStringNotContainsString( 'ash-nazg-copy-token"', $dashboard_contents, 'Dashboard should no longer render a copy-machine-token button' );
		$this->assertStringNotContainsString( 'ash_nazg_copy_machine_token', $dashboard_js, 'Dashboard JS should no longer bind the copy-machine-token button' );
	}
}
