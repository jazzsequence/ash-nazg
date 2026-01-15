<?php
/**
 * Backups page template.
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

	<p><?php esc_html_e( 'Create, download, and restore backups for your Pantheon environments.', 'ash-nazg' ); ?></p>

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
			<p><?php esc_html_e( 'Not running on Pantheon. Backup features are not available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Create Backup -->
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
			<h2><?php esc_html_e( 'Create New Backup', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'Create a manual backup for any environment. Backups include code, database, and files.', 'ash-nazg' ); ?></p>

			<form method="post" id="ash-nazg-create-backup-form" class="ash-nazg-backup-form">
				<table class="form-table">
					<tbody>
						<?php if ( ! empty( $environments ) ) : ?>
						<tr>
							<th scope="row">
								<label for="backup-environment"><?php esc_html_e( 'Environment', 'ash-nazg' ); ?></label>
							</th>
							<td>
								<select name="environment" id="backup-environment" required>
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
										$is_selected = ( $env_id === $selected_env );
										?>
										<option value="<?php echo esc_attr( $env_id ); ?>" <?php selected( $is_selected ); ?>>
											<?php echo esc_html( strtoupper( $env_id ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select which environment to back up.', 'ash-nazg' ); ?>
								</p>
							</td>
						</tr>
						<?php endif; ?>
						<tr>
							<th scope="row">
								<label for="backup-element"><?php esc_html_e( 'Backup Type', 'ash-nazg' ); ?></label>
							</th>
							<td>
								<select name="element" id="backup-element" required>
									<option value="all"><?php esc_html_e( 'All (Code + Database + Files)', 'ash-nazg' ); ?></option>
									<option value="code"><?php esc_html_e( 'Code Only', 'ash-nazg' ); ?></option>
									<option value="database"><?php esc_html_e( 'Database Only', 'ash-nazg' ); ?></option>
									<option value="files"><?php esc_html_e( 'Files Only', 'ash-nazg' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose which components to include in the backup.', 'ash-nazg' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="backup-keep-for"><?php esc_html_e( 'Keep For', 'ash-nazg' ); ?></label>
							</th>
							<td>
								<input type="number" name="keep_for" id="backup-keep-for" value="365" min="1" class="small-text" required />
								<span><?php esc_html_e( 'days', 'ash-nazg' ); ?></span>
								<p class="description">
									<?php esc_html_e( 'Number of days to retain this backup (default: 365 days).', 'ash-nazg' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="ash-nazg-create-backup-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_create_backup' ) ); ?>">
						<?php esc_html_e( 'Create Backup', 'ash-nazg' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Existing Backups -->
		<div class="ash-nazg-card ash-nazg-card-full">
			<h2><?php esc_html_e( 'Existing Backups', 'ash-nazg' ); ?></h2>

			<?php if ( empty( $all_backups ) ) : ?>
				<p><?php esc_html_e( 'No backups found.', 'ash-nazg' ); ?></p>
			<?php else : ?>
				<?php
				/*
				 * Display backups organized by environment.
				 * Loop through each environment and show its backups.
				 */
				$env_order = [ 'dev', 'test', 'live' ];
				$all_env_ids = array_keys( $all_backups );

				// Separate standard envs and multidevs.
				$multidevs = array_diff( $all_env_ids, $env_order );
				sort( $multidevs );

				// Combine in display order.
				$ordered_envs = array_merge(
					array_intersect( $env_order, $all_env_ids ),
					$multidevs
				);

				foreach ( $ordered_envs as $env_id ) :
					$backups = $all_backups[ $env_id ];

					// Skip if no backups for this environment.
					if ( empty( $backups ) ) {
						continue;
					}
					?>

					<!-- Environment Header -->
					<h3 class="ash-nazg-backup-environment-header">
						<?php
						/* translators: %s: environment name (e.g., DEV, TEST, LIVE) */
						printf( esc_html__( '%s Environment Backups', 'ash-nazg' ), esc_html( strtoupper( $env_id ) ) );
						?>
					</h3>

					<?php
					// Group backups by folder (which represents a backup set).
					$backup_sets = [];
					foreach ( $backups as $backup_id => $backup ) {
						$folder = isset( $backup['folder'] ) ? $backup['folder'] : $backup_id;
						if ( ! isset( $backup_sets[ $folder ] ) ) {
							$backup_sets[ $folder ] = [
								'timestamp' => isset( $backup['timestamp'] ) ? $backup['timestamp'] : ( isset( $backup['finish_time'] ) ? $backup['finish_time'] : 0 ),
								'backups' => [],
							];
						}

						// Determine element type from filename or backup_id.
						$element = 'unknown';
						if ( isset( $backup['filename'] ) ) {
							if ( strpos( $backup['filename'], '_code.tar.gz' ) !== false ) {
								$element = 'code';
							} elseif ( strpos( $backup['filename'], '_database.sql.gz' ) !== false ) {
								$element = 'database';
							} elseif ( strpos( $backup['filename'], '_files.tar.gz' ) !== false ) {
								$element = 'files';
							}
						}

						$backup_sets[ $folder ]['backups'][ $element ] = [
							'id' => $backup_id,
							'filename' => isset( $backup['filename'] ) ? $backup['filename'] : '',
							'size' => isset( $backup['size'] ) ? $backup['size'] : 0,
							'ttl' => isset( $backup['ttl'] ) ? $backup['ttl'] : 0,
						];
					}

					// Sort backup sets by timestamp (newest first).
					uasort(
						$backup_sets,
						function ( $a, $b ) {
							return $b['timestamp'] - $a['timestamp'];
						}
					);
					?>

				<div class="ash-nazg-backups-list">
					<?php foreach ( $backup_sets as $folder => $backup_set ) : ?>
						<div class="ash-nazg-backup-set ash-nazg-mb-20">
							<div class="ash-nazg-backup-set-header ash-nazg-backup-toggle" role="button" tabindex="0">
								<div class="ash-nazg-backup-set-title">
									<span class="ash-nazg-backup-toggle-icon">▶</span>
									<h3>
										<?php
										$date = $backup_set['timestamp'] ? gmdate( 'F j, Y g:i A', $backup_set['timestamp'] ) : esc_html__( 'Unknown date', 'ash-nazg' );
										echo esc_html( $date );
										?>
									</h3>
								</div>
								<span class="ash-nazg-text-meta">
									<?php
									$relative_time = $backup_set['timestamp'] ? human_time_diff( $backup_set['timestamp'], time() ) : '';
									if ( $relative_time ) {
										/* translators: %s: relative time (e.g., "2 days") */
										printf( esc_html__( '%s ago', 'ash-nazg' ), esc_html( $relative_time ) );
									}
									?>
								</span>
							</div>

							<table class="widefat striped ash-nazg-backup-elements-table ash-nazg-backup-table-collapsed">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Element', 'ash-nazg' ); ?></th>
										<th><?php esc_html_e( 'Size', 'ash-nazg' ); ?></th>
										<th><?php esc_html_e( 'Expires', 'ash-nazg' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'ash-nazg' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( [ 'code', 'database', 'files' ] as $element ) : ?>
										<?php if ( isset( $backup_set['backups'][ $element ] ) ) : ?>
											<?php $backup = $backup_set['backups'][ $element ]; ?>
											<tr>
												<td>
													<strong><?php echo esc_html( ucfirst( $element ) ); ?></strong>
													<?php if ( $backup['filename'] ) : ?>
														<br />
														<span class="ash-nazg-text-meta"><?php echo esc_html( $backup['filename'] ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<?php
													$size = $backup['size'];
													if ( $size > 0 ) {
														echo esc_html( size_format( $size, 2 ) );
													} else {
														esc_html_e( 'Unknown', 'ash-nazg' );
													}
													?>
												</td>
												<td>
													<?php
													$ttl = $backup['ttl'];
													if ( $ttl > 0 ) {
														$expires = $backup_set['timestamp'] + $ttl;
														$days_left = round( ( $expires - time() ) / DAY_IN_SECONDS );
														if ( $days_left > 0 ) {
															/* translators: %d: number of days */
															printf( esc_html__( 'In %d days', 'ash-nazg' ), absint( $days_left ) );
														} else {
															esc_html_e( 'Expired', 'ash-nazg' );
														}
													} else {
														esc_html_e( 'Unknown', 'ash-nazg' );
													}
													?>
												</td>
												<td>
													<button
														type="button"
														class="button button-small ash-nazg-download-backup"
														data-backup-id="<?php echo esc_attr( $folder ); ?>"
														data-element="<?php echo esc_attr( $element ); ?>"
														data-environment="<?php echo esc_attr( $env_id ); ?>"
														data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_download_backup' ) ); ?>"
													>
														<?php esc_html_e( 'Download', 'ash-nazg' ); ?>
													</button>
													<button
														type="button"
														class="button button-small ash-nazg-restore-backup"
														data-backup-id="<?php echo esc_attr( $folder ); ?>"
														data-element="<?php echo esc_attr( $element ); ?>"
														data-environment="<?php echo esc_attr( $env_id ); ?>"
														data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_restore_backup' ) ); ?>"
													>
														<?php esc_html_e( 'Restore', 'ash-nazg' ); ?>
													</button>
												</td>
											</tr>
										<?php endif; ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>

					<!-- Add visual separation between environments -->
					<div class="ash-nazg-backup-environment-separator"></div>

				<?php endforeach; // End environment loop. ?>
			</div>
		<?php endif; ?>
		</div>

	<?php endif; ?>
</div>

<!-- Progress Modal (reused from development.js pattern) -->
<div id="ash-nazg-backup-progress-modal" class="ash-nazg-modal" style="display: none;">
	<div class="ash-nazg-modal-content">
		<h2 id="ash-nazg-backup-progress-title"><?php esc_html_e( 'Processing...', 'ash-nazg' ); ?></h2>
		<div class="ash-nazg-progress-bar">
			<div class="ash-nazg-progress-bar-inner"></div>
		</div>
		<p id="ash-nazg-backup-progress-message"><?php esc_html_e( 'Please wait...', 'ash-nazg' ); ?></p>
	</div>
</div>
