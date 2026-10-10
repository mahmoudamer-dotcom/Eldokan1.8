<?php
/**
 * Plugin Name: ElDokan Stories
 * Description: Manage bilingual product stories for the ElDokan storefront through the Customer API client.
 * Version: 1.1.1
 * Requires PHP: 8.0
 * Author: ElDokan
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/class-eldokan-stories-picker.php';

final class ElDokan_Stories {
    public static function init() {
        add_action('init', [self::class, 'register']);
        add_action('add_meta_boxes_eldokan_story', [self::class, 'boxes']);
        add_action('save_post_eldokan_story', [self::class, 'save']);
        add_action('rest_api_init', function () {
            register_rest_route('eldokan-customer/v1', '/stories', [
                'methods' => 'GET', 'permission_callback' => '__return_true',
                'callback' => [self::class, 'listing'],
                'args' => ['lang' => ['default' => 'en', 'enum' => ['en', 'ar']]],
            ]);
        });
        add_action('admin_notices', function () {
            if (current_user_can('manage_woocommerce') && !class_exists('ElDokan_Customer_API_Products')) {
                echo '<div class="notice notice-warning"><p>ElDokan Stories requires WooCommerce and the ElDokan Customer API plugin to display product stories.</p></div>';
            }
        });
    }
    public static function register() {
        $caps = [];
        foreach (['edit_post','read_post','delete_post','edit_posts','edit_others_posts','publish_posts','read_private_posts','delete_posts','delete_private_posts','delete_published_posts','delete_others_posts','edit_private_posts','edit_published_posts','create_posts'] as $cap) {
            $caps[$cap] = 'manage_woocommerce';
        }
        register_post_type('eldokan_story', [
            'labels' => ['name' => 'Store Stories', 'singular_name' => 'Store Story', 'add_new_item' => 'Add Store Story', 'edit_item' => 'Edit Store Story'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'woocommerce', 'show_in_rest' => false,
            'supports' => ['title', 'thumbnail', 'page-attributes'], 'capabilities' => $caps, 'map_meta_cap' => false,
        ]);
        add_post_type_support('eldokan_story', 'thumbnail');
    }
    public static function boxes($post) {
        add_meta_box('eldokan-story-product', 'Story content', [self::class, 'fields'], 'eldokan_story', 'normal', 'high');
    }
    public static function fields($post) {
        wp_nonce_field('eldokan_story_save', 'eldokan_story_nonce');
        ElDokan_Stories_Picker::fields($post);
        $fields = ['title_en' => ['English title', 'text'], 'title_ar' => ['Arabic title', 'text'], 'expires' => ['Expires at (store timezone; optional)', 'datetime-local']];
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, '_eldokan_story_' . $key, true);
            echo '<p><label for="eldokan-story-' . esc_attr($key) . '"><strong>' . esc_html($field[0]) . '</strong></label><br><input style="width:100%;max-width:560px" id="eldokan-story-' . esc_attr($key) . '" name="eldokan_story[' . esc_attr($key) . ']" type="' . esc_attr($field[1]) . '" value="' . esc_attr($value) . '"' . ($key === 'product_id' ? ' min="1" step="1" required' : '') . '></p>';
        }
        echo '<p>Set a featured image for custom story artwork, or leave it empty to use the product image. Publish to display; move to Draft to hide. Use Order under Attributes to sort stories. Scheduled posts appear after publication. Prices, stock and product links come from the live product.</p>';
    }
    public static function save($id) {
        if (wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('manage_woocommerce') || !current_user_can('edit_post', $id)) { return; }
        if (!isset($_POST['eldokan_story_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eldokan_story_nonce'])), 'eldokan_story_save')) { return; }
        $data = isset($_POST['eldokan_story']) && is_array($_POST['eldokan_story']) ? wp_unslash($_POST['eldokan_story']) : [];
        $product_id = isset($data['product_id']) && is_scalar($data['product_id']) ? absint($data['product_id']) : 0;
        $seller_id = isset($data['seller_id']) && is_scalar($data['seller_id']) ? absint($data['seller_id']) : 0;
        $card = ElDokan_Stories_Picker::card($product_id);
        $valid_product = $card && $seller_id && ($card['seller']['id'] ?? '') === 'sel_' . $seller_id
            && absint(get_post_meta($product_id, '_eldokan_seller_user_id', true)) === $seller_id;
        update_post_meta($id, '_eldokan_story_product_id', $valid_product ? $product_id : 0);
        update_post_meta($id, '_eldokan_story_seller_id', $valid_product ? $seller_id : 0);
        if ($valid_product) { delete_post_meta($id, '_eldokan_story_picker_error'); }
        else { update_post_meta($id, '_eldokan_story_picker_error', 'Select a valid seller and one of their public products. This story will not appear until its product is selected.'); }
        foreach (['title_en', 'title_ar'] as $key) { update_post_meta($id, '_eldokan_story_' . $key, sanitize_text_field($data[$key] ?? '')); }
        $raw = sanitize_text_field($data['expires'] ?? '');
        $date = $raw ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, wp_timezone()) : false;
        $valid = $date && $date->format('Y-m-d\TH:i') === $raw;
        update_post_meta($id, '_eldokan_story_expires', $valid ? $raw : '');
        update_post_meta($id, '_eldokan_story_expires_at', $valid ? $date->getTimestamp() : 0);
    }
    public static function listing($request) {
        if (!class_exists('ElDokan_Customer_API_Products') || !class_exists('ElDokan_Customer_API_Language') || !function_exists('wc_get_product')) {
            return new WP_Error('stories_dependency_unavailable', 'Activate WooCommerce and ElDokan Customer API.', ['status' => 503]);
        }
        $lang = $request->get_param('lang') === 'ar' ? 'ar' : 'en';
        // Stories are bilingual records, not separate translated posts. Load the
        // canonical records before switching the product translation context.
        $posts = get_posts(['post_type' => 'eldokan_story', 'post_status' => 'publish', 'posts_per_page' => 24, 'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'], 'suppress_filters' => true, 'lang' => '',
                'meta_query' => ['relation' => 'OR', ['key' => '_eldokan_story_expires_at', 'compare' => 'NOT EXISTS'], ['key' => '_eldokan_story_expires_at', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'], ['key' => '_eldokan_story_expires_at', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC']]]);
        return ElDokan_Customer_API_Language::run($request, function () use ($lang, $posts) {
            $items = [];
            foreach ($posts as $post) {
                $product_id = absint(get_post_meta($post->ID, '_eldokan_story_product_id', true));
                $product = ElDokan_Customer_API_Products::card_by_internal_id($product_id, $lang);
                if (is_wp_error($product) && $product->get_error_code() === 'product_not_found') {
                    // A missing translation must not remove a valid published product.
                    $product = ElDokan_Customer_API_Products::card_by_internal_id($product_id);
                }
                if (is_wp_error($product) || ($product['stock']['status'] ?? '') !== 'in_stock') { continue; }
                $title = get_post_meta($post->ID, '_eldokan_story_title_' . $lang, true);
                $url = get_the_post_thumbnail_url($post->ID, 'large');
                $items[] = ['id' => 'sty_' . $post->ID, 'title' => $title ?: $post->post_title, 'image' => $url ? ['url' => esc_url_raw($url), 'alt' => $title ?: $post->post_title] : null, 'product' => $product];
            }
            $response = new WP_REST_Response(['success' => true, 'data' => ['items' => $items], 'meta' => ['request_id' => 'req_' . str_replace('-', '', wp_generate_uuid4())]], 200);
            $response->header('Cache-Control', 'no-store');
            $response->header('Content-Language', $lang);
            return $response;
        });
    }
}
ElDokan_Stories::init();
