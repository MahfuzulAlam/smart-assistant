<?php
/**
 * Chat Handler for AJAX Requests
 *
 * @package AIAssistant
 */

namespace AIAssistant\Frontend;

use AIAssistant\API\OpenAIClient;
use AIAssistant\Data\ContentRetriever;

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

		// Send to OpenAI
		$response = $this->openai_client->send_chat_message( $messages );

		if ( is_wp_error( $response ) ) {
			error_log( 'Smart Assistant Error: ' . $response->get_error_message() );
			//wp_send_json_error( array( 'message' => __( 'Sorry, I\'m having trouble connecting. Please try again later.', 'smart-assistant' ) ) );
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Return success response
		wp_send_json_success(
			array(
				'response'   => $response,
				'timestamp'  => current_time( 'mysql' ),
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
			$content_text = "\n\nAvailable content:\n";
			foreach ( $content_context as $item ) {
				$content_text .= sprintf(
					"\nTitle: %s\nExcerpt: %s\n",
					$item['title'],
					$item['excerpt']
				);
			}
		}

		$system_prompt = sprintf(
			'You are a helpful assistant for %s (%s). You can only answer questions based on the following content from this website. If the user asks something not in the provided content, politely say you can only help with information from this website. Be concise, friendly, and helpful.%s',
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

