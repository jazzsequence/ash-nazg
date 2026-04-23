(function($) {
	'use strict';

	$(document).ready(function() {
		var $form = $('#ash-nazg-delete-site-form');
		var $input = $('#delete-confirmation');
		var $button = $('#delete-site-button');
		var $cancelMessage = $('#cancel-message');

		// Enable button only when "DELETE" is typed exactly
		$input.on('input', function() {
			$button.prop('disabled', $(this).val() !== 'DELETE');
		});

		// Form submission — two-step modal confirmation before deleting
		$form.on('submit', function(e) {
			e.preventDefault();

			if ($input.val() !== 'DELETE') {
				window.AshNazgModal.alert({
					title: 'Validation Error',
					message: 'You must type DELETE to proceed.',
					type: 'warning'
				});
				return;
			}

			// First confirmation: detail the consequences
			window.AshNazgModal.confirm({
				title: 'DANGER: PERMANENT DELETION',
				message: '<p><strong>This action is PERMANENT and IRREVERSIBLE.</strong></p>' +
					'<ul style="list-style:disc;margin-left:20px;line-height:1.8">' +
					'<li>ALL environments (dev, test, live, multidevs) will be DELETED</li>' +
					'<li>ALL database content will be PERMANENTLY LOST</li>' +
					'<li>ALL files and uploads will be PERMANENTLY LOST</li>' +
					'<li>ALL backups will be PERMANENTLY LOST</li>' +
					'<li>There is NO UNDO. This cannot be reversed.</li>' +
					'</ul>' +
					'<p style="font-weight:bold;color:#dc3232">Your website will be gone forever. All data will be lost.</p>',
				confirmText: 'I Understand the Risk',
				cancelText: 'Cancel',
				type: 'danger',
				onConfirm: function() {
					// Second confirmation: last chance
					window.AshNazgModal.confirm({
						title: 'Final Confirmation',
						message: '<p>This is your <strong>LAST CHANCE</strong> to cancel.</p>' +
							'<p>Click <strong>DELETE FOREVER</strong> to permanently delete your site.</p>',
						confirmText: 'DELETE FOREVER',
						cancelText: 'Keep My Site',
						type: 'danger',
						onConfirm: function() {
							performDeletion();
						},
						onCancel: function() {
							showCancelMessage();
						}
					});
				},
				onCancel: function() {
					showCancelMessage();
				}
			});
		});

		function performDeletion() {
			$button.prop('disabled', true).text(ashNazgDeleteSite.i18n.deleting);

			$.ajax({
				url: ashNazgDeleteSite.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ash_nazg_delete_site',
					nonce: ashNazgDeleteSite.nonce,
					confirmation: $input.val()
				},
				success: function(response) {
					if (response.success) {
						window.AshNazgModal.alert({
							title: 'Site Deleted',
							message: ashNazgDeleteSite.i18n.deleted,
							type: 'info',
							onClose: function() {
								window.location.href = 'https://dashboard.pantheon.io/sites';
							}
						});
					} else {
						window.AshNazgModal.alert({
							title: 'Deletion Failed',
							message: response.data?.message || ashNazgDeleteSite.i18n.error,
							type: 'danger'
						});
						$button.prop('disabled', false).text('DELETE SITE');
					}
				},
				error: function() {
					window.AshNazgModal.alert({
						title: 'Error',
						message: ashNazgDeleteSite.i18n.error,
						type: 'danger'
					});
					$button.prop('disabled', false).text('DELETE SITE');
				}
			});
		}

		function showCancelMessage() {
			$cancelMessage.show();
			$input.val('');
			$button.prop('disabled', true);

			$('html, body').animate({
				scrollTop: $cancelMessage.offset().top - 100
			}, 500);

			setTimeout(function() {
				$cancelMessage.fadeOut();
			}, 8000);
		}
	});
})(jQuery);
