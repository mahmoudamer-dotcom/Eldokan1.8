<?php
class Paymob_Custom_Add_To_Cart {

    public static function custom_add_to_cart() {
        check_ajax_referer('your_nonce_action', '_ajax_nonce'); // Security check
        $product_id= Paymob::filterVar( 'product_id', 'POST' );
        $quantity=Paymob::filterVar( 'quantity', 'POST' );
        if (!isset($product_id)) {
            
            wp_send_json_error(['message' => 'Product ID is missing']);
        }

        $product_id = absint($product_id);
        $quantity = isset($quantity) ? absint($quantity) : 1;

        $cart = WC()->cart;
        // Check if the product is already in the cart
        $found = false;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                $found = true;
                break;
            }
        }

        // If not found, add to cart
        if (!$found) {
            $cart->add_to_cart($product_id, $quantity);
        }

        wp_send_json_success(['message' => 'Product added to cart']);
    }
}