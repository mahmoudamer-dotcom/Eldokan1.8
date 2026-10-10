<?php

class Paymob_Apply_Gateway_Order {

	public static function apply_paymob_gateway_order( $available_gateways ) {
		$paymob_options  = get_option( 'woocommerce_paymob-main_settings' );
		$default_enabled = isset( $paymob_options['enabled'] ) ? $paymob_options['enabled'] : 'no';
		// Always check subscription first
		if ( self::cart_has_subscription() ) {
			// Keep only paymob-subscription if cart has a subscription
			foreach ( $available_gateways as $gateway_id => $gateway_obj ) {
				if ( $gateway_id !== 'paymob-subscription' ) {
					unset( $available_gateways[ $gateway_id ] );
				}
			}
			return $available_gateways;
		} else {
			// If no subscription, remove paymob-subscription
			unset( $available_gateways['paymob-subscription'] );
		}
		if ( is_checkout() && 'yes' === $default_enabled ) {
			$order = get_option( 'paymob_gateway_order', array() );

			// Collect Paymob child gateways (except main)
			$paymob_children = array();
			foreach ( $order as $gateway_id ) {
				if (
					isset( $available_gateways[ $gateway_id ] )
					&& $gateway_id !== 'paymob-main'
				) {
					$paymob_children[ $gateway_id ] = $available_gateways[ $gateway_id ];
					unset( $available_gateways[ $gateway_id ] ); // temporarily remove them
				}
			}

			// Now build a new array, inserting children right after 'paymob-main'
			$new_gateways = array();
			foreach ( $available_gateways as $id => $gateway ) {
				if ( $id === 'paymob-main' ) {
					// Insert the children after 'paymob-main'
					$new_gateways += $paymob_children;
					// Skip adding 'paymob-main' itself
					continue;
				}
				$new_gateways[ $id ] = $gateway;
			}

			return $new_gateways;
		}

		// Filter gateways if cart has subscription
		if ( self::cart_has_subscription() ) {
			// Only show paymob-subscription
			foreach ( $available_gateways as $gateway_id => $gateway_obj ) {
				if ( $gateway_id !== 'paymob-subscription' ) {
					unset( $available_gateways[ $gateway_id ] );
				}
			}
		} else {
			// If no subscription, remove paymob-subscription
			unset( $available_gateways['paymob-subscription'] );
		}

		return $available_gateways;
	}

	public static function cart_has_subscription() {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
	
		$cart = WC()->cart;
		if ( ! $cart || empty( $cart->get_cart() ) ) {
			return false;
		}
	
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
				$product = $cart_item['data'];
				if ( method_exists( $product, 'get_type' ) && in_array( $product->get_type(), array( 'subscription', 'subscription_variation' ), true ) ) {
					return true;
				}
			}
		}
	
		return false;
	}
}





