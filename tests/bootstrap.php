<?php
/**
 * PHPUnit bootstrap file for Ash-Nazg plugin tests.
 *
 * @package Pantheon\AshNazg
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Plugin file.
define( 'ASH_NAZG_PLUGIN_FILE', dirname( __DIR__ ) . '/ash-nazg.php' );

// Load the real WordPress test suite if it's been installed (composer test:install[:withdb]).
$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
if ( file_exists( $wp_tests_dir . '/includes/bootstrap.php' ) ) {
	require_once $wp_tests_dir . '/includes/bootstrap.php';
}
