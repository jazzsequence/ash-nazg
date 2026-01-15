/**
 * Reusable modal utilities for Ash Nazg admin pages.
 * Provides confirm() and alert() style modals.
 */

(function($) {
	'use strict';

	// Create modal HTML if it doesn't exist
	function ensureModalExists() {
		if ($('#ash-nazg-modal-overlay').length === 0) {
			$('body').append(`
				<div id="ash-nazg-modal-overlay" class="ash-nazg-modal-overlay">
					<div class="ash-nazg-modal">
						<div class="ash-nazg-modal-header">
							<h2 id="ash-nazg-modal-title"></h2>
						</div>
						<div class="ash-nazg-modal-body">
							<div id="ash-nazg-modal-message"></div>
						</div>
						<div class="ash-nazg-modal-footer">
							<button type="button" id="ash-nazg-modal-cancel" class="button">Cancel</button>
							<button type="button" id="ash-nazg-modal-confirm" class="button button-primary">Confirm</button>
						</div>
					</div>
				</div>
			`);

			// Click overlay to close
			$('#ash-nazg-modal-overlay').on('click', function(e) {
				if (e.target === this) {
					window.AshNazgModal.close();
				}
			});

			// ESC key to close
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#ash-nazg-modal-overlay').hasClass('active')) {
					window.AshNazgModal.close();
				}
			});
		}
	}

	// Modal API
	window.AshNazgModal = {
		/**
		 * Show a confirmation modal.
		 *
		 * @param {Object} options Modal configuration
		 * @param {string} options.title Modal title
		 * @param {string} options.message Modal message (can include HTML)
		 * @param {string} options.confirmText Confirm button text (default: 'Confirm')
		 * @param {string} options.cancelText Cancel button text (default: 'Cancel')
		 * @param {string} options.type Modal type: 'info', 'warning', 'danger' (default: 'info')
		 * @param {Function} options.onConfirm Callback when confirmed
		 * @param {Function} options.onCancel Callback when cancelled
		 * @returns {Promise} Promise that resolves on confirm, rejects on cancel
		 */
		confirm: function(options) {
			ensureModalExists();

			const defaults = {
				title: 'Confirm Action',
				message: 'Are you sure?',
				confirmText: 'Confirm',
				cancelText: 'Cancel',
				type: 'info',
				onConfirm: null,
				onCancel: null
			};

			const config = $.extend({}, defaults, options);

			return new Promise(function(resolve, reject) {
				const $overlay = $('#ash-nazg-modal-overlay');
				const $modal = $overlay.find('.ash-nazg-modal');
				const $confirm = $('#ash-nazg-modal-confirm');
				const $cancel = $('#ash-nazg-modal-cancel');

				// Set modal type class
				$modal.removeClass('ash-nazg-modal-info ash-nazg-modal-warning ash-nazg-modal-danger');
				$modal.addClass('ash-nazg-modal-' + config.type);

				// Set content
				$('#ash-nazg-modal-title').text(config.title);
				$('#ash-nazg-modal-message').html(config.message);
				$confirm.text(config.confirmText);
				$cancel.text(config.cancelText);

				// Show buttons
				$cancel.show();
				$confirm.show();

				// Set button type based on modal type
				$confirm.removeClass('button-primary button-secondary');
				if (config.type === 'danger') {
					$confirm.addClass('button-primary').css('background', '#dc3232');
				} else {
					$confirm.addClass('button-primary').css('background', '');
				}

				// Remove previous event handlers
				$confirm.off('click');
				$cancel.off('click');

				// Add new event handlers
				$confirm.one('click', function() {
					window.AshNazgModal.close();
					if (config.onConfirm) config.onConfirm();
					resolve(true);
				});

				$cancel.one('click', function() {
					window.AshNazgModal.close();
					if (config.onCancel) config.onCancel();
					reject(false);
				});

				// Show modal
				$overlay.addClass('active');
			});
		},

		/**
		 * Show an alert/notification modal.
		 *
		 * @param {Object} options Modal configuration
		 * @param {string} options.title Modal title
		 * @param {string} options.message Modal message (can include HTML)
		 * @param {string} options.buttonText Button text (default: 'OK')
		 * @param {string} options.type Modal type: 'info', 'warning', 'danger' (default: 'info')
		 * @param {Function} options.onClose Callback when closed
		 * @returns {Promise} Promise that resolves when closed
		 */
		alert: function(options) {
			ensureModalExists();

			const defaults = {
				title: 'Notice',
				message: '',
				buttonText: 'OK',
				type: 'info',
				onClose: null
			};

			const config = $.extend({}, defaults, options);

			return new Promise(function(resolve) {
				const $overlay = $('#ash-nazg-modal-overlay');
				const $modal = $overlay.find('.ash-nazg-modal');
				const $confirm = $('#ash-nazg-modal-confirm');
				const $cancel = $('#ash-nazg-modal-cancel');

				// Set modal type class
				$modal.removeClass('ash-nazg-modal-info ash-nazg-modal-warning ash-nazg-modal-danger');
				$modal.addClass('ash-nazg-modal-' + config.type);

				// Set content
				$('#ash-nazg-modal-title').text(config.title);
				$('#ash-nazg-modal-message').html(config.message);
				$confirm.text(config.buttonText);

				// Hide cancel button for alerts
				$cancel.hide();
				$confirm.show();

				// Reset confirm button style
				$confirm.removeClass('button-primary button-secondary');
				$confirm.addClass('button-primary').css('background', '');

				// Remove previous event handlers
				$confirm.off('click');

				// Add new event handler
				$confirm.one('click', function() {
					window.AshNazgModal.close();
					if (config.onClose) config.onClose();
					resolve(true);
				});

				// Show modal
				$overlay.addClass('active');
			});
		},

		/**
		 * Close the modal.
		 */
		close: function() {
			$('#ash-nazg-modal-overlay').removeClass('active');
			// Clean up event handlers
			$('#ash-nazg-modal-confirm').off('click');
			$('#ash-nazg-modal-cancel').off('click');
		}
	};

})(jQuery);
