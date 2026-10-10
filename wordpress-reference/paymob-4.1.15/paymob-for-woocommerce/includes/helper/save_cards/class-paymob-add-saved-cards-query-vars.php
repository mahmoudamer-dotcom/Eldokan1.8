<?php
class Paymob_Save_Cards_Query_Vars {


	public static function paymob_add_saved_cards_query_vars( $vars ) {
		$main_options    = get_option( 'woocommerce_paymob-main_settings' );
		$default_enabled = isset( $main_options['enabled'] ) ? $main_options['enabled'] : '';

		if ( 'yes' === $default_enabled ) {
			$vars['saved-cards'] = 'saved-cards';
		}
		return $vars;
	}
}
