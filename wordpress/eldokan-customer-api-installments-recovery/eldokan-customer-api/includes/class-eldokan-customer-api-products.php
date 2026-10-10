<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Products {

    public static function list(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $page = max(1, absint($request->get_param('page')));
        $per_page = absint($request->get_param('per_page'));
        $per_page = max(1, min(48, $per_page ?: 24));

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_parent' => 0,
            'has_password' => false,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'fields' => 'ids',
            'ignore_sticky_posts' => true,
        ];

        $search = sanitize_text_field((string) $request->get_param('search'));
        if ($search !== '') {
            $args['s'] = $search;
        }

        $tax_query = self::visibility_tax_query($search !== '');

        $category = sanitize_title((string) $request->get_param('category'));
        if ($category !== '') {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => [$category],
            ];
        }

        $filters = self::attribute_filters($request, $category);
        if (is_wp_error($filters)) {
            return $filters;
        }
        $tax_query = array_merge($tax_query, $filters);

        $brand = sanitize_title((string) $request->get_param('brand'));
        $brand_taxonomy = ElDokan_Customer_API_Utils::brand_taxonomy();
        if ($brand !== '' && $brand_taxonomy) {
            $tax_query[] = [
                'taxonomy' => $brand_taxonomy,
                'field' => 'slug',
                'terms' => [$brand],
            ];
        }

        $tag = sanitize_title((string) $request->get_param('tag'));
        if ($tag !== '') {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field' => 'slug',
                'terms' => [$tag],
            ];
        }

        if ($request->get_param('featured')) {
            $visibility = function_exists('wc_get_product_visibility_term_ids')
                ? wc_get_product_visibility_term_ids()
                : [];
            if (!empty($visibility['featured'])) {
                $tax_query[] = [
                    'taxonomy' => 'product_visibility',
                    'field' => 'term_id',
                    'terms' => [(int) $visibility['featured']],
                ];
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $meta_query = [];

        $stock_status = ElDokan_Customer_API_Utils::to_woocommerce_stock_status(
            $request->get_param('stock_status')
        );
        if ($stock_status !== null) {
            $meta_query[] = [
                'key' => '_stock_status',
                'value' => $stock_status,
            ];
        }

        $min_price = $request->get_param('min_price');
        $max_price = $request->get_param('max_price');

        if ($min_price !== null && $min_price !== '' && $max_price !== null && $max_price !== '' && (float) $min_price > (float) $max_price) {
            return new WP_Error('invalid_price_range', 'min_price must not exceed max_price.', ['status' => 422]);
        }

        if ($min_price !== null && $min_price !== '') {
            $meta_query[] = [
                'key' => '_price',
                'value' => (float) $min_price,
                'compare' => '>=',
                'type' => 'DECIMAL(20,6)',
            ];
        }

        if ($max_price !== null && $max_price !== '') {
            $meta_query[] = [
                'key' => '_price',
                'value' => (float) $max_price,
                'compare' => '<=',
                'type' => 'DECIMAL(20,6)',
            ];
        }

        if ($request->get_param('on_sale')) {
            $sale_ids = function_exists('wc_get_product_ids_on_sale') ? wc_get_product_ids_on_sale() : [];
            if (empty($sale_ids)) {
                return [
                    'items' => [],
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => 0,
                    'total_pages' => 0,
                ];
            }
            $args['post__in'] = array_values(array_unique(array_map('absint', $sale_ids)));
        }

        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $args['meta_query'] = $meta_query;
        }

        self::apply_sort($args, sanitize_key((string) $request->get_param('sort')), $search !== '');

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $product_id) {
            if (get_post_type($product_id) !== 'product') {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!self::is_public($product, $search !== '' ? 'search' : 'catalog')) {
                continue;
            }

            $items[] = self::serialize_card($product);
        }

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ];
    }

    public static function single_by_public_id($public_id, $lang = null) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $product_id = ElDokan_Customer_API_Utils::parse_opaque_id('prd', $public_id);
        if (!$product_id) {
            return new WP_Error('invalid_product_id', 'Invalid product ID.', ['status' => 400]);
        }

        if ($lang) {
            $product_id = ElDokan_Customer_API_Language::object_id(
                $product_id,
                'product',
                $lang
            );
            if (!$product_id) {
                return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
            }
        }

        $product = wc_get_product($product_id);
        if (
            !$product ||
            !$product->exists() ||
            $product->is_type('variation') ||
            get_post_type($product_id) !== 'product' ||
            !self::is_public($product, 'direct')
        ) {
            return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
        }

        return self::serialize_detail($product);
    }

    public static function card_by_internal_id($product_id, $lang = null) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $product_id = absint($product_id);
        if ($lang) {
            $product_id = ElDokan_Customer_API_Language::object_id(
                $product_id,
                'product',
                $lang
            );
        }

        $product = $product_id ? wc_get_product($product_id) : false;
        if (
            !$product
            || !$product->exists()
            || $product->is_type('variation')
            || get_post_type($product_id) !== 'product'
            || !self::is_public($product, 'direct')
        ) {
            return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
        }

        return self::serialize_card($product);
    }

    /**
     * Build one authoritative Cart line without exposing WooCommerce IDs/keys.
     * Stored source IDs stay server-side and language-independent.
     */
    public static function cart_line($product_id, $variation_id, $quantity, $lang = null, $strict = true, $include_seller = true) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $product_id = absint($product_id);
        $variation_id = absint($variation_id);
        $quantity = absint($quantity);
        $parent = $product_id ? wc_get_product($product_id) : false;

        if (!$parent || !$parent->exists() || !self::is_public($parent, 'catalog')) {
            return new WP_Error('cart_product_unavailable', 'This product is no longer available.', ['status' => 422]);
        }

        $purchasable = $parent;
        $selected = [];
        if ($variation_id) {
            $variation = wc_get_product($variation_id);
            if (
                !$parent->is_type('variable')
                || !$variation
                || !$variation->exists()
                || !$variation->is_type('variation')
                || absint($variation->get_parent_id()) !== $product_id
                || get_post_status($variation_id) !== 'publish'
                || !$variation->variation_is_visible()
            ) {
                return new WP_Error('invalid_variation', 'The selected variation is invalid.', ['status' => 422]);
            }
            $purchasable = $variation;
            $selected = self::variation_selections($variation);
        } elseif (!$parent->is_type('simple')) {
            return new WP_Error('variation_required', 'Select a valid product variation.', ['status' => 422]);
        }

        $issues = [];
        if (!$purchasable->is_purchasable() || $purchasable->get_price() === '') {
            $issues[] = ['code' => 'not_purchasable', 'message' => 'This item is not currently purchasable.'];
        }
        $maximum = (int) $purchasable->get_max_purchase_quantity();
        if (
            $quantity < 1
            || $quantity > 999
            || ($purchasable->is_sold_individually() && $quantity > 1)
            || ($maximum > 0 && $quantity > $maximum)
        ) {
            $issues[] = ['code' => 'purchase_quantity_limit', 'message' => 'The requested quantity exceeds the current purchase limit.'];
        }
        if (!$purchasable->is_in_stock()) {
            $issues[] = ['code' => 'out_of_stock', 'message' => 'This item is out of stock.'];
        } elseif (!$purchasable->has_enough_stock($quantity)) {
            $issues[] = ['code' => 'insufficient_stock', 'message' => 'The requested quantity is not available.'];
        }

        if ($strict && $issues) {
            return new WP_Error($issues[0]['code'], $issues[0]['message'], ['status' => 422]);
        }

        // Localize only the response projection. Missing translations must not
        // mutate or invalidate the stored source membership.
        $display_parent = $parent;
        $display_item = $purchasable;
        if ($lang) {
            $localized_parent_id = ElDokan_Customer_API_Language::object_id($product_id, 'product', $lang);
            $localized_parent = $localized_parent_id ? wc_get_product($localized_parent_id) : false;
            if ($localized_parent && $localized_parent->exists()) {
                $display_parent = $localized_parent;
                if ($variation_id) {
                    $localized_variation_id = ElDokan_Customer_API_Language::object_id($variation_id, 'product_variation', $lang);
                    $localized_variation = $localized_variation_id ? wc_get_product($localized_variation_id) : false;
                    if ($localized_variation && $localized_variation->exists()) {
                        $display_item = $localized_variation;
                    }
                } else {
                    $display_item = $localized_parent;
                }
            }
        }

        $image_id = $display_item->get_image_id() ?: $display_parent->get_image_id();
        $price = $purchasable->get_price();

        return [
            'product_id' => ElDokan_Customer_API_Utils::opaque_id('prd', $product_id),
            'variation_id' => $variation_id ? ElDokan_Customer_API_Utils::opaque_id('var', $variation_id) : null,
            'name' => ElDokan_Customer_API_Utils::clean_text($display_parent->get_name()),
            'image' => ElDokan_Customer_API_Utils::image($image_id),
            'selected_attributes' => $selected,
            'quantity' => $quantity,
            'unit_price' => ElDokan_Customer_API_Utils::money($price),
            'line_subtotal' => ElDokan_Customer_API_Utils::money((float) $price * $quantity),
            'stock' => self::stock($purchasable),
            'seller' => $include_seller ? ElDokan_Customer_API_Sellers::for_product($product_id) : null,
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    public static function single_by_slug($slug, $lang = null) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $lookup = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'name' => sanitize_title($slug),
            'post_parent' => 0,
            'has_password' => false,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'ignore_sticky_posts' => true,
        ]);
        $product_id = !empty($lookup->posts) ? absint($lookup->posts[0]) : 0;
        if (!$product_id) {
            return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
        }
        $product = wc_get_product($product_id);
        if (!self::is_public($product, 'direct')) {
            return new WP_Error('product_not_found', 'Product not found.', ['status' => 404]);
        }

        return self::serialize_detail($product);
    }

    public static function suggestions(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $q = trim((string) $request->get_param('q'));
        $query_length = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
        if ($query_length < 2) {
            return new WP_Error('search_too_short', 'Search query must be at least 2 characters.', ['status' => 422]);
        }

        $limit = max(1, min(20, absint($request->get_param('limit')) ?: 8));

        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_parent' => 0,
            'has_password' => false,
            'posts_per_page' => $limit,
            'fields' => 'ids',
            's' => $q,
            'ignore_sticky_posts' => true,
            'tax_query' => self::visibility_tax_query(true),
        ]);

        $items = [];
        foreach ($query->posts as $product_id) {
            if (get_post_type($product_id) !== 'product') {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!self::is_public($product, 'search')) {
                continue;
            }

            $image_id = $product->get_image_id();

            $items[] = [
                'id' => ElDokan_Customer_API_Utils::opaque_id('prd', $product->get_id()),
                'name' => ElDokan_Customer_API_Utils::clean_text($product->get_name()),
                'slug' => $product->get_slug(),
                'price' => ElDokan_Customer_API_Utils::money($product->get_price()),
                'image' => ElDokan_Customer_API_Utils::image($image_id),
            ];
        }

        return $items;
    }

    public static function serialize_card($product) {
        $image = ElDokan_Customer_API_Utils::image($product->get_image_id());
        return [
            'id' => ElDokan_Customer_API_Utils::opaque_id('prd', $product->get_id()),
            'slug' => $product->get_slug(),
            'name' => ElDokan_Customer_API_Utils::clean_text($product->get_name()),
            'type' => self::public_type($product),
            'brand' => ElDokan_Customer_API_Utils::product_brand($product->get_id()),
            'pricing' => self::pricing($product),
            'stock' => self::stock($product),
            'image' => $image,
            'average_rating' => (float) $product->get_average_rating(),
            'rating_count' => (int) $product->get_rating_count(),
            'seller' => ElDokan_Customer_API_Sellers::for_product($product->get_id()),
        ];
    }

    public static function serialize_detail($product) {
        $images = [];
        $primary_image = null;

        if ($product->get_image_id()) {
            $primary_image = ElDokan_Customer_API_Utils::image(
                $product->get_image_id(),
                'woocommerce_single'
            );
            if ($primary_image) {
                $images[] = $primary_image;
            }
        }

        foreach ($product->get_gallery_image_ids() as $image_id) {
            $image = ElDokan_Customer_API_Utils::image($image_id, 'woocommerce_single');
            if ($image) {
                $images[] = $image;
            }
        }

        $categories = [];
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = [
                    'id' => ElDokan_Customer_API_Utils::opaque_id('cat', $term->term_id),
                    'name' => ElDokan_Customer_API_Utils::clean_text($term->name),
                    'slug' => $term->slug,
                ];
            }
        }

        return [
            'id' => ElDokan_Customer_API_Utils::opaque_id('prd', $product->get_id()),
            'slug' => $product->get_slug(),
            'name' => ElDokan_Customer_API_Utils::clean_text($product->get_name()),
            'type' => self::public_type($product),
            'sku' => $product->get_sku() ?: null,
            'brand' => ElDokan_Customer_API_Utils::product_brand($product->get_id()),
            'categories' => $categories,
            'pricing' => self::pricing($product),
            'stock' => self::stock($product),
            'image' => $primary_image,
            'delivery' => [
                'label' => null,
                'min_days' => null,
                'max_days' => null,
            ],
            'warranty' => self::warranty($product),
            'seller' => ElDokan_Customer_API_Sellers::for_product($product->get_id()),
            'images' => $images,
            'attributes' => self::attributes($product),
            'variations' => self::variations($product),
            'short_description_html' => ElDokan_Customer_API_Utils::clean_html($product->get_short_description()),
            'description_html' => ElDokan_Customer_API_Utils::clean_html($product->get_description()),
            'average_rating' => (float) $product->get_average_rating(),
            'rating_count' => (int) $product->get_rating_count(),
        ];
    }

    private static function stock($product) {
        return [
            'status' => ElDokan_Customer_API_Utils::normalize_stock_status(
                $product->get_stock_status()
            ),
            'quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
            'backorders_allowed' => (bool) $product->backorders_allowed(),
        ];
    }

    private static function warranty($product) {
        foreach ($product->get_attributes() as $attribute) {
            $name = strtolower((string) $attribute->get_name());
            if (strpos($name, 'warranty') !== false || strpos($name, 'ضمان') !== false) {
                $options = $attribute->is_taxonomy()
                    ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                    : $attribute->get_options();

                if (!empty($options)) {
                    return [
                        'label' => ElDokan_Customer_API_Utils::clean_text(implode(', ', array_map('strval', $options))),
                        'duration' => null,
                        'unit' => null,
                        'type' => null,
                    ];
                }
            }
        }

        foreach (['_eldokan_warranty', 'eldokan_warranty', '_warranty', 'warranty'] as $meta_key) {
            $value = trim((string) $product->get_meta($meta_key, true));
            if ($value !== '') {
                return [
                    'label' => ElDokan_Customer_API_Utils::clean_text($value),
                    'duration' => null,
                    'unit' => null,
                    'type' => null,
                ];
            }
        }

        return [
            'label' => null,
            'duration' => null,
            'unit' => null,
            'type' => null,
        ];
    }

    private static function attributes($product) {
        $items = [];

        foreach ($product->get_attributes() as $attribute) {
            $options = $attribute->is_taxonomy()
                ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'all'])
                : $attribute->get_options();

            $serialized_options = [];

            foreach ((array) $options as $option) {
                if (is_object($option) && isset($option->term_id)) {
                    $serialized_options[] = [
                        'id' => ElDokan_Customer_API_Utils::opaque_id('atr', $option->term_id),
                        'name' => ElDokan_Customer_API_Utils::clean_text($option->name),
                        'slug' => $option->slug,
                    ];
                } else {
                    $serialized_options[] = [
                        'id' => null,
                        'name' => ElDokan_Customer_API_Utils::clean_text($option),
                        'slug' => sanitize_title((string) $option),
                    ];
                }
            }

            $items[] = [
                'name' => ElDokan_Customer_API_Utils::clean_text(wc_attribute_label($attribute->get_name())),
                'slug' => $attribute->is_taxonomy() ? wc_attribute_taxonomy_slug($attribute->get_name()) : sanitize_title($attribute->get_name()),
                'id' => $attribute->is_taxonomy() ? ElDokan_Customer_API_Utils::opaque_id('att', wc_attribute_taxonomy_id_by_name($attribute->get_name())) : null,
                'visible' => (bool) $attribute->get_visible(),
                'variation' => (bool) $attribute->get_variation(),
                'options' => $serialized_options,
            ];
        }

        return $items;
    }

    private static function variations($product) {
        if (!$product->is_type('variable')) {
            return [];
        }

        $items = [];

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation || !$variation->exists() || !$variation->variation_is_visible() || get_post_status($variation_id) !== 'publish') {
                continue;
            }

            $items[] = [
                'id' => ElDokan_Customer_API_Utils::opaque_id('var', $variation->get_id()),
                'attributes' => self::variation_selections($variation),
                'purchasable' => (bool) $variation->is_purchasable() && $variation->is_in_stock(),
                'pricing' => self::pricing($variation),
                'stock' => self::stock($variation),
                'image' => ElDokan_Customer_API_Utils::image($variation->get_image_id(), 'woocommerce_single'),
            ];
        }

        return $items;
    }


    /** Explicit category attribute IDs and term IDs only; no taxonomy names from clients. */
    private static function attribute_filters(WP_REST_Request $request, $category) {
        $raw = trim((string) $request->get_param('attributes'));
        if ($raw === '') {
            return [];
        }
        if ($category === '' || strlen($raw) > 500) {
            return new WP_Error('invalid_attribute_filter', 'Attribute filtering requires a category and at most 500 characters.', ['status' => 422]);
        }
        $definition = ElDokan_Customer_API_Categories::filters($category);
        if (is_wp_error($definition)) {
            return $definition;
        }
        $allowed = [];
        foreach ($definition['attributes'] as $attribute) {
            $options = [];
            foreach ($attribute['options'] as $option) {
                $options[$option['id']] = true;
            }
            $allowed[$attribute['id']] = ['slug' => $attribute['slug'], 'options' => $options];
        }
        $groups = explode(';', $raw);
        if (count($groups) > 5) {
            return new WP_Error('invalid_attribute_filter', 'Too many attribute groups.', ['status' => 422]);
        }
        $query = [];
        $seen = [];
        foreach ($groups as $group) {
            $parts = explode(':', $group);
            if (count($parts) !== 2 || !preg_match('/^att_[1-9][0-9]*$/', $parts[0]) || isset($seen[$parts[0]]) || !isset($allowed[$parts[0]])) {
                return new WP_Error('invalid_attribute_filter', 'Unknown or repeated attribute for this category.', ['status' => 422]);
            }
            $seen[$parts[0]] = true;
            $selected = explode(',', $parts[1]);
            if (count($selected) > 10 || !$selected || count($selected) !== count(array_unique($selected))) {
                return new WP_Error('invalid_attribute_filter', 'Invalid attribute options.', ['status' => 422]);
            }
            $term_ids = [];
            foreach ($selected as $option) {
                if (!preg_match('/^atr_[1-9][0-9]*$/', $option) || !isset($allowed[$parts[0]]['options'][$option])) {
                    return new WP_Error('invalid_attribute_filter', 'Option is not available in this category.', ['status' => 422]);
                }
                $term_ids[] = ElDokan_Customer_API_Utils::parse_opaque_id('atr', $option);
            }
            $query[] = [
                'taxonomy' => wc_attribute_taxonomy_name($allowed[$parts[0]]['slug']),
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
            ];
        }
        return $query;
    }

    private static function is_public($product, $context) {
        if (!$product || !$product->exists() || $product->is_type('variation')) {
            return false;
        }
        $id = $product->get_id();
        if (get_post_type($id) !== 'product' || wp_get_post_parent_id($id) || get_post_status($id) !== 'publish' || post_password_required($id)) {
            return false;
        }
        $post = get_post($id);
        if (!$post || $post->post_password !== '') {
            return false;
        }
        $visibility = $product->get_catalog_visibility();
        if ($context === 'search') {
            return in_array($visibility, ['visible', 'search'], true);
        }
        if ($context === 'catalog') {
            return in_array($visibility, ['visible', 'catalog'], true);
        }
        return true; // Explicit link: WooCommerce hidden listings can still open directly.
    }

    private static function variation_selections($variation) {
        $items = [];
        foreach ($variation->get_variation_attributes() as $key => $value) {
            $taxonomy = preg_replace('/^attribute_/', '', $key);
            $is_taxonomy = taxonomy_exists($taxonomy) && strpos($taxonomy, 'pa_') === 0;
            $attribute_id = $is_taxonomy ? wc_attribute_taxonomy_id_by_name($taxonomy) : 0;
            $term = ($is_taxonomy && $value !== '') ? get_term_by('slug', $value, $taxonomy) : false;
            $items[] = [
                'attribute_id' => $attribute_id ? ElDokan_Customer_API_Utils::opaque_id('att', $attribute_id) : null,
                'attribute_slug' => $is_taxonomy ? wc_attribute_taxonomy_slug($taxonomy) : sanitize_title($taxonomy),
                'option_id' => $term ? ElDokan_Customer_API_Utils::opaque_id('atr', $term->term_id) : null,
                'option_slug' => $value !== '' ? $value : null,
            ];
        }
        return $items;
    }

    private static function pricing($product) {
        $price = $product->get_price();
        $regular = $product->get_regular_price();
        $sale = $product->get_sale_price();
        $min = $price;
        $max = $price;

        if ($product->is_type('variable')) {
            $min = $product->get_variation_price('min', false);
            $max = $product->get_variation_price('max', false);
        }

        return [
            'price' => ElDokan_Customer_API_Utils::money($price),
            'min_price' => ElDokan_Customer_API_Utils::money($min),
            'max_price' => ElDokan_Customer_API_Utils::money($max),
            'regular_price' => ElDokan_Customer_API_Utils::money($regular),
            'sale_price' => ElDokan_Customer_API_Utils::money($sale),
            'on_sale' => (bool) $product->is_on_sale(),
            'discount_percent' => self::discount_percent($regular, $price),
        ];
    }

    private static function public_type($product) {
        $type = sanitize_key((string) $product->get_type());
        return in_array($type, ['simple', 'variable', 'grouped', 'external'], true)
            ? $type
            : 'other';
    }

    private static function discount_percent($regular, $current) {
        $regular = (float) $regular;
        $current = (float) $current;

        if ($regular <= 0 || $current <= 0 || $current >= $regular) {
            return 0;
        }

        return (int) round((($regular - $current) / $regular) * 100);
    }

    private static function visibility_tax_query($search_context) {
        $query = [];

        if (!function_exists('wc_get_product_visibility_term_ids')) {
            return $query;
        }

        $visibility = wc_get_product_visibility_term_ids();
        $exclude = [];

        if (!$search_context && !empty($visibility['exclude-from-catalog'])) {
            $exclude[] = (int) $visibility['exclude-from-catalog'];
        }

        if ($search_context && !empty($visibility['exclude-from-search'])) {
            $exclude[] = (int) $visibility['exclude-from-search'];
        }

        if (!empty($exclude)) {
            $query[] = [
                'taxonomy' => 'product_visibility',
                'field' => 'term_id',
                'terms' => $exclude,
                'operator' => 'NOT IN',
            ];
        }

        return $query;
    }

    private static function apply_sort(&$args, $sort, $has_search) {
        switch ($sort) {
            case 'price_asc':
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;

            case 'price_desc':
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'best_selling':
                $args['meta_key'] = 'total_sales';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'rating':
                $args['meta_key'] = '_wc_average_rating';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;

            case 'relevance':
                if ($has_search) {
                    $args['orderby'] = 'relevance';
                    $args['order'] = 'DESC';
                } else {
                    $args['orderby'] = 'date';
                    $args['order'] = 'DESC';
                }
                break;

            case 'newest':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }
    }
}
