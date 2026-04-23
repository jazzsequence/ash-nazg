<?php
/**
 * WordPress Updates Page Integration.
 *
 * Replaces the Pantheon mu-plugin's admin notice (which directs users to the
 * Pantheon dashboard) with one that stays in WP admin. On update-core.php,
 * renders a full upstream-update section with a commit list and Apply button.
 * On other admin pages, shows a simplified notice when updates are pending.
 *
 * @package Pantheon\AshNazg
 */

namespace Pantheon\AshNazg\UpdatesPage;

use Pantheon\AshNazg\API;
use Pantheon\AshNazg\Helpers;

/**
 * Remove the Pantheon mu-plugin update notice and register ours.
 *
 * Runs at admin_init priority 20 — after the mu-plugin registers
 * _pantheon_upstream_update_notice at priority 10 — so we can reliably
 * unhook it before admin_notices fires.
 */
function replace_pantheon_notice() {
	if ( ! function_exists( '_pantheon_upstream_update_notice' ) ) {
		return;
	}
	remove_action( 'admin_notices', '_pantheon_upstream_update_notice' );
	remove_action( 'network_admin_notices', '_pantheon_upstream_update_notice' );
	add_action( 'admin_notices', __NAMESPACE__ . '\\render_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\\render_notice' );
}

/**
 * Enqueue modal + updates-page scripts on update-core.php.
 *
 * @param string $hook Current admin page hook suffix.
 */
function enqueue_scripts( $hook ) {
	if ( 'update-core.php' !== $hook ) {
		return;
	}

	// Styles and modal are normally loaded only on ash-nazg pages; register them here too.
	wp_enqueue_style(
		'ash-nazg-admin',
		ASH_NAZG_PLUGIN_URL . 'assets/css/admin.css',
		[],
		ASH_NAZG_VERSION
	);

	wp_enqueue_script(
		'ash-nazg-modal',
		ASH_NAZG_PLUGIN_URL . 'assets/js/modal.js',
		[ 'jquery' ],
		ASH_NAZG_VERSION,
		true
	);

	wp_enqueue_script(
		'ash-nazg-updates-page',
		ASH_NAZG_PLUGIN_URL . 'assets/js/updates-page.js',
		[ 'jquery', 'ash-nazg-modal' ],
		ASH_NAZG_VERSION,
		true
	);

	wp_localize_script(
		'ash-nazg-updates-page',
		'ashNazgUpdatesPage',
		[
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'applyNonce'    => wp_create_nonce( 'ash_nazg_apply_upstream_updates' ),
			'workflowNonce' => wp_create_nonce( 'ash_nazg_workflow_status' ),
			'i18n'          => [
				'applying'  => __( 'Applying Upstream Updates…', 'ash-nazg' ),
				'pleaseWait' => __( 'Please wait while the updates are applied. This may take a minute.', 'ash-nazg' ),
				'applied'   => __( 'Upstream updates applied successfully!', 'ash-nazg' ),
				'failed'    => __( 'Failed to apply upstream updates. Please try again.', 'ash-nazg' ),
				'ajaxError' => __( 'An error occurred. Please try again.', 'ash-nazg' ),
				'timeout'   => __( 'Operation timed out. Please check the Pantheon dashboard.', 'ash-nazg' ),
			],
		]
	);
}

/**
 * Fetch upstream update data for the current environment.
 *
 * @return array { commits: array, behind: int, has_updates: bool }
 */
function get_upstream_data() {
	$site_id     = API\get_pantheon_site_id();
	$environment = API\get_effective_environment( $site_id );
	$commits     = [];
	$behind      = 0;

	if ( $site_id ) {
		$raw = API\get_upstream_updates( $site_id, $environment );
		if ( ! is_wp_error( $raw ) && $raw ) {
			$filtered = Helpers\filter_upstream_updates_for_env( $raw, $site_id, $environment );
			$commits  = $filtered['commits'] ?? [];
			$behind   = $filtered['behind'] ?? 0;
		}
	}

	return [
		'commits'     => $commits,
		'behind'      => $behind,
		'has_updates' => $behind > 0 || ! empty( $commits ),
	];
}

/**
 * Render the upstream update notice, replacing the mu-plugin's version.
 *
 * On the WP Updates page: full section with commit list and Apply button.
 * On other pages: simplified notice only when updates are pending.
 */
function render_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen          = get_current_screen();
	$is_updates_page = $screen && in_array( $screen->id, [ 'update-core', 'update-core-network' ], true );
	$data            = get_upstream_data();
	$dev_page_url    = admin_url( 'admin.php?page=ash-nazg-development' );

	if ( $is_updates_page ) {
		render_updates_page_section( $data['commits'], $data['behind'], $data['has_updates'], $dev_page_url );
	} elseif ( $data['has_updates'] ) {
		render_simplified_notice( $data['behind'] ?: count( $data['commits'] ), $dev_page_url );
	}
}

/**
 * Render the full upstream updates section on update-core.php.
 *
 * @param array  $commits      Upstream commit objects.
 * @param int    $behind       Number of commits behind upstream.
 * @param bool   $has_updates  Whether updates are available.
 * @param string $dev_page_url URL to the Ash Nazg development page.
 */
function render_updates_page_section( $commits, $behind, $has_updates, $dev_page_url ) {
	?>
	<div id="ash-nazg-upstream-notice" class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'Pantheon Upstream Updates', 'ash-nazg' ); ?></strong>
		</p>
		<?php if ( $has_updates ) : ?>
			<p>
				<?php
				$count = $behind ?: count( $commits );
				echo esc_html(
					sprintf(
						/* translators: %d: number of upstream updates available */
						_n( '%d upstream update is available for this site.', '%d upstream updates are available for this site.', $count, 'ash-nazg' ),
						$count
					)
				);
				?>
			</p>
			<?php if ( ! empty( $commits ) ) : ?>
				<p>
					<?php foreach ( array_slice( $commits, 0, 5 ) as $i => $commit ) : ?>
						<?php if ( $i > 0 ) : ?>
							<br>
						<?php endif; ?>
						<code><?php echo esc_html( substr( $commit['hash'] ?? '', 0, 8 ) ); ?></code>
						&mdash; <?php echo esc_html( $commit['message'] ?? '' ); ?>
					<?php endforeach; ?>
					<?php if ( count( $commits ) > 5 ) : ?>
						<br><?php echo esc_html( sprintf( /* translators: %d: number of additional commits not shown */ __( '…and %d more.', 'ash-nazg' ), count( $commits ) - 5 ) ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p>
				<button type="button" id="ash-nazg-apply-upstream-updates-core" class="button button-primary">
					<?php esc_html_e( 'Apply Upstream Updates', 'ash-nazg' ); ?>
				</button>
				<a href="<?php echo esc_url( $dev_page_url ); ?>" class="button">
					<?php esc_html_e( 'View Details', 'ash-nazg' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Your site is up to date with the Pantheon upstream.', 'ash-nazg' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render a simplified notice on other admin pages when updates are pending.
 *
 * @param int    $count        Number of pending upstream updates.
 * @param string $dev_page_url URL to the Ash Nazg development page.
 */
function render_simplified_notice( $count, $dev_page_url ) {
	$updates_page_url = admin_url( 'update-core.php' );
	?>
	<div class="notice notice-warning">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1: update count, 2: WP updates page URL, 3: Development page URL.
					_n(
						'%1$d Pantheon upstream update is available. <a href="%2$s">Apply it on the Updates page</a> or view details on the <a href="%3$s">Ash Nazg Development page</a>.',
						'%1$d Pantheon upstream updates are available. <a href="%2$s">Apply them on the Updates page</a> or view details on the <a href="%3$s">Ash Nazg Development page</a>.',
						$count,
						'ash-nazg'
					),
					$count,
					esc_url( $updates_page_url ),
					esc_url( $dev_page_url )
				),
				[ 'a' => [ 'href' => [] ] ]
			);
			?>
		</p>
	</div>
	<?php
}

// Register hooks.
add_action( 'admin_init', __NAMESPACE__ . '\\replace_pantheon_notice', 20 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_scripts' );
