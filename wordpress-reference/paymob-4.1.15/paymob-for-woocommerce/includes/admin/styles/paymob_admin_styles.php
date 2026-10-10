<?php
class Paymob_Style {

	public static function paymob_admin() {
		wp_enqueue_style( 'paymob-admin-css', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/admin.css', array(), PAYMOB_VERSION );
	}

	/**
	 * Ensure Paymob tab styles (including the Affordability Widget "New" badge) load on
	 * every Paymob checkout settings section, not only when a specific gateway boots admin.css.
	 */
	public static function maybe_enqueue_checkout_tabs_styles() {
		if ( ! is_admin() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

		if ( 'wc-settings' !== $page || 'checkout' !== $tab ) {
			return;
		}

		$section = isset( $_GET['section'] ) ? sanitize_title( wp_unslash( $_GET['section'] ) ) : 'paymob-main';

		$paymob_sections = array(
			'paymob-main',
			'paymob_list_gateways',
			'paymob_pixel',
			'widget',
			'paymob_subscription',
			'paymob_add_gateway',
		);

		if ( class_exists( 'PaymobAutoGenerate' ) ) {
			$gateways = PaymobAutoGenerate::get_db_gateways_data();
			if ( is_array( $gateways ) ) {
				foreach ( $gateways as $gateway ) {
					if ( ! empty( $gateway->gateway_id ) ) {
						$paymob_sections[] = (string) $gateway->gateway_id;
					}
				}
			}
		}

		if ( in_array( $section, array_unique( $paymob_sections ), true ) ) {
			self::paymob_admin();
		}
	}

	/**
	 * Load admin.css on Edit Order screens for Instant Refund notice styling.
	 */
	public static function maybe_enqueue_order_admin_styles() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			self::paymob_admin();
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wc-orders' === $page ) {
			self::paymob_admin();
		}
	}

	public static function paymob_list_gateways() {
		wp_enqueue_style( 'paymob_list_gateways', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/paymob_list_gateways.css', array(), PAYMOB_VERSION );
	}

	public static function paymob_save_cards() {
		wp_enqueue_style( 'paymob-save-cards', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/save-cards.css', array(), PAYMOB_VERSION );
	}

	public static function paymob_enqueue() {
		wp_enqueue_style( 'paymob-css', plugins_url( PAYMOB_PLUGIN_NAME . '/assets/css/paymob.css' ), array(), PAYMOB_VERSION );
		wp_enqueue_script('paymob-flash-message', plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/js/flash-massege.js', array('jquery'), PAYMOB_VERSION, true);
		wp_localize_script(
			'paymob-flash-message',
			'paymobFlashLogger',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'paymob_log_error_nonce' ),
			)
		);

	}

	public static function confirmation_popup() {
		wp_enqueue_style( 'paymob-css', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/confirmation-popup.css', array(), PAYMOB_VERSION );
	}

	public static function hide_main_gateway_enqueue() {
		wp_enqueue_style( 'hide-main-paymob-css', plugins_url( PAYMOB_PLUGIN_NAME . '/assets/css/checkout-block.css' ), array(), PAYMOB_VERSION );
	}

	public static function paymob_pixel_styles() {
		wp_enqueue_style( 'paymob-pixel-css', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/pixel.css', array(), PAYMOB_VERSION );
	}
}
