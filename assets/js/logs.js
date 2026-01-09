/**
 * Logs page JavaScript for Ash-Nazg plugin.
 *
 * @package Pantheon\AshNazg
 */

jQuery(document).ready(function($) {
	/**
	 * Handle fetch logs button click.
	 */
	$('#ash-nazg-fetch-logs').on('click', function() {
		var $button = $(this);
		var $clearButton = $('#ash-nazg-clear-logs');
		var $loading = $('#ash-nazg-logs-loading');
		var $loadingMessage = $('#ash-nazg-logs-loading-message');
		var $container = $('#ash-nazg-logs-container');

		// Disable buttons and show loading.
		$button.prop('disabled', true);
		$clearButton.prop('disabled', true);
		$loadingMessage.text(ashNazgLogs.i18n.fetchingLogs);
		$loading.show();

		$.ajax({
			url: ashNazgLogs.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_fetch_logs',
				nonce: ashNazgLogs.fetchLogsNonce
			},
			success: function(response) {
				$loading.hide();
				$button.prop('disabled', false);
				$clearButton.prop('disabled', false);

				if (response.success) {
					var logs = response.data.logs;
					var timestamp = response.data.timestamp;
					var switchedMode = response.data.switched_mode;

					// Update container with new logs.
					if (logs) {
						var html = '<h3>' + ashNazgLogs.i18n.logContents + '</h3>';
						html += '<div style="background: #f5f5f5; border: 1px solid #ddd; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto;">';
						html += '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 12px;">' + $('<div>').text(logs).html() + '</pre>';
						html += '</div>';
						$container.html(html);
					} else {
						$container.html('<p style="color: #666;"><em>' + ashNazgLogs.i18n.emptyLog + '</em></p>');
					}

					// Update timestamp display.
					$('#ash-nazg-logs-timestamp').show();
					$('#ash-nazg-logs-timestamp-value').text(ashNazgLogs.i18n.justNow);

					// Show success message.
					var message = switchedMode ? ashNazgLogs.i18n.successWithSwitch : ashNazgLogs.i18n.success;
					$('.wrap h1').after('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : ashNazgLogs.i18n.fetchError;
					$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
				}
			},
			error: function() {
				$loading.hide();
				$button.prop('disabled', false);
				$clearButton.prop('disabled', false);
				$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p>' + ashNazgLogs.i18n.ajaxError + '</p></div>');
			}
		});
	});

	/**
	 * Handle clear logs button click.
	 */
	$('#ash-nazg-clear-logs').on('click', function() {
		var $button = $(this);
		var $fetchButton = $('#ash-nazg-fetch-logs');
		var $loading = $('#ash-nazg-logs-loading');
		var $loadingMessage = $('#ash-nazg-logs-loading-message');
		var $container = $('#ash-nazg-logs-container');

		// Disable buttons and show loading.
		$button.prop('disabled', true);
		$fetchButton.prop('disabled', true);
		$loadingMessage.text(ashNazgLogs.i18n.clearingLogs);
		$loading.show();

		$.ajax({
			url: ashNazgLogs.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_clear_logs',
				nonce: ashNazgLogs.clearLogsNonce
			},
			success: function(response) {
				$loading.hide();
				$button.prop('disabled', false);
				$fetchButton.prop('disabled', false);

				if (response.success) {
					var switchedMode = response.data.switched_mode;

					// Clear the container.
					$container.html('<p style="color: #666;"><em>' + ashNazgLogs.i18n.emptyLog + '</em></p>');

					// Hide timestamp display.
					$('#ash-nazg-logs-timestamp').hide();

					// Show success message.
					var message = switchedMode ? ashNazgLogs.i18n.clearSuccessSwitch : ashNazgLogs.i18n.clearSuccess;
					$('.wrap h1').after('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : ashNazgLogs.i18n.clearError;
					$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
				}
			},
			error: function() {
				$loading.hide();
				$button.prop('disabled', false);
				$fetchButton.prop('disabled', false);
				$('.wrap h1').after('<div class="notice notice-error is-dismissible"><p>' + ashNazgLogs.i18n.clearAjaxError + '</p></div>');
			}
		});
	});
});
