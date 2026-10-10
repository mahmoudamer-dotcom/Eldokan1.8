<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Paymob_Gateway_Blocks extends AbstractPaymentMethodType {

	public $name;
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', array() );
	}
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}
	public function get_payment_method_script_handles() {
		Paymob_Scripts::method_script( $this->name );

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $this->name . '-blocks-integration' );
		}

		return array( $this->name . '-blocks-integration' );
	}

	public function get_payment_method_data() {

		return include __DIR__ . '/../checkout/checkout_paymob_gateways.php';
	}
}
