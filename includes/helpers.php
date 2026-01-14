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

/**
 * Check if current environment is a local development environment.
 *
 * @param string|null $env Optional environment name to check. If null, uses current environment.
 * @return bool True if local environment (lando, local, localhost, ddev), false otherwise.
 */
function is_local_environment( $env = null ) {
	if ( null === $env ) {
		$env = \Pantheon\AshNazg\API\get_pantheon_environment();
	}

	if ( ! $env ) {
		return false;
	}

	$local_envs = [ 'lando', 'local', 'localhost', 'ddev' ];
	return in_array( strtolower( $env ), $local_envs, true );
}

/**
 * Check if current environment is a multidev environment.
 *
 * @param string|null $env Optional environment name to check. If null, uses current environment.
 * @return bool True if multidev (not dev/test/live and not local), false otherwise.
 */
function is_multidev_environment( $env = null ) {
	if ( null === $env ) {
		$env = \Pantheon\AshNazg\API\get_pantheon_environment();
	}

	if ( ! $env ) {
		return false;
	}

	// Must be on Pantheon (not local).
	if ( ! \Pantheon\AshNazg\API\is_pantheon() ) {
		return false;
	}

	// Must not be a local environment.
	if ( is_local_environment( $env ) ) {
		return false;
	}

	// Must not be a standard environment.
	$standard_envs = [ 'dev', 'test', 'live' ];
	return ! in_array( $env, $standard_envs, true );
}

/**
 * Check if dev environment has different commits than target environment.
 *
 * @param string $site_id Site UUID.
 * @param string $target_env Target environment name to compare against dev.
 * @return bool True if dev has different commits (changes to merge), false otherwise.
 */
function dev_has_changes_for_env( $site_id, $target_env ) {
	$dev_commits = \Pantheon\AshNazg\API\get_environment_commits( $site_id, 'dev' );
	$target_commits = \Pantheon\AshNazg\API\get_environment_commits( $site_id, $target_env );

	if ( ! $dev_commits || is_wp_error( $dev_commits ) || ! is_array( $dev_commits ) || empty( $dev_commits ) ) {
		return false;
	}

	if ( ! $target_commits || is_wp_error( $target_commits ) || ! is_array( $target_commits ) || empty( $target_commits ) ) {
		return false;
	}

	$dev_latest_hash = $dev_commits[0]['hash'] ?? $dev_commits[0]['id'] ?? null;
	$target_latest_hash = $target_commits[0]['hash'] ?? $target_commits[0]['id'] ?? null;

	if ( ! $dev_latest_hash || ! $target_latest_hash ) {
		return false;
	}

	return $dev_latest_hash !== $target_latest_hash;
}
