/**
 * Development page functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Click to copy commit hash
		$('.ash-nazg-hash-copyable').on('click', function(e) {
			e.preventDefault();

			var fullHash = $(this).data('hash');
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
			window.AshNazgModal.confirm(
				ashNazgDevelopment.i18n.confirmApplyUpdates,
				function() {
					// User confirmed - execute upstream updates
					executeApplyUpstreamUpdates($button, nonce);
				},
				'warning'
			);
		});

		// Merge Dev to Multidev button
		$('#ash-nazg-merge-dev-to-multidev').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var nonce = $button.data('nonce');

			// Confirm action
			window.AshNazgModal.confirm(
				ashNazgDevelopment.i18n.confirmMergeDevToMultidev,
				function() {
					// User confirmed - execute merge
					executeMergeDevToMultidev($button, nonce);
				},
				'warning'
			);
		});

		// Toggle Deploy to Test panel
		$('#ash-nazg-deploy-to-test-toggle').on('click', function(e) {
			e.preventDefault();
			$('#ash-nazg-deploy-to-test-panel').slideToggle(200);
		});

		// Cancel Deploy to Test
		$('#ash-nazg-deploy-to-test-cancel').on('click', function(e) {
			e.preventDefault();
			$('#ash-nazg-deploy-to-test-panel').slideUp(200);
			$('#ash-nazg-deploy-note-test').val('');
		});

		// Deploy to Test button
		$('#ash-nazg-deploy-to-test').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var nonce = $button.data('nonce');
			var target = $button.data('target');

			// Get user's deployment note
			var userNote = $('#ash-nazg-deploy-note-test').val().trim();

			// Build full note with context
			var siteUrl = window.location.origin;
			var fullNote = userNote ? userNote + ' ' : '';
			fullNote += '(Triggered by Ash Nazg at ' + siteUrl + ')';

			// Confirm action
			window.AshNazgModal.confirm(
				ashNazgDevelopment.i18n.confirmDeployToTest,
				function() {
					// User confirmed - execute deploy to test
					executeDeployToTest($button, nonce, target, fullNote);
				},
				'warning'
			);
		});

		// Toggle Deploy to Live panel
		$('#ash-nazg-deploy-to-live-toggle').on('click', function(e) {
			e.preventDefault();
			$('#ash-nazg-deploy-to-live-panel').slideToggle(200);
		});

		// Cancel Deploy to Live
		$('#ash-nazg-deploy-to-live-cancel').on('click', function(e) {
			e.preventDefault();
			$('#ash-nazg-deploy-to-live-panel').slideUp(200);
			$('#ash-nazg-deploy-note-live').val('');
			$('#ash-nazg-sync-content').prop('checked', false);
		});

		// Deploy to Live button
		$('#ash-nazg-deploy-to-live').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var nonce = $button.data('nonce');
			var target = $button.data('target');

			// Get user's deployment note
			var userNote = $('#ash-nazg-deploy-note-live').val().trim();

			// Build full note with context
			var siteUrl = window.location.origin;
			var fullNote = userNote ? userNote + ' ' : '';
			fullNote += '(Triggered by Ash Nazg at ' + siteUrl + ')';

			// Get sync_content checkbox value
			var syncContent = $('#ash-nazg-sync-content').is(':checked');

			// Confirm action (warn about live deployment)
			window.AshNazgModal.confirm(
				ashNazgDevelopment.i18n.confirmDeployToLive,
				function() {
					// User confirmed - execute deploy to live
					executeDeployToLive($button, nonce, target, fullNote, syncContent);
				},
				'danger'
			);
		});
	});

	/**
	 * Execute apply upstream updates after confirmation.
	 */
	function executeApplyUpstreamUpdates($button, nonce) {
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
								// Clear upstream updates cache before reloading
								$.ajax({
									url: ashNazgDevelopment.ajaxUrl,
									type: 'POST',
									data: {
										action: 'ash_nazg_clear_upstream_cache',
										nonce: ashNazgDevelopment.clearUpstreamCacheNonce
									},
									complete: function() {
										// Reload page whether cache clear succeeded or not
										window.AshNazgModal.alert(
											ashNazgDevelopment.i18n.updatesApplied,
											function() {
												window.location.reload();
											},
											'info'
										);
									}
								});
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgDevelopment.i18n.operationFailed,
									null,
									'danger'
								);
							}
						}
					);
				} else {
					modal.close();
					$button.prop('disabled', false);
					window.AshNazgModal.alert(
						response.data?.message || ashNazgDevelopment.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgDevelopment.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	}

	/**
	 * Execute merge dev to multidev after confirmation.
	 */
	function executeMergeDevToMultidev($button, nonce) {
		// Disable button
		$button.prop('disabled', true);

		// Show progress modal
		var modal = showProgressModal(
			ashNazgDevelopment.i18n.mergingDevToMultidev,
			ashNazgDevelopment.i18n.pleaseWait
		);

		// Submit via AJAX
		$.ajax({
			url: ashNazgDevelopment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_merge_dev_to_multidev',
				nonce: nonce,
				updatedb: false
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
								window.AshNazgModal.alert(
									ashNazgDevelopment.i18n.devMergedToMultidev,
									function() {
										window.location.reload();
									},
									'info'
								);
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgDevelopment.i18n.operationFailed,
									null,
									'danger'
								);
							}
						}
					);
				} else {
					modal.close();
					$button.prop('disabled', false);
					window.AshNazgModal.alert(
						response.data?.message || ashNazgDevelopment.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgDevelopment.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	}

	/**
	 * Execute deploy to test after confirmation.
	 */
	function executeDeployToTest($button, nonce, target, fullNote) {
		// Disable button
		$button.prop('disabled', true);

		// Show progress modal
		var modal = showProgressModal(
			ashNazgDevelopment.i18n.deployingToTest,
			ashNazgDevelopment.i18n.pleaseWait
		);

		// Submit via AJAX
		$.ajax({
			url: ashNazgDevelopment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_deploy_code',
				nonce: nonce,
				target: target,
				note: fullNote,
				clear_cache: 'true'
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
								window.AshNazgModal.alert(
									ashNazgDevelopment.i18n.deploySucceeded,
									function() {
										window.location.reload();
									},
									'info'
								);
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgDevelopment.i18n.operationFailed,
									null,
									'danger'
								);
							}
						}
					);
				} else {
					modal.close();
					$button.prop('disabled', false);
					window.AshNazgModal.alert(
						response.data?.message || ashNazgDevelopment.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgDevelopment.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	}

	/**
	 * Execute deploy to live after confirmation.
	 */
	function executeDeployToLive($button, nonce, target, fullNote, syncContent) {
		// Disable button
		$button.prop('disabled', true);

		// Show progress modal
		var modal = showProgressModal(
			ashNazgDevelopment.i18n.deployingToLive,
			ashNazgDevelopment.i18n.pleaseWait
		);

		// Submit via AJAX
		$.ajax({
			url: ashNazgDevelopment.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_deploy_code',
				nonce: nonce,
				target: target,
				note: fullNote,
				clear_cache: 'true',
				sync_content: syncContent ? 'true' : 'false'
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
								window.AshNazgModal.alert(
									ashNazgDevelopment.i18n.deploySucceeded,
									function() {
										window.location.reload();
									},
									'info'
								);
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgDevelopment.i18n.operationFailed,
									null,
									'danger'
								);
							}
						}
					);
				} else {
					modal.close();
					$button.prop('disabled', false);
					window.AshNazgModal.alert(
						response.data?.message || ashNazgDevelopment.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgDevelopment.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	}

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
