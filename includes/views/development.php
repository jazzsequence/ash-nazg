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

		<!-- Environment Commits -->
		<div class="ash-nazg-card ash-nazg-card-full">
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
					printf(
						/* translators: 1: number of commits, 2: environment name */
						esc_html__( 'Showing %1$d commits for %2$s environment:', 'ash-nazg' ),
						count( $commits ),
						'<code>' . esc_html( $environment ) . '</code>'
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
						<?php foreach ( array_slice( $commits, 0, 50 ) as $commit ) : ?>
							<tr>
								<td>
									<code><?php echo esc_html( substr( $commit['hash'] ?? $commit['id'] ?? 'unknown', 0, 8 ) ); ?></code>
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

		<!-- Upstream Updates -->
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mt-20">
			<h2><?php esc_html_e( 'Upstream Updates', 'ash-nazg' ); ?></h2>
			<?php if ( is_wp_error( $upstream_updates ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $upstream_updates->get_error_message() ); ?>
					</p>
				</div>
			<?php elseif ( $upstream_updates && is_array( $upstream_updates ) && count( $upstream_updates ) > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: number of upstream updates */
						esc_html__( '%d upstream update(s) available:', 'ash-nazg' ),
						count( $upstream_updates )
					);
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
						<?php foreach ( $upstream_updates as $update ) : ?>
							<tr>
								<td>
									<code><?php echo esc_html( substr( $update['hash'] ?? $update['id'] ?? 'unknown', 0, 8 ) ); ?></code>
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
			<?php else : ?>
				<p><?php esc_html_e( 'No upstream updates available. Your site is up to date!', 'ash-nazg' ); ?></p>

				<?php if ( $upstream_updates ) : ?>
					<details class="ash-nazg-mt-20">
						<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
						<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $upstream_updates, JSON_PRETTY_PRINT ) ); ?></pre>
					</details>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<!-- Code Tips (Branches) -->
		<div class="ash-nazg-card ash-nazg-card-full ash-nazg-mt-20">
			<h2><?php esc_html_e( 'Git Branches', 'ash-nazg' ); ?></h2>
			<?php if ( is_wp_error( $code_tips ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Error:', 'ash-nazg' ); ?></strong>
						<?php echo esc_html( $code_tips->get_error_message() ); ?>
					</p>
				</div>
			<?php elseif ( $code_tips && is_array( $code_tips ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: number of branches */
						esc_html__( '%d branch(es) found:', 'ash-nazg' ),
						count( $code_tips )
					);
					?>
				</p>
				<details class="ash-nazg-mt-20">
					<summary><strong><?php esc_html_e( 'Raw API Response (Debug)', 'ash-nazg' ); ?></strong></summary>
					<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 400px;"><?php echo esc_html( wp_json_encode( $code_tips, JSON_PRETTY_PRINT ) ); ?></pre>
				</details>
			<?php else : ?>
				<p><?php esc_html_e( 'No branch data found.', 'ash-nazg' ); ?></p>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>
