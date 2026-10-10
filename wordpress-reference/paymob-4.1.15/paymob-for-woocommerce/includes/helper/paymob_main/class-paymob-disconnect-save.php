<?php
class Paymob_Disconnect_Save {

    public static function disconnect_save_keys() {
        global $wpdb;
        check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }
        try{
            delete_option( 'woocommerce_paymob-main_settings' );
            delete_option( 'woocommerce_paymob_settings' );
            $gateways = PaymobAutoGenerate::get_db_gateways_data();
            foreach ( $gateways as $gateway ) {
                if ( 'paymob' !== $gateway->gateway_id ) {
                    delete_option( 'woocommerce_' . $gateway->gateway_id . '_settings' );
                }
            }
            delete_option( 'paymob_gateway_order' );
            delete_option( 'woocommerce_paymob_country' );
            delete_option( 'woocommerce_paymob_gateway_data' );
            delete_option( 'woocommerce_paymob_gateway_data_failure' );
            delete_option('woocommerce_paymob_widget_settings');
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}paymob_gateways" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}paymob_cards_token" );
  
            wp_send_json_success(['message' => 'You Are Disconnect Sucessfully With Paymob Account!',
            'redirect_url'=>admin_url('admin.php?page=wc-settings&tab=checkout&section=paymob-main')]);

        } catch ( \Exception $e ) {

             wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

}