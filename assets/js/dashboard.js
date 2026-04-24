/**
 * Dashboard JavaScript for Ash-Nazg plugin.
 *
 * @package Pantheon\AshNazg
 */

jQuery(document).ready(function($) {
	/**
	 * Poll workflow status until completion.
	 */
	function pollWorkflowStatus(siteId, workflowId, completeCallback) {
		const pollInterval = 2000; // 2 seconds
		const maxAttempts = 60; // 2 minutes total
		let attempts = 0;

		function checkStatus() {
			$.ajax({
				url: ashNazgDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_get_workflow_status',
					nonce: ashNazgDashboard.workflowStatusNonce,
					site_id: siteId,
					workflow_id: workflowId
				},
				success: function(response) {
					if (response.success && response.data) {
						const status = response.data;

						// Check if workflow is complete
						if (status.result === 'succeeded' || status.result === 'failed') {
							completeCallback(status);
							return;
						}

						// Continue polling if not complete
						attempts++;
						if (attempts < maxAttempts) {
							setTimeout(checkStatus, pollInterval);
						} else {
							completeCallback({
								result: 'failed',
								error: ashNazgDashboard.i18n.timeoutError || 'Operation timed out.'
							});
						}
					} else {
						completeCallback({
							result: 'failed',
							error: response.data?.message || 'Failed to get workflow status.'
						});
					}
				},
				error: function() {
					completeCallback({
						result: 'failed',
						error: ashNazgDashboard.i18n.ajaxError || 'AJAX request failed.'
					});
				}
			});
		}

		checkStatus();
	}

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
				if (response.success && response.data && response.data.workflow_id) {
					// Poll workflow status until complete
					pollWorkflowStatus(
						response.data.site_id,
						response.data.workflow_id,
						function(status) {
							if (status.result === 'succeeded') {
								// Reload page to show updated state
								location.reload();
							} else {
								$loading.hide();
								$button.prop('disabled', false);
								var errorMsg = status.error || ashNazgDashboard.i18n.toggleError;
								$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
							}
						}
					);
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

	/**
	 * Handle site label inline editing.
	 */
	$('#ash-nazg-edit-site-label').on('click', function(e) {
		e.preventDefault();
		$('#ash-nazg-site-label-display').hide();
		$('#ash-nazg-site-label-form').show();
		$('#ash-nazg-site-label-input').focus();
	});

	$('#ash-nazg-cancel-site-label').on('click', function() {
		$('#ash-nazg-site-label-form').hide();
		$('#ash-nazg-site-label-display').show();
		// Reset input to original value.
		var originalLabel = $('#ash-nazg-site-label-display').text().trim();
		$('#ash-nazg-site-label-input').val(originalLabel);
	});

	$('#ash-nazg-save-site-label').on('click', function() {
		var $input = $('#ash-nazg-site-label-input');
		var $saveButton = $(this);
		var $cancelButton = $('#ash-nazg-cancel-site-label');
		var $loading = $('#ash-nazg-site-label-loading');
		var newLabel = $input.val().trim();

		if (newLabel === '') {
			$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + ashNazgDashboard.i18n.emptyLabelError + '</p></div>');
			return;
		}

		// Disable buttons and show loading.
		$input.prop('disabled', true);
		$saveButton.prop('disabled', true);
		$cancelButton.prop('disabled', true);
		$loading.show();

		$.ajax({
			url: ashNazgDashboard.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_update_site_label',
				label: newLabel,
				nonce: ashNazgDashboard.updateLabelNonce
			},
			success: function(response) {
				$loading.hide();
				if (response.success) {
					// Update the displayed label.
					$('#ash-nazg-site-label-display').html('<span>' + response.data.label + '</span><a href="#" id="ash-nazg-edit-site-label" class="ash-nazg-edit-link" title="Edit site label"><span class="dashicons dashicons-edit"></span></a>');
					$('#ash-nazg-site-label-form').hide();
					$('#ash-nazg-site-label-display').show();
					$('.wrap h1').first().after('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
					// Re-bind the edit link click handler.
					$('#ash-nazg-edit-site-label').on('click', function(e) {
						e.preventDefault();
						$('#ash-nazg-site-label-display').hide();
						$('#ash-nazg-site-label-form').show();
						$('#ash-nazg-site-label-input').focus();
					});
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : ashNazgDashboard.i18n.updateLabelError;
					$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
				}
				// Re-enable buttons.
				$input.prop('disabled', false);
				$saveButton.prop('disabled', false);
				$cancelButton.prop('disabled', false);
			},
			error: function() {
				$loading.hide();
				$input.prop('disabled', false);
				$saveButton.prop('disabled', false);
				$cancelButton.prop('disabled', false);
				$('.wrap h1').first().after('<div class="notice notice-error is-dismissible"><p>' + ashNazgDashboard.i18n.updateLabelError + '</p></div>');
			}
		});
	});

	// Allow pressing Enter key to save label.
	$('#ash-nazg-site-label-input').on('keypress', function(e) {
		if (e.which === 13) {
			e.preventDefault();
			$('#ash-nazg-save-site-label').click();
		}
	});

	// Allow pressing Escape key to cancel editing.
	$('#ash-nazg-site-label-input').on('keyup', function(e) {
		if (e.which === 27) {
			$('#ash-nazg-cancel-site-label').click();
		}
	});

	// ── Organization selector ────────────────────────────────────────────────

	$('#ash-nazg-org-edit').on('click', function(e) {
		e.preventDefault();
		$('#ash-nazg-org-display').hide();
		$('#ash-nazg-org-select-wrap').show();
		$('#ash-nazg-org-select').trigger('focus');
	});

	$('#ash-nazg-org-cancel').on('click', function() {
		$('#ash-nazg-org-select-wrap').hide();
		$('#ash-nazg-org-display').show();
	});

	$('#ash-nazg-org-save').on('click', function() {
		var $btn    = $(this);
		var $select = $('#ash-nazg-org-select');
		var orgId   = $select.val();
		var orgName = $select.find('option:selected').data('name');
		var nonce   = $btn.data('nonce');

		$btn.prop('disabled', true);

		$.ajax({
			url: ashNazgDashboard.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action:   'ash_nazg_save_primary_org',
				nonce:    nonce,
				org_id:   orgId,
				org_name: orgName
			},
			success: function(response) {
				if (response.success) {
					$('#ash-nazg-org-display span:first').text(orgName);
					$('#ash-nazg-org-select-wrap').hide();
					$('#ash-nazg-org-display').show();
				} else {
					window.alert(response.data && response.data.message ? response.data.message : ashNazgDashboard.i18n.saveOrgError);
				}
			},
			error: function() {
				window.alert(ashNazgDashboard.i18n.saveOrgError);
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});

	// ── Machine token copy ───────────────────────────────────────────────────

	$('#ash-nazg-copy-token').on('click', function() {
		var $btn      = $(this);
		var $feedback = $('#ash-nazg-copy-token-feedback');
		var nonce     = $btn.data('nonce');

		$btn.prop('disabled', true);

		$.ajax({
			url: ashNazgDashboard.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'ash_nazg_copy_machine_token',
				nonce:  nonce
			},
			success: function(response) {
				if (response.success && response.data && response.data.token) {
					navigator.clipboard.writeText(response.data.token).then(function() {
						$feedback.removeAttr('hidden');
						setTimeout(function() { $feedback.attr('hidden', true); }, 2500);
					}).catch(function() {
						window.alert(ashNazgDashboard.i18n.clipboardError);
					});
				} else {
					window.alert(response.data && response.data.message ? response.data.message : ashNazgDashboard.i18n.copyTokenError);
				}
			},
			error: function() {
				window.alert(ashNazgDashboard.i18n.copyTokenError);
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});
});
