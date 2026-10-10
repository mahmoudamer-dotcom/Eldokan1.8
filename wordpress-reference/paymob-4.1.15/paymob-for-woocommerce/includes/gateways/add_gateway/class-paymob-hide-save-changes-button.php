<?php

class Paymob_Hide_Save_Button {

	public static function hide_save_changes_button_in_paymob_list_gateways_section() {

		$current_section = ( Paymob::filterVar( 'section' ) ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob_list_gateways' === $current_section ) {
			Paymob_Style::paymob_list_gateways();
		}
	}
}
