<?php
/**
 * Settings page template.
 *
 * @package Pantheon\AshNazg
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pantheon\AshNazg\API;
?>

<div class="wrap">
	<?php \Pantheon\AshNazg\Admin\render_pantheon_header( get_admin_page_title() ); ?>

	<?php if ( ! empty( $message ) ) : ?>
		<?php
		// Check if this is a migration instructions message (contains terminus command).
		$is_instructions = strpos( $message, 'terminus secret:set' ) !== false;
		$notice_type = $is_instructions ? 'notice-info' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr( $notice_type ); ?> is-dismissible">
			<?php if ( $is_instructions ) : ?>
				<?php
				// Extract the command from the message for better formatting.
				preg_match( '/terminus secret:set [^\s]+ ash_nazg_machine_token_\d+ YOUR_TOKEN --scope=user,web/', $message, $matches );
				$command = ! empty( $matches[0] ) ? $matches[0] : '';

				// Split message into parts.
				$parts = explode( 'Run this command from your local terminal:', $message );
				$before_command = trim( $parts[0] );
				$after_command = isset( $parts[1] ) ? trim( str_replace( $command, '', $parts[1] ) ) : '';
				?>
				<p><?php echo esc_html( $before_command ); ?></p>
				<?php if ( $command ) : ?>
					<p><strong><?php esc_html_e( 'Run this command from your local terminal:', 'ash-nazg' ); ?></strong></p>
					<pre class="ash-nazg-code-block"><?php echo esc_html( $command ); ?></pre>
				<?php endif; ?>
				<?php if ( $after_command ) : ?>
					<p><?php echo esc_html( $after_command ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<p><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( null !== $test_result ) : ?>
		<?php if ( is_wp_error( $test_result ) ) : ?>
			<?php
			$error_code = $test_result->get_error_code();
			// Use warning for API availability issues, error for configuration issues.
			$notice_type = in_array( $error_code, [ 'api_unavailable', 'api_connection_failed' ], true ) ? 'warning' : 'error';
			?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible">
				<p><strong>
					<?php
					if ( 'api_unavailable' === $error_code ) {
						esc_html_e( 'API Temporarily Unavailable', 'ash-nazg' );
					} else {
						esc_html_e( 'Connection Test Failed', 'ash-nazg' );
					}
					?>
				</strong></p>
				<p><?php echo esc_html( $test_result->get_error_message() ); ?></p>
				<?php if ( 'api_unavailable' === $error_code ) : ?>
					<p>
						<em><?php esc_html_e( 'Note: Your token is stored correctly. This is a temporary Pantheon API issue, not a configuration problem.', 'ash-nazg' ); ?></em>
					</p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Connection Test Successful', 'ash-nazg' ); ?></strong></p>
				<p><?php esc_html_e( 'Successfully authenticated with Pantheon API.', 'ash-nazg' ); ?></p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20" style="margin-top: 30px;">
		<h2><?php esc_html_e( 'Environment Information', 'ash-nazg' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Running on Pantheon:', 'ash-nazg' ); ?></th>
				<td>
					<?php if ( $is_pantheon ) : ?>
						<span class="dashicons dashicons-yes-alt ash-nazg-icon-success"></span>
						<?php esc_html_e( 'Yes', 'ash-nazg' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-no ash-nazg-icon-error"></span>
						<?php esc_html_e( 'No', 'ash-nazg' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $is_pantheon ) : ?>
				<tr>
					<th><?php esc_html_e( 'Site ID:', 'ash-nazg' ); ?></th>
					<td><code><?php echo esc_html( $site_id ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Environment:', 'ash-nazg' ); ?></th>
					<td><code><?php echo esc_html( $environment ); ?></code></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( 'Pantheon Secrets API:', 'ash-nazg' ); ?></th>
				<td>
					<?php if ( $has_secret_api ) : ?>
						<span class="dashicons dashicons-yes-alt ash-nazg-icon-success"></span>
						<?php esc_html_e( 'Available', 'ash-nazg' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-no ash-nazg-icon-error"></span>
						<?php esc_html_e( 'Not available', 'ash-nazg' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<hr style="margin: 30px 0;">

		<?php if ( ! empty( $machine_token ) ) : ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Machine Token', 'ash-nazg' ); ?>
					</th>
					<td>
						<p>
							<span class="dashicons dashicons-yes-alt ash-nazg-icon-success"></span>
							<?php
							if ( $has_global_token && ! $has_user_token && ! $has_user_secret ) {
								esc_html_e( 'A site-wide machine token is currently set (shared by all admins).', 'ash-nazg' );
							} else {
								esc_html_e( 'A machine token is currently set.', 'ash-nazg' );
							}
							?>
						</p>
						<?php if ( $has_global_token && ! $has_user_token && ! $has_user_secret ) : ?>
							<p>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg-settings&ash_nazg_migrate=me' ), 'ash_nazg_migrate_token' ) ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Migrate to My Account', 'ash-nazg' ); ?>
								</a>
							</p>
							<p class="description">
								<?php esc_html_e( 'Starting with version 0.4.0, each admin can have their own machine token for better security and audit trails. Click "Migrate to My Account" to copy the site-wide token to your user account.', 'ash-nazg' ); ?>
							</p>
						<?php elseif ( $has_global_token && ( $has_user_token || $has_user_secret ) ) : ?>
							<p class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">
								<strong><?php esc_html_e( 'Global Token Still Exists:', 'ash-nazg' ); ?></strong>
								<?php esc_html_e( 'You have your own per-user token, but a site-wide token still exists (shared by all admins). For security, consider deleting the global token.', 'ash-nazg' ); ?>
							</p>
							<p>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ash-nazg-settings&ash_nazg_delete_global=1' ), 'ash_nazg_delete_global_token' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete the global token? Other admins without their own tokens will lose access.', 'ash-nazg' ); ?>');">
									<?php esc_html_e( 'Delete Global Token', 'ash-nazg' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		<?php else : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'ash_nazg_settings', 'ash_nazg_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ash_nazg_machine_token">
								<?php esc_html_e( 'Machine Token', 'ash-nazg' ); ?>
							</label>
						</th>
						<td>
							<?php if ( $has_secret_api && ! $has_user_secret ) : ?>
								<div class="notice notice-info inline" style="margin: 0 0 15px 0; padding: 10px;">
									<p>
										<strong><?php esc_html_e( 'Recommended: Use Pantheon Secrets for Your Token', 'ash-nazg' ); ?></strong>
									</p>
									<p>
										<?php
										esc_html_e(
											'For better security, store your machine token using Pantheon Secrets. Your token will be encrypted and retrieved at runtime.',
											'ash-nazg'
										);
										?>
									</p>
									<p>
										<strong><?php esc_html_e( 'Your User ID:', 'ash-nazg' ); ?></strong>
										<code class="ash-nazg-user-id"><?php echo absint( $user_id ); ?></code>
									</p>
									<p>
										<?php esc_html_e( 'Run this command from your local terminal (copy and paste):', 'ash-nazg' ); ?>
									</p>
									<pre class="ash-nazg-code-block">terminus secret:set <?php echo ! empty( $site_name ) ? esc_attr( $site_name ) : '<site>'; ?> ash_nazg_machine_token_<?php echo absint( $user_id ); ?> YOUR_TOKEN --scope=user,web</pre>
									<p class="description">
										<?php
										printf(
											/* translators: %s: user ID number */
											esc_html__( 'Note: The "_%s" suffix in the secret name is your user ID. Each WordPress admin can have their own Pantheon machine token.', 'ash-nazg' ),
											'<strong>' . absint( $user_id ) . '</strong>'
										);
										?>
									</p>
								</div>
								<p class="description">
									<?php esc_html_e( 'Alternatively, you can enter a token below which will be encrypted and stored in the WordPress database.', 'ash-nazg' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'Enter your Pantheon machine token below. It will be encrypted and stored in the WordPress database.', 'ash-nazg' ); ?>
								</p>
							<?php endif; ?>

							<input
								type="password"
								id="ash_nazg_machine_token"
								name="ash_nazg_machine_token"
								value=""
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter Pantheon machine token', 'ash-nazg' ); ?>"
							/>

							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to Pantheon dashboard */
									esc_html__( 'Generate a machine token in your %s.', 'ash-nazg' ),
									'<a href="https://dashboard.pantheon.io/users/#account/tokens" target="_blank">' .
									esc_html__( 'Pantheon account settings', 'ash-nazg' ) .
									'</a>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Settings', 'ash-nazg' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<div class="ash-nazg-dashboard">
		<div class="ash-nazg-card">
			<h2><?php esc_html_e( 'Test Connection', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'Test your Pantheon API connection to verify the machine token is valid.', 'ash-nazg' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'test_connection', 'test_connection_nonce' ); ?>
				<?php submit_button( __( 'Test Connection', 'ash-nazg' ), 'secondary', 'test_connection' ); ?>
			</form>
		</div>

		<div class="ash-nazg-card">
			<h2><?php esc_html_e( 'Clear Session Token', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'Clear the cached session token. Use this if you are experiencing authentication issues or after API downtime. A new session token will be generated automatically on the next API request.', 'ash-nazg' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'clear_session', 'clear_session_nonce' ); ?>
				<?php submit_button( __( 'Clear Session Token', 'ash-nazg' ), 'secondary', 'clear_session' ); ?>
			</form>
		</div>
	</div>
</div>
