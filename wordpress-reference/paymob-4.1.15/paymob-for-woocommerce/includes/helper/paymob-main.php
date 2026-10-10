<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('wp_ajax_connect_paymob_account', 'connect_paymob_account_handler');
function connect_paymob_account_handler() {
  return Paymob_Main_Connect_Account::connect_paymob_account_handler();
}
add_action( 'admin_enqueue_scripts', 'enqueue_paymob_main_scripts' );
function enqueue_paymob_main_scripts() {
    return Paymob_Main_Scripts::enqueue_paymob_main_scripts();
}

add_action( 'admin_enqueue_scripts', 'enqueue_paymob_pixel_script' );
function enqueue_paymob_pixel_script() {
    return Paymob_Main_Scripts::enqueue_paymob_pixel_script();
}


add_action( 'admin_footer', 'manual_setup_modal_html' );
/**
 * Adds custom modal HTML to the admin footer for confirmation before disabling the Paymob gateway.
 *
 * This function hooks into the 'admin_footer' action and includes the HTML for a confirmation modal
 * that appears on the WooCommerce settings page when the 'checkout' tab is selected.
 */
function manual_setup_modal_html() {

	return Paymob_Manual_Setup_Model::manual_setup_modal_html();
}
add_action( 'admin_footer', 'disconnect_modal_html' );
/**
 * Adds custom modal HTML to the admin footer for confirmation before disabling the Paymob gateway.
 *
 * This function hooks into the 'admin_footer' action and includes the HTML for a confirmation modal
 * that appears on the WooCommerce settings page when the 'checkout' tab is selected.
 */
function disconnect_modal_html() {

	return Paymob_Disconnect_Model::disconnect_modal_html();
}

add_action( 'admin_footer', 'change_mode_modal_html' );
/**
 * Adds custom modal HTML to the admin footer for confirmation before disabling the Paymob gateway.
 *
 * This function hooks into the 'admin_footer' action and includes the HTML for a confirmation modal
 * that appears on the WooCommerce settings page when the 'checkout' tab is selected.
 */
function change_mode_modal_html() {

	return Paymob_Change_Mode_Model::change_mode_modal_html();
}

add_action('wp_ajax_manual_setup_save_keys', 'manual_setup_save_keys');

function manual_setup_save_keys() {

  return Paymob_Manual_Setup_Save::manual_setup_save_keys();
	
}

add_action('wp_ajax_disconnect_save_keys', 'disconnect_save_keys');

function disconnect_save_keys() {

  return Paymob_Disconnect_Save::disconnect_save_keys();
	
}

add_action('wp_ajax_change_mode_save', 'change_mode_save');

function change_mode_save() {

  return Paymob_Change_Mode_Save::change_mode_save();
	
}