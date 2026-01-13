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
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_commit_form_submission' );
	add_action( 'admin_init', __NAMESPACE__ . '\\handle_multidev_form_submission' );
	add_action( 'wp_ajax_ash_nazg_fetch_logs', __NAMESPACE__ . '\\ajax_fetch_logs' );
	add_action( 'wp_ajax_ash_nazg_clear_logs', __NAMESPACE__ . '\\ajax_clear_logs' );
	add_action( 'wp_ajax_ash_nazg_toggle_connection_mode', __NAMESPACE__ . '\\ajax_toggle_connection_mode' );
	add_action( 'wp_ajax_ash_nazg_update_site_label', __NAMESPACE__ . '\\ajax_update_site_label' );
	add_action( 'load-ash-nazg_page_ash-nazg-development', __NAMESPACE__ . '\\development_screen_options' );
	add_filter( 'set-screen-option', __NAMESPACE__ . '\\set_screen_option', 10, 3 );
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

	// Development page.
	add_submenu_page(
		'ash-nazg',
		__( 'Development', 'ash-nazg' ),
		__( 'Development', 'ash-nazg' ),
		'manage_options',
		'ash-nazg-development',
		__NAMESPACE__ . '\\render_development_page'
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
	$ash_nazg_pages = [
		'toplevel_page_ash-nazg',
		'ash-nazg_page_ash-nazg-addons',
		'ash-nazg_page_ash-nazg-workflows',
		'ash-nazg_page_ash-nazg-development',
		'ash-nazg_page_ash-nazg-logs',
		'ash-nazg_page_ash-nazg-settings',
	];

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
		[],
		ASH_NAZG_VERSION
	);

	// Enqueue dashboard JavaScript on main dashboard page.
	if ( 'toplevel_page_ash-nazg' === $hook ) {
		wp_enqueue_script(
			'ash-nazg-dashboard',
			ASH_NAZG_PLUGIN_URL . 'assets/js/dashboard.js',
			[ 'jquery' ],
			ASH_NAZG_VERSION,
			true
		);

		// Localize script with AJAX URL and nonces.
		wp_localize_script(
			'ash-nazg-dashboard',
			'ashNazgDashboard',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'toggleModeNonce' => wp_create_nonce( 'ash_nazg_toggle_connection_mode' ),
				'updateLabelNonce' => wp_create_nonce( 'ash_nazg_update_site_label' ),
				'i18n' => [
					'toggleError' => __( 'Failed to switch connection mode.', 'ash-nazg' ),
					'ajaxError' => __( 'An error occurred while switching connection mode.', 'ash-nazg' ),
					'updateLabelError' => __( 'Failed to update site label.', 'ash-nazg' ),
					'emptyLabelError' => __( 'Site label cannot be empty.', 'ash-nazg' ),
				],
			]
		);
	}

	// Enqueue development JavaScript on development page.
	if ( 'ash-nazg_page_ash-nazg-development' === $hook ) {
		wp_enqueue_script(
			'ash-nazg-development',
			ASH_NAZG_PLUGIN_URL . 'assets/js/development.js',
			[ 'jquery' ],
			ASH_NAZG_VERSION,
			true
		);
	}

	// Enqueue logs JavaScript on logs page.
	if ( 'ash-nazg_page_ash-nazg-logs' === $hook ) {
		wp_enqueue_script(
			'ash-nazg-logs',
			ASH_NAZG_PLUGIN_URL . 'assets/js/logs.js',
			[ 'jquery' ],
			ASH_NAZG_VERSION,
			true
		);

		// Localize script with AJAX URL and nonces.
		wp_localize_script(
			'ash-nazg-logs',
			'ashNazgLogs',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'fetchLogsNonce' => wp_create_nonce( 'ash_nazg_fetch_logs' ),
				'clearLogsNonce' => wp_create_nonce( 'ash_nazg_clear_logs' ),
				'i18n'           => [
					'logContents'        => __( 'Log Contents', 'ash-nazg' ),
					'emptyLog'           => __( 'Debug log is empty or does not exist yet.', 'ash-nazg' ),
					'justNow'            => __( 'just now', 'ash-nazg' ),
					'success'            => __( 'Logs fetched successfully.', 'ash-nazg' ),
					'successWithSwitch'  => __( 'Logs fetched successfully. Connection mode was temporarily switched to SFTP.', 'ash-nazg' ),
					'fetchError'         => __( 'Failed to fetch logs.', 'ash-nazg' ),
					'ajaxError'          => __( 'An error occurred while fetching logs.', 'ash-nazg' ),
					'clearSuccess'       => __( 'Debug log cleared successfully.', 'ash-nazg' ),
					'clearSuccessSwitch' => __( 'Debug log cleared successfully. Connection mode was temporarily switched to SFTP.', 'ash-nazg' ),
					'clearError'         => __( 'Failed to clear log.', 'ash-nazg' ),
					'clearAjaxError'     => __( 'An error occurred while clearing the log.', 'ash-nazg' ),
					'fetchingLogs'       => __( 'Fetching logs... This may take a moment if we need to switch connection modes.', 'ash-nazg' ),
					'clearingLogs'       => __( 'Clearing log... This may take a moment if we need to switch connection modes.', 'ash-nazg' ),
				],
			]
		);
	}
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
	$endpoints_status = [];

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

		/*
		 * Get endpoints status if we can connect to the API.
		 * We fetch endpoints even if the environment doesn't exist on Pantheon
		 * (local dev).
		 */
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
				$endpoints_user = [];
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
		$errors = [];

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
						++$updated_count;
					}
				}
			}
		}

		// Set success/error messages and redirect to avoid form resubmission.
		$redirect_args = [ 'page' => 'ash-nazg-addons' ];

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

	// Handle redirect messages from transients.
	$updated_count = get_transient( 'ash_nazg_addons_updated' );
	if ( false !== $updated_count ) {
		$message = sprintf(
			/* translators: %d: number of addons updated */
			_n( '%d addon updated successfully.', '%d addons updated successfully.', absint( $updated_count ), 'ash-nazg' ),
			absint( $updated_count )
		);
		delete_transient( 'ash_nazg_addons_updated' );
	}

	$stored_errors = get_transient( 'ash_nazg_addon_errors' );
	if ( $stored_errors ) {
		$error = implode( '<br>', $stored_errors );
		delete_transient( 'ash_nazg_addon_errors' );
	}

	// Get current addon states.
	$site_id = API\get_pantheon_site_id();
	$addons = [];
	$api_error = null;

	if ( $site_id ) {
		$addons = API\get_site_addons( $site_id );
		if ( is_wp_error( $addons ) ) {
			$api_error = $addons;
			$addons = [];
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
				$redirect_args = [
					'page' => 'ash-nazg-workflows',
					'error' => 'invalid_env',
				];
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

			$redirect_args = [ 'page' => 'ash-nazg-workflows' ];

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
 * Handle SFTP commit form submission.
 *
 * @return void
 */
function handle_commit_form_submission() {
	// Only process on commit form submissions.
	if ( ! isset( $_POST['ash_nazg_commit_nonce'] ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_commit_nonce'] ) ), 'ash_nazg_commit_changes' ) ) {
		return;
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Validate action.
	if ( ! isset( $_POST['ash_nazg_action'] ) || 'commit_changes' !== $_POST['ash_nazg_action'] ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( ! $site_id || ! $environment ) {
		$redirect_args = [
			'page' => 'ash-nazg-development',
			'error' => 'missing_params',
		];
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	// Get and validate commit message.
	if ( ! isset( $_POST['commit_message'] ) || empty( trim( $_POST['commit_message'] ) ) ) {
		$redirect_args = [
			'page' => 'ash-nazg-development',
			'error' => 'empty_message',
		];
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	$commit_message = sanitize_textarea_field( wp_unslash( $_POST['commit_message'] ) );

	// Trigger commit workflow.
	$result = API\commit_sftp_changes( $site_id, $environment, $commit_message );

	$redirect_args = [ 'page' => 'ash-nazg-development' ];

	if ( is_wp_error( $result ) ) {
		$redirect_args['error'] = '1';
		set_transient( 'ash_nazg_commit_error', $result->get_error_message(), 30 );
	} else {
		$redirect_args['committed'] = '1';

		// Clear diffstat cache after successful commit.
		delete_transient( sprintf( 'ash_nazg_diffstat_%s_%s', $site_id, $environment ) );

		// Clear commits cache to refresh the list.
		delete_transient( sprintf( 'ash_nazg_env_commits_%s_%s', $site_id, $environment ) );
	}

	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Handle multidev management form submission.
 *
 * @return void
 */
function handle_multidev_form_submission() {
	// Only process on multidev form submissions.
	if ( ! isset( $_POST['ash_nazg_multidev_nonce'] ) ) {
		return;
	}

	// Verify nonce.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ash_nazg_multidev_nonce'] ) ), 'ash_nazg_manage_multidev' ) ) {
		return;
	}

	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Validate action.
	if ( ! isset( $_POST['multidev_action'] ) ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();
	$action = sanitize_text_field( wp_unslash( $_POST['multidev_action'] ) );
	$redirect_args = [ 'page' => 'ash-nazg-development' ];

	if ( ! $site_id ) {
		$redirect_args['error'] = 'missing_site_id';
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	switch ( $action ) {
		case 'create':
			// Get and validate multidev name.
			if ( ! isset( $_POST['multidev_name'] ) || empty( trim( $_POST['multidev_name'] ) ) ) {
				$redirect_args['error'] = 'empty_multidev_name';
				wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
				exit;
			}

			$multidev_name = sanitize_text_field( wp_unslash( $_POST['multidev_name'] ) );
			$source_env = isset( $_POST['source_env'] ) ? sanitize_text_field( wp_unslash( $_POST['source_env'] ) ) : 'dev';

			$result = API\create_multidev( $site_id, $multidev_name, $source_env );

			if ( is_wp_error( $result ) ) {
				$redirect_args['error'] = '1';
				set_transient( 'ash_nazg_multidev_error', $result->get_error_message(), 30 );
			} else {
				$redirect_args['multidev_created'] = '1';
			}
			break;

		case 'merge':
			// Get and validate multidev name.
			if ( ! isset( $_POST['multidev_name'] ) || empty( trim( $_POST['multidev_name'] ) ) ) {
				$redirect_args['error'] = 'empty_multidev_name';
				wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
				exit;
			}

			$multidev_name = sanitize_text_field( wp_unslash( $_POST['multidev_name'] ) );

			$result = API\merge_multidev_to_dev( $site_id, $multidev_name );

			if ( is_wp_error( $result ) ) {
				$redirect_args['error'] = '1';
				set_transient( 'ash_nazg_multidev_error', $result->get_error_message(), 30 );
			} else {
				$redirect_args['multidev_merged'] = '1';
			}
			break;

		case 'delete':
			// Get and validate multidev name.
			if ( ! isset( $_POST['multidev_name'] ) || empty( trim( $_POST['multidev_name'] ) ) ) {
				$redirect_args['error'] = 'empty_multidev_name';
				wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
				exit;
			}

			$multidev_name = sanitize_text_field( wp_unslash( $_POST['multidev_name'] ) );

			$result = API\delete_multidev( $site_id, $multidev_name );

			if ( is_wp_error( $result ) ) {
				$redirect_args['error'] = '1';
				set_transient( 'ash_nazg_multidev_error', $result->get_error_message(), 30 );
			} else {
				$redirect_args['multidev_deleted'] = '1';
			}
			break;
	}

	wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
	exit;
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

	// Handle redirect messages from transients.
	$stored_workflow_id = get_transient( 'ash_nazg_workflow_id' );
	if ( $stored_workflow_id ) {
		$message = __( 'Workflow triggered successfully.', 'ash-nazg' );
		$workflow_id = $stored_workflow_id;
		delete_transient( 'ash_nazg_workflow_id' );
	}

	$stored_error = get_transient( 'ash_nazg_workflow_error' );
	if ( $stored_error ) {
		$error = $stored_error;
		delete_transient( 'ash_nazg_workflow_error' );
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
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( ! $site_id || ! $environment ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX fetch logs - Not running on Pantheon' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		wp_send_json_error( [ 'message' => __( 'Not running on Pantheon.', 'ash-nazg' ) ] );
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Site: %s, Env: %s', $site_id, $environment ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	// Get current connection mode from state.
	$original_mode = API\get_connection_mode();
	if ( ! $original_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX fetch logs - Syncing environment state' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		API\sync_environment_state( $site_id, $environment );
		$original_mode = API\get_connection_mode();
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Original mode: %s', $original_mode ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	$switched_mode = false;

	// If in Git mode, switch to SFTP temporarily.
	if ( 'git' === $original_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX fetch logs - Switching to SFTP mode' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$result = API\update_connection_mode( $site_id, $environment, 'sftp' );
		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Failed to switch to SFTP: %s', $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		$switched_mode = true;
		// Give the mode switch a moment to take effect.
		sleep( 2 );
	}

	// Read debug.log file.
	$log_path = WP_CONTENT_DIR . '/debug.log';
	$logs = '';

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Looking for log at: %s', $log_path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	if ( file_exists( $log_path ) && is_readable( $log_path ) ) {
		$logs = file_get_contents( $log_path );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Log file read, size: %d bytes', strlen( $logs ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'Ash-Nazg: AJAX fetch logs - Log file not found or not readable. Exists: %s, Readable: %s', file_exists( $log_path ) ? 'yes' : 'no', is_readable( $log_path ) ? 'yes' : 'no' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

	}

	// Switch back to original mode if we changed it.
	if ( $switched_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX fetch logs - Switching back to Git mode' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		API\update_connection_mode( $site_id, $environment, 'git' );
	}

	// Clear old transient and store new logs.
	delete_transient( 'ash_nazg_debug_logs' );
	delete_transient( 'ash_nazg_debug_logs_timestamp' );

	// Store for 1 year.
	set_transient( 'ash_nazg_debug_logs', $logs, YEAR_IN_SECONDS );
	set_transient( 'ash_nazg_debug_logs_timestamp', time(), YEAR_IN_SECONDS );

	wp_send_json_success(
		[
			'logs' => $logs,
			'timestamp' => time(),
			'switched_mode' => $switched_mode,
		]
	);
}

/**
 * Handle AJAX request to clear debug logs.
 *
 * @return void
 */
function ajax_clear_logs() {
	// Check nonce.
	check_ajax_referer( 'ash_nazg_clear_logs', 'nonce' );

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( ! $site_id || ! $environment ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Not running on Pantheon' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		wp_send_json_error( [ 'message' => __( 'Not running on Pantheon.', 'ash-nazg' ) ] );
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX clear logs - Site: %s, Env: %s', $site_id, $environment ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	// Get current connection mode from state.
	$original_mode = API\get_connection_mode();
	if ( ! $original_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Syncing environment state' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		API\sync_environment_state( $site_id, $environment );
		$original_mode = API\get_connection_mode();
	}

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX clear logs - Original mode: %s', $original_mode ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	$switched_mode = false;

	// If in Git mode, switch to SFTP temporarily.
	if ( 'git' === $original_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Switching to SFTP mode' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$result = API\update_connection_mode( $site_id, $environment, 'sftp' );
		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Ash-Nazg: AJAX clear logs - Failed to switch to SFTP: %s', $result->get_error_message() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		$switched_mode = true;
		// Give the mode switch a moment to take effect.
		sleep( 2 );
	}

	// Delete debug.log file.
	$log_path = WP_CONTENT_DIR . '/debug.log';

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: AJAX clear logs - Attempting to delete log at: %s', $log_path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	if ( file_exists( $log_path ) ) {
		if ( unlink( $log_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Ash-Nazg: AJAX clear logs - Log file deleted successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Ash-Nazg: AJAX clear logs - Failed to delete log file' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			// Switch back before error.
			if ( $switched_mode ) {
				API\update_connection_mode( $site_id, $environment, 'git' );
			}
			wp_send_json_error( [ 'message' => __( 'Failed to delete log file. Check file permissions.', 'ash-nazg' ) ] );
		}
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Log file does not exist' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

	}

	// Verify the log file was deleted.
	sleep( 1 ); // Give filesystem a moment.
	if ( file_exists( $log_path ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Log file still exists after deletion attempt' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		// Switch back before error.
		if ( $switched_mode ) {
			API\update_connection_mode( $site_id, $environment, 'git' );
		}
		wp_send_json_error( [ 'message' => __( 'Log file was not deleted. Please try again.', 'ash-nazg' ) ] );
	}

	// Switch back to original mode if we changed it.
	if ( $switched_mode ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Ash-Nazg: AJAX clear logs - Switching back to Git mode' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		API\update_connection_mode( $site_id, $environment, 'git' );
	}

	// Store empty logs in transient (instead of deleting) so page refresh shows empty state.
	set_transient( 'ash_nazg_debug_logs', '', YEAR_IN_SECONDS );
	set_transient( 'ash_nazg_debug_logs_timestamp', time(), YEAR_IN_SECONDS );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Ash-Nazg: AJAX clear logs - Log cleared and verified successfully' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	wp_send_json_success(
		[
			'message'       => __( 'Debug log cleared successfully.', 'ash-nazg' ),
			'switched_mode' => $switched_mode,
		]
	);
}

/**
 * Handle AJAX request to toggle connection mode.
 *
 * @return void
 */
function ajax_toggle_connection_mode() {
	// Check nonce.
	check_ajax_referer( 'ash_nazg_toggle_connection_mode', 'nonce' );

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	if ( ! $site_id || ! $environment ) {
		wp_send_json_error( [ 'message' => __( 'Not running on Pantheon.', 'ash-nazg' ) ] );
	}

	// Get the requested mode.
	$new_mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : '';

	if ( ! in_array( $new_mode, [ 'sftp', 'git' ], true ) ) {
		wp_send_json_error( [ 'message' => __( 'Invalid connection mode.', 'ash-nazg' ) ] );
	}

	// Update the connection mode.
	$result = API\update_connection_mode( $site_id, $environment, $new_mode );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ] );
	}

	/* 
	 * Wait and verify the mode has actually changed.
	 * The API returns success when the workflow is initiated, but we need to
	 * wait for completion.
	 */
	$expected_on_server_dev = ( 'sftp' === $new_mode );
	$max_attempts = 10; // 10 attempts * 2 seconds = 20 seconds max wait.
	$verified = false;

	for ( $i = 0; $i < $max_attempts; $i++ ) {
		// Wait 2 seconds before checking.
		sleep( 2 );

		// Clear cache and fetch fresh environment info.
		delete_transient( sprintf( 'ash_nazg_all_env_info_%s', $site_id ) );
		$env_info = API\get_environment_info( $site_id, $environment );

		if ( ! is_wp_error( $env_info ) && isset( $env_info['on_server_development'] ) ) {
			if ( $env_info['on_server_development'] === $expected_on_server_dev ) {
				$verified = true;
				break;
			}
		}
	}

	if ( ! $verified ) {
		wp_send_json_error(
			[
				'message' => __( 'Mode change initiated but could not verify completion. Please refresh the page.', 'ash-nazg' ),
			]
		);
	}

	// Update stored state now that the mode has been verified.
	API\update_environment_state(
		[
			'connection_mode' => $new_mode,
		]
	);

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( 'Ash-Nazg: Connection mode verified and state updated to %s on %s/%s', $new_mode, $site_id, $environment ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	wp_send_json_success(
		[
			'mode' => $new_mode,
			'message' => sprintf(
				/* translators: %s: connection mode (SFTP or Git) */
				__( 'Successfully switched to %s mode.', 'ash-nazg' ),
				strtoupper( $new_mode )
			),
		]
	);
}

/**
 * AJAX handler for updating site label.
 *
 * @return void
 */
function ajax_update_site_label() {
	// Check nonce.
	check_ajax_referer( 'ash_nazg_update_site_label', 'nonce' );

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ash-nazg' ) ] );
	}

	$site_id = API\get_pantheon_site_id();

	if ( ! $site_id ) {
		wp_send_json_error( [ 'message' => __( 'Site ID not found.', 'ash-nazg' ) ] );
	}

	// Get the new label.
	$new_label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

	if ( empty( $new_label ) ) {
		wp_send_json_error( [ 'message' => __( 'Site label cannot be empty.', 'ash-nazg' ) ] );
	}

	// Update the site label.
	$result = API\update_site_label( $site_id, $new_label );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ] );
	}

	wp_send_json_success(
		[
			'message' => __( 'Site label updated successfully.', 'ash-nazg' ),
			'label' => $new_label,
		]
	);
}

/**
 * Render development page.
 *
 * @return void
 */
function render_development_page() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	// Get commits per page from screen options.
	$commits_per_page = get_commits_per_page();

	// Handle redirect messages from transients.
	$message = null;
	$error = null;

	// Check for commit success.
	if ( isset( $_GET['committed'] ) && '1' === $_GET['committed'] ) {
		$message = __( 'Changes committed successfully.', 'ash-nazg' );
	}

	// Check for multidev success.
	if ( isset( $_GET['multidev_created'] ) && '1' === $_GET['multidev_created'] ) {
		$message = __( 'Multidev environment created successfully.', 'ash-nazg' );
	}
	if ( isset( $_GET['multidev_merged'] ) && '1' === $_GET['multidev_merged'] ) {
		$message = __( 'Multidev merged to dev successfully.', 'ash-nazg' );
	}
	if ( isset( $_GET['multidev_deleted'] ) && '1' === $_GET['multidev_deleted'] ) {
		$message = __( 'Multidev environment deleted successfully.', 'ash-nazg' );
	}

	// Check for errors.
	$stored_error = get_transient( 'ash_nazg_commit_error' );
	if ( $stored_error ) {
		$error = $stored_error;
		delete_transient( 'ash_nazg_commit_error' );
	}

	$stored_multidev_error = get_transient( 'ash_nazg_multidev_error' );
	if ( $stored_multidev_error ) {
		$error = $stored_multidev_error;
		delete_transient( 'ash_nazg_multidev_error' );
	}

	if ( isset( $_GET['error'] ) ) {
		switch ( $_GET['error'] ) {
			case 'missing_params':
				$error = __( 'Missing required parameters.', 'ash-nazg' );
				break;
			case 'empty_message':
				$error = __( 'Commit message is required.', 'ash-nazg' );
				break;
			case 'empty_multidev_name':
				$error = __( 'Multidev name is required.', 'ash-nazg' );
				break;
			case 'missing_site_id':
				$error = __( 'Site ID is missing.', 'ash-nazg' );
				break;
			default:
				$error = __( 'An error occurred.', 'ash-nazg' );
		}
	}

	// Get environment info to check connection mode.
	$environment_info = null;
	$connection_mode = null;

	// Get git data from API.
	$commits = null;
	$upstream_updates = null;
	$environments = null;
	$diffstat = null;

	if ( $site_id ) {
		$environment_info = API\get_environment_info( $site_id, $environment );
		$commits = API\get_environment_commits( $site_id, $environment );
		$upstream_updates = API\get_upstream_updates( $site_id );
		$environments = API\get_environments( $site_id );

		// Get connection mode from environment info.
		if ( $environment_info && isset( $environment_info['on_server_development'] ) ) {
			$connection_mode = $environment_info['on_server_development'] ? 'sftp' : 'git';
		}

		// Get diffstat if in SFTP mode.
		if ( 'sftp' === $connection_mode ) {
			$diffstat = API\get_diffstat( $site_id, $environment );
		}
	}

	// Include the view.
	require_once ASH_NAZG_PLUGIN_DIR . '/includes/views/development.php';
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

	// Handle redirect messages from transients.
	$logs_fetched = get_transient( 'ash_nazg_logs_fetched' );
	if ( $logs_fetched ) {
		$message = __( 'Debug logs fetched successfully.', 'ash-nazg' );
		delete_transient( 'ash_nazg_logs_fetched' );
	}

	$stored_error = get_transient( 'ash_nazg_logs_error' );
	if ( $stored_error ) {
		$error = $stored_error;
		delete_transient( 'ash_nazg_logs_error' );
	}

	// Get current environment info.
	$site_id = API\get_pantheon_site_id();
	$environment = API\get_pantheon_environment();

	// Get cached logs.
	$logs = get_transient( 'ash_nazg_debug_logs' );
	$logs_fetched_at = get_transient( 'ash_nazg_debug_logs_timestamp' );

	require ASH_NAZG_PLUGIN_DIR . 'includes/views/logs.php';
}

/**
 * Register screen options for development page.
 *
 * @return void
 */
function development_screen_options() {
	$screen = get_current_screen();

	if ( ! is_object( $screen ) || 'ash-nazg_page_ash-nazg-development' !== $screen->id ) {
		return;
	}

	// Add custom screen options HTML instead of using built-in per_page.
	add_filter( 'screen_settings', function( $settings, $args ) {
		if ( 'ash-nazg_page_ash-nazg-development' !== $args->id ) {
			return $settings;
		}

		$user_id = get_current_user_id();
		$per_page = get_user_meta( $user_id, 'ash_nazg_commits_per_page', true );
		$per_page = $per_page ? absint( $per_page ) : 50;

		ob_start();
		?>
		<fieldset class="screen-options">
		<legend><?php esc_html_e( 'Commits Display', 'ash-nazg' ); ?></legend>
		<label for="ash_nazg_commits_per_page">
			<?php esc_html_e( 'Number of commits to show:', 'ash-nazg' ); ?>
			<input type="number" step="1" min="1" max="50" name="ash_nazg_commits_per_page" id="ash_nazg_commits_per_page" value="<?php echo esc_attr( $per_page ); ?>" class="screen-per-page" />
		</label>
		<p class="ash-nazg-screen-options-note"><em><?php esc_html_e( 'Note: The Pantheon API returns a maximum of 50 commits.', 'ash-nazg' ); ?></em></p>
		</fieldset>
		<?php
		$settings .= ob_get_clean();

		return $settings;
	}, 10, 2 );
}

/**
 * Save screen option value.
 *
 * @param mixed $status Screen option value. False by default.
 * @param string $option Screen option name.
 * @param mixed $value Screen option value.
 * @return mixed
 */
function set_screen_option( $status, $option, $value ) {
	if ( 'ash_nazg_commits_per_page' === $option ) {
		// Cap at 50 due to Pantheon API limitation.
		$value = absint( $value );
		return min( $value, 50 );
	}

	return $status;
}

/**
 * Get commits per page setting for current user.
 *
 * @return int Number of commits to display per page.
 */
function get_commits_per_page() {
	$user_id = get_current_user_id();
	$per_page = get_user_meta( $user_id, 'ash_nazg_commits_per_page', true );

	// Default to 50 if not set.
	$per_page = $per_page ? absint( $per_page ) : 50;

	// Cap at 50 due to Pantheon API limitation (handles legacy stored values).
	if ( $per_page > 50 ) {
		// Update the stored value to 50.
		update_user_meta( $user_id, 'ash_nazg_commits_per_page', 50 );
		return 50;
	}

	return $per_page;
}
