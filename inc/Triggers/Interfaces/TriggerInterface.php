<?php
/**
 * Trigger Interface
 *
 * Defines the contract that all triggers must implement.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers\Interfaces;

/**
 * Interface TriggerInterface
 *
 * All triggers must implement this interface to be registered with the system.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
interface TriggerInterface {

	/**
	 * Get unique trigger identifier
	 *
	 * @return string Unique trigger ID (e.g., 'email_post_author')
	 * @since 1.0.0
	 */
	public function get_id(): string;

	/**
	 * Get human-readable trigger name
	 *
	 * @return string Display name (e.g., 'Email Post Author')
	 * @since 1.0.0
	 */
	public function get_name(): string;

	/**
	 * Get trigger description
	 *
	 * @return string What the trigger does
	 * @since 1.0.0
	 */
	public function get_description(): string;

	/**
	 * Get regex pattern to match command in AI response
	 *
	 * @return string Regex pattern (e.g., '/\[EMAIL_AUTHOR:([^:]+):([^:]+):([^\]]+)\]/i')
	 * @since 1.0.0
	 */
	public function get_command_pattern(): string;

	/**
	 * Execute the trigger action
	 *
	 * @param array $params Extracted parameters from command.
	 * @param array $context Execution context (user_id, session_id, etc.).
	 * @return array Response array with 'success', 'message', and 'data' keys.
	 * @since 1.0.0
	 */
	public function execute( array $params, array $context ): array;

	/**
	 * Check if trigger can be executed with given context
	 *
	 * @param array $context Execution context.
	 * @return bool True if allowed, false otherwise.
	 * @since 1.0.0
	 */
	public function can_execute( array $context ): bool;

	/**
	 * Get required parameter names
	 *
	 * @return array Array of required parameter names (e.g., ['post_id', 'subject', 'message']).
	 * @since 1.0.0
	 */
	public function get_required_params(): array;

	/**
	 * Get settings schema for admin interface
	 *
	 * @return array Settings configuration array.
	 * @since 1.0.0
	 */
	public function get_settings_schema(): array;
}

