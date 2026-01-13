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
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'Git commits, upstream updates, and code management.', 'ash-nazg' ); ?></p>

	<?php if ( ! $site_id ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Not running on Pantheon. Git features are not available.', 'ash-nazg' ); ?></p>
		</div>
	<?php else : ?>

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
						$behind
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
							<code class="ash-nazg-hash-copyable" title="<?php echo esc_attr( sprintf( __( 'Click to copy: %s', 'ash-nazg' ), $full_hash ) ); ?>">
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

			<details class="ash-nazg-mt-20">
				<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
				<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $upstream_updates, JSON_PRETTY_PRINT ) ); ?></pre>
			</details>
		</div>
		<?php endif; ?>

		<!-- Uncommitted SFTP Changes -->
		<?php if ( 'sftp' === $connection_mode && $diffstat && ! is_wp_error( $diffstat ) && ! empty( $diffstat ) ) : ?>
		<div class="ash-nazg-card ash-nazg-card-full<?php echo $update_count > 0 ? ' ash-nazg-mt-20' : ''; ?>">
			<h2><?php esc_html_e( 'Uncommitted Changes', 'ash-nazg' ); ?></h2>
			<p><?php esc_html_e( 'You have uncommitted changes in SFTP mode. Commit them to save your work to the git repository.', 'ash-nazg' ); ?></p>

			<h3><?php esc_html_e( 'Changed Files:', 'ash-nazg' ); ?></h3>
			<ul>
				<?php foreach ( $diffstat as $file => $changes ) : ?>
					<li><code><?php echo esc_html( $file ); ?></code></li>
				<?php endforeach; ?>
			</ul>

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
						$displayed_count
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
									<code class="ash-nazg-hash-copyable" title="<?php echo esc_attr( sprintf( __( 'Click to copy: %s', 'ash-nazg' ), $full_hash ) ); ?>">
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

		<!-- Environments -->
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mt-20">
			<h2><?php esc_html_e( 'Environments', 'ash-nazg' ); ?></h2>
			<?php if ( is_wp_error( $environments ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $environments->get_error_message() ); ?>
					</p>
				</div>
			<?php elseif ( $environments && is_array( $environments ) ) : ?>
				<p>
					<?php
					$env_count = count( $environments );
					echo esc_html(
						sprintf(
							/* translators: %d: number of environments */
							_n( '%d environment found:', '%d environments found:', $env_count, 'ash-nazg' ),
							$env_count
						)
					);
					?>
				</p>
				<ul>
					<?php foreach ( $environments as $env_id => $env_data ) : ?>
						<li>
							<strong><?php echo esc_html( $env_id ); ?></strong>
							<?php if ( isset( $env_data['on_server_development'] ) ) : ?>
								<span class="ash-nazg-badge <?php echo $env_data['on_server_development'] ? 'ash-nazg-badge-sftp' : 'ash-nazg-badge-git'; ?>">
									<?php echo $env_data['on_server_development'] ? esc_html__( 'SFTP', 'ash-nazg' ) : esc_html__( 'Git', 'ash-nazg' ); ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<details class="ash-nazg-mt-20">
					<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
					<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $environments, JSON_PRETTY_PRINT ) ); ?></pre>
				</details>
			<?php else : ?>
				<p><?php esc_html_e( 'No environments found.', 'ash-nazg' ); ?></p>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>
