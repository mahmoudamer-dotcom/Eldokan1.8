<?php
/**
 * Paymob post-activation handler.
 *
 * QIT Activation tests break on forced redirects after plugin activate.
 * Merchants are pointed to settings via an admin notice instead.
 */
class WC_Paymob_RedirectUrl {

	/**
	 * Consume the activation flag without leaving the current admin screen.
	 */
	public static function redirect_after_activation() {
		if ( ! get_option( 'paymob_activation_redirect', false ) ) {
			return;
		}

		delete_option( 'paymob_activation_redirect' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Never redirect during/after activation — keeps QIT activation flows stable.
		set_transient(
			'paymob_flash_notice',
			array(
				'type'    => 'updated',
				'message' => __( 'Paymob is active. Open WooCommerce → Settings → Payments → Paymob to connect your account.', 'paymob-for-woocommerce' ),
			),
			120
		);
	}
}
