<?php
class Paymob_Check_IntergrationID {

	public static function check_integration_id( $integration_id, $gateway_id, &$mismatched_ids, &$mismatched_currencies, &$mismatched_integration_ids ) {
		$paymob_options   = get_option( 'woocommerce_paymob_settings' );
		$currency_matched = false; // Initialize a flag to check currency match.

		if ( isset( $paymob_options['integration_id_hidden'] ) && ! empty( $paymob_options['integration_id_hidden'] ) ) {
			$integration_id_hidden = explode( ',', $paymob_options['integration_id_hidden'] );

			foreach ( $integration_id_hidden as $entry ) {
				$parts = explode( ':', $entry );

				// Check if parts are set correctly.
				if ( count( $parts ) < 3 ) {
					continue; // Skip this entry if it doesn't have enough parts.
				}

				$id       = trim( $parts[0] );
				$currency = isset( $parts[2] ) ? trim( substr( $parts[2], strpos( $parts[2], '(' ) + 1 ) ) : '';
				$currency = str_replace([')', ' '], '', $currency);
				
				if ( $id === $integration_id ) {
					if ( get_woocommerce_currency() === $currency ) {
						$currency_matched = true;
						break; // Currency matched, no need to check further.
					} else {
						$currency_matched             = false;
						$mismatched_integration_ids[] = $integration_id; // Add mismatched ID to the array.
						$mismatched_ids[]             = $gateway_id; // Add gateway ID to the array.
						$mismatched_currencies[]      = $currency; // Add the associated currency to the array.
						break; // Exit the loop if currency mismatch is found.
					}
				}
			}
		}

		// If no currency matched, consider it a mismatch.
		if ( ! $currency_matched ) {
			$mismatched_integration_ids[] = $integration_id;
			$mismatched_ids[]             = $gateway_id;
		}
	}
}
