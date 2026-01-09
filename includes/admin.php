<?php
/**
 * Admin interface functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\Admin;

use Pantheon\AshNazg\API;

/**
 * Initialize admin interface.
 *
 * @return void
 */
function init() {
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_admin_menu' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets' );
}

/**
 * Add admin menu pages.
 *
 * @return void
 */
function add_admin_menu() {
	// Main dashboard page.
	add_menu_page(
		__( 'Pantheon', 'ash-nazg' ),
		__( 'Pantheon', 'ash-nazg' ),
		'manage_options',
		'ash-nazg',
		__NAMESPACE__ . '\\render_dashboard_page',
		'dashicons-cloud',
		80
	);

	// Settings page.
	add_submenu_page(
		'ash-nazg',
		__( 'Pantheon Settings', 'ash-nazg' ),
		__( 'Settings', 'ash-nazg' ),
		'manage_options',
		'ash-nazg-settings',
		'Pantheon\\AshNazg\\Settings\\render_settings_page'
	);
}

/**
 * Enqueue admin assets.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function enqueue_assets( $hook ) {
	// Only load on our admin pages.
	if ( ! str_starts_with( $hook, 'toplevel_page_ash-nazg' ) && ! str_starts_with( $hook, 'pantheon_page_ash-nazg' ) ) {
		return;
	}

	// Enqueue admin styles.
	wp_enqueue_style(
		'ash-nazg-admin',
		ASH_NAZG_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		ASH_NAZG_VERSION
	);
}

/**
 * Render dashboard page.
 *
 * @return void
 */
function render_dashboard_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle cache refresh request.
	$refresh_message = null;
	if ( isset( $_GET['refresh_cache'] ) && isset( $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ash_nazg_refresh_cache' ) ) {
			API\clear_cache();
			$refresh_message = __( 'API cache cleared. Data refreshed.', 'ash-nazg' );
		}
	}

	// Get Pantheon environment info.
	$is_pantheon = API\is_pantheon();
	$site_id     = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	// Try to get site and environment info from API.
	$site_info        = null;
	$environment_info = null;
	$api_error        = null;
	$env_info_notice  = null;
	$endpoints_status = array();

	if ( $is_pantheon ) {
		$site_info = API\get_site_info();
		if ( is_wp_error( $site_info ) ) {
			$api_error = $site_info;
			$site_info = null;
		}

		$environment_info = API\get_environment_info();
		if ( is_wp_error( $environment_info ) ) {
			// "environment_not_found" is expected for local environments like "lando".
			if ( 'environment_not_found' === $environment_info->get_error_code() ) {
				$env_info_notice  = $environment_info;
				$environment_info = null;
			} else {
				// Other errors are real API problems.
				if ( null === $api_error ) {
					$api_error = $environment_info;
				}
				$environment_info = null;
			}
		}

		// Get endpoints status if we can connect to the API.
		// We fetch endpoints even if the environment doesn't exist on Pantheon (local dev).
		if ( null === $api_error ) {
			$endpoints_data = API\get_endpoints_status( $site_id, $environment );
			// Handle both old and new cache formats during transition.
			if ( isset( $endpoints_data['all'] ) ) {
				// New format with separate groups.
				$endpoints_site = $endpoints_data['site'];
				$endpoints_user = $endpoints_data['user'];
				$endpoints_all = $endpoints_data['all'];
			} else {
				// Old cache format - data is the categories directly.
				$endpoints_site = $endpoints_data;
				$endpoints_user = array();
				$endpoints_all = $endpoints_data;
			}
		}
	}

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/dashboard.php';
}
