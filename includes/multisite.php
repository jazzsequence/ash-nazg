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
	add_domain_to_pantheon( $domain, $new_site->blog_id );
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

	add_domain_to_pantheon( $domain, $blog_id );
}

/**
 * Add a domain to Pantheon live environment.
 *
 * @param string $domain Domain name.
 * @param int $blog_id Blog ID for context.
 */
function add_domain_to_pantheon( $domain, $blog_id ) {
	Helpers\debug_log( sprintf( 'New multisite subsite created: %s (Blog ID: %d)', $domain, $blog_id ) );

	// Add domain to live environment.
	$result = API\add_domain( $domain );

	if ( is_wp_error( $result ) ) {
		// Store error in transient for admin notice.
		set_transient(
			'ash_nazg_domain_add_error_' . $blog_id,
			[
				'domain' => $domain,
				'error' => $result->get_error_message(),
			],
			HOUR_IN_SECONDS
		);

		Helpers\debug_log(
			sprintf(
				'Failed to add domain %s to Pantheon: %s',
				$domain,
				$result->get_error_message()
			)
		);
	} else {
		// Store success in transient for admin notice.
		set_transient(
			'ash_nazg_domain_add_success_' . $blog_id,
			[
				'domain' => $domain,
			],
			HOUR_IN_SECONDS
		);

		Helpers\debug_log( sprintf( 'Successfully added domain %s to Pantheon live environment', $domain ) );
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

	// Check for success notice.
	$success = get_transient( 'ash_nazg_domain_add_success_' . $blog_id );
	if ( $success ) {
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: domain name */
					esc_html__( 'Domain %s has been added to Pantheon live environment.', 'ash-nazg' ),
					'<strong>' . esc_html( $success['domain'] ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
		delete_transient( 'ash_nazg_domain_add_success_' . $blog_id );
	}

	// Check for error notice.
	$error = get_transient( 'ash_nazg_domain_add_error_' . $blog_id );
	if ( $error ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: domain name, 2: error message */
					esc_html__( 'Failed to add domain %1$s to Pantheon: %2$s', 'ash-nazg' ),
					'<strong>' . esc_html( $error['domain'] ) . '</strong>',
					esc_html( $error['error'] )
				);
				?>
			</p>
		</div>
		<?php
		delete_transient( 'ash_nazg_domain_add_error_' . $blog_id );
	}
}
