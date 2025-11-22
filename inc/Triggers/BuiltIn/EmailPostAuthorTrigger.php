<?php
/**
 * Email Post Author Trigger
 *
 * Sends an email to a post author when triggered via AI command.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers\BuiltIn;

use SmartAssistant\Triggers\Trigger;

/**
 * Class EmailPostAuthorTrigger
 *
 * Trigger ID: email_post_author
 * Command: [EMAIL_AUTHOR:post_id:subject:message]
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
class EmailPostAuthorTrigger extends Trigger {

	/**
	 * Get unique trigger identifier
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_id(): string {
		return 'email_post_author';
	}

	/**
	 * Get human-readable trigger name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_name(): string {
		return __( 'Email Post Author', 'smart-assistant' );
	}

	/**
	 * Get trigger description
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description(): string {
		return __( 'Sends an email to the author of a specified post.', 'smart-assistant' );
	}

	/**
	 * Get regex pattern to match command
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_command_pattern(): string {
		return '/\[EMAIL_AUTHOR:([^:]+):([^:]+):([^\]]+)\]/i';
	}

	/**
	 * Get required parameter names
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_required_params(): array {
		return array( 'post_id', 'subject', 'message' );
	}

	/**
	 * Check if trigger can be executed
	 *
	 * @param array $context Execution context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function can_execute( array $context ): bool {
		$user_id = isset( $context['user_id'] ) ? $context['user_id'] : 0;
		return current_user_can( 'edit_posts' ) || user_can( $user_id, 'edit_posts' );
	}

	/**
	 * Execute the trigger
	 *
	 * @param array $params Trigger parameters.
	 * @param array $context Execution context.
	 * @return array Response array.
	 * @since 1.0.0
	 */
	public function execute( array $params, array $context ): array {
		$post_id = isset( $params['post_id'] ) ? $this->sanitize_number( $params['post_id'] ) : 0;
		$subject = isset( $params['subject'] ) ? $this->sanitize_text( $params['subject'] ) : '';
		$message = isset( $params['message'] ) ? $this->sanitize_textarea( $params['message'] ) : '';

		// Get post
		$post = $this->get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		// Get post author
		$author = $this->get_user( $post->post_author );
		if ( ! $author ) {
			return array(
				'success' => false,
				'message' => __( 'Post author not found.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		// Get settings
		$settings = $this->get_settings();
		$from_email = ! empty( $settings['from_email'] ) ? $this->sanitize_email( $settings['from_email'] ) : get_option( 'admin_email' );
		$template = ! empty( $settings['email_template'] ) ? $settings['email_template'] : $this->get_default_template();

		// Replace placeholders in template
		$email_body = $this->replace_template_placeholders(
			$template,
			array(
				'author_name' => $author->display_name,
				'message'     => $message,
				'post_title'  => $post->post_title,
				'post_link'   => get_permalink( $post_id ),
			)
		);

		// Prepare email
		$to = $author->user_email;
		$email_subject = ! empty( $subject ) ? $subject : sprintf(
			/* translators: %s: Site name */
			__( 'Message about your post on %s', 'smart-assistant' ),
			get_bloginfo( 'name' )
		);

		$headers = array(
			'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>',
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email
		$sent = wp_mail( $to, $email_subject, $email_body, $headers );

		if ( $sent ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: Author name */
					__( 'Email sent successfully to %s.', 'smart-assistant' ),
					$author->display_name
				),
				'data'    => array(
					'post_id'    => $post_id,
					'author_id'  => $author->ID,
					'author_name' => $author->display_name,
				),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Failed to send email. Please check your WordPress email configuration.', 'smart-assistant' ),
				'data'    => array(),
			);
		}
	}

	/**
	 * Get settings schema
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_settings_schema(): array {
		return array(
			array(
				'name'        => 'enabled',
				'type'        => 'checkbox',
				'label'       => __( 'Enable this trigger', 'smart-assistant' ),
				'description' => __( 'Allow the AI to send emails to post authors.', 'smart-assistant' ),
				'default'     => true,
			),
			array(
				'name'        => 'from_email',
				'type'        => 'email',
				'label'       => __( 'From Email Address', 'smart-assistant' ),
				'description' => __( 'Email address to send from. Leave empty to use admin email.', 'smart-assistant' ),
				'default'     => '',
			),
			array(
				'name'        => 'email_template',
				'type'        => 'textarea',
				'label'       => __( 'Email Template', 'smart-assistant' ),
				'description' => __( 'Email template with placeholders: {author_name}, {message}, {post_title}, {post_link}', 'smart-assistant' ),
				'default'     => $this->get_default_template(),
			),
		);
	}

	/**
	 * Get default email template
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function get_default_template(): string {
		return sprintf(
			'<html><body>
				<p>%s,</p>
				<p>%s</p>
				<p><strong>%s:</strong> %s</p>
				<p><strong>%s:</strong> <a href="%s">%s</a></p>
				<p>%s</p>
			</body></html>',
			/* translators: Placeholder for author name */
			'{author_name}',
			/* translators: Email message introduction */
			__( 'You have received a message regarding your post.', 'smart-assistant' ),
			/* translators: Message label */
			__( 'Message', 'smart-assistant' ),
			'{message}',
			/* translators: Post label */
			__( 'Post', 'smart-assistant' ),
			'{post_link}',
			'{post_title}',
			/* translators: Email closing */
			__( 'Best regards,', 'smart-assistant' ) . '<br>' . get_bloginfo( 'name' )
		);
	}

	/**
	 * Replace template placeholders
	 *
	 * @param string $template Template string.
	 * @param array  $data Data to replace.
	 * @return string Template with placeholders replaced.
	 * @since 1.0.0
	 */
	private function replace_template_placeholders( string $template, array $data ): string {
		$result = $template;
		foreach ( $data as $key => $value ) {
			$result = str_replace( '{' . $key . '}', $value, $result );
		}
		return $result;
	}
}

