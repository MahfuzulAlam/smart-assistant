/**
 * Admin JavaScript for Smart Assistant Settings
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Color picker enhancement (if needed)
		$('input[type="color"]').on('change', function() {
			// Optional: Add preview or validation
		});

		// API key validation on blur
		$('input[name*="[api_key]"]').on('blur', function() {
			const apiKey = $(this).val();
			if (apiKey && !apiKey.match(/^sk-[a-zA-Z0-9]{32,}$/)) {
				$(this).css('border-color', '#dc3232');
			} else {
				$(this).css('border-color', '');
			}
		});

		// Number input validation
		$('input[type="number"][name*="[max_context_posts]"]').on('change', function() {
			const value = parseInt($(this).val(), 10);
			if (value < 1 || value > 200) {
				alert('Max context posts must be between 1 and 200.');
				$(this).val(50);
			}
		});
	});

})(jQuery);

