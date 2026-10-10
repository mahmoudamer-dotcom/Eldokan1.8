<?php

class Paymob_Reset_gateways {

	public static function reset_paymob_gateways() {
		// Verify nonce for security.
		check_ajax_referer( 'reset_paymob_gateways', 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		// Retrieve the main Paymob options.
		$main_options    = get_option( 'woocommerce_paymob-main_settings' );
		
		// Retrieve the debug setting.
		$debug_ = isset( $main_options['debug'] ) ? $main_options['debug'] : '';
		$debug  = 'yes' === $debug_ ? '1' : '0';
		// Load integration keys.
		$conf['apiKey'] = isset( $main_options['api_key'] ) ? $main_options['api_key'] : '';
		$conf['pubKey'] = isset( $main_options['pub_key'] ) ? $main_options['pub_key'] : '';
		$conf['secKey'] = isset( $main_options['sec_key'] ) ? $main_options['sec_key'] : '';

		// Check if all keys are present.
		if ( ! empty( $conf['apiKey'] ) && ! empty( $conf['pubKey'] ) && ! empty( $conf['secKey'] ) ) {
			try {
				
				Paymob_Reset_gateways::resetGateways($conf);
				// Return success message.
				wp_send_json_success( array( 'message' => __( 'Payment methods have been reset successfully.', 'paymob-for-woocommerce' ) ) );
			} catch ( Exception $exc ) {
				Paymob::addLogs( $debug, Paymob::log_dir() . 'paymob-auth.log', $exc->getMessage() );
				wp_send_json_error(
					wp_json_encode(
						array(
							'success' => false,
							'error'   => $exc->getMessage(),
						)
					)
				);
			}
		}
	}

	public static function resetGateways($conf, $migrate = false){
		// Retrieve the main Paymob options.
		$main_options    = get_option( 'woocommerce_paymob-main_settings' );
		
		// Retrieve the debug setting.
		$debug_ = isset( $main_options['debug'] ) ? $main_options['debug'] : '';
		$debug  = 'yes' === $debug_ ? '1' : '0';
		// Instantiate the Paymob request handler.
		$default_enabled = isset( $main_options['enabled'] ) ? $main_options['enabled'] : '';
		$debug_ = isset( $main_options['debug'] ) ? $main_options['debug'] : '';

		$paymob_req = new Paymob( $debug, Paymob::log_dir() . 'paymob-auth.log' );
		// Get the auth token and gateway data.
		$result       = $paymob_req->authToken( $conf );
		$gateway_data = $paymob_req->getPaymobGateways( $conf['secKey'], PAYMOB_PLUGIN_PATH . 'assets/img/', isset( $result['token'] ) ? $result['token'] : '' );
		update_option( 'woocommerce_paymob_gateway_data', $gateway_data );
		// Auto-generate the gateways.
		PaymobAutoGenerate::create_gateways( $result, 1, $gateway_data );
		$integration_id_hidden = array();
		$ids                   = array();
		foreach ( $result['integrationIDs'] as $value ) {
 			$text = $value['id'] . ' : ' . $value['name'] . ' (' . $value['type'] . ' : ' . $value['currency'] . ' : ' . $value['mode'] . ' : ' . $value['is_moto'] . ' : ' . $value['is_3DS'] . ' )';			$integration_id_hidden[] = $text . ',';
			$ids[]                   = trim( $value['id'] );
		}
		if ( 'yes' === $default_enabled ) {
			PaymobAutoGenerate::register_framework( $ids );
		}
		$paymob_existing_settings = get_option( 'woocommerce_paymob_settings', array() );
		// print_r($paymob_existing_settings['integration_id_hidden']);
		// Update only the specific fields we need.
		$paymob_existing_settings['integration_id_hidden'] = implode( "\n", $integration_id_hidden );
		// Save the updated settings back to the database.
		update_option( 'woocommerce_paymob_settings', $paymob_existing_settings );
		Paymob_Manual_Setup_Save::pixel_settings($main_options['mode'], $migrate, $result);

	}
}