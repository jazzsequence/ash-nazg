/**
 * Dashboard JavaScript for Ash-Nazg plugin.
 *
 * @package Pantheon\AshNazg
 */

jQuery(document).ready(function($) {
	/**
	 * Handle connection mode toggle.
	 */
	$('#ash-nazg-toggle-mode').on('click', function() {
		var $button = $(this);
		var $toggle = $('#ash-nazg-connection-mode-toggle');
		var $loading = $('#ash-nazg-mode-loading');
		var newMode = $button.data('mode');

		// Disable button and show loading.
		$button.prop('disabled', true);
		$loading.show();

		$.ajax({
			url: ashNazgDashboard.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_toggle_connection_mode',
				mode: newMode,
				nonce: ashNazgDashboard.toggleModeNonce
			},
			success: function(response) {
				if (response.success) {
					// Reload the page to show updated state.
					location.reload();
				} else {
					$loading.hide();
					$button.prop('disabled', false);
					var errorMsg = response.data && response.data.message ? response.data.message : ashNazgDashboard.i18n.toggleError;
					$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
				}
			},
			error: function() {
				$loading.hide();
				$button.prop('disabled', false);
				$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + ashNazgDashboard.i18n.ajaxError + '</p></div>');
			}
		});
	});
});
