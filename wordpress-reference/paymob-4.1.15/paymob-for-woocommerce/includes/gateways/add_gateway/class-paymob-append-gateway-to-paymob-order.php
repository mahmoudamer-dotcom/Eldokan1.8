<?php

class Paymob_Append_Gateway {

	public static function append_gateway_to_paymob_order( $new_gateway_id ) {
		// Retrieve the current paymob_gateway_order array.
		$order = get_option( 'paymob_gateway_order', array() );

		// Append the new gateway ID to the array.
		$order[] = $new_gateway_id;

		// Update the paymob_gateway_order option with the new array.
		update_option( 'paymob_gateway_order', $order );
	}
}
