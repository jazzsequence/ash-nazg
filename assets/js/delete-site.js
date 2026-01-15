(function($) {
	'use strict';

	$(document).ready(function() {
		var $form = $('#ash-nazg-delete-site-form');
		var $input = $('#delete-confirmation');
		var $button = $('#delete-site-button');
		var $cancelMessage = $('#cancel-message');
		var $modal = $('#danger-modal');
		var $modalConfirm = $('#modal-confirm');
		var $modalCancel = $('#modal-cancel');

		// Enable button only when "DELETE" is typed exactly
		$input.on('input', function() {
			var typed = $(this).val();
			if (typed === 'DELETE') {
				$button.prop('disabled', false);
			} else {
				$button.prop('disabled', true);
			}
		});

		// Form submission with modal and alert confirmations
		$form.on('submit', function(e) {
			e.preventDefault();

			var typedText = $input.val();
			if (typedText !== 'DELETE') {
				alert('You must type DELETE to proceed.');
				return;
			}

			// Show modal for first confirmation
			$modal.show();
		});

		// Modal confirm button - proceed to second confirmation
		$modalConfirm.on('click', function() {
			$modal.hide();

			// Second confirmation (JavaScript alert)
			if (!confirm(ashNazgDeleteSite.i18n.secondConfirmMessage)) {
				showCancelMessage();
				return;
			}

			// Disable button and show loading state
			$button.prop('disabled', true).text(ashNazgDeleteSite.i18n.deleting);

			// Perform deletion
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
						alert(ashNazgDeleteSite.i18n.deleted);
						// Redirect to Pantheon sites dashboard
						window.location.href = 'https://dashboard.pantheon.io/sites';
					} else {
						alert(response.data?.message || ashNazgDeleteSite.i18n.error);
						$button.prop('disabled', false).text('DELETE SITE');
					}
				},
				error: function() {
					alert(ashNazgDeleteSite.i18n.error);
					$button.prop('disabled', false).text('DELETE SITE');
				}
			});
		});

		// Modal cancel button - show "whew" message
		$modalCancel.on('click', function() {
			$modal.hide();
			showCancelMessage();
		});

		// Close modal if clicking outside
		$modal.on('click', function(e) {
			if (e.target === this) {
				$modal.hide();
				showCancelMessage();
			}
		});

		function showCancelMessage() {
			$cancelMessage.show();
			$input.val('');
			$button.prop('disabled', true);

			// Scroll to message
			$('html, body').animate({
				scrollTop: $cancelMessage.offset().top - 100
			}, 500);

			// Hide message after 8 seconds
			setTimeout(function() {
				$cancelMessage.fadeOut();
			}, 8000);
		}
	});
})(jQuery);
