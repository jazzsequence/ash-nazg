/**
 * Development page functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Click to copy commit hash
		$('.ash-nazg-hash-copyable').on('click', function(e) {
			e.preventDefault();
			
			var fullHash = $(this).attr('title');
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
	});
})(jQuery);
