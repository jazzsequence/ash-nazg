<?php
/**
 * Dashboard page template.
 *
 * @package Pantheon\AshNazg
 */

use Pantheon\AshNazg\API;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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
	<?php else : ?>
		<div class="notice notice-success">
			<p>
				<strong><?php esc_html_e( 'API Connection Active', 'ash-nazg' ); ?></strong>
			</p>
			<p><?php esc_html_e( 'Successfully connected to Pantheon API.', 'ash-nazg' ); ?></p>
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

	<div class="ash-nazg-dashboard">
		<?php if ( $is_pantheon ) : ?>
			<div class="ash-nazg-card">
				<h2><?php esc_html_e( 'Environment Information', 'ash-nazg' ); ?></h2>
				<table class="widefat striped">
					<tbody>
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
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( null !== $site_info ) : ?>
			<div class="ash-nazg-card">
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
						// Get all 14 site info fields.
						$site_id = API\get_api_field( 'site', 'id' );
						$site_name = API\get_api_field( 'site', 'name' );
						$site_label = API\get_api_field( 'site', 'label' );
						$site_created = API\get_api_field( 'site', 'created' );
						$site_framework = API\get_api_field( 'site', 'framework' );
						$site_organization = API\get_api_field( 'site', 'organization' );
						$site_plan_name = API\get_api_field( 'site', 'plan_name' );
						$site_holder_type = API\get_api_field( 'site', 'holder_type' );
						$site_holder_id = API\get_api_field( 'site', 'holder_id' );
						$site_owner = API\get_api_field( 'site', 'owner' );
						$site_frozen = API\get_api_field( 'site', 'frozen' );
						$site_region = API\get_api_field( 'site', 'region' );
						$site_max_multidevs = API\get_api_field( 'site', 'max_num_multidevs' );
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
									<span id="ash-nazg-site-label-display">
										<?php echo esc_html( $site_label ); ?>
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
						<?php if ( $site_organization ) : ?>
							<tr>
								<th><?php esc_html_e( 'Organization', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $site_organization ); ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_plan_name ) : ?>
							<tr>
								<th><?php esc_html_e( 'Plan', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_plan_name ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_holder_type ) : ?>
							<tr>
								<th><?php esc_html_e( 'Holder Type', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( ucfirst( $site_holder_type ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_holder_id ) : ?>
							<tr>
								<th><?php esc_html_e( 'Holder ID', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $site_holder_id ); ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( $site_owner ) : ?>
							<tr>
								<th><?php esc_html_e( 'Owner', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $site_owner ); ?></code></td>
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
						<?php if ( $site_region ) : ?>
							<tr>
								<th><?php esc_html_e( 'Region', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( strtoupper( $site_region ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( null !== $site_max_multidevs ) : ?>
							<tr>
								<th><?php esc_html_e( 'Max Multidevs', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_max_multidevs ); ?></td>
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

		<?php if ( ! empty( $endpoints_site ) || ! empty( $endpoints_user ) || ! empty( $endpoints_all ) ) : ?>
			<?php
			/*
			 * Determine active tab.
			 * Verify nonce if tab parameter is present.
			 */
			$active_tab = 'site';
			if ( isset( $_GET['endpoints_tab'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ash_nazg_tab' ) ) {
				$active_tab = sanitize_text_field( wp_unslash( $_GET['endpoints_tab'] ) );
			}
			?>
			<div id="endpoints" class="ash-nazg-card ash-nazg-card-full">
				<div class="ash-nazg-flex-between ash-nazg-mb-10">
					<h2 class="ash-nazg-m-0">
						<?php esc_html_e( 'Available API Endpoints', 'ash-nazg' ); ?>
						<?php if ( $endpoints_cached_at ) : ?>
							<span class="ash-nazg-meta-text">
								(Last checked: <?php echo esc_html( human_time_diff( $endpoints_cached_at ) ); ?> ago)
							</span>
						<?php endif; ?>
					</h2>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&refresh_cache=1' ), 'ash_nazg_refresh_cache' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh Data', 'ash-nazg' ); ?>
					</a>
				</div>

				<h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=site#endpoints' ), 'ash_nazg_tab' ) ); ?>" class="nav-tab <?php echo 'site' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Site Endpoints', 'ash-nazg' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=user#endpoints' ), 'ash_nazg_tab' ) ); ?>" class="nav-tab <?php echo 'user' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'User Endpoints', 'ash-nazg' ); ?>
					</a>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=all#endpoints' ), 'ash_nazg_tab' ) ); ?>" class="nav-tab <?php echo 'all' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'All Endpoints', 'ash-nazg' ); ?>
					</a>
				</h2>

				<?php if ( 'site' === $active_tab ) : ?>
					<p><?php esc_html_e( 'Endpoints for managing this specific Pantheon site.', 'ash-nazg' ); ?></p>
					<?php $endpoints_status = $endpoints_site; ?>
				<?php elseif ( 'user' === $active_tab ) : ?>
					<p><?php esc_html_e( 'Endpoints for the current user (authenticated via machine token).', 'ash-nazg' ); ?></p>
					<?php $endpoints_status = $endpoints_user; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'All available Pantheon API endpoints accessible from this plugin.', 'ash-nazg' ); ?></p>
					<?php $endpoints_status = $endpoints_all; ?>
				<?php endif; ?>

				<?php
				$total_endpoints = 0;
				$successful = 0;
				$unavailable = 0;
				$endpoint_errors = 0;
				foreach ( $endpoints_status as $category => $endpoints ) {
					foreach ( $endpoints as $endpoint ) {
						++$total_endpoints;
						if ( 'success' === $endpoint['status'] ) {
							++$successful;
						} elseif ( 'unavailable' === $endpoint['status'] ) {
							++$unavailable;
						} else {
							++$endpoint_errors;
						}
					}
				}
				?>
				<p class="ash-nazg-my-10">
					<strong><?php esc_html_e( 'Summary:', 'ash-nazg' ); ?></strong>
					<span class="ash-nazg-text-success">✓ <?php echo esc_html( $successful ); ?> <?php esc_html_e( 'working', 'ash-nazg' ); ?></span>
					<?php if ( $unavailable > 0 ) : ?>
						<span class="ash-nazg-ml-10 ash-nazg-text-warning">⊘ <?php echo esc_html( $unavailable ); ?> <?php esc_html_e( 'unavailable', 'ash-nazg' ); ?></span>
					<?php endif; ?>
					<?php if ( $endpoint_errors > 0 ) : ?>
						<span class="ash-nazg-ml-10 ash-nazg-text-error">✗ <?php echo esc_html( $endpoint_errors ); ?> <?php esc_html_e( 'errors', 'ash-nazg' ); ?></span>
					<?php endif; ?>
					<span class="ash-nazg-ml-10 ash-nazg-text-muted">
						(<?php echo esc_html( $total_endpoints ); ?> <?php esc_html_e( 'total endpoints', 'ash-nazg' ); ?>)
					</span>
				</p>

				<?php foreach ( $endpoints_status as $category => $endpoints ) : ?>
					<h3 class="ash-nazg-section-header">
						<?php echo esc_html( $category ); ?>
						<span class="ash-nazg-label-text">
							(<?php echo count( $endpoints ); ?> <?php echo esc_html( _n( 'endpoint', 'endpoints', count( $endpoints ), 'ash-nazg' ) ); ?>)
						</span>
					</h3>
					<table class="widefat striped ash-nazg-table-mb">
						<thead>
							<tr>
								<th class="ash-nazg-th-icon"></th>
								<th class="ash-nazg-th-20"><?php esc_html_e( 'Endpoint', 'ash-nazg' ); ?></th>
								<th class="ash-nazg-th-30"><?php esc_html_e( 'Path', 'ash-nazg' ); ?></th>
								<th class="ash-nazg-th-15"><?php esc_html_e( 'Last Checked', 'ash-nazg' ); ?></th>
								<th><?php esc_html_e( 'Status / Data', 'ash-nazg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $endpoints as $endpoint ) : ?>
								<tr>
									<td class="ash-nazg-th-icon">
										<?php if ( 'success' === $endpoint['status'] ) : ?>
											<span class="dashicons dashicons-yes-alt ash-nazg-icon-success" title="<?php esc_attr_e( 'Available', 'ash-nazg' ); ?>"></span>
										<?php elseif ( 'unavailable' === $endpoint['status'] ) : ?>
											<span class="dashicons dashicons-minus ash-nazg-icon-warning" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Unavailable', 'ash-nazg' ) ); ?>"></span>
										<?php else : ?>
											<span class="dashicons dashicons-dismiss ash-nazg-icon-error" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Error', 'ash-nazg' ) ); ?>"></span>
										<?php endif; ?>
									</td>
									<td>
										<strong><?php echo esc_html( $endpoint['name'] ); ?></strong>
										<?php if ( ! empty( $endpoint['description'] ) ) : ?>
											<br><small class="ash-nazg-text-muted"><?php echo esc_html( $endpoint['description'] ); ?></small>
										<?php endif; ?>
									</td>
									<td><code class="ash-nazg-tiny-text"><?php echo esc_html( $endpoint['path'] ); ?></code></td>
									<td>
										<?php if ( $endpoints_cached_at ) : ?>
											<small class="ash-nazg-text-muted"><?php echo esc_html( human_time_diff( $endpoints_cached_at ) ); ?> ago</small>
										<?php else : ?>
											<small class="ash-nazg-text-light">—</small>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'success' === $endpoint['status'] && ! empty( $endpoint['data'] ) ) : ?>
											<?php foreach ( $endpoint['data'] as $data_key => $data_value ) : ?>
												<?php if ( is_scalar( $data_value ) ) : ?>
													<div class="ash-nazg-small-text">
														<strong><?php echo esc_html( ucfirst( $data_key ) ); ?>:</strong>
														<?php echo esc_html( $data_value ); ?>
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										<?php elseif ( 'success' !== $endpoint['status'] && ! empty( $endpoint['error'] ) ) : ?>
											<small class="ash-nazg-text-error"><?php echo esc_html( $endpoint['error'] ); ?></small>
										<?php else : ?>
											<small class="ash-nazg-text-light">—</small>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( null === $site_info && null === $environment_info && null === $api_error ) : ?>
			<div class="ash-nazg-card">
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
