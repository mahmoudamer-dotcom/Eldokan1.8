<?php

class Paymob_Main_Scripts {

	public static function enqueue_paymob_main_scripts() {
		$current_section = Paymob::filterVar( 'section' ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob-main' === $current_section ) {
			Paymob_Scripts::paymob_main_scripts();
		}
	}

	public static function enqueue_paymob_pixel_script() {
		$current_section = Paymob::filterVar( 'section' ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob_pixel' === $current_section ) {
			Paymob_Scripts::enqueue_paymob_pixel_script();
		}
	}
	
}
