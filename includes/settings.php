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
		[
			'type'              => 'string',
			'description'       => __( 'Pantheon machine token for API authentication', 'ash-nazg' ),
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => false,
			'default'           => '',
		]
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

	$user_id = get_current_user_id();

	// Check for migration success message from transient.
	$message = '';
	$migration_message = get_transient( sprintf( 'ash_nazg_migration_success_%d', $user_id ) );
	if ( $migration_message ) {
		$message = $migration_message;
		delete_transient( sprintf( 'ash_nazg_migration_success_%d', $user_id ) );
	}

	// Handle settings update.
	if ( isset( $_POST['ash_nazg_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_settings_nonce'] ) ), 'ash_nazg_settings' ) ) {
		if ( isset( $_POST['ash_nazg_machine_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['ash_nazg_machine_token'] ) );

			// Encrypt token before storing in user meta.
			$encrypted_token = API\encrypt_token( $token );
			update_user_meta( $user_id, 'ash_nazg_user_machine_token', $encrypted_token );

			// Clear user's session token when token changes.
			API\clear_user_session_token( $user_id );
			API\clear_cache();

			$message = __( 'Settings saved successfully. Your machine token has been encrypted and stored.', 'ash-nazg' );
		}
	}

	// Handle test connection.
	$test_result = null;
	if ( isset( $_POST['test_connection_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['test_connection_nonce'] ) ), 'test_connection' ) ) {
		$test_result = API\test_connection();
	}

	// Handle clear session token.
	if ( isset( $_POST['clear_session_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clear_session_nonce'] ) ), 'clear_session' ) ) {
		API\clear_user_session_token( $user_id );
		$message = __( 'Session token cleared successfully. A new session token will be generated on the next API request.', 'ash-nazg' );
	}

	$is_pantheon = API\is_pantheon();
	$has_secret_api = function_exists( 'pantheon_get_secret' );
	$site_id = API\get_pantheon_site_id();
	$site_name = isset( $_ENV['PANTHEON_SITE_NAME'] ) ? sanitize_text_field( wp_unslash( $_ENV['PANTHEON_SITE_NAME'] ) ) : '';
	$environment = API\get_pantheon_environment();

	// Check if user has per-user token.
	$user_token = get_user_meta( $user_id, 'ash_nazg_user_machine_token', true );
	$has_user_token = ! empty( $user_token );

	// Check if user has Pantheon Secret set.
	$has_user_secret = false;
	if ( $has_secret_api ) {
		$secret_key = sprintf( 'ash_nazg_machine_token_%d', $user_id );
		$secret_value = pantheon_get_secret( $secret_key );
		$has_user_secret = ! empty( $secret_value );
	}

	// Check for global token (migration detection).
	$global_token = get_option( 'ash_nazg_machine_token' );
	$global_secret = $has_secret_api ? pantheon_get_secret( 'ash_nazg_machine_token' ) : false;
	$has_global_token = ! empty( $global_token ) || ! empty( $global_secret );

	// For backward compatibility: use user-scoped token if available, otherwise global.
	$machine_token = API\get_user_machine_token( $user_id );

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/settings.php';
}
