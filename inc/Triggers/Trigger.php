<?php
/**
 * Abstract Trigger Base Class
 *
 * Provides common functionality for all triggers including settings management,
 * parameter validation, security, and error handling.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers;

use SmartAssistant\Triggers\Interfaces\TriggerInterface;

/**
 * Abstract class Trigger
 *
 * Base class for all triggers. Provides common functionality and enforces
 * security best practices.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
abstract class Trigger implements TriggerInterface {

	/**
	 * Trigger settings option name prefix
	 *
	 * @var string
	 */
	private $settings_prefix = 'smart_assistant_trigger_';

	/**
	 * Get trigger settings from WordPress options
	 *
	 * @return array Trigger settings array.
	 * @since 1.0.0
	 */
	protected function get_settings(): array {
		$option_name = $this->settings_prefix . $this->get_id();
		$settings    = get_option( $option_name, array() );

		// Merge with defaults from schema
		$schema = $this->get_settings_schema();
		$defaults = array();
		foreach ( $schema as $field ) {
			if ( isset( $field['default'] ) ) {
				$defaults[ $field['name'] ] = $field['default'];
			}
		}

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Save trigger settings to WordPress options
	 *
	 * @param array $settings Settings array to save.
	 * @return bool True on success, false on failure.
	 * @since 1.0.0
	 */
	protected function save_settings( array $settings ): bool {
		$option_name = $this->settings_prefix . $this->get_id();
		return update_option( $option_name, $settings );
	}

	/**
	 * Check if trigger is enabled
	 *
	 * @return bool True if enabled, false otherwise.
	 * @since 1.0.0
	 */
	protected function is_enabled(): bool {
		$settings = $this->get_settings();
		return isset( $settings['enabled'] ) ? (bool) $settings['enabled'] : true;
	}

	/**
	 * Validate and sanitize parameters
	 *
	 * @param array $params Raw parameters.
	 * @return array Sanitized parameters.
	 * @since 1.0.0
	 */
	protected function validate_params( array $params ): array {
		$required = $this->get_required_params();
		$sanitized = array();

		// Check required parameters
		foreach ( $required as $param_name ) {
			if ( ! isset( $params[ $param_name ] ) || empty( $params[ $param_name ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						/* translators: %s: Parameter name */
						__( 'Required parameter "%s" is missing.', 'smart-assistant' ),
						$param_name
					)
				);
			}
		}

		// Sanitize all parameters
		foreach ( $params as $key => $value ) {
			$sanitized[ $key ] = $this->sanitize_param( $key, $value );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single parameter based on its name
	 *
	 * @param string $key Parameter key.
	 * @param mixed  $value Parameter value.
	 * @return mixed Sanitized value.
	 * @since 1.0.0
	 */
	protected function sanitize_param( string $key, $value ) {
		// Email fields
		if ( strpos( $key, 'email' ) !== false || strpos( $key, 'mail' ) !== false ) {
			return $this->sanitize_email( $value );
		}

		// URL fields
		if ( strpos( $key, 'url' ) !== false || strpos( $key, 'link' ) !== false ) {
			return $this->sanitize_url( $value );
		}

		// Numeric fields
		if ( strpos( $key, 'id' ) !== false || strpos( $key, 'quantity' ) !== false || strpos( $key, 'count' ) !== false || strpos( $key, 'number' ) !== false ) {
			return $this->sanitize_number( $value );
		}

		// Default: text field
		return $this->sanitize_text( $value );
	}

	/**
	 * Sanitize email address
	 *
	 * @param mixed $value Email value.
	 * @return string Sanitized email or empty string.
	 * @since 1.0.0
	 */
	protected function sanitize_email( $value ): string {
		return sanitize_email( $value );
	}

	/**
	 * Sanitize URL
	 *
	 * @param mixed $value URL value.
	 * @return string Sanitized URL or empty string.
	 * @since 1.0.0
	 */
	protected function sanitize_url( $value ): string {
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize text field
	 *
	 * @param mixed $value Text value.
	 * @return string Sanitized text.
	 * @since 1.0.0
	 */
	protected function sanitize_text( $value ): string {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize textarea field
	 *
	 * @param mixed $value Textarea value.
	 * @return string Sanitized textarea content.
	 * @since 1.0.0
	 */
	protected function sanitize_textarea( $value ): string {
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitize HTML content
	 *
	 * @param mixed $value HTML value.
	 * @return string Sanitized HTML.
	 * @since 1.0.0
	 */
	protected function sanitize_html( $value ): string {
		return wp_kses_post( $value );
	}

	/**
	 * Sanitize number (positive integer)
	 *
	 * @param mixed $value Number value.
	 * @return int Sanitized positive integer.
	 * @since 1.0.0
	 */
	protected function sanitize_number( $value ): int {
		return absint( $value );
	}

	/**
	 * Execute trigger with error handling wrapper
	 *
	 * @param array $params Trigger parameters.
	 * @param array $context Execution context.
	 * @return array Response array.
	 * @since 1.0.0
	 */
	public function safe_execute( array $params, array $context ): array {
		// Check if enabled
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Trigger name */
					__( 'Trigger "%s" is disabled.', 'smart-assistant' ),
					$this->get_name()
				),
				'data'    => array(),
			);
		}

		// Check permissions
		if ( ! $this->can_execute( $context ) ) {
			$this->log( 'Permission denied for trigger execution', array( 'trigger' => $this->get_id(), 'context' => $context ) );
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to execute this action.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		try {
			// Validate parameters
			$sanitized_params = $this->validate_params( $params );

			// Execute trigger
			$result = $this->execute( $sanitized_params, $context );

			// Ensure result has required keys
			if ( ! isset( $result['success'] ) ) {
				$result['success'] = true;
			}
			if ( ! isset( $result['message'] ) ) {
				$result['message'] = __( 'Action completed successfully.', 'smart-assistant' );
			}
			if ( ! isset( $result['data'] ) ) {
				$result['data'] = array();
			}

			// Log successful execution
			$this->log_execution( $context, $sanitized_params, $result );

			return $result;

		} catch ( \InvalidArgumentException $e ) {
			$this->log( 'Parameter validation error: ' . $e->getMessage(), array( 'trigger' => $this->get_id(), 'params' => $params ) );
			return array(
				'success' => false,
				'message' => $e->getMessage(),
				'data'    => array(),
			);
		} catch ( \Exception $e ) {
			$this->log( 'Trigger execution error: ' . $e->getMessage(), array( 'trigger' => $this->get_id(), 'params' => $params, 'context' => $context ) );
			return array(
				'success' => false,
				'message' => __( 'An error occurred while executing this action. Please try again.', 'smart-assistant' ),
				'data'    => array(
					'error' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getMessage() : '',
				),
			);
		}
	}

	/**
	 * Log message (respects WP_DEBUG)
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 * @since 1.0.0
	 */
	protected function log( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$log_message = sprintf(
				'[Smart Assistant Trigger: %s] %s',
				$this->get_id(),
				$message
			);
			if ( ! empty( $context ) ) {
				$log_message .= ' | Context: ' . wp_json_encode( $context );
			}
			error_log( $log_message );
		}
	}

	/**
	 * Log trigger execution for audit trail
	 *
	 * @param array $context Execution context.
	 * @param array $params Trigger parameters.
	 * @param array $result Execution result.
	 * @return void
	 * @since 1.0.0
	 */
	protected function log_execution( array $context, array $params, array $result ): void {
		$log_entry = array(
			'trigger_id'   => $this->get_id(),
			'trigger_name' => $this->get_name(),
			'user_id'      => isset( $context['user_id'] ) ? $context['user_id'] : 0,
			'session_id'   => isset( $context['session_id'] ) ? $context['session_id'] : '',
			'ip_address'   => isset( $context['ip_address'] ) ? $context['ip_address'] : '',
			'timestamp'    => current_time( 'mysql' ),
			'params'       => $params,
			'success'      => $result['success'],
			'message'      => $result['message'],
		);

		// Store in transient (last 50 executions)
		$logs = get_transient( 'smart_assistant_trigger_logs' );
		if ( false === $logs ) {
			$logs = array();
		}

		$logs[] = $log_entry;

		// Keep only last 50
		if ( count( $logs ) > 50 ) {
			$logs = array_slice( $logs, -50 );
		}

		set_transient( 'smart_assistant_trigger_logs', $logs, DAY_IN_SECONDS );
	}

	/**
	 * Check rate limiting for trigger execution
	 *
	 * @param array $context Execution context.
	 * @return bool True if allowed, false if rate limited.
	 * @since 1.0.0
	 */
	protected function check_rate_limit( array $context ): bool {
		$session_id = isset( $context['session_id'] ) ? $context['session_id'] : 'unknown';
		$transient_key = 'smart_assistant_trigger_rate_' . $this->get_id() . '_' . $session_id;
		$executions = get_transient( $transient_key );

		if ( false === $executions ) {
			set_transient( $transient_key, 1, 60 ); // 1 minute
			return true;
		}

		if ( $executions >= 10 ) {
			return false;
		}

		set_transient( $transient_key, $executions + 1, 60 );
		return true;
	}

	/**
	 * Get post by ID with validation
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null Post object or null if not found.
	 * @since 1.0.0
	 */
	protected function get_post( int $post_id ): ?\WP_Post {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}
		return $post;
	}

	/**
	 * Get user by ID with validation
	 *
	 * @param int $user_id User ID.
	 * @return \WP_User|null User object or null if not found.
	 * @since 1.0.0
	 */
	protected function get_user( int $user_id ): ?\WP_User {
		$user = get_user_by( 'ID', $user_id );
		return $user ? $user : null;
	}
}

