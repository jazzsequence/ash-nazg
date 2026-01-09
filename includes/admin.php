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
