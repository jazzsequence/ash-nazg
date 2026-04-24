<?php
/**
 * Development page UI tests — screen options section visibility.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test development page screen options for section visibility.
 */
class Test_Development_UI extends TestCase {

	/**
	 * Test that the development screen options hook is already registered.
	 */
	public function test_development_screen_options_registered() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'load-ash-nazg_page_ash-nazg-development',
			$admin_contents,
			'Development screen options hook should be registered'
		);
	}

	/**
	 * Test that development screen options include Environments toggle.
	 */
	public function test_development_screen_options_includes_environments() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_dev_hidden_sections',
			$admin_contents,
			'Development screen options should use ash_nazg_dev_hidden_sections user meta key'
		);
		$this->assertStringContainsString(
			'environments',
			$admin_contents,
			'Development screen options should include environments section toggle'
		);
	}

	/**
	 * Test that development screen options include Multidev Management toggle.
	 */
	public function test_development_screen_options_includes_multidevs() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'multidevs',
			$admin_contents,
			'Development screen options should include multidevs section toggle'
		);
	}

	/**
	 * Test that handle_screen_options_submission handles the development page sections.
	 */
	public function test_development_screen_options_submission_handled() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/ash-nazg-development.*ash_nazg_dev_hidden_sections|ash_nazg_dev_hidden_sections.*ash-nazg-development/s',
			$admin_contents,
			'handle_screen_options_submission should handle ash-nazg-development page for section visibility'
		);
	}

	/**
	 * Test that the Environments card is conditionally rendered.
	 */
	public function test_development_view_environments_conditional() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/development.php' );

		$this->assertMatchesRegularExpression(
			'/hidden_sections.*Environments|Environments.*hidden_sections/s',
			$view_contents,
			'Environments card should be gated on hidden_sections'
		);
	}

	/**
	 * Test that the Multidev Management card is conditionally rendered.
	 */
	public function test_development_view_multidevs_conditional() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/development.php' );

		$this->assertMatchesRegularExpression(
			'/hidden_sections.*Multidev Management|Multidev Management.*hidden_sections/s',
			$view_contents,
			'Multidev Management card should be gated on hidden_sections'
		);
	}
}
