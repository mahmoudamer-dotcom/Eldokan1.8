<?php
/**
 * Paymob Loading Data
 */
class WC_Paymob_UnInstall {

	public static function uninstall() {
		global $wpdb;
		delete_option( 'woocommerce_paymob-main_settings' );
		// delete_option( 'woocommerce_paymob-pixel_settings' );
		delete_option( 'woocommerce_paymob_settings' );
		delete_option( 'woocommerce_paymob-subscription_settings' );
		// Get all options starting with 'paymob_subscription'
		$options = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'paymob_subscription%'"
		);
		$gateways = PaymobAutoGenerate::get_db_gateways_data();
		foreach ( $gateways as $gateway ) {
			if ( 'paymob' !== $gateway->gateway_id ) {
				delete_option( 'woocommerce_' . $gateway->gateway_id . '_settings' );
			}
		}
		delete_option( 'paymob_gateway_order' );
		delete_option( 'woocommerce_paymob_country' );
		delete_option( 'woocommerce_paymob_gateway_data' );
		delete_option( 'woocommerce_paymob_gateway_data_failure' );
		delete_option('woocommerce_paymob_widget_settings');
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}paymob_gateways" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}paymob_cards_token" );
	}
}
