<?php
class Paymob_Save_Cards_Tab {

	public static function paymob_add_saved_cards_tab( $menu_links ) {
		$main_options    = get_option( 'woocommerce_paymob-main_settings' );
		$default_enabled = isset( $main_options['enabled'] ) ? $main_options['enabled'] : '';

		if ( 'yes' === $default_enabled ) {
			$menu_links = array_slice( $menu_links, 0, 5, true )
				+ array( 'saved-cards' => __( 'Paymob Saved Cards', 'paymob-for-woocommerce' ) )
				+ array_slice( $menu_links, 5, null, true );

		}
		return $menu_links;
	}
}
