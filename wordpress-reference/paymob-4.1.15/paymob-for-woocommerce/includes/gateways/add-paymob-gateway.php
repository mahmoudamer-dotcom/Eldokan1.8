<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds custom sections to WooCommerce checkout settings for Paymob integrations.
 *
 * This file hooks into WooCommerce to add additional sections to the checkout settings,
 * allowing management of Paymob payment gateways.
 *
 * @package Paymob_WooCommerce
 */
// add_filter( 'woocommerce_get_sections_checkout', 'add_paymob_checkout_section', 20 );
/**
 * Add custom sections to WooCommerce checkout settings
 *
 * @param array $sections Existing sections in the WooCommerce checkout settings.
 * @return array Modified sections including Paymob configurations.
 */
function add_paymob_checkout_section( $sections ) {

	return Paymob_Checkout_Section::add_paymob_checkout_section( $sections );
}

// Add fields to the paymob_add_gateway section.
add_filter( 'woocommerce_get_settings_checkout', 'paymob_add_gateway_settings', 10, 2 );
/**
 * Add custom settings to WooCommerce payment gateway.
 *
 * @param array  $settings The WooCommerce payment gateway settings.
 * @param string $current_section The current section being processed.
 * @return array The updated settings array
 */
function paymob_add_gateway_settings( $settings, $current_section ) {
	return Paymob_Gateway_Settings::paymob_add_gateway_settings( $settings, $current_section );
}
// Save custom gateway settings.
add_action( 'woocommerce_update_options_checkout', 'save_paymob_add_gateway_settings' );
/**
 * Save paymob_add_gateway settings.
 *
 * @return void
 */
function save_paymob_add_gateway_settings() {

	return Paymob_Save_Gateway_Settings::save_paymob_add_gateway_settings();
}

add_action('woocommerce_update_options_checkout', 'save_paymob_subscription_settings');

function save_paymob_subscription_settings() {
	return Paymob_Save_Gateway_Settings::save_paymob_subscription_settings();
}
// Save custom gateway settings.
add_action( 'woocommerce_update_options_checkout', 'save_paymob_widget_settings' );
/**
 * Save paymob_add_gateway settings.
 *
 * @return void
 */
function save_paymob_widget_settings() {

	return Paymob_Save_Gateway_Settings::save_paymob_widget_settings();
}
/**
 * Verifies if the integration ID is valid based on the hidden integration ID and currency.
 *
 * @param array  $integration_id_hidden The hidden integration IDs.
 * @param string $integration_id        The integration ID to verify.
 * @param array  &$currency_errors      Reference to the array storing currency errors.
 * @param array  &$ids                  Reference to the array storing IDs.
 * @param string $gateway_id            The gateway ID (optional).
 */
function verify_integration_id( $integration_id_hidden, $integration_id, &$currency_errors, &$ids, $gateway_id = null ) {
	return Paymob_Verify_IntegrationID::verify_integration_id( $integration_id_hidden, $integration_id, $currency_errors, $ids, $gateway_id );
}

/**
 * Checks if a URL exists by validating the headers.
 *
 * @param string $url The URL to check.
 * @return bool True if the URL exists, false otherwise.
 */
function url_exists( $url ) {

	return Paymob_Url_Exists::url_exists( $url );
}
/**
 * Appends a new gateway ID to the Paymob gateway order.
 *
 * @param string $new_gateway_id The ID of the new gateway to append.
 */
function append_gateway_to_paymob_order( $new_gateway_id ) {
	return Paymob_Append_Gateway::append_gateway_to_paymob_order( $new_gateway_id );
}

// Add fields to the paymob_list_gateways section.
add_filter( 'woocommerce_get_settings_checkout', 'paymob_list_gateways_settings', 10, 2 );

/**
 * Adds custom Paymob gateway fields to the WooCommerce settings page.
 *
 * @param array  $settings The current WooCommerce settings.
 * @param string $current_section The current settings section.
 * @return array The updated settings with custom Paymob gateway fields.
 */
function paymob_list_gateways_settings( $settings, $current_section ) {

	return Paymob_List_Gateways_Settings::paymob_list_gateways_section_settings( $settings, $current_section );
}
// Handle the 'table' field type.
add_action( 'woocommerce_admin_field_table', 'create_paymob_custom_gateways_table' );
/**
 * Generates the HTML for the Paymob custom gateways table in the WooCommerce settings.
 *
 * @param array $value The value array containing table configuration data.
 */
function create_paymob_custom_gateways_table( $value ) {
	return Paymob_Custom_Gateways_Table::create_paymob_custom_gateways_table( $value );
}
// Add AJAX handler for resetting the gateways.
add_action( 'wp_ajax_reset_paymob_gateways', 'reset_paymob_gateways' );
/**
 * AJAX handler to reset the Paymob gateways.
 *
 * Retrieves the necessary keys from the Paymob settings and attempts.
 * to reset the available payment methods by making API requests.
 */
function reset_paymob_gateways() {

	return Paymob_Reset_gateways::reset_paymob_gateways();
}

add_action( 'wp_ajax_save_paymob_gateway_order', 'save_paymob_gateway_order' );
/**
 * Saves the custom order of Paymob payment gateways.
 *
 * This function is responsible for handling the AJAX request to save the new
 * order of Paymob gateways, ensuring that the updated order is stored in the
 * appropriate WooCommerce settings.
 */
function save_paymob_gateway_order() {
	return Paymob_Save_Gateway_Order::save_paymob_gateway_order();
}
add_filter( 'woocommerce_available_payment_gateways', 'apply_paymob_gateway_order' );

/**
 * Reorders Paymob gateways based on a custom saved order.
 *
 * This function applies the saved order of the Paymob gateways on the checkout page. It ensures
 * that Paymob gateways are listed in the desired order and updates the WooCommerce gateway
 * order settings accordingly.
 *
 * @param array $available_gateways The available payment gateways.
 * @return array The reordered payment gateways.
 */
function apply_paymob_gateway_order( $available_gateways ) {
	return Paymob_Apply_Gateway_Order::apply_paymob_gateway_order( $available_gateways );
}
// AJAX to handle gateway deletion.
add_action( 'wp_ajax_delete_gateway', 'delete_gateway' );
/**
 * Handles the AJAX request to delete a payment gateway.
 *
 * This function handles the deletion of payment gateway files and database records. It performs
 * the following actions:
 * - Deletes JavaScript and PHP files associated with the gateway.
 * - Removes the gateway record from the database.
 * - Deletes the gateway settings option.
 * - Removes the gateway from the Paymob order list.
 *
 * @return void
 */
function delete_gateway() {
	return Paymob_Delete_Gateway::delete_gateway();
}
/**
 * Removes a specified gateway from the Paymob order array.
 *
 * This function retrieves the current order array of Paymob gateways, removes the specified gateway ID
 * if it exists, and then updates the order array without gaps in keys.
 *
 * @param string $gateway_id_to_remove The ID of the gateway to remove from the order array.
 * @return void
 */
function remove_gateway_from_paymob_order( $gateway_id_to_remove ) {
	return Paymob_Remove_Gateway_From_Order::remove_gateway_from_paymob_order( $gateway_id_to_remove );
}
// AJAX to handle gateway enabling/disabling.
add_action( 'wp_ajax_webhook_url', 'webhook_url' );
/**
 * Toggles the status of a payment gateway.
 *
 * This function handles the AJAX request to enable or disable a payment gateway.
 * It checks the current status, validates required API keys, and updates the gateway's status accordingly.
 *
 * @return void
 */
function webhook_url() {

	return Paymob_Webhook_Url::webhook_url();
}
// AJAX to handle gateway enabling/disabling.
add_action( 'wp_ajax_toggle_gateway', 'toggle_gateway' );
/**
 * Toggles the status of a payment gateway.
 *
 * This function handles the AJAX request to enable or disable a payment gateway.
 * It checks the current status, validates required API keys, and updates the gateway's status accordingly.
 *
 * @return void
 */
function toggle_gateway() {
	return Paymob_Toggle_Gateway::toggle_gateway();
}
/**
 * Registers the framework with Paymob if enabled.
 *
 * This function checks the main Paymob settings and, if the gateway is enabled, collects
 * integration IDs from all active gateways and registers the framework with Paymob.
 *
 * @return void
 */
function register_frameworks() {
	return Paymob_Register_Frameworks::register_frameworks();
}

/**
 * Adds custom CSS to hide specific gateways and elements.
 *
 * This function generates and outputs CSS to hide specific payment gateways and
 * certain elements if the tab is 'checkout'. The CSS is fetched based on gateway IDs
 * and additional styles are added for specific elements.
 *
 * @return void
 */
function gateways_to_hide_css() {

	return Paymob_Gateways_HideCss::gateways_to_hide_css();
}
add_action( 'admin_head', 'gateways_to_hide_css' );

add_action( 'admin_enqueue_scripts', 'enqueue_paymob_list_gateways_styles' );
/**
 * Enqueue custom CSS for Paymob gateways list in WooCommerce admin.
 */
function enqueue_paymob_list_gateways_styles() {

	return Paymob_List_Gateways_Style::enqueue_paymob_list_gateways_styles();
}

add_action( 'admin_enqueue_scripts', 'enqueue_paymob_admin_scripts' );
add_action( 'admin_enqueue_scripts', array( 'Paymob_Style', 'maybe_enqueue_checkout_tabs_styles' ), 20 );
add_action( 'admin_enqueue_scripts', array( 'Paymob_Style', 'maybe_enqueue_order_admin_styles' ), 20 );
/**
 * Enqueue JavaScript to handle AJAX for gateway deletion and enabling/disabling.
 */
function enqueue_paymob_admin_scripts() {
	return Paymob_Admin_Scripts::enqueue_paymob_admin_scripts();
}

add_action( 'admin_head', 'hide_save_changes_button_in_paymob_list_gateways_section' );
/**
 * Hide save changes button in paymob_list_gateways section.
 */
function hide_save_changes_button_in_paymob_list_gateways_section() {

	return Paymob_Hide_Save_Button::hide_save_changes_button_in_paymob_list_gateways_section();
}

// Add fields to the paymob_list_gateways section.
add_filter( 'woocommerce_get_settings_checkout', 'paymob_widget_settings', 10, 2 );

/**
 * Adds custom Paymob gateway fields to the WooCommerce settings page.
 *
 * @param array  $settings The current WooCommerce settings.
 * @param string $current_section The current settings section.
 * @return array The updated settings with custom Paymob gateway fields.
 */
function paymob_widget_settings( $settings, $current_section ) {

	return Paymob_Widget_Settings::paymob_widget_setting( $settings, $current_section );
}

add_filter( 'woocommerce_get_settings_checkout', 'paymob_subscription_settings', 10, 2 );

/**
 * Load Paymob Subscription settings section.
 *
 * @param array  $settings Current settings array.
 * @param string $current_section Current settings section ID.
 * @return array
 */
function paymob_subscription_settings( $settings, $current_section ) {
	return Paymob_Gateway_Settings::paymob_subscription_settings( $settings, $current_section );
}
