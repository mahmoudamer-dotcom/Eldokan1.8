<?php
if (!defined('ABSPATH')) { exit; }

final class ElDokan_Stories_Picker {
    public static function init() {
        add_action('wp_ajax_eldokan_stories_sellers', [self::class, 'sellers']);
        add_action('wp_ajax_eldokan_stories_products', [self::class, 'products']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }
    public static function assets() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'eldokan_story') { return; }
        $plugin_file = dirname(__DIR__) . '/eldokan-stories.php';
        wp_enqueue_script('eldokan-stories-picker', plugins_url('assets/picker.js', $plugin_file), [], '1.1.0', true);
        wp_enqueue_style('eldokan-stories-picker', plugins_url('assets/picker.css', $plugin_file), [], '1.1.0');
        wp_localize_script('eldokan-stories-picker', 'eldokanStoriesPicker', ['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('eldokan_stories_picker')]);
    }
    private static function authorize() {
        check_ajax_referer('eldokan_stories_picker', 'nonce');
        if (!current_user_can('manage_woocommerce')) { wp_send_json_error(['message' => 'Access denied.'], 403); }
        if (!class_exists('ElDokan_Customer_API_Sellers') || !class_exists('ElDokan_Customer_API_Products')) {
            wp_send_json_error(['message' => 'Activate the ElDokan Customer API plugin.'], 503);
        }
    }
    public static function seller($id) {
        if (!$id || !class_exists('ElDokan_Customer_API_Sellers')) { return null; }
        $seller = ElDokan_Customer_API_Sellers::by_public_id('sel_' . absint($id));
        return is_wp_error($seller) ? null : $seller;
    }
    public static function card($id) {
        if (!$id || !class_exists('ElDokan_Customer_API_Products')) { return null; }
        $card = ElDokan_Customer_API_Products::card_by_internal_id($id);
        if (is_wp_error($card)) { return null; }
        $pricing = $card['pricing'];
        $price = !empty($pricing['on_sale']) ? ($pricing['sale_price']['formatted'] ?? '') : ($pricing['regular_price']['formatted'] ?? '');
        $price = $price ?: ($pricing['min_price']['formatted'] ?? '');
        return ['id' => absint($id), 'name' => $card['name'], 'image' => $card['image']['url'] ?? '',
            'price' => $price, 'stock' => $card['stock']['status'] ?? '', 'quantity' => $card['stock']['quantity'] ?? null,
            'sku' => (string) get_post_meta($id, '_sku', true), 'seller' => $card['seller'] ?? null];
    }
    public static function sellers() {
        self::authorize();
        $q = isset($_GET['q']) && is_string($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $args = ['role__in' => ['eldokan_owner', 'wcfm_vendor', 'seller', 'vendor'], 'number' => 20, 'fields' => 'ID', 'orderby' => 'display_name', 'order' => 'ASC'];
        $ids = get_users($q === '' ? $args : array_merge($args, ['search' => '*' . $q . '*', 'search_columns' => ['display_name', 'user_login']]));
        if ($q !== '') {
            $meta = ['relation' => 'OR'];
            foreach (['_eldokan_company_name','eldokan_company_name','billing_company','company_name','wcfmmp_store_name','store_name','dokan_store_name','dokan_profile_settings'] as $key) {
                $meta[] = ['key' => $key, 'value' => $q, 'compare' => 'LIKE'];
            }
            $ids = array_unique(array_merge($ids, get_users(array_merge($args, ['meta_query' => $meta]))));
        }
        $items = [];
        foreach ($ids as $id) {
            $seller = self::seller($id);
            if ($seller && ($q === '' || stripos($seller['name'], $q) !== false)) { $items[] = ['id' => (int) $id, 'name' => $seller['name']]; }
            if (count($items) >= 20) { break; }
        }
        wp_send_json_success(['items' => $items]);
    }
    public static function products() {
        self::authorize();
        $seller_id = isset($_GET['seller_id']) && is_scalar($_GET['seller_id']) ? absint($_GET['seller_id']) : 0;
        if (!self::seller($seller_id)) { wp_send_json_error(['message' => 'Select a valid seller first.'], 422); }
        $q = isset($_GET['q']) && is_string($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'post_parent' => 0, 'has_password' => false,
            'posts_per_page' => 12, 'fields' => 'ids', 's' => $q, 'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => [['key' => '_eldokan_seller_user_id', 'value' => $seller_id, 'compare' => '=', 'type' => 'NUMERIC']]]);
        $items = [];
        foreach ($ids as $id) {
            $card = self::card($id);
            if ($card && ($card['seller']['id'] ?? '') === 'sel_' . $seller_id) { $items[] = $card; }
        }
        wp_send_json_success(['items' => $items]);
    }
    public static function fields($post) {
        $product_id = absint(get_post_meta($post->ID, '_eldokan_story_product_id', true));
        $seller_id = absint(get_post_meta($product_id, '_eldokan_seller_user_id', true));
        $seller = self::seller($seller_id);
        $card = self::card($product_id);
        echo '<div id="eldokan-story-picker" data-product="' . esc_attr(wp_json_encode($card)) . '">';
        echo '<input type="hidden" id="eldokan-story-seller-id" name="eldokan_story[seller_id]" value="' . esc_attr($seller_id) . '">';
        echo '<input type="hidden" id="eldokan-story-product-id" name="eldokan_story[product_id]" value="' . esc_attr($product_id) . '">';
        echo '<p><label for="eldokan-story-seller-search"><strong>Seller name</strong></label><br><input type="search" id="eldokan-story-seller-search" autocomplete="off" placeholder="Search by seller / store name" value="' . esc_attr($seller['name'] ?? '') . '"></p>';
        echo '<div id="eldokan-story-seller-results" aria-label="Seller search results"></div><p id="eldokan-story-selected-seller">' . esc_html($seller ? 'Selected seller: ' . $seller['name'] : 'Select a seller to search their products.') . '</p>';
        echo '<p><label for="eldokan-story-product-search"><strong>Seller products</strong></label><br><input type="search" id="eldokan-story-product-search" autocomplete="off" placeholder="Search product name"' . (!$seller ? ' disabled' : '') . '></p>';
        echo '<div id="eldokan-story-picker-status" role="status" aria-live="polite"></div><div id="eldokan-story-product-results"></div><div id="eldokan-story-product-preview"></div></div>';
        $error = get_post_meta($post->ID, '_eldokan_story_picker_error', true);
        if ($error) { echo '<p style="color:#b32d2e">' . esc_html($error) . '</p>'; }
    }
}
ElDokan_Stories_Picker::init();
