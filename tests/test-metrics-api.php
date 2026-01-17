<?php
/**
 * Metrics API Tests
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test Metrics API functions.
 */
class MetricsAPITest extends TestCase {

	/**
	 * Test that get_environment_metrics function exists.
	 */
	public function test_get_environment_metrics_exists() {
		$this->assertTrue( function_exists( 'Pantheon\\AshNazg\\API\\get_environment_metrics' ) );
	}

	/**
	 * Test that clear_metrics_cache function exists.
	 */
	public function test_clear_metrics_cache_exists() {
		$this->assertTrue( function_exists( 'Pantheon\\AshNazg\\API\\clear_metrics_cache' ) );
	}

	/**
	 * Test metrics endpoint path construction.
	 */
	public function test_metrics_endpoint_path() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for metrics endpoint pattern.
		$this->assertStringContainsString(
			'/v0/sites/%s/environments/%s/metrics',
			$api_file,
			'Metrics endpoint should use correct path structure'
		);
	}

	/**
	 * Test duration parameter validation.
	 */
	public function test_duration_validation() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for valid durations array.
		$this->assertStringContainsString(
			"[ '7d', '28d', '12w', '12m' ]",
			$api_file,
			'Should validate duration against valid values'
		);

		// Check for invalid_duration error code.
		$this->assertStringContainsString(
			"'invalid_duration'",
			$api_file,
			'Should return WP_Error for invalid duration'
		);
	}

	/**
	 * Test metrics cache key format.
	 */
	public function test_metrics_cache_key() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for cache key pattern.
		$this->assertStringContainsString(
			"'ash_nazg_metrics_%s_%s_%s'",
			$api_file,
			'Cache key should include site_id, env, and duration'
		);
	}

	/**
	 * Test metrics cache TTL.
	 */
	public function test_metrics_cache_ttl() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for 1 hour cache.
		$this->assertStringContainsString(
			'HOUR_IN_SECONDS',
			$api_file,
			'Metrics should be cached for 1 hour'
		);
	}

	/**
	 * Test that metrics functions use ensure_site_id helper.
	 */
	public function test_metrics_uses_ensure_site_id() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for ensure_site_id usage in get_environment_metrics.
		$this->assertMatchesRegularExpression(
			'/function get_environment_metrics.*?Helpers\\\\ensure_site_id/s',
			$api_file,
			'get_environment_metrics should use ensure_site_id helper'
		);
	}

	/**
	 * Test that metrics functions use ensure_environment helper.
	 */
	public function test_metrics_uses_ensure_environment() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for ensure_environment usage in get_environment_metrics.
		$this->assertMatchesRegularExpression(
			'/function get_environment_metrics.*?Helpers\\\\ensure_environment/s',
			$api_file,
			'get_environment_metrics should use ensure_environment helper'
		);
	}

	/**
	 * Test that metrics functions use map_local_env_to_dev.
	 */
	public function test_metrics_local_env_mapping() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for local environment mapping.
		$this->assertMatchesRegularExpression(
			'/function get_environment_metrics.*?map_local_env_to_dev/s',
			$api_file,
			'get_environment_metrics should map local environments to dev'
		);
	}

	/**
	 * Test that clear_metrics_cache clears all duration caches.
	 */
	public function test_clear_metrics_cache_clears_all_durations() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check that clear_metrics_cache loops through all durations.
		$this->assertMatchesRegularExpression(
			'/function clear_metrics_cache.*?foreach.*?durations.*?as.*?duration/s',
			$api_file,
			'clear_metrics_cache should clear all duration caches'
		);

		// Check for delete_transient calls.
		$this->assertMatchesRegularExpression(
			'/function clear_metrics_cache.*?delete_transient/s',
			$api_file,
			'clear_metrics_cache should call delete_transient'
		);
	}

	/**
	 * Test metrics error handling.
	 */
	public function test_metrics_error_handling() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for WP_Error handling.
		$this->assertMatchesRegularExpression(
			'/function get_environment_metrics.*?is_wp_error/s',
			$api_file,
			'get_environment_metrics should check for WP_Error'
		);
	}

	/**
	 * Test metrics debug logging.
	 */
	public function test_metrics_debug_logging() {
		$api_file = file_get_contents( __DIR__ . '/../includes/api.php' );

		// Check for debug logging in get_environment_metrics.
		$this->assertMatchesRegularExpression(
			'/function get_environment_metrics.*?Helpers\\\\debug_log/s',
			$api_file,
			'get_environment_metrics should use debug_log'
		);

		// Check for debug logging in clear_metrics_cache.
		$this->assertMatchesRegularExpression(
			'/function clear_metrics_cache.*?Helpers\\\\debug_log/s',
			$api_file,
			'clear_metrics_cache should use debug_log'
		);
	}

	/**
	 * Test AJAX handler exists.
	 */
	public function test_ajax_get_metrics_exists() {
		$this->assertTrue( function_exists( 'Pantheon\\AshNazg\\Admin\\ajax_get_metrics' ) );
	}

	/**
	 * Test AJAX refresh handler exists.
	 */
	public function test_ajax_refresh_metrics_exists() {
		$this->assertTrue( function_exists( 'Pantheon\\AshNazg\\Admin\\ajax_refresh_metrics' ) );
	}

	/**
	 * Test AJAX handlers are registered.
	 */
	public function test_ajax_handlers_registered() {
		$admin_file = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			"add_action( 'wp_ajax_ash_nazg_get_metrics'",
			$admin_file,
			'ajax_get_metrics should be registered'
		);

		$this->assertStringContainsString(
			"add_action( 'wp_ajax_ash_nazg_refresh_metrics'",
			$admin_file,
			'ajax_refresh_metrics should be registered'
		);
	}

	/**
	 * Test AJAX handler security checks.
	 */
	public function test_ajax_handlers_security() {
		$admin_file = file_get_contents( __DIR__ . '/../includes/admin.php' );

		// Check for nonce verification in ajax_get_metrics.
		$this->assertMatchesRegularExpression(
			'/function ajax_get_metrics.*?check_ajax_referer.*?ash_nazg_get_metrics/s',
			$admin_file,
			'ajax_get_metrics should verify nonce'
		);

		// Check for capability check in ajax_get_metrics.
		$this->assertMatchesRegularExpression(
			'/function ajax_get_metrics.*?current_user_can.*?manage_options/s',
			$admin_file,
			'ajax_get_metrics should check capabilities'
		);

		// Check for nonce verification in ajax_refresh_metrics.
		$this->assertMatchesRegularExpression(
			'/function ajax_refresh_metrics.*?check_ajax_referer.*?ash_nazg_refresh_metrics/s',
			$admin_file,
			'ajax_refresh_metrics should verify nonce'
		);

		// Check for capability check in ajax_refresh_metrics.
		$this->assertMatchesRegularExpression(
			'/function ajax_refresh_metrics.*?current_user_can.*?manage_options/s',
			$admin_file,
			'ajax_refresh_metrics should check capabilities'
		);
	}

	/**
	 * Test render_metrics_page function exists.
	 */
	public function test_render_metrics_page_exists() {
		$this->assertTrue( function_exists( 'Pantheon\\AshNazg\\Admin\\render_metrics_page' ) );
	}

	/**
	 * Test metrics page has capability check.
	 */
	public function test_metrics_page_capability_check() {
		$admin_file = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function render_metrics_page.*?current_user_can.*?manage_options/s',
			$admin_file,
			'render_metrics_page should check capabilities'
		);
	}

	/**
	 * Test metrics JavaScript is enqueued.
	 */
	public function test_metrics_javascript_enqueued() {
		$admin_file = file_get_contents( __DIR__ . '/../includes/admin.php' );

		// Check for Chart.js enqueue.
		$this->assertStringContainsString(
			"wp_enqueue_script(\n\t\t\t'chartjs'",
			$admin_file,
			'Chart.js should be enqueued on metrics page'
		);

		// Check for metrics.js enqueue.
		$this->assertStringContainsString(
			"'ash-nazg-metrics',\n\t\t\tASH_NAZG_PLUGIN_URL . 'assets/js/metrics.js'",
			$admin_file,
			'metrics.js should be enqueued on metrics page'
		);

		// Check for localization.
		$this->assertStringContainsString(
			"wp_localize_script(\n\t\t\t'ash-nazg-metrics',\n\t\t\t'ashNazgMetrics'",
			$admin_file,
			'metrics.js should be localized with AJAX data'
		);
	}

	/**
	 * Test metrics view template exists.
	 */
	public function test_metrics_view_exists() {
		$this->assertFileExists(
			__DIR__ . '/../includes/views/metrics.php',
			'Metrics view template should exist'
		);
	}

	/**
	 * Test metrics JavaScript file exists.
	 */
	public function test_metrics_javascript_exists() {
		$this->assertFileExists(
			__DIR__ . '/../assets/js/metrics.js',
			'Metrics JavaScript file should exist'
		);
	}

	/**
	 * Test Chart.js library file exists.
	 */
	public function test_chartjs_libs_exists() {
		$this->assertFileExists(
			__DIR__ . '/../assets/js/libs/chart.umd.js',
			'Chart.js library file should exist'
		);
	}

	/**
	 * Test metrics SCSS file exists.
	 */
	public function test_metrics_scss_exists() {
		$this->assertFileExists(
			__DIR__ . '/../assets/sass/_pages/_metrics.scss',
			'Metrics SCSS file should exist'
		);
	}

	/**
	 * Test metrics SCSS is imported.
	 */
	public function test_metrics_scss_imported() {
		$admin_scss = file_get_contents( __DIR__ . '/../assets/sass/admin.scss' );

		$this->assertStringContainsString(
			"@use '_pages/_metrics'",
			$admin_scss,
			'Metrics SCSS should be imported in admin.scss'
		);
	}
}
