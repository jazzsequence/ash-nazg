/**
 * Development page functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Click to copy commit hash
		$('.ash-nazg-hash-copyable').on('click', function(e) {
			e.preventDefault();
			
			var fullHash = $(this).attr('title');
			var $code = $(this);
			
			// Copy to clipboard
			navigator.clipboard.writeText(fullHash).then(function() {
				// Show success feedback
				var originalText = $code.text();
				$code.text('Copied!');
				$code.addClass('ash-nazg-hash-copied');
				
				// Reset after 1.5 seconds
				setTimeout(function() {
					$code.text(originalText);
					$code.removeClass('ash-nazg-hash-copied');
				}, 1500);
			}).catch(function(err) {
				console.error('Failed to copy hash:', err);
			});
		});
		
		// Change cursor to pointer to indicate clickability
		$('.ash-nazg-hash-copyable').css('cursor', 'pointer');

		// Apply Upstream Updates button
		$('#ash-nazg-apply-upstream-updates').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var nonce = $button.data('nonce');

			// Confirm action
			if (!confirm(ashNazgDevelopment.i18n.confirmApplyUpdates)) {
				return;
			}

			// Disable button
			$button.prop('disabled', true);

			// Show progress modal
			var modal = showProgressModal(
				ashNazgDevelopment.i18n.applyingUpdates,
				ashNazgDevelopment.i18n.pleaseWait
			);

			// Submit via AJAX
			$.ajax({
				url: ashNazgDevelopment.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_apply_upstream_updates',
					nonce: nonce,
					updatedb: false,
					xoption: false
				},
				success: function(response) {
					if (response.success && response.data && response.data.workflow_id) {
						// Poll workflow status
						pollWorkflowStatus(
							response.data.site_id,
							response.data.workflow_id,
							function(progress, status) {
								modal.updateProgress(progress, status);
							},
							function(status) {
								modal.close();
								$button.prop('disabled', false);

								if (status.result === 'succeeded') {
									alert(ashNazgDevelopment.i18n.updatesApplied);
									// Reload page to show updated state
									window.location.reload();
								} else {
									alert(status.error || ashNazgDevelopment.i18n.operationFailed);
								}
							}
						);
					} else {
						modal.close();
						$button.prop('disabled', false);
						alert(response.data?.message || ashNazgDevelopment.i18n.operationFailed);
					}
				},
				error: function() {
					modal.close();
					$button.prop('disabled', false);
					alert(ashNazgDevelopment.i18n.ajaxError);
				}
			});
		});
	});

	/**
	 * Poll workflow status until completion.
	 */
	function pollWorkflowStatus(siteId, workflowId, progressCallback, completeCallback) {
		const pollInterval = 2000; // 2 seconds
		const maxAttempts = 60; // 2 minutes total
		let attempts = 0;

		function checkStatus() {
			$.ajax({
				url: ashNazgDevelopment.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_get_workflow_status',
					nonce: ashNazgDevelopment.workflowStatusNonce,
					site_id: siteId,
					workflow_id: workflowId
				},
				success: function(response) {
					if (response.success && response.data) {
						const status = response.data;

						// Calculate progress
						let progress = 0;
						if (status.step && status.operations && status.operations.length > 0) {
							progress = Math.round((status.step / status.operations.length) * 100);
						}

						// Update progress if callback provided
						if (progressCallback && progress > 0) {
							progressCallback(progress, status.active_description || '');
						}

						// Check if workflow is complete
						if (status.result === 'succeeded' || status.result === 'failed') {
							completeCallback(status);
							return;
						}

						// Continue polling if not complete
						attempts++;
						if (attempts < maxAttempts || progress < 100) {
							setTimeout(checkStatus, pollInterval);
						} else {
							completeCallback({
								result: 'failed',
								error: ashNazgDevelopment.i18n.timeoutError
							});
						}
					} else {
						completeCallback({
							result: 'failed',
							error: response.data?.message || ashNazgDevelopment.i18n.statusError
						});
					}
				},
				error: function() {
					completeCallback({
						result: 'failed',
						error: ashNazgDevelopment.i18n.ajaxError
					});
				}
			});
		}

		checkStatus();
	}

	/**
	 * Show progress modal.
	 */
	function showProgressModal(title, message) {
		const modal = $('<div class="ash-nazg-progress-modal"></div>');
		const overlay = $('<div class="ash-nazg-progress-overlay"></div>');

		const content = $('<div class="ash-nazg-progress-content"></div>');
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
})(jQuery);
