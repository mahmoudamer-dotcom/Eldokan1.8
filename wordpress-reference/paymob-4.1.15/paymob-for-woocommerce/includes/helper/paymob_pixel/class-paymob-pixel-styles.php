<?php

class Paymob_Pixel_Style {

	public static function enqueue_paymob_pixel_styles() {
		$current_section = Paymob::filterVar( 'section' ) ? sanitize_text_field( Paymob::filterVar( 'section' ) ) : '';
		if ( 'paymob_pixel' === $current_section|| 'paymob_subscription' === $current_section ) {
			Paymob_Style::paymob_pixel_styles();
		}
	}
}
