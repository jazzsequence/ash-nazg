<?php
/**
 * Clone content page template.
 *
 * @package Pantheon\AshNazg
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pantheon\AshNazg\API;
use Pantheon\AshNazg\Helpers;
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'Clone database and/or files from one environment to another.', 'ash-nazg' ); ?></p>

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong><?php echo esc_html( $message ); ?></strong></p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong> <?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $site_id ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Not running on Pantheon. Clone features are not available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Clone Content Form -->
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
			<h2><?php esc_html_e( 'Clone Content Between Environments', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'Select source and target environments, then choose what to clone.', 'ash-nazg' ); ?></p>

			<form id="ash-nazg-clone-form" class="ash-nazg-clone-form">
				<table class="form-table">
					<tbody>
						<?php if ( ! empty( $environments ) ) : ?>
						<tr>
							<th scope="row">
								<label for="clone-from-env"><?php esc_html_e( 'Clone From', 'ash-nazg' ); ?></label>
							</th>
							<td>
								<select name="from_env" id="clone-from-env" required>
									<option value=""><?php esc_html_e( '— Select source environment —', 'ash-nazg' ); ?></option>
									<?php
									// Define environment order.
									$env_order = [ 'dev', 'test', 'live' ];
									$multidevs = [];

									// Separate standard envs and multidevs.
									foreach ( $environments as $env_id => $env_data ) {
										if ( ! in_array( $env_id, $env_order, true ) ) {
											$multidevs[] = $env_id;
										}
									}
									sort( $multidevs );

									// Combine in display order.
									$all_envs = array_merge( $env_order, $multidevs );

									foreach ( $all_envs as $env_id ) :
										if ( ! isset( $environments[ $env_id ] ) ) {
											continue;
										}
										$is_initialized = isset( $initialized[ $env_id ] ) && $initialized[ $env_id ];
										$disabled = ! $is_initialized ? 'disabled' : '';
										?>
										<option value="<?php echo esc_attr( $env_id ); ?>" <?php echo esc_attr( $disabled ); ?>>
											<?php echo esc_html( strtoupper( $env_id ) ); ?>
											<?php if ( ! $is_initialized ) : ?>
												<?php esc_html_e( '(Not Initialized)', 'ash-nazg' ); ?>
											<?php endif; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Environment to copy content from.', 'ash-nazg' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="clone-to-env"><?php esc_html_e( 'Clone To', 'ash-nazg' ); ?></label>
							</th>
							<td>
								<select name="to_env" id="clone-to-env" required>
									<option value=""><?php esc_html_e( '— Select target environment —', 'ash-nazg' ); ?></option>
									<?php
									foreach ( $all_envs as $env_id ) :
										if ( ! isset( $environments[ $env_id ] ) ) {
											continue;
										}
										$is_initialized = isset( $initialized[ $env_id ] ) && $initialized[ $env_id ];
										$disabled = ! $is_initialized ? 'disabled' : '';
										?>
										<option value="<?php echo esc_attr( $env_id ); ?>" <?php echo esc_attr( $disabled ); ?>>
											<?php echo esc_html( strtoupper( $env_id ) ); ?>
											<?php if ( ! $is_initialized ) : ?>
												<?php esc_html_e( '(Not Initialized)', 'ash-nazg' ); ?>
											<?php endif; ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Environment to copy content to (will be overwritten).', 'ash-nazg' ); ?>
								</p>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'What to Clone', 'ash-nazg' ); ?>
							</th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'What to Clone', 'ash-nazg' ); ?></legend>
									<label>
										<input type="checkbox" name="clone_database" id="clone-database" value="1" checked>
										<strong><?php esc_html_e( 'Database', 'ash-nazg' ); ?></strong>
										<p class="description ash-nazg-ml-10">
											<?php esc_html_e( 'Clone database with automatic URL search-replace for WordPress.', 'ash-nazg' ); ?>
										</p>
									</label>
									<br>
									<label>
										<input type="checkbox" name="clone_files" id="clone-files" value="1" checked>
										<strong><?php esc_html_e( 'Files', 'ash-nazg' ); ?></strong>
										<p class="description ash-nazg-ml-10">
											<?php esc_html_e( 'Clone uploaded files and media.', 'ash-nazg' ); ?>
										</p>
									</label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Warning about destructive operation -->
				<div class="notice notice-warning inline">
					<p>
						<strong>⚠️ <?php esc_html_e( 'Warning: This will OVERWRITE the target environment\'s content!', 'ash-nazg' ); ?></strong>
						<?php esc_html_e( 'The target environment database and/or files will be replaced with content from the source environment. This action cannot be undone.', 'ash-nazg' ); ?>
					</p>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary" id="ash-nazg-clone-submit">
						<?php esc_html_e( 'Clone Content', 'ash-nazg' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Workflow Progress Modal (hidden initially) -->
		<div id="ash-nazg-clone-progress-modal" class="ash-nazg-modal" style="display:none;">
			<div class="ash-nazg-modal-content">
				<h2><?php esc_html_e( 'Clone Progress', 'ash-nazg' ); ?></h2>
				<div id="ash-nazg-clone-progress-content">
					<p><?php esc_html_e( 'Cloning content...', 'ash-nazg' ); ?></p>
					<div class="ash-nazg-progress-bar">
						<div class="ash-nazg-progress-fill"></div>
					</div>
				</div>
				<div id="ash-nazg-clone-progress-status"></div>
			</div>
		</div>

	<?php endif; ?>
</div>
