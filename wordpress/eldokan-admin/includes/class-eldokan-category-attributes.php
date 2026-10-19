<?php
if (!defined('ABSPATH')) exit;

/** Category attribute configuration owned exclusively by ElDokan Admin. */
final class ElDokan_Admin_Category_Attributes {
    public function __construct() {
        add_action('admin_post_eldokan_admin_save_category_attributes', [$this, 'save']);
        // Runs after the original route's permission callback. Do not register a
        // competing endpoint or bypass the Dashboard API authentication layer.
        add_filter('rest_dispatch_request', [$this, 'dispatch'], 20, 4);
    }

    /** null means no explicit rule; an empty array means explicitly none. */
    public static function resolve($category_id) {
        $visited = [];
        while ($category_id && !isset($visited[$category_id])) {
            $visited[$category_id] = true;
            $term = get_term($category_id, 'product_cat');
            if (!$term || is_wp_error($term)) break;
            $mode = get_term_meta($category_id, '_eldokan_attribute_mode', true);
            if ($mode === 'none') return ['ids' => [], 'sourceCategoryId' => (int) $category_id];
            if ($mode === 'custom') {
                $ids = (array) get_term_meta($category_id, '_eldokan_attribute_ids', true);
                return ['ids' => array_values(array_unique(array_filter(array_map('absint', $ids)))), 'sourceCategoryId' => (int) $category_id];
            }
            $category_id = (int) $term->parent;
        }
        return null;
    }

    public function dispatch($result, $request, $route, $handler) {
        if ($result !== null || !in_array($request->get_method(), ['GET', 'HEAD'], true)) return $result;
        if (rtrim($request->get_route(), '/') !== '/eldokan/v1/product-attributes') return $result;
        // Only augment the existing official product-attributes handler.
        $callback = $handler['callback'] ?? null;
        if (!is_array($callback) || !isset($callback[0], $callback[1]) || !is_a($callback[0], 'ElDokan_Dashboard_API') || $callback[1] !== 'list_product_attributes') return $result;
        if (empty($handler['permission_callback'])) return $result;
        if (!function_exists('wc_get_attribute_taxonomies')) return $result;
        $category_id = absint($request->get_param('categoryId'));
        if (!$category_id) return $result;
        $rule = self::resolve($category_id);
        // Preserve the old API's discovery for all unconfigured categories.
        if ($rule === null) return $result;
        $allowed = array_fill_keys($rule['ids'], true);
        $items = [];
        foreach (wc_get_attribute_taxonomies() as $attribute) {
            if (!isset($allowed[(int) $attribute->attribute_id])) continue;
            $items[] = [
                'id' => (int) $attribute->attribute_id,
                'name' => html_entity_decode($attribute->attribute_label, ENT_QUOTES, 'UTF-8'),
                'slug' => $attribute->attribute_name,
                'taxonomy' => wc_attribute_taxonomy_name($attribute->attribute_name),
            ];
        }
        usort($items, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
        return [
            'success' => true,
            'categoryId' => $category_id,
            'attributes' => $items,
        ];
    }

    public static function panel($category_id) {
        if (!current_user_can('eldokan_backoffice_manage_categories')) wp_die('Not allowed');
        $term = get_term($category_id, 'product_cat');
        $back = admin_url('admin.php?page=eldokan-admin-categories');
        echo '<p><a class="button" href="' . esc_url($back) . '">Back to Categories / العودة للفئات</a></p>';
        if (!$term || is_wp_error($term)) {
            echo '<div class="notice notice-error"><p>Category not found.</p></div>';
            return;
        }
        if (!function_exists('wc_get_attribute_taxonomies')) {
            echo '<div class="notice notice-error"><p>WooCommerce is required.</p></div>';
            return;
        }
        $mode = get_term_meta($category_id, '_eldokan_attribute_mode', true) ?: 'inherit';
        $ids = array_map('absint', (array) get_term_meta($category_id, '_eldokan_attribute_ids', true));
        $attributes = wc_get_attribute_taxonomies();
        $effective = self::resolve($category_id);
        if (!empty($_GET['attributes_saved'])) echo '<div class="notice notice-success"><p>تم حفظ خصائص الفئة. أعد اختيار الفئة في لوحة التجار لعرضها.</p></div>';
        echo '<div class="eda-card"><h2>Attributes — ' . esc_html($term->name) . ' #' . absint($category_id) . '</h2>';
        if ($effective === null) {
            echo '<p>المصدر الحالي: الاستنتاج من المنتجات الموجودة. لتوفير الخصائص قبل إضافة أي منتج، حددها هنا أو في إحدى الفئات الأم.</p>';
        } else {
            $source = get_term($effective['sourceCategoryId'], 'product_cat');
            $names = [];
            foreach ($attributes as $attribute) {
                if (in_array((int) $attribute->attribute_id, $effective['ids'], true)) $names[] = $attribute->attribute_label;
            }
            echo '<p>الإعداد الفعّال من: <strong>' . esc_html($source && !is_wp_error($source) ? $source->name : $effective['sourceCategoryId']) . '</strong></p>';
            echo '<p>الخصائص الحالية: ' . esc_html($names ? implode('، ', $names) : 'بدون خصائص') . '</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('eda_category_attributes_' . $category_id);
        echo '<input type="hidden" name="action" value="eldokan_admin_save_category_attributes"><input type="hidden" name="category_id" value="' . absint($category_id) . '">';
        echo '<p><label for="eda-attribute-mode"><strong>طريقة تحديد الخصائص / Attribute mode</strong></label></p><select id="eda-attribute-mode" name="attribute_mode">';
        foreach (['inherit' => 'توريث من الفئة الأم؛ وإلا الاستنتاج الحالي', 'custom' => 'تحديد خصائص هذه الفئة', 'none' => 'بدون خصائص'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($mode, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p>في وضع التحديد، اختيارات هذه الفئة تستبدل قائمة الأم بالكامل. ترك الاختيارات فارغة يعني بدون خصائص. الفئات الفرعية تورث أقرب إعداد صريح ما لم تُخصص بشكل منفصل.</p>';
        echo '<fieldset id="eda-attribute-choices"><legend><strong>الخصائص / Attributes</strong></legend>';
        foreach ($attributes as $attribute) {
            $id = (int) $attribute->attribute_id;
            echo '<p><label><input type="checkbox" name="attribute_ids[]" value="' . esc_attr($id) . '" ' . checked(in_array($id, $ids, true), true, false) . '> ' . esc_html($attribute->attribute_label) . ' (' . esc_html($attribute->attribute_name) . ')</label></p>';
        }
        if (!$attributes) echo '<p>لا توجد خصائص عامة. أضفها أولًا من المنتجات ← السمات (Attributes).</p>';
        echo '</fieldset><p>الإعداد يحدد قائمة الاختيار للتاجر، ولا يعدّل خصائص المنتجات المحفوظة.</p><p><button type="submit" class="button button-primary">Save Attributes / حفظ الخصائص</button></p></form></div>';
        echo '<script>(function(){var mode=document.getElementById("eda-attribute-mode"), choices=document.getElementById("eda-attribute-choices");function sync(){choices.hidden=mode.value!=="custom";}mode.addEventListener("change",sync);sync();})();</script>';
    }

    public function save() {
        if (!current_user_can('eldokan_backoffice_manage_categories')) wp_die('Not allowed');
        if (!isset($_POST['category_id']) || !is_scalar($_POST['category_id'])) wp_die('Invalid category');
        $id = absint($_POST['category_id']);
        check_admin_referer('eda_category_attributes_' . $id);
        $term = get_term($id, 'product_cat');
        if (!$id || !$term || is_wp_error($term) || !current_user_can('edit_term', $id)) wp_die('Invalid category or permission');
        if (!function_exists('wc_get_attribute_taxonomies')) wp_die('WooCommerce is required');
        if (!isset($_POST['attribute_mode']) || !is_string($_POST['attribute_mode'])) wp_die('Invalid mode');
        $mode = sanitize_key(wp_unslash($_POST['attribute_mode']));
        if (!in_array($mode, ['inherit', 'custom', 'none'], true)) wp_die('Invalid mode');
        $submitted = isset($_POST['attribute_ids']) && is_array($_POST['attribute_ids']) ? $_POST['attribute_ids'] : [];
        $submitted = array_map('absint', array_filter($submitted, 'is_scalar'));
        $valid = array_map(function ($attribute) { return (int) $attribute->attribute_id; }, wc_get_attribute_taxonomies());
        $ids = array_values(array_unique(array_intersect($submitted, $valid)));
        // Reuse the earlier API prototype's keys, preserving any saved rules.
        update_term_meta($id, '_eldokan_attribute_ids', $ids);
        update_term_meta($id, '_eldokan_attribute_mode', $mode);
        wp_safe_redirect(add_query_arg(['page' => 'eldokan-admin-categories', 'category_attributes' => $id, 'attributes_saved' => 1], admin_url('admin.php')));
        exit;
    }
}
