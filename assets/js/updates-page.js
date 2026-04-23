/**
 * Pantheon upstream updates integration for the WordPress Updates page.
 */

(function($) {
	'use strict';

	if (typeof ashNazgUpdatesPage === 'undefined') {
		return;
	}

	/**
	 * Show a progress modal with an animated progress bar.
	 * Matches showProgressModal() from development.js exactly.
	 */
	function showProgressModal(title, message) {
		var modal   = $('<div class="ash-nazg-progress-modal"></div>');
		var overlay = $('<div class="ash-nazg-progress-overlay"></div>');

		var content = $('<div class="ash-nazg-progress-content"></div>');
		content.append('<h2>' + title + '</h2>');
		content.append('<p class="ash-nazg-progress-message">' + message + '</p>');
		content.append('<div class="ash-nazg-progress-bar"><div class="ash-nazg-progress-fill"></div></div>');
		content.append('<p class="ash-nazg-progress-percent">0%</p>');
		content.append('<p class="ash-nazg-progress-status"></p>');

		modal.append(content);
		$('body').append(overlay).append(modal);

		return {
			updateProgress: function(percent, status) {
				modal.find('.ash-nazg-progress-fill').css('width', percent + '%');
				modal.find('.ash-nazg-progress-percent').text(percent + '%');
				if (status) {
					modal.find('.ash-nazg-progress-status').text(status);
				}
			},
			close: function() {
				modal.remove();
				overlay.remove();
			}
		};
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

			window.AshNazgModal.confirm({
				message: ashNazgUpdatesPage.i18n.confirmApply,
				type: 'warning',
				onConfirm: function() {
					executeApply($btn, originalText);
				}
			});
		});

		function executeApply($btn, originalText) {
			$btn.prop('disabled', true);

			var progressModal = showProgressModal(
				ashNazgUpdatesPage.i18n.applying,
				ashNazgUpdatesPage.i18n.pleaseWait
			);

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
						pollWorkflow(response.data.workflow_id, response.data.site_id, $btn, originalText, progressModal);
					} else {
						progressModal.close();
						$btn.prop('disabled', false);
						window.AshNazgModal.alert({
							message: (response && response.data && response.data.message) || ashNazgUpdatesPage.i18n.failed,
							type: 'danger'
						});
					}
				},
				error: function(xhr) {
					progressModal.close();
					$btn.prop('disabled', false);
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

		function pollWorkflow(workflowId, siteId, $btn, originalText, progressModal) {
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
							progressModal.close();
							$btn.prop('disabled', false);
							return;
						}

						var status = response.data;
						var progress = 0;
						if (status.step && status.operations && status.operations.length > 0) {
							progress = Math.round((status.step / status.operations.length) * 100);
						}
						if (progress > 0) {
							progressModal.updateProgress(progress, status.active_description || '');
						}

						if (status.result === 'succeeded') {
							progressModal.updateProgress(100, '');
							clearCacheAndComplete($btn, originalText, progressModal);
						} else if (status.result === 'failed') {
							progressModal.close();
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
								progressModal.close();
								$btn.prop('disabled', false);
								window.AshNazgModal.alert({
									message: ashNazgUpdatesPage.i18n.timeout,
									type: 'warning'
								});
							}
						}
					},
					error: function() {
						progressModal.close();
						$btn.prop('disabled', false);
					}
				});
			}

			checkStatus();
		}

		function clearCacheAndComplete($btn, originalText, progressModal) {
			$.ajax({
				url: ashNazgUpdatesPage.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ash_nazg_clear_upstream_cache',
					nonce: ashNazgUpdatesPage.clearCacheNonce
				},
				complete: function() {
					progressModal.close();
					$btn.prop('disabled', false);
					window.AshNazgModal.alert({
						message: ashNazgUpdatesPage.i18n.applied,
						type: 'info',
						onClose: function() { window.location.reload(); }
					});
				}
			});
		}
	});
})(jQuery);
