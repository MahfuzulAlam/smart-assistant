<?php
/**
 * Admin Settings Page
 *
 * @package AIAssistant
 */

namespace AIAssistant\Admin;

/**
 * Admin settings page class
 */
class Settings {

	/**
	 * Option group name
	 *
	 * @var string
	 */
	private $option_group = 'ai_assistant_settings';

	/**
	 * Option name
	 *
	 * @var string
	 */
	private $option_name = 'ai_assistant_settings';

	/**
	 * Initialize settings page
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add settings page to WordPress admin menu
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'AI Assistant Settings', 'ai-assistant' ),
			__( 'AI Assistant', 'ai-assistant' ),
			'manage_options',
			'ai-assistant-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings using WordPress Settings API
	 */
	public function register_settings() {
		register_setting(
			$this->option_group,
			$this->option_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// General Settings Section
		add_settings_section(
			'ai_assistant_general',
			__( 'General Settings', 'ai-assistant' ),
			array( $this, 'render_general_section' ),
			'ai-assistant-settings'
		);

		// API Settings Section
		add_settings_section(
			'ai_assistant_api',
			__( 'API Settings', 'ai-assistant' ),
			array( $this, 'render_api_section' ),
			'ai-assistant-settings'
		);

		// Appearance Settings Section
		add_settings_section(
			'ai_assistant_appearance',
			__( 'Appearance Settings', 'ai-assistant' ),
			array( $this, 'render_appearance_section' ),
			'ai-assistant-settings'
		);

		// Enable/Disable
		add_settings_field(
			'enabled',
			__( 'Enable AI Assistant', 'ai-assistant' ),
			array( $this, 'render_enabled_field' ),
			'ai-assistant-settings',
			'ai_assistant_general'
		);

		// Welcome Message
		add_settings_field(
			'welcome_message',
			__( 'Welcome Message', 'ai-assistant' ),
			array( $this, 'render_welcome_message_field' ),
			'ai-assistant-settings',
			'ai_assistant_general'
		);

		// API Key
		add_settings_field(
			'api_key',
			__( 'OpenAI API Key', 'ai-assistant' ),
			array( $this, 'render_api_key_field' ),
			'ai-assistant-settings',
			'ai_assistant_api'
		);

		// Model Selection
		add_settings_field(
			'model',
			__( 'Model', 'ai-assistant' ),
			array( $this, 'render_model_field' ),
			'ai-assistant-settings',
			'ai_assistant_api'
		);

		// Max Context Posts
		add_settings_field(
			'max_context_posts',
			__( 'Max Context Posts', 'ai-assistant' ),
			array( $this, 'render_max_context_posts_field' ),
			'ai-assistant-settings',
			'ai_assistant_api'
		);

		// Button Color
		add_settings_field(
			'button_color',
			__( 'Button Color', 'ai-assistant' ),
			array( $this, 'render_button_color_field' ),
			'ai-assistant-settings',
			'ai_assistant_appearance'
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $input['enabled'];
		}

		if ( isset( $input['welcome_message'] ) ) {
			$sanitized['welcome_message'] = sanitize_textarea_field( $input['welcome_message'] );
		}

		if ( isset( $input['api_key'] ) ) {
			$api_key = sanitize_text_field( $input['api_key'] );
			// Trim whitespace from API key
			$api_key_trimmed = trim( $api_key );
			
			// Simple validation: OpenAI API keys start with sk- and have minimum length
			// Very lenient validation - just check prefix and minimum length
			// Actual validation will happen when API is called, so we don't block valid keys
			if ( ! empty( $api_key_trimmed ) ) {
				// Check if it starts with "sk-" and has at least 20 characters total
				// Allow alphanumeric, underscores, hyphens, and dots (some keys may have these)
				if ( ! preg_match( '/^sk-[a-zA-Z0-9._-]{17,}$/', $api_key_trimmed ) ) {
					// If basic format check fails, still allow it but show a warning
					// The API will validate it when used
					if ( substr( $api_key_trimmed, 0, 3 ) !== 'sk-' || strlen( $api_key_trimmed ) < 20 ) {
						add_settings_error(
							$this->option_name,
							'invalid_api_key',
							__( 'Warning: API key format may be invalid. OpenAI API keys should start with "sk-" and be at least 20 characters long. The key will be validated when used.', 'ai-assistant' ),
							'warning'
						);
					}
				}
				// Always save the key (let OpenAI API validate it when used)
				$sanitized['api_key'] = $this->encrypt_api_key( $api_key_trimmed );
			} else {
				// Empty key is allowed (to clear the setting)
				$sanitized['api_key'] = '';
			}
		}

		if ( isset( $input['model'] ) ) {
			$allowed_models = array( 'gpt-3.5-turbo', 'gpt-4' );
			$sanitized['model'] = in_array( $input['model'], $allowed_models, true ) ? $input['model'] : 'gpt-3.5-turbo';
		}

		if ( isset( $input['max_context_posts'] ) ) {
			$sanitized['max_context_posts'] = absint( $input['max_context_posts'] );
			if ( $sanitized['max_context_posts'] < 1 || $sanitized['max_context_posts'] > 200 ) {
				$sanitized['max_context_posts'] = 50;
			}
		}

		if ( isset( $input['button_color'] ) ) {
			$sanitized['button_color'] = sanitize_hex_color( $input['button_color'] );
		}

		return $sanitized;
	}

	/**
	 * Encrypt API key
	 *
	 * @param string $api_key API key to encrypt.
	 * @return string Encrypted API key
	 */
	private function encrypt_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		$salt = wp_salt();
		$encrypted = base64_encode( $api_key . $salt );
		return $encrypted;
	}

	/**
	 * Decrypt API key
	 *
	 * @param string $encrypted_api_key Encrypted API key.
	 * @return string Decrypted API key
	 */
	public static function decrypt_api_key( $encrypted_api_key ) {
		if ( empty( $encrypted_api_key ) ) {
			return '';
		}

		$salt = wp_salt();
		$decoded = base64_decode( $encrypted_api_key, true );
		if ( false === $decoded ) {
			return '';
		}

		$api_key = str_replace( $salt, '', $decoded );
		return $api_key;
	}

	/**
	 * Get settings
	 *
	 * @return array Settings array
	 */
	public function get_settings() {
		return get_option( $this->option_name, array() );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( $this->option_group );
				do_settings_sections( 'ai-assistant-settings' );
				submit_button( __( 'Save Settings', 'ai-assistant' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general settings for the AI Assistant.', 'ai-assistant' ) . '</p>';
	}

	/**
	 * Render API section
	 */
	public function render_api_section() {
		echo '<p>' . esc_html__( 'Configure OpenAI API settings.', 'ai-assistant' ) . '</p>';
	}

	/**
	 * Render appearance section
	 */
	public function render_appearance_section() {
		echo '<p>' . esc_html__( 'Customize the appearance of the chat widget.', 'ai-assistant' ) . '</p>';
	}

	/**
	 * Render enabled field
	 */
	public function render_enabled_field() {
		$settings = $this->get_settings();
		$enabled  = isset( $settings['enabled'] ) ? $settings['enabled'] : true;
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="1" <?php checked( $enabled, true ); ?> />
			<?php esc_html_e( 'Enable the AI Assistant on frontend pages', 'ai-assistant' ); ?>
		</label>
		<?php
	}

	/**
	 * Render welcome message field
	 */
	public function render_welcome_message_field() {
		$settings        = $this->get_settings();
		$welcome_message = isset( $settings['welcome_message'] ) ? $settings['welcome_message'] : __( 'Hi! How can I help you find information?', 'ai-assistant' );
		?>
		<textarea name="<?php echo esc_attr( $this->option_name ); ?>[welcome_message]" rows="3" cols="50" class="large-text"><?php echo esc_textarea( $welcome_message ); ?></textarea>
		<p class="description"><?php esc_html_e( 'The initial message shown when users open the chat.', 'ai-assistant' ); ?></p>
		<?php
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$settings = $this->get_settings();
		$api_key  = isset( $settings['api_key'] ) ? self::decrypt_api_key( $settings['api_key'] ) : '';
		?>
		<input type="password" name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Your OpenAI API key. Get one at https://platform.openai.com/api-keys', 'ai-assistant' ); ?></p>
		<?php
	}

	/**
	 * Render model field
	 */
	public function render_model_field() {
		$settings = $this->get_settings();
		$model    = isset( $settings['model'] ) ? $settings['model'] : 'gpt-3.5-turbo';
		?>
		<select name="<?php echo esc_attr( $this->option_name ); ?>[model]">
			<option value="gpt-3.5-turbo" <?php selected( $model, 'gpt-3.5-turbo' ); ?>>GPT-3.5 Turbo</option>
			<option value="gpt-4" <?php selected( $model, 'gpt-4' ); ?>>GPT-4</option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose the OpenAI model to use.', 'ai-assistant' ); ?></p>
		<?php
	}

	/**
	 * Render max context posts field
	 */
	public function render_max_context_posts_field() {
		$settings         = $this->get_settings();
		$max_context_posts = isset( $settings['max_context_posts'] ) ? $settings['max_context_posts'] : 50;
		?>
		<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[max_context_posts]" value="<?php echo esc_attr( $max_context_posts ); ?>" min="1" max="200" class="small-text" />
		<p class="description"><?php esc_html_e( 'Maximum number of posts/pages to include in AI context (1-200).', 'ai-assistant' ); ?></p>
		<?php
	}

	/**
	 * Render button color field
	 */
	public function render_button_color_field() {
		$settings     = $this->get_settings();
		$button_color = isset( $settings['button_color'] ) ? $settings['button_color'] : '#0073aa';
		?>
		<input type="color" name="<?php echo esc_attr( $this->option_name ); ?>[button_color]" value="<?php echo esc_attr( $button_color ); ?>" />
		<p class="description"><?php esc_html_e( 'Choose the color for the chat button.', 'ai-assistant' ); ?></p>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_ai-assistant-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ai-assistant-admin',
			AI_ASSISTANT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AI_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			'ai-assistant-admin',
			AI_ASSISTANT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AI_ASSISTANT_VERSION,
			true
		);
	}
}

