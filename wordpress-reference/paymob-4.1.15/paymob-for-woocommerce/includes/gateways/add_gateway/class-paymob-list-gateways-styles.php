<?php

class Paymob_List_Gateways_Style {

	public static function enqueue_paymob_list_gateways_styles() {
		$current_section = Paymob::filterVar( 'section' ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob_list_gateways' === $current_section ) {
			Paymob_Style::paymob_list_gateways();
		}
	}
}
