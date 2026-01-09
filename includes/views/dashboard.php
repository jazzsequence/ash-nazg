<?php
/**
 * Dashboard page template.
 *
 * @package Pantheon\AshNazg
 */

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
				<h2><?php esc_html_e( 'Site Information (from API)', 'ash-nazg' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<?php if ( ! empty( $site_info['name'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Site Name', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_info['name'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $site_info['label'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Site Label', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_info['label'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $site_info['framework'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Framework', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( $site_info['framework'] ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $site_info['created'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Created', 'ash-nazg' ); ?></th>
								<td><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $site_info['created'] ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $site_info['frozen'] ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Status', 'ash-nazg' ); ?></th>
								<td>
									<?php if ( $site_info['frozen'] ) : ?>
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
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<?php if ( null !== $environment_info ) : ?>
			<div class="ash-nazg-card">
				<h2><?php esc_html_e( 'Current Environment Details (from API)', 'ash-nazg' ); ?></h2>
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
