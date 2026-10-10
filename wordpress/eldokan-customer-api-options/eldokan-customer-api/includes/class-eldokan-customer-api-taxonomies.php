<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Public catalog term collections.
 *
 * Taxonomy names stay inside the WordPress adapter. The public contract only
 * exposes stable ElDokan IDs, localized names and slugs.
 */
final class ElDokan_Customer_API_Taxonomies {

    public static function brands(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $taxonomy = ElDokan_Customer_API_Utils::brand_taxonomy();

        if (!$taxonomy) {
            return self::empty_result($request);
        }

        return self::terms(
            $request,
            $taxonomy,
            'brd',
            ['ElDokan_Customer_API_Utils', 'serialize_brand']
        );
    }

    public static function tags(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        if (!taxonomy_exists('product_tag')) {
            return self::empty_result($request);
        }

        return self::terms($request, 'product_tag', 'tag');
    }

    private static function terms(
        WP_REST_Request $request,
        $taxonomy,
        $public_prefix,
        $serializer = null
    ) {
        $page = max(1, absint($request->get_param('page')));
        $per_page = max(
            1,
            min(100, absint($request->get_param('per_page')) ?: 50)
        );
        $search = trim(
            sanitize_text_field((string) $request->get_param('search'))
        );

        $base_args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ];

        if ($search !== '') {
            $base_args['search'] = $search;
        }

        $terms = get_terms(array_merge($base_args, [
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ]));

        if (is_wp_error($terms)) {
            return $terms;
        }

        $total = wp_count_terms(
            $taxonomy,
            array_filter([
                'hide_empty' => true,
                'search' => $search,
            ], function ($value) {
                return $value !== '';
            })
        );

        if (is_wp_error($total)) {
            return $total;
        }

        $items = [];
        foreach ($terms as $term) {
            if (is_callable($serializer)) {
                $items[] = call_user_func($serializer, $term);
                continue;
            }

            $items[] = [
                'id' => ElDokan_Customer_API_Utils::opaque_id(
                    $public_prefix,
                    $term->term_id
                ),
                'name' => ElDokan_Customer_API_Utils::clean_text($term->name),
                'slug' => $term->slug,
            ];
        }

        $total = (int) $total;

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total > 0
                ? (int) ceil($total / $per_page)
                : 0,
        ];
    }

    private static function empty_result(WP_REST_Request $request) {
        $page = max(1, absint($request->get_param('page')));
        $per_page = max(
            1,
            min(100, absint($request->get_param('per_page')) ?: 50)
        );

        return [
            'items' => [],
            'page' => $page,
            'per_page' => $per_page,
            'total' => 0,
            'total_pages' => 0,
        ];
    }
}
