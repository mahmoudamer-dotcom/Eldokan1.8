<?php

class Paymob_Delete_Gateway {


	public static function delete_gateway() {
		global $wpdb;

		// Verify the nonce for security.
		check_ajax_referer( 'delete_gateway_nonce', 'security' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}
		// Sanitize the gateway ID from the request.
		$gateway_id = sanitize_text_field( Paymob::filterVar( 'gateway_id', 'POST' ) );

		if ( ! empty( $gateway_id ) ) {
			$js_file   = PAYMOB_PLUGIN_PATH . 'assets/js/blocks/' . $gateway_id . '_block.js';
			$blc_file  = PAYMOB_PLUGIN_PATH . 'includes/blocks/' . $gateway_id . '-block.php';
			$file_path = PAYMOB_PLUGIN_PATH . 'includes/gateways/class-gateway-' . $gateway_id . '.php';

			// Unlink the files if they exist.
			if ( file_exists( $js_file ) ) {
				wp_delete_file( $js_file );
			}
			if ( file_exists( $blc_file ) ) {
				wp_delete_file( $blc_file );
			}
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Remove the gateway from the database.
			$wpdb->delete(
				$wpdb->prefix . 'paymob_gateways',
				array( 'gateway_id' => $gateway_id ),
				array( '%s' )
			);

			// Delete the gateway settings option.
			delete_option( 'woocommerce_' . $gateway_id . '_settings' );

			// Remove the gateway from the Paymob order list.
			remove_gateway_from_paymob_order( $gateway_id );

			// Send a success response.
			wp_send_json_success( array( 'status' => 'success' ) );
		} else {
			// Send an error response if the gateway ID is invalid.
			wp_send_json_error(
				array(
					'status'  => 'error',
					'message' => 'Invalid gateway ID.',
				)
			);
		}

		wp_die();
	}
}
