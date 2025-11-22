/**
 * Smart Assistant Triggers Admin JavaScript
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		// Handle trigger test button
		$(document).on('click', '.trigger-test', function(e) {
			e.preventDefault();
			const triggerId = $(this).data('trigger-id');
			const button = $(this);
			const originalText = button.text();

			button.prop('disabled', true).text(smartAssistantTriggers.strings.testing);

			$.ajax({
				url: smartAssistantTriggers.ajaxUrl,
				type: 'POST',
				data: {
					action: 'smart_assistant_test_trigger',
					nonce: smartAssistantTriggers.nonce,
					trigger_id: triggerId
				},
				success: function(response) {
					if (response.success) {
						alert(smartAssistantTriggers.strings.success + '\n\n' + response.data.message);
					} else {
						alert(smartAssistantTriggers.strings.error + '\n\n' + (response.data.message || 'Unknown error'));
					}
				},
				error: function() {
					alert(smartAssistantTriggers.strings.error);
				},
				complete: function() {
					button.prop('disabled', false).text(originalText);
				}
			});
		});

		// Handle trigger settings (would open modal or navigate to settings page)
		$(document).on('click', '.trigger-settings', function(e) {
			e.preventDefault();
			// TODO: Implement settings modal or page
			alert('Settings functionality coming soon');
		});
	});

})(jQuery);

