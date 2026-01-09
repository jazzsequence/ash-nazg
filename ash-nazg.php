<?php
/**
 * Plugin Name: Ash-Nazg - Pantheon Integration
 * Plugin URI: https://github.com/pantheon-systems/ash-nazg
 * Description: Integrates Pantheon Public API into WordPress admin dashboard
 * Version: 0.1.0
 * Author: Pantheon
 * Author URI: https://pantheon.io
 * License: MIT
 * Text Domain: ash-nazg
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'ASH_NAZG_VERSION', '0.1.0' );
define( 'ASH_NAZG_PLUGIN_FILE', __FILE__ );
define( 'ASH_NAZG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASH_NAZG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Bootstrap the plugin.
 *
 * Loads all plugin files and initializes hooks.
 *
 * @return void
 */
function bootstrap() {
	// Load core files.
	require_once ASH_NAZG_PLUGIN_DIR . 'includes/api.php';
	require_once ASH_NAZG_PLUGIN_DIR . 'includes/settings.php';
	require_once ASH_NAZG_PLUGIN_DIR . 'includes/admin.php';

	// Initialize admin interface.
	if ( is_admin() ) {
		Admin\init();
	}

	// Initialize settings.
	Settings\init();
}

// Bootstrap the plugin.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
