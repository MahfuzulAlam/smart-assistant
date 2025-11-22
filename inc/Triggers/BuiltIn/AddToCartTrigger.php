<?php
/**
 * Add To Cart Trigger
 *
 * Adds a WooCommerce product to the cart when triggered via AI command.
 *
 * @package SmartAssistant
 * @since 1.0.0
 */

namespace SmartAssistant\Triggers\BuiltIn;

use SmartAssistant\Triggers\Trigger;

/**
 * Class AddToCartTrigger
 *
 * Trigger ID: add_to_cart
 * Command: [ADD_TO_CART:product_id:quantity]
 *
 * @package SmartAssistant
 * @since 1.0.0
 */
class AddToCartTrigger extends Trigger {

	/**
	 * Get unique trigger identifier
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_id(): string {
		return 'add_to_cart';
	}

	/**
	 * Get human-readable trigger name
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_name(): string {
		return __( 'Add to Cart', 'smart-assistant' );
	}

	/**
	 * Get trigger description
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description(): string {
		return __( 'Adds a WooCommerce product to the shopping cart.', 'smart-assistant' );
	}

	/**
	 * Get regex pattern to match command
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_command_pattern(): string {
		return '/\[ADD_TO_CART:([^:]+):([^\]]+)\]/i';
	}

	/**
	 * Get required parameter names
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_required_params(): array {
		return array( 'product_id', 'quantity' );
	}

	/**
	 * Check if trigger can be executed
	 *
	 * @param array $context Execution context.
	 * @return bool
	 * @since 1.0.0
	 */
	public function can_execute( array $context ): bool {
		// Anyone can add to cart (including guests)
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

		$product_id = isset( $params['product_id'] ) ? $this->sanitize_number( $params['product_id'] ) : 0;
		$quantity   = isset( $params['quantity'] ) ? $this->sanitize_number( $params['quantity'] ) : 1;

		// Validate quantity
		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		// Get product
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'success' => false,
				'message' => __( 'Product not found.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		// Check if product is purchasable
		if ( ! $product->is_purchasable() ) {
			return array(
				'success' => false,
				'message' => __( 'This product cannot be purchased.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		// Check stock
		if ( ! $product->is_in_stock() ) {
			return array(
				'success' => false,
				'message' => __( 'This product is out of stock.', 'smart-assistant' ),
				'data'    => array(),
			);
		}

		// Add to cart
		$cart = WC()->cart;
		$cart_item_key = $cart->add_to_cart( $product_id, $quantity );

		if ( $cart_item_key ) {
			$cart_url = wc_get_cart_url();

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: Quantity, 2: Product name */
					__( 'Added %1$d × %2$s to cart.', 'smart-assistant' ),
					$quantity,
					$product->get_name()
				),
				'data'    => array(
					'product_id'   => $product_id,
					'product_name' => $product->get_name(),
					'quantity'     => $quantity,
					'cart_url'     => $cart_url,
					'cart_item_key' => $cart_item_key,
				),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Failed to add product to cart.', 'smart-assistant' ),
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
				'description' => __( 'Allow the AI to add products to the shopping cart.', 'smart-assistant' ),
				'default'     => true,
			),
		);
	}
}

