<?php
/**
 * Metrics UI tests — screen options, debug gating, layout order.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test metrics page UI: screen options, debug section, summary position.
 */
class Test_Metrics_UI extends TestCase {

	/**
	 * Test that the metrics screen options hook is registered.
	 */
	public function test_metrics_screen_options_registered() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'load-ash-nazg_page_ash-nazg-metrics',
			$admin_contents,
			'Metrics screen options hook should be registered on load-ash-nazg_page_ash-nazg-metrics'
		);
	}

	/**
	 * Test that metrics screen options includes the three chart toggle options.
	 */
	public function test_metrics_screen_options_chart_options() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_metrics_hidden_charts',
			$admin_contents,
			'Metrics screen options should use ash_nazg_metrics_hidden_charts user meta key'
		);

		foreach ( [ 'pages_served', 'unique_visits', 'cache_performance' ] as $chart ) {
			$this->assertStringContainsString(
				$chart,
				$admin_contents,
				"Metrics screen options should include {$chart} toggle"
			);
		}
	}

	/**
	 * Test that handle_screen_options_submission handles the metrics page.
	 */
	public function test_metrics_screen_options_submission_handled() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/ash-nazg-metrics.*ash_nazg_metrics_hidden_charts|ash_nazg_metrics_hidden_charts.*ash-nazg-metrics/s',
			$admin_contents,
			'handle_screen_options_submission should handle ash-nazg-metrics page'
		);
	}

	/**
	 * Test that the debug section is gated on a debug flag in the render function.
	 */
	public function test_metrics_debug_mode_passed_to_view() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'debug_mode',
			$admin_contents,
			'render_metrics_page should detect and pass $debug_mode to the view'
		);
	}

	/**
	 * Test that the debug section in the view is wrapped in a debug_mode check.
	 */
	public function test_metrics_view_debug_section_gated() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/metrics.php' );

		$this->assertMatchesRegularExpression(
			'/debug_mode.*API Request Details|API Request Details.*debug_mode/s',
			$view_contents,
			'Debug API details section should only render when $debug_mode is true'
		);
	}

	/**
	 * Test that summary statistics appear before the filters in the view.
	 */
	public function test_metrics_summary_before_filters() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/metrics.php' );

		$summary_pos = strpos( $view_contents, 'Summary Statistics' );
		$filters_pos = strpos( $view_contents, 'Metrics Filters' );

		$this->assertNotFalse( $summary_pos, 'Summary Statistics heading should exist in view' );
		$this->assertNotFalse( $filters_pos, 'Metrics Filters heading should exist in view' );
		$this->assertLessThan(
			$filters_pos,
			$summary_pos,
			'Summary Statistics should appear before Metrics Filters in the view'
		);
	}

	/**
	 * Test that the filters section is hidden when all charts are hidden.
	 */
	public function test_metrics_filters_hidden_when_no_charts() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/metrics.php' );

		$this->assertMatchesRegularExpression(
			'/hidden_charts.*Metrics Filters|Metrics Filters.*hidden_charts/s',
			$view_contents,
			'Metrics Filters section should be conditional on at least one chart being visible'
		);
	}
}
