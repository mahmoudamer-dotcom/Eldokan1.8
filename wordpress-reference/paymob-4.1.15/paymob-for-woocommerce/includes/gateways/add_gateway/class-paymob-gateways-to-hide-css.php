<?php

class Paymob_Gateways_HideCss {

	public static function gateways_to_hide_css() {
		if ( ( Paymob::filterVar( 'tab' ) ) && Paymob::filterVar( 'tab' ) === 'checkout' ) {
			// Fetch gateway IDs from your custom table.
			$results = PaymobAutoGenerate::get_db_gateways_data();
			$css     = '';

			foreach ( $results as $result ) {
				$css .= 'div[id="' . esc_attr( $result->gateway_id ) . '"] { display: none; }';
				$css .= 'tr[data-gateway_id="' . esc_attr( $result->gateway_id ) . '"] { display: none; }';
			}

			if ( ! empty( $css ) ) {
				echo '<style>' . wp_kses( $css, array() ) . '</style>';
			}
		}
	}
}
