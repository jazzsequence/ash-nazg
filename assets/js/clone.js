/**
 * Clone content page functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var $fromSelect = $('#clone-from-env');
		var $toSelect = $('#clone-to-env');

		// Disable selected source in target dropdown
		$fromSelect.on('change', function() {
			var fromValue = $(this).val();
			$toSelect.find('option').prop('disabled', false);
			if (fromValue) {
				$toSelect.find('option[value="' + fromValue + '"]').prop('disabled', true);
				// If target is same as source, reset target
				if ($toSelect.val() === fromValue) {
					$toSelect.val('');
				}
			}
		});

		// Disable selected target in source dropdown
		$toSelect.on('change', function() {
			var toValue = $(this).val();
			$fromSelect.find('option').prop('disabled', false);
			if (toValue) {
				$fromSelect.find('option[value="' + toValue + '"]').prop('disabled', true);
				// If source is same as target, reset source
				if ($fromSelect.val() === toValue) {
					$fromSelect.val('');
				}
			}
		});

		// Form submission
		$('#ash-nazg-clone-form').on('submit', function(e) {
			e.preventDefault();

			var fromEnv = $fromSelect.val();
			var toEnv = $toSelect.val();
			var cloneDatabase = $('#clone-database').is(':checked');
			var cloneFiles = $('#clone-files').is(':checked');

			// Validation
			if (!fromEnv || !toEnv) {
				alert(ashNazgClone.i18n.selectBoth);
				return;
			}

			if (fromEnv === toEnv) {
				alert(ashNazgClone.i18n.sameEnvironment);
				return;
			}

			if (!cloneDatabase && !cloneFiles) {
				alert(ashNazgClone.i18n.selectOne);
				return;
			}

			// Confirmation
			if (!confirm(ashNazgClone.i18n.confirmClone)) {
				return;
			}

			var $submitButton = $('#ash-nazg-clone-submit');
			$submitButton.prop('disabled', true);

			// Show progress modal
			var modal = showProgressModal(
				ashNazgClone.i18n.cloningContent,
				ashNazgClone.i18n.pleaseWait
			);

			// Submit via AJAX
			$.ajax({
				url: ashNazgClone.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_clone_content',
					nonce: ashNazgClone.cloneContentNonce,
					from_env: fromEnv,
					to_env: toEnv,
					clone_database: cloneDatabase ? 'true' : 'false',
					clone_files: cloneFiles ? 'true' : 'false'
				},
				success: function(response) {
					if (response.success && response.data && response.data.workflow_ids) {
						// Poll all workflow IDs
						pollMultipleWorkflows(
							response.data.site_id,
							response.data.workflow_ids,
							function(progress, status) {
								modal.updateProgress(progress, status);
							},
							function(success, error) {
								modal.close();
								$submitButton.prop('disabled', false);

								if (success) {
									alert(ashNazgClone.i18n.cloneSuccess);
									window.location.reload();
								} else {
									alert(error || ashNazgClone.i18n.operationFailed);
								}
							}
						);
					} else {
						modal.close();
						$submitButton.prop('disabled', false);
						alert(response.data?.message || ashNazgClone.i18n.operationFailed);
					}
				},
				error: function() {
					modal.close();
					$submitButton.prop('disabled', false);
					alert(ashNazgClone.i18n.ajaxError);
				}
			});
		});
	});

	/**
	 * Poll multiple workflows until all complete.
	 */
	function pollMultipleWorkflows(siteId, workflowIds, progressCallback, completeCallback) {
		if (!workflowIds || workflowIds.length === 0) {
			completeCallback(false, ashNazgClone.i18n.operationFailed);
			return;
		}

		const pollInterval = 2000; // 2 seconds
		const maxAttempts = 120; // 4 minutes total
		let attempts = 0;
		let completedWorkflows = 0;
		let failedWorkflows = 0;
		let workflowStatuses = {};

		function checkAllWorkflows() {
			let allComplete = true;
			let totalProgress = 0;

			// Poll each workflow
			let remaining = workflowIds.length;

			workflowIds.forEach(function(workflowId) {
				// Skip if already marked complete or failed
				if (workflowStatuses[workflowId]?.complete) {
					remaining--;
					if (remaining === 0) {
						processResults();
					}
					return;
				}

				$.ajax({
					url: ashNazgClone.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ash_nazg_get_workflow_status',
						nonce: ashNazgClone.workflowStatusNonce,
						site_id: siteId,
						workflow_id: workflowId
					},
					success: function(response) {
						if (response.success && response.data) {
							const status = response.data;

							// Calculate progress for this workflow
							let progress = 0;
							if (status.step && status.operations && status.operations.length > 0) {
								progress = Math.round((status.step / status.operations.length) * 100);
							}

							workflowStatuses[workflowId] = {
								progress: progress,
								status: status.active_description || '',
								result: status.result,
								complete: status.result === 'succeeded' || status.result === 'failed'
							};

							if (status.result === 'succeeded') {
								completedWorkflows++;
							} else if (status.result === 'failed') {
								failedWorkflows++;
							}
						}

						remaining--;
						if (remaining === 0) {
							processResults();
						}
					},
					error: function() {
						failedWorkflows++;
						workflowStatuses[workflowId] = {
							progress: 0,
							status: '',
							result: 'failed',
							complete: true
						};

						remaining--;
						if (remaining === 0) {
							processResults();
						}
					}
				});
			});
		}

		function processResults() {
			// Calculate average progress across all workflows
			let totalProgress = 0;
			let activeStatus = '';
			let allComplete = true;

			Object.values(workflowStatuses).forEach(function(wf) {
				totalProgress += wf.progress || 0;
				if (!wf.complete) {
					allComplete = false;
				}
				if (wf.status && !activeStatus) {
					activeStatus = wf.status;
				}
			});

			const avgProgress = Math.round(totalProgress / workflowIds.length);

			// Update progress
			if (progressCallback && avgProgress > 0) {
				progressCallback(avgProgress, activeStatus);
			}

			// Check if all workflows are complete
			if (allComplete || completedWorkflows + failedWorkflows >= workflowIds.length) {
				if (failedWorkflows > 0) {
					completeCallback(false, ashNazgClone.i18n.operationFailed);
				} else {
					completeCallback(true, null);
				}
				return;
			}

			// Continue polling
			attempts++;
			if (attempts < maxAttempts) {
				setTimeout(checkAllWorkflows, pollInterval);
			} else {
				completeCallback(false, ashNazgClone.i18n.timeoutError);
			}
		}

		// Start polling
		checkAllWorkflows();
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
