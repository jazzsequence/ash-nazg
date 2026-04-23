/**
 * Pantheon upstream updates integration for the WordPress Updates page.
 *
 * Uses native browser dialogs intentionally — no plugin CSS is loaded on
 * WP core admin pages, so AshNazgModal cannot be used here.
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

			// Native confirm — no plugin CSS needed on WP core pages.
			if (!window.confirm(ashNazgUpdatesPage.i18n.confirmApply)) {
				return;
			}

			var $btn = $(this);
			var originalText = $btn.text();
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
						window.alert((response && response.data && response.data.message) || ashNazgUpdatesPage.i18n.failed);
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
					window.alert(msg);
				}
			});
		});

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
							clearCacheAndReload($btn, originalText);
						} else if (status.result === 'failed') {
							$btn.prop('disabled', false).text(originalText);
							window.alert(status.error || ashNazgUpdatesPage.i18n.failed);
						} else {
							attempts++;
							if (attempts < maxAttempts) {
								setTimeout(checkStatus, 5000);
							} else {
								$btn.prop('disabled', false).text(originalText);
								window.alert(ashNazgUpdatesPage.i18n.timeout);
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

		function clearCacheAndReload($btn, originalText) {
			$.ajax({
				url: ashNazgUpdatesPage.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ash_nazg_clear_upstream_cache',
					nonce: ashNazgUpdatesPage.clearCacheNonce
				},
				complete: function() {
					$btn.prop('disabled', false).text(originalText);
					window.alert(ashNazgUpdatesPage.i18n.applied);
					window.location.reload();
				}
			});
		}
	});
})(jQuery);
