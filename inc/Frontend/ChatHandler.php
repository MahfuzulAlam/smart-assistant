<?php
/**
 * Chat Handler for AJAX Requests
 *
 * @package SmartAssistant
 */

namespace SmartAssistant\Frontend;

use SmartAssistant\API\OpenAIClient;
use SmartAssistant\Data\ContentRetriever;
use SmartAssistant\Triggers\TriggerRegistry;

/**
 * Chat handler class for processing AJAX requests
 */
class ChatHandler {

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAIClient
	 */
	private $openai_client;

	/**
	 * Content retriever instance
	 *
	 * @var ContentRetriever
	 */
	private $content_retriever;

	/**
	 * Initialize the chat handler
	 */
	public function init() {
		$this->openai_client     = new OpenAIClient();
		$this->content_retriever = new ContentRetriever();

		add_action( 'wp_ajax_smart_assistant_chat', array( $this, 'handle_chat_request' ) );
		add_action( 'wp_ajax_nopriv_smart_assistant_chat', array( $this, 'handle_chat_request' ) );
	}

	/**
	 * Handle chat AJAX request
	 */
	public function handle_chat_request() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'smart_assistant_chat_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smart-assistant' ) ) );
		}

		// Check rate limiting
		if ( ! $this->check_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a moment before sending another message.', 'smart-assistant' ) ) );
		}

		// Get user message
		$user_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( empty( $user_message ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'smart-assistant' ) ) );
		}

		// Get chat history
		$history = isset( $_POST['history'] ) ? json_decode( wp_unslash( $_POST['history'] ), true ) : array();
		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Sanitize history
		$history = $this->sanitize_history( $history );

		// Get WordPress content for context
		$content_context = $this->content_retriever->get_content_for_context();

		// Build messages array for OpenAI
		$messages = $this->build_messages( $user_message, $history, $content_context );

		error_log( json_encode( $messages ) );

		// Send to OpenAI
		$response = $this->openai_client->send_chat_message( $messages );

		if ( is_wp_error( $response ) ) {
			error_log( 'Smart Assistant Error: ' . $response->get_error_message() );
			//wp_send_json_error( array( 'message' => __( 'Sorry, I\'m having trouble connecting. Please try again later.', 'smart-assistant' ) ) );
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Initialize trigger registry
		$trigger_registry = TriggerRegistry::get_instance();

		// Build execution context
		$context = array(
			'user_id'            => get_current_user_id(),
			'user_message'       => $user_message,
			'conversation_history' => $history,
			'timestamp'          => current_time( 'mysql' ),
			'session_id'         => $this->get_session_id(),
			'ip_address'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		// Parse and execute triggers from AI response
		$trigger_results = $trigger_registry->parse_and_execute( $response, $context );

		// Strip trigger commands from display message
		$clean_message = $trigger_registry->strip_commands( $response );

		// Return success response with trigger results
		wp_send_json_success(
			array(
				'message'          => $clean_message,
				'original_message' => $response,
				'triggers_executed' => $trigger_results,
				'timestamp'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Build messages array for OpenAI API
	 *
	 * @param string $user_message User's current message.
	 * @param array  $history Chat history.
	 * @param array  $content_context WordPress content context.
	 * @return array Messages array
	 */
	private function build_messages( $user_message, $history, $content_context ) {
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		// Build system prompt
		$content_text = '';
		if ( ! empty( $content_context ) ) {
			$content_text = "\n\n=== AVAILABLE CONTENT FROM THE WEBSITE ===\n\n";
			foreach ( $content_context as $index => $item ) {
				$content_text .= sprintf(
					"[Post %d]\nTitle: %s\nContent: %s\n\n",
					$index + 1,
					$item['title'],
					$item['content']
				);
			}
			$content_text .= "=== END OF CONTENT ===\n";
		}

		$system_prompt = sprintf(
			'You are a helpful assistant for %s (%s). Your role is to answer questions ONLY using the content provided below from this website.

CRITICAL INSTRUCTIONS - READ CAREFULLY:
1. The content below is the SOURCE OF TRUTH - always prioritize it over any previous conversation history
2. Search through ALL content below case-insensitively (ignore capitalization differences like "PaikarClud" vs "paikarclub")
3. If the user asks about something that appears in ANY form (different capitalization, partial match, similar spelling, or variations) in the content below, you MUST provide an answer based on that content
4. Look for keywords, phrases, and related terms - be flexible and intelligent with matching
5. If you previously said information was not available but it actually exists in the content below, CORRECT YOURSELF and provide the correct answer
6. Only say information is not available if you have thoroughly searched ALL posts and the information is genuinely not present
7. When you find the information, cite the post title it came from

Be concise, friendly, and helpful.%s',
			$site_name,
			$site_url,
			$content_text
		);

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
		);

		// Add history (limit to last 10 messages to avoid token limits)
		$recent_history = array_slice( $history, -10 );
		foreach ( $recent_history as $msg ) {
			if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
				$messages[] = array(
					'role'    => sanitize_text_field( $msg['role'] ),
					'content' => sanitize_textarea_field( $msg['content'] ),
				);
			}
		}

		// Add current user message
		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		return $messages;
	}

	/**
	 * Sanitize chat history
	 *
	 * @param array $history Raw history array.
	 * @return array Sanitized history
	 */
	private function sanitize_history( $history ) {
		$sanitized = array();
		foreach ( $history as $msg ) {
			if ( isset( $msg['role'] ) && isset( $msg['content'] ) ) {
				$sanitized[] = array(
					'role'    => sanitize_text_field( $msg['role'] ),
					'content' => sanitize_textarea_field( $msg['content'] ),
				);
			}
		}
		return $sanitized;
	}

	/**
	 * Check rate limiting (max 10 requests per minute per session)
	 *
	 * @return bool True if allowed, false if rate limited
	 */
	private function check_rate_limit() {
		$session_id = $this->get_session_id();
		$transient_key = 'smart_assistant_rate_limit_' . $session_id;
		$requests = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, 60 ); // 1 minute
			return true;
		}

		if ( $requests >= 10 ) {
			return false;
		}

		set_transient( $transient_key, $requests + 1, 60 );
		return true;
	}

	/**
	 * Get session ID
	 *
	 * @return string Session ID
	 */
	private function get_session_id() {
		// Use IP address and user agent as session identifier
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		return md5( $ip . $ua );
	}
}

