<?php
/**
 * Paymob Loading Data
 */
class WC_Paymob_Loading {

	public static function load() {
		global $wpdb;

		// Create table
		WC_Paymob_Tables::create_paymob_gateways_table();
		WC_Paymob_Tables::update_paymob_gateways_table();
		WC_Paymob_Tables::create_paymob_pixel_table();

		Paymob_Main_Partner_Info::partner_info();
		// Rebuild gateway rows when table was wiped (reinstall / disconnect) but keys remain.
		self::ensure_paymob_gateways_table_populated();
		// Gateways Files Creation on Updates
		$gateways = PaymobAutoGenerate::get_db_gateways_data();
		WC_Paymob_HandelUpdate::handle_plugin_update( $gateways );
		WC_Paymob_GatewayData::getPaymobGatewayData();
		foreach ( $gateways as $gateway ) {
			new Paymob_WooCommerce( $gateway->gateway_id );
		}
		// Load translation
		load_plugin_textdomain( 'paymob-woocommerce', false, PAYMOB_PLUGIN_NAME . '/i18n/languages' );
	}

	/**
	 * After uninstall/reinstall the paymob_gateways table can be empty while API keys remain.
	 * Regenerate gateway rows so classic + Blocks checkout show payment methods again.
	 */
	public static function ensure_paymob_gateways_table_populated() {
		global $wpdb;

		$table = $wpdb->prefix . 'paymob_gateways';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}paymob_gateways" );
		if ( $count > 0 ) {
			return;
		}

		$main = get_option( 'woocommerce_paymob-main_settings', array() );
		if ( empty( $main['api_key'] ) || empty( $main['sec_key'] ) || empty( $main['pub_key'] ) ) {
			return;
		}

		if ( ! class_exists( 'Paymob_Reset_gateways' ) ) {
			return;
		}

		try {
			Paymob_Reset_gateways::resetGateways(
				array(
					'apiKey' => $main['api_key'],
					'pubKey' => $main['pub_key'],
					'secKey' => $main['sec_key'],
				)
			);
		} catch ( \Exception $e ) {
			return;
		}
	}
}

