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
 * Check if dev environment has commits that target environment doesn't have.
 *
 * This checks if dev has changes that can be merged into the target environment.
 * Returns false if target is ahead of dev or if they're in sync.
 *
 * @param string $site_id Site UUID.
 * @param string $target_env Target environment name to compare against dev.
 * @return bool True if dev has commits not in target (changes to merge), false otherwise.
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

	// Build set of all commit hashes in both environments.
	$dev_hashes = [];
	foreach ( $dev_commits as $commit ) {
		$hash = $commit['hash'] ?? $commit['id'] ?? null;
		if ( $hash ) {
			$dev_hashes[ $hash ] = true;
		}
	}

	$target_hashes = [];
	foreach ( $target_commits as $commit ) {
		$hash = $commit['hash'] ?? $commit['id'] ?? null;
		if ( $hash ) {
			$target_hashes[ $hash ] = true;
		}
	}

	// Check if dev has any commits not in target environment.
	$dev_unique = 0;
	foreach ( $dev_commits as $commit ) {
		$hash = $commit['hash'] ?? $commit['id'] ?? null;
		if ( $hash && ! isset( $target_hashes[ $hash ] ) ) {
			++$dev_unique;
		}
	}

	// Check if target has any commits not in dev environment.
	$target_unique = 0;
	foreach ( $target_commits as $commit ) {
		$hash = $commit['hash'] ?? $commit['id'] ?? null;
		if ( $hash && ! isset( $dev_hashes[ $hash ] ) ) {
			++$target_unique;
		}
	}

	/*
	 * Find the most recent dev commit that exists in target
	 * (accounts for merges). The position in dev's list tells us
	 * how many commits target is behind.
	 * - If shared commit is at dev[0-9]: target is < 10 behind
	 *   = up-to-date enough.
	 * - If shared commit is at dev[10+]: target is >= 10 behind
	 *   = show merge button.
	 */
	if ( ! empty( $dev_commits ) && ! empty( $target_commits ) ) {
		/* Check dev commits from newest to oldest. */
		foreach ( $dev_commits as $index => $commit ) {
			$dev_hash = $commit['hash'] ?? $commit['id'] ?? null;
			if ( $dev_hash && isset( $target_hashes[ $dev_hash ] ) ) {
				/*
				 * Found most recent shared commit at position $index in dev.
				 * If it's within first 10 commits, target is reasonably
				 * up-to-date.
				 */
				return $index >= 10;
			}
		}
		/* No shared commits found in entire window = definitely behind. */
		return true;
	}

	return false;
}

/**
 * Filter upstream updates to only include those not in current environment.
 *
 * @param array  $upstream_updates Site-wide upstream updates from API.
 * @param string $site_id Site UUID.
 * @param string $env Environment name.
 * @return array Filtered upstream updates for current environment only.
 */
function filter_upstream_updates_for_env( $upstream_updates, $site_id, $env ) {
	if ( ! $upstream_updates || is_wp_error( $upstream_updates ) || ! is_array( $upstream_updates ) ) {
		return [];
	}

	// Get commits in current environment.
	$env_commits = \Pantheon\AshNazg\API\get_environment_commits( $site_id, $env );
	if ( ! $env_commits || is_wp_error( $env_commits ) || ! is_array( $env_commits ) ) {
		// If we can't get env commits, return all upstream updates (safer).
		return $upstream_updates;
	}

	// Build set of commit hashes in current environment.
	$env_hashes = [];
	foreach ( $env_commits as $commit ) {
		$hash = $commit['hash'] ?? $commit['id'] ?? null;
		if ( $hash ) {
			$env_hashes[ $hash ] = true;
		}
	}

	// Filter out upstream commits already in this environment.
	$filtered_updates = $upstream_updates;
	if ( isset( $upstream_updates['update_log'] ) && is_array( $upstream_updates['update_log'] ) ) {
		$filtered_log = [];
		foreach ( $upstream_updates['update_log'] as $update ) {
			$hash = $update['hash'] ?? $update['commit'] ?? null;
			// Only include if not already in environment.
			if ( $hash && ! isset( $env_hashes[ $hash ] ) ) {
				$filtered_log[] = $update;
			}
		}
		$filtered_updates['update_log'] = $filtered_log;
		$filtered_updates['behind'] = count( $filtered_log );
	}

	return $filtered_updates;
}
