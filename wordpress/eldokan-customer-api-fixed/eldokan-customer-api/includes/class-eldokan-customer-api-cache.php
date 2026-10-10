<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small read-model cache for public Customer API endpoints.
 *
 * Design goals:
 * - keep WooCommerce as the source of truth;
 * - reduce repeated expensive catalog queries;
 * - never cache errors;
 * - keep short TTLs for price/stock-bearing responses;
 * - invalidate the cache generation when relevant catalog data changes;
 * - do not couple the frontend to WordPress cache internals.
 */
final class ElDokan_Customer_API_Cache {

    const GENERATION_OPTION = 'eldokan_customer_api_cache_generation';
    const HOME_GENERATION_OPTION = 'eldokan_customer_api_home_generation';

    private static $invalidated_this_request = false;

    public static function init() {
        add_action('save_post_product', [__CLASS__, 'invalidate'], 20, 3);
        add_action('before_delete_post', [__CLASS__, 'invalidate_deleted_post'], 20, 2);

        add_action('created_term', [__CLASS__, 'invalidate_term'], 20, 3);
        add_action('edited_term', [__CLASS__, 'invalidate_term'], 20, 3);
        add_action('delete_term', [__CLASS__, 'invalidate_deleted_term'], 20, 5);

        add_action('woocommerce_product_set_stock', [__CLASS__, 'invalidate']);
        add_action('woocommerce_variation_set_stock', [__CLASS__, 'invalidate']);
        add_action('woocommerce_update_product', [__CLASS__, 'invalidate']);
        add_action('woocommerce_update_product_variation', [__CLASS__, 'invalidate']);

        add_action('added_user_meta', [__CLASS__, 'invalidate_seller_meta'], 20, 4);
        add_action('updated_user_meta', [__CLASS__, 'invalidate_seller_meta'], 20, 4);
        add_action('deleted_user_meta', [__CLASS__, 'invalidate_seller_meta'], 20, 4);
        add_action('added_post_meta', [__CLASS__, 'invalidate_product_meta'], 20, 4);
        add_action('updated_post_meta', [__CLASS__, 'invalidate_product_meta'], 20, 4);
        add_action('deleted_post_meta', [__CLASS__, 'invalidate_product_meta'], 20, 4);
        add_action('added_term_meta', [__CLASS__, 'invalidate_category_meta'], 20, 4);
        add_action('updated_term_meta', [__CLASS__, 'invalidate_category_meta'], 20, 4);
        add_action('deleted_term_meta', [__CLASS__, 'invalidate_category_meta'], 20, 4);
        add_action('set_user_role', [__CLASS__, 'invalidate']);
    }

    public static function ttl($scope) {
        $ttls = [
            'home' => 60,
            'categories' => 300,
            'category' => 300,
            'category_filters' => 300,
            'brands' => 300,
            'tags' => 300,
            'seller' => 300,
            'products' => 30,
            'product' => 30,
            'product_lookup' => 30,
            'search_suggestions' => 30,
        ];

        return isset($ttls[$scope]) ? (int) $ttls[$scope] : 30;
    }

    /**
     * Returns:
     * [
     *   'value'  => mixed,
     *   'status' => HIT|MISS|BYPASS,
     *   'ttl'    => int
     * ]
     */
    public static function remember($scope, WP_REST_Request $request, $producer) {
        $ttl = self::ttl($scope);
        $generation = self::generation();
        $scope_generation = $scope === 'home' ? self::home_generation() : 1;
        $key = self::key_with_generation(
            $scope,
            $request,
            $generation,
            $scope_generation
        );

        $diagnostics = [
            'generation' => $generation,
            'key_hash' => substr(md5($key), 0, 12),
            'backend' => wp_using_ext_object_cache()
                ? 'object-cache'
                : 'transients-db',
            'store' => 'not_attempted',
        ];

        if (self::should_bypass($request)) {
            return [
                'value' => call_user_func($producer),
                'status' => 'BYPASS',
                'ttl' => 0,
                'diagnostics' => $diagnostics,
            ];
        }

        $cached = get_transient($key);

        if (
            is_array($cached) &&
            isset($cached['_eldokan_cache']) &&
            array_key_exists('value', $cached)
        ) {
            $diagnostics['store'] = 'hit';

            return [
                'value' => $cached['value'],
                'status' => 'HIT',
                'ttl' => $ttl,
                'diagnostics' => $diagnostics,
            ];
        }

        $value = call_user_func($producer);

        if (!is_wp_error($value)) {
            $write_ok = set_transient(
                $key,
                [
                    '_eldokan_cache' => 1,
                    'value' => $value,
                ],
                $ttl
            );

            // Immediate read-back tells us whether the cache backend
            // actually persisted the value for the same key.
            $verify = get_transient($key);

            $verified = (
                is_array($verify) &&
                isset($verify['_eldokan_cache']) &&
                array_key_exists('value', $verify)
            );

            if ($verified) {
                $diagnostics['store'] = 'stored';
            } elseif ($write_ok) {
                $diagnostics['store'] = 'write_ok_readback_failed';
            } else {
                $diagnostics['store'] = 'write_failed';
            }
        } else {
            $diagnostics['store'] = 'skipped_error';
        }

        return [
            'value' => $value,
            'status' => 'MISS',
            'ttl' => $ttl,
            'diagnostics' => $diagnostics,
        ];
    }

    public static function invalidate(...$unused) {
        if (self::$invalidated_this_request) {
            return;
        }

        $generation = self::generation() + 1;

        update_option(
            self::GENERATION_OPTION,
            $generation,
            false
        );

        self::$invalidated_this_request = true;
    }

    public static function invalidate_home(...$unused) {
        update_option(
            self::HOME_GENERATION_OPTION,
            self::home_generation() + 1,
            false
        );
    }

    public static function invalidate_deleted_post($post_id, $post = null) {
        if ($post && isset($post->post_type) && in_array(
            $post->post_type,
            ['product', 'product_variation'],
            true
        )) {
            self::invalidate();
        }
    }

    public static function invalidate_term($term_id, $tt_id, $taxonomy) {
        if (
            $taxonomy === 'product_cat' ||
            $taxonomy === 'product_tag' ||
            in_array($taxonomy, ['product_brand', 'pwb-brand', 'yith_product_brand'], true) ||
            strpos((string) $taxonomy, 'pa_') === 0
        ) {
            self::invalidate();
        }
    }

    public static function invalidate_deleted_term(
        $term_id,
        $tt_id,
        $taxonomy,
        $deleted_term,
        $object_ids
    ) {
        self::invalidate_term($term_id, $tt_id, $taxonomy);
    }

    public static function invalidate_seller_meta(
        $meta_id,
        $user_id,
        $meta_key,
        $meta_value = null
    ) {
        $keys = [
            '_eldokan_company_name',
            'eldokan_company_name',
            'billing_company',
            'company_name',
            'wcfmmp_store_name',
            'store_name',
            'dokan_store_name',
            'dokan_profile_settings',
        ];

        if (in_array((string) $meta_key, $keys, true)) {
            self::invalidate();
        }
    }

    public static function invalidate_product_meta($meta_id, $object_id, $meta_key, $value = null) {
        if ($meta_key === '_eldokan_seller_user_id' && get_post_type($object_id) === 'product') {
            self::invalidate();
        }
    }

    public static function invalidate_category_meta($meta_id, $term_id, $meta_key, $value = null) {
        if (in_array($meta_key, ['_eldokan_attribute_mode', '_eldokan_attribute_ids', 'thumbnail_id'], true)) {
            self::invalidate();
        }
    }

    private static function generation() {
        $generation = get_option(self::GENERATION_OPTION, null);

        if ($generation === null) {
            add_option(
                self::GENERATION_OPTION,
                1,
                '',
                false
            );
            return 1;
        }

        return max(1, absint($generation));
    }

    private static function home_generation() {
        $generation = get_option(self::HOME_GENERATION_OPTION, null);
        if ($generation === null) {
            add_option(self::HOME_GENERATION_OPTION, 1, '', false);
            return 1;
        }
        return max(1, absint($generation));
    }

    private static function should_bypass(WP_REST_Request $request) {
        $requested = rest_sanitize_boolean(
            $request->get_param('no_cache')
        );

        // Cache bypass is deliberately restricted to administrators.
        return $requested && current_user_can('manage_options');
    }

    private static function key_with_generation(
        $scope,
        WP_REST_Request $request,
        $generation,
        $scope_generation = 1
    ) {
        $params = [];
        $scope_keys = [
            'home' => [], 'categories' => ['parent'], 'category' => ['slug'],
            'category_filters' => ['slug'], 'product' => ['product_id'],
            'brands' => ['page', 'per_page', 'search'],
            'tags' => ['page', 'per_page', 'search'],
            'seller' => ['seller_id'],
            'product_lookup' => ['slug'], 'search_suggestions' => ['q', 'limit'],
            'products' => ['page', 'per_page', 'search', 'category', 'brand', 'tag',
                'min_price', 'max_price', 'stock_status', 'on_sale', 'featured', 'sort', 'attributes'],
        ];
        foreach (($scope_keys[$scope] ?? []) as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $params[$key] = $value;
            }
        }
        if ($scope === 'products') {
            $params['page'] = max(1, absint($params['page'] ?? 1));
            $params['per_page'] = max(1, min(48, absint($params['per_page'] ?? 24) ?: 24));
            $params['sort'] = sanitize_key((string) ($params['sort'] ?? 'newest'));
            foreach (['category', 'brand', 'tag'] as $slug) {
                if (isset($params[$slug])) {
                    $params[$slug] = sanitize_title((string) $params[$slug]);
                }
            }
            if (isset($params['search'])) {
                $params['search'] = trim(sanitize_text_field((string) $params['search']));
            }
            if (isset($params['stock_status'])) {
                $params['stock_status'] = sanitize_key((string) $params['stock_status']);
            }
            foreach (['on_sale', 'featured'] as $flag) {
                $params[$flag] = rest_sanitize_boolean($params[$flag] ?? false);
            }
            foreach (['min_price', 'max_price'] as $price) {
                if (isset($params[$price])) {
                    $params[$price] = ElDokan_Customer_API_Utils::decimal($params[$price]);
                }
            }
            if (isset($params['attributes'])) {
                $groups = explode(';', trim(sanitize_text_field((string) $params['attributes'])));
                foreach ($groups as &$group) {
                    $parts = explode(':', $group, 2);
                    if (count($parts) === 2) {
                        $values = explode(',', $parts[1]);
                        sort($values, SORT_STRING);
                        $group = $parts[0] . ':' . implode(',', $values);
                    }
                }
                unset($group);
                sort($groups, SORT_STRING);
                $params['attributes'] = implode(';', $groups);
            }
        }
        if ($scope === 'search_suggestions') {
            $params['limit'] = max(1, min(20, absint($params['limit'] ?? 8) ?: 8));
            $params['q'] = trim(sanitize_text_field((string) ($params['q'] ?? '')));
        }
        if (in_array($scope, ['brands', 'tags'], true)) {
            $params['page'] = max(1, absint($params['page'] ?? 1));
            $params['per_page'] = max(
                1,
                min(100, absint($params['per_page'] ?? 50) ?: 50)
            );
            if (isset($params['search'])) {
                $params['search'] = trim(
                    sanitize_text_field((string) $params['search'])
                );
            }
        }
        if (in_array($scope, ['categories', 'category', 'category_filters', 'product_lookup'], true)) {
            foreach (['parent', 'slug'] as $slug) {
                if (isset($params[$slug])) {
                    $params[$slug] = sanitize_title((string) $params[$slug]);
                }
            }
        }
        if ($scope === 'product' && isset($params['product_id'])) {
            $params['product_id'] = sanitize_text_field((string) $params['product_id']);
        }
        if ($scope === 'seller' && isset($params['seller_id'])) {
            $params['seller_id'] = sanitize_text_field(
                (string) $params['seller_id']
            );
        }

        self::ksort_recursive($params);

        $language = ElDokan_Customer_API_Language::cache_identity($request);

        $identity = [
            'generation' => max(1, absint($generation)),
            'scope_generation' => max(1, absint($scope_generation)),
            'scope' => (string) $scope,
            'route' => (string) $request->get_route(),
            'method' => (string) $request->get_method(),
            'language' => $language,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EGP',
            'params' => $params,
        ];

        return 'eldokan_customer_api_' . md5(
            wp_json_encode($identity)
        );
    }

    private static function ksort_recursive(&$value) {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as &$item) {
            self::ksort_recursive($item);
        }

        unset($item);

        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value);
        }
    }
}
