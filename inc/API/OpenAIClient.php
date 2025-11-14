<?php
/**
 * OpenAI API Client
 *
 * @package AIAssistant
 */

namespace AIAssistant\API;

use AIAssistant\Admin\Settings;

/**
 * OpenAI API client class
 */
class OpenAIClient {

	/**
	 * OpenAI API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Get API key from settings
	 *
	 * @return string API key
	 */
	private function get_api_key() {
		$settings = get_option( 'ai_assistant_settings', array() );
		if ( empty( $settings['api_key'] ) ) {
			return '';
		}
		return Settings::decrypt_api_key( $settings['api_key'] );
	}

	/**
	 * Get model from settings
	 *
	 * @return string Model name
	 */
	private function get_model() {
		$settings = get_option( 'ai_assistant_settings', array() );
		return isset( $settings['model'] ) ? $settings['model'] : 'gpt-3.5-turbo';
	}

	/**
	 * Send chat message to OpenAI API
	 *
	 * @param array $messages Messages array with role and content.
	 * @return string|WP_Error Response text or error
	 */
	public function send_chat_message( $messages ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'ai-assistant' ) );
		}

		$model = $this->get_model();

		$body = array(
			'model'    => $model,
			'messages' => $messages,
			'max_tokens' => 500, // Limit response length
			'temperature' => 0.7,
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		);

		$response = wp_remote_post( $this->api_endpoint, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'AI Assistant API Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : __( 'API request failed.', 'ai-assistant' );
			error_log( 'AI Assistant API Error: ' . $error_message );
			return new \WP_Error( 'api_error', $error_message );
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid response from API.', 'ai-assistant' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}
}

