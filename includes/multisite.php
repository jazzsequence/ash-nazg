<?php
/**
 * WordPress Multisite integration functions.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\Multisite;

use Pantheon\AshNazg\API;
use Pantheon\AshNazg\Helpers;

/**
 * Initialize multisite hooks.
 */
function init() {
	// Only run on multisite installations.
	if ( ! is_multisite() ) {
		return;
	}

	// Hook into site creation (WP 5.1+).
	if ( function_exists( 'wp_initialize_site' ) ) {
		add_action( 'wp_initialize_site', __NAMESPACE__ . '\\on_new_site', 10, 2 );
	} else {
		// Legacy hook for older WordPress versions.
		add_action( 'wpmu_new_blog', __NAMESPACE__ . '\\on_new_site_legacy', 10, 6 );
	}

	// Hook admin notices for displaying results.
	add_action( 'admin_notices', __NAMESPACE__ . '\\display_admin_notices' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\\display_admin_notices' );

	// Add custom field to Add New Site form.
	add_action( 'network_site_new_form', __NAMESPACE__ . '\\add_environment_field' );
}

/**
 * Add environment selection field to Add New Site form.
 */
function add_environment_field() {
	// Skip on local environments.
	if ( Helpers\is_local_environment() ) {
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Pantheon Domain', 'ash-nazg' ); ?></th>
			<td>
				<p class="description">
					<?php esc_html_e( 'Local environment detected. Domain will not be added to Pantheon.', 'ash-nazg' ); ?>
				</p>
			</td>
		</tr>
		<?php
		return;
	}

	$current_env = Helpers\ensure_environment();
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Add Domain to Pantheon', 'ash-nazg' ); ?></th>
		<td>
			<fieldset>
				<legend class="screen-reader-text">
					<?php esc_html_e( 'Select Pantheon environments to add domain to', 'ash-nazg' ); ?>
				</legend>
				<label>
					<input type="checkbox" name="ash_nazg_environments[]" value="dev" <?php checked( 'dev', $current_env ); ?>>
					<?php esc_html_e( 'Dev', 'ash-nazg' ); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="ash_nazg_environments[]" value="test">
					<?php esc_html_e( 'Test', 'ash-nazg' ); ?>
				</label>
				<br>
				<label>
					<input type="checkbox" name="ash_nazg_environments[]" value="live">
					<?php esc_html_e( 'Live', 'ash-nazg' ); ?>
				</label>
				<p class="description">
					<?php
					printf(
						/* translators: %s: current environment name */
						esc_html__( 'Select which Pantheon environment(s) to add this domain to. Current environment: %s', 'ash-nazg' ),
						'<strong>' . esc_html( $current_env ) . '</strong>'
					);
					?>
				</p>
			</fieldset>
		</td>
	</tr>
	<?php
}

/**
 * Handle new site creation (WP 5.1+).
 *
 * @param \WP_Site $new_site New site object.
 * @param array $_args Arguments for the initialization (unused).
 */
function on_new_site( $new_site, $_args ) {
	// Skip local environments.
	if ( Helpers\is_local_environment() ) {
		Helpers\debug_log( 'Skipping domain addition - local environment detected' );
		return;
	}

	$domain = $new_site->domain;

	// Get selected environments from form submission.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$environments = isset( $_POST['ash_nazg_environments'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ash_nazg_environments'] ) ) : [];

	// If no environments selected, skip domain addition.
	if ( empty( $environments ) ) {
		Helpers\debug_log( sprintf( 'No environments selected for domain %s - skipping domain addition', $domain ) );
		return;
	}

	// Add domain to each selected environment.
	foreach ( $environments as $env ) {
		add_domain_to_pantheon( $domain, $new_site->blog_id, $env );
	}
}

/**
 * Handle new site creation (legacy, pre-WP 5.1).
 *
 * @param int $blog_id Blog ID.
 * @param int $_user_id User ID (unused).
 * @param string $domain Site domain.
 * @param string $_path Site path (unused).
 * @param int $_site_id Site ID (deprecated, unused).
 * @param array $_meta Site meta (unused).
 */
function on_new_site_legacy( $blog_id, $_user_id, $domain, $_path, $_site_id, $_meta ) {
	// Skip local environments.
	if ( Helpers\is_local_environment() ) {
		Helpers\debug_log( 'Skipping domain addition - local environment detected' );
		return;
	}

	// Get selected environments from form submission.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$environments = isset( $_POST['ash_nazg_environments'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ash_nazg_environments'] ) ) : [];

	// If no environments selected, skip domain addition.
	if ( empty( $environments ) ) {
		Helpers\debug_log( sprintf( 'No environments selected for domain %s - skipping domain addition', $domain ) );
		return;
	}

	// Add domain to each selected environment.
	foreach ( $environments as $env ) {
		add_domain_to_pantheon( $domain, $blog_id, $env );
	}
}

/**
 * Add a domain to Pantheon environment.
 *
 * @param string $domain Domain name.
 * @param int $blog_id Blog ID for context.
 * @param string $env Environment name (dev, test, live).
 */
function add_domain_to_pantheon( $domain, $blog_id, $env ) {
	Helpers\debug_log( sprintf( 'Adding domain %s (Blog ID: %d) to %s environment', $domain, $blog_id, $env ) );

	// Add domain to specified environment.
	$result = API\add_domain( $domain, null, $env );

	if ( is_wp_error( $result ) ) {
		// Store error in transient for admin notice (unique key per environment).
		$transient_key = sprintf( 'ash_nazg_domain_add_error_%d_%s', $blog_id, $env );
		set_transient(
			$transient_key,
			[
				'domain' => $domain,
				'env' => $env,
				'error' => $result->get_error_message(),
			],
			HOUR_IN_SECONDS
		);

		Helpers\debug_log(
			sprintf(
				'Failed to add domain %s to Pantheon %s environment: %s',
				$domain,
				$env,
				$result->get_error_message()
			)
		);
	} else {
		// Store success in transient for admin notice (unique key per environment).
		$transient_key = sprintf( 'ash_nazg_domain_add_success_%d_%s', $blog_id, $env );
		set_transient(
			$transient_key,
			[
				'domain' => $domain,
				'env' => $env,
			],
			HOUR_IN_SECONDS
		);

		Helpers\debug_log( sprintf( 'Successfully added domain %s to Pantheon %s environment', $domain, $env ) );
	}
}

/**
 * Display admin notices for domain addition results.
 */
function display_admin_notices() {
	if ( ! is_multisite() || ! current_user_can( 'manage_network' ) ) {
		return;
	}

	$blog_id = get_current_blog_id();
	$environments = [ 'dev', 'test', 'live' ];

	// Check for success notices across all environments.
	foreach ( $environments as $env ) {
		$transient_key = sprintf( 'ash_nazg_domain_add_success_%d_%s', $blog_id, $env );
		$success = get_transient( $transient_key );
		if ( $success ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: domain name, 2: environment name */
						esc_html__( 'Domain %1$s has been added to Pantheon %2$s environment.', 'ash-nazg' ),
						'<strong>' . esc_html( $success['domain'] ) . '</strong>',
						'<strong>' . esc_html( $success['env'] ) . '</strong>'
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( $transient_key );
		}
	}

	// Check for error notices across all environments.
	foreach ( $environments as $env ) {
		$transient_key = sprintf( 'ash_nazg_domain_add_error_%d_%s', $blog_id, $env );
		$error = get_transient( $transient_key );
		if ( $error ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: domain name, 2: environment name, 3: error message */
						esc_html__( 'Failed to add domain %1$s to Pantheon %2$s environment: %3$s', 'ash-nazg' ),
						'<strong>' . esc_html( $error['domain'] ) . '</strong>',
						'<strong>' . esc_html( $error['env'] ) . '</strong>',
						esc_html( $error['error'] )
					);
					?>
				</p>
			</div>
			<?php
			delete_transient( $transient_key );
		}
	}
}
