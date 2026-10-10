<?php
class Paymob_Disable_Confirmation {


	public static function enqueue_disable_gateway_confirmation_script() {
		if ( ( Paymob::filterVar( 'tab' ) ) && 'checkout' === Paymob::filterVar( 'tab' ) ) {
			Paymob_Style::confirmation_popup();
			// Enqueue the custom JavaScript file.
			Paymob_Scripts::confirmation_popup();

			$paymob_options = get_option( 'woocommerce_paymob-main_settings' );
			$pub_key        = isset( $paymob_options['pub_key'] ) ? esc_attr( $paymob_options['pub_key'] ) : '';
			$sec_key        = isset( $paymob_options['sec_key'] ) ? esc_attr( $paymob_options['sec_key'] ) : '';
			$api_key        = isset( $paymob_options['api_key'] ) ? esc_attr( $paymob_options['api_key'] ) : '';
			$exist          = ( $pub_key && $sec_key && $api_key ) ? 1 : 0;
			return Paymob_Scripts::confirmation_popup_localize( $exist );

		}
	}
}
