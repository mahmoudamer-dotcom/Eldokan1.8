<?php

class Paymob_Check_IntergrationMatch {

	public static function check_integration_id_match( $integration_id, $currency ) {

		$paymob_options        = get_option( 'woocommerce_paymob_settings' );
		$integration_id_hidden = explode( ',', $paymob_options ['integration_id_hidden'] );
		foreach ( $integration_id_hidden as $entry ) {
			$parts = explode( ':', $entry );
			if ( count( $parts ) < 3 ) {
				continue; // Skip this entry if it doesn't have enough parts.
			}

			$id             = trim( $parts[0] );
			$entry_currency = isset( $parts[2] ) ? trim( substr( $parts[2], strpos( $parts[2], '(' ) + 1) ) : '';
			if (is_array($integration_id)&& in_array( $id, $integration_id, true ) && $entry_currency === $currency ) {
				return true;
			}
		}
		return false;
	}
}
