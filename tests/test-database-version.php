<?php
/**
 * Database version parsing tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;
use Pantheon\AshNazg\Helpers;

/**
 * Test parsing of the database server version string.
 */
class Test_Database_Version extends TestCase {

	/**
	 * Test the real Pantheon MariaDB string (not ProxySQL's emulated 5.5.30).
	 */
	public function test_parses_mariadb_version() {
		$this->assertSame(
			[
				'name' => 'MariaDB',
				'version' => '10.4.34',
			],
			Helpers\parse_database_version( '10.4.34-MariaDB-1:10.4.34+maria~ubu2004-log' )
		);
	}

	/**
	 * Test the replication-compat "5.5.5-" prefix older MariaDB releases add.
	 */
	public function test_strips_replication_prefix() {
		$this->assertSame(
			[
				'name' => 'MariaDB',
				'version' => '10.3.39',
			],
			Helpers\parse_database_version( '5.5.5-10.3.39-MariaDB' )
		);
	}

	/**
	 * Test a plain MySQL version.
	 */
	public function test_parses_mysql_version() {
		$this->assertSame(
			[
				'name' => 'MySQL',
				'version' => '8.0.36',
			],
			Helpers\parse_database_version( '8.0.36-0ubuntu0.22.04.1' )
		);
	}

	/**
	 * Test the dashboard no longer shows the ProxySQL-emulated db_version().
	 */
	public function test_dashboard_does_not_use_db_version() {
		$view = file_get_contents( __DIR__ . '/../includes/views/dashboard.php' );

		$this->assertStringNotContainsString( 'db_version()', $view );
		$this->assertStringContainsString( 'get_database_version()', $view );
	}
}
