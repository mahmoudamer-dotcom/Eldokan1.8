<?php

class Paymob_Admin_Scripts {

	public static function enqueue_paymob_admin_scripts() {
		$current_section = ( Paymob::filterVar( 'section' ) ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob_list_gateways' === $current_section ) {
			Paymob_Scripts::paymob_list_gateways();
		}
	}
}
