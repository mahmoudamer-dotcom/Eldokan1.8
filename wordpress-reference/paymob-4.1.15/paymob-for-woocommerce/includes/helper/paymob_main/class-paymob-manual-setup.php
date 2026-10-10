<?php
class Paymob_Manual_Setup_Save {

    public static function manual_setup_save_keys() {
        global $wpdb;
        check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }
       
        $apiKey        =  sanitize_text_field( Paymob::filterVar( 'apiKey', 'POST' ) );
        $testSecretKey =  sanitize_text_field( Paymob::filterVar( 'testSecretKey', 'POST' ) );
        $liveSecretKey =  sanitize_text_field( Paymob::filterVar( 'liveSecretKey', 'POST' ) );   
        $testPublicKey =  sanitize_text_field( Paymob::filterVar( 'testPublicKey', 'POST' ) ); 
        $livePublicKey =  sanitize_text_field( Paymob::filterVar( 'livePublicKey', 'POST' ) ); 
        $isTestMode    =  sanitize_text_field( Paymob::filterVar( 'isTestMode', 'POST' ) ); 
        
        if(empty($apiKey)){
            wp_send_json_error(['message' => __('Please fill Paymob API key.','paymob-for-woocommerce')]);
        }else if($isTestMode=='live' && empty($liveSecretKey) && empty($livePublicKey) && strpos($liveSecretKey, 'live')&& strpos($livePublicKey, 'live')){
            wp_send_json_error(['message' => __('Please fill Paymob Live manual configuration.','paymob-for-woocommerce')]);
        }else if($isTestMode=='test' && empty($testSecretKey) && empty($testPublicKey) && strpos($liveSecretKey, 'test')&& strpos($livePublicKey, 'test')){
            wp_send_json_error(['message' => __('Please fill Paymob Test manual configuration.','paymob-for-woocommerce')]);
        }
        $main_settings = get_option('woocommerce_paymob-main_settings',array());
        // $main_settings['pixel_payment']   = 'yes';
        $main_settings['debug']   = 'yes';
        $main_settings['api_key']      = $apiKey;
		$main_settings['test_pub_key'] = $testPublicKey;
		$main_settings['live_pub_key'] = $livePublicKey;
		$main_settings['test_sec_key'] = $testSecretKey;
		$main_settings['live_sec_key'] = $liveSecretKey;
		$main_settings['mode'] = $isTestMode ;
        $conf=array();
       
        if($main_settings['mode']=='live'){
            $conf['apiKey']            = $apiKey;
            $conf['pubKey']            = $livePublicKey;
            $conf['secKey']            = $liveSecretKey;
            $main_settings['pub_key']   = $livePublicKey;
            $main_settings['sec_key']   = $liveSecretKey;
        }
        if($main_settings['mode']=='test')
        { 
            $conf['apiKey']            = $apiKey;
            $conf['pubKey']            = $testPublicKey;
            $conf['secKey']            = $testSecretKey;
            $main_settings['pub_key']   = $testPublicKey;
            $main_settings['sec_key']   = $testSecretKey;
        }
      
        $addlog = Paymob::log_dir() . 'paymob-auth.log';
        $debug='1';
        delete_option('woocommerce_paymob_widget_settings');

        if(!empty($conf))
        {
            
           try{
                $paymobReq = new Paymob($debug, $addlog);
                $result = $paymobReq->authToken($conf);
                Paymob::addLogs($debug, $addlog, __('Merchant configuration: ', 'paymob-for-woocommerce'), $result);
                $gatewayData = $paymobReq->getPaymobGateways($conf['secKey'], PAYMOB_PLUGIN_PATH . 'assets/img/', isset( $result['token'] ) ? $result['token'] : '');
                Paymob_Unset_Old_Setting::unset_old_settings();
                // $wpdb->delete($wpdb->prefix . 'paymob_gateways', array('gateway_id' => 'paymob-pixel'));
                update_option('woocommerce_paymob_gateway_data', $gatewayData);
                update_option('woocommerce_paymob_country', Paymob::getCountryCode($conf['pubKey']));
                delete_option('woocommerce_paymob_gateway_data_failure');
                // Generate gateways 
                PaymobAutoGenerate::create_gateways($result, 1, $gatewayData);
                $ids = array();
                $main_integration_id_hidden = array();
                $integration_id_hidden = array();
                $count = 0;
                foreach ($result['integrationIDs'] as $value) {
                    if($value['mode'] ==$isTestMode){
                        $count ++;
                    }
                    $text = $value['id'] . ' : ' . $value['name'] . ' (' . $value['type'] . ' : ' . $value['currency'] . ' : ' . $value['mode'] . ' : ' . $value['is_moto'] . ' : ' . $value['is_3DS'] . ' )';
                    $main_integration_id_hidden[] = $text . ',';
                    if (isset($value['mode']) && $value['mode'] == $isTestMode) {
                        $integration_id_hidden[] = $text . ',';
					    $ids[]=trim( $value['id']);
				    }

                    // Update webhook Url by default
                    $webhhok    = $paymobReq->getIntegrationID( $conf,$value['id'] );
                    if ( false !== strpos( $webhhok->transaction_processed_callback, 'api/acceptance/post_pay' ) ) {
                        $data =array(
                            'transaction_processed_callback'=>add_query_arg( array( 'wc-api' => 'paymob_callback' ), home_url() ),
                            'transaction_response_callback'=>add_query_arg( array( 'wc-api' => 'paymob_callback' ), home_url() )
                        );

                        $paymobReq->updateWebHookUrl( $conf,$value['id'],$data );
                    }
                }

                if($count == 0){
                    wp_send_json_error(
						array(
							'message' => sprintf(
								/* translators: %s: test or live mode label */
								__( 'No %s integrations in this account.', 'paymob-for-woocommerce' ),
								$isTestMode
							),
						)
					);
                }

                if (!empty($ids)) {
                    $main_integration_id_hidden = implode("\n", $main_integration_id_hidden);
                    $integration_id_hidden = implode("\n", $integration_id_hidden);
                    $paymob_default_settings = array(
                        'enabled' => 'no',
                        'sec_key' => $conf['secKey'],
                        'pub_key' => $conf['pubKey'],
                        'api_key' => $conf['apiKey'],
                        'title' => 'Pay with Paymob',
                        'description' => 'Pay with Paymob',
                        'integration_id' => $ids,
                        'integration_id_hidden' => $integration_id_hidden,
                        'hmac_hidden' => $result['hmac'],
                        'debug' => 'yes',
                        'logo' => plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/img/paymob.png',
                    );
                    $main_settings['integration_id_hidden']   = $main_integration_id_hidden;
                    update_option('woocommerce_paymob_settings', $paymob_default_settings);
                    $main_settings['enabled']='yes';
                    update_option('woocommerce_paymob-main_settings', $main_settings);

                    self::pixel_settings($main_settings['mode']);
                  
                    Paymob_Unset_Old_Setting::paymob_setting($isTestMode,$ids);
                } else {
                    $wpdb->delete($wpdb->prefix . 'paymob_gateways', array('gateway_id' => 'paymob'));
                }
                update_option('woocommerce_paymob-main_settings', $main_settings);
                          
                self::deactive_payment($result);
                wp_send_json_success(['message' => 'Configurations saved successfully!','redirect_url'=>admin_url('admin.php?page=wc-settings&tab=checkout&section=paymob-main')]);
           
            } catch ( \Exception $e ) {
                 wp_send_json_error(['message' => $e->getMessage()]);
            }
           
        }
        else
        {
            wp_send_json_error(['message' => __('Empty configuration and we are not able to save configurations.', 'paymob-for-woocommerce')]);
        }
		
    }

    public static function pixel_settings($paymob_mode, $migrate = false, $gateway_data = array())
	{
		global $wpdb;
        // var_dump($gateway_data);die;
		$pixel_settings = get_option('woocommerce_paymob-pixel_settings', array());
		$pixel_settings['title'] = empty($pixel_settings['title'])? 'Debit/Credit Card Payment' : $pixel_settings['title'];
        $pixel_enabled = $pixel_settings['enabled'] = isset($pixel_settings['enabled']) ? ($pixel_settings['enabled']) :'yes';
        $pixel_settings['show_save_card'] = isset($pixel_settings['show_save_card'])? $pixel_settings['show_save_card'] : 'yes';
        $pixel_settings['force_save_card'] = isset($pixel_settings['force_save_card'])? $pixel_settings['force_save_card'] : 'no';
        if ( $pixel_enabled ) {
            $card_id1 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card'));
            $card_id2 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('OMANNET'));
            $card_ids = array_merge($card_id1,$card_id2);
            $cardEnabledIntegrations = array();
            // Check if $gateway_id contains the desired string.
            $omannet__migs_options = $wpdb->get_results("
                SELECT option_value
                FROM {$wpdb->options}
                WHERE option_name LIKE 'woocommerce_%omannet%' 
                OR option_name LIKE 'woocommerce_%migs%'
            ");
			$apple_pay_ids = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('apple_pay'));
            $googlePay = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('google-pay'));
            $googlePay2 =   array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Google-pay'));
             // $googlePay3 =   array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Google-Pay',$migrate));

			$google_pay_ids = array_merge($googlePay, $googlePay2);
			$bank_installment_ids = array_keys( PaymobAutoGenerate::get_pixel_bank_installment_integration_ids() );
			$all_ids = array_merge($card_ids, $apple_pay_ids, $google_pay_ids, $bank_installment_ids);
			$all_ids = array_filter($all_ids);

			$integration_ids = implode(',', $all_ids);
            $card_integrations = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card'));
            if (!empty($omannet__migs_options)) {
                foreach ($omannet__migs_options as $result) {
                    // Unserialize the option value to convert it to an array.
                    $option_value = maybe_unserialize($result->option_value);
            
                    // Check if the 'enabled' key exists and output its value.
                    if ($option_value['enabled']=="yes") {
                        $card_integrations1 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card'));
                        $card_integrations2 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('OMANNET'));
            
                        $card_integrations =array_merge( $card_integrations1,$card_integrations2);
                
            
                        $card_integrations = array_map('strval', $card_integrations);
                    }
                }
            }
            $existing = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", 'paymob-pixel'), OBJECT);
			if (empty($existing)) {
				$wpdb->insert(
					$wpdb->prefix . 'paymob_gateways',
					array(
						'gateway_id' => 'paymob-pixel',
						'file_name' => 'class-gateway-paymob-pixel.php',
						'class_name' => 'Paymob_Pixel',
						'checkout_title' => 'Debit/Credit Card Payment',
						'checkout_description' => 'Debit/Credit Card Payment',
						'integration_id' => $integration_ids,
						'is_manual' => '0',
						'ordering' => 35,
						'mode' => $paymob_mode
					)
				);
				if (empty($pixel_settings['cards_integration_id'])) {
					$pixel_settings['cards_integration_id'] = $card_integrations;
				}

                if($migrate && !empty( $gateway_data['integrationIDs'] ) ) {
                    $wpdb->update(
                        $wpdb->prefix . 'paymob_gateways',
                        array(
                            'mode'       => $paymob_mode,
                        ),
                        array( 'gateway_id' => 'paymob' )
                    );
                    foreach ( $gateway_data['integrationIDs'] as $value ) {
                        $title                     = empty( $value['name'] ) ? $value['type'] : $value['name'];
                        $payment_integrations_type = $value['id'] . ' ' . $title . ' ' . $value['gateway_type'] . ' ' . $value['currency'];
                        $payment_integrations_type = 'paymob-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', strtolower( $payment_integrations_type ) );
                        $mode       = isset($value['mode'])?$value['mode'] :'test';
                        $wpdb->update(
                            $wpdb->prefix . 'paymob_gateways',
                            array(
                                'mode'       => $mode,
                            ),
                            array( 'integration_id' => $value['id'] )
                        );
		        foreach($card_ids as $id){
                            $gateway_option = get_option( 'woocommerce_' . $payment_integrations_type . '_settings' );
                            if($value['id']== $id && $gateway_option['enabled'] == 'yes'){
                                    $cardEnabledIntegrations[]= $id;
                                    $gateway_option['enabled']='no';
                                    update_option( 'woocommerce_' . $payment_integrations_type. '_settings' , $gateway_option);
                            }
                        }
                        $pixel_settings['cards_integration_id'] = array_map('strval',$cardEnabledIntegrations);

                        update_option('woocommerce_paymob-pixel_settings', $pixel_settings);  
                    }
                }

			} else {
				if (($pixel_settings['cards_integration_id'] !== $card_integrations)) {
					$wpdb->update(
						$wpdb->prefix . 'paymob_gateways',
						array(
							'integration_id' => $integration_ids,
							'ordering' => 35,
							'mode' => $paymob_mode
						),
						array('gateway_id' => 'paymob-pixel')
					);
					$pixel_settings['cards_integration_id'] = $card_integrations;
				}
			}
            PaymobAutoGenerate::update_paymob_gateway_order();
		} else {
			$wpdb->delete($wpdb->prefix . 'paymob_gateways', array('gateway_id' => 'paymob-pixel'));
		}

        $pixel_settings['enabled'] = $pixel_enabled;
		update_option('woocommerce_paymob-pixel_settings', $pixel_settings);



	}
  
    public static function deactive_payment($gateway_data = array())
    {
            global $wpdb;
            $card_id1 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card'));
            $card_id2 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('OMANNET'));
            $card_ids = array_merge($card_id1,$card_id2);
			$apple_pay_ids = array_keys(PaymobAutoGenerate::get_pixel_integration_ids_all('apple_pay'));
            $googlePay = array_keys(PaymobAutoGenerate::get_pixel_integration_ids_all('google-pay'));
            $googlePay2 =   array_keys(PaymobAutoGenerate::get_pixel_integration_ids_all('Google-pay'));
			$google_pay_ids = array_merge($googlePay, $googlePay2);
			$bank_installment_ids = array_keys( PaymobAutoGenerate::get_pixel_bank_installment_integration_ids() );
			$all_ids = array_merge($card_ids, $apple_pay_ids, $google_pay_ids, $bank_installment_ids);
			$all_ids = array_filter($all_ids);
			$integration_ids = implode(',', $all_ids);
            if(!empty( $gateway_data['integrationIDs'] ) ) {
            foreach ( $gateway_data['integrationIDs'] as $value ) {
                $title                     = empty( $value['name'] ) ? $value['type'] : $value['name'];
                $payment_integrations_type = $value['id'] . ' ' . $title . ' ' . $value['gateway_type'] . ' ' . $value['currency'];
                $payment_integrations_type = 'paymob-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', strtolower( $payment_integrations_type ) );
                foreach($all_ids as $id){
                    if($value['id']== $id){

                        $gateway_option = get_option( 'woocommerce_' . $payment_integrations_type . '_settings' );
                        $gateway_option['enabled']='no';
                        update_option( 'woocommerce_' . $payment_integrations_type. '_settings' , $gateway_option);
                    }
                }
            }
        }

    }
}
