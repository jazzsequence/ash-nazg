/**
 * Pantheon upstream updates integration for the WordPress Updates page.
 */

(function($) {
	'use strict';

	if (typeof ashNazgUpdatesPage === 'undefined') {
		return;
	}

	$(document).ready(function() {
		// Move our notice to sit directly after the "Last checked on..." paragraph.
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
			var originalText = $btn.text();

			// Step 1: ask for confirmation, matching the Development page flow.
			window.AshNazgModal.confirm({
				message: ashNazgUpdatesPage.i18n.confirmApply,
				type: 'warning',
				confirmText: ashNazgUpdatesPage.i18n.confirmText,
				onConfirm: function() {
					executeApply($btn, originalText);
				}
			});
		});

		function executeApply($btn, originalText) {
			$btn.prop('disabled', true).text(ashNazgUpdatesPage.i18n.applying);

			$.ajax({
				url: ashNazgUpdatesPage.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ash_nazg_apply_upstream_updates',
					nonce: ashNazgUpdatesPage.applyNonce,
					updatedb: false,
					xoption: false
				},
				success: function(response) {
					if (response && response.success && response.data && response.data.workflow_id) {
						pollWorkflow(response.data.workflow_id, response.data.site_id, $btn, originalText);
					} else {
						$btn.prop('disabled', false).text(originalText);
						window.AshNazgModal.alert({
							message: (response && response.data && response.data.message) || ashNazgUpdatesPage.i18n.failed,
							type: 'danger'
						});
					}
				},
				error: function(xhr) {
					$btn.prop('disabled', false).text(originalText);
					var msg = ashNazgUpdatesPage.i18n.ajaxError;
					try {
						var parsed = JSON.parse(xhr.responseText);
						if (parsed && parsed.data && parsed.data.message) {
							msg = parsed.data.message;
						}
					} catch (e) {}
					window.AshNazgModal.alert({ message: msg, type: 'danger' });
				}
			});
		}

		function pollWorkflow(workflowId, siteId, $btn, originalText) {
			var attempts    = 0;
			var maxAttempts = 60;

			function checkStatus() {
				$.ajax({
					url: ashNazgUpdatesPage.ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'ash_nazg_get_workflow_status',
						nonce: ashNazgUpdatesPage.workflowNonce,
						site_id: siteId,
						workflow_id: workflowId
					},
					success: function(response) {
						if (!response || !response.success || !response.data) {
							$btn.prop('disabled', false).text(originalText);
							return;
						}

						var status = response.data;

						if (status.result === 'succeeded') {
							$btn.prop('disabled', false).text(originalText);
							window.AshNazgModal.alert({
								message: ashNazgUpdatesPage.i18n.applied,
								type: 'info',
								onClose: function() { window.location.reload(); }
							});
						} else if (status.result === 'failed') {
							$btn.prop('disabled', false).text(originalText);
							window.AshNazgModal.alert({
								message: status.error || ashNazgUpdatesPage.i18n.failed,
								type: 'danger'
							});
						} else {
							attempts++;
							if (attempts < maxAttempts) {
								setTimeout(checkStatus, 5000);
							} else {
								$btn.prop('disabled', false).text(originalText);
								window.AshNazgModal.alert({
									message: ashNazgUpdatesPage.i18n.timeout,
									type: 'warning'
								});
							}
						}
					},
					error: function() {
						$btn.prop('disabled', false).text(originalText);
					}
				});
			}

			checkStatus();
		}
	});
})(jQuery);
