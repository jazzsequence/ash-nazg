<?php
/**
 * Logs page template.
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

	<?php if ( $message ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ash-nazg-dashboard">
		<div class="ash-nazg-card ash-nazg-card-full">
			<h2><?php esc_html_e( 'WordPress Debug Logs', 'ash-nazg' ); ?></h2>
			<p>
				<?php esc_html_e( 'View the contents of your WordPress debug.log file. If the site is in Git mode, it will temporarily switch to SFTP mode to read the file, then switch back.', 'ash-nazg' ); ?>
			</p>

			<p id="ash-nazg-logs-timestamp" <?php echo $logs_fetched_at ? '' : 'style="display: none;"'; ?>>
				<strong><?php esc_html_e( 'Last fetched:', 'ash-nazg' ); ?></strong>
				<span id="ash-nazg-logs-timestamp-value">
					<?php
					if ( $logs_fetched_at ) {
						echo esc_html( human_time_diff( $logs_fetched_at ) ) . ' ' . esc_html__( 'ago', 'ash-nazg' );
					}
					?>
				</span>
			</p>

			<button type="button" id="ash-nazg-fetch-logs" class="button button-primary">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Fetch Logs', 'ash-nazg' ); ?>
			</button>

			<button type="button" id="ash-nazg-clear-logs" class="button button-secondary ash-nazg-clear-button">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear Log', 'ash-nazg' ); ?>
			</button>

			<div id="ash-nazg-logs-loading">
				<p>
					<span class="spinner is-active"></span>
					<em id="ash-nazg-logs-loading-message"><?php esc_html_e( 'Fetching logs... This may take a moment if we need to switch connection modes.', 'ash-nazg' ); ?></em>
				</p>
			</div>

			<div id="ash-nazg-logs-container">
				<?php if ( $logs ) : ?>
					<h3><?php esc_html_e( 'Log Contents', 'ash-nazg' ); ?></h3>
					<div class="ash-nazg-log-contents">
						<pre><?php echo esc_html( $logs ); ?></pre>
					</div>
				<?php elseif ( false !== $logs ) : ?>
					<p class="ash-nazg-log-empty">
						<em><?php esc_html_e( 'Debug log is empty or does not exist yet.', 'ash-nazg' ); ?></em>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
