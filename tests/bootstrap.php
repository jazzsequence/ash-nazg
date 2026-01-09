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

// Load WPUnit Helpers if available.
if ( file_exists( dirname( __DIR__ ) . '/vendor/pantheon-systems/wpunit-helpers/bootstrap.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/pantheon-systems/wpunit-helpers/bootstrap.php';
}
