<?php
/**
 * Tests for Git-related API functions.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Git API functions.
 */
class Test_Git_API extends TestCase {

	/**
	 * Test that get_environment_commits function exists.
	 */
	public function test_get_environment_commits_exists() {
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_environment_commits' ),
			'get_environment_commits function should exist'
		);
	}

	/**
	 * Test that get_upstream_updates function exists.
	 */
	public function test_get_upstream_updates_exists() {
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_upstream_updates' ),
			'get_upstream_updates function should exist'
		);
	}

	/**
	 * Test that get_code_tips function exists.
	 */
	public function test_get_code_tips_exists() {
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_code_tips' ),
			'get_code_tips function should exist'
		);
	}

	/**
	 * Test that get_environment_commits uses correct endpoint.
	 */
	public function test_get_environment_commits_endpoint() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/commits',
			$file_contents,
			'get_environment_commits should use correct API endpoint'
		);
	}

	/**
	 * Test that get_upstream_updates uses correct endpoint.
	 */
	public function test_get_upstream_updates_endpoint() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertStringContainsString(
			'/v0/sites/%s/upstream-updates',
			$file_contents,
			'get_upstream_updates should use correct API endpoint'
		);
	}

	/**
	 * Test that get_code_tips uses correct endpoint.
	 */
	public function test_get_code_tips_endpoint() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertStringContainsString(
			'/v0/sites/%s/code-tips',
			$file_contents,
			'get_code_tips should use correct API endpoint'
		);
	}

	/**
	 * Test that get_environment_commits implements caching.
	 */
	public function test_get_environment_commits_caching() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for cache key pattern.
		$this->assertStringContainsString(
			'ash_nazg_commits_',
			$file_contents,
			'get_environment_commits should use cache key'
		);

		// Check for transient functions.
		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*get_transient.*set_transient/s',
			$file_contents,
			'get_environment_commits should use WordPress transients for caching'
		);
	}

	/**
	 * Test that get_upstream_updates implements caching.
	 */
	public function test_get_upstream_updates_caching() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for cache key pattern.
		$this->assertStringContainsString(
			'ash_nazg_upstream_updates_',
			$file_contents,
			'get_upstream_updates should use cache key'
		);

		// Check for transient functions.
		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*get_transient.*set_transient/s',
			$file_contents,
			'get_upstream_updates should use WordPress transients for caching'
		);
	}

	/**
	 * Test that get_code_tips implements caching.
	 */
	public function test_get_code_tips_caching() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for cache key pattern.
		$this->assertStringContainsString(
			'ash_nazg_code_tips_',
			$file_contents,
			'get_code_tips should use cache key'
		);

		// Check for transient functions.
		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*get_transient.*set_transient/s',
			$file_contents,
			'get_code_tips should use WordPress transients for caching'
		);
	}

	/**
	 * Test that get_environment_commits uses local environment mapping.
	 */
	public function test_get_environment_commits_local_mapping() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*map_local_env_to_dev/s',
			$file_contents,
			'get_environment_commits should map local environments to dev'
		);
	}

	/**
	 * Test that all git functions include error logging.
	 */
	public function test_git_functions_error_logging() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check each function has error logging.
		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*WP_DEBUG.*error_log.*Failed to get commits/s',
			$file_contents,
			'get_environment_commits should log errors when WP_DEBUG is enabled'
		);

		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*WP_DEBUG.*error_log.*Failed to get upstream updates/s',
			$file_contents,
			'get_upstream_updates should log errors when WP_DEBUG is enabled'
		);

		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*WP_DEBUG.*error_log.*Failed to get code tips/s',
			$file_contents,
			'get_code_tips should log errors when WP_DEBUG is enabled'
		);
	}

	/**
	 * Test that all git functions cache with timestamp.
	 */
	public function test_git_functions_cache_timestamp() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for cached_at timestamp in set_transient calls.
		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*cached_at.*time\(\)/s',
			$file_contents,
			'get_environment_commits should store cached_at timestamp'
		);

		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*cached_at.*time\(\)/s',
			$file_contents,
			'get_upstream_updates should store cached_at timestamp'
		);

		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*cached_at.*time\(\)/s',
			$file_contents,
			'get_code_tips should store cached_at timestamp'
		);
	}

	/**
	 * Test that all git functions use HOUR_IN_SECONDS for cache expiration.
	 */
	public function test_git_functions_cache_duration() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*set_transient.*HOUR_IN_SECONDS/s',
			$file_contents,
			'get_environment_commits should cache for 1 hour'
		);

		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*set_transient.*HOUR_IN_SECONDS/s',
			$file_contents,
			'get_upstream_updates should cache for 1 hour'
		);

		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*set_transient.*HOUR_IN_SECONDS/s',
			$file_contents,
			'get_code_tips should cache for 1 hour'
		);
	}
}
