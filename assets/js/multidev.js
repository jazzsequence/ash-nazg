/**
 * Multidev management JavaScript.
 */
(function($) {
	'use strict';

	/**
	 * Poll workflow status until completion.
	 */
	function pollWorkflowStatus(siteId, workflowId, progressCallback, completeCallback) {
		const pollInterval = 2000; // 2 seconds
		const maxAttempts = 60; // 2 minutes total
		let attempts = 0;

		function checkStatus() {
			$.ajax({
				url: ashNazgMultidev.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_get_workflow_status',
					nonce: ashNazgMultidev.workflowStatusNonce,
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
								error: ashNazgMultidev.i18n.timeoutError
							});
						}
					} else {
						completeCallback({
							result: 'failed',
							error: response.data?.message || ashNazgMultidev.i18n.statusError
						});
					}
				},
				error: function() {
					completeCallback({
						result: 'failed',
						error: ashNazgMultidev.i18n.ajaxError
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

	/**
	 * Handle "Merge from Dev" button clicks (in multidev table).
	 */
	$(document).on('click', '.ash-nazg-merge-from-dev-btn', function(e) {
		e.preventDefault();

		const $button = $(this);
		const multidevName = $button.data('multidev-name');
		const nonce = $button.data('nonce');

		// Confirm action
		window.AshNazgModal.confirm(
			ashNazgMultidev.i18n.confirmMergeFromDev || 'Merge changes from dev into this multidev?',
			function() {
				// User confirmed - execute merge
				executeMergeFromDev($button, multidevName, nonce);
			},
			'warning'
		);
	});

	/**
	 * Execute merge from dev after confirmation.
	 */
	function executeMergeFromDev($button, multidevName, nonce) {
		// Disable button
		$button.prop('disabled', true);

		// Show progress modal
		const modal = showProgressModal(
			ashNazgMultidev.i18n.mergingFromDev || 'Merging from Dev',
			ashNazgMultidev.i18n.pleaseWait
		);

		// Submit via AJAX
		$.ajax({
			url: ashNazgMultidev.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_merge_dev_to_multidev',
				nonce: nonce,
				multidev_name: multidevName,
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
								// Reload page to show updated state
								window.location.reload();
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgMultidev.i18n.operationFailed,
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
						response.data?.message || ashNazgMultidev.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgMultidev.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	}

	/**
	 * Handle multidev form submission.
	 */
	$(document).on('submit', 'form[data-multidev-action]', function(e) {
		e.preventDefault();

		const $form = $(this);
		const action = $form.data('multidev-action');
		const $button = $form.find('button[type="submit"]');

		// Get form data
		const formData = {
			action: 'ash_nazg_' + action + '_multidev',
			nonce: $form.find('input[name="ash_nazg_multidev_nonce"]').val(),
			multidev_name: $form.find('input[name="multidev_name"]').val(),
			source_env: $form.find('select[name="source_env"]').val()
		};

		// Disable button
		$button.prop('disabled', true);

		// Show progress modal
		let modalTitle = ashNazgMultidev.i18n.creatingMultidev;
		let modalMessage = ashNazgMultidev.i18n.pleaseWait;

		if (action === 'delete') {
			modalTitle = ashNazgMultidev.i18n.deletingMultidev;
		} else if (action === 'merge') {
			modalTitle = ashNazgMultidev.i18n.mergingMultidev;
		}

		const modal = showProgressModal(modalTitle, modalMessage);

		// Submit via AJAX
		$.ajax({
			url: ashNazgMultidev.ajaxUrl,
			type: 'POST',
			data: formData,
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
								// Reload page to show updated state
								window.location.reload();
							} else {
								window.AshNazgModal.alert(
									status.error || ashNazgMultidev.i18n.operationFailed,
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
						response.data?.message || ashNazgMultidev.i18n.operationFailed,
						null,
						'danger'
					);
				}
			},
			error: function() {
				modal.close();
				$button.prop('disabled', false);
				window.AshNazgModal.alert(
					ashNazgMultidev.i18n.ajaxError,
					null,
					'danger'
				);
			}
		});
	});

})(jQuery);
