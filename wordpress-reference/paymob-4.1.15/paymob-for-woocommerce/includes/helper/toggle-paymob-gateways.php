<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter available payment gateways based on currency and other conditions.
 *
 * @package Paymob_WooCommerce
 */

add_filter( 'woocommerce_available_payment_gateways', 'filter_payment_gateways_based_on_currency' );

/**
 * Filter available payment gateways based on currency and other conditions.
 *
 * @param array $available_gateways List of available payment gateways.
 * @return array Filtered list of available payment gateways.
 */
function filter_payment_gateways_based_on_currency( $available_gateways ) {

	return Paymob_Filter_currency::filter_payment_gateways_based_on_currency( $available_gateways );
}
/**
 * Checks if the integration ID matches the store currency and updates mismatch arrays accordingly.
 *
 * @param string $integration_id The integration ID to check.
 * @param string $gateway_id The gateway ID associated with the integration ID.
 * @param array  &$mismatched_ids Array to collect gateway IDs with mismatched integration IDs.
 * @param array  &$mismatched_currencies Array to collect currencies associated with mismatched integration IDs.
 * @param array  &$mismatched_integration_ids Array to collect mismatched integration IDs.
 */
function check_integration_id( $integration_id, $gateway_id, &$mismatched_ids, &$mismatched_currencies, &$mismatched_integration_ids ) {

	return Paymob_Check_IntergrationID::check_integration_id( $integration_id, $gateway_id, $mismatched_ids, $mismatched_currencies, $mismatched_integration_ids );
}
/**
 * Checks if the integration ID matches the store's current currency.
 *
 * @param string $integration_id The integration ID to check.
 * @param string $currency The currency of the store.
 * @return bool True if the integration ID matches the store currency, false otherwise.
 */
function check_integration_id_match( $integration_id, $currency ) {

	return Paymob_Check_IntergrationMatch::check_integration_id_match( $integration_id, $currency );
}

/**
 * Enqueues the confirmation popup CSS and JavaScript for disabling a payment gateway.
 *
 * This function checks if the 'tab' parameter in the URL is 'checkout', and if so, it enqueues
 * the necessary CSS and JavaScript files for displaying a confirmation popup when disabling
 * a payment gateway. It also localizes the script with relevant data.
 */
function enqueue_disable_gateway_confirmation_script() {
	return Paymob_Disable_Confirmation::enqueue_disable_gateway_confirmation_script();
}
add_action( 'admin_footer', 'enqueue_disable_gateway_confirmation_script' );

// Hook into the admin_footer action to include custom modal HTML.
add_action( 'admin_footer', 'add_custom_modal_html' );
/**
 * Adds custom modal HTML to the admin footer for confirmation before disabling the Paymob gateway.
 *
 * This function hooks into the 'admin_footer' action and includes the HTML for a confirmation modal
 * that appears on the WooCommerce settings page when the 'checkout' tab is selected.
 */
function add_custom_modal_html() {

	return Paymob_Custom_Model::add_custom_modal_html();
}

add_action( 'admin_footer', 'webhook_modal_html' );
/**
 * Adds custom modal HTML to the admin footer for confirmation before disabling the Paymob gateway.
 *
 * This function hooks into the 'admin_footer' action and includes the HTML for a confirmation modal
 * that appears on the WooCommerce settings page when the 'checkout' tab is selected.
 */
function webhook_modal_html() {

	return Paymob_Webhook_Model::webhook_modal_html();
}


add_action('wp_ajax_save_webhook_callbacks', 'save_webhook_callbacks_callback');

function save_webhook_callbacks_callback() {
	return Paymob_Webhook_Update::save_webhook_callbacks_callback();
	
}
// Hook into the AJAX action to handle gateway toggling.
add_action( 'wp_ajax_paymob_toggle_gateway', 'handle_toggle_gateway' );
/**
 * Handles AJAX requests to toggle the Paymob gateway.
 *
 * This function hooks into the 'wp_ajax_paymob_toggle_gateway' action and processes the AJAX
 * request to enable or disable the Paymob gateway based on the provided parameters.
 */
function handle_toggle_gateway() {
	return Paymob_Handel_Toggle::handle_toggle_gateway();
}

add_action('wp_ajax_add_to_cart', 'custom_add_to_cart');
add_action('wp_ajax_nopriv_add_to_cart', 'custom_add_to_cart'); // Allow guests

function custom_add_to_cart() {
    return Paymob_Custom_Add_To_Cart::custom_add_to_cart();
}
