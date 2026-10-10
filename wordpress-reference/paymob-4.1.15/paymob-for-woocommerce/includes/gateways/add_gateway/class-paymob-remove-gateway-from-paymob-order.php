<?php

class Paymob_Remove_Gateway_From_Order {

	public static function remove_gateway_from_paymob_order( $gateway_id_to_remove ) {
		// Retrieve the current paymob_gateway_order array.
		$order = get_option( 'paymob_gateway_order', array() );

		// Search for the gateway ID and remove it if it exists.
		$index = array_search( $gateway_id_to_remove, $order, true );
		if ( false !== $index ) {
			unset( $order[ $index ] );
		}

		// Reindex the array to prevent gaps in the keys.
		$order = array_values( $order );

		// Update the paymob_gateway_order option with the new array.
		update_option( 'paymob_gateway_order', $order );
	}
}
