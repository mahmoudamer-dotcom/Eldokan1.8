<?php
/**
 * Plugin Name: ElDokan Customer API
 * Description: Stable customer-facing API contract for ElDokan. WooCommerce is the current data source behind the API.
 * Version: 0.8.0
 * Author: ElDokan
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ELDOKAN_CUSTOMER_API_VERSION', '0.8.0');
define('ELDOKAN_CUSTOMER_API_FILE', __FILE__);
define('ELDOKAN_CUSTOMER_API_DIR', plugin_dir_path(__FILE__));

require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-utils.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-account.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-address-store.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-addresses.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-auth.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-language.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-cache.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-sellers.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-categories.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-taxonomies.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-products.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-home.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-wishlist.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-cart.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-checkout.php';
// A3 order placement only.
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-order-store.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-orders.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-checkout-recovery.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-checkout-cli.php';
ElDokan_Customer_API_Checkout_CLI::register();
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ELDOKAN_CUSTOMER_API_FILE, true);
    }
});
register_activation_hook(__FILE__, ['ElDokan_Customer_API_Order_Store', 'install']);
add_action('plugins_loaded', ['ElDokan_Customer_API_Order_Store', 'maybe_upgrade'], 6);
// End A3 order placement.
// A4 native hosted payment and private order reads.
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-payment-store.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-paymob.php';
require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-order-read.php';
register_activation_hook(__FILE__, ['ElDokan_Customer_API_Payment_Store', 'install']);
add_action('plugins_loaded', ['ElDokan_Customer_API_Payment_Store', 'maybe_upgrade'], 7);
add_filter('eldokan_checkout_transaction_tables', ['ElDokan_Customer_API_Payment_Store', 'participants']);
add_filter('eldokan_customer_api_allowed_origins', ['ElDokan_Customer_API_Paymob', 'allowed_origins']);
add_action('woocommerce_api_paymob_callback', ['ElDokan_Customer_API_Paymob', 'browser_return'], PHP_INT_MIN);
add_action('woocommerce_api_paymob_callback', ['ElDokan_Customer_API_Paymob', 'native_callback_admission'], PHP_INT_MIN + 1);
// End A4 native hosted payment and private order reads.

register_activation_hook(__FILE__, ['ElDokan_Customer_API_Address_Store', 'install']);
add_action('plugins_loaded', ['ElDokan_Customer_API_Address_Store', 'maybe_upgrade'], 5);

add_action('plugins_loaded', ['ElDokan_Customer_API_Cache', 'init']);
add_action('plugins_loaded', ['ElDokan_Customer_API_Auth', 'init']);
add_action('plugins_loaded', ['ElDokan_Customer_API', 'init']);
