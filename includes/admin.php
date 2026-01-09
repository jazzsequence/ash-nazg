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
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_addon_form_submission' );
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_workflow_form_submission' );
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_connection_mode_toggle' );
	add_action( 'wp_ajax_ash_nazg_fetch_logs', __NAMESPACE__ . '\\ajax_fetch_logs' );
}

/**
 * Add admin menu pages.
 *
 * @return void
 */
function add_admin_menu() {
	// Main dashboard page.
	add_menu_page(
		__( 'Pantheon Dashboard', 'ash-nazg' ),
		__( 'Ash Nazg', 'ash-nazg' ),
		'manage_options',
		'ash-nazg',
		__NAMESPACE__ . '\\render_dashboard_page',
		'dashicons-marker',
		80
	);

	// Addons page.
	add_submenu_page(
		'ash-nazg',
		__( 'Site Addons', 'ash-nazg' ),
		__( 'Addons', 'ash-nazg' ),
		'manage_options',
		'ash-nazg-addons',
		__NAMESPACE__ . '\\render_addons_page'
	);

	// Workflows page.
	add_submenu_page(
		'ash-nazg',
		__( 'Pantheon Workflows', 'ash-nazg' ),
		__( 'Workflows', 'ash-nazg' ),
		'manage_options',
		'ash-nazg-workflows',
		__NAMESPACE__ . '\\render_workflows_page'
	);

	// Logs page.
	add_submenu_page(
		'ash-nazg',
		__( 'Debug Logs', 'ash-nazg' ),
		__( 'Logs', 'ash-nazg' ),
		'manage_options',
		'ash-nazg-logs',
		__NAMESPACE__ . '\\render_logs_page'
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
	$ash_nazg_pages = array(
		'toplevel_page_ash-nazg',
		'ash-nazg_page_ash-nazg-addons',
		'ash-nazg_page_ash-nazg-workflows',
		'ash-nazg_page_ash-nazg-logs',
		'ash-nazg_page_ash-nazg-settings',
	);

	$is_ash_nazg_page = false;
	foreach ( $ash_nazg_pages as $page ) {
		if ( str_starts_with( $hook, $page ) ) {
			$is_ash_nazg_page = true;
			break;
		}
	}

	if ( ! $is_ash_nazg_page ) {
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

	// Handle connection mode toggle messages.
	$mode_message = null;
	$mode_error = null;

	if ( isset( $_GET['mode_changed'] ) && isset( $_GET['new_mode'] ) ) {
		$new_mode = sanitize_text_field( wp_unslash( $_GET['new_mode'] ) );
		if ( 'sftp' === $new_mode ) {
			$mode_message = __( 'Switched to SFTP mode. You can now install/update plugins and themes.', 'ash-nazg' );
		} else {
			$mode_message = __( 'Switched to Git mode. Changes must be committed via Git.', 'ash-nazg' );
		}
	}

	if ( isset( $_GET['mode_error'] ) ) {
		if ( 'invalid_mode' === $_GET['mode_error'] ) {
			$mode_error = __( 'Invalid connection mode specified.', 'ash-nazg' );
		} else {
			$stored_error = get_transient( 'ash_nazg_mode_error' );
			if ( $stored_error ) {
				$mode_error = $stored_error;
				delete_transient( 'ash_nazg_mode_error' );
			}
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

		// Sync environment state if we successfully fetched environment info.
		if ( null !== $environment_info && null === $api_error ) {
			API\sync_environment_state( $site_id, $environment );
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

	// Get cache timestamps.
	$site_info_cached_at = null;
	$env_info_cached_at = null;
	$endpoints_cached_at = null;

	if ( $is_pantheon && $site_id ) {
		$site_info_cached_at = API\get_cache_timestamp( 'ash_nazg_site_info_' . $site_id );
		$env_info_cached_at = API\get_cache_timestamp( sprintf( 'ash_nazg_all_env_info_%s', $site_id ) );
		$endpoints_cached_at = API\get_cache_timestamp( sprintf( 'ash_nazg_endpoints_status_%s_%s', $site_id, $environment ) );
	}

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/dashboard.php';
}

/**
 * Handle addon form submission.
 *
 * Runs on admin_init to process form before any output.
 *
 * @return void
 */
function handle_addon_form_submission() {
	// Only process on addon page submissions.
	if ( ! isset( $_POST['ash_nazg_addons_nonce'] ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_addons_nonce'] ) ), 'ash_nazg_update_addons' ) ) {
		return;
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();

	if ( $site_id && isset( $_POST['submit'] ) ) {
		$updated_count = 0;
		$errors = array();

		// Get list of all known addons with their current state.
		$all_addons = API\get_site_addons( $site_id );
		if ( ! is_wp_error( $all_addons ) ) {
			// Process each addon - only update if state has changed.
			foreach ( $all_addons as $addon ) {
				$addon_id = $addon['id'];
				$old_state = isset( $addon['enabled'] ) ? (bool) $addon['enabled'] : false;
				// Checkbox checked = enabled, checkbox unchecked = disabled.
				$new_state = isset( $_POST['addons'][ $addon_id ] ) && 'on' === $_POST['addons'][ $addon_id ];

				// Only send API request if state has changed.
				if ( $old_state !== $new_state ) {
					$result = API\update_site_addon( $site_id, $addon_id, $new_state );

					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf(
							/* translators: 1: addon ID, 2: error message */
							__( 'Failed to update %1$s: %2$s', 'ash-nazg' ),
							$addon_id,
							$result->get_error_message()
						);
					} else {
						$updated_count++;
					}
				}
			}
		}

		// Set success/error messages and redirect to avoid form resubmission.
		$redirect_args = array( 'page' => 'ash-nazg-addons' );

		if ( $updated_count > 0 ) {
			$redirect_args['updated'] = $updated_count;
		}

		if ( ! empty( $errors ) ) {
			$redirect_args['error'] = '1';
			// Store errors in transient for display after redirect.
			set_transient( 'ash_nazg_addon_errors', $errors, 30 );
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

/**
 * Render addons page.
 *
 * @return void
 */
function render_addons_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = null;
	$error = null;

	// Handle redirect messages.
	if ( isset( $_GET['updated'] ) ) {
		$updated_count = absint( $_GET['updated'] );
		$message = sprintf(
			/* translators: %d: number of addons updated */
			_n( '%d addon updated successfully.', '%d addons updated successfully.', $updated_count, 'ash-nazg' ),
			$updated_count
		);
	}

	if ( isset( $_GET['error'] ) ) {
		$stored_errors = get_transient( 'ash_nazg_addon_errors' );
		if ( $stored_errors ) {
			$error = implode( '<br>', $stored_errors );
			delete_transient( 'ash_nazg_addon_errors' );
		}
	}

	// Get current addon states.
	$site_id = API\get_pantheon_site_id();
	$addons = array();
	$api_error = null;

	if ( $site_id ) {
		$addons = API\get_site_addons( $site_id );
		if ( is_wp_error( $addons ) ) {
			$api_error = $addons;
			$addons = array();
		}
	}

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/addons.php';
}

/**
 * Handle workflow form submission.
 *
 * Runs on admin_init to process form before any output.
 *
 * @return void
 */
function handle_workflow_form_submission() {
	// Only process on workflow page submissions.
	if ( ! isset( $_POST['ash_nazg_workflows_nonce'] ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_workflows_nonce'] ) ), 'ash_nazg_trigger_workflow' ) ) {
		return;
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( $site_id && isset( $_POST['workflow_id'] ) ) {
		$workflow_id = sanitize_text_field( wp_unslash( $_POST['workflow_id'] ) );
		$available_workflows = API\get_available_workflows();

		if ( isset( $available_workflows[ $workflow_id ] ) ) {
			$workflow = $available_workflows[ $workflow_id ];

			// Check if environment is allowed.
			if ( ! in_array( $environment, $workflow['allowed_envs'], true ) ) {
				$redirect_args = array(
					'page' => 'ash-nazg-workflows',
					'error' => 'invalid_env',
				);
				wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
				exit;
			}

			// Trigger the workflow.
			$result = API\trigger_workflow(
				$site_id,
				$environment,
				$workflow['workflow_type'],
				$workflow['params']
			);

			$redirect_args = array( 'page' => 'ash-nazg-workflows' );

			if ( is_wp_error( $result ) ) {
				$redirect_args['error'] = '1';
				set_transient( 'ash_nazg_workflow_error', $result->get_error_message(), 30 );
			} else {
				$redirect_args['triggered'] = '1';
				// Store workflow ID if available in response.
				if ( isset( $result['id'] ) ) {
					set_transient( 'ash_nazg_workflow_id', $result['id'], 30 );
				}
			}

			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}
	}
}

/**
 * Handle connection mode toggle form submission.
 *
 * Runs on admin_init to process form before any output.
 *
 * @return void
 */
function handle_connection_mode_toggle() {
	// Only process on connection mode toggle submissions.
	if ( ! isset( $_POST['ash_nazg_connection_mode_nonce'] ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_connection_mode_nonce'] ) ), 'ash_nazg_toggle_connection_mode' ) ) {
		return;
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( $site_id && $environment && isset( $_POST['connection_mode'] ) ) {
		$new_mode = sanitize_text_field( wp_unslash( $_POST['connection_mode'] ) );

		// Validate mode.
		if ( ! in_array( $new_mode, array( 'sftp', 'git' ), true ) ) {
			$redirect_args = array(
				'page' => 'ash-nazg',
				'mode_error' => 'invalid_mode',
			);
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// Toggle connection mode.
		$result = API\update_connection_mode( $site_id, $environment, $new_mode );

		$redirect_args = array( 'page' => 'ash-nazg' );

		if ( is_wp_error( $result ) ) {
			$redirect_args['mode_error'] = '1';
			set_transient( 'ash_nazg_mode_error', $result->get_error_message(), 30 );
		} else {
			$redirect_args['mode_changed'] = '1';
			$redirect_args['new_mode'] = $new_mode;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

/**
 * Render workflows page.
 *
 * @return void
 */
function render_workflows_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = null;
	$error = null;
	$workflow_id = null;

	// Handle redirect messages.
	if ( isset( $_GET['triggered'] ) ) {
		$message = __( 'Workflow triggered successfully.', 'ash-nazg' );
		$stored_workflow_id = get_transient( 'ash_nazg_workflow_id' );
		if ( $stored_workflow_id ) {
			$workflow_id = $stored_workflow_id;
			delete_transient( 'ash_nazg_workflow_id' );
		}
	}

	if ( isset( $_GET['error'] ) ) {
		if ( 'invalid_env' === $_GET['error'] ) {
			$error = __( 'This workflow can only be triggered on dev or local development environments.', 'ash-nazg' );
		} else {
			$stored_error = get_transient( 'ash_nazg_workflow_error' );
			if ( $stored_error ) {
				$error = $stored_error;
				delete_transient( 'ash_nazg_workflow_error' );
			}
		}
	}

	// Get available workflows.
	$workflows = API\get_available_workflows();

	// Get current environment.
	$environment = API\get_pantheon_environment();
	$site_id = API\get_pantheon_site_id();

	// Get workflow status if we have a workflow ID.
	$workflow_status = null;
	if ( $workflow_id ) {
		$workflow_status = API\get_workflow_status( $workflow_id );
	}

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/workflows.php';
}

/**
 * AJAX handler to fetch debug logs.
 *
 * Fetches debug.log, switching to SFTP mode if necessary.
 *
 * @return void
 */
function ajax_fetch_logs() {
	// Check nonce.
	check_ajax_referer( 'ash_nazg_fetch_logs', 'nonce' );

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ash-nazg' ) ) );
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( ! $site_id || ! $environment ) {
		error_log( 'Ash-Nazg: AJAX fetch logs - Not running on Pantheon' );
		wp_send_json_error( array( 'message' => __( 'Not running on Pantheon.', 'ash-nazg' ) ) );
	}

	error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Site: %s, Env: %s', $site_id, $environment ) );

	// Get current connection mode from state.
	$original_mode = API\get_connection_mode();
	if ( ! $original_mode ) {
		// Sync state if not known.
		error_log( 'Ash-Nazg: AJAX fetch logs - Syncing environment state' );
		API\sync_environment_state( $site_id, $environment );
		$original_mode = API\get_connection_mode();
	}

	error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Original mode: %s', $original_mode ) );

	$switched_mode = false;

	// If in Git mode, switch to SFTP temporarily.
	if ( 'git' === $original_mode ) {
		error_log( 'Ash-Nazg: AJAX fetch logs - Switching to SFTP mode' );
		$result = API\update_connection_mode( $site_id, $environment, 'sftp' );
		if ( is_wp_error( $result ) ) {
			error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Failed to switch to SFTP: %s', $result->get_error_message() ) );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$switched_mode = true;
		// Give the mode switch a moment to take effect.
		sleep( 2 );
	}

	// Read debug.log file.
	$log_path = WP_CONTENT_DIR . '/debug.log';
	$logs = '';

	error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Looking for log at: %s', $log_path ) );

	if ( file_exists( $log_path ) && is_readable( $log_path ) ) {
		$logs = file_get_contents( $log_path );
		error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Log file read, size: %d bytes', strlen( $logs ) ) );
	} else {
		error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Log file not found or not readable. Exists: %s, Readable: %s', file_exists( $log_path ) ? 'yes' : 'no', is_readable( $log_path ) ? 'yes' : 'no' ) );
	}

	// Switch back to original mode if we changed it.
	if ( $switched_mode ) {
		error_log( 'Ash-Nazg: AJAX fetch logs - Switching back to Git mode' );
		API\update_connection_mode( $site_id, $environment, 'git' );
	}

	// Clear old transient and store new logs.
	delete_transient( 'ash_nazg_debug_logs' );
	delete_transient( 'ash_nazg_debug_logs_timestamp' );

	// Store for 1 year.
	set_transient( 'ash_nazg_debug_logs', $logs, YEAR_IN_SECONDS );
	set_transient( 'ash_nazg_debug_logs_timestamp', time(), YEAR_IN_SECONDS );

	wp_send_json_success(
		array(
			'logs' => $logs,
			'timestamp' => time(),
			'switched_mode' => $switched_mode,
		)
	);
}

/**
 * Render logs page.
 *
 * @return void
 */
function render_logs_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = null;
	$error = null;

	// Handle redirect messages.
	if ( isset( $_GET['logs_fetched'] ) ) {
		$message = __( 'Debug logs fetched successfully.', 'ash-nazg' );
	}

	if ( isset( $_GET['logs_error'] ) ) {
		$stored_error = get_transient( 'ash_nazg_logs_error' );
		if ( $stored_error ) {
			$error = $stored_error;
			delete_transient( 'ash_nazg_logs_error' );
		}
	}

	// Get current environment info.
	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	// Get cached logs.
	$logs = get_transient( 'ash_nazg_debug_logs' );
	$logs_fetched_at = get_transient( 'ash_nazg_debug_logs_timestamp' );

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/logs.php';
}

