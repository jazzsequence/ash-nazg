<?php
/**
 * AJAX handler tests.
 *
 * @package Pantheon\AshNazg
 */

use PHPUnit\Framework\TestCase;

/**
 * Test AJAX handler functions.
 */
class Test_AJAX_Handlers extends TestCase {

	/**
	 * Test that AJAX handler functions exist.
	 */
	public function test_ajax_handlers_exist() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_fetch_logs' ),
			'ajax_fetch_logs function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_clear_logs' ),
			'ajax_clear_logs function should exist'
		);
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_toggle_connection_mode' ),
			'ajax_toggle_connection_mode function should exist'
		);
	}

	/**
	 * Test AJAX actions are registered.
	 */
	public function test_ajax_actions_registered() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		$ajax_actions = array(
			'wp_ajax_ash_nazg_fetch_logs',
			'wp_ajax_ash_nazg_clear_logs',
			'wp_ajax_ash_nazg_toggle_connection_mode',
		);

		foreach ( $ajax_actions as $action ) {
			$this->assertStringContainsString(
				"'{$action}'",
				$admin_file_contents,
				"Should register {$action} action"
			);
		}
	}

	/**
	 * Test that AJAX handlers check nonces.
	 */
	public function test_ajax_nonce_verification() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use check_ajax_referer in AJAX handlers.
		$this->assertGreaterThan(
			2,
			substr_count( $admin_file_contents, 'check_ajax_referer' ),
			'Should verify nonce in all AJAX handlers'
		);

		// Should check for specific nonces.
		$nonces = array(
			'ash_nazg_fetch_logs',
			'ash_nazg_clear_logs',
			'ash_nazg_toggle_connection_mode',
		);

		foreach ( $nonces as $nonce ) {
			$this->assertStringContainsString(
				"'{$nonce}'",
				$admin_file_contents,
				"Should verify {$nonce} nonce"
			);
		}
	}

	/**
	 * Test that AJAX handlers check capabilities.
	 */
	public function test_ajax_capability_checks() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should check manage_options capability.
		$this->assertGreaterThan(
			2,
			substr_count( $admin_file_contents, 'current_user_can( \'manage_options\' )' ),
			'Should check manage_options capability in AJAX handlers'
		);

		// Should deny access without capability.
		$this->assertStringContainsString(
			'wp_send_json_error',
			$admin_file_contents,
			'Should return error for unauthorized users'
		);
	}

	/**
	 * Test fetch logs handler switches modes.
	 */
	public function test_fetch_logs_mode_switching() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should check original mode.
		$this->assertStringContainsString(
			'get_connection_mode',
			$admin_file_contents,
			'Should get current connection mode'
		);

		// Should switch to SFTP if in Git mode.
		$this->assertStringContainsString(
			"'git' === \$original_mode",
			$admin_file_contents,
			'Should check if in Git mode'
		);

		// Should switch back after operation.
		$this->assertStringContainsString(
			'$switched_mode',
			$admin_file_contents,
			'Should track whether mode was switched'
		);
	}

	/**
	 * Test clear logs handler deletes file.
	 */
	public function test_clear_logs_deletes_file() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use unlink to delete file.
		$this->assertStringContainsString(
			'unlink',
			$admin_file_contents,
			'Should delete debug.log file'
		);

		// Should verify deletion.
		$this->assertStringContainsString(
			'file_exists( $log_path )',
			$admin_file_contents,
			'Should verify file was deleted'
		);

		// Should update transient with empty logs.
		$this->assertStringContainsString(
			"set_transient( 'ash_nazg_debug_logs', ''",
			$admin_file_contents,
			'Should store empty logs in transient'
		);
	}

	/**
	 * Test toggle connection mode handler verifies mode changed.
	 */
	public function test_toggle_mode_verification() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should return workflow ID for client-side polling.
		$this->assertStringContainsString(
			'workflow_id',
			$admin_file_contents,
			'Should return workflow_id in AJAX response'
		);

		// Should NOT do server-side polling (client polls instead).
		$this->assertStringNotContainsString(
			'$expected_on_server_dev',
			$admin_file_contents,
			'Should not verify mode server-side (client polls workflow instead)'
		);

		// AJAX handler should exist.
		$this->assertTrue(
			function_exists( 'Pantheon\AshNazg\Admin\ajax_toggle_connection_mode' ),
			'ajax_toggle_connection_mode handler should exist'
		);
	}

	/**
	 * Test JavaScript files are enqueued.
	 */
	public function test_javascript_enqueued() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should enqueue dashboard.js.
		$this->assertStringContainsString(
			'ash-nazg-dashboard',
			$admin_file_contents,
			'Should enqueue dashboard JavaScript'
		);

		// Should enqueue logs.js.
		$this->assertStringContainsString(
			'ash-nazg-logs',
			$admin_file_contents,
			'Should enqueue logs JavaScript'
		);

		// Should use wp_localize_script.
		$this->assertStringContainsString(
			'wp_localize_script',
			$admin_file_contents,
			'Should localize scripts with data'
		);

		// Should pass nonces to JavaScript.
		$this->assertStringContainsString(
			'toggleModeNonce',
			$admin_file_contents,
			'Should pass toggle mode nonce'
		);
		$this->assertStringContainsString(
			'fetchLogsNonce',
			$admin_file_contents,
			'Should pass fetch logs nonce'
		);
		$this->assertStringContainsString(
			'clearLogsNonce',
			$admin_file_contents,
			'Should pass clear logs nonce'
		);
	}

	/**
	 * Test proper error logging.
	 */
	public function test_error_logging() {
		require_once dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php';

		$admin_file_contents = file_get_contents( dirname( ASH_NAZG_PLUGIN_FILE ) . '/includes/admin.php' );

		// Should use debug_log helper for debugging.
		$this->assertGreaterThan(
			5,
			substr_count( $admin_file_contents, 'debug_log' ),
			'Should log operations for debugging'
		);

		// Should log AJAX operations.
		$this->assertStringContainsString(
			'AJAX',
			$admin_file_contents,
			'Should log AJAX operations'
		);
	}

	/**
	 * Test that addons screen options hook is registered.
	 */
	public function test_addons_screen_options_registered() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString(
			'load-ash-nazg_page_ash-nazg-addons',
			$admin_file_contents,
			'Addons screen options hook should be registered'
		);
	}

	/**
	 * Test that addons screen settings filter covers known addon names.
	 */
	public function test_addons_screen_settings_covers_known_addons() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString( 'redis', $admin_file_contents, 'Addons screen options should include Redis' );
		$this->assertStringContainsString( 'solr', $admin_file_contents, 'Addons screen options should include Solr' );
		$this->assertStringContainsString( 'elasticsearch', $admin_file_contents, 'Addons screen options should include Elasticsearch' );
	}

	/**
	 * Test that handle_screen_options_submission handles the addons page.
	 */
	public function test_screen_options_handles_addons_page() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertMatchesRegularExpression(
			'/ash-nazg-addons.*ash_nazg_hidden_addons|ash_nazg_hidden_addons.*ash-nazg-addons/s',
			$admin_file_contents,
			'handle_screen_options_submission should handle ash-nazg-addons page'
		);
	}

	/**
	 * Test that saving all-hidden state is prevented (at least one addon must remain visible).
	 */
	public function test_addons_screen_options_prevents_all_hidden() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertMatchesRegularExpression(
			'/ash-nazg-addons.*empty.*visible_addons|visible_addons.*empty/s',
			$admin_file_contents,
			'Saving with all addons hidden should be prevented'
		);
	}

	/**
	 * Test that Elasticsearch is a real, toggleable addon in the view, not a
	 * hardcoded placeholder - v1 (unlike v0) supports it via ElasticPress.io.
	 */
	public function test_addons_elasticsearch_is_toggleable() {
		$view_contents = file_get_contents( __DIR__ . '/../includes/views/addons.php' );
		$this->assertStringNotContainsString(
			'Coming Soon',
			$view_contents,
			'Addons view should no longer show a "Coming Soon" placeholder for Elasticsearch'
		);
	}

	/**
	 * Test that dashboard screen options hook is registered.
	 */
	public function test_dashboard_screen_options_registered() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString(
			'load-toplevel_page_ash-nazg',
			$admin_file_contents,
			'Dashboard screen options hook should be registered on load-toplevel_page_ash-nazg'
		);
		$this->assertStringContainsString(
			'dashboard_screen_options',
			$admin_file_contents,
			'dashboard_screen_options function should be referenced in hooks'
		);
	}

	/**
	 * Test that screen_settings filter is registered for the dashboard.
	 */
	public function test_dashboard_screen_settings_filter_exists() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertStringContainsString(
			'dashboard_screen_settings',
			$admin_file_contents,
			'dashboard_screen_settings filter function should exist'
		);
	}

	/**
	 * Test that set_screen_option handles endpoint visibility options.
	 */
	public function test_set_screen_option_handles_endpoint_visibility() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );
		$this->assertMatchesRegularExpression(
			'/function set_screen_option.*ash_nazg_dashboard_show_endpoints/s',
			$admin_file_contents,
			'set_screen_option should handle ash_nazg_dashboard_show_endpoints option'
		);
		$this->assertMatchesRegularExpression(
			'/function set_screen_option.*ash_nazg_dashboard_hidden_groups/s',
			$admin_file_contents,
			'set_screen_option should handle ash_nazg_dashboard_hidden_groups option'
		);
	}

	/**
	 * Test AJAX handlers for org selection and access token copy are registered.
	 */
	public function test_org_and_token_ajax_handlers_exist() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString(
			'ash_nazg_save_primary_org',
			$admin_file_contents,
			'save_primary_org AJAX action should be registered'
		);
		$this->assertStringContainsString(
			'ash_nazg_copy_access_token',
			$admin_file_contents,
			'copy_access_token AJAX action should be registered'
		);
	}

	/**
	 * Test that access token copy handler has nonce and capability checks.
	 */
	public function test_copy_access_token_security() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function ajax_copy_access_token.*check_ajax_referer/s',
			$admin_file_contents,
			'ajax_copy_access_token must verify nonce'
		);
		$this->assertMatchesRegularExpression(
			'/function ajax_copy_access_token.*current_user_can/s',
			$admin_file_contents,
			'ajax_copy_access_token must check capabilities'
		);
	}

	/**
	 * Test that primary org handler has nonce and capability checks.
	 */
	public function test_save_primary_org_security() {
		$admin_file_contents = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertMatchesRegularExpression(
			'/function ajax_save_primary_org.*check_ajax_referer/s',
			$admin_file_contents,
			'ajax_save_primary_org must verify nonce'
		);
		$this->assertMatchesRegularExpression(
			'/function ajax_save_primary_org.*current_user_can/s',
			$admin_file_contents,
			'ajax_save_primary_org must check capabilities'
		);
	}

	/**
	 * Test that the endpoint group partial escapes everything it prints.
	 */
	public function test_endpoint_group_partial_escapes_output() {
		$partial = file_get_contents( __DIR__ . '/../includes/views/partials/endpoint-group.php' );

		$this->assertDoesNotMatchRegularExpression(
			'/echo \$/',
			$partial,
			'The partial should not echo any variable without escaping'
		);
	}

	/**
	 * Test that endpoints which are listed but not probed (writes, or reads that need an ID
	 * we can't supply) get their own status instead of being reported as errors.
	 */
	public function test_skipped_endpoints_are_not_reported_as_errors() {
		$row = file_get_contents( __DIR__ . '/../includes/views/partials/endpoint-row.php' );
		$js = file_get_contents( __DIR__ . '/../assets/js/dashboard.js' );

		$this->assertStringContainsString( "'skipped' === \$endpoint['status']", $row );
		$this->assertStringContainsString( 'data-status="skipped"', $js );
		$this->assertMatchesRegularExpression( '/errors = \$rows\.length - ok - unavailable - skipped - pending/', $js );
	}

	/**
	 * Test the per-endpoint probe handler is registered and gated.
	 *
	 * It makes a live API request, so it needs a nonce and a capability check,
	 * and it only accepts an ID that the registry knows.
	 */
	public function test_probe_endpoint_handler_is_secured() {
		$admin = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString( "add_action( 'wp_ajax_ash_nazg_probe_endpoint'", $admin );

		$this->assertMatchesRegularExpression(
			"/function ajax_probe_endpoint\\(\\).*check_ajax_referer\\( 'ash_nazg_probe_endpoint', 'nonce' \\).*current_user_can\\( 'manage_options' \\).*sanitize_key\\(.*probe_and_cache_endpoint_entry\\( \\\$id \\).*null === \\\$result/s",
			$admin,
			'The handler should verify the nonce and capability, sanitise the ID, and reject IDs the registry does not know'
		);
	}

	/**
	 * Test the row partial escapes its output and the group partial reuses it.
	 */
	public function test_endpoint_row_partial_escapes_and_is_shared() {
		$row = file_get_contents( __DIR__ . '/../includes/views/partials/endpoint-row.php' );
		$group = file_get_contents( __DIR__ . '/../includes/views/partials/endpoint-group.php' );

		$this->assertDoesNotMatchRegularExpression( '/echo \\$/', $row, 'The row partial should not echo a variable without escaping' );
		$this->assertStringContainsString( 'partials/endpoint-row.php', $group, 'The group partial should reuse the row partial' );
		$this->assertStringNotContainsString( 'dashicons-yes-alt', $group, 'Row markup should live only in the row partial' );
	}

	/**
	 * Test the loader probes pending rows one request at a time, and pending rows are not counted as errors.
	 */
	public function test_row_loader_is_sequential_and_summary_ignores_pending() {
		$js = file_get_contents( __DIR__ . '/../assets/js/dashboard.js' );

		$this->assertStringContainsString( "action: 'ash_nazg_probe_endpoint'", $js );
		$this->assertStringContainsString( 'next(index + 1)', $js );
		$this->assertMatchesRegularExpression( '/errors = \\$rows\\.length - ok - unavailable - skipped - pending/', $js );
	}

	/**
	 * Test the Workspace row lives in its own partial, which the dashboard includes.
	 *
	 * The AJAX loader re-renders this row once workspaces have loaded, so both
	 * must use one template.
	 */
	public function test_workspace_row_is_a_shared_partial() {
		$dashboard = file_get_contents( __DIR__ . '/../includes/views/dashboard.php' );
		$partial = file_get_contents( __DIR__ . '/../includes/views/partials/workspace-row.php' );

		$this->assertStringContainsString( 'partials/workspace-row.php', $dashboard );
		$this->assertStringNotContainsString( 'ash-nazg-org-select', $dashboard, 'Workspace markup should live only in the partial' );
		$this->assertStringContainsString( 'ash-nazg-org-select', $partial );
		$this->assertDoesNotMatchRegularExpression( '/echo \\$/', $partial, 'The partial should not echo a variable without escaping' );
	}

	/**
	 * Test the workspace loader handler is registered and gated.
	 *
	 * It makes live API requests, so it needs a nonce and a capability check, and
	 * it only passes a sanitised cursor to the API function.
	 */
	public function test_load_workspaces_handler_is_secured() {
		$admin = file_get_contents( __DIR__ . '/../includes/admin.php' );

		$this->assertStringContainsString( "add_action( 'wp_ajax_ash_nazg_load_workspaces'", $admin );

		$this->assertMatchesRegularExpression(
			"/function ajax_load_workspaces\\(\\).*check_ajax_referer\\( 'ash_nazg_load_workspaces', 'nonce' \\).*current_user_can\\( 'manage_options' \\).*sanitize_text_field\\(.*fetch_user_workspaces_page\\( \\\$cursor \\)/s",
			$admin,
			'The handler should verify the nonce and capability and sanitise the cursor before fetching'
		);
	}

	/**
	 * Test the loader pages through workspaces, restarts once if the partial result is lost,
	 * and the organization controls are delegated so they survive the row being replaced.
	 */
	public function test_workspace_loader_pages_and_controls_are_delegated() {
		$js = file_get_contents( __DIR__ . '/../assets/js/dashboard.js' );

		$this->assertStringContainsString( "action: 'ash_nazg_load_workspaces'", $js );
		$this->assertStringContainsString( 'next(response.data.next)', $js );
		$this->assertStringContainsString( "code === 'partial_lost' && !restarted", $js );

		foreach ( [ '#ash-nazg-org-edit', '#ash-nazg-org-cancel', '#ash-nazg-org-save' ] as $selector ) {
			$this->assertStringContainsString( "\$(document).on('click', '{$selector}'", $js, $selector );
		}
	}

	/**
	 * Test a cold workspace cache renders a loading row for the loader instead of hiding the row.
	 *
	 * get_user_organizations() returns false when nothing is cached, and the partial
	 * must tell that apart from "no workspaces" (empty) so the AJAX loader has a row to fill in.
	 */
	public function test_cold_workspace_cache_renders_a_loading_row() {
		$partial = file_get_contents( __DIR__ . '/../includes/views/partials/workspace-row.php' );
		$api = file_get_contents( __DIR__ . '/../includes/api.php' );

		$this->assertMatchesRegularExpression(
			'/if \( false === \$user_organizations \) : \?>\s*<tr id="ash-nazg-workspace-row" class="ash-nazg-workspace-pending">/',
			$partial,
			'A cold cache should render the pending Workspace row'
		);
		$this->assertMatchesRegularExpression( '/elseif \( \$user_organizations \) : \?>/', $partial, 'An empty list should still render nothing' );
		$this->assertMatchesRegularExpression(
			'/function get_user_organizations\(\).*return get_transient/s',
			$api,
			'get_user_organizations() must stay cache-only so the page never blocks on it'
		);
	}

	/**
	 * Test the status tabs and version filter are wired up.
	 *
	 * Rows carry their version, the view has the four tabs and the version select,
	 * and the JS re-applies the filters each time a row is replaced.
	 */
	public function test_endpoint_tabs_and_version_filter_are_wired() {
		$row = file_get_contents( __DIR__ . '/../includes/views/partials/endpoint-row.php' );
		$view = file_get_contents( __DIR__ . '/../includes/views/dashboard.php' );
		$js = file_get_contents( __DIR__ . '/../assets/js/dashboard.js' );

		$this->assertStringContainsString( 'data-version="<?php echo esc_attr(', $row );

		foreach ( [ 'all', 'working', 'attention', 'skipped' ] as $filter ) {
			$this->assertStringContainsString( 'data-filter="' . $filter . '"', $view, $filter );
			$this->assertStringContainsString( 'data-count="' . $filter . '"', $view, $filter );
		}

		$this->assertStringContainsString( 'id="ash-nazg-endpoint-version"', $view );

		// Needs attention is the default tab, and All is the last one.
		$this->assertStringContainsString( "var endpointFilter = 'attention';", $js );
		$this->assertMatchesRegularExpression( '/nav-tab nav-tab-active" role="button" aria-pressed="true" data-filter="attention".*data-filter="all"/s', $view );
		$this->assertDoesNotMatchRegularExpression( '/data-filter="all".*data-filter="attention"/s', $view, 'All should be the last tab' );
		$this->assertStringContainsString( 'id="ash-nazg-endpoints-empty"', $view );

		// A group of toggle buttons with a live region, not the ARIA tabs pattern (which needs a tabpanel and arrow-key handling).
		$this->assertStringContainsString( 'role="group"', $view );
		$this->assertStringContainsString( 'aria-pressed=', $view );
		$this->assertStringNotContainsString( 'role="tab"', $view );
		$this->assertStringContainsString( 'role="status" aria-live="polite" id="ash-nazg-endpoint-filter-status"', $view );
		$this->assertStringContainsString( 'announceEndpointFilter()', $js );
		$this->assertMatchesRegularExpression( '/function updateEndpointSummary\\(\\).*applyEndpointFilters\\(\\);/s', $js, 'Filters should be re-applied as rows load' );
		$this->assertMatchesRegularExpression( '/function applyEndpointFilters.*ash-nazg-endpoint-group.*ash-nazg-hidden/s', $js, 'Groups with no visible rows should be hidden' );
	}
}
