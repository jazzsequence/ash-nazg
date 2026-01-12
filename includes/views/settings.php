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
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

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
			$notice_type = in_array( $error_code, array( 'api_unavailable', 'api_connection_failed' ), true ) ? 'warning' : 'error';
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

	<div class="card">
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
	</div>

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
					<?php if ( $has_secret_api ) : ?>
						<p class="description">
							<?php
							esc_html_e(
								'It is recommended that you store your machine token in Pantheon Secrets. To update it, use the Pantheon CLI:',
								'ash-nazg'
							);
							?>
						</p>
						<?php var_dump(API\get_site_info(), $_ENV); ?>
						<code>terminus secret:set ash_nazg_machine_token YOUR_TOKEN --scope=site</code>
						<p class="description">
							<?php
							esc_html_e(
								'For development/testing only, you can also enter a token below:',
								'ash-nazg'
							);
							?>
						</p>
					<?php endif; ?>

					<input
						type="password"
						id="ash_nazg_machine_token"
						name="ash_nazg_machine_token"
						value="<?php echo esc_attr( $machine_token ); ?>"
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

	<hr>

	<h2><?php esc_html_e( 'Test Connection', 'ash-nazg' ); ?></h2>
	<p><?php esc_html_e( 'Test your Pantheon API connection to verify the machine token is valid.', 'ash-nazg' ); ?></p>

	<form method="post" action="">
		<?php wp_nonce_field( 'test_connection', 'test_connection_nonce' ); ?>
		<?php submit_button( __( 'Test Connection', 'ash-nazg' ), 'secondary', 'test_connection' ); ?>
	</form>
</div>
