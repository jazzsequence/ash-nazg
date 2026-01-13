<?php
/**
 * Helper functions for common patterns.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\Helpers;

/**
 * Log debug message if WP_DEBUG is enabled.
 *
 * Replaces the repeated pattern:
 * if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
 *     error_log( 'Ash-Nazg: message' );
 * }
 *
 * @param string $message Debug message to log (will be prefixed with 'Ash-Nazg: ').
 * @return void
 */
function debug_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Ash-Nazg: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * Verify AJAX nonce and user capabilities.
 *
 * Replaces the repeated pattern:
 * check_ajax_referer( 'action', 'nonce' );
 * if ( ! current_user_can( 'manage_options' ) ) {
 *     wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
 * }
 *
 * @param string $nonce_action Nonce action name.
 * @param string $nonce_field Nonce field name (default: 'nonce').
 * @param string $capability Required capability (default: 'manage_options').
 * @return void Dies with JSON error if checks fail.
 */
function verify_ajax_request( $nonce_action, $nonce_field = 'nonce', $capability = 'manage_options' ) {
	check_ajax_referer( $nonce_action, $nonce_field );

	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
	}
}
