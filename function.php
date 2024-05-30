add_filter( 'woocommerce_available_payment_gateways', 'enable_specific_payment_method_for_specific_product' );

function enable_specific_payment_method_for_specific_product( $available_gateways ) {
	// Check if product with ID 74709 is in the cart
	if ( is_admin() || empty( $available_gateways ) ) {
		return $available_gateways;
	}

	$product_id_to_check = 74709;
	$specific_payment_method = 'no_checkout_fee'; // Slug of the "No Checkout Fee" payment method

	// Check if the product with ID 74709 is in the cart
	$product_in_cart = false;
	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( $cart_item['product_id'] === $product_id_to_check ) {
			$product_in_cart = true;
			break;
		}
	}

	// Count the number of items in the cart
	$cart_item_count = WC()->cart->get_cart_contents_count();

	// If only the product with ID 74709 is in the cart, enable only the specific payment method
	if ( $product_in_cart && $cart_item_count === 1 ) {
		// Remove all available payment methods
		$available_gateways = array();

		// Add the specific payment method
		$available_gateways[ $specific_payment_method ] = WC()->payment_gateways->payment_gateways()[ $specific_payment_method ];
	} else {
		// If other products are present or if the product with ID 74709 is not alone, remove the specific payment method
		unset( $available_gateways[ $specific_payment_method ] );
	}

	return $available_gateways;
}


add_filter('woocommerce_payment_gateways', 'add_no_checkout_fee_gateway');
function add_no_checkout_fee_gateway($gateways)
{
	$gateways[] = 'WC_Custom_No_Checkout_Fee_Gateway';
	return $gateways;
}

class WC_Custom_No_Checkout_Fee_Gateway extends WC_Payment_Gateway
{
	public function __construct()
	{
		$this->id                 = 'no_checkout_fee';
		$this->method_title       = 'No Checkout Fee';
		$this->has_fields         = false;
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable No Checkout Fee',
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'no-checkout-fee' ),
				'type'        => 'text',
				'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'no-checkout-fee' ),
				'default'     => __( 'No Checkout Fee', 'no-checkout-fee' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'no-checkout-fee' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'no-checkout-fee' ),
				'default'     => __( 'Purchase without any checkout fee.', 'no-checkout-fee' ),
				'desc_tip'    => true,
			),

		);
	}

	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		// Check if product with ID 74709 is in the cart
		$product_id_to_check = 74709;
		$product_in_cart = false;
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_product_id() === $product_id_to_check ) {
				$product_in_cart = true;
				break;
			}
		}

		// If the specific product is not in the cart, return error
		if ( ! $product_in_cart ) {
			return array(
				'result'   => 'error',
				'redirect' => wc_get_checkout_url(),
			);
		}

		// Update order post meta for payment_pending
		update_post_meta($order_id, '_payment_pending', true);

		// Mark the order as on hold
		$order->update_status('completed');

		// Redirect to the thank you page
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}
}
