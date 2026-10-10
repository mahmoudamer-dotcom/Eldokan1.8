<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Home {

    const SECTIONS_OPTION = 'eldokan_home_sections_v1';
    const SLIDE_TYPE = 'eldokan_home_slide';
    const BANNER_TYPE = 'eldokan_home_banner';

    public static function get(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $language = ElDokan_Customer_API_Language::effective($request);
        $config = self::sections_config();
        $sections = [];

        foreach ($config as $id => $settings) {
            if (empty($settings['enabled'])) continue;
            $items = [];
            $type = '';

            switch ($id) {
                case 'hero_slider':
                    $type = 'hero_slider';
                    $items = self::editorial_items(self::SLIDE_TYPE, 'hsl', $language, 'hero');
                    break;
                case 'banner_grid':
                    $type = 'banner_grid';
                    $items = self::editorial_items(self::BANNER_TYPE, 'hbn', $language, 'banner');
                    break;
                case 'categories':
                    $type = 'category_grid';
                    $items = self::top_categories(10);
                    break;
                case 'featured_deals':
                    $type = 'product_carousel';
                    $items = self::products(['on_sale' => true, 'per_page' => 8, 'sort' => 'newest']);
                    break;
                case 'best_sellers':
                    $type = 'product_carousel';
                    $items = self::products(['per_page' => 8, 'sort' => 'best_selling']);
                    break;
                case 'new_arrivals':
                    $type = 'product_carousel';
                    $items = self::products(['per_page' => 8, 'sort' => 'newest']);
                    break;
                case 'brands':
                    $type = 'brand_grid';
                    $items = self::brands(16);
                    break;
            }

            if (!$type || (in_array($type, ['hero_slider', 'banner_grid'], true) && !$items)) continue;
            $sections[] = [
                'id' => $id,
                'type' => $type,
                'enabled' => true,
                'order' => absint($settings['order']),
                'title' => ElDokan_Customer_API_Utils::clean_text($settings['title_' . $language] ?? ''),
                'items' => $items,
            ];
        }

        usort($sections, function ($a, $b) {
            $order = $a['order'] <=> $b['order'];
            return $order !== 0 ? $order : strcmp($a['id'], $b['id']);
        });

        return [
            'sections' => $sections,
        ];
    }

    private static function sections_config() {
        $defaults = [
            'hero_slider' => ['enabled' => true, 'order' => 1, 'title_ar' => '', 'title_en' => ''],
            'categories' => ['enabled' => true, 'order' => 2, 'title_ar' => 'تسوق حسب القسم', 'title_en' => 'Shop by Category'],
            'banner_grid' => ['enabled' => true, 'order' => 3, 'title_ar' => '', 'title_en' => ''],
            'featured_deals' => ['enabled' => true, 'order' => 4, 'title_ar' => 'عروض مميزة', 'title_en' => 'Featured Deals'],
            'best_sellers' => ['enabled' => true, 'order' => 5, 'title_ar' => 'الأكثر مبيعًا', 'title_en' => 'Best Sellers'],
            'new_arrivals' => ['enabled' => true, 'order' => 6, 'title_ar' => 'وصل حديثًا', 'title_en' => 'New Arrivals'],
            'brands' => ['enabled' => true, 'order' => 7, 'title_ar' => 'علامات تجارية مميزة', 'title_en' => 'Featured Brands'],
        ];
        $stored = get_option(self::SECTIONS_OPTION, []);
        if (!is_array($stored)) return $defaults;
        foreach ($defaults as $id => $row) {
            if (isset($stored[$id]) && is_array($stored[$id])) {
                $defaults[$id] = wp_parse_args($stored[$id], $row);
            }
        }
        return $defaults;
    }

    private static function editorial_items($post_type, $prefix, $language, $kind) {
        if (!post_type_exists($post_type)) return [];

        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_key' => '_eldokan_home_order',
            'orderby' => ['meta_value_num' => 'ASC', 'ID' => 'ASC'],
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_eldokan_home_enabled', 'value' => '1'],
                ['key' => '_eldokan_home_lang', 'value' => $language],
            ],
            'suppress_filters' => false,
        ]);

        $now = current_datetime();
        $items = [];
        foreach ($posts as $post) {
            $starts = (string) get_post_meta($post->ID, '_eldokan_home_starts_at', true);
            $ends = (string) get_post_meta($post->ID, '_eldokan_home_ends_at', true);
            $starts_at = self::site_datetime($starts);
            $ends_at = self::site_datetime($ends);
            if (($starts_at && $starts_at > $now) || ($ends_at && $ends_at < $now)) continue;

            $cta_type = sanitize_key((string) get_post_meta($post->ID, '_eldokan_home_cta_type', true));
            $cta_value = self::cta_value($cta_type, get_post_meta($post->ID, '_eldokan_home_cta_value', true));
            $cta_text = ElDokan_Customer_API_Utils::clean_text(get_post_meta($post->ID, '_eldokan_home_cta_text', true));
            $cta = null;
            if (in_array($cta_type, ['url', 'product', 'category', 'search'], true) && $cta_value !== '') {
                $cta = ['text' => $cta_text, 'type' => $cta_type, 'value' => $cta_value];
            }

            $items[] = [
                'id' => ElDokan_Customer_API_Utils::opaque_id($prefix, $post->ID),
                'kind' => $kind,
                'title' => ElDokan_Customer_API_Utils::clean_text($post->post_title),
                'subtitle' => ElDokan_Customer_API_Utils::clean_text(get_post_meta($post->ID, '_eldokan_home_subtitle', true)),
                'desktop_image' => ElDokan_Customer_API_Utils::image(get_post_meta($post->ID, '_eldokan_home_desktop_image_id', true), 'full'),
                'mobile_image' => ElDokan_Customer_API_Utils::image(get_post_meta($post->ID, '_eldokan_home_mobile_image_id', true), 'full'),
                'cta' => $cta,
            ];
        }
        return $items;
    }

    private static function cta_value($type, $value) {
        $value = trim((string) $value);
        if ($type === 'url') return esc_url_raw($value);
        if ($type === 'product') return preg_match('/^prd_[1-9][0-9]*$/', $value) ? $value : '';
        if ($type === 'category') return sanitize_title($value);
        if ($type === 'search') return sanitize_text_field($value);
        return '';
    }

    private static function site_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') return null;

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, wp_timezone());
        if (!$date) return null;

        $errors = DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date;
    }

    private static function products($params) {
        $request = new WP_REST_Request('GET');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        $result = ElDokan_Customer_API_Products::list($request);
        if (is_wp_error($result)) {
            return [];
        }

        return $result['items'];
    }

    private static function top_categories($limit) {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0,
            'number' => absint($limit),
            'orderby' => 'count',
            'order' => 'DESC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_values(array_map(['ElDokan_Customer_API_Categories', 'serialize_term'], $terms));
    }

    private static function brands($limit) {
        $taxonomy = ElDokan_Customer_API_Utils::brand_taxonomy();
        if (!$taxonomy) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => absint($limit),
            'orderby' => 'count',
            'order' => 'DESC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return array_values(array_map(
            ['ElDokan_Customer_API_Utils', 'serialize_brand'],
            $terms
        ));
    }
}
