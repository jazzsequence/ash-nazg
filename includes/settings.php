<?php
/**
 * Settings management functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\Settings;

use Pantheon\AshNazg\API;

/**
 * Initialize settings.
 *
 * @return void
 */
function init() {
	add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
}

/**
 * Register plugin settings.
 *
 * @return void
 */
function register_settings() {
	register_setting(
		'ash_nazg_settings',
		'ash_nazg_machine_token',
		array(
			'type'              => 'string',
			'description'       => __( 'Pantheon machine token for API authentication', 'ash-nazg' ),
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => false,
			'default'           => '',
		)
	);
}

/**
 * Render settings page.
 *
 * @return void
 */
function render_settings_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle settings update.
	$message = '';
	if ( isset( $_POST['ash_nazg_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_settings_nonce'] ) ), 'ash_nazg_settings' ) ) {
		if ( isset( $_POST['ash_nazg_machine_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['ash_nazg_machine_token'] ) );
			update_option( 'ash_nazg_machine_token', $token );

			// Clear API cache when token changes.
			API\clear_cache();

			$message = __( 'Settings saved successfully.', 'ash-nazg' );
		}
	}

	// Handle test connection.
	$test_result = null;
	if ( isset( $_POST['test_connection_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['test_connection_nonce'] ) ), 'test_connection' ) ) {
		$test_result = API\test_connection();
	}

	$is_pantheon = API\is_pantheon();
	$has_secret_api = function_exists( 'pantheon_get_secret' );
	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();
	$machine_token = API\get_machine_token();

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/settings.php';
}
