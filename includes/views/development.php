<?php
/**
 * Development page template.
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

	<p><?php esc_html_e( 'Git commits, upstream updates, and code management.', 'ash-nazg' ); ?></p>

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
			<p><?php esc_html_e( 'Not running on Pantheon. Git features are not available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Merge Dev to Multidev (only shown in multidev environments) -->
		<?php
		$current_env = API\get_pantheon_environment();
		$is_multidev = Helpers\is_multidev_environment( $current_env );
		?>
		<?php
		// Check if dev has different commits than this multidev.
		$has_dev_changes = $is_multidev && Helpers\dev_has_changes_for_env( $site_id, $current_env );
		?>
		<?php if ( $is_multidev && $has_dev_changes ) : ?>
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mb-20">
			<h2><?php esc_html_e( 'Merge Dev Changes', 'ash-nazg' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: current environment name */
					esc_html__( 'Merge the latest changes from the dev environment into %s.', 'ash-nazg' ),
					'<strong>' . esc_html( $current_env ) . '</strong>'
				);
				?>
			</p>
			<button type="button" class="button button-primary" id="ash-nazg-merge-dev-to-multidev" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_merge_dev_to_multidev' ) ); ?>">
				<?php esc_html_e( 'Merge Dev into This Environment', 'ash-nazg' ); ?>
			</button>
			<p class="description">
				<?php esc_html_e( 'This will merge all changes from dev into your current multidev environment. This action cannot be undone.', 'ash-nazg' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<!-- Upstream Updates -->
		<?php
		// Extract actual updates from update_log object.
		$updates_list = [];
		$update_count = 0;
		$behind = 0;
		if ( ! is_wp_error( $upstream_updates ) && $upstream_updates && is_array( $upstream_updates ) ) {
			$updates_list = isset( $upstream_updates['update_log'] ) ? $upstream_updates['update_log'] : [];
			$update_count = count( $updates_list );
			$behind = isset( $upstream_updates['behind'] ) ? $upstream_updates['behind'] : 0;
		}
		?>
		<?php if ( $update_count > 0 ) : ?>
		<div class="ash-nazg-card ash-nazg-card-full">
			<h2><?php esc_html_e( 'Upstream Updates', 'ash-nazg' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of upstream updates */
						_n( '%d upstream update available:', '%d upstream updates available:', $update_count, 'ash-nazg' ),
						$update_count
					)
				);
				if ( $behind > 0 ) {
					echo ' ';
					printf(
						/* translators: %d: number of commits behind */
						esc_html__( '(You are %d commit behind)', 'ash-nazg' ),
						absint( $behind )
					);
				}
				?>
			</p>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Hash', 'ash-nazg' ); ?></th>
						<th><?php esc_html_e( 'Message', 'ash-nazg' ); ?></th>
						<th><?php esc_html_e( 'Date', 'ash-nazg' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $updates_list as $update ) : ?>
					<tr>
						<td>
							<?php
							$full_hash = $update['hash'] ?? $update['id'] ?? 'unknown';
							$short_hash = substr( $full_hash, 0, 8 );
							?>
							<?php /* translators: %s: full commit hash */ ?><code class="ash-nazg-hash-copyable" data-hash="<?php echo esc_attr( $full_hash ); ?>" title="<?php echo esc_attr( sprintf( __( 'Click to copy: %s', 'ash-nazg' ), $full_hash ) ); ?>">
								<?php echo esc_html( $short_hash ); ?>
								<span class="dashicons dashicons-clipboard"></span>
							</code>
						</td>
						<td>
							<?php echo esc_html( $update['message'] ?? $update['commit_message'] ?? 'No message' ); ?>
						</td>
						<td>
							<?php
							$timestamp = $update['datetime'] ?? $update['timestamp'] ?? null;
							if ( $timestamp ) {
								echo esc_html( human_time_diff( is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp ), time() ) );
								echo ' ' . esc_html__( 'ago', 'ash-nazg' );
							} else {
								esc_html_e( 'Unknown', 'ash-nazg' );
							}
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="ash-nazg-mt-20">
				<button type="button" class="button button-primary" id="ash-nazg-apply-upstream-updates" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_apply_upstream_updates' ) ); ?>">
					<?php esc_html_e( 'Apply Upstream Updates', 'ash-nazg' ); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'This will apply all available upstream updates to your environment. This action cannot be undone.', 'ash-nazg' ); ?>
				</p>
			</div>

			<details class="ash-nazg-mt-20">
				<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
				<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $upstream_updates, JSON_PRETTY_PRINT ) ); ?></pre>
			</details>
		</div>
		<?php endif; ?>

		<!-- Code Deployment -->
		<?php
		// Check if dev has ANY changes for test (even 1 commit).
		$dev_commits = API\get_environment_commits( $site_id, 'dev' );
		$test_commits_for_deploy = API\get_environment_commits( $site_id, 'test' );
		$dev_has_changes_for_test = false;

		if ( is_array( $dev_commits ) && is_array( $test_commits_for_deploy ) && ! empty( $dev_commits ) ) {
			// Check if dev's most recent commit exists in test.
			$dev_latest = $dev_commits[0] ?? null;
			if ( $dev_latest ) {
				$dev_latest_hash = $dev_latest['hash'] ?? $dev_latest['id'] ?? null;
				if ( $dev_latest_hash ) {
					$found_in_test = false;
					foreach ( $test_commits_for_deploy as $test_commit ) {
						$test_hash = $test_commit['hash'] ?? $test_commit['id'] ?? null;
						if ( $test_hash === $dev_latest_hash ) {
							$found_in_test = true;
							break;
						}
					}
					$dev_has_changes_for_test = ! $found_in_test;
				}
			}
		}

		// Check if test has ANY changes for live (even 1 commit).
		$test_commits = API\get_environment_commits( $site_id, 'test' );
		$live_commits = API\get_environment_commits( $site_id, 'live' );
		$test_has_changes_for_live = false;

		if ( is_array( $test_commits ) && is_array( $live_commits ) && ! empty( $test_commits ) ) {
			// Check if test's most recent commit exists in live.
			$test_latest = $test_commits[0] ?? null;
			if ( $test_latest ) {
				$test_latest_hash = $test_latest['hash'] ?? $test_latest['id'] ?? null;
				if ( $test_latest_hash ) {
					$found_in_live = false;
					foreach ( $live_commits as $live_commit ) {
						$live_hash = $live_commit['hash'] ?? $live_commit['id'] ?? null;
						if ( $live_hash === $test_latest_hash ) {
							$found_in_live = true;
							break;
						}
					}
					$test_has_changes_for_live = ! $found_in_live;
				}
			}
		}
		?>
		<div class="ash-nazg-card ash-nazg-card-full">
			<h2><?php esc_html_e( 'Code Deployment', 'ash-nazg' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Deploy code between environments. Buttons are disabled if there are no changes to deploy.', 'ash-nazg' ); ?></p>

			<div class="ash-nazg-deploy-container">
				<!-- Deploy to Test -->
				<div class="ash-nazg-deploy-column">
					<h3><?php esc_html_e( 'Deploy to Test', 'ash-nazg' ); ?></h3>
					<?php if ( ! $test_initialized ) : ?>
						<div class="notice notice-error inline">
							<p>
								<strong><?php esc_html_e( 'Test environment is not initialized.', 'ash-nazg' ); ?></strong><br />
								<?php esc_html_e( 'You must initialize it via the Pantheon Dashboard before deploying code.', 'ash-nazg' ); ?>
							</p>
						</div>
					<?php elseif ( ! $dev_has_changes_for_test ) : ?>
						<p class="description ash-nazg-text-muted"><?php esc_html_e( 'Test environment is up-to-date with dev.', 'ash-nazg' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Deploy code from dev to test.', 'ash-nazg' ); ?></p>
					<?php endif; ?>
					<button id="ash-nazg-deploy-to-test-toggle" class="button button-primary" <?php echo ( ! $dev_has_changes_for_test || ! $test_initialized ) ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Deploy to Test', 'ash-nazg' ); ?>
					</button>

					<!-- Deploy panel (hidden by default) -->
					<div id="ash-nazg-deploy-to-test-panel" class="ash-nazg-deploy-panel" style="display: none;">
						<p>
							<label for="ash-nazg-deploy-note-test">
								<?php esc_html_e( 'Deployment Note (optional):', 'ash-nazg' ); ?>
							</label>
							<textarea id="ash-nazg-deploy-note-test" class="ash-nazg-deploy-note" rows="3" placeholder="<?php esc_attr_e( 'e.g., Deploy feature X for testing', 'ash-nazg' ); ?>"></textarea>
						</p>
						<button id="ash-nazg-deploy-to-test" class="button button-primary" data-target="test" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_deploy_code' ) ); ?>">
							<?php esc_html_e( 'Deploy Now', 'ash-nazg' ); ?>
						</button>
						<button id="ash-nazg-deploy-to-test-cancel" class="button">
							<?php esc_html_e( 'Cancel', 'ash-nazg' ); ?>
						</button>
					</div>
				</div>

				<!-- Deploy to Live -->
				<div class="ash-nazg-deploy-column">
					<h3><?php esc_html_e( 'Deploy to Live', 'ash-nazg' ); ?></h3>
					<?php if ( ! $live_initialized ) : ?>
						<div class="notice notice-error inline">
							<p>
								<strong><?php esc_html_e( 'Live environment is not initialized.', 'ash-nazg' ); ?></strong><br />
								<?php esc_html_e( 'You must initialize it via the Pantheon Dashboard before deploying code.', 'ash-nazg' ); ?>
							</p>
						</div>
					<?php elseif ( ! $test_has_changes_for_live ) : ?>
						<p class="description ash-nazg-text-muted"><?php esc_html_e( 'Live environment is up-to-date with test.', 'ash-nazg' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Deploy code from test to live.', 'ash-nazg' ); ?></p>
					<?php endif; ?>
					<button id="ash-nazg-deploy-to-live-toggle" class="button button-primary" <?php echo ( ! $test_has_changes_for_live || ! $live_initialized ) ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Deploy to Live', 'ash-nazg' ); ?>
					</button>

					<!-- Deploy panel (hidden by default) -->
					<div id="ash-nazg-deploy-to-live-panel" class="ash-nazg-deploy-panel" style="display: none;">
						<p>
							<label for="ash-nazg-deploy-note-live">
								<?php esc_html_e( 'Deployment Note (optional):', 'ash-nazg' ); ?>
							</label>
							<textarea id="ash-nazg-deploy-note-live" class="ash-nazg-deploy-note" rows="3" placeholder="<?php esc_attr_e( 'e.g., Deploy to production', 'ash-nazg' ); ?>"></textarea>
						</p>
						<p>
							<label>
								<input type="checkbox" id="ash-nazg-sync-content" />
								<?php esc_html_e( 'Sync content from live to test after deployment', 'ash-nazg' ); ?>
							</label>
						</p>
						<button id="ash-nazg-deploy-to-live" class="button button-primary" data-target="live" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_deploy_code' ) ); ?>">
							<?php esc_html_e( 'Deploy Now', 'ash-nazg' ); ?>
						</button>
						<button id="ash-nazg-deploy-to-live-cancel" class="button">
							<?php esc_html_e( 'Cancel', 'ash-nazg' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Uncommitted SFTP Changes -->
		<?php if ( 'sftp' === $connection_mode && $diffstat && ! is_wp_error( $diffstat ) && ! empty( $diffstat ) ) : ?>
		<div class="ash-nazg-card ash-nazg-card-full<?php echo $update_count > 0 ? ' ash-nazg-mt-20' : ''; ?>">
			<h2><?php esc_html_e( 'Uncommitted Changes', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'You have uncommitted changes in SFTP mode. Commit them to save your work to the git repository.', 'ash-nazg' ); ?></p>

			<h3><?php esc_html_e( 'Changed Files:', 'ash-nazg' ); ?></h3>
			<?php
			$file_list = array_keys( $diffstat );
			$shown_files = array_slice( $file_list, 0, 10 );
			$hidden_files = array_slice( $file_list, 10 );
			?>
			<ul>
				<?php foreach ( $shown_files as $file ) : ?>
					<li><code><?php echo esc_html( $file ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $hidden_files ) ) : ?>
				<details>
					<summary><?php /* translators: %d: number of hidden files */ ?><strong><?php echo esc_html( sprintf( __( 'Show %d more files', 'ash-nazg' ), count( $hidden_files ) ) ); ?></strong></summary>
					<ul>
						<?php foreach ( $hidden_files as $file ) : ?>
							<li><code><?php echo esc_html( $file ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<form method="post" action="" class="ash-nazg-commit-form ash-nazg-mt-20">
				<?php wp_nonce_field( 'ash_nazg_commit_changes', 'ash_nazg_commit_nonce' ); ?>
				<input type="hidden" name="ash_nazg_action" value="commit_changes" />

				<label for="commit_message">
					<strong><?php esc_html_e( 'Commit Message:', 'ash-nazg' ); ?></strong>
				</label>
				<textarea name="commit_message" id="commit_message" rows="3" class="large-text" required placeholder="<?php esc_attr_e( 'Describe your changes...', 'ash-nazg' ); ?>"></textarea>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Commit Changes', 'ash-nazg' ); ?></button>
				</p>
			</form>
		</div>
		<?php endif; ?>

		<!-- Environment Commits -->
		<div class="ash-nazg-card ash-nazg-card-full<?php echo $update_count > 0 ? ' ash-nazg-mt-20' : ''; ?>">
			<h2><?php esc_html_e( 'Recent Commits', 'ash-nazg' ); ?></h2>
			<?php if ( is_wp_error( $commits ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $commits->get_error_message() ); ?>
					</p>
				</div>
			<?php elseif ( $commits && is_array( $commits ) ) : ?>
				<p>
					<?php
					$total_commits = count( $commits );
					$displayed_count = min( $total_commits, $commits_per_page );
					printf(
						/* translators: %d: number of commits */
						esc_html__( 'Showing the last %d commits:', 'ash-nazg' ),
						absint( $displayed_count )
					);
					?>
				</p>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Hash', 'ash-nazg' ); ?></th>
							<th><?php esc_html_e( 'Author', 'ash-nazg' ); ?></th>
							<th><?php esc_html_e( 'Message', 'ash-nazg' ); ?></th>
							<th><?php esc_html_e( 'Date', 'ash-nazg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $commits, 0, $commits_per_page ) as $commit ) : ?>
							<tr>
								<td>
									<?php
									$full_hash = $commit['hash'] ?? $commit['id'] ?? 'unknown';
									$short_hash = substr( $full_hash, 0, 8 );
									?>
									<?php /* translators: %s: full commit hash */ ?><code class="ash-nazg-hash-copyable" data-hash="<?php echo esc_attr( $full_hash ); ?>" title="<?php echo esc_attr( sprintf( __( 'Click to copy: %s', 'ash-nazg' ), $full_hash ) ); ?>">
										<?php echo esc_html( $short_hash ); ?>
										<span class="dashicons dashicons-clipboard"></span>
									</code>
								</td>
								<td>
									<?php echo esc_html( $commit['author'] ?? $commit['committer_name'] ?? 'Unknown' ); ?>
								</td>
								<td>
									<?php echo esc_html( $commit['message'] ?? $commit['commit_message'] ?? 'No message' ); ?>
								</td>
								<td>
									<?php
									$timestamp = $commit['datetime'] ?? $commit['timestamp'] ?? null;
									if ( $timestamp ) {
										echo esc_html( human_time_diff( is_numeric( $timestamp ) ? $timestamp : strtotime( $timestamp ), time() ) );
										echo ' ' . esc_html__( 'ago', 'ash-nazg' );
									} else {
										esc_html_e( 'Unknown', 'ash-nazg' );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<details class="ash-nazg-mt-20">
					<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
					<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( array_slice( $commits, 0, 5 ), JSON_PRETTY_PRINT ) ); ?></pre>
				</details>
			<?php else : ?>
				<p><?php esc_html_e( 'No commits found.', 'ash-nazg' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Grid container for side-by-side cards -->
		<div class="ash-nazg-dashboard">
			<!-- Environments -->
			<div class="ash-nazg-card">
			<div class="ash-nazg-flex-between ash-nazg-mb-10">
				<h2 class="ash-nazg-m-0"><?php esc_html_e( 'Environments', 'ash-nazg' ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( 'refresh_environments', '1' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Refresh', 'ash-nazg' ); ?>
				</a>
			</div>
			<?php if ( is_wp_error( $environments ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $environments->get_error_message() ); ?>
					</p>
				</div>
			<?php elseif ( $environments && is_array( $environments ) ) : ?>
				<table class="widefat">
					<thead>
						<tr>
							<th class="ash-nazg-th-30"><?php esc_html_e( 'Name', 'ash-nazg' ); ?></th>
							<th class="ash-nazg-th-20"><?php esc_html_e( 'Mode', 'ash-nazg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $environments as $env_id => $env_data ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $env_id ); ?></strong></td>
								<td>
									<?php if ( isset( $env_data['connection_mode'] ) ) : ?>
										<span class="ash-nazg-badge <?php echo 'sftp' === $env_data['connection_mode'] ? 'ash-nazg-badge-sftp' : 'ash-nazg-badge-git'; ?>">
											<?php echo 'sftp' === $env_data['connection_mode'] ? esc_html__( 'SFTP', 'ash-nazg' ) : esc_html__( 'Git', 'ash-nazg' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<details class="ash-nazg-mt-20">
					<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
					<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $environments, JSON_PRETTY_PRINT ) ); ?></pre>
				</details>
			<?php else : ?>
				<p><?php esc_html_e( 'No environments found.', 'ash-nazg' ); ?></p>
			<?php endif; ?>
		</div>


			<!-- Multidev Management -->
			<div class="ash-nazg-card">
			<h2><?php esc_html_e( 'Multidev Management', 'ash-nazg' ); ?></h2>

			<!-- Create Multidev -->
			<div class="ash-nazg-mb-20">
				<h3><?php esc_html_e( 'Create New Multidev', 'ash-nazg' ); ?></h3>
				<form method="post" action="" data-multidev-action="create">
					<?php wp_nonce_field( 'ash_nazg_multidev', 'ash_nazg_multidev_nonce' ); ?>
					<input type="hidden" name="multidev_action" value="create" />

					<p>
						<label for="multidev_name">
							<strong><?php esc_html_e( 'Multidev Name:', 'ash-nazg' ); ?></strong>
						</label><br />
						<input type="text" name="multidev_name" id="multidev_name" class="regular-text" required maxlength="11" placeholder="<?php esc_attr_e( 'e.g., my-feature', 'ash-nazg' ); ?>" />
					<p class="description"><?php esc_html_e( 'Maximum 11 characters. Lowercase letters, numbers, and hyphens only.', 'ash-nazg' ); ?></p>
					</p>

					<div class="ash-nazg-flex-between">
						<div>
							<label for="source_env">
								<strong><?php esc_html_e( 'Clone From:', 'ash-nazg' ); ?></strong>
							</label>
							<select name="source_env" id="source_env">
								<option value="dev"><?php esc_html_e( 'Dev', 'ash-nazg' ); ?></option>
								<?php if ( $environments && is_array( $environments ) ) : ?>
									<?php foreach ( $environments as $env_id => $env_data ) : ?>
										<?php if ( ! in_array( $env_id, [ 'dev', 'test', 'live' ], true ) ) : ?>
											<option value="<?php echo esc_attr( $env_id ); ?>"><?php echo esc_html( ucfirst( $env_id ) ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Multidev', 'ash-nazg' ); ?></button>
					</div>
				</form>
			</div>

			<!-- Existing Multidevs -->
			<?php
			$multidevs = [];
			if ( $environments && is_array( $environments ) ) {
				foreach ( $environments as $env_id => $env_data ) {
					if ( ! in_array( $env_id, [ 'dev', 'test', 'live' ], true ) ) {
						$multidevs[ $env_id ] = $env_data;
					}
				}
			}
			?>

			<?php if ( ! empty( $multidevs ) ) : ?>
				<h3><?php esc_html_e( 'Existing Multidevs', 'ash-nazg' ); ?></h3>
				<table class="widefat">
					<thead>
						<tr>
							<th class="ash-nazg-th-5"><?php esc_html_e( 'Name', 'ash-nazg' ); ?></th>
							<th class="ash-nazg-th-5"><?php esc_html_e( 'Mode', 'ash-nazg' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ash-nazg' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $multidevs as $multidev_id => $multidev_data ) : ?>
							<?php
							// Check if dev has different commits than this multidev.
							$has_dev_changes = Helpers\dev_has_changes_for_env( $site_id, $multidev_id );
							?>
							<tr>
								<td><strong><?php echo esc_html( $multidev_id ); ?></strong></td>
								<td>
									<?php if ( isset( $multidev_data['connection_mode'] ) ) : ?>
										<span class="ash-nazg-badge <?php echo 'sftp' === $multidev_data['connection_mode'] ? 'ash-nazg-badge-sftp' : 'ash-nazg-badge-git'; ?>">
											<?php echo 'sftp' === $multidev_data['connection_mode'] ? esc_html__( 'SFTP', 'ash-nazg' ) : esc_html__( 'Git', 'ash-nazg' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<?php
									// Construct admin URL from environment data.
									if ( $site_info && ! is_wp_error( $site_info ) && isset( $site_info['name'], $multidev_data['dns_zone'] ) ) {
										// Check if using WordPress Composer Managed upstream (wp subdirectory).
										$admin_path = '/wp-admin/';
										if ( isset( $site_info['upstream']['id'] ) && 'wordpress-composer-managed' === $site_info['upstream']['id'] ) {
											$admin_path = '/wp/wp-admin/';
										}

										$admin_url = sprintf(
											'https://%s-%s.%s%s',
											$multidev_id,
											$site_info['name'],
											$multidev_data['dns_zone'],
											$admin_path
										);
										?>
										<a href="<?php echo esc_url( $admin_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Visit Admin', 'ash-nazg' ); ?>
										</a>
										<?php
									}
									?>

									<?php if ( $has_dev_changes ) : ?>
									<button type="button" class="button button-secondary ash-nazg-ml-10 ash-nazg-merge-from-dev-btn" data-multidev-name="<?php echo esc_attr( $multidev_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ash_nazg_merge_dev_to_multidev' ) ); ?>">
										<?php esc_html_e( 'Merge from Dev', 'ash-nazg' ); ?>
									</button>
									<?php endif; ?>

									<form method="post" action="" class="ash-nazg-inline-block ash-nazg-ml-10" data-multidev-action="merge">
										<?php wp_nonce_field( 'ash_nazg_multidev', 'ash_nazg_multidev_nonce' ); ?>
										<input type="hidden" name="multidev_action" value="merge" />
										<input type="hidden" name="multidev_name" value="<?php echo esc_attr( $multidev_id ); ?>" />
										<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Merge this multidev into dev?', 'ash-nazg' ); ?>');">
											<?php esc_html_e( 'Merge to Dev', 'ash-nazg' ); ?>
										</button>
									</form>

									<form method="post" action="" class="ash-nazg-inline-block ash-nazg-ml-10" data-multidev-action="delete">
										<?php wp_nonce_field( 'ash_nazg_multidev', 'ash_nazg_multidev_nonce' ); ?>
										<input type="hidden" name="multidev_action" value="delete" />
										<input type="hidden" name="multidev_name" value="<?php echo esc_attr( $multidev_id ); ?>" />
										<button type="submit" class="button" onclick="return confirm('<?php esc_attr_e( 'Delete this multidev? This action cannot be undone.', 'ash-nazg' ); ?>');">
											<?php esc_html_e( 'Delete', 'ash-nazg' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No multidev environments found.', 'ash-nazg' ); ?></p>
			<?php endif; ?>
		</div>
		</div><!-- .ash-nazg-dashboard -->

	<?php endif; ?>
</div>
