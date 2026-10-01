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
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
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

	<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20 ash-nazg-mt-30">
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

		<hr class="ash-nazg-my-30">

		<h2><?php esc_html_e( 'Personal Access Token (v1 API)', 'ash-nazg' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Required for almost all plugin features, including the small set of v0-only fallback features.', 'ash-nazg' ); ?></p>

		<?php if ( ! empty( $access_token ) ) : ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Personal Access Token', 'ash-nazg' ); ?>
					</th>
					<td>
						<p>
							<span class="dashicons dashicons-yes-alt ash-nazg-icon-success"></span>
							<?php esc_html_e( 'A Personal Access Token is currently set.', 'ash-nazg' ); ?>
						</p>
					</td>
				</tr>
			</table>
		<?php else : ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'ash_nazg_settings', 'ash_nazg_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="ash_nazg_access_token">
								<?php esc_html_e( 'Personal Access Token', 'ash-nazg' ); ?>
							</label>
						</th>
						<td>
							<?php if ( $has_secret_api && ! $has_user_access_secret ) : ?>
								<div class="notice notice-info inline ash-nazg-notice-inline">
									<p>
										<strong><?php esc_html_e( 'Recommended: Use Pantheon Secrets for Your Token', 'ash-nazg' ); ?></strong>
									</p>
									<p>
										<?php esc_html_e( 'For better security, store your Personal Access Token using Pantheon Secrets. Your token will be encrypted and retrieved at runtime.', 'ash-nazg' ); ?>
									</p>
									<p>
										<strong><?php esc_html_e( 'Your User ID:', 'ash-nazg' ); ?></strong>
										<code class="ash-nazg-user-id"><?php echo absint( $user_id ); ?></code>
									</p>
									<p>
										<?php esc_html_e( 'Run this command from your local terminal (copy and paste):', 'ash-nazg' ); ?>
									</p>
									<pre class="ash-nazg-code-block">terminus secret:set <?php echo ! empty( $site_name ) ? esc_attr( $site_name ) : '<site>'; ?> ash_nazg_access_token_<?php echo absint( $user_id ); ?> YOUR_TOKEN --scope=user,web</pre>
									<p class="description">
										<?php
										printf(
											/* translators: %s: user ID number */
											esc_html__( 'Note: The "_%s" suffix in the secret name is your user ID. Each WordPress admin can have their own Personal Access Token.', 'ash-nazg' ),
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
									<?php esc_html_e( 'Enter your Pantheon Personal Access Token below. It will be encrypted and stored in the WordPress database.', 'ash-nazg' ); ?>
								</p>
							<?php endif; ?>

							<input
								type="password"
								id="ash_nazg_access_token"
								name="ash_nazg_access_token"
								value=""
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter Pantheon Personal Access Token', 'ash-nazg' ); ?>"
							/>

							<p class="description">
								<?php
								printf(
									/* translators: %s: URL to Pantheon dashboard */
									esc_html__( 'Generate a Personal Access Token in your %s.', 'ash-nazg' ),
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
					<?php submit_button( __( 'Save Personal Access Token', 'ash-nazg' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		<?php endif; ?>

	</div>

	<div class="ash-nazg-dashboard">
		<div class="ash-nazg-card">
			<h2><?php esc_html_e( 'Test Connection', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'Test your Pantheon API connection to verify your Personal Access Token is valid.', 'ash-nazg' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'test_connection', 'test_connection_nonce' ); ?>
				<?php submit_button( __( 'Test Connection', 'ash-nazg' ), 'secondary', 'test_connection' ); ?>
			</form>
		</div>
	</div>
</div>
