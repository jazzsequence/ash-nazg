/**
 * Backups page functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Create Backup button
		$('#ash-nazg-create-backup-btn').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var $form = $('#ash-nazg-create-backup-form');
			var nonce = $button.data('nonce');
			var element = $form.find('#backup-element').val();
			var keepFor = $form.find('#backup-keep-for').val();

			// Confirm action
			if (!confirm(ashNazgBackups.i18n.confirmCreate)) {
				return;
			}

			// Disable button
			$button.prop('disabled', true);

			// Show progress modal
			var modal = showProgressModal(
				ashNazgBackups.i18n.creatingBackup,
				ashNazgBackups.i18n.pleaseWait
			);

			// Submit via AJAX
			$.ajax({
				url: ashNazgBackups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_create_backup',
					nonce: nonce,
					element: element,
					keep_for: keepFor
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
									alert(ashNazgBackups.i18n.backupCreated);
									window.location.reload();
								} else {
									alert(status.error || ashNazgBackups.i18n.operationFailed);
								}
							}
						);
					} else {
						modal.close();
						$button.prop('disabled', false);
						alert(response.data?.message || ashNazgBackups.i18n.operationFailed);
					}
				},
				error: function() {
					modal.close();
					$button.prop('disabled', false);
					alert(ashNazgBackups.i18n.ajaxError);
				}
			});
		});

		// Download Backup button
		$('.ash-nazg-download-backup').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var backupId = $button.data('backup-id');
			var element = $button.data('element');
			var nonce = $button.data('nonce');

			// Disable button and show loading state
			$button.prop('disabled', true);
			var originalText = $button.text();
			$button.text(ashNazgBackups.i18n.downloadingBackup);

			// Get download URL via AJAX
			$.ajax({
				url: ashNazgBackups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_download_backup',
					nonce: nonce,
					backup_id: backupId,
					element: element
				},
				success: function(response) {
					$button.prop('disabled', false);
					$button.text(originalText);

					if (response.success && response.data && response.data.url) {
						// Open download URL in new tab
						window.open(response.data.url, '_blank');
					} else {
						alert(response.data?.message || ashNazgBackups.i18n.operationFailed);
					}
				},
				error: function() {
					$button.prop('disabled', false);
					$button.text(originalText);
					alert(ashNazgBackups.i18n.ajaxError);
				}
			});
		});

		// Restore Backup button
		$('.ash-nazg-restore-backup').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var backupId = $button.data('backup-id');
			var element = $button.data('element');
			var nonce = $button.data('nonce');

			// Confirm action with strong warning
			if (!confirm(ashNazgBackups.i18n.confirmRestore)) {
				return;
			}

			// Disable button
			$button.prop('disabled', true);

			// Show progress modal
			var modal = showProgressModal(
				ashNazgBackups.i18n.restoringBackup,
				ashNazgBackups.i18n.pleaseWait
			);

			// Submit via AJAX
			$.ajax({
				url: ashNazgBackups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_restore_backup',
					nonce: nonce,
					backup_id: backupId,
					element: element
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
									alert(ashNazgBackups.i18n.backupRestored);
									window.location.reload();
								} else {
									alert(status.error || ashNazgBackups.i18n.operationFailed);
								}
							}
						);
					} else {
						modal.close();
						$button.prop('disabled', false);
						alert(response.data?.message || ashNazgBackups.i18n.operationFailed);
					}
				},
				error: function() {
					modal.close();
					$button.prop('disabled', false);
					alert(ashNazgBackups.i18n.ajaxError);
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
				url: ashNazgBackups.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_get_workflow_status',
					nonce: ashNazgBackups.workflowStatusNonce,
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
								error: ashNazgBackups.i18n.timeoutError
							});
						}
					} else {
						completeCallback({
							result: 'failed',
							error: response.data?.message || ashNazgBackups.i18n.statusError
						});
					}
				},
				error: function() {
					completeCallback({
						result: 'failed',
						error: ashNazgBackups.i18n.ajaxError
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
