<?php
/**
 * Dashboard widget tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test the WP admin dashboard metrics widget.
 */
class Test_Dashboard_Widget extends TestCase {

	/**
	 * Test that the dashboard widget hook is registered.
	 */
	public function test_dashboard_widget_hook_registered() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'wp_dashboard_setup',
			$admin_contents,
			'wp_dashboard_setup hook should be registered'
		);
	}

	/**
	 * Test that the widget registration function exists.
	 */
	public function test_register_dashboard_widget_function_exists() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\register_dashboard_widget' ),
			'register_dashboard_widget function should exist'
		);
	}

	/**
	 * Test that wp_add_dashboard_widget is called with the correct slug.
	 */
	public function test_widget_uses_correct_slug() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_metrics_widget',
			$admin_contents,
			'Dashboard widget should use ash_nazg_metrics_widget as slug'
		);
		$this->assertStringContainsString(
			'wp_add_dashboard_widget',
			$admin_contents,
			'wp_add_dashboard_widget should be called'
		);
	}

	/**
	 * Test that the widget JS file exists.
	 */
	public function test_widget_javascript_exists() {
		$this->assertFileExists(
			dirname( ASH_NAZG_PLUGIN_FILE ) . '/assets/js/dashboard-widget.js',
			'assets/js/dashboard-widget.js should exist'
		);
	}

	/**
	 * Test that the widget JS is enqueued on the WP dashboard page.
	 */
	public function test_widget_javascript_enqueued_on_dashboard() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash-nazg-dashboard-widget',
			$admin_contents,
			'Dashboard widget script handle should be enqueued'
		);
		$this->assertStringContainsString(
			'index.php',
			$admin_contents,
			'Widget JS should only be enqueued on the WP dashboard (index.php hook)'
		);
	}

	/**
	 * Test that the widget uses the existing metrics AJAX action.
	 */
	public function test_widget_uses_metrics_ajax_action() {
		$widget_js = file_get_contents( __DIR__ . '/../assets/js/dashboard-widget.js' );

		$this->assertStringContainsString(
			'ash_nazg_get_metrics',
			$widget_js,
			'Widget JS should call the existing ash_nazg_get_metrics AJAX action'
		);
	}

	/**
	 * Test that the widget render function includes the Pantheon logo.
	 */
	public function test_widget_includes_pantheon_logo() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'pantheon-logo',
			$admin_contents,
			'Widget render function should include the Pantheon logo'
		);
	}

	/**
	 * Test that the logo is in the widget title (register function), not the render function.
	 */
	public function test_widget_logo_in_title_not_render() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash-nazg-widget-title-wrap',
			$admin_contents,
			'Widget title should use ash-nazg-widget-title-wrap to keep logo and text together'
		);

		// Logo must appear in register_dashboard_widget, before render_dashboard_widget.
		$register_pos = strpos( $admin_contents, 'function register_dashboard_widget' );
		$render_pos = strpos( $admin_contents, 'function render_dashboard_widget' );
		$logo_pos = strpos( $admin_contents, 'ash-nazg-widget-title-logo' );

		$this->assertLessThan(
			$render_pos,
			$logo_pos,
			'Logo class should appear in register_dashboard_widget (before render_dashboard_widget)'
		);
		$this->assertGreaterThan(
			$register_pos,
			$logo_pos,
			'Logo class should appear after the register_dashboard_widget function definition'
		);
	}

	/**
	 * Test that the widget links to the Metrics page.
	 */
	public function test_widget_links_to_metrics_page() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash-nazg-metrics',
			$admin_contents,
			'Widget should link to the plugin Metrics page'
		);
	}
}
