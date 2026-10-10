<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Wishlist {

    const META_KEY = '_eldokan_customer_wishlist_product_ids';

    public static function get(WP_REST_Request $request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, false);
        if (is_wp_error($user)) {
            return $user;
        }

        return self::serialize($user->ID, $request);
    }

    public static function add(WP_REST_Request $request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }

        $public_id = sanitize_text_field((string) $request->get_param('product_id'));
        $product_id = ElDokan_Customer_API_Utils::parse_opaque_id('prd', $public_id);
        if (!$product_id) {
            return new WP_Error('invalid_product_id', 'Invalid product ID.', ['status' => 400]);
        }

        $card = ElDokan_Customer_API_Products::card_by_internal_id(
            $product_id,
            ElDokan_Customer_API_Utils::requested_language($request)
        );
        if (is_wp_error($card)) {
            return $card;
        }

        $ids = self::ids($user->ID);
        $changed = !in_array($product_id, $ids, true);
        if ($changed) {
            $ids[] = $product_id;
            update_user_meta($user->ID, self::META_KEY, array_values(array_unique($ids)));
        }

        return self::mutation($user->ID, $request, $changed);
    }

    public static function remove(WP_REST_Request $request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }

        $product_id = ElDokan_Customer_API_Utils::parse_opaque_id(
            'prd',
            sanitize_text_field((string) $request->get_param('product_id'))
        );
        if (!$product_id) {
            return new WP_Error('invalid_product_id', 'Invalid product ID.', ['status' => 400]);
        }

        $ids = self::ids($user->ID);
        $changed = in_array($product_id, $ids, true);
        if ($changed) {
            $ids = array_values(array_diff($ids, [$product_id]));
            update_user_meta($user->ID, self::META_KEY, $ids);
        }

        return self::mutation($user->ID, $request, $changed);
    }

    private static function mutation($user_id, WP_REST_Request $request, $changed) {
        $wishlist = self::serialize($user_id, $request);
        if (is_wp_error($wishlist)) {
            return $wishlist;
        }
        $wishlist['changed'] = (bool) $changed;
        return $wishlist;
    }

    private static function serialize($user_id, WP_REST_Request $request) {
        $items = [];
        $visible_ids = [];
        $language = ElDokan_Customer_API_Utils::requested_language($request);
        $stored_ids = self::ids($user_id);

        foreach ($stored_ids as $product_id) {
            // Membership is language-independent. Only prune an item when its
            // stored source product is no longer a valid public product.
            $source_card = ElDokan_Customer_API_Products::card_by_internal_id($product_id);
            if (is_wp_error($source_card)) {
                continue;
            }
            $visible_ids[] = $product_id;

            $card = ElDokan_Customer_API_Products::card_by_internal_id($product_id, $language);
            if (is_wp_error($card)) {
                // A missing requested-language translation hides the item from
                // this localized response but must not delete Wishlist membership.
                continue;
            }
            $items[] = $card;
        }

        if ($visible_ids !== $stored_ids) {
            update_user_meta($user_id, self::META_KEY, $visible_ids);
        }

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    private static function ids($user_id) {
        $ids = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }
}
