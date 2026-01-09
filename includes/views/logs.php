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
		<div class="ash-nazg-card">
			<h2><?php esc_html_e( 'WordPress Debug Logs', 'ash-nazg' ); ?></h2>
			<p>
				<?php esc_html_e( 'View the contents of your WordPress debug.log file. If the site is in Git mode, it will temporarily switch to SFTP mode to read the file, then switch back.', 'ash-nazg' ); ?>
			</p>

			<p id="ash-nazg-logs-timestamp" style="margin-bottom: 15px; <?php echo $logs_fetched_at ? '' : 'display: none;'; ?>">
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
				<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Fetch Logs', 'ash-nazg' ); ?>
			</button>

			<div id="ash-nazg-logs-loading" style="display: none; margin-top: 20px;">
				<p>
					<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
					<em><?php esc_html_e( 'Fetching logs... This may take a moment if we need to switch connection modes.', 'ash-nazg' ); ?></em>
				</p>
			</div>

			<div id="ash-nazg-logs-container" style="margin-top: 20px;">
				<?php if ( $logs ) : ?>
					<h3><?php esc_html_e( 'Log Contents', 'ash-nazg' ); ?></h3>
					<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto;">
						<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 12px;"><?php echo esc_html( $logs ); ?></pre>
					</div>
				<?php elseif ( false !== $logs ) : ?>
					<p style="color: #666;">
						<em><?php esc_html_e( 'Debug log is empty or does not exist yet.', 'ash-nazg' ); ?></em>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
