<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Utils {

    public static function require_woocommerce() {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
            return new WP_Error(
                'woocommerce_unavailable',
                'WooCommerce is not available.',
                ['status' => 503]
            );
        }

        return true;
    }

    public static function requested_language(WP_REST_Request $request) {
        $lang = sanitize_key((string) $request->get_param('lang'));

        if (in_array($lang, ['ar', 'en'], true)) {
            return $lang;
        }

        $header = strtolower((string) $request->get_header('accept-language'));
        if (strpos($header, 'ar') === 0) {
            return 'ar';
        }
        if (strpos($header, 'en') === 0) {
            return 'en';
        }

        return null;
    }

    public static function opaque_id($prefix, $id) {
        $id = absint($id);
        return $id ? sanitize_key($prefix) . '_' . $id : null;
    }

    public static function parse_opaque_id($prefix, $value) {
        $prefix = preg_quote(sanitize_key($prefix), '/');
        $value = (string) $value;

        if (!preg_match('/^' . $prefix . '_(\d+)$/', $value, $matches)) {
            return 0;
        }

        return absint($matches[1]);
    }

    public static function clean_text($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value);
    }

    public static function clean_html($value) {
        $value = strip_shortcodes((string) $value);
        $value = wp_kses_post($value);

        // Normalize non-breaking spaces without flattening the allowed HTML.
        $value = str_ireplace(
            ['&nbsp;', '&#160;', '&#xa0;'],
            ' ',
            $value
        );
        $value = str_replace("\xC2\xA0", ' ', $value);

        // Gutenberg/editor metadata must never become part of the public API contract.
        $value = preg_replace(
            '/\sdata-[a-zA-Z0-9:_-]+\s*=\s*(["\']).*?\1/s',
            '',
            $value
        );

        return trim((string) $value);
    }

    public static function normalize_stock_status($status) {
        $map = [
            'instock' => 'in_stock',
            'outofstock' => 'out_of_stock',
            'onbackorder' => 'on_backorder',
            'in_stock' => 'in_stock',
            'out_of_stock' => 'out_of_stock',
            'on_backorder' => 'on_backorder',
        ];

        $status = sanitize_key((string) $status);

        return $map[$status] ?? 'out_of_stock';
    }

    public static function to_woocommerce_stock_status($status) {
        $map = [
            'in_stock' => 'instock',
            'out_of_stock' => 'outofstock',
            'on_backorder' => 'onbackorder',
            'instock' => 'instock',
            'outofstock' => 'outofstock',
            'onbackorder' => 'onbackorder',
        ];

        $status = sanitize_key((string) $status);

        return $map[$status] ?? null;
    }

    public static function money($value) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EGP';

        if ($value === '' || $value === null) {
            return null;
        }

        $normalized = function_exists('wc_format_decimal')
            ? wc_format_decimal($value, $decimals)
            : number_format((float) $value, $decimals, '.', '');

        $negative = strpos($normalized, '-') === 0;
        $normalized = ltrim($normalized, '-');

        $parts = explode('.', $normalized, 2);
        $whole = preg_replace('/\D+/', '', $parts[0] ?? '0');
        $fraction = preg_replace('/\D+/', '', $parts[1] ?? '');
        $fraction = str_pad(substr($fraction, 0, $decimals), $decimals, '0');

        $factor = 10 ** $decimals;
        $minor = ((int) $whole * $factor) + (int) ($fraction ?: 0);
        if ($negative) {
            $minor *= -1;
        }

        $formatted = function_exists('wc_price')
            ? self::clean_text(wc_price((float) $value))
            : self::clean_text($currency . ' ' . $normalized);

        return [
            'amount' => $minor,
            'currency' => $currency,
            'decimals' => $decimals,
            'formatted' => $formatted,
        ];
    }

    public static function decimal($value) {
        return function_exists('wc_format_decimal')
            ? wc_format_decimal($value)
            : preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    public static function image($attachment_id, $size = 'woocommerce_thumbnail') {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            return null;
        }

        $url = wp_get_attachment_image_url($attachment_id, $size);
        if (!$url) {
            return null;
        }

        return [
            'url' => esc_url_raw($url),
            'alt' => self::clean_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
        ];
    }

    public static function brand_taxonomy() {
        foreach (['product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand'] as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                return $taxonomy;
            }
        }

        return null;
    }

    public static function product_brand($product_id) {
        $taxonomy = self::brand_taxonomy();
        if (!$taxonomy) {
            return null;
        }

        $terms = get_the_terms($product_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            return null;
        }

        $term = reset($terms);

        return self::serialize_brand($term);
    }

    /**
     * Normalize the existing WooCommerce Brand taxonomy thumbnail.
     *
     * WooCommerce stores the Brand edit-screen Thumbnail attachment in the
     * taxonomy term's `thumbnail_id` meta. The attachment ID remains an
     * adapter detail and is never exposed by the public projection.
     */
    public static function serialize_brand($term) {
        if (!$term || is_wp_error($term)) {
            return null;
        }

        $thumbnail_id = absint(
            get_term_meta($term->term_id, 'thumbnail_id', true)
        );

        return [
            'id' => self::opaque_id('brd', $term->term_id),
            'name' => self::clean_text($term->name),
            'slug' => $term->slug,
            'image' => self::image($thumbnail_id, 'woocommerce_thumbnail'),
        ];
    }
}
