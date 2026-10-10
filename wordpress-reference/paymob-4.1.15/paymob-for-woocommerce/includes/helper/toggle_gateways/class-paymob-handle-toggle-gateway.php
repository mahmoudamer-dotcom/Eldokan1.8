<?php
class Paymob_Handel_Toggle {

	public static function handle_toggle_gateway() {
		// Check nonce for security.
		check_ajax_referer( 'your_nonce_action', '_ajax_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}
		// Get the gateway ID and action from the AJAX request.
		$gateway_id = ( Paymob::filterVar( 'gateway_id', 'POST' ) ) ? sanitize_text_field( Paymob::filterVar( 'gateway_id', 'POST' ) ) : '';

		if ( $gateway_id ) {
			// Ensure settings is an array.
			$options            = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
			$options['enabled'] = 'no';
			update_option( 'woocommerce_' . $gateway_id . '_settings', $options );
			PaymobAutoGenerate::register_framework( $ids    = array() );
			wp_send_json_success( 'Gateway disabled' );
		} else {
			// Send an error response if gateway ID or action is not provided.
			wp_send_json_error( 'Gateway ID or toggle action not provided' );
		}
		wp_die();
	}
}
