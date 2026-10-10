<?php

class Paymob_Toggle_Gateway {

	public static function toggle_gateway() {
		check_ajax_referer( 'toggle_gateway_nonce', 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		$gateway_id     = sanitize_text_field( Paymob::filterVar( 'gateway_id', 'POST' ) );
		$current_status = get_option( 'woocommerce_' . $gateway_id . '_settings' )['enabled'];
		$enabled        = ( 'yes' === $current_status ) ? 'no' : 'yes';

		if ( 'yes' === $enabled ) {
			$paymob_options = get_option( 'woocommerce_paymob_settings' );
			$pub_key        = isset( $paymob_options['pub_key'] ) ? $paymob_options['pub_key'] : '';
			$sec_key        = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
			$api_key        = isset( $paymob_options['api_key'] ) ? $paymob_options['api_key'] : '';

			if ( empty( $pub_key ) || empty( $sec_key ) || empty( $api_key ) ) {
				wp_send_json_error(
					array(
						'success' => false,
						'msg'     => 'Please ensure you are entering API, public, and secret keys in the main Paymob configuration.',
					)
				);
			}

		}
		$settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		// Merge the new status with the existing settings.
		$settings['enabled'] = $enabled;
		// Update the gateway settings with the new status.
		update_option( 'woocommerce_' . $gateway_id . '_settings', $settings );
		// Register the Framework into Paymob if enabled.
		register_frameworks();

		wp_send_json_success(
			array(
				'success' => true,
				'msg'     => __( 'Payment Method status updated successfully.','paymob-for-woocommerce'),
			)
		);

		wp_die();
	}
}
