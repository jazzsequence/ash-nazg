/**
 * Pantheon upstream updates integration for the WordPress Updates page.
 */

(function($) {
	'use strict';

	// Bail early if localization data is missing — prevents silent failures
	// when the script loads on unexpected pages.
	if (typeof ashNazgUpdatesPage === 'undefined') {
		return;
	}

	$(document).ready(function() {
		// Move our notice to sit directly after the "Last checked on..." paragraph,
		// which is the natural position between the version info and the update sections.
		var $notice = $('#ash-nazg-upstream-notice');
		if ($notice.length) {
			$notice.detach();
			var $lastChecked = $('p.update-last-checked');
			if ($lastChecked.length) {
				$lastChecked.after($notice);
			} else {
				var $version = $('h2.wp-current-version');
				if ($version.length) {
					$version.after($notice);
				} else {
					var $firstH2 = $('div.wrap h2').first();
					if ($firstH2.length) {
						$firstH2.before($notice);
					} else {
						$('div.wrap').append($notice);
					}
				}
			}
		}

		$('#ash-nazg-apply-upstream-updates-core').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true);

			// Show progress modal immediately so the user has feedback.
			window.AshNazgModal.alert({
				title: ashNazgUpdatesPage.i18n.applying,
				message: ashNazgUpdatesPage.i18n.pleaseWait,
				type: 'info'
			});
			// Hide the OK button — modal should not be dismissable while in progress.
			$('#ash-nazg-modal-confirm').prop('disabled', true).hide();

			$.ajax({
				url: ashNazgUpdatesPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_apply_upstream_updates',
					nonce: ashNazgUpdatesPage.applyNonce,
					updatedb: false,
					xoption: false
				},
				success: function(response) {
					if (response.success && response.data && response.data.workflow_id) {
						pollWorkflow(response.data.workflow_id, response.data.site_id, $btn);
					} else {
						window.AshNazgModal.close();
						$btn.prop('disabled', false);
						window.AshNazgModal.alert({
							message: (response.data && response.data.message) || ashNazgUpdatesPage.i18n.failed,
							type: 'danger'
						});
					}
				},
				error: function() {
					window.AshNazgModal.close();
					$btn.prop('disabled', false);
					window.AshNazgModal.alert({
						message: ashNazgUpdatesPage.i18n.ajaxError,
						type: 'danger'
					});
				}
			});
		});

		function pollWorkflow(workflowId, siteId, $btn) {
			var attempts    = 0;
			var maxAttempts = 60;

			function checkStatus() {
				$.ajax({
					url: ashNazgUpdatesPage.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ash_nazg_get_workflow_status',
						nonce: ashNazgUpdatesPage.workflowNonce,
						site_id: siteId,
						workflow_id: workflowId
					},
					success: function(response) {
						if (!response.success || !response.data) {
							window.AshNazgModal.close();
							$btn.prop('disabled', false);
							return;
						}

						var status = response.data;

						if (status.result === 'succeeded') {
							window.AshNazgModal.close();
							window.AshNazgModal.alert({
								message: ashNazgUpdatesPage.i18n.applied,
								type: 'info',
								onClose: function() { window.location.reload(); }
							});
						} else if (status.result === 'failed') {
							window.AshNazgModal.close();
							$btn.prop('disabled', false);
							window.AshNazgModal.alert({
								message: status.error || ashNazgUpdatesPage.i18n.failed,
								type: 'danger'
							});
						} else {
							attempts++;
							if (attempts < maxAttempts) {
								setTimeout(checkStatus, 5000);
							} else {
								window.AshNazgModal.close();
								$btn.prop('disabled', false);
								window.AshNazgModal.alert({
									message: ashNazgUpdatesPage.i18n.timeout,
									type: 'warning'
								});
							}
						}
					},
					error: function() {
						window.AshNazgModal.close();
						$btn.prop('disabled', false);
					}
				});
			}

			checkStatus();
		}
	});
})(jQuery);
