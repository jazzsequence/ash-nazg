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
			function_exists( 'Pantheon\AshNazg\API\get_user_machine_token' ),
			'get_user_machine_token function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\clear_user_session_token' ),
			'clear_user_session_token function should exist'
		);
	}

	/**
	 * Test that get_user_machine_token checks per-user sources.
	 */
	public function test_get_user_machine_token_checks_user_sources() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should check per-user Pantheon Secret.
		$this->assertStringContainsString(
			'ash_nazg_machine_token_%d',
			$contents,
			'get_user_machine_token should check per-user Pantheon Secret with user ID suffix'
		);

		// Should check per-user meta.
		$this->assertStringContainsString(
			'ash_nazg_user_machine_token',
			$contents,
			'get_user_machine_token should check per-user meta'
		);

		// Should call decrypt_token for user meta.
		$this->assertStringContainsString(
			'decrypt_token',
			$contents,
			'get_user_machine_token should decrypt tokens from user meta'
		);

		// Should fallback to global token.
		$this->assertStringContainsString(
			'get_machine_token()',
			$contents,
			'get_user_machine_token should fallback to global token for backward compatibility'
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
	 * Test that get_api_token uses per-user session tokens.
	 */
	public function test_get_api_token_uses_per_user_cache() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should use per-user transient key format.
		$this->assertStringContainsString(
			'ash_nazg_session_token_%d',
			$contents,
			'get_api_token should use per-user session token cache key'
		);

		// Should call get_user_machine_token instead of get_machine_token.
		$this->assertStringContainsString(
			'get_user_machine_token',
			$contents,
			'get_api_token should call get_user_machine_token for per-user tokens'
		);
	}

	/**
	 * Test that clear_user_session_token function clears per-user cache.
	 */
	public function test_clear_user_session_token_clears_user_cache() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should format cache key with user ID.
		$this->assertStringContainsString(
			'sprintf',
			$contents,
			'clear_user_session_token should format cache key with user ID'
		);

		// Should delete transient.
		$this->assertStringContainsString(
			'delete_transient',
			$contents,
			'clear_user_session_token should delete transient'
		);
	}

	/**
	 * Test that settings page handles per-user tokens.
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

		// Should use ash_nazg_user_machine_token meta key.
		$this->assertStringContainsString(
			'ash_nazg_user_machine_token',
			$contents,
			'Settings page should use ash_nazg_user_machine_token meta key'
		);

		// Should clear user session token after saving.
		$this->assertStringContainsString(
			'clear_user_session_token',
			$contents,
			'Settings page should clear user session token after saving new token'
		);
	}

	/**
	 * Test that migration notice checks for global token.
	 */
	public function test_migration_notice_checks_global_token() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should check for global option.
		$this->assertStringContainsString(
			"get_option( 'ash_nazg_machine_token' )",
			$contents,
			'Migration notice should check for global option'
		);

		// Should check for global Pantheon Secret.
		$this->assertStringContainsString(
			"pantheon_get_secret( 'ash_nazg_machine_token' )",
			$contents,
			'Migration notice should check for global Pantheon Secret'
		);

		// Should check if user already has token.
		$this->assertStringContainsString(
			'ash_nazg_user_machine_token',
			$contents,
			'Migration notice should check if user already has per-user token'
		);
	}

	/**
	 * Test that migration handler encrypts and deletes global token.
	 */
	public function test_migration_handler_encrypts_and_deletes() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should encrypt token during migration.
		$this->assertStringContainsString(
			'encrypt_token',
			$contents,
			'Migration handler should encrypt token before storing'
		);

		// Should delete global token after migration.
		$this->assertStringContainsString(
			"delete_option( 'ash_nazg_machine_token' )",
			$contents,
			'Migration handler should delete global token after migration'
		);

		// Should update user meta.
		$this->assertStringContainsString(
			'update_user_meta',
			$contents,
			'Migration handler should store token in user meta'
		);

		// Should clear user session token.
		$this->assertStringContainsString(
			'clear_user_session_token',
			$contents,
			'Migration handler should clear user session token'
		);
	}

	/**
	 * Test that migration notice has progressive nag logic.
	 */
	public function test_migration_notice_has_progressive_nag() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should track dismiss timestamp.
		$this->assertStringContainsString(
			'ash_nazg_migration_dismissed_time',
			$contents,
			'Migration notice should track dismiss timestamp'
		);

		// Should track dismiss count.
		$this->assertStringContainsString(
			'ash_nazg_migration_dismiss_count',
			$contents,
			'Migration notice should track dismiss count'
		);

		// Should have week-long wait period for first dismiss.
		$this->assertStringContainsString(
			'WEEK_IN_SECONDS',
			$contents,
			'Migration notice should wait 1 week after first dismiss'
		);

		// Should have 24-hour wait period for subsequent dismisses.
		$this->assertStringContainsString(
			'DAY_IN_SECONDS',
			$contents,
			'Migration notice should wait 24 hours after subsequent dismisses'
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
			'ash_nazg_machine_token_',
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
	 * Test that migration button appears on settings page for global token.
	 */
	public function test_settings_view_has_migration_button() {
		$file_path = dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/views/settings.php';
		$this->assertFileExists( $file_path );

		$contents = file_get_contents( $file_path );

		// Should check if using global token.
		$this->assertStringContainsString(
			'$has_global_token',
			$contents,
			'Settings view should check if global token exists'
		);

		// Should show migration button.
		$this->assertStringContainsString(
			'Migrate to My Account',
			$contents,
			'Settings view should show migration button'
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
}
