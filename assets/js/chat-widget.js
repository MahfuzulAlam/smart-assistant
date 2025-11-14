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
			$('#ai-assistant-button').on('click', function() {
				self.toggleChat();
			});

			// Close chat
			$('#ai-assistant-close').on('click', function() {
				self.closeChat();
			});

			// Send message on button click
			$('#ai-assistant-send').on('click', function() {
				self.sendMessage();
			});

			// Send message on Enter key
			$('#ai-assistant-input').on('keypress', function(e) {
				if (e.which === 13 && !e.shiftKey) {
					e.preventDefault();
					self.sendMessage();
				}
			});

			// Close on outside click (optional)
			$(document).on('click', function(e) {
				if (self.isOpen && !$(e.target).closest('.ai-assistant-widget').length) {
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
			$('#ai-assistant-chat').slideDown(300);
			$('#ai-assistant-input').focus();
			this.scrollToBottom();
		},

		closeChat: function() {
			this.isOpen = false;
			$('#ai-assistant-chat').slideUp(300);
		},

		showWelcomeMessage: function() {
			if (typeof aiAssistant !== 'undefined' && aiAssistant.welcomeMessage) {
				this.addMessage('ai', aiAssistant.welcomeMessage);
			}
		},

		sendMessage: function() {
			const input = $('#ai-assistant-input');
			const message = input.val().trim();

			if (!message) {
				return;
			}

			// Disable input and send button
			input.prop('disabled', true);
			$('#ai-assistant-send').prop('disabled', true);

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
				url: aiAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ai_assistant_chat',
					nonce: aiAssistant.nonce,
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
							: aiAssistant.strings.error;
						this.addMessage('ai', errorMsg, true);
					}
				},
				error: () => {
					this.hideLoading();
					this.addMessage('ai', aiAssistant.strings.error, true);
				},
				complete: () => {
					// Re-enable input and send button
					input.prop('disabled', false);
					$('#ai-assistant-send').prop('disabled', false);
					input.focus();
				}
			});
		},

		addMessage: function(role, content, isError = false) {
			const messagesContainer = $('#ai-assistant-messages');
			const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			
			const messageClass = isError ? 'ai error' : role;
			const messageHtml = `
				<div class="ai-assistant-message ${messageClass}">
					<div class="ai-assistant-message-content">${this.escapeHtml(content)}</div>
					<div class="ai-assistant-message-time">${timestamp}</div>
				</div>
			`;

			messagesContainer.append(messageHtml);
			this.scrollToBottom();
		},

		showLoading: function() {
			const messagesContainer = $('#ai-assistant-messages');
			const loadingHtml = `
				<div class="ai-assistant-loading" id="ai-assistant-loading">
					<div class="ai-assistant-loading-dots">
						<div class="ai-assistant-loading-dot"></div>
						<div class="ai-assistant-loading-dot"></div>
						<div class="ai-assistant-loading-dot"></div>
					</div>
					<span>${aiAssistant.strings.sending}</span>
				</div>
			`;
			messagesContainer.append(loadingHtml);
			this.scrollToBottom();
		},

		hideLoading: function() {
			$('#ai-assistant-loading').remove();
		},

		scrollToBottom: function() {
			const messagesContainer = $('#ai-assistant-messages');
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
				sessionStorage.setItem('ai_assistant_history', JSON.stringify(this.history));
			} catch (e) {
				// Fallback if sessionStorage is not available
				console.warn('Could not save chat history');
			}
		},

		loadHistory: function() {
			// Load from sessionStorage
			try {
				const saved = sessionStorage.getItem('ai_assistant_history');
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
			$('#ai-assistant-messages').empty();
			this.showWelcomeMessage();
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		if (typeof aiAssistant !== 'undefined') {
			ChatWidget.init();
		}
	});

	// Add clear chat functionality
	$(document).on('click', '.ai-assistant-clear', function(e) {
		e.preventDefault();
		if (confirm('Clear chat history?')) {
			ChatWidget.clearHistory();
		}
	});

})(jQuery);

