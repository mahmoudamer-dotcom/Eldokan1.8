<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add a custom tab for 'Saved Cards' to My Account page
add_filter( 'woocommerce_account_menu_items', 'paymob_add_saved_cards_tab', 40 );
function paymob_add_saved_cards_tab( $menu_links ) {
	return Paymob_Save_Cards_Tab::paymob_add_saved_cards_tab( $menu_links );
}

// Add the endpoint for the 'Saved Cards' tab
add_action( 'init', 'paymob_add_saved_cards_endpoint' );
function paymob_add_saved_cards_endpoint() {
	Paymob_Save_Cards_Endpoints::paymob_add_saved_cards_endpoint();
}
// Ensure WooCommerce recognizes the 'saved-cards' endpoint
add_filter( 'woocommerce_get_query_vars', 'paymob_add_saved_cards_query_vars', 0 );
function paymob_add_saved_cards_query_vars( $vars ) {
	return Paymob_Save_Cards_Query_Vars::paymob_add_saved_cards_query_vars( $vars );
}

// Set the title for the custom tab
add_filter( 'the_title', 'paymob_saved_cards_title', 10, 2 );
function paymob_saved_cards_title( $title, $id ) {

	return Paymob_Save_Cards_Title::paymob_saved_cards_title( $title, $id );
}

// Content for the 'Saved Cards' tab
add_action( 'woocommerce_account_saved-cards_endpoint', 'paymob_display_saved_cards' );
function paymob_display_saved_cards() {

	return Paymob_Display_Save_Cards::paymob_display_saved_cards();
}

// Hook to handle card deletion
add_action( 'template_redirect', 'paymob_handle_card_deletion' );
function paymob_handle_card_deletion() {

	return Paymob_Handle_Card_Deletion::paymob_handle_card_deletions();
}

add_action( 'wp_footer', 'paymob_add_delete_confirmation_modal' );
function paymob_add_delete_confirmation_modal() {

	return Paymob_Delete_Confirmation_Model::paymob_add_delete_confirmation_modal();
}
add_action( 'wp_enqueue_scripts', 'paymob_enqueue_saved_cards_styles' );
function paymob_enqueue_saved_cards_styles() {

	return Paymob_Enqueue_Style::paymob_enqueue_saved_cards_styles();
}
