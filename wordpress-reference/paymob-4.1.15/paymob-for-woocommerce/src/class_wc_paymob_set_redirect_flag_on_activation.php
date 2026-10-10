<?php
/**
 * Paymob Redirect flag
 */
class WC_Paymob_RedirectFlag
{
    public static function set_redirect_flag_on_activation( $plugin ) {
		if ( $plugin === PAYMOB_PLUGIN ) {
			update_option( 'paymob_activation_redirect', true );
		}
	}

}