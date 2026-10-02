<?php
/**
 * Backups API tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Backup API functions.
 *
 * Note: These are integration-style tests that verify the code structure
 * and logic. Full API testing requires WordPress test environment.
 */
class Test_Backups_API extends TestCase {

	/**
	 * Test that backup API functions exist.
	 */
	public function test_backup_functions_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_backups' ),
			'get_backups function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\create_backup' ),
			'create_backup function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\restore_backup' ),
			'restore_backup function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_backup_download_url' ),
			'get_backup_download_url function should exist'
		);
	}

	/**
	 * Test backup API endpoint paths.
	 */
	public function test_backup_endpoint_paths() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use correct backup endpoints.
		$this->assertStringContainsString(
			'/sites/%s/environments/%s/backups',
			$api_file_contents,
			'Should use backups listing endpoint'
		);
		$this->assertStringContainsString(
			'/sites/%s/backups?environment=%s',
			$api_file_contents,
			'Should use backup creation endpoint'
		);
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/backups/%s/restore',
			$api_file_contents,
			'Should use the v0 backup restore endpoint'
		);
		$this->assertStringContainsString(
			'/sites/%s/environments/%s/backup-download-urls',
			$api_file_contents,
			'Should use backup download URL endpoint'
		);
	}

	/**
	 * Test backup element validation.
	 */
	public function test_backup_element_validation() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should validate backup elements for creation (all, code, database, files).
		$this->assertStringContainsString(
			"'all'",
			$api_file_contents,
			'Should support "all" backup element'
		);
		$this->assertStringContainsString(
			"'code'",
			$api_file_contents,
			'Should support "code" backup element'
		);
		$this->assertStringContainsString(
			"'database'",
			$api_file_contents,
			'Should support "database" backup element'
		);
		$this->assertStringContainsString(
			"'files'",
			$api_file_contents,
			'Should support "files" backup element'
		);

		// Should have element validation.
		$this->assertStringContainsString(
			'invalid_element',
			$api_file_contents,
			'Should have invalid_element error code'
		);
	}

	/**
	 * Test backup keep_for parameter.
	 */
	public function test_backup_keep_for_parameter() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should convert keep_for (days) to v1's ttl (seconds) parameter.
		$this->assertStringContainsString(
			'$keep_for * DAY_IN_SECONDS',
			$api_file_contents,
			'Should convert keep_for days to ttl seconds for v1'
		);

		// Should validate keep_for (minimum 1 day).
		$this->assertStringContainsString(
			'max( 1',
			$api_file_contents,
			'Should validate keep_for minimum value'
		);
	}

	/**
	 * Test backup cache handling.
	 */
	public function test_backup_cache_handling() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use cache key for backups.
		$this->assertStringContainsString(
			'ash_nazg_backups_',
			$api_file_contents,
			'Should use backups cache key'
		);

		// Should clear cache after mutations.
		$this->assertGreaterThan(
			1,
			substr_count( $api_file_contents, "delete_transient( sprintf( 'ash_nazg_backups_%s_%s'" ),
			'Should clear backups cache after create/restore operations'
		);
	}

	/**
	 * Test restore element validation.
	 *
	 * Restore endpoint does not support 'all' - only code, database, files.
	 */
	public function test_restore_element_validation() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should have a comment noting 'all' is not valid for restore.
		$this->assertStringContainsString(
			"'all' is not valid for restore",
			$api_file_contents,
			'Should document that "all" is not valid for restore operations'
		);
	}

	/**
	 * Test download URL response handling.
	 */
	public function test_download_url_response() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should check for URL field in response.
		$this->assertStringContainsString(
			"isset( \$result['url'] )",
			$api_file_contents,
			'Should check for url field in download URL response'
		);

		// Should have missing_url error code.
		$this->assertStringContainsString(
			'missing_url',
			$api_file_contents,
			'Should have missing_url error code'
		);
	}

	/**
	 * Test local environment mapping for backups.
	 */
	public function test_local_environment_mapping() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// All backup functions should map local environments to dev.
		$backup_functions = [
			'function get_backups',
			'function create_backup',
			'function restore_backup',
			'function get_backup_download_url',
		];

		foreach ( $backup_functions as $func_declaration ) {
			// Find the function.
			$func_pos = strpos( $api_file_contents, $func_declaration );
			$this->assertNotFalse( $func_pos, "Should find {$func_declaration}" );

			// Get the next 2000 characters after function declaration.
			$func_content = substr( $api_file_contents, $func_pos, 2000 );

			// Should map local environments.
			$this->assertStringContainsString(
				'map_local_env_to_dev',
				$func_content,
				"{$func_declaration} should map local environments to dev"
			);
		}
	}

	/**
	 * Test error handling in backup functions.
	 */
	public function test_backup_error_handling() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/helpers.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should use ensure_site_id() helper for validation.
		$this->assertGreaterThan(
			3,
			substr_count( $api_file_contents, 'Helpers\ensure_site_id' ),
			'Backup functions should use ensure_site_id() helper'
		);

		// Should handle API errors with is_wp_error checks.
		$this->assertStringContainsString(
			'is_wp_error( $result )',
			$api_file_contents,
			'Should check for WP_Error in API responses'
		);
	}

	/**
	 * Test debug logging in backup operations.
	 */
	public function test_backup_debug_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php';

		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		// Should log backup operations.
		$this->assertStringContainsString(
			'Retrieved %d backups',
			$api_file_contents,
			'Should log backup retrieval'
		);
		$this->assertStringContainsString(
			'Created backup for',
			$api_file_contents,
			'Should log backup creation'
		);
		$this->assertStringContainsString(
			'Restored %s from backup',
			$api_file_contents,
			'Should log backup restore'
		);
		$this->assertStringContainsString(
			'Retrieved download URL',
			$api_file_contents,
			'Should log download URL retrieval'
		);
	}

	/**
	 * Test that Backup.date is parsed as a numeric Unix timestamp, not an ISO date.
	 *
	 * Regression guard: Backup.date is a numeric string (e.g. "1780592488"),
	 * not an ISO datetime - strtotime() on a bare numeric string does not
	 * reliably parse it as a Unix timestamp.
	 */
	public function test_backup_date_parsed_as_numeric_timestamp() {
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertMatchesRegularExpression(
			"/function get_backups.*is_numeric\\( \\\$backup\\['date'\\]/s",
			$api_file_contents,
			'get_backups should check is_numeric() on Backup.date before casting'
		);
		$this->assertDoesNotMatchRegularExpression(
			"/function get_backups.*strtotime\\( \\\$backup\\['date'\\]/s",
			$api_file_contents,
			'get_backups should not call strtotime() directly on Backup.date'
		);
	}

	/**
	 * Test that get_backups() uses the real size/ttl/name fields from each
	 * backup element instead of hardcoding them.
	 *
	 * Regression guard: v1 backup elements have real size/ttl fields and a
	 * complete filename, contrary to what the OpenAPI schema doc implied.
	 */
	public function test_backups_use_real_size_ttl_name() {
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$get_backups_pos = strpos( $api_file_contents, 'function get_backups(' );
		$this->assertNotFalse( $get_backups_pos, 'Should find get_backups function' );
		$func_content = substr( $api_file_contents, $get_backups_pos, 2000 );

		$this->assertStringContainsString(
			"\$backup[ \$element ]['size']",
			$func_content,
			'get_backups should read the real size field from each backup element'
		);
		$this->assertStringContainsString(
			"\$backup[ \$element ]['ttl']",
			$func_content,
			'get_backups should read the real ttl field from each backup element'
		);
		$this->assertStringContainsString(
			"\$backup[ \$element ]['name']",
			$func_content,
			'get_backups should use the real, already-complete filename from each backup element'
		);
	}

	/**
	 * Test that get_backup_download_url() looks up the real backup timestamp
	 * instead of parsing one out of the opaque backup id.
	 */
	public function test_backup_download_url_looks_up_real_timestamp() {
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertStringNotContainsString(
			"preg_replace( '/\\D.*\$/'",
			$api_file_contents,
			'get_backup_download_url should no longer derive a timestamp by regex-stripping the backup id'
		);

		$get_download_url_pos = strpos( $api_file_contents, 'function get_backup_download_url(' );
		$this->assertNotFalse( $get_download_url_pos, 'Should find get_backup_download_url function' );
		$func_content = substr( $api_file_contents, $get_download_url_pos, 1500 );

		$this->assertStringContainsString(
			'get_raw_backups(',
			$func_content,
			'get_backup_download_url should look up the raw backups list rather than guessing a timestamp'
		);
		$this->assertStringContainsString(
			"\$backup['date']",
			$func_content,
			'get_backup_download_url should read the matched backup\'s own date field'
		);
	}

	/**
	 * Test that get_backups() and get_backup_download_url() share the same
	 * raw-backups fetch helper instead of duplicating the endpoint/cache key.
	 */
	public function test_backups_share_raw_fetch_helper() {
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_raw_backups' ),
			'get_raw_backups helper function should exist'
		);
		$this->assertMatchesRegularExpression(
			'/function get_backups.*get_raw_backups\(/s',
			$api_file_contents,
			'get_backups should call the shared get_raw_backups() helper'
		);
		$this->assertMatchesRegularExpression(
			'/function get_backup_download_url.*get_raw_backups\(/s',
			$api_file_contents,
			'get_backup_download_url should call the shared get_raw_backups() helper'
		);
	}

	/**
	 * restore_backup() should use the v0 fallback endpoint and request shape.
	 */
	public function test_restore_backup_uses_v0_fallback() {
		$api_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/api.php' );

		$func_pos = strpos( $api_file_contents, 'function restore_backup' );
		$this->assertNotFalse( $func_pos, 'Should find function restore_backup' );

		$func_content = substr( $api_file_contents, $func_pos, 2000 );

		$this->assertStringContainsString(
			'api_request_v0(',
			$func_content,
			'restore_backup should use the v0 fallback client'
		);
		$this->assertStringContainsString(
			"'/v0/sites/%s/environments/%s/backups/%s/restore'",
			$func_content,
			'restore_backup should use the v0 path-based restore endpoint'
		);
		$this->assertStringContainsString(
			"[ 'element' => \$element ]",
			$func_content,
			'restore_backup should send element in the v0 request body'
		);
	}
}
