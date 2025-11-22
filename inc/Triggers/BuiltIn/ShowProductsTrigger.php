<?php
/**
 * Show Products Trigger
 *
 * Returns product information for display when triggered via AI command.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers\BuiltIn;

use SmartAssistant\Triggers\Trigger;

/**
 * Class ShowProductsTrigger
 *
 * Trigger ID: show_products
 * Command: [SHOW_PRODUCTS:product_id1,product_id2,product_id3]
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
class ShowProductsTrigger extends Trigger {

	/**
	 * Get unique trigger identifier
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_id(): string {
		return 'show_products';
	}

	/**
	 * Get human-readable trigger name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_name(): string {
		return __( 'Show Products', 'smart-assistant' );
	}

	/**
	 * Get trigger description
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description(): string {
		return __( 'Returns product information for display in the chat.', 'smart-assistant' );
	}

	/**
	 * Get regex pattern to match command
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_command_pattern(): string {
		return '/\[SHOW_PRODUCTS:([^\]]+)\]/i';
	}

	/**
	 * Get required parameter names
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_required_params(): array {
		return array( 'product_ids' );
	}

	/**
	 * Check if trigger can be executed
	 *
	 * @param array $context Execution context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function can_execute( array $context ): bool {
		// Anyone can view products
		return true;
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
		// Check WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WooCommerce is not active.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		$product_ids_str = isset( $params['product_ids'] ) ? $params['product_ids'] : '';
		$product_ids = explode( ',', $product_ids_str );
		$product_ids = array_map( array( $this, 'sanitize_number' ), $product_ids );
		$product_ids = array_filter( $product_ids ); // Remove empty values

		if ( empty( $product_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No product IDs provided.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		$products_data = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}

			$image_id = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();

			$products_data[] = array(
				'id'       => $product_id,
				'name'     => $product->get_name(),
				'price'    => $product->get_price_html(),
				'price_raw' => $product->get_price(),
				'url'      => $product->get_permalink(),
				'image'    => $image_url,
				'in_stock' => $product->is_in_stock(),
			);
		}

		if ( empty( $products_data ) ) {
			return array(
				'success' => false,
				'message' => __( 'No valid products found.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: Number of products */
				_n( 'Found %d product.', 'Found %d products.', count( $products_data ), 'smart-assistant' ),
				count( $products_data )
			),
			'data'    => array(
				'products' => $products_data,
			),
		);
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
				'description' => __( 'Allow the AI to display product information.', 'smart-assistant' ),
				'default'     => true,
			),
		);
	}
}

