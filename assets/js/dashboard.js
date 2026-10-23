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

	// ── Workspace row ─────────────────────────────────────────────────────────
	// Workspaces take too long to resolve during a page load, so they are fetched
	// a couple at a time and the row is replaced when the last page arrives.

	function loadWorkspaces() {
		var $row = $('.ash-nazg-workspace-pending');
		var restarted = false;

		if (!$row.length) {
			return;
		}

		function fail() {
			$row.find('td').text(ashNazgDashboard.i18n.workspacesError);
			$row.removeClass('ash-nazg-workspace-pending');
		}

		function next(cursor) {
			$.ajax({
				url: ashNazgDashboard.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ash_nazg_load_workspaces',
					nonce: ashNazgDashboard.loadWorkspacesNonce,
					cursor: cursor
				}
			}).done(function(response) {
				if (!response.success) {
					fail();
					return;
				}

				if (response.data.done) {
					$row.replaceWith(response.data.html);
				} else {
					next(response.data.next);
				}
			}).fail(function(xhr) {
				var code = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.code : '';

				// The partial results expired mid-load; start over once instead of caching a truncated list.
				if (code === 'partial_lost' && !restarted) {
					restarted = true;
					next('');
					return;
				}

				fail();
			});
		}

		next('');
	}

	loadWorkspaces();

	// ── API Endpoints panel ───────────────────────────────────────────────────
	// Endpoints are listed immediately; the ones that can be probed are checked
	// one request at a time after the page has loaded.

	// Client-side filters over the endpoint rows already on the page: a status tab
	// and an API version, so no extra requests are needed.
	var endpointFilter = 'all';
	var endpointVersion = '';

	function endpointRowMatchesFilter($row, filter) {
		var status = $row.attr('data-status');

		if (filter === 'working') {
			return status === 'success';
		}

		if (filter === 'attention') {
			return status === 'error' || status === 'unavailable';
		}

		if (filter === 'skipped') {
			return status === 'skipped';
		}

		return true;
	}

	function applyEndpointFilters() {
		var counts = { all: 0, working: 0, attention: 0, skipped: 0 };

		$('.ash-nazg-endpoint-row').each(function() {
			var $row = $(this);
			var inVersion = endpointVersion === '' || $row.attr('data-version') === endpointVersion;

			if (inVersion) {
				counts.all++;
				$.each(['working', 'attention', 'skipped'], function(index, filter) {
					if (endpointRowMatchesFilter($row, filter)) {
						counts[filter]++;
					}
				});
			}

			$row.toggleClass('ash-nazg-hidden', !(inVersion && endpointRowMatchesFilter($row, endpointFilter)));
		});

		$('.ash-nazg-endpoint-group').each(function() {
			var $group = $(this);
			$group.toggleClass('ash-nazg-hidden', $group.find('.ash-nazg-endpoint-row:not(.ash-nazg-hidden)').length === 0);
		});

		$.each(counts, function(filter, count) {
			$('.ash-nazg-endpoint-tab-count[data-count="' + filter + '"]').text(count);
		});
	}

	// Tell screen reader users how many endpoints the filter left, but only after
	// they change it, not each time a row finishes loading.
	function announceEndpointFilter() {
		var shown = $('.ash-nazg-endpoint-row:not(.ash-nazg-hidden)').length;

		$('#ash-nazg-endpoint-filter-status').text(ashNazgDashboard.i18n.endpointsShown.replace('%d', shown));
	}

	$('.ash-nazg-endpoint-tabs').on('click', '.nav-tab', function(e) {
		e.preventDefault();

		endpointFilter = $(this).data('filter');
		$('.ash-nazg-endpoint-tabs .nav-tab').removeClass('nav-tab-active').attr('aria-pressed', 'false');
		$(this).addClass('nav-tab-active').attr('aria-pressed', 'true');

		applyEndpointFilters();
		announceEndpointFilter();
	});

	$('#ash-nazg-endpoint-version').on('change', function() {
		endpointVersion = $(this).val();
		applyEndpointFilters();
		announceEndpointFilter();
	});

	function updateEndpointSummary() {
		var $rows = $('.ash-nazg-endpoint-row');
		var ok = $rows.filter('[data-status="success"]').length;
		var unavailable = $rows.filter('[data-status="unavailable"]').length;
		var skipped = $rows.filter('[data-status="skipped"]').length;
		var pending = $rows.filter('[data-status="pending"]').length;
		var errors = $rows.length - ok - unavailable - skipped - pending;

		$('#ash-nazg-endpoints-ok').text(ok);
		$('#ash-nazg-endpoints-unavailable').text(unavailable);
		$('#ash-nazg-endpoints-skipped').text(skipped);
		$('#ash-nazg-endpoints-error').text(errors);
		$('#ash-nazg-endpoints-total').text($rows.length);
		$('#ash-nazg-endpoints-unavailable-wrap').toggleClass('ash-nazg-hidden', unavailable === 0);
		$('#ash-nazg-endpoints-skipped-wrap').toggleClass('ash-nazg-hidden', skipped === 0);
		$('#ash-nazg-endpoints-error-wrap').toggleClass('ash-nazg-hidden', errors === 0);

		applyEndpointFilters();
	}

	// Rows rendered as "checking" are probed one request each, in order, so no
	// single request is longer than the slowest endpoint.
	function loadPendingEndpointRows() {
		var $rows = $('.ash-nazg-endpoint-pending');

		function next(index) {
			if (index >= $rows.length) {
				return;
			}

			var $row = $rows.eq(index);

			$.ajax({
				url: ashNazgDashboard.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'ash_nazg_probe_endpoint',
					nonce: ashNazgDashboard.probeEndpointNonce,
					entry: $row.data('entry-id')
				}
			}).done(function(response) {
				if (response.success) {
					$row.replaceWith(response.data.html);
				} else {
					$row.find('td').last().text(ashNazgDashboard.i18n.endpointError);
					$row.attr('data-status', 'error').removeClass('ash-nazg-endpoint-pending');
				}
			}).fail(function() {
				$row.find('td').last().text(ashNazgDashboard.i18n.endpointError);
				$row.attr('data-status', 'error').removeClass('ash-nazg-endpoint-pending');
			}).always(function() {
				updateEndpointSummary();
				next(index + 1);
			});
		}

		next(0);
	}

	updateEndpointSummary();
	loadPendingEndpointRows();

	// ── Screen Options: endpoint visibility master toggle ───────────────────────
	// Unchecking the master toggle unchecks all group checkboxes; re-checking restores all.
	$('#ash_nazg_dashboard_show_endpoints').on('change', function() {
		$('input[name="ash_nazg_dashboard_visible_group[]"]').prop('checked', $(this).is(':checked'));
	});

	// ── Organization selector ────────────────────────────────────────────────

	// Delegated: the Workspace row is replaced once workspaces have loaded.
	$(document).on('click', '#ash-nazg-org-edit', function(e) {
		e.preventDefault();
		$('#ash-nazg-org-display').hide();
		$('#ash-nazg-org-select-wrap').show();
		$('#ash-nazg-org-select').trigger('focus');
	});

	$(document).on('click', '#ash-nazg-org-cancel', function() {
		$('#ash-nazg-org-select-wrap').hide();
		$('#ash-nazg-org-display').show();
	});

	$(document).on('click', '#ash-nazg-org-save', function() {
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

	// ── Token copy (Personal Access Token + legacy Machine Token) ───────────

	function bindCopyTokenButton(buttonId, feedbackId, ajaxAction) {
		$(buttonId).on('click', function() {
			var $btn      = $(this);
			var $feedback = $(feedbackId);
			var nonce     = $btn.data('nonce');

			$btn.prop('disabled', true);

			$.ajax({
				url: ashNazgDashboard.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: ajaxAction,
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
	}

	bindCopyTokenButton('#ash-nazg-copy-access-token', '#ash-nazg-copy-access-token-feedback', 'ash_nazg_copy_access_token');
});
