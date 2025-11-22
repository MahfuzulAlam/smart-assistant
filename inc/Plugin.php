<?php
/**
 * Main Plugin Class
 *
 * @package SmartAssistant
 */

namespace SmartAssistant;

/**
 * Main plugin class that initializes all components
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Admin settings instance
	 *
	 * @var Admin\Settings
	 */
	private $settings;

	/**
	 * Frontend chat widget instance
	 *
	 * @var Frontend\ChatWidget
	 */
	private $chat_widget;

	/**
	 * Frontend chat handler instance
	 *
	 * @var Frontend\ChatHandler
	 */
	private $chat_handler;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Load text domain
		load_plugin_textdomain( 'smart-assistant', false, dirname( plugin_basename( SMART_ASSISTANT_PLUGIN_FILE ) ) . '/languages' );

		// Initialize admin settings
		if ( is_admin() ) {
			$this->settings = new Admin\Settings();
			$this->settings->init();
		}

		// Initialize frontend components
		if ( ! is_admin() ) {
			$this->chat_widget = new Frontend\ChatWidget();
			$this->chat_widget->init();
		}

		// Initialize AJAX handler (works in both admin and frontend)
		$this->chat_handler = new Frontend\ChatHandler();
		$this->chat_handler->init();

		// Initialize trigger system
		$this->init_triggers();
	}

	/**
	 * Initialize trigger system
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function init_triggers(): void {
		// Load trigger registry (this will auto-register built-in triggers)
		Triggers\TriggerRegistry::get_instance();

		// Initialize admin interface for triggers
		if ( is_admin() ) {
			$trigger_manager = new Triggers\Admin\TriggerManager();
			$trigger_manager->init();
		}
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set default options
		$defaults = array(
			'api_key'           => '',
			'model'             => 'gpt-3.5-turbo',
			'max_context_posts' => 50,
			'welcome_message'   => __( 'Hi! How can I help you find information?', 'smart-assistant' ),
			'button_color'      => '#0073aa',
			'enabled'           => true,
		);

		$options = get_option( 'smart_assistant_settings', array() );
		$options = wp_parse_args( $options, $defaults );
		update_option( 'smart_assistant_settings', $options );

		// Flush rewrite rules if needed
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up transients
		delete_transient( 'smart_assistant_content_cache' );
	}
}

