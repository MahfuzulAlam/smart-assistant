<?php
/**
 * Frontend Chat Widget
 *
 * @package AIAssistant
 */

namespace AIAssistant\Frontend;

/**
 * Chat widget class for frontend display
 */
class ChatWidget {

	/**
	 * Initialize the chat widget
	 */
	public function init() {
		// Check if enabled
		$settings = get_option( 'smart_assistant_settings', array() );
		if ( isset( $settings['enabled'] ) && ! $settings['enabled'] ) {
			return;
		}

		// Check if API key is set
		if ( empty( $settings['api_key'] ) ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'smart-assistant-frontend',
			SMART_ASSISTANT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SMART_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			'smart-assistant-chat-widget',
			SMART_ASSISTANT_PLUGIN_URL . 'assets/js/chat-widget.js',
			array( 'jquery' ),
			SMART_ASSISTANT_VERSION,
			true
		);

		$settings = get_option( 'smart_assistant_settings', array() );
		$welcome_message = isset( $settings['welcome_message'] ) ? $settings['welcome_message'] : __( 'Hi! How can I help you find information?', 'smart-assistant' );
		$button_color = isset( $settings['button_color'] ) ? $settings['button_color'] : '#0073aa';

		wp_localize_script(
			'smart-assistant-chat-widget',
			'smartAssistant',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'smart_assistant_chat_nonce' ),
				'welcomeMessage' => $welcome_message,
				'buttonColor'    => $button_color,
				'strings'        => array(
					'sending'   => __( 'Sending...', 'smart-assistant' ),
					'error'     => __( 'Sorry, I\'m having trouble connecting. Please try again later.', 'smart-assistant' ),
					'noContent' => __( 'I couldn\'t find information about that.', 'smart-assistant' ),
					'rateLimit' => __( 'Please wait a moment before sending another message.', 'smart-assistant' ),
					'clearChat' => __( 'Clear Chat', 'smart-assistant' ),
				),
			)
		);
	}

	/**
	 * Render chat widget HTML
	 */
	public function render_widget() {
		$settings = get_option( 'smart_assistant_settings', array() );
		$button_color = isset( $settings['button_color'] ) ? $settings['button_color'] : '#0073aa';
		?>
		<div id="smart-assistant-widget" class="smart-assistant-widget">
			<!-- Chat Button -->
			<button id="smart-assistant-button" class="smart-assistant-button" aria-label="<?php esc_attr_e( 'Open Smart Assistant', 'smart-assistant' ); ?>" style="background-color: <?php echo esc_attr( $button_color ); ?>;">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z" fill="currentColor"/>
				</svg>
			</button>

			<!-- Chat Window -->
			<div id="smart-assistant-chat" class="smart-assistant-chat" style="display: none;">
				<div class="smart-assistant-chat-header">
					<h3><?php esc_html_e( 'Smart Assistant', 'smart-assistant' ); ?></h3>
					<div style="display: flex; gap: 8px;">
						<button class="smart-assistant-clear" aria-label="<?php esc_attr_e( 'Clear chat', 'smart-assistant' ); ?>" title="<?php esc_attr_e( 'Clear chat', 'smart-assistant' ); ?>">
							<?php esc_html_e( 'Clear', 'smart-assistant' ); ?>
						</button>
						<button id="smart-assistant-close" class="smart-assistant-close" aria-label="<?php esc_attr_e( 'Close chat', 'smart-assistant' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="currentColor"/>
							</svg>
						</button>
					</div>
				</div>
				<div id="smart-assistant-messages" class="smart-assistant-messages">
					<!-- Messages will be inserted here via JavaScript -->
				</div>
				<div class="smart-assistant-input-container">
					<input type="text" id="smart-assistant-input" class="smart-assistant-input" placeholder="<?php esc_attr_e( 'Type your message...', 'smart-assistant' ); ?>" />
					<button id="smart-assistant-send" class="smart-assistant-send" aria-label="<?php esc_attr_e( 'Send message', 'smart-assistant' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" fill="currentColor"/>
						</svg>
					</button>
				</div>
				<div class="smart-assistant-footer">
					<p><?php esc_html_e( 'Powered by OpenAI', 'smart-assistant' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}

