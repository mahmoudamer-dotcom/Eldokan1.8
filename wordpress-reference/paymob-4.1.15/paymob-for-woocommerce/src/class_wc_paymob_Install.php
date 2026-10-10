<?php
/**
 * Paymob Installing Plugin
 */
class WC_Paymob_Install {

	public static function install() {
		global $wpdb;

		// Do not fatal activation if WooCommerce is momentarily unavailable.
		if ( ! self::is_woocommerce_active() ) {
			set_transient(
				'paymob_flash_notice',
				array(
					'type'    => 'error',
					'message' => __( 'Sorry, PayMob plugin requires WooCommerce to be installed and active.', 'paymob-for-woocommerce' ),
				),
				60
			);
			return;
		}

		self::maybe_copy_arabic_translations();

		WC_Paymob_Tables::create_paymob_gateways_table();
		WC_Paymob_Tables::update_paymob_gateways_table();
		WC_Paymob_Tables::create_paymob_pixel_table();
		WC_Paymob_Tables::flush_tables_verified_cache();
	}

	/**
	 * @return bool
	 */
	private static function is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
			if ( isset( $sitewide['woocommerce/woocommerce.php'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Copy Arabic translations when source files exist (avoid warnings on activation).
	 */
	private static function maybe_copy_arabic_translations() {
		if ( ! is_dir( WP_LANG_DIR . '/plugins/' ) ) {
			return;
		}

		$ar_trans          = 'paymob-woocommerce-ar';
		$trans_path        = WP_LANG_DIR . '/plugins/' . $ar_trans;
		$plugin_trans_path = PAYMOB_PLUGIN_PATH . 'i18n/languages/' . $ar_trans;

		foreach ( array( '.mo', '.po' ) as $ext ) {
			$source = $plugin_trans_path . $ext;
			$dest   = $trans_path . $ext;
			if ( file_exists( $source ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- intentional one-time activation copy.
				@copy( $source, $dest );
			}
		}
	}
}
