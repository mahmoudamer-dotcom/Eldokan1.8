<?php
/**
 * Detect when Paymob should run heavy bootstrap (gateways, DB, API sync).
 *
 * Conditional tags like is_cart() are not available on plugins_loaded,
 * so early requests use URI/AJAX detection and deferred checks on wp/woocommerce_init.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paymob_Context {

	/**
	 * Admin screens that need full gateway bootstrap.
	 *
	 * @return bool
	 */
	public static function is_admin_gateway_context() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'wc-settings', 'wc-orders', 'paymob-error-logs' ), true ) ) {
			return true;
		}

		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
			if ( 'shop_order' === $post_type ) {
				return true;
			}
			if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
				$post_id = absint( $_GET['post'] );
				if ( $post_id && 'shop_order' === get_post_type( $post_id ) ) {
					return true;
				}
			}
		}

		// Any Paymob admin-ajax action.
		if ( wp_doing_ajax() ) {
			return self::is_paymob_ajax_request();
		}

		return false;
	}

	/**
	 * Paymob / WooCommerce API callback (webhook, return URL).
	 *
	 * @return bool
	 */
	public static function is_paymob_api_request() {
		$uri = self::get_request_uri();
		if ( '' === $uri ) {
			return false;
		}

		if ( false !== strpos( $uri, 'wc-api' ) ) {
			return true;
		}

		if ( isset( $_GET['wc-api'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * WooCommerce Blocks Store API (block checkout/cart).
	 *
	 * @return bool
	 */
	public static function is_store_api_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$uri = self::get_request_uri();
			return ( false !== strpos( $uri, '/wp-json/wc/store/' ) );
		}

		return false;
	}

	/**
	 * Frontend/admin AJAX used by Paymob checkout or settings.
	 *
	 * @return bool
	 */
	public static function is_paymob_ajax_request() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '' === $action ) {
			return false;
		}

		if ( 0 === strpos( $action, 'paymob' ) ) {
			return true;
		}

		$paymob_ajax_actions = array(
			'update_pixel_data',
			'create_order',
			'get_order_id_from_session',
			'reset_paymob_gateways',
			'save_paymob_gateway_order',
			'delete_gateway',
			'webhook_url',
			'toggle_gateway',
		);

		return in_array( $action, $paymob_ajax_actions, true );
	}

	/**
	 * Cron / WP-CLI (subscription renewals, etc.).
	 *
	 * @return bool
	 */
	public static function is_background_request() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return false;
	}

	/**
	 * Cart, checkout, my-account via request URI (safe before $wp_rewrite exists).
	 *
	 * Does NOT call wc_get_page_permalink() — that requires rewrite rules and
	 * fatals on plugins_loaded when $wp_rewrite is still null.
	 *
	 * @return bool
	 */
	public static function is_wc_storefront_request() {
		if ( is_admin() || wp_doing_ajax() ) {
			return false;
		}

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return false;
		}

		$request_uri = strtolower( self::get_request_uri() );

		// Plain permalinks: ?page_id=123
		if ( isset( $_GET['page_id'] ) ) {
			$requested_page_id = absint( $_GET['page_id'] );
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $wc_page ) {
				$wc_page_id = wc_get_page_id( $wc_page );
				if ( $requested_page_id > 0 && $requested_page_id === $wc_page_id ) {
					return true;
				}
			}
		}

		// Pretty permalinks: match page slug in URI (no get_permalink needed).
		if ( '' !== $request_uri ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $wc_page ) {
				$page_id = wc_get_page_id( $wc_page );
				if ( $page_id <= 0 ) {
					continue;
				}
				$post = get_post( $page_id );
				if ( ! $post || empty( $post->post_name ) ) {
					continue;
				}
				if ( false !== strpos( $request_uri, $post->post_name ) ) {
					return true;
				}
			}

			if ( false !== strpos( $request_uri, 'order-pay' ) || false !== strpos( $request_uri, 'order-received' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Safe to run on plugins_loaded (before conditional tags exist).
	 *
	 * @return bool
	 */
	public static function should_bootstrap_early() {
		if ( self::is_admin_gateway_context() ) {
			return true;
		}

		if ( self::is_wc_storefront_request() ) {
			return true;
		}

		if ( self::is_paymob_api_request() ) {
			return true;
		}

		if ( self::is_store_api_request() ) {
			return true;
		}

		if ( self::is_paymob_ajax_request() ) {
			return true;
		}

		if ( self::is_background_request() ) {
			return true;
		}

		return false;
	}

	/**
	 * Safe after wp / WooCommerce conditional tags are set.
	 *
	 * @return bool
	 */
	public static function should_bootstrap_deferred() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			if ( class_exists( 'Paymob_Affordability_Widget' ) && Paymob_Affordability_Widget::is_enabled() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether gateway classes, sync, and per-gateway hooks should load.
	 *
	 * @return bool
	 */
	public static function should_load_gateways() {
		return self::should_bootstrap_early() || self::should_bootstrap_deferred();
	}

	/**
	 * Frontend pages where global Paymob CSS/JS is needed.
	 *
	 * @return bool
	 */
	public static function should_enqueue_frontend_assets() {
		if ( is_admin() ) {
			return false;
		}

		if ( self::should_bootstrap_deferred() || self::is_wc_storefront_request() ) {
			return true;
		}

		if ( self::is_paymob_api_request() ) {
			return true;
		}

		return false;
	}

	/**
	 * @return string
	 */
	private static function get_request_uri() {
		return isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
	}
}
