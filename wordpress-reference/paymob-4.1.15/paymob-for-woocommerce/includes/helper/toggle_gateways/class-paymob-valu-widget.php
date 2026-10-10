<?php
class Paymob_Valu_Widget {

	public static function valu_widget() {
		check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
        $paymob_options = get_option( 'woocommerce_paymob-main_settings' );
        global $wpdb;

        $option_name = $wpdb->get_var("
            SELECT option_name 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'woocommerce_paymob-%valu%_settings'
            LIMIT 1
        ");
        
        $option_value = get_option($option_name);
        if(!empty($paymob_options) && (!empty($option_value) && $option_value['enabled']=='yes')){
            
            $conf['apiKey'] = isset( $paymob_options['api_key'] ) ? $paymob_options['api_key'] : '';
            $conf['pubKey'] = isset( $paymob_options['pub_key'] ) ? $paymob_options['pub_key'] : '';
            $conf['secKey'] = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
            $debug  = isset( $paymob_options['debug'] ) ? sanitize_text_field( $paymob_options['debug'] ) : '0';
            $debug   = 'yes' === $debug ? '1' : '0';
            $add_log = Paymob::log_dir() . 'paymob.log';

            $paymob_req = new Paymob( $debug, Paymob::log_dir() . 'paymob.log' );
            $price = sanitize_text_field( Paymob::filterVar( 'price', 'POST' ) );
            $data = array(
				'amount'  => (float)$price
            );
            $widgets = $paymob_req->valuWidget($conf['secKey'], $data );
            $widgetsresult= array();
            foreach($widgets->tenure_list as $widget){
                  array_push($widgetsresult,$widget);
            }
          
            wp_send_json_success( $widgetsresult);
        } else {
            // Send an error response if gateway ID or action is not provided.
        
            wp_send_json_error(
                array(
                    'status'  => 'error',
                    'message' => 'Valu is not enabled',
                )
            );
        }
        wp_die();
	}
}

