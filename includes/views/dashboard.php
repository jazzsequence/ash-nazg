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
		$notice_type = in_array( $error_code, array( 'api_unavailable', 'api_connection_failed' ), true ) ? 'warning' : 'error';
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
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( null !== $site_info ) : ?>
			<div class="ash-nazg-card">
				<h2>
					<?php esc_html_e( 'Site Information (from API)', 'ash-nazg' ); ?>
					<?php if ( $site_info_cached_at ) : ?>
						<span style="font-size: 12px; font-weight: normal; color: #757575;">
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
								<td><?php echo esc_html( $site_label ); ?></td>
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
								<td><?php echo esc_html( ucfirst( $site_framework ) ); ?></td>
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
											<br><small><?php echo esc_html( $site_upstream['label'] ); ?></small>
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

		<?php if ( null !== $environment_info ) : ?>
			<div class="ash-nazg-card">
				<h2>
					<?php esc_html_e( 'Current Environment Details (from API)', 'ash-nazg' ); ?>
					<?php if ( $env_info_cached_at ) : ?>
						<span style="font-size: 12px; font-weight: normal; color: #757575;">
							(Last checked: <?php echo esc_html( human_time_diff( $env_info_cached_at ) ); ?> ago)
						</span>
					<?php endif; ?>
				</h2>
				<table class="widefat striped">
					<tbody>
						<?php if ( ! empty( $environment_info['id'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Environment ID', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $environment_info['id'] ); ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( isset( $environment_info['on_server_development'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Development Mode', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( $environment_info['on_server_development'] ) : ?>
										<span class="ash-nazg-badge ash-nazg-badge-sftp">SFTP Mode</span>
									<?php else : ?>
										<span class="ash-nazg-badge ash-nazg-badge-git">Git Mode</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $environment_info['php_version'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'PHP Version', 'ash-nazg' ); ?></th>
								<td><code><?php echo esc_html( $environment_info['php_version'] ); ?></code></td>
							</tr>
						<?php endif; ?>
						<?php if ( isset( $environment_info['lock'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Environment Lock', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( ! empty( $environment_info['lock']['locked'] ) ) : ?>
										<span class="dashicons dashicons-lock" style="color: #dc3232;"></span>
										<?php esc_html_e( 'Locked', 'ash-nazg' ); ?>
										<?php if ( ! empty( $environment_info['lock']['username'] ) ) : ?>
											<br>
											<small>
												<?php
												printf(
													/* translators: %s: username */
													esc_html__( 'by %s', 'ash-nazg' ),
													esc_html( $environment_info['lock']['username'] )
												);
												?>
											</small>
										<?php endif; ?>
									<?php else : ?>
										<span class="dashicons dashicons-unlock" style="color: #46b450;"></span>
										<?php esc_html_e( 'Unlocked', 'ash-nazg' ); ?>
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
			// Determine active tab.
			$active_tab = isset( $_GET['endpoints_tab'] ) ? sanitize_text_field( wp_unslash( $_GET['endpoints_tab'] ) ) : 'site';
			?>
			<div class="ash-nazg-card" style="grid-column: 1 / -1;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
					<h2 style="margin: 0;">
						<?php esc_html_e( 'Available API Endpoints', 'ash-nazg' ); ?>
						<?php if ( $endpoints_cached_at ) : ?>
							<span style="font-size: 12px; font-weight: normal; color: #757575;">
								(Last checked: <?php echo esc_html( human_time_diff( $endpoints_cached_at ) ); ?> ago)
							</span>
						<?php endif; ?>
					</h2>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg&refresh_cache=1' ), 'ash_nazg_refresh_cache' ) ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
						<?php esc_html_e( 'Refresh Data', 'ash-nazg' ); ?>
					</a>
				</div>

				<h2 class="nav-tab-wrapper">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=site' ) ); ?>" class="nav-tab <?php echo 'site' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Site Endpoints', 'ash-nazg' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=user' ) ); ?>" class="nav-tab <?php echo 'user' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'User Endpoints', 'ash-nazg' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ash-nazg&endpoints_tab=all' ) ); ?>" class="nav-tab <?php echo 'all' === $active_tab ? 'nav-tab-active' : ''; ?>">
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
				$errors = 0;
				foreach ( $endpoints_status as $category => $endpoints ) {
					foreach ( $endpoints as $endpoint ) {
						$total_endpoints++;
						if ( 'success' === $endpoint['status'] ) {
							$successful++;
						} elseif ( 'unavailable' === $endpoint['status'] ) {
							$unavailable++;
						} else {
							$errors++;
						}
					}
				}
				?>
				<p style="margin: 10px 0;">
					<strong><?php esc_html_e( 'Summary:', 'ash-nazg' ); ?></strong>
					<span style="color: #46b450;">✓ <?php echo esc_html( $successful ); ?> <?php esc_html_e( 'working', 'ash-nazg' ); ?></span>
					<?php if ( $unavailable > 0 ) : ?>
						<span style="margin-left: 10px; color: #dba617;">⊘ <?php echo esc_html( $unavailable ); ?> <?php esc_html_e( 'unavailable', 'ash-nazg' ); ?></span>
					<?php endif; ?>
					<?php if ( $errors > 0 ) : ?>
						<span style="margin-left: 10px; color: #dc3232;">✗ <?php echo esc_html( $errors ); ?> <?php esc_html_e( 'errors', 'ash-nazg' ); ?></span>
					<?php endif; ?>
					<span style="margin-left: 10px; color: #666;">
						(<?php echo esc_html( $total_endpoints ); ?> <?php esc_html_e( 'total endpoints', 'ash-nazg' ); ?>)
					</span>
				</p>

				<?php foreach ( $endpoints_status as $category => $endpoints ) : ?>
					<h3 style="margin-top: 20px; margin-bottom: 10px; font-size: 16px; border-bottom: 1px solid #dcdcde; padding-bottom: 5px;">
						<?php echo esc_html( $category ); ?>
						<span style="color: #666; font-weight: normal; font-size: 13px;">
							(<?php echo count( $endpoints ); ?> <?php echo _n( 'endpoint', 'endpoints', count( $endpoints ), 'ash-nazg' ); ?>)
						</span>
					</h3>
					<table class="widefat striped" style="margin-bottom: 20px;">
						<thead>
							<tr>
								<th style="width: 35px;"></th>
								<th style="width: 20%;"><?php esc_html_e( 'Endpoint', 'ash-nazg' ); ?></th>
								<th style="width: 30%;"><?php esc_html_e( 'Path', 'ash-nazg' ); ?></th>
								<th style="width: 15%;"><?php esc_html_e( 'Last Checked', 'ash-nazg' ); ?></th>
								<th><?php esc_html_e( 'Status / Data', 'ash-nazg' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $endpoints as $endpoint ) : ?>
								<tr>
									<td style="text-align: center;">
										<?php if ( 'success' === $endpoint['status'] ) : ?>
											<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php esc_attr_e( 'Available', 'ash-nazg' ); ?>"></span>
										<?php elseif ( 'unavailable' === $endpoint['status'] ) : ?>
											<span class="dashicons dashicons-minus" style="color: #dba617;" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Unavailable', 'ash-nazg' ) ); ?>"></span>
										<?php else : ?>
											<span class="dashicons dashicons-dismiss" style="color: #dc3232;" title="<?php echo esc_attr( $endpoint['error'] ?? __( 'Error', 'ash-nazg' ) ); ?>"></span>
										<?php endif; ?>
									</td>
									<td>
										<strong><?php echo esc_html( $endpoint['name'] ); ?></strong>
										<?php if ( ! empty( $endpoint['description'] ) ) : ?>
											<br><small style="color: #666;"><?php echo esc_html( $endpoint['description'] ); ?></small>
										<?php endif; ?>
									</td>
									<td><code style="font-size: 11px;"><?php echo esc_html( $endpoint['path'] ); ?></code></td>
									<td>
										<?php if ( $endpoints_cached_at ) : ?>
											<small style="color: #666;"><?php echo esc_html( human_time_diff( $endpoints_cached_at ) ); ?> ago</small>
										<?php else : ?>
											<small style="color: #999;">—</small>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'success' === $endpoint['status'] && ! empty( $endpoint['data'] ) ) : ?>
											<?php foreach ( $endpoint['data'] as $data_key => $data_value ) : ?>
												<?php if ( is_scalar( $data_value ) ) : ?>
													<div style="font-size: 12px;">
														<strong><?php echo esc_html( ucfirst( $data_key ) ); ?>:</strong>
														<?php echo esc_html( $data_value ); ?>
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										<?php elseif ( 'success' !== $endpoint['status'] && ! empty( $endpoint['error'] ) ) : ?>
											<small style="color: #dc3232;"><?php echo esc_html( $endpoint['error'] ); ?></small>
										<?php else : ?>
											<small style="color: #999;">—</small>
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
