<?php

class Paymob_Webhook_Url {

	public static function webhook_url() {
		check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}
		$integration_id     = sanitize_text_field( Paymob::filterVar( 'integration_id', 'POST' ) );
        $mainOptions = get_option( 'woocommerce_paymob-main_settings' );
        $debug = isset( $mainOptions['debug'] ) ? $mainOptions['debug'] : '';
		$debug = 'yes' === $debug ? '1' : '0';
        $addlog    = Paymob::log_dir() . 'paymob-auth.log';
		// Load integrations IDs
		$conf['apiKey'] = isset( $mainOptions['api_key'] ) ? $mainOptions['api_key'] : '';
		$conf['pubKey'] = isset( $mainOptions['pub_key'] ) ? $mainOptions['pub_key'] : '';
		$conf['secKey'] = isset( $mainOptions['sec_key'] ) ? $mainOptions['sec_key'] : '';
		if ( ! empty( $conf['apiKey'] ) && ! empty( $conf['pubKey'] ) && ! empty( $conf['secKey'] ) )
        {
            $paymobReq = new Paymob( $debug, $addlog );
            $result    = $paymobReq->getIntegrationID( $conf,$integration_id );
            if( empty($result))
            {
                wp_send_json_error(
					array(
						'success' => false,
						'msg'     => 'Please ensure that your integration id is valid.'
                        
					)
				); 
            }
            
            wp_send_json_success(
                array(
                    'success' => true,
                    'data'=> $result
                )
            );
        
            wp_die();
        }
        else{
            wp_send_json_error(
                array(
                    'success' => false,
                    'msg'     => 'Please ensure that your configation is valid.'
                    
                )
            ); 

        }
           
	}
}
