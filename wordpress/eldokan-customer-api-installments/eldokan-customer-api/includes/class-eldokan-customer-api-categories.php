<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Categories {

    public static function list(WP_REST_Request $request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $parent_slug = sanitize_title((string) $request->get_param('parent'));
        $parent_id = 0;

        if ($parent_slug !== '') {
            $parent = self::term_by_slug($parent_slug);
            if (!$parent || is_wp_error($parent)) {
                return new WP_Error(
                    'category_not_found',
                    'Parent category not found.',
                    ['status' => 404]
                );
            }
            $parent_id = (int) $parent->term_id;
        }

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $parent_id,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return $terms;
        }

        return array_values(
            array_map([__CLASS__, 'serialize_term'], $terms)
        );
    }

    public static function single($slug) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $term = self::term_by_slug($slug);

        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'category_not_found',
                'Category not found.',
                ['status' => 404]
            );
        }

        $data = self::serialize_term($term);

        $children = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => (int) $term->term_id,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        $data['children'] = is_wp_error($children)
            ? []
            : array_values(
                array_map([__CLASS__, 'serialize_term'], $children)
            );

        return $data;
    }

    public static function filters($slug) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) {
            return $required;
        }

        $term = self::term_by_slug($slug);

        if (!$term || is_wp_error($term)) {
            return new WP_Error(
                'category_not_found',
                'Category not found.',
                ['status' => 404]
            );
        }

        if (!function_exists('wc_get_attribute_taxonomies')) {
            return [
                'category_id' => ElDokan_Customer_API_Utils::opaque_id(
                    'cat',
                    $term->term_id
                ),
                'source' => [
                    'mode' => 'unavailable',
                    'category_id' => null,
                ],
                'attributes' => [],
            ];
        }

        $rule = self::resolve_attribute_rule((int) $term->term_id);
        $definitions = self::attribute_definitions();

        if ($rule !== null && $rule['mode'] === 'none') {
            return [
                'category_id' => ElDokan_Customer_API_Utils::opaque_id(
                    'cat',
                    $term->term_id
                ),
                'source' => [
                    'mode' => 'none',
                    'category_id' => ElDokan_Customer_API_Utils::opaque_id(
                        'cat',
                        $rule['source_category_id']
                    ),
                ],
                'attributes' => [],
            ];
        }

        $used_terms = self::used_attribute_terms_for_category(
            (int) $term->term_id
        );

        if (is_wp_error($used_terms)) {
            return $used_terms;
        }

        $allowed_taxonomies = null;
        $source_mode = 'inferred_from_products';
        $source_category_id = (int) $term->term_id;

        if ($rule !== null) {
            $allowed_taxonomies = [];

            foreach ($rule['ids'] as $attribute_id) {
                if (!isset($definitions['by_id'][$attribute_id])) {
                    continue;
                }

                $taxonomy = $definitions['by_id'][$attribute_id]['taxonomy'];
                $allowed_taxonomies[$taxonomy] = true;
            }

            $source_mode = (
                (int) $rule['source_category_id'] === (int) $term->term_id
            )
                ? 'configured'
                : 'inherited';

            $source_category_id = (int) $rule['source_category_id'];
        }

        $items = [];

        foreach ($definitions['ordered'] as $definition) {
            $taxonomy = $definition['taxonomy'];

            if (
                is_array($allowed_taxonomies) &&
                !isset($allowed_taxonomies[$taxonomy])
            ) {
                continue;
            }

            if (
                $rule === null &&
                empty($used_terms[$taxonomy])
            ) {
                continue;
            }

            $options = [];

            foreach (($used_terms[$taxonomy] ?? []) as $option) {
                $options[] = [
                    'id' => ElDokan_Customer_API_Utils::opaque_id(
                        'atr',
                        $option['term_id']
                    ),
                    'name' => ElDokan_Customer_API_Utils::clean_text(
                        $option['name']
                    ),
                    'slug' => $option['slug'],
                ];
            }

            $items[] = [
                'id' => ElDokan_Customer_API_Utils::opaque_id(
                    'att',
                    $definition['id']
                ),
                'name' => ElDokan_Customer_API_Utils::clean_text(
                    $definition['name']
                ),
                'slug' => $definition['slug'],
                // The internal Woo taxonomy name is intentionally not public.
                'options' => $options,
            ];
        }

        return [
            'category_id' => ElDokan_Customer_API_Utils::opaque_id(
                'cat',
                $term->term_id
            ),
            'source' => [
                'mode' => $source_mode,
                'category_id' => ElDokan_Customer_API_Utils::opaque_id(
                    'cat',
                    $source_category_id
                ),
            ],
            'attributes' => $items,
        ];
    }

    /**
     * Mirrors the existing ElDokan Admin rule semantics exactly:
     * - custom: explicit attributes
     * - none: explicit no attributes
     * - inherit/unset: continue to nearest parent
     * - no explicit rule anywhere: null, so discovery comes from products
     */
    private static function resolve_attribute_rule($category_id) {
        $visited = [];

        while ($category_id && !isset($visited[$category_id])) {
            $visited[$category_id] = true;

            $term = get_term($category_id, 'product_cat');
            if (!$term || is_wp_error($term)) {
                break;
            }

            $mode = get_term_meta(
                $category_id,
                '_eldokan_attribute_mode',
                true
            );

            if ($mode === 'none') {
                return [
                    'mode' => 'none',
                    'ids' => [],
                    'source_category_id' => (int) $category_id,
                ];
            }

            if ($mode === 'custom') {
                $ids = (array) get_term_meta(
                    $category_id,
                    '_eldokan_attribute_ids',
                    true
                );

                return [
                    'mode' => 'custom',
                    'ids' => array_values(
                        array_unique(
                            array_filter(
                                array_map('absint', $ids)
                            )
                        )
                    ),
                    'source_category_id' => (int) $category_id,
                ];
            }

            $category_id = (int) $term->parent;
        }

        return null;
    }

    private static function attribute_definitions() {
        $ordered = [];
        $by_id = [];

        foreach (wc_get_attribute_taxonomies() as $attribute) {
            $id = (int) $attribute->attribute_id;
            $taxonomy = wc_attribute_taxonomy_name(
                $attribute->attribute_name
            );

            if (!$id || !$taxonomy) {
                continue;
            }

            $definition = [
                'id' => $id,
                'name' => (string) $attribute->attribute_label,
                'slug' => (string) $attribute->attribute_name,
                'taxonomy' => $taxonomy,
            ];

            $ordered[] = $definition;
            $by_id[$id] = $definition;
        }

        usort($ordered, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'ordered' => $ordered,
            'by_id' => $by_id,
        ];
    }

    /**
     * Finds only attribute terms actually used by published parent products
     * in the requested category or any child category.
     *
     * This avoids loading hundreds of WC_Product objects just to discover
     * taxonomy attributes.
     */
    private static function used_attribute_terms_for_category($category_id) {
        global $wpdb;

        $category_ids = [(int) $category_id];

        $children = get_term_children(
            (int) $category_id,
            'product_cat'
        );

        if (!is_wp_error($children)) {
            foreach ($children as $child_id) {
                $category_ids[] = (int) $child_id;
            }
        }

        $category_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $category_ids)
                )
            )
        );

        if (!$category_ids) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($category_ids), '%d')
        );

        $visibility = function_exists('wc_get_product_visibility_term_ids')
            ? wc_get_product_visibility_term_ids()
            : [];
        $excluded_id = absint($visibility['exclude-from-catalog'] ?? 0);
        $excluded_sql = $excluded_id ? $wpdb->prepare("AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} AS hidden_rel
            INNER JOIN {$wpdb->term_taxonomy} AS hidden_tt
                ON hidden_tt.term_taxonomy_id = hidden_rel.term_taxonomy_id
            WHERE hidden_rel.object_id = p.ID AND hidden_tt.term_id = %d
                AND hidden_tt.taxonomy = 'product_visibility'
        )", $excluded_id) : '';
        // The translation provider owns its optimized language scope.
        $language_sql = ElDokan_Customer_API_Language::product_language_sql('p');

        $sql = "
            SELECT DISTINCT
                attr_tt.taxonomy AS taxonomy,
                attr_term.term_id AS term_id,
                attr_term.name AS term_name,
                attr_term.slug AS term_slug
            FROM {$wpdb->term_relationships} AS cat_rel
            INNER JOIN {$wpdb->term_taxonomy} AS cat_tt
                ON cat_tt.term_taxonomy_id = cat_rel.term_taxonomy_id
            INNER JOIN {$wpdb->posts} AS p
                ON p.ID = cat_rel.object_id
            INNER JOIN {$wpdb->term_relationships} AS attr_rel
                ON attr_rel.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} AS attr_tt
                ON attr_tt.term_taxonomy_id = attr_rel.term_taxonomy_id
            INNER JOIN {$wpdb->terms} AS attr_term
                ON attr_term.term_id = attr_tt.term_id
            WHERE cat_tt.taxonomy = 'product_cat'
              AND cat_tt.term_id IN ({$placeholders})
              AND p.post_type = 'product'
              AND p.post_status = 'publish'
              AND p.post_parent = 0
              AND p.post_password = ''
              {$excluded_sql}
              {$language_sql}
              AND attr_tt.taxonomy LIKE 'pa\\_%'
            ORDER BY attr_tt.taxonomy ASC, attr_term.name ASC
        ";

        $prepared = $wpdb->prepare(
            $sql,
            ...$category_ids
        );

        $rows = $wpdb->get_results(
            $prepared,
            ARRAY_A
        );

        if ($rows === null && !empty($wpdb->last_error)) {
            return new WP_Error(
                'category_filters_failed',
                'Could not build category filters.',
                ['status' => 500]
            );
        }

        $map = [];

        foreach ((array) $rows as $row) {
            $taxonomy = sanitize_key(
                (string) ($row['taxonomy'] ?? '')
            );

            if (
                $taxonomy === '' ||
                strpos($taxonomy, 'pa_') !== 0
            ) {
                continue;
            }

            $term_id = absint($row['term_id'] ?? 0);
            if (!$term_id) {
                continue;
            }

            $map[$taxonomy][$term_id] = [
                'term_id' => $term_id,
                'name' => (string) ($row['term_name'] ?? ''),
                'slug' => sanitize_title(
                    (string) ($row['term_slug'] ?? '')
                ),
            ];
        }

        foreach ($map as $taxonomy => $terms) {
            $map[$taxonomy] = array_values($terms);
        }

        return $map;
    }

    public static function serialize_term($term) {
        $thumbnail_id = absint(
            get_term_meta(
                $term->term_id,
                'thumbnail_id',
                true
            )
        );

        $image = ElDokan_Customer_API_Utils::image(
            $thumbnail_id,
            'woocommerce_thumbnail'
        );

        return [
            'id' => ElDokan_Customer_API_Utils::opaque_id(
                'cat',
                $term->term_id
            ),
            'name' => ElDokan_Customer_API_Utils::clean_text(
                $term->name
            ),
            'slug' => $term->slug,
            'description' => ElDokan_Customer_API_Utils::clean_html(
                $term->description
            ),
            'count' => (int) $term->count,
            'parent_id' => $term->parent
                ? ElDokan_Customer_API_Utils::opaque_id(
                    'cat',
                    $term->parent
                )
                : null,
            'image' => $image,
        ];
    }

    private static function term_by_slug($slug) {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'slug' => sanitize_title($slug),
            'number' => 1,
        ]);
        return is_wp_error($terms) || empty($terms) ? false : reset($terms);
    }
}
