<?php
class Paymob_Enqueue_Style {

	public static function paymob_enqueue_saved_cards_styles() {
		if ( is_wc_endpoint_url( 'saved-cards' ) ) {
			Paymob_Style::paymob_save_cards();
		}
	}
}
