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

		// Check each function has error logging using debug_log helper.
		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*debug_log.*Failed to get commits/s',
			$file_contents,
			'get_environment_commits should log errors using debug_log helper'
		);

		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*debug_log.*Failed to get upstream updates/s',
			$file_contents,
			'get_upstream_updates should log errors using debug_log helper'
		);

		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*debug_log.*Failed to get code tips/s',
			$file_contents,
			'get_code_tips should log errors using debug_log helper'
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
	 * Test git function cache durations.
	 */
	public function test_git_functions_cache_duration() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/function get_environment_commits.*set_transient.*MINUTE_IN_SECONDS/s',
			$file_contents,
			'get_environment_commits should cache for 5 minutes'
		);

		$this->assertMatchesRegularExpression(
			'/function get_upstream_updates.*set_transient.*MINUTE_IN_SECONDS/s',
			$file_contents,
			'get_upstream_updates should cache for 5 minutes (short TTL so reverts self-heal)'
		);

		$this->assertMatchesRegularExpression(
			'/function get_code_tips.*set_transient.*HOUR_IN_SECONDS/s',
			$file_contents,
			'get_code_tips should cache for 1 hour'
		);
	}

	/**
	 * Test that get_local_git_diffstat and parse_git_porcelain_lines exist.
	 */
	public function test_local_git_diffstat_functions_exist() {
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_local_git_diffstat' ),
			'get_local_git_diffstat function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\parse_git_porcelain_lines' ),
			'parse_git_porcelain_lines function should exist'
		);
	}

	/**
	 * Test porcelain parser handles unstaged modifications (" M filename").
	 */
	public function test_parse_porcelain_unstaged_modification() {
		$result = \Pantheon\AshNazg\API\parse_git_porcelain_lines(
			[ ' M wp-content/plugins/ash-nazg/includes/api.php' ]
		);
		$this->assertArrayHasKey( 'wp-content/plugins/ash-nazg/includes/api.php', $result );
		$this->assertSame( 'M', $result['wp-content/plugins/ash-nazg/includes/api.php']['status'] );
	}

	/**
	 * Test porcelain parser handles staged modifications ("M  filename").
	 */
	public function test_parse_porcelain_staged_modification() {
		$result = \Pantheon\AshNazg\API\parse_git_porcelain_lines(
			[ 'M  wp-content/plugins/ash-nazg/includes/api.php' ]
		);
		$this->assertArrayHasKey( 'wp-content/plugins/ash-nazg/includes/api.php', $result );
		$this->assertSame( 'M', $result['wp-content/plugins/ash-nazg/includes/api.php']['status'] );
	}

	/**
	 * Test porcelain parser handles new staged files ("A  filename").
	 */
	public function test_parse_porcelain_staged_new_file() {
		$result = \Pantheon\AshNazg\API\parse_git_porcelain_lines(
			[ 'A  wp-content/plugins/ash-nazg/assets/css/modal.css' ]
		);
		$this->assertArrayHasKey( 'wp-content/plugins/ash-nazg/assets/css/modal.css', $result );
		$this->assertSame( 'A', $result['wp-content/plugins/ash-nazg/assets/css/modal.css']['status'] );
	}

	/**
	 * Test porcelain parser handles untracked files ("?? filename").
	 */
	public function test_parse_porcelain_untracked_file() {
		$result = \Pantheon\AshNazg\API\parse_git_porcelain_lines(
			[ '?? wp-content/plugins/ash-nazg/reviewer-approved' ]
		);
		$this->assertArrayHasKey( 'wp-content/plugins/ash-nazg/reviewer-approved', $result );
		$this->assertSame( '??', $result['wp-content/plugins/ash-nazg/reviewer-approved']['status'] );
	}

	/**
	 * Test porcelain parser handles both staged and unstaged on same file ("MM filename").
	 */
	public function test_parse_porcelain_staged_and_unstaged() {
		$result = \Pantheon\AshNazg\API\parse_git_porcelain_lines(
			[ 'MM wp-content/plugins/ash-nazg/includes/api.php' ]
		);
		$this->assertArrayHasKey( 'wp-content/plugins/ash-nazg/includes/api.php', $result );
		$this->assertSame( 'MM', $result['wp-content/plugins/ash-nazg/includes/api.php']['status'] );
	}

	/**
	 * Test porcelain parser returns empty array for empty input.
	 */
	public function test_parse_porcelain_empty_input() {
		$this->assertSame( [], \Pantheon\AshNazg\API\parse_git_porcelain_lines( [] ) );
	}

	/**
	 * Test get_local_git_diffstat uses safe.directory to handle containerised environments.
	 */
	public function test_local_git_diffstat_uses_safe_directory() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertStringContainsString(
			'safe.directory',
			$file_contents,
			'get_local_git_diffstat must pass safe.directory to git to work in containers'
		);
	}

	/**
	 * Test get_local_git_diffstat uses porcelain format (not --no-color which some versions reject).
	 */
	public function test_local_git_diffstat_uses_porcelain_not_no_color() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertStringContainsString(
			'--porcelain',
			$file_contents,
			'get_local_git_diffstat should use --porcelain for stable output'
		);
		$this->assertStringNotContainsString(
			'--no-color',
			$file_contents,
			'get_local_git_diffstat must not use --no-color (rejected by older git versions)'
		);
	}

	/**
	 * Test that get_local_git_unpushed and parse_git_log_lines exist.
	 */
	public function test_local_git_unpushed_functions_exist() {
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\get_local_git_unpushed' ),
			'get_local_git_unpushed function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\API\parse_git_log_lines' ),
			'parse_git_log_lines function should exist'
		);
	}

	/**
	 * Test parse_git_log_lines parses hash and message correctly.
	 */
	public function test_parse_git_log_lines_parses_commit() {
		$hash   = 'abc123def456abc123def456abc123def456abc1';
		$result = \Pantheon\AshNazg\API\parse_git_log_lines(
			[ $hash . '|Fix upstream updates' ]
		);
		$this->assertCount( 1, $result );
		$this->assertSame( $hash, $result[0]['hash'] );
		$this->assertSame( 'Fix upstream updates', $result[0]['message'] );
	}

	/**
	 * Test parse_git_log_lines handles multiple commits.
	 */
	public function test_parse_git_log_lines_multiple_commits() {
		$lines  = [
			'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa|First commit',
			'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb|Second commit',
		];
		$result = \Pantheon\AshNazg\API\parse_git_log_lines( $lines );
		$this->assertCount( 2, $result );
		$this->assertSame( 'First commit', $result[0]['message'] );
		$this->assertSame( 'Second commit', $result[1]['message'] );
	}

	/**
	 * Test parse_git_log_lines returns empty array for empty input.
	 */
	public function test_parse_git_log_lines_empty_input() {
		$this->assertSame( [], \Pantheon\AshNazg\API\parse_git_log_lines( [] ) );
	}

	/**
	 * Test parse_git_log_lines handles message with pipe characters.
	 */
	public function test_parse_git_log_lines_message_with_pipe() {
		$hash   = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$result = \Pantheon\AshNazg\API\parse_git_log_lines(
			[ $hash . '|Feat: foo|bar (message with pipe)' ]
		);
		$this->assertSame( 'Feat: foo|bar (message with pipe)', $result[0]['message'] );
	}

	/**
	 * Test get_local_git_unpushed uses safe.directory for container compatibility.
	 */
	public function test_local_git_unpushed_uses_safe_directory() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_local_git_unpushed.*safe\.directory/s',
			$file_contents,
			'get_local_git_unpushed must use safe.directory for containerised environments'
		);
	}

	/**
	 * Test get_local_git_unpushed uses @{u} to compare against remote tracking branch.
	 */
	public function test_local_git_unpushed_compares_to_upstream() {
		$file_contents = file_get_contents( __DIR__ . '/../includes/api.php' );
		$this->assertMatchesRegularExpression(
			'/function get_local_git_unpushed.*@\{u\}/s',
			$file_contents,
			'get_local_git_unpushed must use @{u} to find commits not on the remote'
		);
	}
}
