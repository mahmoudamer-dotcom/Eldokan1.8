<?php
class Paymob_Save_Cards_Title {


	public static function paymob_saved_cards_title( $title, $id ) {

		if ( is_wc_endpoint_url( 'saved-cards' ) && in_the_loop() ) {
			$title = __( 'Paymob Saved Credit Cards', 'paymob-for-woocommerce' );
		}
		return $title;
	}
}
