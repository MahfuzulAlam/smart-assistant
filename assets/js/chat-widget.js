/**
 * Frontend Chat Widget JavaScript
 */
(function($) {
	'use strict';

	// Chat widget state
	const ChatWidget = {
		isOpen: false,
		history: [],
		rateLimitCheck: true,

		init: function() {
			this.bindEvents();
			this.loadHistory();
			this.showWelcomeMessage();
		},

		bindEvents: function() {
			const self = this;

			// Toggle chat window
			$('#smart-assistant-button').on('click', function() {
				self.toggleChat();
			});

			// Close chat
			$('#smart-assistant-close').on('click', function() {
				self.closeChat();
			});

			// Send message on button click
			$('#smart-assistant-send').on('click', function() {
				self.sendMessage();
			});

			// Send message on Enter key
			$('#smart-assistant-input').on('keypress', function(e) {
				if (e.which === 13 && !e.shiftKey) {
					e.preventDefault();
					self.sendMessage();
				}
			});

			// Close on outside click (optional)
			$(document).on('click', function(e) {
				if (self.isOpen && !$(e.target).closest('.smart-assistant-widget').length) {
					// Uncomment to close on outside click
					// self.closeChat();
				}
			});
		},

		toggleChat: function() {
			if (this.isOpen) {
				this.closeChat();
			} else {
				this.openChat();
			}
		},

		openChat: function() {
			this.isOpen = true;
			$('#smart-assistant-chat').slideDown(300);
			$('#smart-assistant-input').focus();
			this.scrollToBottom();
		},

		closeChat: function() {
			this.isOpen = false;
			$('#smart-assistant-chat').slideUp(300);
		},

		showWelcomeMessage: function() {
			if (typeof smartAssistant !== 'undefined' && smartAssistant.welcomeMessage) {
				this.addMessage('ai', smartAssistant.welcomeMessage);
			}
		},

		sendMessage: function() {
			const input = $('#smart-assistant-input');
			const message = input.val().trim();

			if (!message) {
				return;
			}

			// Disable input and send button
			input.prop('disabled', true);
			$('#smart-assistant-send').prop('disabled', true);

			// Add user message to chat
			this.addMessage('user', message);
			this.history.push({
				role: 'user',
				content: message
			});
			this.saveHistory();

			// Clear input
			input.val('');

			// Show loading indicator
			this.showLoading();

			// Send AJAX request
			$.ajax({
				url: smartAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'smart_assistant_chat',
					nonce: smartAssistant.nonce,
					message: message,
					history: JSON.stringify(this.history)
				},
				success: (response) => {
					this.hideLoading();

					if (response.success && response.data.response) {
						this.addMessage('ai', response.data.response);
						this.history.push({
							role: 'assistant',
							content: response.data.response
						});
						this.saveHistory();
					} else {
						const errorMsg = response.data && response.data.message 
							? response.data.message 
							: smartAssistant.strings.error;
						this.addMessage('ai', errorMsg, true);
					}
				},
				error: () => {
					this.hideLoading();
					this.addMessage('ai', smartAssistant.strings.error, true);
				},
				complete: () => {
					// Re-enable input and send button
					input.prop('disabled', false);
					$('#smart-assistant-send').prop('disabled', false);
					input.focus();
				}
			});
		},

		addMessage: function(role, content, isError = false) {
			const messagesContainer = $('#smart-assistant-messages');
			const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			
			const messageClass = isError ? 'ai error' : role;
			const messageHtml = `
				<div class="smart-assistant-message ${messageClass}">
					<div class="smart-assistant-message-content">${this.escapeHtml(content)}</div>
					<div class="smart-assistant-message-time">${timestamp}</div>
				</div>
			`;

			messagesContainer.append(messageHtml);
			this.scrollToBottom();
		},

		showLoading: function() {
			const messagesContainer = $('#smart-assistant-messages');
			const loadingHtml = `
				<div class="smart-assistant-loading" id="smart-assistant-loading">
					<div class="smart-assistant-loading-dots">
						<div class="smart-assistant-loading-dot"></div>
						<div class="smart-assistant-loading-dot"></div>
						<div class="smart-assistant-loading-dot"></div>
					</div>
					<span>${smartAssistant.strings.sending}</span>
				</div>
			`;
			messagesContainer.append(loadingHtml);
			this.scrollToBottom();
		},

		hideLoading: function() {
			$('#smart-assistant-loading').remove();
		},

		scrollToBottom: function() {
			const messagesContainer = $('#smart-assistant-messages');
			messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
		},

		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		},

		saveHistory: function() {
			// Store in sessionStorage (limited to session)
			try {
				sessionStorage.setItem('smart_assistant_history', JSON.stringify(this.history));
			} catch (e) {
				// Fallback if sessionStorage is not available
				console.warn('Could not save chat history');
			}
		},

		loadHistory: function() {
			// Load from sessionStorage
			try {
				const saved = sessionStorage.getItem('smart_assistant_history');
				if (saved) {
					this.history = JSON.parse(saved);
					// Optionally restore messages to UI
					// This would require storing message timestamps too
				}
			} catch (e) {
				console.warn('Could not load chat history');
			}
		},

		clearHistory: function() {
			this.history = [];
			this.saveHistory();
			$('#smart-assistant-messages').empty();
			this.showWelcomeMessage();
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		if (typeof smartAssistant !== 'undefined') {
			ChatWidget.init();
		}
	});

	// Add clear chat functionality
	$(document).on('click', '.smart-assistant-clear', function(e) {
		e.preventDefault();
		if (confirm('Clear chat history?')) {
			ChatWidget.clearHistory();
		}
	});

})(jQuery);

