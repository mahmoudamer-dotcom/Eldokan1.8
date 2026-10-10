<?php

class Paymob_Verify_IntegrationID {


	public static function verify_integration_id( $integration_id_hidden, $integration_id, &$currency_errors, &$ids, $gateway_id = null ) {
		foreach ( $integration_id_hidden as $entry ) {
			$parts = explode( ':', $entry );
			$id    = trim( $parts[0] );
			if ( isset( $parts[2] ) ) {
				$currency = trim( substr( $parts[2], strpos( $parts[2], '(' ) + 1, -2 ) );
				if ( 'paymob' === $gateway_id ) {
					$paymob_options          = get_option( 'woocommerce_paymob_settings' );
					$unified_integration_ids = isset( $paymob_options['integration_id'] ) ? $paymob_options['integration_id'] : '';
					foreach ( $unified_integration_ids as $unified_integration_id ) {
						if ( $unified_integration_id === $id && get_woocommerce_currency() !== $currency ) {
							$currency_errors[] = $currency;
							$ids[]             = $id;
						}
					}
				} elseif ( $integration_id === $id && get_woocommerce_currency() !== $currency ) {
					$currency_errors[] = $currency;
					$ids[]             = $integration_id;
				}
			}
		}
	}
}
