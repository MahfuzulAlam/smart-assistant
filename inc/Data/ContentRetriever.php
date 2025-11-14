<?php
/**
 * Content Retriever for WordPress Posts and Pages
 *
 * @package AIAssistant
 */

namespace AIAssistant\Data;

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
		$cached = get_transient( 'ai_assistant_content_cache' );
		if ( false !== $cached ) {
			return $cached;
		}

		$settings = get_option( 'ai_assistant_settings', array() );
		$max_posts = isset( $settings['max_context_posts'] ) ? absint( $settings['max_context_posts'] ) : 50;

		// Get posts and pages
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'   => 'publish',
				'numberposts'   => $max_posts,
				'orderby'       => 'date',
				'order'         => 'DESC',
				'suppress_filters' => false,
			)
		);

		$content = array();

		foreach ( $posts as $post ) {
			$excerpt = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 30 );
			$content_text = wp_strip_all_tags( wp_trim_words( $post->post_content, 50 ) );

			$content[] = array(
				'title'   => sanitize_text_field( $post->post_title ),
				'excerpt' => sanitize_text_field( $excerpt ),
				'content' => sanitize_text_field( $content_text ),
			);
		}

		// Cache for 1 hour
		set_transient( 'ai_assistant_content_cache', $content, HOUR_IN_SECONDS );

		return $content;
	}
}

