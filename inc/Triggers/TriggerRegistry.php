<?php
/**
 * Trigger Registry
 *
 * Singleton class that manages all registered triggers and handles
 * parsing and execution of trigger commands from AI responses.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers;

use SmartAssistant\Triggers\Interfaces\TriggerInterface;

/**
 * Class TriggerRegistry
 *
 * Manages trigger registration and execution.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
class TriggerRegistry {

	/**
	 * Singleton instance
	 *
	 * @var TriggerRegistry|null
	 */
	private static $instance = null;

	/**
	 * Registered triggers
	 *
	 * @var TriggerInterface[]
	 */
	private $triggers = array();

	/**
	 * Get singleton instance
	 *
	 * @return TriggerRegistry
	 * @since 1.0.0
	 */
	public static function get_instance(): TriggerRegistry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_built_in_triggers();
		$this->load_third_party_triggers();
	}

	/**
	 * Load built-in triggers
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function load_built_in_triggers(): void {
		// Email Post Author trigger (always available)
		$this->register( new BuiltIn\EmailPostAuthorTrigger() );

		// WooCommerce triggers (only if WooCommerce is active)
		if ( $this->is_woocommerce_active() ) {
			$this->register( new BuiltIn\AddToCartTrigger() );
			$this->register( new BuiltIn\ShowProductsTrigger() );
		}
	}

	/**
	 * Load third-party triggers via WordPress hooks
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function load_third_party_triggers(): void {
		/**
		 * Allow third-party plugins to register custom triggers
		 *
		 * @param TriggerRegistry $registry The trigger registry instance.
		 * @since 1.0.0
		 */
		do_action( 'smart_assistant_register_triggers', $this );
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool True if WooCommerce is installed and active.
	 * @since 1.0.0
	 */
	private function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Register a trigger
	 *
	 * @param TriggerInterface $trigger Trigger instance to register.
	 * @return bool True on success, false if trigger ID already exists.
	 * @since 1.0.0
	 */
	public function register( TriggerInterface $trigger ): bool {
		$trigger_id = $trigger->get_id();

		if ( isset( $this->triggers[ $trigger_id ] ) ) {
			return false;
		}

		$this->triggers[ $trigger_id ] = $trigger;

		/**
		 * Fires after a trigger is registered
		 *
		 * @param TriggerInterface $trigger The registered trigger instance.
		 * @since 1.0.0
		 */
		do_action( 'smart_assistant_trigger_registered', $trigger );

		return true;
	}

	/**
	 * Unregister a trigger
	 *
	 * @param string $trigger_id Trigger ID to unregister.
	 * @return bool True on success, false if trigger not found.
	 * @since 1.0.0
	 */
	public function unregister( string $trigger_id ): bool {
		if ( ! isset( $this->triggers[ $trigger_id ] ) ) {
			return false;
		}

		unset( $this->triggers[ $trigger_id ] );

		/**
		 * Fires after a trigger is unregistered
		 *
		 * @param string $trigger_id The unregistered trigger ID.
		 * @since 1.0.0
		 */
		do_action( 'smart_assistant_trigger_unregistered', $trigger_id );

		return true;
	}

	/**
	 * Get all registered triggers
	 *
	 * @return TriggerInterface[] Array of trigger instances.
	 * @since 1.0.0
	 */
	public function get_all(): array {
		/**
		 * Filter the list of registered triggers
		 *
		 * @param TriggerInterface[] $triggers Array of trigger instances.
		 * @since 1.0.0
		 */
		return apply_filters( 'smart_assistant_get_triggers', $this->triggers );
	}

	/**
	 * Get a specific trigger by ID
	 *
	 * @param string $trigger_id Trigger ID.
	 * @return TriggerInterface|null Trigger instance or null if not found.
	 * @since 1.0.0
	 */
	public function get( string $trigger_id ): ?TriggerInterface {
		$triggers = $this->get_all();
		return isset( $triggers[ $trigger_id ] ) ? $triggers[ $trigger_id ] : null;
	}

	/**
	 * Parse AI response for trigger commands and execute them
	 *
	 * @param string $ai_response AI response text.
	 * @param array  $context Execution context.
	 * @return array Execution results array.
	 * @since 1.0.0
	 */
	public function parse_and_execute( string $ai_response, array $context ): array {
		$results = array();
		$triggers = $this->get_all();

		foreach ( $triggers as $trigger ) {
			$pattern = $trigger->get_command_pattern();
			$matches = array();

			if ( preg_match_all( $pattern, $ai_response, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					// Extract parameters from match
					$params = $this->extract_params( $match, $trigger );

					// Execute trigger
					$result = $trigger->safe_execute( $params, $context );

					$results[] = array(
						'trigger_id'   => $trigger->get_id(),
						'trigger_name' => $trigger->get_name(),
						'success'      => $result['success'],
						'message'      => $result['message'],
						'data'         => $result['data'],
					);
				}
			}
		}

		return $results;
	}

	/**
	 * Extract parameters from regex match
	 *
	 * @param array            $match Regex match array.
	 * @param TriggerInterface $trigger Trigger instance.
	 * @return array Extracted parameters.
	 * @since 1.0.0
	 */
	private function extract_params( array $match, TriggerInterface $trigger ): array {
		$required_params = $trigger->get_required_params();
		$params = array();

		// Skip first match (full match), use capture groups
		for ( $i = 0; $i < count( $required_params ); $i++ ) {
			$param_index = $i + 1; // First capture group is index 1
			if ( isset( $match[ $param_index ] ) ) {
				$params[ $required_params[ $i ] ] = $match[ $param_index ];
			}
		}

		return $params;
	}

	/**
	 * Remove trigger commands from text
	 *
	 * @param string $text Text containing commands.
	 * @return string Text with commands removed.
	 * @since 1.0.0
	 */
	public function strip_commands( string $text ): string {
		$triggers = $this->get_all();
		$cleaned = $text;

		foreach ( $triggers as $trigger ) {
			$pattern = $trigger->get_command_pattern();
			$cleaned = preg_replace( $pattern, '', $cleaned );
		}

		// Clean up extra whitespace
		$cleaned = preg_replace( '/\s+/', ' ', $cleaned );
		$cleaned = trim( $cleaned );

		return $cleaned;
	}
}

