<?php
class Paymob_Webhook_Update {

    public static function save_webhook_callbacks_callback() {
        // Verify the nonce for security
        check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }
        // Get the values sent from the AJAX request
        $processed_callback = sanitize_text_field( Paymob::filterVar( 'new_callback', 'POST' ) );
        $response_callback = sanitize_text_field( Paymob::filterVar( 'new_callback', 'POST' ) );
        $integration_id = sanitize_text_field( Paymob::filterVar( 'integration_id', 'POST' ) );
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
            $data =array(
                'transaction_processed_callback'=>$processed_callback,
                'transaction_response_callback'=>$response_callback
            );
            $paymobReq = new Paymob( $debug, $addlog );
            $result    = $paymobReq->updateWebHookUrl( $conf,$integration_id,$data );

        }
        wp_send_json_success(['data' => ['msg' => 'Callbacks saved successfully']]);
    }
}