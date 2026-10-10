<?php
class Paymob_Save_Cards_Endpoints {

	public static function paymob_add_saved_cards_endpoint() {
		$main_options    = get_option( 'woocommerce_paymob-main_settings' );
		$default_enabled = isset( $main_options['enabled'] ) ? $main_options['enabled'] : '';

		if ( 'yes' === $default_enabled ) {
			add_rewrite_endpoint( 'saved-cards', EP_ROOT | EP_PAGES );
		}
	}
}
