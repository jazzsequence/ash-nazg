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

<script type="text/javascript">
jQuery(document).ready(function($) {
	$('#ash-nazg-fetch-logs').on('click', function() {
		var $button = $(this);
		var $loading = $('#ash-nazg-logs-loading');
		var $container = $('#ash-nazg-logs-container');

		// Disable button and show loading.
		$button.prop('disabled', true);
		$loading.show();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ash_nazg_fetch_logs',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ash_nazg_fetch_logs' ) ); ?>'
			},
			success: function(response) {
				$loading.hide();
				$button.prop('disabled', false);

				if (response.success) {
					var logs = response.data.logs;
					var timestamp = response.data.timestamp;
					var switchedMode = response.data.switched_mode;

					// Update container with new logs.
					if (logs) {
						var html = '<h3><?php echo esc_js( __( 'Log Contents', 'ash-nazg' ) ); ?></h3>';
						html += '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto;">';
						html += '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 12px;">' + $('<div>').text(logs).html() + '</pre>';
						html += '</div>';
						$container.html(html);
					} else {
						$container.html('<p style="color: #666;"><em><?php echo esc_js( __( 'Debug log is empty or does not exist yet.', 'ash-nazg' ) ); ?></em></p>');
					}

					// Update timestamp display.
					$('#ash-nazg-logs-timestamp').show();
					$('#ash-nazg-logs-timestamp-value').text('<?php echo esc_js( __( 'just now', 'ash-nazg' ) ); ?>');

					// Show success message.
					var message = switchedMode ?
						'<?php echo esc_js( __( 'Logs fetched successfully. Connection mode was temporarily switched to SFTP.', 'ash-nazg' ) ); ?>' :
						'<?php echo esc_js( __( 'Logs fetched successfully.', 'ash-nazg' ) ); ?>';

					$('.wrap h1').after('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to fetch logs.', 'ash-nazg' ) ); ?>';
					$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
				}
			},
			error: function() {
				$loading.hide();
				$button.prop('disabled', false);
				$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p><?php echo esc_js( __( 'An error occurred while fetching logs.', 'ash-nazg' ) ); ?></p></div>');
			}
		});
	});
});
</script>
