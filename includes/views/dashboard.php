<?php
/**
 * Dashboard page template.
 *
 * @package Pantheon\AshNazg
 */

use Pantheon\AshNazg\Admin;
use Pantheon\AshNazg\API;
use Pantheon\AshNazg\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<?php \Pantheon\AshNazg\Admin\render_pantheon_header( get_admin_page_title() ); ?>

	<?php if ( ! $is_pantheon ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Not Running on Pantheon', 'ash-nazg' ); ?></strong>
			</p>
			<p>
				<?php
				esc_html_e(
					'This site does not appear to be running on Pantheon. Some features may not work correctly.',
					'ash-nazg'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( null !== $api_error ) : ?>
		<?php
		$error_code  = $api_error->get_error_code();
		$notice_type = in_array( $error_code, [ 'api_unavailable', 'api_connection_failed' ], true ) ? 'warning' : 'error';
		?>
		<div class="notice notice-<?php echo esc_attr( $notice_type ); ?>">
			<p>
				<strong>
					<?php
					if ( 'api_unavailable' === $error_code ) {
						esc_html_e( 'Pantheon API Temporarily Unavailable', 'ash-nazg' );
					} else {
						esc_html_e( 'API Connection Error', 'ash-nazg' );
					}
					?>
				</strong>
			</p>
			<p><?php echo esc_html( $api_error->get_error_message() ); ?></p>
			<?php if ( 'api_unavailable' === $error_code ) : ?>
				<p>
					<em><?php esc_html_e( 'This is a temporary Pantheon API issue. Your configuration is correct. Please try again later.', 'ash-nazg' ); ?></em>
				</p>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %s: URL to settings page */
						esc_html__( 'Please check your %s and ensure your machine token is configured correctly.', 'ash-nazg' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=ash-nazg-settings' ) ) . '">' .
						esc_html__( 'settings', 'ash-nazg' ) .
						'</a>'
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $mode_message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $mode_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $mode_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $mode_error ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ash-nazg-dashboard ash-nazg-dashboard--2col">
		<?php if ( $is_pantheon ) : ?>
			<div class="ash-nazg-card ash-nazg-card-1-col">
				<h2><?php esc_html_e( 'Environment Information', 'ash-nazg' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'API Status', 'ash-nazg' ); ?></th>
							<td>
								<?php if ( $site_info ) : ?>
									<span class="ash-nazg-badge ash-nazg-badge-success">
										<?php esc_html_e( 'Connected', 'ash-nazg' ); ?>
									</span>
								<?php else : ?>
									<span class="ash-nazg-badge ash-nazg-badge-error">
										<?php esc_html_e( 'Disconnected', 'ash-nazg' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Site ID', 'ash-nazg' ); ?></th>
							<td><code><?php echo esc_html( $site_id ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Environment', 'ash-nazg' ); ?></th>
							<td>
								<code><?php echo esc_html( $environment ); ?></code>
								<?php
								$env_badge_class = 'dev' === $environment ? 'dev' : ( 'test' === $environment ? 'test' : ( 'live' === $environment ? 'live' : 'multidev' ) );
								?>
								<span class="ash-nazg-badge ash-nazg-badge-<?php echo esc_attr( $env_badge_class ); ?>">
									<?php echo esc_html( strtoupper( $environment ) ); ?>
								</span>
							</td>
						</tr>
						<?php
						// Get connection mode from fresh API data first (source of truth).
						$connection_mode = null;
						if ( $environment_info && isset( $environment_info['on_server_development'] ) ) {
							$connection_mode = $environment_info['on_server_development'] ? 'sftp' : 'git';
						} else {
							// Fall back to stored state if API data unavailable.
							$connection_mode = API\get_connection_mode();
						}
						?>
					<?php if ( $connection_mode ) : ?>
						<tr>
							<th><?php esc_html_e( 'Connection Mode', 'ash-nazg' ); ?></th>
							<td>
								<?php if ( 'sftp' === $connection_mode ) : ?>
									<span class="ash-nazg-badge ash-nazg-badge-sftp">SFTP Mode</span>
								<?php else : ?>
									<span class="ash-nazg-badge ash-nazg-badge-git">Git Mode</span>
								<?php endif; ?>
								<div id="ash-nazg-connection-mode-toggle">
									<?php if ( 'sftp' === $connection_mode ) : ?>
										<button type="button" id="ash-nazg-toggle-mode" data-mode="git" class="button button-primary button-small">
											<span class="dashicons dashicons-media-code"></span>
											<?php esc_html_e( 'Switch to Git Mode', 'ash-nazg' ); ?>
										</button>
									<?php else : ?>
										<button type="button" id="ash-nazg-toggle-mode" data-mode="sftp" class="button button-primary button-small">
											<span class="dashicons dashicons-admin-tools"></span>
											<?php esc_html_e( 'Switch to SFTP Mode', 'ash-nazg' ); ?>
										</button>
									<?php endif; ?>
									<div id="ash-nazg-mode-loading">
										<span class="spinner is-active"></span>
										<em><?php esc_html_e( 'Switching connection mode...', 'ash-nazg' ); ?></em>
									</div>
								</div>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $environment_info && ! empty( $environment_info['php_version'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'ash-nazg' ); ?></th>
							<td>
								<code>
									<?php
									// Format PHP version: "82" -> "8.2", "74" -> "7.4", etc.
									$php_version = $environment_info['php_version'];
									if ( preg_match( '/^(\d)(\d+)$/', $php_version, $matches ) ) {
										$php_version = $matches[1] . '.' . $matches[2];
									}
									echo esc_html( $php_version );
									?>
								</code>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $environment_info && isset( $environment_info['lock'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Environment Lock', 'ash-nazg' ); ?></th>
							<td>
								<?php if ( ! empty( $environment_info['lock']['locked'] ) ) : ?>
									<span class="dashicons dashicons-lock ash-nazg-icon-locked"></span>
									<?php esc_html_e( 'Locked', 'ash-nazg' ); ?>
									<?php if ( ! empty( $environment_info['lock']['username'] ) ) : ?>
										<span class="ash-nazg-text-muted">
											<?php
											printf(
												/* translators: %s: username */
												esc_html__( '— by %s', 'ash-nazg' ),
												esc_html( $environment_info['lock']['username'] )
											);
											?>
										</span>
									<?php endif; ?>
								<?php else : ?>
									<span class="dashicons dashicons-unlock ash-nazg-icon-unlocked"></span>
									<?php esc_html_e( 'Unlocked', 'ash-nazg' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( $pantheon_user ) : ?>
						<tr>
							<th><?php esc_html_e( 'Pantheon Account', 'ash-nazg' ); ?></th>
							<td>
								<?php
								$firstname = $pantheon_user['profile']['firstname'] ?? '';
								$lastname  = $pantheon_user['profile']['lastname'] ?? '';
								$fullname  = trim( $firstname . ' ' . $lastname );
								$email     = $pantheon_user['email'] ?? '';
								if ( $fullname ) {
									echo esc_html( $fullname );
								}
								if ( $email ) {
									echo ' <span class="ash-nazg-text-muted">(' . esc_html( $email ) . ')</span>';
								}
								?>
							</td>
						</tr>
						<?php if ( $user_organizations ) : ?>
							<?php
							// Determine display org: stored preference → site owner org → first in list.
							$display_org_id   = $primary_org_id;
							$display_org_name = $primary_org_name;
							if ( ! $display_org_id ) {
								$site_org_id = $site_info['organization'] ?? null;
								if ( $site_org_id ) {
									foreach ( $user_organizations as $membership ) {
										if ( ( $membership['organization']['id'] ?? '' ) === $site_org_id ) {
											$display_org_id   = $site_org_id;
											$display_org_name = $membership['organization']['profile']['name'] ?? '';
											break;
										}
									}
								}
							}
							if ( ! $display_org_id && ! empty( $user_organizations[0]['organization']['id'] ) ) {
								$display_org_id   = $user_organizations[0]['organization']['id'];
								$display_org_name = $user_organizations[0]['organization']['profile']['name'] ?? '';
							}
							?>
						<tr>
							<th><?php esc_html_e( 'Workspace', 'ash-nazg' ); ?></th>
							<td>
								<span id="ash-nazg-org-display" class="ash-nazg-editable">
									<span><?php echo esc_html( $display_org_name ); ?></span>
									<?php if ( count( $user_organizations ) > 1 ) : ?>
										<a href="#" id="ash-nazg-org-edit" class="ash-nazg-edit-link" title="<?php esc_attr_e( 'Change workspace', 'ash-nazg' ); ?>">
											<span class="dashicons dashicons-edit" aria-hidden="true"></span>
										</a>
									<?php endif; ?>
								</span>
								<?php if ( count( $user_organizations ) > 1 ) : ?>
								<span id="ash-nazg-org-select-wrap" class="ash-nazg-hidden">
									<select id="ash-nazg-org-select" aria-label="<?php esc_attr_e( 'Select workspace', 'ash-nazg' ); ?>">
										<?php foreach ( $user_organizations as $membership ) : ?>
											<?php
											$org_id   = $membership['organization']['id'] ?? '';
											$org_name = $membership['organization']['profile']['name'] ?? '';
											?>
											<option value="<?php echo esc_attr( $org_id ); ?>" data-name="<?php echo esc_attr( $org_name ); ?>"
												<?php selected( $org_id, $display_org_id ); ?>>
												<?php echo esc_html( $org_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<button type="button" id="ash-nazg-org-save" class="button button-primary button-small"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_save_primary_org' ) ); ?>">
										<?php esc_html_e( 'Save', 'ash-nazg' ); ?>
									</button>
									<button type="button" id="ash-nazg-org-cancel" class="button button-secondary button-small">
										<?php esc_html_e( 'Cancel', 'ash-nazg' ); ?>
									</button>
								</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endif; // user_organizations. ?>
					<tr>
						<th><?php esc_html_e( 'Personal Access Token', 'ash-nazg' ); ?></th>
						<td>
							<button type="button" id="ash-nazg-copy-access-token" class="button button-small"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_copy_access_token' ) ); ?>">
								<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
								<?php esc_html_e( 'Copy token', 'ash-nazg' ); ?>
							</button>
							<span id="ash-nazg-copy-access-token-feedback" class="ash-nazg-text-muted" hidden>
								<?php esc_html_e( 'Copied!', 'ash-nazg' ); ?>
							</span>
						</td>
					</tr>
				<?php endif; // pantheon_user. ?>
					<tr>
						<th><?php esc_html_e( 'WordPress', 'ash-nazg' ); ?></th>
						<td><code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
					</tr>
					<?php $db_server = Helpers\get_database_version(); ?>
					<tr>
						<th><?php echo esc_html( $db_server['name'] ); ?></th>
						<td><code><?php echo esc_html( $db_server['version'] ); ?></code></td>
					</tr>
					<?php if ( defined( 'PANTHEON_MU_PLUGIN_VERSION' ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Pantheon MU Plugin', 'ash-nazg' ); ?></th>
							<td><code><?php echo esc_html( PANTHEON_MU_PLUGIN_VERSION ); ?></code></td>
						</tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( null !== $site_info ) : ?>
			<div class="ash-nazg-card ash-nazg-card-1-col">
				<h2>
					<?php esc_html_e( 'Site Information', 'ash-nazg' ); ?>
					<?php if ( $site_info_cached_at ) : ?>
						<span class="ash-nazg-meta-text">
							(Last checked: <?php echo esc_html( human_time_diff( $site_info_cached_at ) ); ?> ago)
						</span>
					<?php endif; ?>
				</h2>
				<table class="widefat striped">
					<tbody>
						<?php
						// Get all site info fields (holder_type/holder_id/owner have no v1 equivalent - see normalize_site_info()).
						$site_id = API\get_api_field( 'site', 'id' );
						$site_name = API\get_api_field( 'site', 'name' );
						$site_label = API\get_api_field( 'site', 'label' );
						$site_created = API\get_api_field( 'site', 'created' );
						$site_framework = API\get_api_field( 'site', 'framework' );
						$site_organization_name = API\get_api_field( 'site', 'organization_name' );
						$site_plan_name = API\get_api_field( 'site', 'plan_name' );
						$site_frozen = API\get_api_field( 'site', 'frozen' );
						$site_region_label = API\get_api_field( 'site', 'region_label' );
						$site_can_create_multidev = API\get_api_field( 'site', 'can_create_multidev' );
						$site_upstream = API\get_api_field( 'site', 'upstream' );
						?>
						<?php if ( $site_id ) : ?>
							<tr>
								<th><?php esc_html_e( 'Site ID', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $site_id ); ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_name ) : ?>
							<tr>
								<th><?php esc_html_e( 'Site Name', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_name ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_label ) : ?>
							<tr>
								<th><?php esc_html_e( 'Site Label', 'ash-nazg' ); ?></th>
								<td>
									<span id="ash-nazg-site-label-display" class="ash-nazg-editable">
										<span><?php echo esc_html( $site_label ); ?></span>
										<a href="#" id="ash-nazg-edit-site-label" class="ash-nazg-edit-link" title="<?php esc_attr_e( 'Edit site label', 'ash-nazg' ); ?>">
											<span class="dashicons dashicons-edit"></span>
										</a>
									</span>
									<span id="ash-nazg-site-label-form" class="ash-nazg-hidden">
										<input type="text" id="ash-nazg-site-label-input" value="<?php echo esc_attr( $site_label ); ?>" class="regular-text" />
										<button type="button" id="ash-nazg-save-site-label" class="button button-primary button-small">
											<?php esc_html_e( 'Save', 'ash-nazg' ); ?>
										</button>
										<button type="button" id="ash-nazg-cancel-site-label" class="button button-secondary button-small">
											<?php esc_html_e( 'Cancel', 'ash-nazg' ); ?>
										</button>
										<span id="ash-nazg-site-label-loading" class="ash-nazg-hidden">
											<span class="spinner is-active"></span>
										</span>
									</span>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_created ) : ?>
							<tr>
								<th><?php esc_html_e( 'Created', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $site_created ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_framework ) : ?>
							<tr>
								<th><?php esc_html_e( 'Framework', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_framework ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_organization_name ) : ?>
							<tr>
								<th><?php esc_html_e( 'Workspace', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_organization_name ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_plan_name ) : ?>
							<tr>
								<th><?php esc_html_e( 'Plan', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_plan_name ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( null !== $site_frozen ) : ?>
							<tr>
								<th><?php esc_html_e( 'Status', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( $site_frozen ) : ?>
										<span class="ash-nazg-badge ash-nazg-badge-frozen">
											<?php esc_html_e( 'Frozen', 'ash-nazg' ); ?>
										</span>
									<?php else : ?>
										<span class="ash-nazg-badge ash-nazg-badge-active">
											<?php esc_html_e( 'Active', 'ash-nazg' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_region_label ) : ?>
							<tr>
								<th><?php esc_html_e( 'Region', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_region_label ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( null !== $site_can_create_multidev ) : ?>
							<tr>
								<th><?php esc_html_e( 'Can Create Multidev', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( $site_can_create_multidev ) : ?>
										<span class="dashicons dashicons-yes-alt ash-nazg-icon-success"></span>
										<?php esc_html_e( 'Yes', 'ash-nazg' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-no ash-nazg-icon-error"></span>
										<?php esc_html_e( 'No', 'ash-nazg' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_upstream ) : ?>
							<tr>
								<th><?php esc_html_e( 'Upstream', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( is_array( $site_upstream ) ) : ?>
										<?php if ( ! empty( $site_upstream['product_id'] ) ) : ?>
											<code><?php echo esc_html( $site_upstream['product_id'] ); ?></code>
										<?php elseif ( ! empty( $site_upstream['upstream_id'] ) ) : ?>
											<code><?php echo esc_html( $site_upstream['upstream_id'] ); ?></code>
										<?php endif; ?>
										<?php if ( ! empty( $site_upstream['label'] ) ) : ?>
											<?php echo esc_html( $site_upstream['label'] ); ?>
										<?php endif; ?>
									<?php else : ?>
										<code><?php echo esc_html( $site_upstream ); ?></code>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $endpoint_groups ) ) : ?>
			<div id="endpoints" class="ash-nazg-card ash-nazg-card-full">
				<div class="ash-nazg-flex-between ash-nazg-mb-10">
					<h2 class="ash-nazg-m-0"><?php esc_html_e( 'Available API Endpoints', 'ash-nazg' ); ?></h2>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&refresh_cache=1' ), 'ash_nazg_refresh_cache' ) ); ?>" class="button button-secondary ash-nazg-editable">
						<span class="dashicons dashicons-update"></span>
						<span><?php esc_html_e( 'Refresh Data', 'ash-nazg' ); ?></span>
					</a>
				</div>

				<p class="ash-nazg-my-10" id="ash-nazg-endpoints-summary">
					<strong><?php esc_html_e( 'Summary:', 'ash-nazg' ); ?></strong>
					<span class="ash-nazg-text-success">✓ <span id="ash-nazg-endpoints-ok">0</span> <?php esc_html_e( 'working', 'ash-nazg' ); ?></span>
					<span class="ash-nazg-ml-10 ash-nazg-text-warning ash-nazg-hidden" id="ash-nazg-endpoints-unavailable-wrap">⊘ <span id="ash-nazg-endpoints-unavailable">0</span> <?php esc_html_e( 'unavailable', 'ash-nazg' ); ?></span>
					<span class="ash-nazg-ml-10 ash-nazg-text-muted ash-nazg-hidden" id="ash-nazg-endpoints-skipped-wrap">○ <span id="ash-nazg-endpoints-skipped">0</span> <?php esc_html_e( 'not probed', 'ash-nazg' ); ?></span>
					<span class="ash-nazg-ml-10 ash-nazg-text-error ash-nazg-hidden" id="ash-nazg-endpoints-error-wrap">✗ <span id="ash-nazg-endpoints-error">0</span> <?php esc_html_e( 'errors', 'ash-nazg' ); ?></span>
					<span class="ash-nazg-ml-10 ash-nazg-text-muted">(<span id="ash-nazg-endpoints-total">0</span> <?php esc_html_e( 'total endpoints', 'ash-nazg' ); ?>)</span>
				</p>

				<?php foreach ( $endpoint_groups as $group => $endpoint_rows ) : ?>
					<?php echo Admin\render_endpoint_group( $group, $endpoint_rows, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup escaped in the partials. ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( null === $site_info && null === $environment_info && null === $api_error ) : ?>
			<div class="ash-nazg-card ash-nazg-card-full">
				<h2><?php esc_html_e( 'Getting Started', 'ash-nazg' ); ?></h2>
				<p>
					<?php
					esc_html_e(
						'To connect to the Pantheon API, you need to configure a machine token.',
						'ash-nazg'
					);
					?>
				</p>
				<ol>
					<li>
						<?php
						printf(
							/* translators: %s: URL to Pantheon dashboard */
							esc_html__( 'Generate a machine token in your %s', 'ash-nazg' ),
							'<a href="https://dashboard.pantheon.io/users/#account/tokens" target="_blank">' .
							esc_html__( 'Pantheon account settings', 'ash-nazg' ) .
							'</a>'
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: URL to settings page */
							esc_html__( 'Enter the token in the %s', 'ash-nazg' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=ash-nazg-settings' ) ) . '">' .
							esc_html__( 'plugin settings', 'ash-nazg' ) .
							'</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Test the connection to verify it works', 'ash-nazg' ); ?></li>
				</ol>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Go to Settings', 'ash-nazg' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>
