<?php
/**
 * Backups UI tests — screen options, tabs, column layout.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test backups page UI: screen options age filter, env tabs, element column.
 */
class Test_Backups_UI extends TestCase {

	/**
	 * Test that the backups screen options hook is registered.
	 */
	public function test_backups_screen_options_registered() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'load-ash-nazg_page_ash-nazg-backups',
			$admin_contents,
			'Backups screen options hook should be registered on load-ash-nazg_page_ash-nazg-backups'
		);
	}

	/**
	 * Test that backups screen options includes the three age filter values.
	 */
	public function test_backups_screen_options_age_values() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_backups_max_age',
			$admin_contents,
			'Backups screen options should use ash_nazg_backups_max_age user meta key'
		);

		// The three threshold values must appear in the screen options output.
		foreach ( [ '7', '30', '365' ] as $days ) {
			$this->assertMatchesRegularExpression(
				'/ash_nazg_backups_max_age.*' . $days . '|' . $days . '.*ash_nazg_backups_max_age/s',
				$admin_contents,
				"Backups screen options should include {$days}-day age filter option"
			);
		}
	}

	/**
	 * Test that handle_screen_options_submission handles the backups page.
	 */
	public function test_backups_screen_options_submission_handled() {
		$admin_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/ash-nazg-backups.*ash_nazg_backups_max_age|ash_nazg_backups_max_age.*ash-nazg-backups/s',
			$admin_contents,
			'handle_screen_options_submission should handle ash-nazg-backups page for age filter'
		);
	}

	/**
	 * Test that the backups view renders environment tabs.
	 */
	public function test_backups_view_has_env_tabs() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/backups.php' );

		$this->assertStringContainsString(
			'nav-tab-wrapper',
			$view_contents,
			'Backups view should use nav-tab-wrapper for environment tabs'
		);
		$this->assertStringContainsString(
			'nav-tab',
			$view_contents,
			'Backups view should use nav-tab class for environment tab links'
		);
	}

	/**
	 * Test that the element column has a width class applied.
	 */
	public function test_backups_element_column_has_width_class() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/backups.php' );

		$this->assertMatchesRegularExpression(
			'/class="[^"]*ash-nazg-backup-element-col[^"]*"/',
			$view_contents,
			'Element <th> should have ash-nazg-backup-element-col class for wider column'
		);
	}

	/**
	 * Test that the element column CSS class is defined.
	 */
	public function test_backups_element_col_css_defined() {
		$css_contents = file_get_contents( __DIR__ . '/../assets/css/admin.css' );

		$this->assertStringContainsString(
			'ash-nazg-backup-element-col',
			$css_contents,
			'admin.css should define .ash-nazg-backup-element-col with a width'
		);
	}

	/**
	 * Test that age filtering uses the stored user meta preference.
	 */
	public function test_backups_view_references_max_age() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/backups.php' );

		$this->assertStringContainsString(
			'max_age',
			$view_contents,
			'Backups view should reference max_age for filtering backup sets by age'
		);
	}
}
