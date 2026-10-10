<?php

class Paymob_Save_Gateway_Order {

	public static function save_paymob_gateway_order() {
		check_ajax_referer( 'save_gateway_order', 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}
		if ( ( Paymob::filterVar( 'order', 'POST' ) ) && is_array( Paymob::filterVar( 'order', 'POST' ) ) ) {
			global $wpdb;
			// Update the ordering column.
			$order = Paymob::filterVar( 'order', 'POST' );
			foreach ( $order as $index => $gateway_id ) {
				$wpdb->update(
					$wpdb->prefix . 'paymob_gateways',
					array( 'ordering' => $index ),
					array( 'gateway_id' => sanitize_text_field( $gateway_id ) ),
					array( '%d' ),
					array( '%s' )
				);
			}
			$order = array_map( 'sanitize_text_field', $order );
			update_option( 'paymob_gateway_order', $order );
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Invalid order data.' );
		}
	}
}
