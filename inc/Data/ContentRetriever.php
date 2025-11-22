<?php
/**
 * Content Retriever for WordPress Posts and Pages
 *
 * @package SmartAssistant
 */

namespace SmartAssistant\Data;

/**
 * Content retriever class
 */
class ContentRetriever {

	/**
	 * Get WordPress content for AI context
	 *
	 * @return array Array of content items with title and excerpt
	 */
	public function get_content_for_context() {
		// Check cache first
		$cached = get_transient( 'smart_assistant_content_cache' );
		if ( false !== $cached ) {
			//return $cached;
		}

		$settings = get_option( 'smart_assistant_settings', array() );
		$max_posts = isset( $settings['max_context_posts'] ) ? absint( $settings['max_context_posts'] ) : 50;

		// Get posts and pages
		$posts = get_posts(
			array(
				'post_type'      => array( 'post' ),
				'post_status'   => 'publish',
				'numberposts'   => $max_posts,
				'orderby'       => 'date',
				'order'         => 'DESC',
				'suppress_filters' => false,
			)
		);

		$content = array();

		foreach ( $posts as $post ) {
			// Strip all HTML tags from content first
			$clean_content = wp_strip_all_tags( $post->post_content );
			// Trim to a reasonable length (200 words gives more context while managing token usage)
			$content_text = wp_trim_words( $clean_content, 200 );

			$content[] = array(
				'title'   => sanitize_text_field( $post->post_title ),
				'content' => sanitize_text_field( $content_text ),
			);
		}

		// Cache for 1 hour
		set_transient( 'smart_assistant_content_cache', $content, HOUR_IN_SECONDS );

		return $content;
	}
}

