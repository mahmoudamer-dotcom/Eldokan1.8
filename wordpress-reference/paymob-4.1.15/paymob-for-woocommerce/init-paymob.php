<?php
/**
 * Plugin Name: Paymob for WooCommerce
 * Description: PayMob Payment Gateway Integration for WooCommerce.
 * Version: 4.1.15
 * Author: Paymob
 * Author URI: https://paymob.com
 * Text Domain: paymob-for-woocommerce
 * Domain Path: /i18n/languages
 * Requires PHP: 7.0
 * Requires at least: 5.0
 * Requires Plugins: woocommerce
 * WC requires at least: 4.0
 * WC tested up to: 11.1
 * Tested up to: 7.1
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Copyright: © 2024 Paymob
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Early HTTP 403 when a log path is routed through PHP (Nginx often serves *.log as static files —
// those legacy files are purged on plugins_loaded; PHP-guarded logs self-deny with 403).
if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
	$paymob_request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$paymob_req_path    = wp_parse_url( rawurldecode( $paymob_request_uri ), PHP_URL_PATH );
	if ( is_string( $paymob_req_path )
		&& (
			preg_match( '#/wp-content/uploads/wc-logs/.+\.log$#i', $paymob_req_path )
			|| preg_match( '#/wp-content/uploads/paymob-private-logs/#i', $paymob_req_path )
			|| preg_match( '#/(?:paymob(?:-auth|-pixel|-token|-subscription)?)\.log$#i', $paymob_req_path )
		)
	) {
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		exit( 'Forbidden' );
	}
}

if ( ! defined( 'PAYMOB_VERSION' ) ) {
	define( 'PAYMOB_VERSION', '4.1.15');
}
if ( ! defined( 'PAYMOB_PLUGIN' ) ) {
	define( 'PAYMOB_PLUGIN', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'PAYMOB_PLUGIN_PATH' ) ) {
	// Use dirname( __FILE__ ) (not plugin_dir_path) so PHPStan can resolve include paths.
	define( 'PAYMOB_PLUGIN_PATH', dirname( __FILE__ ) . '/' );
}
if ( ! defined( 'PAYMOB_PLUGIN_NAME' ) ) {
	define( 'PAYMOB_PLUGIN_NAME', dirname( PAYMOB_PLUGIN ) );
}

include_once __DIR__ . '/src/class_wc_paymob_initDependencies.php';
class Init_Paymob {
	protected static $instance = null;
	protected $gateways;

	public function __construct() {
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
		add_action( 'activate_' . PAYMOB_PLUGIN, array( $this, 'install' ), 0 );
		// Set redirect flag upon activation of PayMob plugin
		add_action( 'activated_plugin', array( $this, 'set_redirect_flag_on_activation' ) );
		add_action( 'plugins_loaded', array( $this, 'load' ), 20 );
		// Block / purge publicly reachable Paymob logs as early as possible (Nginx ignores .htaccess).
		add_action( 'plugins_loaded', array( $this, 'harden_paymob_logging' ), 1 );
		add_action( 'activate_' . PAYMOB_PLUGIN, array( $this, 'harden_paymob_logging' ), 1 );
		$subscription_settings = get_option('woocommerce_paymob-subscription_settings', []);
		$allow_cancel = (!empty($subscription_settings['allow_cancel']) && $subscription_settings['allow_cancel'] === 'yes');

		add_filter( 'wcs_view_subscription_actions', function( $actions, $subscription ) use ( $allow_cancel ) {
			unset( $actions['subscription_renewal_early'] );
			unset( $actions['change_payment_method'] );
			unset( $actions['resubscribe'] );

			// Only unset cancel if NOT allowed
			if ( ! $allow_cancel ) {
				unset( $actions['cancel'] );
			}

			return $actions;
		}, 99, 2 );
		
		add_action('admin_notices', function () {
			if ($notice = get_transient('paymob_flash_notice')) {
				$type = $notice['type'] === 'error' ? 'error' : 'updated';
				echo '<div class="' . esc_attr($type) . ' notice is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
				delete_transient('paymob_flash_notice');
			}
		});
		// Check redirect flag and perform redirect with high priority
		add_action( 'admin_init', array( $this, 'redirect_after_activation' ), 1 );
		// Declare compatibility with WooCommerce features
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
				}
			}
		);
	
		
	}

	public static function add_row_meta( $links, $file ) {
		return WC_Paymob_Row_Meta::add_row_meta( $links, $file );
	}

	public static function install() {
		return WC_Paymob_Install::install();
	}

	// Set a flag in options table to trigger redirect after PayMob plugin activation
	function set_redirect_flag_on_activation( $plugin ) {
		return WC_Paymob_RedirectFlag::set_redirect_flag_on_activation($plugin);
	}

	// Check the redirect flag and perform redirect if true
	function redirect_after_activation() {
		
		return WC_Paymob_RedirectUrl::redirect_after_activation();
	}

	

	public static function uninstall() {
		return WC_Paymob_UnInstall::uninstall();
	}

	public function load() {
		return WC_Paymob_Loading::load();
	}

	/**
	 * Ensure Paymob debug logs are not publicly downloadable (HTTP 403 / purged legacy *.log).
	 */
	public function harden_paymob_logging() {
		if ( ! class_exists( 'Paymob' ) ) {
			$paymob_helper = PAYMOB_PLUGIN_PATH . 'includes/helper/paymob.php';
			if ( file_exists( $paymob_helper ) ) {
				include_once $paymob_helper;
			}
		}
		if ( class_exists( 'Paymob' ) && method_exists( 'Paymob', 'harden_logging' ) ) {
			Paymob::harden_logging();
		}
	}
}

register_uninstall_hook( __FILE__, array( 'Init_Paymob', 'uninstall' ) );
// ✅ Add columns to WooCommerce orders table
add_filter('manage_edit-shop_order_columns','paymob_order_list_columns');
add_filter('manage_woocommerce_page_wc-orders_columns', 'paymob_order_list_columns');

function paymob_order_list_columns($columns) {
    $columns["paymob_merchant_order_id"] = __( 'Paymob Merchant Order ID', 'paymob-for-woocommerce' );
    $columns["paymob_transaction_id"] = __( 'Paymob Transaction ID', 'paymob-for-woocommerce' );
    return $columns;
}

// ✅ Output data for the custom columns
add_action('manage_shop_order_posts_custom_column', 'paymob_order_columns_data', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'paymob_order_columns_data', 10, 2);

function paymob_order_columns_data($colName, $orderId) {
    $order = wc_get_order($orderId);
    $paymobMerchantOrderID = $order->get_meta('PaymobMerchantOrderID'); // ✅ Correct meta key
    $paymobTransactionId = $order->get_meta('PaymobTransactionId');     // ✅ Correct meta key

    if ($colName === 'paymob_merchant_order_id') {
        echo !empty($paymobMerchantOrderID) ? esc_html($paymobMerchantOrderID) : "---";
    }

    if ($colName === 'paymob_transaction_id') {
        echo !empty($paymobTransactionId) ? esc_html($paymobTransactionId) : "---";
    }
}

// ✅ Change "Sign up now" to "Subscribe Now" globally
add_filter( 'gettext', 'paymob_change_subscription_button_text', 20, 3 );

function paymob_change_subscription_button_text( $translated_text, $text, $domain ) {
	if ( 'woocommerce-subscriptions' === $domain || 'woocommerce' === $domain ) {
		if ( $translated_text === 'Sign up now' ) {
			return 'Subscribe Now';
		}
	}
	return $translated_text;
}

add_filter( 'woocommerce_add_to_cart_validation', 'prevent_multiple_subscription_products', 10, 3 );

function prevent_multiple_subscription_products( $passed, $product_id, $quantity ) {
	if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product_id ) ) {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) && $cart_item['product_id'] != $product_id ) {
				wc_add_notice( __( 'You cannot add multiple subscription products to the cart.', 'paymob-for-woocommerce' ), 'error' );
				return false;
			}
		}
	}
	return $passed;
}

add_filter( 'woocommerce_add_to_cart_validation', 'prevent_mixed_subscription_checkout', 20, 3 );

function prevent_mixed_subscription_checkout( $passed, $product_id, $quantity ) {
	if ( ! $passed ) {
		return false;
	}

	if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
		return $passed;
	}

	// Check if the current product being added is a subscription (simple or variable)
	$is_subscription_product = WC_Subscriptions_Product::is_subscription( $product_id );

	// Get current cart
	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$cart_product_id = $cart_item['product_id'];
		$cart_variation_id = $cart_item['variation_id'];

		// Check both variation and parent
		$cart_item_id_to_check = $cart_variation_id > 0 ? $cart_variation_id : $cart_product_id;

		// Check if this item in cart is a subscription
		$is_cart_item_subscription = WC_Subscriptions_Product::is_subscription( $cart_item_id_to_check );

		// If both are subscriptions, allow unless they are both variable subs or mixed types
		if ( $is_cart_item_subscription && $is_subscription_product ) {
			// Block adding if both are subscriptions and one is variable
			$product_obj = wc_get_product( $product_id );
			$cart_product_obj = wc_get_product( $cart_item_id_to_check );

			$is_variable_subscription = $product_obj && $product_obj->is_type( 'variable-subscription' );
			$cart_has_variable_sub    = $cart_product_obj && $cart_product_obj->is_type( 'variable-subscription' );

			if ( $is_variable_subscription || $cart_has_variable_sub ) {
				wc_add_notice( __( 'You can only have one variable subscription product in the cart at a time.', 'paymob-for-woocommerce' ), 'error' );
				return false;
			}
		}

		// If one is subscription and the other is not, block
		if ( $is_cart_item_subscription !== $is_subscription_product ) {
			wc_add_notice( __( 'You can either add a subscription product or a non-subscription product to the cart — not both.', 'paymob-for-woocommerce' ), 'error' );
			return false;
		}
	}

	return $passed;
}



function paymob_check_subscription_product_update( $post_id ) {

    // Get current & old subscription period data
    $current_period          = get_post_meta( $post_id, '_subscription_period', true );
    $current_period_interval = get_post_meta( $post_id, '_subscription_period_interval', true );
    $old_period              = get_post_meta( $post_id, '_paymob_saved_period', true );
    $old_period_interval     = get_post_meta( $post_id, '_paymob_saved_period_interval', true );

    // If frequency changed, remove Paymob plan meta
    if ( $current_period !== $old_period || $current_period_interval !== $old_period_interval ) {
        delete_post_meta( $post_id, '_paymob_plan_id' );
        delete_post_meta( $post_id, '_paymob_start_date' );
    }
// var_dump(777);die;
    // Save current period as "old" for next check
    update_post_meta( $post_id, '_paymob_saved_period', $current_period );
    update_post_meta( $post_id, '_paymob_saved_period_interval', $current_period_interval );
}

// Products
add_action( 'woocommerce_process_product_meta', 'paymob_check_subscription_product_update', 20 );

// Variations
add_action( 'woocommerce_save_product_variation', 'paymob_check_subscription_product_update', 20 );



new Init_Paymob();
