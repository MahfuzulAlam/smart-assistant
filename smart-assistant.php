<?php
/**
 * Plugin Name: Smart Assistant
 * Plugin URI: https://example.com/smart-assistant
 * Description: AI-powered chatbot to help users find information from WordPress posts and pages.
 * Version: 0.0.1
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: smart-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SMART_ASSISTANT_VERSION', '0.0.1' );
define( 'SMART_ASSISTANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMART_ASSISTANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMART_ASSISTANT_PLUGIN_FILE', __FILE__ );

// Load Composer autoloader
if ( file_exists( SMART_ASSISTANT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SMART_ASSISTANT_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Fallback autoloader if Composer hasn't been run
	spl_autoload_register( function ( $class ) {
		$prefix = 'SmartAssistant\\';
		$base_dir = SMART_ASSISTANT_PLUGIN_DIR . 'inc/';
		
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
		
		$relative_class = substr( $class, $len );
		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
		
		if ( file_exists( $file ) ) {
			require $file;
		}
	} );
}

// Initialize the plugin
function smart_assistant_init() {
	$plugin = new SmartAssistant\Plugin();
	$plugin->init();
}

// Hook into WordPress
add_action( 'plugins_loaded', 'smart_assistant_init' );

// Activation hook
register_activation_hook( __FILE__, function () {
	if ( class_exists( 'SmartAssistant\\Plugin' ) ) {
		$plugin = new SmartAssistant\Plugin();
		$plugin->activate();
	}
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
	if ( class_exists( 'SmartAssistant\\Plugin' ) ) {
		$plugin = new SmartAssistant\Plugin();
		$plugin->deactivate();
	}
} );

