<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Sellers {

    public static function by_public_id($public_id) {
        $seller_id = ElDokan_Customer_API_Utils::parse_opaque_id(
            'sel',
            $public_id
        );

        if (!$seller_id) {
            return new WP_Error(
                'invalid_seller_id',
                'Invalid seller ID.',
                ['status' => 400]
            );
        }

        $seller = self::serialize(get_user_by('ID', $seller_id));
        if ($seller !== null) {
            return $seller;
        }

        return new WP_Error(
            'seller_not_found',
            'Seller not found.',
            ['status' => 404]
        );
    }

    public static function for_product($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return null;
        }

        // Written by the ElDokan seller dashboard when a seller owns a product.
        // Never infer commercial ownership from the editor, WCFM or generic metadata.
        $seller_id = absint(get_post_meta($product_id, '_eldokan_seller_user_id', true));

        if (!$seller_id) {
            return null;
        }

        $user = get_user_by('ID', $seller_id);
        if (!$user || !self::is_seller($user)) {
            return null;
        }

        return self::serialize($user);
    }

    private static function serialize($user) {
        if (!$user || !self::is_seller($user)) {
            return null;
        }

        $seller_id = absint($user->ID);
        $name = self::company_name($seller_id, $user);
        if (!$seller_id || $name === '') {
            return null;
        }

        return [
            'id' => ElDokan_Customer_API_Utils::opaque_id('sel', $seller_id),
            'name' => ElDokan_Customer_API_Utils::clean_text($name),
            'slug' => sanitize_title($name),
            // Customer-facing seller ratings belong to Phase 2. Never project
            // internal/manual Admin scoring into the public Customer API.
            'rating' => null,
            'rating_count' => null,
        ];
    }

    private static function company_name($seller_id, $user) {
        foreach ([
            '_eldokan_company_name',
            'eldokan_company_name',
            'billing_company',
            'company_name',
            'wcfmmp_store_name',
            'store_name',
            'dokan_store_name',
        ] as $key) {
            $value = trim((string) get_user_meta($seller_id, $key, true));
            if ($value !== '') {
                return $value;
            }
        }

        $dokan_profile = get_user_meta($seller_id, 'dokan_profile_settings', true);
        if (is_array($dokan_profile) && !empty($dokan_profile['store_name'])) {
            return trim((string) $dokan_profile['store_name']);
        }

        return '';
    }

    private static function is_seller($user) {
        // A seller has completed ElDokan onboarding; an employee post author has not.
        $roles = (array) $user->roles;
        return !empty(array_intersect($roles, ['eldokan_owner', 'wcfm_vendor', 'seller', 'vendor']));
    }
}
