/**
 * Pantheon upstream updates integration for the WordPress Updates page.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		$('#ash-nazg-apply-upstream-updates-core').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: ashNazgUpdatesPage.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_apply_upstream_updates',
					nonce: ashNazgUpdatesPage.applyNonce,
					updatedb: false,
					xoption: false
				},
				success: function(response) {
					if (response.success && response.data && response.data.workflow_id) {
						pollWorkflow(response.data.workflow_id, response.data.site_id, $btn);
					} else {
						$btn.prop('disabled', false);
						window.AshNazgModal.alert({
							message: (response.data && response.data.message) || ashNazgUpdatesPage.i18n.failed,
							type: 'danger'
						});
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					window.AshNazgModal.alert({
						message: ashNazgUpdatesPage.i18n.ajaxError,
						type: 'danger'
					});
				}
			});
		});

		function pollWorkflow(workflowId, siteId, $btn) {
			var attempts    = 0;
			var maxAttempts = 60;

			function checkStatus() {
				$.ajax({
					url: ashNazgUpdatesPage.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ash_nazg_get_workflow_status',
						nonce: ashNazgUpdatesPage.workflowNonce,
						site_id: siteId,
						workflow_id: workflowId
					},
					success: function(response) {
						if (!response.success || !response.data) {
							$btn.prop('disabled', false);
							return;
						}

						var status = response.data;

						if (status.result === 'succeeded') {
							window.AshNazgModal.alert({
								message: ashNazgUpdatesPage.i18n.applied,
								type: 'info',
								onClose: function() { window.location.reload(); }
							});
						} else if (status.result === 'failed') {
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
								$btn.prop('disabled', false);
								window.AshNazgModal.alert({
									message: ashNazgUpdatesPage.i18n.timeout,
									type: 'warning'
								});
							}
						}
					},
					error: function() {
						$btn.prop('disabled', false);
					}
				});
			}

			checkStatus();
		}
	});
})(jQuery);
