<?php

class Paymob_Register_Frameworks {

	public static function register_frameworks() {
		$paymob_options  = get_option( 'woocommerce_paymob-main_settings' );
		$default_enabled = isset( $paymob_options['enabled'] ) ? $paymob_options['enabled'] : '';
		if ( 'yes' === $default_enabled ) {
			$gateways = PaymobAutoGenerate::get_db_gateways_data();
			$ids      = array();

			foreach ( $gateways as $gateway ) {
				$options = get_option( 'woocommerce_' . $gateway->gateway_id . '_settings', array() );

				if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
					// Collect single_integration_id.
					if ( isset( $options['single_integration_id'] ) ) {
						$ids[] = (int) $options['single_integration_id'];
					}

					// Collect and merge integration_id array.
					if ( isset( $options['integration_id'] ) && is_array( $options['integration_id'] ) ) {
						$ids = array_merge( $ids, array_map( 'intval', $options['integration_id'] ) );
					}
				}
			}
			PaymobAutoGenerate::register_framework( $ids );
		}
	}
}
