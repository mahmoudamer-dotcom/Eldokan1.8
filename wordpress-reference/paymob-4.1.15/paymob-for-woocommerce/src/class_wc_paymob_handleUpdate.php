<?php
/**
 * Handel Plugin Update
 */
class WC_Paymob_HandelUpdate {

	public static function handle_plugin_update( $gateways ) {
		// Retrieve the main settings
		$mainOptions = get_option( 'woocommerce_paymob-main_settings' );
		$paymobReq = new Paymob( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log' );
		// Check if main settings are empty
		if ( empty( $mainOptions ) ) {
			// Retrieve the Paymob settings
			$paymobSettings = get_option( 'woocommerce_paymob_settings' );
			
			// echo "<pre>";print_r($mainSettings);exit;
			// Check if Paymob settings are not empty
			if ( ! empty( $paymobSettings ) ) {
				// Prepare the main settings with values from Paymob settings
				$mainSettings = array(
					'enabled'    => 'yes',
					'sec_key'    => isset( $paymobSettings['sec_key'] ) ? $paymobSettings['sec_key'] : '',
					'pub_key'    => isset( $paymobSettings['pub_key'] ) ? $paymobSettings['pub_key'] : '',
					'api_key'    => isset( $paymobSettings['api_key'] ) ? $paymobSettings['api_key'] : '',
					'integration_id_hidden' => isset( $paymobSettings['integration_id_hidden'] ) ? $paymobSettings['integration_id_hidden'] : '',
					'empty_cart' => isset( $paymobSettings['empty_cart'] ) ? $paymobSettings['empty_cart'] : '',
					'debug'      => 'yes',
					'has_items'  => 'yes',
				);
				$mode = $paymobReq->getMode( $paymobSettings['sec_key'] );
				if($mode === 'live'){
					$mainSettings['live_sec_key']            =  isset( $paymobSettings['sec_key'] ) ? $paymobSettings['sec_key'] : '';
					$mainSettings['live_pub_key']            = isset( $paymobSettings['pub_key'] ) ? $paymobSettings['pub_key'] : '';
				}
				if($mode === 'test')
				{ 
					$mainSettings['test_sec_key']            =  isset( $paymobSettings['sec_key'] ) ? $paymobSettings['sec_key'] : '';
					$mainSettings['test_pub_key']            = isset( $paymobSettings['pub_key'] ) ? $paymobSettings['pub_key'] : '';
				}
				$mainSettings['mode']            = $mode;
				// echo "<pre>";print_r($mainSettings);exit;
				// Update the main settings
				update_option( 'woocommerce_paymob-main_settings', $mainSettings );

				$paymob_default_settings = array(
					'enabled'               => 'yes',
					'sec_key'               => isset( $paymobSettings['sec_key'] ) ? $paymobSettings['sec_key'] : '',
					'pub_key'               => isset( $paymobSettings['pub_key'] ) ? $paymobSettings['pub_key'] : '',
					'api_key'               => isset( $paymobSettings['api_key'] ) ? $paymobSettings['api_key'] : '',
					'title'                 => isset( $paymobSettings['title'] ) ? $paymobSettings['title'] : '',
					'description'           => isset( $paymobSettings['description'] ) ? $paymobSettings['description'] : '',
					'integration_id'        => isset( $paymobSettings['integration_id'] ) ? $paymobSettings['integration_id'] : '',
					'integration_id_hidden' => isset( $paymobSettings['integration_id_hidden'] ) ? $paymobSettings['integration_id_hidden'] : '',
					'hmac_hidden'           => isset( $paymobSettings['hmac_hidden'] ) ? $paymobSettings['hmac_hidden'] : '',
					'empty_cart'            => isset( $paymobSettings['empty_cart'] ) ? $paymobSettings['empty_cart'] : '',
					'debug'                 => 'yes',
					'logo'                  => plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png',
					'has_items'             => 'yes',
				);
				update_option( 'woocommerce_paymob_settings', $paymob_default_settings );
			}
		}
		// display enabled gateways count
		if ( ! empty( $mainOptions ) ) {
			// Retrieve the main Paymob options.
			$mainOptions    = get_option( 'woocommerce_paymob-main_settings' );
			// unset($mainOptions['mode']);
			// update_option( 'woocommerce_paymob-main_settings', $mainOptions);
			$mode_= isset( $mainOptions['mode'] ) ? $mainOptions['mode'] : '';
			// echo "<pre>";print_r($mode_);exit;
			if ( empty( $mode_ ) ) {
				$mode = $paymobReq->getMode( $mainOptions['sec_key'] );
				if($mode === 'live'){
					$mainOptions['live_sec_key']            =  isset( $mainOptions['sec_key'] ) ? $mainOptions['sec_key'] : '';
					$mainOptions['live_pub_key']            = isset( $mainOptions['pub_key'] ) ? $mainOptions['pub_key'] : '';
				}
				if($mode === 'test')
				{ 
					$mainOptions['test_sec_key']            =  isset( $mainOptions['sec_key'] ) ? $mainOptions['sec_key'] : '';
					$mainOptions['test_pub_key']            = isset( $mainOptions['pub_key'] ) ? $mainOptions['pub_key'] : '';
				}
				$mainOptions['mode']            = $mode;
				update_option( 'woocommerce_paymob-main_settings', $mainOptions);
				// Retrieve the debug setting.
				$debug_ = isset( $mainOptions['debug'] ) ? $mainOptions['debug'] : '';
				$debug  = 'yes' === $debug_ ? '1' : '0';
				// Load integration keys.
				$conf['apiKey'] = isset( $mainOptions['api_key'] ) ? $mainOptions['api_key'] : '';
				$conf['pubKey'] = isset( $mainOptions['pub_key'] ) ? $mainOptions['pub_key'] : '';
				$conf['secKey'] = isset( $mainOptions['sec_key'] ) ? $mainOptions['sec_key'] : '';
				Paymob_Reset_gateways::resetGateways($conf, true);
			}
			PaymobAutoGenerate::enabled_gateways_count( $gateways );
		}

		$debug = isset( $mainOptions['debug'] ) ? $mainOptions['debug'] : '';
		$debug = 'yes' === $debug ? '1' : '0';
		// Load integrations IDs
		$conf['apiKey'] = isset( $mainOptions['api_key'] ) ? $mainOptions['api_key'] : '';
		$conf['pubKey'] = isset( $mainOptions['pub_key'] ) ? $mainOptions['pub_key'] : '';
		$conf['secKey'] = isset( $mainOptions['sec_key'] ) ? $mainOptions['sec_key'] : '';
		if ( ! empty( $conf['apiKey'] ) && ! empty( $conf['pubKey'] ) && ! empty( $conf['secKey'] ) ) {

			try {
				$paymob_country = get_option( 'woocommerce_paymob_country' );
				$lastFailure = get_option('woocommerce_paymob_gateway_data_failure');
				if ( empty( $paymob_country ) && empty($lastFailure) ) {
					$paymobReq = new Paymob( $debug, Paymob::log_dir() . 'paymob-auth.log' );
					update_option( 'woocommerce_paymob_country', Paymob::getCountryCode( $conf['pubKey'] ) );
					$result = $paymobReq->authToken( $conf );
					$ids    = array();
					foreach ( $result['integrationIDs'] as $value ) {
						$ids[] = trim( $value['id'] );
					}
					PaymobAutoGenerate::register_framework( $ids );
					$gatewayData = get_option( 'woocommerce_paymob_gateway_data' );
					if ( empty( $gatewayData ) ) {
						$gatewayData = $paymobReq->getPaymobGateways( $conf['secKey'], PAYMOB_PLUGIN_PATH . 'assets/img/' , isset( $result['token'] ) ? $result['token'] : '');
						update_option( 'woocommerce_paymob_gateway_data', $gatewayData );
						delete_option( 'woocommerce_paymob_gateway_data_failure' );
					}
					Paymob_Unset_Old_Setting::paymob_setting($paymobReq->getMode( $conf['secKey'] ),$ids);
					PaymobAutoGenerate::create_gateways( $result, 0, $gatewayData );
					
				}
			} catch ( \Exception $e ) {
				WC_Admin_Settings::add_error( esc_html( $e->getMessage() ) );
			}
		}
		// Load gateways from db
		foreach ( $gateways as $gateway ) {
			// Check if properties are set and provide a default value if not
			$class_name                = isset( $gateway->class_name ) ? $gateway->class_name : '';
			$payment_integrations_type = isset( $gateway->gateway_id ) ? $gateway->gateway_id : '';
			$checkout_title            = isset( $gateway->checkout_title ) ? $gateway->checkout_title : '';
			$checkout_description      = isset( $gateway->checkout_description ) ? $gateway->checkout_description : '';
			$file_name                 = isset( $gateway->file_name ) ? $gateway->file_name : '';
			$f_array                   = array(
				'class_name'           => $class_name,
				'gateway_id'           => $payment_integrations_type,
				'checkout_title'       => $checkout_title,
				'checkout_description' => $checkout_description,
				'file_name'            => $file_name,
			);
			PaymobAutoGenerate::generate_files( $f_array );
		}
	}
}
