<?php
/**
 * Trigger Manager Admin Interface
 *
 * Provides admin UI for managing triggers and their settings.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers\Admin;

use SmartAssistant\Triggers\TriggerRegistry;

/**
 * Class TriggerManager
 *
 * Manages admin interface for triggers.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
class TriggerManager {

	/**
	 * Initialize admin interface
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_smart_assistant_test_trigger', array( $this, 'ajax_test_trigger' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'options-general.php',
			__( 'Smart Assistant Triggers', 'smart-assistant' ),
			__( 'Smart Assistant Triggers', 'smart-assistant' ),
			'manage_options',
			'smart-assistant-triggers',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		$registry = TriggerRegistry::get_instance();
		$triggers = $registry->get_all();

		foreach ( $triggers as $trigger ) {
			$option_name = 'smart_assistant_trigger_' . $trigger->get_id();
			register_setting(
				'smart_assistant_triggers',
				$option_name,
				array(
					'sanitize_callback' => array( $this, 'sanitize_trigger_settings' ),
				)
			);
		}
	}

	/**
	 * Sanitize trigger settings
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 * @since 1.0.0
	 */
	public function sanitize_trigger_settings( array $input ): array {
		$sanitized = array();

		foreach ( $input as $key => $value ) {
			if ( 'enabled' === $key ) {
				$sanitized[ $key ] = (bool) $value;
			} elseif ( 'email' === $key || strpos( $key, 'email' ) !== false ) {
				$sanitized[ $key ] = sanitize_email( $value );
			} elseif ( 'url' === $key || strpos( $key, 'url' ) !== false ) {
				$sanitized[ $key ] = esc_url_raw( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'settings_page_smart-assistant-triggers' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'smart-assistant-triggers-admin',
			SMART_ASSISTANT_PLUGIN_URL . 'assets/css/triggers-admin.css',
			array(),
			SMART_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			'smart-assistant-triggers-admin',
			SMART_ASSISTANT_PLUGIN_URL . 'assets/js/triggers-admin.js',
			array( 'jquery' ),
			SMART_ASSISTANT_VERSION,
			true
		);

		wp_localize_script(
			'smart-assistant-triggers-admin',
			'smartAssistantTriggers',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'smart_assistant_trigger_test' ),
				'strings' => array(
					'testing' => __( 'Testing...', 'smart-assistant' ),
					'success' => __( 'Test successful!', 'smart-assistant' ),
					'error'   => __( 'Test failed.', 'smart-assistant' ),
				),
			)
		);
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registry = TriggerRegistry::get_instance();
		$triggers = $registry->get_all();

		// Separate built-in and third-party triggers
		$built_in_triggers = array();
		$third_party_triggers = array();

		foreach ( $triggers as $trigger ) {
			$namespace = get_class( $trigger );
			if ( strpos( $namespace, 'SmartAssistant\\Triggers\\BuiltIn' ) !== false ) {
				$built_in_triggers[] = $trigger;
			} else {
				$third_party_triggers[] = $trigger;
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="smart-assistant-triggers-header">
				<p class="description">
					<?php esc_html_e( 'Manage triggers that extend the Smart Assistant functionality. Triggers are executed when the AI includes specific commands in its responses.', 'smart-assistant' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'smart_assistant_triggers' ); ?>

				<h2><?php esc_html_e( 'Built-in Triggers', 'smart-assistant' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-name"><?php esc_html_e( 'Trigger', 'smart-assistant' ); ?></th>
							<th class="column-description"><?php esc_html_e( 'Description', 'smart-assistant' ); ?></th>
							<th class="column-command"><?php esc_html_e( 'Command Pattern', 'smart-assistant' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'smart-assistant' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'smart-assistant' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $built_in_triggers as $trigger ) : ?>
							<?php $this->render_trigger_row( $trigger ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( ! empty( $third_party_triggers ) ) : ?>
					<h2><?php esc_html_e( 'Third-party Triggers', 'smart-assistant' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="column-name"><?php esc_html_e( 'Trigger', 'smart-assistant' ); ?></th>
								<th class="column-description"><?php esc_html_e( 'Description', 'smart-assistant' ); ?></th>
								<th class="column-command"><?php esc_html_e( 'Command Pattern', 'smart-assistant' ); ?></th>
								<th class="column-status"><?php esc_html_e( 'Status', 'smart-assistant' ); ?></th>
								<th class="column-actions"><?php esc_html_e( 'Actions', 'smart-assistant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $third_party_triggers as $trigger ) : ?>
								<?php $this->render_trigger_row( $trigger ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php submit_button( __( 'Save Changes', 'smart-assistant' ) ); ?>
			</form>

			<div class="smart-assistant-triggers-logs">
				<h2><?php esc_html_e( 'Execution Logs', 'smart-assistant' ); ?></h2>
				<?php $this->render_logs(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render trigger row in table
	 *
	 * @param \SmartAssistant\Triggers\Interfaces\TriggerInterface $trigger Trigger instance.
	 * @return void
	 * @since 1.0.0
	 */
	private function render_trigger_row( $trigger ): void {
		$option_name = 'smart_assistant_trigger_' . $trigger->get_id();
		$settings = get_option( $option_name, array() );
		$enabled = isset( $settings['enabled'] ) ? (bool) $settings['enabled'] : true;
		$schema = $trigger->get_settings_schema();
		$has_settings = count( $schema ) > 1; // More than just 'enabled'

		?>
		<tr>
			<td class="column-name">
				<strong><?php echo esc_html( $trigger->get_name() ); ?></strong>
				<div class="row-actions">
					<span class="id">ID: <code><?php echo esc_html( $trigger->get_id() ); ?></code></span>
				</div>
			</td>
			<td class="column-description">
				<?php echo esc_html( $trigger->get_description() ); ?>
			</td>
			<td class="column-command">
				<code><?php echo esc_html( $trigger->get_command_pattern() ); ?></code>
			</td>
			<td class="column-status">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $enabled, true ); ?> />
					<?php echo $enabled ? esc_html__( 'Enabled', 'smart-assistant' ) : esc_html__( 'Disabled', 'smart-assistant' ); ?>
				</label>
			</td>
			<td class="column-actions">
				<?php if ( $has_settings ) : ?>
					<a href="#" class="button trigger-settings" data-trigger-id="<?php echo esc_attr( $trigger->get_id() ); ?>">
						<?php esc_html_e( 'Settings', 'smart-assistant' ); ?>
					</a>
				<?php endif; ?>
				<button type="button" class="button trigger-test" data-trigger-id="<?php echo esc_attr( $trigger->get_id() ); ?>">
					<?php esc_html_e( 'Test', 'smart-assistant' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render execution logs
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_logs(): void {
		$logs = get_transient( 'smart_assistant_trigger_logs' );
		if ( false === $logs || empty( $logs ) ) {
			echo '<p>' . esc_html__( 'No execution logs yet.', 'smart-assistant' ) . '</p>';
			return;
		}

		// Show last 20 logs
		$recent_logs = array_slice( array_reverse( $logs ), 0, 20 );

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'smart-assistant' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'smart-assistant' ); ?></th>
					<th><?php esc_html_e( 'User', 'smart-assistant' ); ?></th>
					<th><?php esc_html_e( 'Status', 'smart-assistant' ); ?></th>
					<th><?php esc_html_e( 'Message', 'smart-assistant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['timestamp'] ); ?></td>
						<td><strong><?php echo esc_html( $log['trigger_name'] ); ?></strong></td>
						<td>
							<?php
							if ( $log['user_id'] > 0 ) {
								$user = get_user_by( 'ID', $log['user_id'] );
								echo $user ? esc_html( $user->display_name ) : esc_html__( 'Unknown', 'smart-assistant' );
							} else {
								esc_html_e( 'Guest', 'smart-assistant' );
							}
							?>
						</td>
						<td>
							<?php if ( $log['success'] ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
								<?php esc_html_e( 'Success', 'smart-assistant' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: red;"></span>
								<?php esc_html_e( 'Failed', 'smart-assistant' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX handler for testing triggers
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function ajax_test_trigger(): void {
		check_ajax_referer( 'smart_assistant_trigger_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-assistant' ) ) );
		}

		$trigger_id = isset( $_POST['trigger_id'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_id'] ) ) : '';
		if ( empty( $trigger_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Trigger ID required.', 'smart-assistant' ) ) );
		}

		$registry = TriggerRegistry::get_instance();
		$trigger = $registry->get( $trigger_id );

		if ( ! $trigger ) {
			wp_send_json_error( array( 'message' => __( 'Trigger not found.', 'smart-assistant' ) ) );
		}

		// Create test context
		$context = array(
			'user_id'      => get_current_user_id(),
			'user_message' => __( 'Test message', 'smart-assistant' ),
			'timestamp'    => current_time( 'mysql' ),
			'session_id'   => 'test_' . time(),
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		// Get test parameters (this would need to be customized per trigger)
		$test_params = $this->get_test_params( $trigger_id );

		$result = $trigger->safe_execute( $test_params, $context );

		wp_send_json_success( $result );
	}

	/**
	 * Get test parameters for trigger
	 *
	 * @param string $trigger_id Trigger ID.
	 * @return array Test parameters.
	 * @since 1.0.0
	 */
	private function get_test_params( string $trigger_id ): array {
		// This would need to be customized based on trigger requirements
		// For now, return empty array - triggers should handle missing params gracefully
		return array(
			'post_id' => 1,
			'subject' => 'Test Subject',
			'message' => 'Test Message',
		);
	}
}

