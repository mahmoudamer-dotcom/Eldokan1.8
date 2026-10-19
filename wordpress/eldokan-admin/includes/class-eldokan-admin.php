<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/trait-eldokan-admin-rc5.php';

final class ElDokan_Admin {
    use ElDokan_Admin_RC5;
    private $commission_table;
    private $traffic_table;
    private $audit_table;
    private $access_roles_table;
    private $wcfm_vendor_table;
    private $wcfm_product_table;
    private $wcfm_order_table;
    private $wcfm_raw_table;

    public function __construct() {
        global $wpdb;
        $this->commission_table = $wpdb->prefix . 'eldokan_admin_commission_rules';
        $this->traffic_table = $wpdb->prefix . 'eldokan_admin_traffic';
        $this->audit_table = $wpdb->prefix . 'eldokan_admin_audit_log';
        $this->access_roles_table = $wpdb->prefix . 'eldokan_admin_access_roles';
        $this->wcfm_vendor_table = $wpdb->prefix . 'eldokan_wcfm_vendor_index';
        $this->wcfm_product_table = $wpdb->prefix . 'eldokan_wcfm_product_index';
        $this->wcfm_order_table = $wpdb->prefix . 'eldokan_wcfm_order_index';
        $this->wcfm_raw_table = $wpdb->prefix . 'eldokan_wcfm_raw_snapshot';

        add_action('admin_menu', [$this, 'menu'], 35);
        add_action('admin_init', [$this, 'ensure_index_schema'], 5);
        add_action('admin_init', [$this, 'ensure_builtin_access_roles'], 8);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('wp', [$this, 'track_visit'], 99);
        // Snapshot the effective seller fee rule on each new WooCommerce line item.
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'snapshot_order_item_fee_rule'], 30, 4);

        add_action('admin_post_eldokan_admin_save_commission', [$this, 'save_commission']);
        add_action('admin_post_eldokan_admin_save_category_commission', [$this, 'save_category_commission']);
        add_action('admin_post_eldokan_admin_clear_category_fee', [$this, 'clear_category_fee']);
        add_action('admin_post_eldokan_admin_save_product_commission', [$this, 'save_product_commission']);
        add_action('admin_post_eldokan_admin_delete_commission', [$this, 'delete_commission']);
        add_action('admin_post_eldokan_admin_rate_seller', [$this, 'rate_seller']);
        add_action('admin_post_eldokan_admin_seller_status', [$this, 'seller_status']);
        add_action('admin_post_eldokan_admin_save_dashboard_control', [$this, 'save_dashboard_control']);
        add_action('admin_post_eldokan_admin_save_access_role', [$this, 'save_access_role']);
        add_action('admin_post_eldokan_admin_delete_access_role', [$this, 'delete_access_role']);
        add_action('admin_post_eldokan_admin_assign_access_role', [$this, 'assign_access_role']);
        add_action('admin_post_eldokan_admin_update_seller_account_role', [$this, 'update_seller_account_role']);
        add_action('admin_post_eldokan_admin_delete_seller_account', [$this, 'delete_seller_account']);
        add_action('admin_post_eldokan_admin_update_wcfm_role', [$this, 'update_wcfm_role']);
        add_action('admin_post_eldokan_admin_bulk_wcfm_role', [$this, 'bulk_wcfm_role']);
        add_action('admin_post_eldokan_admin_save_email_settings', [$this, 'save_email_settings']);
        add_action('admin_post_eldokan_admin_send_test_email', [$this, 'send_test_email']);
        // ElDokan Admin is the central operational control plane.
        add_action('admin_post_eldokan_admin_registration_status', [$this, 'registration_status']);
        add_action('admin_post_eldokan_admin_delete_registration', [$this, 'delete_registration']);
        add_action('admin_post_eldokan_admin_view_registration_pdf', [$this, 'view_registration_pdf']);
        add_action('admin_post_eldokan_admin_update_order_status', [$this, 'update_order_status']);
        add_action('eldokan_admin_home_audit', [$this, 'audit_home_change'], 10, 3);

        add_action('rest_api_init', [$this, 'rest_routes']);
        add_action('wp_ajax_eldokan_wcfm_index_scan', [$this, 'wcfm_index_scan']);
        add_action('wp_ajax_eldokan_wcfm_index_batch', [$this, 'wcfm_index_batch']);
        add_action('wp_ajax_eldokan_wcfm_index_reset', [$this, 'wcfm_index_reset']);
        add_action('wp_ajax_eldokan_wcfm_index_rebuild', [$this, 'wcfm_index_rebuild']);
        add_action('wp_ajax_eldokan_wcfm_migration_scan', [$this, 'wcfm_migration_scan']);
        add_action('wp_ajax_eldokan_wcfm_migration_batch', [$this, 'wcfm_migration_batch']);
        add_action('wp_ajax_eldokan_wcfm_migration_reset', [$this, 'wcfm_migration_reset']);
        add_action('wp_ajax_eldokan_wcfm_migration_verify', [$this, 'wcfm_migration_verify']);
        add_action('wp_ajax_eldokan_wcfm_migration_verify_detailed', [$this, 'wcfm_migration_verify_detailed']);
        add_action('wp_ajax_eldokan_wcfm_migration_repair', [$this, 'wcfm_migration_repair']);

        // Complete ElDokan audit trail.
        add_action('shutdown', [$this, 'capture_eldokan_request_audit'], 999);
        add_filter('rest_request_after_callbacks', [$this, 'capture_eldokan_rest_audit'], 99, 3);
        add_action('user_register', [$this, 'audit_user_registered'], 20, 2);
        add_action('profile_update', [$this, 'audit_user_updated'], 20, 3);
        add_action('delete_user', [$this, 'audit_user_deleted'], 20, 3);
        add_action('save_post_product', [$this, 'audit_product_saved'], 20, 3);
        add_action('before_delete_post', [$this, 'audit_post_deleted'], 20, 2);
        add_action('transition_post_status', [$this, 'audit_product_status_changed'], 20, 3);
        add_action('created_product_cat', [$this, 'audit_product_category_created'], 20, 3);
        add_action('edited_product_cat', [$this, 'audit_product_category_updated'], 20, 3);
        add_action('delete_product_cat', [$this, 'audit_product_category_deleted'], 20, 4);
        add_action('woocommerce_new_order', [$this, 'audit_order_created'], 20, 2);
        add_action('woocommerce_order_status_changed', [$this, 'audit_order_status_changed'], 20, 4);
        add_action('wp_login', [$this, 'audit_wp_login'], 20, 2);
        add_action('wp_logout', [$this, 'audit_wp_logout'], 20, 1);
        add_action('admin_post_eldokan_admin_export_activity', [$this, 'export_activity_csv']);
        add_action('admin_post_eldokan_admin_export_seller_report', [$this, 'export_seller_report_excel']);
        add_action('admin_post_eldokan_admin_export_seller_report_pdf', [$this, 'export_seller_report_pdf']);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();

        $commission = $wpdb->prefix . 'eldokan_admin_commission_rules';
        $traffic = $wpdb->prefix . 'eldokan_admin_traffic';

        dbDelta("CREATE TABLE {$commission} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(20) NOT NULL DEFAULT 'global',
            seller_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            target_type VARCHAR(20) NOT NULL DEFAULT 'all',
            target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            calculation_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            value DECIMAL(18,4) NOT NULL DEFAULT 0,
            fixed_value DECIMAL(18,4) NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            KEY scope(scope),
            KEY seller_user_id(seller_user_id),
            KEY target_type(target_type),
            KEY target_id(target_id)
        ) $c;");

        dbDelta("CREATE TABLE {$traffic} (
            day DATE NOT NULL,
            page_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
            logged_in_views BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY(day)
        ) $c;");

        $audit = $wpdb->prefix . 'eldokan_admin_audit_log';

        dbDelta("CREATE TABLE {$audit} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id VARCHAR(191) NULL,
            created_at DATETIME NOT NULL,
            created_at_gmt DATETIME NOT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'system',
            action VARCHAR(191) NOT NULL DEFAULT '',
            actor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            actor_name VARCHAR(191) NOT NULL DEFAULT '',
            actor_email VARCHAR(191) NOT NULL DEFAULT '',
            seller_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            object_type VARCHAR(80) NOT NULL DEFAULT '',
            object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            description TEXT NULL,
            meta LONGTEXT NULL,
            ip_address VARCHAR(100) NOT NULL DEFAULT '',
            user_agent TEXT NULL,
            request_method VARCHAR(20) NOT NULL DEFAULT '',
            request_uri TEXT NULL,
            status_code INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id),
            UNIQUE KEY external_id(external_id),
            KEY created_at(created_at),
            KEY source(source),
            KEY action(action),
            KEY actor_user_id(actor_user_id),
            KEY seller_user_id(seller_user_id),
            KEY object_type(object_type),
            KEY object_id(object_id)
        ) $c;");

        $access_roles = $wpdb->prefix . 'eldokan_admin_access_roles';

        dbDelta("CREATE TABLE {$access_roles} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            permissions LONGTEXT NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY slug(slug)
        ) $c;");

        $wv = $wpdb->prefix . 'eldokan_wcfm_vendor_index';
        $wpd = $wpdb->prefix . 'eldokan_wcfm_product_index';
        $wo = $wpdb->prefix . 'eldokan_wcfm_order_index';
        $wr = $wpdb->prefix . 'eldokan_wcfm_raw_snapshot';

        dbDelta("CREATE TABLE {$wv} (
            seller_user_id BIGINT UNSIGNED NOT NULL,
            company_name VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            phone VARCHAR(100) NOT NULL DEFAULT '',
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            registered_at DATETIME NULL,
            store_settings LONGTEXT NULL,
            safe_user_meta LONGTEXT NULL,
            products_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            live_products BIGINT UNSIGNED NOT NULL DEFAULT 0,
            orders_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            units_sold DECIMAL(18,4) NOT NULL DEFAULT 0,
            gross_sales DECIMAL(18,4) NOT NULL DEFAULT 0,
            refunded_total DECIMAL(18,4) NOT NULL DEFAULT 0,
            cancelled_orders BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_order_at DATETIME NULL,
            last_synced_at DATETIME NOT NULL,
            PRIMARY KEY(seller_user_id),
            KEY company_name(company_name),
            KEY status(status)
        ) $c;");

        dbDelta("CREATE TABLE {$wpd} (
            product_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL,
            title TEXT NULL,
            sku VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(40) NOT NULL DEFAULT '',
            regular_price DECIMAL(18,4) NOT NULL DEFAULT 0,
            sale_price DECIMAL(18,4) NULL,
            stock_qty DECIMAL(18,4) NULL,
            categories LONGTEXT NULL,
            image_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_units_sold DECIMAL(18,4) NOT NULL DEFAULT 0,
            total_sales DECIMAL(18,4) NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            last_synced_at DATETIME NOT NULL,
            PRIMARY KEY(product_id),
            KEY seller_user_id(seller_user_id),
            KEY status(status),
            KEY sku(sku)
        ) $c;");

        dbDelta("CREATE TABLE {$wo} (
            row_key VARCHAR(191) NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_name TEXT NULL,
            sku VARCHAR(191) NOT NULL DEFAULT '',
            quantity DECIMAL(18,4) NOT NULL DEFAULT 0,
            line_subtotal DECIMAL(18,4) NOT NULL DEFAULT 0,
            line_total DECIMAL(18,4) NOT NULL DEFAULT 0,
            order_total DECIMAL(18,4) NOT NULL DEFAULT 0,
            order_status VARCHAR(40) NOT NULL DEFAULT '',
            customer_name VARCHAR(191) NOT NULL DEFAULT '',
            customer_email VARCHAR(191) NOT NULL DEFAULT '',
            payment_method VARCHAR(100) NOT NULL DEFAULT '',
            created_at DATETIME NULL,
            item_meta LONGTEXT NULL,
            last_synced_at DATETIME NOT NULL,
            PRIMARY KEY(row_key),
            KEY seller_user_id(seller_user_id),
            KEY order_id(order_id),
            KEY product_id(product_id),
            KEY order_status(order_status),
            KEY created_at(created_at)
        ) $c;");

        dbDelta("CREATE TABLE {$wr} (
            snapshot_key VARCHAR(191) NOT NULL,
            seller_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_table VARCHAR(191) NOT NULL DEFAULT '',
            source_id VARCHAR(191) NOT NULL DEFAULT '',
            payload LONGTEXT NULL,
            last_synced_at DATETIME NOT NULL,
            PRIMARY KEY(snapshot_key),
            KEY seller_user_id(seller_user_id),
            KEY source_table(source_table)
        ) $c;");
    }


    public function ensure_index_schema() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;

        $required = [
            $wpdb->prefix . 'eldokan_wcfm_vendor_index',
            $wpdb->prefix . 'eldokan_wcfm_product_index',
            $wpdb->prefix . 'eldokan_wcfm_order_index',
            $wpdb->prefix . 'eldokan_wcfm_raw_snapshot',
            $wpdb->prefix . 'eldokan_admin_access_roles',
            $wpdb->prefix . 'eldokan_admin_commission_rules',
            $wpdb->prefix . 'eldokan_admin_traffic',
            $wpdb->prefix . 'eldokan_admin_audit_log',
        ];

        foreach ($required as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) {
                self::activate();
                break;
            }
        }

        // v1.6.5: Hybrid commission needs a second amount column.
        // Add it automatically for existing installations.
        $commission_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $this->commission_table)
        );

        if ($commission_exists === $this->commission_table) {
            $fixed_column = $wpdb->get_var(
                "SHOW COLUMNS FROM {$this->commission_table} LIKE 'fixed_value'"
            );

            if (!$fixed_column) {
                $wpdb->query(
                    "ALTER TABLE {$this->commission_table}
                     ADD fixed_value DECIMAL(18,4) NOT NULL DEFAULT 0 AFTER value"
                );
            }
        }
    }

    private function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private function seller_roles() {
        return [
            'eldokan_owner',
            'eldokan_admin',
            'eldokan_operator',
            'eldokan_finance',
            'eldokan_viewer',
            // Legacy role retained for accounts created by older builds.
            'eldokan_editor',
            'wcfm_vendor',
            'vendor',
            'seller',
        ];
    }

    private function wcfm_role_choices() {
        $roles = wp_roles();
        $out = [];

        if (!$roles || !is_array($roles->roles)) {
            return $out;
        }

        foreach ($roles->roles as $slug => $data) {
            $name = (string)($data['name'] ?? $slug);
            $haystack = strtolower($slug . ' ' . $name);

            if (
                strpos($haystack, 'wcfm') !== false ||
                strpos($haystack, 'vendor') !== false ||
                strpos($haystack, 'seller') !== false ||
                strpos($haystack, 'store manager') !== false ||
                strpos($haystack, 'store_manager') !== false ||
                strpos($haystack, 'store staff') !== false ||
                strpos($haystack, 'store_staff') !== false
            ) {
                $out[$slug] = $name;
            }
        }

        natcasesort($out);
        return $out;
    }

    private function user_wcfm_roles($user) {
        if (!$user instanceof WP_User) {
            return [];
        }

        $choices = $this->wcfm_role_choices();
        $out = [];

        foreach ((array)$user->roles as $role_slug) {
            if (isset($choices[$role_slug])) {
                $out[$role_slug] = $choices[$role_slug];
            }
        }

        return $out;
    }

    private function replace_user_wcfm_role($user_id, $new_role) {
        $user = get_userdata(absint($user_id));
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found.');
        }

        $choices = $this->wcfm_role_choices();

        if ($new_role !== '' && !isset($choices[$new_role])) {
            return new WP_Error('invalid_wcfm_role', 'Selected WCFM role is not available.');
        }

        // Keep ElDokan dashboard roles intact. Only replace WCFM/vendor/seller roles.
        foreach (array_keys($this->user_wcfm_roles($user)) as $existing_role) {
            $user->remove_role($existing_role);
        }

        if ($new_role !== '') {
            $user->add_role($new_role);
        }

        update_user_meta($user->ID, '_eldokan_wcfm_role_synced_at', current_time('mysql'));
        return true;
    }

    private function owner_users($search='', $limit=100, $offset=0) {
        $args = [
            'role__in' => ['eldokan_owner','wcfm_vendor','vendor','seller'],
            'number' => min(200, max(10, absint($limit))),
            'offset' => max(0, absint($offset)),
            'orderby' => 'registered',
            'order' => 'DESC',
        ];
        if ($search !== '') $args['search'] = '*' . $search . '*';
        return get_users($args);
    }

    private function seller_team_users($search='', $limit=200, $offset=0) {
        $args = [
            'role__in' => $this->seller_roles(),
            'number' => min(300, max(10, absint($limit))),
            'offset' => max(0, absint($offset)),
            'orderby' => 'registered',
            'order' => 'DESC',
        ];
        if ($search !== '') $args['search'] = '*' . $search . '*';
        return get_users($args);
    }

    private function owner_id($user_id) {
        $user_id = absint($user_id);

        // Current Dashboard API canonical parent key.
        $parent = absint(get_user_meta($user_id, '_eldokan_parent_owner_id', true));

        // Compatibility with previous ElDokan Admin / Dashboard builds.
        if (!$parent) $parent = absint(get_user_meta($user_id, '_eldokan_owner_user_id', true));
        if (!$parent) $parent = absint(get_user_meta($user_id, 'eldokan_owner_user_id', true));
        if (!$parent) $parent = absint(get_user_meta($user_id, '_eldokan_owner_group', true));

        return $parent ?: $user_id;
    }

    private function company_accounts($owner_id) {
        $owner_id = absint($owner_id);
        if (!$owner_id) return [];

        $users = $this->seller_team_users('', 300, 0);
        $out = [];

        foreach ($users as $user) {
            if ($this->owner_id($user->ID) === $owner_id) {
                $out[$user->ID] = $user;
            }
        }

        $owner = get_userdata($owner_id);
        if ($owner) $out[$owner_id] = $owner;

        uasort($out, function($a, $b) use ($owner_id) {
            if ($a->ID === $owner_id) return -1;
            if ($b->ID === $owner_id) return 1;
            return strcasecmp($a->display_name ?: $a->user_login, $b->display_name ?: $b->user_login);
        });

        return array_values($out);
    }

    private function automatic_cancel_rating($seller_id, $period='30d') {
        $seller_id = absint($seller_id);
        $metrics = $this->seller_order_metrics($seller_id, ...$this->period($period));
        $orders = max(0, absint($metrics['orders'] ?? 0));
        $cancelled = max(0, absint($metrics['cancelled'] ?? 0));

        $cfg = get_option('eldokan_dashboard_control_global', $this->dashboard_defaults());
        $warning_limit = max(1, absint($cfg['cancel_warning_limit'] ?? 3));

        if ($orders === 0) {
            return [
                'rating' => null,
                'orders' => 0,
                'cancelled' => $cancelled,
                'warning_limit' => $warning_limit,
                'label' => 'No orders',
            ];
        }

        if ($cancelled === 0) {
            $rating = 5;
        } else {
            $ratio = $cancelled / $warning_limit;
            if ($ratio <= 0.34) $rating = 4;
            elseif ($ratio <= 0.67) $rating = 3;
            elseif ($ratio < 1) $rating = 2;
            else $rating = 1;
        }

        return [
            'rating' => $rating,
            'orders' => $orders,
            'cancelled' => $cancelled,
            'warning_limit' => $warning_limit,
            'label' => $cancelled >= $warning_limit ? 'Needs Attention' : 'Within Limit',
        ];
    }

    private function company_name($user_id) {
        $owner = $this->owner_id($user_id);
        $company = trim((string)get_user_meta($owner, 'billing_company', true));
        if ($company === '') {
            $settings = get_user_meta($owner, 'wcfmmp_profile_settings', true);
            if (is_array($settings)) {
                $company = trim((string)($settings['store_name'] ?? $settings['store-name'] ?? ''));
            }
        }
        if ($company === '') {
            $u = get_userdata($owner);
            $company = $u ? ($u->display_name ?: $u->user_login) : ('Seller #' . $owner);
        }
        return $company;
    }

    private function product_owner_id($product_id) {
        $id = absint(get_post_meta($product_id, '_eldokan_seller_user_id', true));
        if (!$id) $id = absint(get_post_field('post_author', $product_id));
        return $this->owner_id($id);
    }

    private function seller_product_ids($owner_id, $limit=200) {
        $limit = min(500, max(20, absint($limit)));
        $q = new WP_Query([
            'post_type' => 'product',
            'post_status' => ['publish','pending','draft','private'],
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'author' => absint($owner_id),
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $ids = $q->posts ?: [];

        $meta = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish','pending','draft','private'],
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'meta_key' => '_eldokan_seller_user_id',
            'meta_value' => absint($owner_id),
        ]);
        return array_values(array_unique(array_map('absint', array_merge($ids, $meta))));
    }

    private function all_orders($limit=300) {
        if (!function_exists('wc_get_orders')) return [];
        static $cache = [];
        $limit = min(500, max(50, absint($limit)));
        if (isset($cache[$limit])) return $cache[$limit];
        foreach ($cache as $cached_limit=>$orders) {
            if ($cached_limit >= $limit) return $cache[$limit] = array_slice($orders, 0, $limit);
        }

        $cache[$limit] = wc_get_orders([
            'type' => 'shop_order',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        return $cache[$limit];
    }

    private function item_seller_id($item) {
        $sid = absint($item->get_meta('_eldokan_seller_user_id', true));
        if (!$sid) {
            $pid = $item->get_product_id();
            if ($pid) $sid = $this->product_owner_id($pid);
        }
        return $this->owner_id($sid);
    }

    /**
     * Read historical ownership for order-list display and filtering only.
     * Keep financial, checkout and index callers on item_seller_id().
     */
    private function order_item_display_seller_id($item) {
        // The canonical line-item snapshot always wins over legacy/current data.
        if (absint($item->get_meta('_eldokan_seller_user_id', true))) {
            return $this->item_seller_id($item);
        }

        // Existing Admin migration/repair writers store a WP user ID here.
        // Never interpret names, arrays or other marketplace ID namespaces.
        $legacy = $item->get_meta('_eldokan_seller_owner', true);
        if ((is_int($legacy) || is_string($legacy)) &&
            preg_match('/^[1-9][0-9]*$/D', (string)$legacy)) {
            $legacy_id = filter_var($legacy, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($legacy_id !== false) return $this->owner_id($legacy_id);
        }

        // Preserve the existing product fallback when no stable snapshot exists.
        return $this->item_seller_id($item);
    }

    private function seller_order_metrics($seller_id, $from=null, $to=null) {
        $seller_id = absint($seller_id);

        $cache_key = 'eda_m_' . md5(
            implode('|', [
                $seller_id,
                (string)$from,
                (string)$to
            ])
        );

        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $m = [
            'orders'=>0,
            'units'=>0,
            'gross'=>0.0,
            'commission'=>0.0,
            'cancelled'=>0,
            'refunded'=>0
        ];

        $seen = [];

        // Lightweight mode: inspect only the most recent 300 orders.
        foreach ($this->all_orders(300) as $order) {
            $created = $order->get_date_created();
            if (!$created) continue;

            $ts = $created->getTimestamp();
            if ($from && $ts < $from) continue;
            if ($to && $ts > $to) continue;

            $matched = false;

            foreach ($order->get_items('line_item') as $item) {
                if ($this->item_seller_id($item) !== $seller_id) continue;

                $matched = true;
                $m['units'] += (float)$item->get_quantity();
                $m['gross'] += (float)$item->get_total();
                $m['commission'] += $this->commission_amount(
                    $seller_id,
                    $item->get_product_id(),
                    (float)$item->get_total()
                );
            }

            if ($matched) {
                $oid = $order->get_id();

                if (!isset($seen[$oid])) {
                    $seen[$oid] = 1;
                    $m['orders']++;

                    if (in_array(
                        $order->get_status(),
                        ['cancelled','failed'],
                        true
                    )) {
                        $m['cancelled']++;
                    }

                    if ($order->get_status() === 'refunded') {
                        $m['refunded']++;
                    }
                }
            }
        }

        set_transient($cache_key, $m, 120);
        return $m;
    }

    private function category_ids_for_product($product_id) {
        $product_id = absint($product_id);
        if (!array_key_exists($product_id, $this->product_category_cache)) {
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields'=>'ids']);
            $this->product_category_cache[$product_id] = is_array($terms) ? array_map('absint', $terms) : [];
        }
        return $this->product_category_cache[$product_id];
    }

    private function effective_rule($seller_id, $product_id) {
        $key = absint($seller_id).':'.absint($product_id);
        if (!array_key_exists($key, $this->commission_rule_cache)) {
            $this->commission_rule_cache[$key] = $this->compute_effective_rule($seller_id, $product_id);
        }
        return $this->commission_rule_cache[$key];
    }

    private function compute_effective_rule($seller_id, $product_id) {
        global $wpdb;
        $seller_id = absint($seller_id);
        $product_id = absint($product_id);
        $cats = $this->category_ids_for_product($product_id);
        $cat_sql = $cats ? implode(',', array_map('absint', $cats)) : '0';

        // Precedence:
        // seller product > seller category > seller all > global product >
        // category fee stored directly on product_cat term meta > legacy global category > global all.
        $seller_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->commission_table}
             WHERE enabled=1
               AND scope='seller'
               AND seller_user_id=%d
               AND (
                 (target_type='product' AND target_id=%d)
                 OR (target_type='category' AND target_id IN ({$cat_sql}))
                 OR target_type='all'
               )
             ORDER BY CASE target_type WHEN 'product' THEN 0 WHEN 'category' THEN 1 ELSE 2 END, id DESC
             LIMIT 1",
            $seller_id, $product_id
        ));
        if ($seller_rule) return $seller_rule;

        $product_rule = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->commission_table}
             WHERE enabled=1 AND scope='global' AND target_type='product' AND target_id=%d
             ORDER BY id DESC LIMIT 1",
            $product_id
        ));
        if ($product_rule) return $product_rule;

        $category_fee = $this->category_fee_rule_for_product($product_id);
        if ($category_fee) return $category_fee;

        return $wpdb->get_row(
            "SELECT * FROM {$this->commission_table}
             WHERE enabled=1
               AND scope='global'
               AND (
                 (target_type='category' AND target_id IN ({$cat_sql}))
                 OR target_type='all'
               )
             ORDER BY CASE target_type WHEN 'category' THEN 0 ELSE 1 END, id DESC
             LIMIT 1"
        );
    }

    private function category_fee_rule_from_meta($term_id) {
        $term_id = absint($term_id);
        if (!$term_id || !term_exists($term_id, 'product_cat')) return null;

        $enabled = get_term_meta($term_id, '_eldokan_fee_enabled', true);
        if ((string)$enabled !== '1') return null;

        $type = sanitize_key((string)get_term_meta($term_id, '_eldokan_fee_type', true));
        if (!in_array($type, ['percentage','fixed','hybrid'], true)) $type = 'percentage';

        $value = max(0, (float)get_term_meta($term_id, '_eldokan_fee_value', true));
        $fixed = max(0, (float)get_term_meta($term_id, '_eldokan_fee_fixed', true));
        if ($type === 'percentage' || $type === 'hybrid') $value = min(100, $value);
        if ($type !== 'hybrid') $fixed = 0;

        $rule = new stdClass();
        $rule->id = 0;
        $rule->scope = 'global';
        $rule->seller_user_id = 0;
        $rule->target_type = 'category';
        $rule->target_id = $term_id;
        $rule->calculation_type = $type;
        $rule->value = $value;
        $rule->fixed_value = $fixed;
        $rule->enabled = 1;
        $rule->source = 'category_term_meta';
        return $rule;
    }

    private function category_fee_rule_for_product($product_id) {
        $cats = array_values(array_filter(array_map('absint', (array)$this->category_ids_for_product($product_id))));
        if (!$cats) return null;

        // Prefer the most specific (deepest) assigned category.
        usort($cats, function($a, $b) {
            $da = count(get_ancestors($a, 'product_cat', 'taxonomy'));
            $db = count(get_ancestors($b, 'product_cat', 'taxonomy'));
            if ($da === $db) return $b <=> $a;
            return $db <=> $da;
        });

        foreach ($cats as $cat_id) {
            $rule = $this->category_fee_rule_from_meta($cat_id);
            if ($rule) return $rule;
        }
        return null;
    }

    private function commission_amount($seller_id, $product_id, $line_total) {
        $r = $this->effective_rule($seller_id, $product_id);
        if (!$r) return 0.0;

        $line_total = max(0, (float)$line_total);
        $value = max(0, (float)$r->value);
        $fixed_value = isset($r->fixed_value) ? max(0, (float)$r->fixed_value) : 0.0;

        if ($r->calculation_type === 'fixed') {
            return min($line_total, $value);
        }

        $percentage_amount = $line_total * min(100, $value) / 100;

        if ($r->calculation_type === 'hybrid') {
            return min($line_total, $percentage_amount + $fixed_value);
        }

        return max(0, $percentage_amount);
    }

    private function commission_rule_value_label($rule) {
        if (!$rule) return '—';

        $type = (string)($rule->calculation_type ?? 'percentage');
        $value = max(0, (float)($rule->value ?? 0));
        $fixed = max(0, (float)($rule->fixed_value ?? 0));

        if ($type === 'fixed') {
            return number_format($value, 2).' EGP';
        }

        if ($type === 'hybrid') {
            return number_format($value, 2).'% + '.number_format($fixed, 2).' EGP';
        }

        return number_format($value, 2).'%';
    }

    private function commission_type_label($type) {
        if ($type === 'fixed') return 'Fixed EGP';
        if ($type === 'hybrid') return 'Percentage % + Fixed EGP';
        return 'Percentage %';
    }

    private function commission_rule_source_label($rule) {
        if (!$rule) return 'No fee rule';

        $scope = sanitize_key((string)($rule->scope ?? 'global'));
        $target_type = sanitize_key((string)($rule->target_type ?? 'all'));
        $target_id = absint($rule->target_id ?? 0);
        $source = sanitize_key((string)($rule->source ?? ''));

        if ($scope === 'seller') {
            if ($target_type === 'product') return 'Seller product override';
            if ($target_type === 'category') {
                $term = $target_id ? get_term($target_id, 'product_cat') : null;
                return ($term && !is_wp_error($term))
                    ? 'Seller category — '.$term->name
                    : 'Seller category rule';
            }
            return 'Seller default';
        }

        if ($target_type === 'product') return 'Product override';

        if ($target_type === 'category' || $source === 'category_term_meta') {
            $term = $target_id ? get_term($target_id, 'product_cat') : null;
            return ($term && !is_wp_error($term))
                ? 'Category — '.$term->name
                : 'Category fee';
        }

        return 'Global default';
    }

    public function menu() {
        add_menu_page('ElDokan Admin Staff','ElDokan Admin Staff','eldokan_backoffice_access','eldokan-admin',[$this,'dashboard'],'dashicons-store',54);
        add_submenu_page('eldokan-admin',__('Dashboard', 'eldokan-admin'),__('Dashboard', 'eldokan-admin'),'eldokan_backoffice_view_dashboard','eldokan-admin',[$this,'dashboard']);
        add_submenu_page('eldokan-admin',__('Sellers', 'eldokan-admin'),__('Sellers', 'eldokan-admin'),'eldokan_backoffice_view_sellers','eldokan-admin-sellers',[$this,'sellers']);
        $pending_registrations = $this->pending_registration_count();
        $registration_label = __('Registrations', 'eldokan-admin');
        if ($pending_registrations > 0) {
            $registration_label .= ' <span class="awaiting-mod count-' . absint($pending_registrations) . '"><span class="pending-count">' . absint($pending_registrations) . '</span></span>';
        }
        add_submenu_page('eldokan-admin',__('Registrations', 'eldokan-admin'),$registration_label,'eldokan_backoffice_view_registrations','eldokan-admin-registrations',[$this,'registrations']);
        add_submenu_page(null,__('Customer Accounts', 'eldokan-admin'),__('Customer Accounts', 'eldokan-admin'),'list_users','eldokan-admin-customers',[$this,'customers']);
        add_submenu_page('eldokan-admin',__('Products', 'eldokan-admin'),__('Products', 'eldokan-admin'),'eldokan_backoffice_view_products','eldokan-admin-products',[$this,'products']);
        add_submenu_page('eldokan-admin',__('Orders', 'eldokan-admin'),__('Orders', 'eldokan-admin'),'eldokan_backoffice_view_orders','eldokan-admin-orders',[$this,'orders']);
        add_submenu_page('eldokan-admin',__('Categories', 'eldokan-admin'),__('Categories', 'eldokan-admin'),'eldokan_backoffice_view_categories','eldokan-admin-categories',[$this,'categories']);
        add_submenu_page(null,__('Dashboard Control', 'eldokan-admin'),__('Dashboard Control', 'eldokan-admin'),'manage_options','eldokan-admin-dashboard-control',[$this,'dashboard_control']);
        add_submenu_page(null,__('Seller Role Permissions', 'eldokan-admin'),__('Seller Role Permissions', 'eldokan-admin'),'manage_options','eldokan-admin-access-roles',[$this,'access_roles']);
        add_submenu_page('eldokan-admin',__('WCFM Data Index', 'eldokan-admin'),__('WCFM Data Index', 'eldokan-admin'),'manage_options','eldokan-admin-wcfm-index',[$this,'wcfm_index_page']);
        add_submenu_page(null,__('Commissions', 'eldokan-admin'),__('Commissions', 'eldokan-admin'),'eldokan_backoffice_view_commissions','eldokan-admin-commissions',[$this,'commissions']);
        add_submenu_page(null,__('Statements', 'eldokan-admin'),__('Statements', 'eldokan-admin'),'eldokan_backoffice_view_reports','eldokan-admin-statements',[$this,'statements']);
        add_submenu_page(null,__('Seller Performance', 'eldokan-admin'),__('Seller Performance', 'eldokan-admin'),'eldokan_backoffice_view_reports','eldokan-admin-performance',[$this,'performance']);
        add_submenu_page('eldokan-admin',__('Reports', 'eldokan-admin'),__('Reports', 'eldokan-admin'),'eldokan_backoffice_view_reports','eldokan-admin-reports',[$this,'reports']);
        add_submenu_page('eldokan-admin',__('Activity', 'eldokan-admin'),__('Activity', 'eldokan-admin'),'eldokan_backoffice_view_activity','eldokan-admin-activity',[$this,'activity']);
        add_submenu_page('eldokan-admin',__('Email Settings', 'eldokan-admin'),__('Email Settings', 'eldokan-admin'),'eldokan_backoffice_manage_email','eldokan-admin-email-settings',[$this,'email_settings']);
        add_submenu_page('eldokan-admin',__('Settings', 'eldokan-admin'),__('Settings', 'eldokan-admin'),'eldokan_backoffice_manage_settings','eldokan-admin-settings',[$this,'settings']);
    }

    public function assets($hook) {
        if (!ElDokan_Admin_UI::relevant()) return;
        wp_enqueue_style('eldokan-admin', ELDOKAN_ADMIN_URL.'assets/admin.css', [], ELDOKAN_ADMIN_VERSION);
        if ($hook === 'toplevel_page_eldokan-admin') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
        }
    }

    public function track_visit() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) return;
        global $wpdb;
        $day = current_time('Y-m-d');
        $cookie = 'eldokan_admin_uv_' . str_replace('-', '', $day);
        $unique = empty($_COOKIE[$cookie]) ? 1 : 0;
        if ($unique) {
            setcookie($cookie, '1', time()+DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
        }
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->traffic_table}(day,page_views,unique_visitors,logged_in_views)
             VALUES(%s,1,%d,%d)
             ON DUPLICATE KEY UPDATE page_views=page_views+1,
             unique_visitors=unique_visitors+VALUES(unique_visitors),
             logged_in_views=logged_in_views+VALUES(logged_in_views)",
            $day,$unique,is_user_logged_in()?1:0
        ));
    }

    private function period($key) {
        $now = current_time('timestamp');
        if ($key === 'today') return [strtotime('today',$now), strtotime('tomorrow',$now)-1];
        if ($key === '7d') return [strtotime('-6 days 00:00:00',$now), $now];
        if ($key === '30d') return [strtotime('-29 days 00:00:00',$now), $now];
        return [strtotime(date('Y-m-01 00:00:00',$now)), $now];
    }

    private function overall_metrics($period='30d') {
        $cache_key = 'eda_overall_' . sanitize_key($period);

        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        [$from,$to] = $this->period($period);

        $seller_query = new WP_User_Query([
            'role__in' => ['eldokan_owner','wcfm_vendor','vendor','seller'],
            'number' => 1,
            'count_total' => true,
            'fields' => 'ID',
        ]);

        $seller_count = (int)$seller_query->get_total();

        $orders = 0;
        $units = 0;
        $gross = 0.0;
        $commission = 0.0;
        $cancelled = 0;

        // One pass only over a bounded set of recent orders.
        $seen_orders = [];

        foreach ($this->all_orders(300) as $order) {
            $created = $order->get_date_created();
            if (!$created) continue;

            $ts = $created->getTimestamp();
            if ($ts < $from || $ts > $to) continue;

            $matched_order = false;

            foreach ($order->get_items('line_item') as $item) {
                $seller_id = $this->item_seller_id($item);
                if (!$seller_id) continue;

                $matched_order = true;
                $units += (float)$item->get_quantity();
                $gross += (float)$item->get_total();
                $commission += $this->commission_amount(
                    $seller_id,
                    $item->get_product_id(),
                    (float)$item->get_total()
                );
            }

            if ($matched_order) {
                $oid = $order->get_id();

                if (!isset($seen_orders[$oid])) {
                    $seen_orders[$oid] = 1;
                    $orders++;

                    if (in_array(
                        $order->get_status(),
                        ['cancelled','failed'],
                        true
                    )) {
                        $cancelled++;
                    }
                }
            }
        }

        global $wpdb;

        $views = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(page_views),0)
                 FROM {$this->traffic_table}
                 WHERE day BETWEEN %s AND %s",
                date('Y-m-d',$from),
                date('Y-m-d',$to)
            )
        );

        $unique = (int)$wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(unique_visitors),0)
                 FROM {$this->traffic_table}
                 WHERE day BETWEEN %s AND %s",
                date('Y-m-d',$from),
                date('Y-m-d',$to)
            )
        );

        $counts = wp_count_posts('product');

        $result = [
            'orders'=>$orders,
            'units'=>$units,
            'gross'=>$gross,
            'commission'=>$commission,
            'cancelled'=>$cancelled,
            'views'=>$views,
            'unique'=>$unique,
            'products'=>(int)($counts->publish ?? 0),
            'pending'=>(int)($counts->pending ?? 0),
            'sellers'=>$seller_count,
        ];

        set_transient($cache_key, $result, 120);
        return $result;
    }

    private function header($title,$sub='') {
        $user = wp_get_current_user();
        echo '<div class="wrap eda">';
        echo '<section class="eda-hero">';
        echo '<div class="eda-hero-copy">';
        echo '<span class="eda-eyebrow">' . esc_html__('ElDokan Operations', 'eldokan-admin') . '</span>';
        echo '<h1>'.esc_html(translate($title, 'eldokan-admin')).'</h1>';
        if ($sub) echo '<p>'.esc_html(translate($sub, 'eldokan-admin')).'</p>';
        echo '</div>';
        echo '<div class="eda-hero-meta">';
        echo '<span class="eda-version-pill" dir="ltr">' . esc_html__('v', 'eldokan-admin') . ''.esc_html(ELDOKAN_ADMIN_VERSION).'</span>';
        if ($user && $user->exists()) {
            echo '<span class="eda-user-pill"><span class="dashicons dashicons-admin-users"></span>'.esc_html($user->display_name).'</span>';
        }
        echo '</div>';
        echo '</section>';
        ElDokan_Admin_UI::reporting_note();
    }
    private function footer(){ echo '</div>'; }
    private function badge($s) {
        $class = in_array($s,['active','publish','completed','processing','Good'],true)?'green':(in_array($s,['blocked','cancelled','failed','Needs Attention'],true)?'red':'');
        return '<span class="eda-badge '.$class.'">'.esc_html(translate($s, 'eldokan-admin')).'</span>';
    }

    public function dashboard() {
        if (!current_user_can('eldokan_backoffice_view_dashboard')) wp_die('Not allowed');
        $this->header('ElDokan Marketplace','Marketplace operations overview.');

        $m=$this->overall_metrics('30d');
        global $wpdb;

        $customer_q=new WP_User_Query([
            'role__in'=>['customer','subscriber'],
            'number'=>1,
            'count_total'=>true,
            'fields'=>'ID'
        ]);
        $customers=(int)$customer_q->get_total();

        $seller_q=new WP_User_Query([
            'role__in'=>['eldokan_owner','wcfm_vendor','vendor','seller'],
            'number'=>1,
            'count_total'=>true,
            'fields'=>'ID'
        ]);
        $sellers=(int)$seller_q->get_total();

        $pending = $this->product_record_count('pending');
        $products = $this->product_record_count();

        $recent=$this->all_orders(150);
        $status_counts=['processing'=>0,'completed'=>0,'pending'=>0,'cancelled'=>0,'refunded'=>0,'other'=>0];
        foreach($recent as $o){
            $st=$o->get_status();
            if(isset($status_counts[$st]))$status_counts[$st]++;
            else $status_counts['other']++;
        }

        echo '<div class="eda-grid">';
        $home_cards=[
            ['Pending Registrations',$this->pending_registration_count(),'eldokan-admin-registrations','eldokan_backoffice_view_registrations','dashicons-id-alt'],
            ['Seller-role accounts',$sellers,'eldokan-admin-sellers','eldokan_backoffice_view_sellers','dashicons-groups'],
            ['Registered customer/subscriber accounts',$customers,'eldokan-admin-customers','list_users','dashicons-businessperson'],
            ['Product records (live, pending, draft, private)',$products,'eldokan-admin-products','eldokan_backoffice_view_products','dashicons-products'],
            ['Seller-linked orders · recent sample (30d)',$m['orders'],'eldokan-admin-orders','eldokan_backoffice_view_orders','dashicons-clipboard'],
            ['Order line value · recent sample',number_format($m['gross'],2).' EGP','eldokan-admin-statements','eldokan_backoffice_view_reports','dashicons-chart-line'],
            ['Estimated commission · recent sample',number_format($m['commission'],2).' EGP','eldokan-admin-commissions','eldokan_backoffice_view_commissions','dashicons-money-alt'],
            ['Units · recent sample',$m['units'],'eldokan-admin-products','eldokan_backoffice_view_products','dashicons-cart'],
            ['Cancelled/failed · recent sample',$m['cancelled'],'eldokan-admin-orders','eldokan_backoffice_view_orders','dashicons-dismiss']
        ];
        foreach($home_cards as $card){
            if (!current_user_can($card[3])) continue;
            $has_link = $card[2] !== '';
            if ($has_link) {
                $url = admin_url('admin.php?page=' . $card[2]);
                if ($card[2] === 'eldokan-admin-products') $url = add_query_arg('lang', $this->product_view_state($_GET)['lang'], $url);
                echo '<a href="' . esc_url($url) . '" class="eda-card eda-home-link eda-stat-card">';
            } else {
                echo '<div class="eda-card eda-stat-card" aria-label="' . esc_attr(translate($card[0], 'eldokan-admin')) . '">';
            }
            echo '<span class="eda-stat-icon dashicons ' . esc_attr($card[4]) . '"></span>';
            echo '<span class="eda-stat-copy"><small>' . esc_html(translate($card[0], 'eldokan-admin')) . '</small><strong>' . esc_html($card[1]) . '</strong>';
            if ($has_link) echo '<span class="eda-stat-link">' . esc_html__('Open', 'eldokan-admin') . ' <span class="dashicons dashicons-arrow-right-alt2"></span></span>';
            echo '</span>';
            echo $has_link ? '</a>' : '</div>';
        }
        echo '</div>';

        echo '<section class="eda-card eda-pending-panel"><h2>' . esc_html__('Pending work', 'eldokan-admin') . '</h2><div class="eda-pending-links">';
        if (current_user_can('eldokan_backoffice_view_registrations')) echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=eldokan-admin-registrations&status=pending')) . '">' . esc_html__('Seller registration queue', 'eldokan-admin') . '</a>';
        if (current_user_can('eldokan_backoffice_view_products')) echo '<a class="button" href="' . esc_url(add_query_arg('lang', $this->product_view_state($_GET)['lang'], admin_url('admin.php?page=eldokan-admin-products&status=pending'))) . '">' . esc_html(sprintf(__('Pending product records: %d', 'eldokan-admin'), $pending)) . '</a>';
        echo '</div></section>';
        if (current_user_can('eldokan_backoffice_view_activity')) {
            echo '<section class="eda-card"><div class="eda-section-head"><h2>' . esc_html__('Recent activity', 'eldokan-admin') . '</h2><a href="' . esc_url(admin_url('admin.php?page=eldokan-admin-activity')) . '">' . esc_html__('View audit log', 'eldokan-admin') . '</a></div>';
            $events = $this->table_exists($this->audit_table) ? $wpdb->get_results("SELECT id, created_at, action, description FROM {$this->audit_table} ORDER BY id DESC LIMIT 5") : [];
            if (!$events) echo '<p class="description">' . esc_html__('No recent audit events are available.', 'eldokan-admin') . '</p>';
            else {
                echo '<ul class="eda-recent-activity">';
                foreach ($events as $event) echo '<li><time>' . esc_html($event->created_at) . '</time><strong>' . esc_html($event->action) . '</strong><span>' . esc_html($event->description) . '</span></li>';
                echo '</ul>';
            }
            echo '</section>';
        }

        echo '<div class="eda-two">';
        echo '<div class="eda-card eda-chart-card"><div class="eda-section-head"><div><span class="eda-card-kicker">' . esc_html__('Marketplace', 'eldokan-admin') . '</span><h2>' . esc_html__('Distribution', 'eldokan-admin') . '</h2></div><span class="eda-chart-dot"></span></div><div class="eda-chart-wrap"><canvas id="edaMarketPie"></canvas></div></div>';
        echo '<div class="eda-card eda-chart-card"><div class="eda-section-head"><div><span class="eda-card-kicker">' . esc_html__('Last 150 orders', 'eldokan-admin') . '</span><h2>' . esc_html__('Order Status', 'eldokan-admin') . '</h2></div>'; if (current_user_can('eldokan_backoffice_view_orders')) echo '<a class="eda-text-link" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-orders')).'">' . esc_html__('Open orders', 'eldokan-admin') . ' <span class="dashicons dashicons-arrow-right-alt2"></span></a>'; echo '</div><div class="eda-chart-wrap"><canvas id="edaOrderPie"></canvas></div></div>';
        echo '</div>';

        echo '<div class="eda-card eda-operations-card"><div class="eda-section-head"><div><span class="eda-card-kicker">' . esc_html__('Workspace', 'eldokan-admin') . '</span><h2>' . esc_html__('Operations', 'eldokan-admin') . '</h2><p>' . esc_html__('Jump to the areas available to your role.', 'eldokan-admin') . '</p></div></div><div class="eda-grid eda-operation-grid">';
        foreach([
            ['Sellers','eldokan-admin-sellers',$sellers,'eldokan_backoffice_view_sellers','dashicons-groups'],
            ['Products','eldokan-admin-products',$products,'eldokan_backoffice_view_products','dashicons-products'],
            ['Orders','eldokan-admin-orders',$m['orders'],'eldokan_backoffice_view_orders','dashicons-clipboard'],
            ['Commissions','eldokan-admin-commissions',number_format($m['commission'],2).' EGP','eldokan_backoffice_view_commissions','dashicons-money-alt'],
            ['Statements','eldokan-admin-statements','Open','eldokan_backoffice_view_reports','dashicons-media-spreadsheet'],
            ['Seller Access Roles','eldokan-admin-access-roles','Manage','manage_options','dashicons-admin-network'],
            ['WCFM Full Data','eldokan-admin-wcfm-index','Sync','manage_options','dashicons-database']
        ] as $x){
            if (!current_user_can($x[3])) continue;
            echo '<a class="eda-mini eda-operation-link" href="'.esc_url(admin_url('admin.php?page='.$x[1])).'"><span class="eda-operation-icon dashicons '.esc_attr($x[4]).'"></span><span><small>'.esc_html(translate($x[0], 'eldokan-admin')).'</small><strong>'.esc_html($x[2]).'</strong></span><span class="dashicons dashicons-arrow-right-alt2 eda-operation-arrow"></span></a>';
        }
        echo '</div></div>';

        echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
          if(!window.Chart)return;
          new Chart(document.getElementById("edaMarketPie"),{
            type:"doughnut",
            data:{labels:["Products","Orders","Customers","Sellers"],datasets:[{backgroundColor:["#2563eb","#7c3aed","#06b6d4","#14b8a6","#f59e0b","#ef4444"],borderWidth:0,hoverOffset:6,data:['.
              absint($products).','.absint($m['orders']).','.absint($customers).','.absint($sellers).
            ']}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:"72%",animation:{duration:450},plugins:{legend:{position:"bottom",labels:{usePointStyle:true,boxWidth:8,padding:18}}}}
          });
          new Chart(document.getElementById("edaOrderPie"),{
            type:"doughnut",
            data:{labels:["Processing","Completed","Pending","Cancelled","Refunded","Other"],datasets:[{backgroundColor:["#2563eb","#7c3aed","#06b6d4","#14b8a6","#f59e0b","#ef4444"],borderWidth:0,hoverOffset:6,data:['.
              absint($status_counts['processing']).','.absint($status_counts['completed']).','.absint($status_counts['pending']).','.absint($status_counts['cancelled']).','.absint($status_counts['refunded']).','.absint($status_counts['other']).
            ']}]},
            options:{responsive:true,maintainAspectRatio:false,cutout:"72%",animation:{duration:450},plugins:{legend:{position:"bottom",labels:{usePointStyle:true,boxWidth:8,padding:18}}}}
          });
        });
        </script>';

        $this->footer();
    }


    private function is_company_registration_user($user) {
        if (!$user instanceof WP_User || empty($user->ID)) return false;

        $uid = (int)$user->ID;
        $source = (string)get_user_meta($uid, '_eldokan_registration_source', true);
        $account_source = (string)get_user_meta($uid, '_eldokan_account_source', true);
        $parent = absint(
            get_user_meta($uid, '_eldokan_parent_owner_id', true)
            ?: get_user_meta($uid, '_eldokan_owner_user_id', true)
            ?: get_user_meta($uid, 'eldokan_owner_user_id', true)
            ?: get_user_meta($uid, '_eldokan_owner_group', true)
        );

        if ($source === 'company_registration') return true;

        // Team/sub-accounts created from Seller Dashboard are not registrations.
        if ($account_source === 'seller_dashboard' || $parent) return false;

        $status = (string)get_user_meta($uid, '_eldokan_account_status', true);
        if ($status === '') return false;

        return (
            absint(get_user_meta($uid, '_eldokan_verification_pdf_attachment_id', true)) > 0 ||
            trim((string)get_user_meta($uid, 'billing_company', true)) !== '' ||
            trim((string)get_user_meta($uid, '_eldokan_company_name', true)) !== '' ||
            trim((string)get_user_meta($uid, '_eldokan_tax_number', true)) !== '' ||
            in_array('eldokan_pending', (array)$user->roles, true)
        );
    }

    private function registration_users_all() {
        $users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC',
            'meta_query' => [[
                'key' => '_eldokan_account_status',
                'compare' => 'EXISTS',
            ]],
        ]);

        return array_values(array_filter($users, function($user) {
            return $this->is_company_registration_user($user);
        }));
    }

    private function pending_registration_count() {
        if ($this->pending_registration_cache !== null) return $this->pending_registration_cache;
        $users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC',
            'meta_key' => '_eldokan_account_status',
            'meta_value' => 'pending',
        ]);

        $count = 0;
        foreach ($users as $user) {
            if ($this->is_company_registration_user($user)) $count++;
        }
        return $this->pending_registration_cache = $count;
    }

    private function registration_matches($user, $search, $status, $date_from, $date_to) {
        if (!$this->is_company_registration_user($user)) return false;

        $current_status = (string)get_user_meta($user->ID, '_eldokan_account_status', true);
        if ($current_status === '') $current_status = 'pending';
        if ($status !== '' && $current_status !== $status) return false;

        $registered_ts = strtotime((string)$user->user_registered);
        if ($date_from !== '') {
            $from = strtotime($date_from . ' 00:00:00');
            if ($from && $registered_ts < $from) return false;
        }
        if ($date_to !== '') {
            $to = strtotime($date_to . ' 23:59:59');
            if ($to && $registered_ts > $to) return false;
        }

        if ($search === '') return true;

        $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $fields = [
            $user->display_name,
            $user->user_login,
            $user->user_email,
            get_user_meta($user->ID, 'billing_company', true),
            get_user_meta($user->ID, '_eldokan_company_name', true),
            get_user_meta($user->ID, 'billing_phone', true),
            get_user_meta($user->ID, '_eldokan_tax_number', true),
            get_user_meta($user->ID, 'billing_address_1', true),
            get_user_meta($user->ID, 'billing_city', true),
            get_user_meta($user->ID, 'billing_country', true),
        ];

        foreach ($fields as $field) {
            $value = trim((string)$field);
            if ($value === '') continue;
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
            if (strpos($haystack, $needle) !== false) return true;
        }

        return false;
    }

    public function registrations() {
        if (!current_user_can('eldokan_backoffice_view_registrations')) wp_die('Not allowed');

        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $status = sanitize_key($_GET['status'] ?? '');
        $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? ''));
        $date_to = sanitize_text_field(wp_unslash($_GET['date_to'] ?? ''));
        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 30;
        $view_id = absint($_GET['view_id'] ?? 0);

        if (!in_array($status, ['', 'pending', 'approved', 'rejected', 'disabled'], true)) {
            $status = '';
        }

        $all = array_values(array_filter(
            $this->registration_users_all(),
            function($user) use ($search, $status, $date_from, $date_to) {
                return $this->registration_matches($user, $search, $status, $date_from, $date_to);
            }
        ));

        $total = count($all);
        $pages = max(1, (int)ceil($total / $per_page));
        if ($page > $pages) $page = $pages;
        $users = array_slice($all, ($page - 1) * $per_page, $per_page);

        $this->header('Seller Registrations','Central seller/company approval queue. A seller cannot use the Seller Dashboard until ElDokan Admin approves the registration.');

        if (!empty($_GET['registration_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Registration status updated successfully. The Seller Dashboard will read the same status immediately.', 'eldokan-admin') . '</p></div>';
        }
        if (!empty($_GET['registration_deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Registration deleted successfully.', 'eldokan-admin') . '</p></div>';
        }

        echo '<div class="eda-card">';
        echo '<form method="get" style="display:grid;grid-template-columns:minmax(240px,2fr) minmax(150px,1fr) minmax(150px,1fr) minmax(150px,1fr) auto auto;gap:10px;align-items:end">';
        echo '<input type="hidden" name="page" value="eldokan-admin-registrations">';
        echo '<label><b>' . esc_html__('Search', 'eldokan-admin') . '</b><br><input type="search" name="s" value="'.esc_attr($search).'" placeholder="' . esc_attr__('Name, email, company, phone, tax number...', 'eldokan-admin') . '" style="width:100%"></label>';
        echo '<label><b>' . esc_html__('Status', 'eldokan-admin') . '</b><br><select name="status" style="width:100%">';
        foreach ([''=>'All Statuses','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','disabled'=>'Disabled'] as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html(translate($label, 'eldokan-admin')).'</option>';
        }
        echo '</select></label>';
        echo '<label><b>' . esc_html__('From', 'eldokan-admin') . '</b><br><input type="date" name="date_from" value="'.esc_attr($date_from).'" style="width:100%"></label>';
        echo '<label><b>' . esc_html__('To', 'eldokan-admin') . '</b><br><input type="date" name="date_to" value="'.esc_attr($date_to).'" style="width:100%"></label>';
        echo '<button class="button button-primary">' . esc_html__('Filter', 'eldokan-admin') . '</button>';
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-registrations')).'">' . esc_html__('Reset', 'eldokan-admin') . '</a>';
        echo '</form></div>';

        if ($view_id) {
            $u = get_userdata($view_id);
            if ($u && $this->is_company_registration_user($u)) {
                $company = get_user_meta($view_id, 'billing_company', true) ?: get_user_meta($view_id, '_eldokan_company_name', true);
                $phone = get_user_meta($view_id, 'billing_phone', true);
                $tax = get_user_meta($view_id, '_eldokan_tax_number', true);
                $address = trim(implode(', ', array_filter([
                    get_user_meta($view_id, 'billing_address_1', true),
                    get_user_meta($view_id, 'billing_address_2', true),
                    get_user_meta($view_id, 'billing_city', true),
                    get_user_meta($view_id, 'billing_state', true),
                    get_user_meta($view_id, 'billing_postcode', true),
                    get_user_meta($view_id, 'billing_country', true),
                ])));
                $current_status = get_user_meta($view_id, '_eldokan_account_status', true) ?: 'pending';
                $pdf_id = absint(get_user_meta($view_id, '_eldokan_verification_pdf_attachment_id', true));

                echo '<div class="eda-card"><div class="eda-section-head"><div><h2>' . esc_html__('Registration #', 'eldokan-admin') . ''.absint($view_id).' — '.esc_html($company ?: $u->display_name).'</h2><p>' . esc_html__('Full seller registration details from the same WordPress user/meta record used by Seller Dashboard.', 'eldokan-admin') . '</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-registrations')).'">' . esc_html__('Close Details', 'eldokan-admin') . '</a></div>';
                echo '<div class="eda-grid">';
                foreach ([
                    'Contact Name'=>$u->display_name,
                    'Email'=>$u->user_email,
                    'Company'=>$company ?: '—',
                    'Phone'=>$phone ?: '—',
                    'Tax Number'=>$tax ?: '—',
                    'Address'=>$address ?: '—',
                    'Registered'=>$u->user_registered,
                    'Status'=>ucfirst($current_status),
                ] as $label=>$value) {
                    echo '<div class="eda-mini"><small>'.esc_html(translate($label, 'eldokan-admin')).'</small><strong>'.esc_html($value).'</strong></div>';
                }
                echo '</div>';
                if ($pdf_id) {
                    $pdf_url = wp_nonce_url(
                        admin_url('admin-post.php?action=eldokan_admin_view_registration_pdf&user_id='.$view_id),
                        'eldokan_admin_view_registration_pdf_'.$view_id
                    );
                    echo '<p><a class="button" target="_blank" href="'.esc_url($pdf_url).'">' . esc_html__('View Verification PDF', 'eldokan-admin') . '</a></p>';
                }
                echo '</div>';
            }
        }

        echo '<div class="eda-card">';
        echo '<div class="eda-section-head"><div><h2>' . esc_html__('Registration Queue', 'eldokan-admin') . '</h2><p>'.number_format_i18n($total).' registration(s) found.</p></div><span class="eda-badge">'.absint($this->pending_registration_count()).' Pending</span></div>';
        echo '<div class="eda-table-scroll"><table class="eda-table"><tr><th>' . esc_html__('Company / Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Contact', 'eldokan-admin') . '</th><th>' . esc_html__('Phone', 'eldokan-admin') . '</th><th>' . esc_html__('Tax Number', 'eldokan-admin') . '</th><th>' . esc_html__('Registered', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Document', 'eldokan-admin') . '</th><th>' . esc_html__('Actions', 'eldokan-admin') . '</th></tr>';

        if (!$users) {
            echo '<tr><td colspan="8" style="text-align:center;padding:28px">' . esc_html__('No seller registrations match the selected filters.', 'eldokan-admin') . '</td></tr>';
        }

        foreach ($users as $u) {
            $uid = (int)$u->ID;
            $company = get_user_meta($uid, 'billing_company', true) ?: get_user_meta($uid, '_eldokan_company_name', true);
            $phone = get_user_meta($uid, 'billing_phone', true);
            $tax = get_user_meta($uid, '_eldokan_tax_number', true);
            $current_status = get_user_meta($uid, '_eldokan_account_status', true) ?: 'pending';
            $pdf_id = absint(get_user_meta($uid, '_eldokan_verification_pdf_attachment_id', true));

            $view_url = add_query_arg([
                'page'=>'eldokan-admin-registrations',
                'view_id'=>$uid,
                's'=>$search,
                'status'=>$status,
                'date_from'=>$date_from,
                'date_to'=>$date_to,
                'paged'=>$page,
            ], admin_url('admin.php'));

            $approve = wp_nonce_url(
                admin_url('admin-post.php?action=eldokan_admin_registration_status&user_id='.$uid.'&status=approved'),
                'eldokan_admin_registration_status_'.$uid
            );
            $reject = wp_nonce_url(
                admin_url('admin-post.php?action=eldokan_admin_registration_status&user_id='.$uid.'&status=rejected'),
                'eldokan_admin_registration_status_'.$uid
            );
            $delete = wp_nonce_url(
                admin_url('admin-post.php?action=eldokan_admin_delete_registration&user_id='.$uid),
                'eldokan_admin_delete_registration_'.$uid
            );

            echo '<tr>';
            echo '<td><strong>'.esc_html($company ?: $u->display_name).'</strong><br><small>#'.absint($uid).'</small></td>';
            echo '<td>'.esc_html($u->display_name).'<br><small>'.esc_html($u->user_email).'</small></td>';
            echo '<td>'.esc_html($phone ?: '—').'</td>';
            echo '<td>'.esc_html($tax ?: '—').'</td>';
            echo '<td>'.esc_html(mysql2date('Y-m-d H:i', $u->user_registered)).'</td>';
            echo '<td>'.$this->badge($current_status).'</td>';
            echo '<td>'.($pdf_id ? '<span class="eda-badge green">' . esc_html__('PDF', 'eldokan-admin') . '</span>' : '<span class="eda-badge">' . esc_html__('None', 'eldokan-admin') . '</span>').'</td>';
            echo '<td class="eda-row-actions">';
            echo '<a class="button" href="'.esc_url($view_url).'">' . esc_html__('Details', 'eldokan-admin') . '</a>';
            if (current_user_can('eldokan_backoffice_manage_registrations')) {
                if ($current_status !== 'approved') echo '<a class="button button-primary" href="'.esc_url($approve).'">' . esc_html__('Approve', 'eldokan-admin') . '</a>';
                if ($current_status !== 'rejected') echo '<a class="button" href="'.esc_url($reject).'">' . esc_html__('Reject', 'eldokan-admin') . '</a>';
            }
            if (current_user_can('eldokan_backoffice_delete_accounts')) {
                echo '<a class="button eda-danger-button" onclick="return confirm(\'Delete this registration permanently?\')" href="'.esc_url($delete).'">' . esc_html__('Delete', 'eldokan-admin') . '</a>';
            }
            if (!current_user_can('eldokan_backoffice_manage_registrations') && !current_user_can('eldokan_backoffice_delete_accounts')) {
                echo '<span class="eda-read-only"><span class="dashicons dashicons-lock"></span>' . esc_html__('Read only', 'eldokan-admin') . '</span>';
            }
            echo '</td></tr>';
        }

        echo '</table></div>';

        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'=>add_query_arg([
                    'page'=>'eldokan-admin-registrations',
                    'paged'=>'%#%',
                    's'=>$search,
                    'status'=>$status,
                    'date_from'=>$date_from,
                    'date_to'=>$date_to,
                ], admin_url('admin.php')),
                'format'=>'',
                'current'=>$page,
                'total'=>$pages,
            ]);
            echo '</div></div>';
        }

        echo '</div>';
        $this->footer();
    }

    private function send_registration_decision_email($user, $decision) {
        if (!$user instanceof WP_User) return false;

        $settings = $this->get_email_settings_admin();
        $defaults = $this->email_settings_defaults();
        $prefix = $decision === 'approved' ? 'approved' : 'rejected';

        $subject = (string)($settings[$prefix.'_subject'] ?? $defaults[$prefix.'_subject'] ?? '');
        $title = (string)($settings[$prefix.'_title'] ?? $defaults[$prefix.'_title'] ?? '');
        $message = (string)($settings[$prefix.'_message'] ?? $defaults[$prefix.'_message'] ?? '');

        $company = get_user_meta($user->ID, 'billing_company', true) ?: get_user_meta($user->ID, '_eldokan_company_name', true);
        $vars = [
            '{name}'=>$user->display_name ?: $user->user_login,
            '{email}'=>$user->user_email,
            '{company}'=>$company ?: '',
            '{role}'=>'Owner',
            '{login_url}'=>'https://sell.eldokan.com/',
        ];

        $subject = strtr($subject, $vars);
        $title = strtr($title, $vars);
        $message = strtr($message, $vars);

        $logo_id = absint($settings['logo_id'] ?? 0);
        $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
        $width = max(60, min(400, absint($settings['logo_width'] ?? 180)));
        $logo_html = $logo ? '<p style="text-align:center;margin:0 0 24px"><img src="'.esc_url($logo).'" alt="ElDokan" width="'.$width.'" style="max-width:100%;height:auto"></p>' : '';

        $body = '<!doctype html><html><body style="margin:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#101828"><div style="max-width:640px;margin:30px auto;background:#fff;border:1px solid #e4e7ec;border-radius:18px;padding:32px">'.$logo_html.'<h2 style="margin:0 0 18px">'.esc_html($title).'</h2><div style="font-size:15px;line-height:1.7">'.wpautop(esc_html($message)).'</div></div></body></html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_name = trim((string)($settings['sender_name'] ?? ''));
        $from_email = sanitize_email($settings['sender_email'] ?? '');
        if ($from_name !== '' && is_email($from_email)) {
            $headers[] = 'From: '.$from_name.' <'.$from_email.'>';
        }

        $sent = wp_mail($user->user_email, $subject, $body, $headers);
        update_user_meta($user->ID, '_eldokan_last_email_status', $sent ? 'sent' : 'failed');
        return $sent;
    }

    public function registration_status() {
        if (!current_user_can('eldokan_backoffice_manage_registrations')) wp_die('Not allowed');

        $uid = absint($_GET['user_id'] ?? 0);
        $status = sanitize_key($_GET['status'] ?? '');
        if (!$uid || !in_array($status, ['approved','rejected'], true)) wp_die('Invalid registration status.');

        check_admin_referer('eldokan_admin_registration_status_'.$uid);

        $user = get_userdata($uid);
        if (!$user || !$this->is_company_registration_user($user)) wp_die('Registration not found.');

        update_user_meta($uid, '_eldokan_account_status', $status);
        update_user_meta($uid, '_eldokan_registration_source', 'company_registration');
        update_user_meta($uid, '_eldokan_account_source', 'public_registration');

        // A primary registration never belongs to another Seller Owner.
        foreach (['_eldokan_parent_owner_id','_eldokan_owner_user_id','eldokan_owner_user_id','_eldokan_owner_group'] as $key) {
            delete_user_meta($uid, $key);
        }

        if ($status === 'approved') {
            $user->remove_role('eldokan_pending');
            if (!in_array('eldokan_owner', (array)$user->roles, true)) $user->add_role('eldokan_owner');

            $company = get_user_meta($uid, 'billing_company', true);
            if ($company !== '') update_user_meta($uid, '_eldokan_company_name', $company);
        } else {
            $user->remove_role('eldokan_owner');
            if (!in_array('eldokan_pending', (array)$user->roles, true)) $user->add_role('eldokan_pending');
        }

        $this->send_registration_decision_email(get_userdata($uid), $status);

        $this->write_audit_log(
            'registration_'.$status,
            'Seller registration '.($status === 'approved' ? 'approved' : 'rejected').': '.($user->display_name ?: $user->user_login),
            [
                'source'=>'admin',
                'actor_user_id'=>get_current_user_id(),
                'seller_user_id'=>$uid,
                'object_type'=>'seller_registration',
                'object_id'=>$uid,
                'meta'=>['status'=>$status,'email'=>$user->user_email],
            ]
        );

        delete_transient('eda_overall_30d');
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-registrations&registration_updated=1'));
        exit;
    }

    public function delete_registration() {
        if (!current_user_can('eldokan_backoffice_delete_accounts')) wp_die('Not allowed');

        $uid = absint($_GET['user_id'] ?? 0);
        if (!$uid || $uid === get_current_user_id()) wp_die('Invalid registration.');
        check_admin_referer('eldokan_admin_delete_registration_'.$uid);

        $user = get_userdata($uid);
        if (!$user || !$this->is_company_registration_user($user)) wp_die('Registration not found.');

        $email = $user->user_email;
        $name = $user->display_name ?: $user->user_login;
        $pdf_id = absint(get_user_meta($uid, '_eldokan_verification_pdf_attachment_id', true));
        if ($pdf_id) wp_delete_attachment($pdf_id, true);

        $this->write_audit_log(
            'registration_deleted',
            'Seller registration deleted: '.$name,
            [
                'source'=>'admin',
                'actor_user_id'=>get_current_user_id(),
                'object_type'=>'seller_registration',
                'object_id'=>$uid,
                'meta'=>['email'=>$email],
            ]
        );

        require_once ABSPATH.'wp-admin/includes/user.php';
        wp_delete_user($uid);

        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-registrations&registration_deleted=1'));
        exit;
    }

    public function view_registration_pdf() {
        if (!current_user_can('eldokan_backoffice_view_registrations')) wp_die('Not allowed');

        $uid = absint($_GET['user_id'] ?? 0);
        check_admin_referer('eldokan_admin_view_registration_pdf_'.$uid);

        $user = $uid ? get_userdata($uid) : null;
        if (!$user || !$this->is_company_registration_user($user)) wp_die('Registration not found.');

        $attachment_id = absint(get_user_meta($uid, '_eldokan_verification_pdf_attachment_id', true));
        if (!$attachment_id) wp_die('Verification PDF not found.');

        $path = get_attached_file($attachment_id);
        if (!$path || !is_readable($path)) wp_die('Verification PDF file is not readable.');

        $mime = get_post_mime_type($attachment_id);
        if ($mime !== 'application/pdf') wp_die('Verification document is not a PDF.');

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: inline; filename="'.basename($path).'"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function sellers() {
        if (!current_user_can('eldokan_backoffice_view_sellers')) {
            wp_die('Not allowed');
        }

        if (!empty($_GET['seller_id'])) {
            $seller_id = absint($_GET['seller_id']);
            $this->header('Seller / Company Details','Operational seller profile, products, orders, performance and access.');
            $this->seller_detail($seller_id);
            $this->footer();
            return;
        }

        $search = sanitize_text_field($_GET['s'] ?? '');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        $users = $this->owner_users(
            $search,
            $per_page,
            $offset
        );

        $count_query = new WP_User_Query([
            'role__in' => ['eldokan_owner','wcfm_vendor','vendor','seller'],
            'number' => 1,
            'count_total' => true,
            'fields' => 'ID',
            'search' => $search !== '' ? '*' . $search . '*' : '',
        ]);

        $total = (int)$count_query->get_total();

        $this->header(
            'Sellers',
            'One place to manage every seller company and all accounts that belong to it.'
        );

        echo '<form method="get" class="eda-search">';
        echo '<input type="hidden" name="page" value="eldokan-admin-sellers">';
        echo '<input name="s" value="'.esc_attr($search).'" placeholder="' . esc_attr__('Search seller/company', 'eldokan-admin') . '">';
        echo '<button class="button button-primary">' . esc_html__('Search', 'eldokan-admin') . '</button>';
        echo '</form>';

        echo '<div class="eda-card">';
        echo '<table class="eda-table">';
        echo '<tr><th>' . esc_html__('Company', 'eldokan-admin') . '</th><th>' . esc_html__('Owner', 'eldokan-admin') . '</th><th>' . esc_html__('Accounts', 'eldokan-admin') . '</th><th>' . esc_html__('Admin Rating', 'eldokan-admin') . '</th><th>' . esc_html__('Cancel Rating (Auto)', 'eldokan-admin') . '</th><th>' . esc_html__('30d Cancels', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th></th></tr>';

        foreach ($users as $u) {
            $rating = get_user_meta(
                $u->ID,
                '_eldokan_admin_rating',
                true
            ) ?: '—';

            $auto = $this->automatic_cancel_rating($u->ID, '30d');
            $account_count = count($this->company_accounts($u->ID));

            $status = get_user_meta(
                $u->ID,
                '_eldokan_account_status',
                true
            ) ?: 'active';

            $url = add_query_arg(
                ['page'=>'eldokan-admin-sellers','seller_id'=>$u->ID],
                admin_url('admin.php')
            );

            echo '<tr>';
            echo '<td><strong>'.
                esc_html($this->company_name($u->ID)).
                '</strong><br><small>#'.$u->ID.'</small></td>';
            echo '<td>'.
                esc_html($u->display_name).
                '<br><small>'.
                esc_html($u->user_email).
                '</small></td>';
            echo '<td><strong>'.absint($account_count).'</strong></td>';
            echo '<td><strong>'.esc_html($rating).($rating === '—' ? '' : '/5').'</strong><br><small>' . esc_html__('Manual', 'eldokan-admin') . '</small></td>';
            echo '<td><strong>'.($auto['rating'] === null ? '—' : absint($auto['rating']).'/5').'</strong><br><small>'.esc_html($auto['label']).'</small></td>';
            echo '<td>'.absint($auto['cancelled']).'<br><small>' . esc_html__('limit', 'eldokan-admin') . ' '.absint($auto['warning_limit']).'</small></td>';
            echo '<td>'.$this->badge($status).'</td>';
            echo '<td><a class="button button-primary" href="'.
                esc_url($url).
                '">' . esc_html__('Manage Seller', 'eldokan-admin') . '</a></td>';
            echo '</tr>';
        }

        echo '</table></div>';

        $pages = max(1, (int)ceil($total / $per_page));

        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'=>add_query_arg(
                    [
                        'page'=>'eldokan-admin-sellers',
                        'paged'=>'%#%',
                        's'=>$search
                    ],
                    admin_url('admin.php')
                ),
                'format'=>'',
                'current'=>$page,
                'total'=>$pages
            ]);
            echo '</div></div>';
        }


        $this->footer();
    }

    private function seller_detail($id) {
        $u=get_userdata($id);
        if(!$u)return;

        $idx=$this->indexed_seller_details($id);

        echo '<div class="eda-card eda-seller-control-nav"><div><h2>' . esc_html__('Seller Control Center', 'eldokan-admin') . '</h2><p>' . esc_html__('Everything related to this seller is managed from this page.', 'eldokan-admin') . '</p></div><div class="eda-seller-control-links">';
        foreach([
            'seller-overview'=>'Overview',
            'seller-accounts'=>'Accounts & Roles',
            'seller-dashboard-access'=>'Dashboard Access',
            'seller-performance'=>'Ratings & Performance',
            'seller-commission'=>'Commission',
            'seller-statement'=>'Statement',
            'seller-products'=>'Products',
            'seller-orders'=>'Orders'
        ] as $section=>$label){
            echo '<a class="button" href="#'.esc_attr($section).'">'.esc_html(translate($label, 'eldokan-admin')).'</a>';
        }
        echo '</div></div>';

        $seller_status = get_user_meta($id, '_eldokan_account_status', true) ?: 'approved';
        if (!empty($_GET['seller_status_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Seller status updated. Seller Dashboard access now uses the same status.', 'eldokan-admin') . '</p></div>';
        }
        echo '<div class="eda-card"><div class="eda-section-head"><div><h2>' . esc_html__('Seller Account Status', 'eldokan-admin') . '</h2><p>' . esc_html__('Central access switch for this seller. Disabled sellers cannot use the Seller Dashboard.', 'eldokan-admin') . '</p></div>'.$this->badge($seller_status).'</div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">';
        echo '<input type="hidden" name="action" value="eldokan_admin_seller_status">';
        echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
        wp_nonce_field('eda_seller_status_'.$id);
        echo '<label><b>' . esc_html__('Status', 'eldokan-admin') . '</b><br><select name="status"><option value="approved" '.selected($seller_status,'approved',false).'>' . esc_html__('Approved / Active', 'eldokan-admin') . '</option><option value="disabled" '.selected($seller_status,'disabled',false).'>' . esc_html__('Disabled', 'eldokan-admin') . '</option></select></label>';
        echo '<button class="button button-primary">' . esc_html__('Save Seller Status', 'eldokan-admin') . '</button>';
        echo '</form></div>';

        if($idx){
            $sv=$idx['seller'];
            echo '<div id="seller-overview" class="eda-card eda-detail"><h2>'.esc_html($sv['company_name']).' — Full WCFM History</h2>';
            echo '<div class="eda-grid">';
            foreach([
                'Products'=>$sv['products_count'],
                'Live Products'=>$sv['live_products'],
                'Orders'=>$sv['orders_count'],
                'Units Sold'=>$sv['units_sold'],
                'Gross Sales'=>number_format((float)$sv['gross_sales'],2).' EGP',
                'Cancelled Orders'=>$sv['cancelled_orders'],
                'Raw WCFM Records'=>$idx['raw_count'],
                'Last Order'=>$sv['last_order_at']?:'—'
            ] as $k=>$v)echo '<div class="eda-mini"><small>'.esc_html($k).'</small><strong>'.esc_html($v).'</strong></div>';
            echo '</div>';

            $settings=json_decode($sv['store_settings'],true);
            echo '<div class="eda-two"><div><h3>' . esc_html__('Company / Store Data', 'eldokan-admin') . '</h3><p><b>' . esc_html__('Email:', 'eldokan-admin') . '</b> '.esc_html($sv['email']).'</p><p><b>' . esc_html__('Phone:', 'eldokan-admin') . '</b> '.esc_html($sv['phone']?:'—').'</p><p><b>' . esc_html__('Status:', 'eldokan-admin') . '</b> '.esc_html($sv['status']).'</p><p><b>' . esc_html__('Registered:', 'eldokan-admin') . '</b> '.esc_html($sv['registered_at']).'</p><details><summary>' . esc_html__('WCFM Store Settings', 'eldokan-admin') . '</summary><pre class="eda-pre">'.esc_html(wp_json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre></details></div>';
            $role_id=$this->seller_access_role_id($id);$role=$role_id?$this->access_role_by_id($role_id):null;
            $manual_rating = absint(get_user_meta($id,'_eldokan_admin_rating',true));
            $manual_reason = (string)get_user_meta($id,'_eldokan_admin_rating_reason',true);
            $manual_at = (string)get_user_meta($id,'_eldokan_admin_rating_at',true);
            $auto_cancel = $this->automatic_cancel_rating($id, '30d');

            echo '<div><h3>' . esc_html__('ElDokan Control', 'eldokan-admin') . '</h3><p><b>' . esc_html__('Seller Access Role:', 'eldokan-admin') . '</b> '.esc_html($role?$role['name']:'Full Access').'</p>';
            echo '<p><b>' . esc_html__('Admin Rating:', 'eldokan-admin') . '</b> '.($manual_rating ? absint($manual_rating).'/5' : 'Not rated').'</p>';
            echo '<p><b>' . esc_html__('Automatic Cancel Rating:', 'eldokan-admin') . '</b> '.($auto_cancel['rating'] === null ? '—' : absint($auto_cancel['rating']).'/5').' <small>('.absint($auto_cancel['cancelled']).' cancelled in 30 days; warning limit '.absint($auto_cancel['warning_limit']).')</small></p>';
            echo '<p><small>' . esc_html__('Use the sections below to manage accounts, roles, dashboard access, ratings, commission and statements for this seller.', 'eldokan-admin') . '</small></p></div></div>';
            echo '</div>';

            echo '<div id="seller-performance" class="eda-two">';
            echo '<div class="eda-card"><h2>' . esc_html__('Manual Admin Rating', 'eldokan-admin') . '</h2><p>' . esc_html__('This rating is set by ElDokan management and is completely separate from the automatic cancellation rating.', 'eldokan-admin') . '</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-rating-form">';
            echo wp_nonce_field('eda_rate_'.$id,'_wpnonce',true,false);
            echo '<input type="hidden" name="action" value="eldokan_admin_rate_seller">';
            echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
            echo '<label>' . esc_html__('Rating', 'eldokan-admin') . '<select name="rating" required>';
            for($r=5;$r>=1;$r--){echo '<option value="'.$r.'" '.selected($manual_rating,$r,false).'>'.$r.' / 5</option>';}
            echo '</select></label>';
            echo '<label>' . esc_html__('Internal note', 'eldokan-admin') . '<textarea name="reason" rows="4" placeholder="Why did ElDokan give this seller this rating?">'.esc_textarea($manual_reason).'</textarea></label>';
            echo '<button class="button button-primary">' . esc_html__('Save Admin Rating', 'eldokan-admin') . '</button>';
            if($manual_at) echo '<small>' . esc_html__('Last updated:', 'eldokan-admin') . ' '.esc_html($manual_at).'</small>';
            echo '</form></div>';

            echo '<div class="eda-card"><h2>' . esc_html__('Automatic Cancel Rating', 'eldokan-admin') . '</h2>';
            echo '<div class="eda-rating-score">'.($auto_cancel['rating'] === null ? '—' : absint($auto_cancel['rating']).'/5').'</div>';
            echo '<p><b>' . esc_html__('30-day orders:', 'eldokan-admin') . '</b> '.absint($auto_cancel['orders']).'</p>';
            echo '<p><b>' . esc_html__('30-day cancelled orders:', 'eldokan-admin') . '</b> '.absint($auto_cancel['cancelled']).'</p>';
            echo '<p><b>' . esc_html__('Cancellation warning limit:', 'eldokan-admin') . '</b> '.absint($auto_cancel['warning_limit']).'</p>';
            $perf30=$this->seller_order_metrics($id,...$this->period('30d'));
            echo '<p><b>' . esc_html__('30-day sales:', 'eldokan-admin') . '</b> '.number_format((float)$perf30['gross'],2).' EGP</p>';
            echo '<p><b>' . esc_html__('30-day commission:', 'eldokan-admin') . '</b> '.number_format((float)$perf30['commission'],2).' EGP</p>';
            echo '<p><b>' . esc_html__('Status:', 'eldokan-admin') . '</b> '.$this->badge($auto_cancel['label']).'</p>';
            echo '<small>' . esc_html__('This score is calculated automatically from cancelled orders. The cancellation warning limit can be changed in Seller Dashboard Access below.', 'eldokan-admin') . '</small>';
            echo '</div></div>';

            $global_dashboard_cfg = get_option('eldokan_dashboard_control_global',$this->dashboard_defaults());
            $seller_dashboard_cfg = get_user_meta($id,'_eldokan_dashboard_control',true);
            $seller_dashboard_cfg = is_array($seller_dashboard_cfg) ? array_merge($global_dashboard_cfg,$seller_dashboard_cfg) : $global_dashboard_cfg;

            echo '<div id="seller-dashboard-access" class="eda-card">';
            echo '<div class="eda-section-head"><div><h2>' . esc_html__('Seller Dashboard Access', 'eldokan-admin') . '</h2><p>' . esc_html__('Control exactly what this seller can see and do in the React Seller Dashboard.', 'eldokan-admin') . '</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-dashboard-control')).'">' . esc_html__('Global Defaults', 'eldokan-admin') . '</a></div>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo wp_nonce_field('eda_dashboard_control','_wpnonce',true,false);
            echo '<input type="hidden" name="action" value="eldokan_admin_save_dashboard_control">';
            echo '<input type="hidden" name="scope" value="seller">';
            echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
            echo '<input type="hidden" name="return_seller_id" value="'.absint($id).'">';
            echo '<h3>' . esc_html__('Visible Sections', 'eldokan-admin') . '</h3><div class="eda-grid">';
            foreach(['home'=>'Home','products'=>'Products','orders'=>'Orders','reports'=>'Reports','accounts'=>'Accounts','notifications'=>'Notifications','settings'=>'Settings'] as $k=>$v){
                echo '<label class="eda-mini"><input type="checkbox" name="cfg['.$k.']" value="1" '.checked(!empty($seller_dashboard_cfg[$k]),true,false).'> <b>'.$v.'</b></label>';
            }
            echo '</div><h3>' . esc_html__('Permissions', 'eldokan-admin') . '</h3><div class="eda-grid">';
            foreach(['product_create'=>'Create Products','product_edit'=>'Edit Products','quick_edit'=>'Quick Edit','sale_price'=>'Sale Price','sale_dates'=>'Sale Dates','order_accept'=>'Accept Orders','order_reject'=>'Reject Orders'] as $k=>$v){
                echo '<label class="eda-mini"><input type="checkbox" name="cfg['.$k.']" value="1" '.checked(!empty($seller_dashboard_cfg[$k]),true,false).'> <b>'.$v.'</b></label>';
            }
            echo '</div><h3>' . esc_html__('Order Policy', 'eldokan-admin') . '</h3><div class="eda-form">';
            echo '<label>' . esc_html__('Maximum response time (days)', 'eldokan-admin') . '<input type="number" min="1" max="30" name="cfg[order_response_days]" value="'.esc_attr($seller_dashboard_cfg['order_response_days']??2).'"></label>';
            echo '<label>' . esc_html__('Cancellation warning limit', 'eldokan-admin') . '<input type="number" min="1" max="100" name="cfg[cancel_warning_limit]" value="'.esc_attr($seller_dashboard_cfg['cancel_warning_limit']??3).'"></label>';
            echo '</div><button class="button button-primary">' . esc_html__('Save Seller Dashboard Access', 'eldokan-admin') . '</button></form></div>';

            $accounts = $this->company_accounts($id);
            global $wpdb;
            $seller_roles = $wpdb->get_results(
                "SELECT * FROM {$this->access_roles_table} ORDER BY name ASC",
                ARRAY_A
            );
            $wcfm_roles = $this->wcfm_role_choices();

            if (!empty($_GET['role_saved'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Seller account role updated successfully.', 'eldokan-admin') . '</p></div>';
            }
            if (!empty($_GET['account_deleted'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Seller account deleted successfully.', 'eldokan-admin') . '</p></div>';
            }
            if (!empty($_GET['wcfm_role_saved'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('WCFM role updated successfully. The ElDokan dashboard role was kept.', 'eldokan-admin') . '</p></div>';
            }
            if (!empty($_GET['wcfm_bulk_saved'])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('WCFM role applied to all accounts in this seller group.', 'eldokan-admin') . '</p></div>';
            }

            echo '<div id="seller-accounts" class="eda-card">';
            echo '<div class="eda-section-head"><div><h2>' . esc_html__('Seller Accounts & Roles', 'eldokan-admin') . '</h2><p>' . esc_html__('ElDokan Role controls the Seller Dashboard. WCFM Role comes directly from the roles installed by WCFM/WordPress.', 'eldokan-admin') . '</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-access-roles')).'">' . esc_html__('Role Templates', 'eldokan-admin') . '</a></div>';

            if ($wcfm_roles) {
                echo '<div class="eda-wcfm-bulk">';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                echo wp_nonce_field('eda_bulk_wcfm_role_'.$id,'_wpnonce',true,false);
                echo '<input type="hidden" name="action" value="eldokan_admin_bulk_wcfm_role">';
                echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
                echo '<label><b>' . esc_html__('Apply WCFM Role to all accounts in this seller', 'eldokan-admin') . '</b><select name="wcfm_role" required>';
                echo '<option value="">' . esc_html__('Choose WCFM role', 'eldokan-admin') . '</option>';
                foreach($wcfm_roles as $slug=>$label){
                    echo '<option value="'.esc_attr($slug).'">'.esc_html(translate($label, 'eldokan-admin')).' ('.esc_html($slug).')</option>';
                }
                echo '</select></label>';
                echo '<button class="button button-primary">' . esc_html__('Apply to All Accounts', 'eldokan-admin') . '</button>';
                echo '<small>' . esc_html__('The WCFM role is added without replacing the ElDokan role.', 'eldokan-admin') . '</small>';
                echo '</form></div>';
            } else {
                echo '<div class="notice notice-warning inline"><p>' . esc_html__('No WCFM/vendor roles were detected. Make sure WCFM is active and its roles exist in WordPress.', 'eldokan-admin') . '</p></div>';
            }

            echo '<table class="eda-table"><tr><th>' . esc_html__('Account', 'eldokan-admin') . '</th><th>' . esc_html__('Email', 'eldokan-admin') . '</th><th>' . esc_html__('Current WP Roles', 'eldokan-admin') . '</th><th>' . esc_html__('ElDokan Role', 'eldokan-admin') . '</th><th>' . esc_html__('WCFM Role', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Actions', 'eldokan-admin') . '</th></tr>';

            foreach($accounts as $account){
                $account_role_id=$this->seller_access_role_id($account->ID);
                $account_status=get_user_meta($account->ID,'_eldokan_account_status',true)?:'active';
                $is_owner=$account->ID===absint($id);
                $current_wcfm_roles=$this->user_wcfm_roles($account);
                $current_wcfm_slug=$current_wcfm_roles ? array_key_first($current_wcfm_roles) : '';

                echo '<tr>';
                echo '<td><strong>'.esc_html($account->display_name ?: $account->user_login).'</strong> '.($is_owner?'<span class="eda-badge">' . esc_html__('Primary Seller', 'eldokan-admin') . '</span>':'').'<br><small>#'.absint($account->ID).'</small></td>';
                echo '<td>'.esc_html($account->user_email).'</td>';
                echo '<td>'.esc_html(implode(', ',$account->roles)).'</td>';

                echo '<td>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-inline-role-form">';
                echo wp_nonce_field('eda_account_role_'.$id.'_'.$account->ID,'_wpnonce',true,false);
                echo '<input type="hidden" name="action" value="eldokan_admin_update_seller_account_role">';
                echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
                echo '<input type="hidden" name="account_id" value="'.absint($account->ID).'">';
                echo '<select name="role_id">';
                echo '<option value="0" '.selected($account_role_id,0,false).'>' . esc_html__('Full Access', 'eldokan-admin') . '</option>';
                foreach($seller_roles as $seller_role){
                    echo '<option value="'.absint($seller_role['id']).'" '.selected($account_role_id,absint($seller_role['id']),false).'>'.esc_html($seller_role['name']).'</option>';
                }
                echo '</select>';
                echo '<button class="button button-small">' . esc_html__('Save', 'eldokan-admin') . '</button>';
                echo '</form>';
                echo '</td>';

                echo '<td>';
                if($wcfm_roles){
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-inline-role-form">';
                    echo wp_nonce_field('eda_wcfm_role_'.$id.'_'.$account->ID,'_wpnonce',true,false);
                    echo '<input type="hidden" name="action" value="eldokan_admin_update_wcfm_role">';
                    echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
                    echo '<input type="hidden" name="account_id" value="'.absint($account->ID).'">';
                    echo '<select name="wcfm_role">';
                    echo '<option value="">' . esc_html__('No WCFM Role', 'eldokan-admin') . '</option>';
                    foreach($wcfm_roles as $slug=>$label){
                        echo '<option value="'.esc_attr($slug).'" '.selected($current_wcfm_slug,$slug,false).'>'.esc_html(translate($label, 'eldokan-admin')).'</option>';
                    }
                    echo '</select>';
                    echo '<button class="button button-small">' . esc_html__('Save WCFM', 'eldokan-admin') . '</button>';
                    echo '</form>';
                } else {
                    echo '—';
                }
                echo '</td>';

                echo '<td>'.$this->badge($account_status).'</td>';

                echo '<td class="eda-account-actions">';
                echo '<a class="button button-small" href="'.esc_url(get_edit_user_link($account->ID)).'">' . esc_html__('Edit Account', 'eldokan-admin') . '</a>';

                if ($is_owner) {
                    echo '<span class="eda-owner-protected">' . esc_html__('Primary seller cannot be deleted here', 'eldokan-admin') . '</span>';
                } else {
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-inline-delete-form" onsubmit="return confirm(\'Delete this seller account permanently? Its WordPress content will be reassigned to the primary seller.\');">';
                    echo wp_nonce_field('eda_delete_seller_account_'.$id.'_'.$account->ID,'_wpnonce',true,false);
                    echo '<input type="hidden" name="action" value="eldokan_admin_delete_seller_account">';
                    echo '<input type="hidden" name="seller_id" value="'.absint($id).'">';
                    echo '<input type="hidden" name="account_id" value="'.absint($account->ID).'">';
                    echo '<button class="button button-small eda-danger-button">' . esc_html__('Delete Account', 'eldokan-admin') . '</button>';
                    echo '</form>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo '<p class="description"><b>' . esc_html__('Important:', 'eldokan-admin') . '</b> ' . esc_html__('WCFM Role is added alongside the ElDokan role, not instead of it. This keeps Seller Dashboard login and permissions working.', 'eldokan-admin') . '</p>';
            echo '</div>';

            $seller_cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
            $seller_commission_rules = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->commission_table} WHERE scope='seller' AND seller_user_id=%d ORDER BY id DESC LIMIT 100",
                $id
            ));

            echo '<div id="seller-commission" class="eda-card">';
            echo '<div class="eda-section-head"><div><h2>' . esc_html__('Seller Commission', 'eldokan-admin') . '</h2><p>' . esc_html__('Commission rules for this seller only. Seller rules override matching global rules.', 'eldokan-admin') . '</p></div><a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-commissions')).'">' . esc_html__('Global Commission Defaults', 'eldokan-admin') . '</a></div>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo wp_nonce_field('eda_save_commission','_wpnonce',true,false);
            echo '<input type="hidden" name="action" value="eldokan_admin_save_commission">';
            echo '<input type="hidden" name="scope" value="seller">';
            echo '<input type="hidden" name="seller_user_id" value="'.absint($id).'">';
            echo '<input type="hidden" name="return_seller_id" value="'.absint($id).'">';
            echo '<div class="eda-form">';
            echo '<label>' . esc_html__('Apply To', 'eldokan-admin') . '<select name="target_type" id="sellerCommissionTarget"><option value="all">' . esc_html__('All Seller Products', 'eldokan-admin') . '</option><option value="category">' . esc_html__('Category', 'eldokan-admin') . '</option><option value="product">' . esc_html__('One Product', 'eldokan-admin') . '</option></select></label>';
            echo '<label id="sellerCommissionTargetId" style="display:none">' . esc_html__('Target', 'eldokan-admin') . '<select name="target_id"><option value="0">' . esc_html__('Select', 'eldokan-admin') . '</option>';
            foreach((is_wp_error($seller_cats) ? [] : $seller_cats) as $c){echo '<option data-kind="category" value="'.absint($c->term_id).'">' . esc_html__('Category:', 'eldokan-admin') . ' '.esc_html($c->name).'</option>';}
            foreach($idx['products'] as $p){echo '<option data-kind="product" value="'.absint($p['product_id']).'">' . esc_html__('Product:', 'eldokan-admin') . ' '.esc_html($p['title']).' (#'.absint($p['product_id']).')</option>';}
            echo '</select></label>';
            echo '<label>' . esc_html__('Type', 'eldokan-admin') . '<select name="calculation_type" id="sellerCommissionType"><option value="percentage">' . esc_html__('Percentage %', 'eldokan-admin') . '</option><option value="fixed">' . esc_html__('Fixed EGP', 'eldokan-admin') . '</option><option value="hybrid">' . esc_html__('Percentage % + Fixed EGP', 'eldokan-admin') . '</option></select></label>';
            echo '<label id="sellerCommissionValueLabel"><span id="sellerCommissionValueText">' . esc_html__('Percentage %', 'eldokan-admin') . '</span><input type="number" min="0" step="0.01" name="value" id="sellerCommissionValue" required></label>';
            echo '<label id="sellerCommissionFixedWrap" style="display:none"><span>' . esc_html__('Fixed Add-on EGP', 'eldokan-admin') . '</span><input type="number" min="0" step="0.01" name="fixed_value" id="sellerCommissionFixed" value="0"></label>';
            echo '</div><p class="description" id="sellerCommissionHelp">' . esc_html__('Percentage: ElDokan takes a percentage of the seller line total.', 'eldokan-admin') . '</p><button class="button button-primary">' . esc_html__('Add Seller Commission Rule', 'eldokan-admin') . '</button></form>';
            echo '<hr><h3>' . esc_html__('Latest 100 seller rule revisions', 'eldokan-admin') . '</h3><table class="eda-table"><tr><th>' . esc_html__('Target', 'eldokan-admin') . '</th><th>' . esc_html__('Type', 'eldokan-admin') . '</th><th>' . esc_html__('Value', 'eldokan-admin') . '</th><th></th></tr>';
            if(!$seller_commission_rules){echo '<tr><td colspan="4">' . esc_html__('No seller-specific commission rules. Global rules apply.', 'eldokan-admin') . '</td></tr>';}
            foreach($seller_commission_rules as $r){
                $target_label = ucfirst($r->target_type).($r->target_id ? ' #'.absint($r->target_id) : '');
                $del = wp_nonce_url(add_query_arg(['action'=>'eldokan_admin_delete_commission','id'=>$r->id,'seller_id'=>$id],admin_url('admin-post.php')),'eda_del_'.$r->id);
                echo '<tr><td>'.esc_html($target_label).'</td><td>'.esc_html($this->commission_type_label($r->calculation_type)).'</td><td>'.esc_html($this->commission_rule_value_label($r)).'<br><small>'.esc_html((int)$r->enabled === 1 ? __('Enabled', 'eldokan-admin') : __('Disabled', 'eldokan-admin')).'</small></td><td>'.$this->commission_rule_actions($r, ['seller_id'=>$id]).'</td></tr>';
            }
            echo '</table></div>';
            echo '<script>document.addEventListener("DOMContentLoaded",()=>{const t=document.getElementById("sellerCommissionTarget"),wrap=document.getElementById("sellerCommissionTargetId"),sel=wrap?wrap.querySelector("select"):null,type=document.getElementById("sellerCommissionType"),valueText=document.getElementById("sellerCommissionValueText"),value=document.getElementById("sellerCommissionValue"),fixedWrap=document.getElementById("sellerCommissionFixedWrap"),fixed=document.getElementById("sellerCommissionFixed"),help=document.getElementById("sellerCommissionHelp");if(t&&wrap&&sel){const f=()=>{wrap.style.display=t.value==="all"?"none":"grid";[...sel.options].forEach(o=>{if(o.dataset.kind)o.hidden=o.dataset.kind!==t.value});if(t.value==="all")sel.value="0"};t.onchange=f;f()}if(type&&valueText&&value&&fixedWrap&&fixed&&help){const c=()=>{if(type.value==="percentage"){valueText.textContent="Percentage %";value.max="100";fixedWrap.style.display="none";fixed.value="0";help.textContent="Percentage: ElDokan takes the entered percentage of the seller line total."}else if(type.value==="fixed"){valueText.textContent="Fixed EGP";value.removeAttribute("max");fixedWrap.style.display="none";fixed.value="0";help.textContent="Fixed: ElDokan takes the entered fixed EGP amount."}else{valueText.textContent="Percentage %";value.max="100";fixedWrap.style.display="grid";help.textContent="Percentage + Fixed: ElDokan takes both amounts together. Example: 5% + 20 EGP."}};type.onchange=c;c()}})</script>';

            $st_today=$this->seller_order_metrics($id,...$this->period('today'));
            $st_7d=$this->seller_order_metrics($id,...$this->period('7d'));
            $st_30d=$this->seller_order_metrics($id,...$this->period('30d'));
            $st_month=$this->seller_order_metrics($id,...$this->period('month'));
            echo '<div id="seller-statement" class="eda-card"><h2>' . esc_html__('Seller Statement', 'eldokan-admin') . '</h2><p>' . esc_html__('Financial summary for this seller.', 'eldokan-admin') . '</p><table class="eda-table"><tr><th>' . esc_html__('Period', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Platform Commission', 'eldokan-admin') . '</th><th>' . esc_html__('Seller Net Proceeds', 'eldokan-admin') . '</th><th>' . esc_html__('Orders', 'eldokan-admin') . '</th><th>' . esc_html__('Cancelled', 'eldokan-admin') . '</th></tr>';
            foreach(['Today'=>$st_today,'7 Days'=>$st_7d,'30 Days'=>$st_30d,'This Month'=>$st_month] as $label=>$m){
                echo '<tr><td><strong>'.esc_html(translate($label, 'eldokan-admin')).'</strong></td><td>'.number_format((float)$m['gross'],2).' EGP</td><td>'.number_format((float)$m['commission'],2).' EGP</td><td>'.number_format((float)$m['gross']-(float)$m['commission'],2).' EGP</td><td>'.absint($m['orders']).'</td><td>'.absint($m['cancelled']).'</td></tr>';
            }
            echo '</table></div>';

            echo '<div id="seller-products" class="eda-card"><h2>' . esc_html__('Products Sold / Listed', 'eldokan-admin') . '</h2><table class="eda-table"><tr><th>' . esc_html__('Product', 'eldokan-admin') . '</th><th>' . esc_html__('SKU', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Price', 'eldokan-admin') . '</th><th>' . esc_html__('Sale', 'eldokan-admin') . '</th><th>' . esc_html__('Stock', 'eldokan-admin') . '</th><th>' . esc_html__('Units Sold', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th></tr>';
            foreach($idx['products'] as $p)echo '<tr><td><strong>'.esc_html($p['title']).'</strong><br>#'.absint($p['product_id']).'</td><td>'.esc_html($p['sku']).'</td><td>'.$this->badge($p['status']).'</td><td>'.number_format((float)$p['regular_price'],2).'</td><td>'.($p['sale_price']!==null?number_format((float)$p['sale_price'],2):'—').'</td><td>'.esc_html($p['stock_qty']??'—').'</td><td>'.esc_html($p['total_units_sold']).'</td><td>'.number_format((float)$p['total_sales'],2).' EGP</td></tr>';
            echo '</table></div>';

            echo '<div id="seller-orders" class="eda-card"><h2>' . esc_html__('Seller Order History', 'eldokan-admin') . '</h2><table class="eda-table"><tr><th>' . esc_html__('Order', 'eldokan-admin') . '</th><th>' . esc_html__('Date', 'eldokan-admin') . '</th><th>' . esc_html__('Product', 'eldokan-admin') . '</th><th>' . esc_html__('Qty', 'eldokan-admin') . '</th><th>' . esc_html__('Line Total', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Customer', 'eldokan-admin') . '</th><th>' . esc_html__('Payment', 'eldokan-admin') . '</th></tr>';
            foreach($idx['orders'] as $o)echo '<tr><td>#'.absint($o['order_id']).'</td><td>'.esc_html($o['created_at']).'</td><td>'.esc_html($o['product_name']).'<br><small>'.esc_html($o['sku']).'</small></td><td>'.esc_html($o['quantity']).'</td><td>'.number_format((float)$o['line_total'],2).' EGP</td><td>'.$this->badge($o['order_status']).'</td><td>'.esc_html($o['customer_name']).'<br><small>'.esc_html($o['customer_email']).'</small></td><td>'.esc_html($o['payment_method']).'</td></tr>';
            echo '</table></div>';
            return;
        }

        echo '<div class="eda-card"><h2>' . esc_html__('Seller data is not indexed yet', 'eldokan-admin') . '</h2><p>' . esc_html__('Open', 'eldokan-admin') . ' <a href="'.esc_url(admin_url('admin.php?page=eldokan-admin-wcfm-index')).'">' . esc_html__('WCFM Data Index', 'eldokan-admin') . '</a>' . esc_html__(', confirm', 'eldokan-admin') . ' <b>' . esc_html__('Index Schema: Ready', 'eldokan-admin') . '</b>' . esc_html__(', then use', 'eldokan-admin') . ' <b>' . esc_html__('Rebuild ElDokan Index', 'eldokan-admin') . '</b> ' . esc_html__('and Start / Resume Full Index.', 'eldokan-admin') . '</p></div>';
    }

    public function accounts() {
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-sellers'));
        exit;
    }

    public function products() {
        if (!current_user_can('eldokan_backoffice_view_products')) wp_die('Not allowed');
        $view = $this->product_view_state($_GET);
        $product_page = $view['paged'];
        $category_id = $view['category_id'];
        $product_search = $view['s'];
        $product_status = $view['status'];
        $category = $category_id ? get_term($category_id, 'product_cat') : null;

        $title = 'Products';
        $description = 'Every seller product, status, stock, price and company.';

        if ($category && !is_wp_error($category)) {
            $title = 'Products — '.$category->name;
            $description = 'Products inside the selected category. Use Back to Categories to choose another category.';
        } else {
            $category_id = 0;
        }

        $this->header($title, $description);

        if (!empty($_GET['commission_changed'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Product override changed. The next matching rule now applies.', 'eldokan-admin').'</p></div>';
        if (!empty($_GET['commission_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Product commission saved successfully.', 'eldokan-admin') . '</p></div>';
        }

        $view['category_id'] = $category_id;
        $query_args = $this->product_query_args($view);

        $q = new WP_Query($query_args);
        if ($q->max_num_pages > 0 && $product_page > (int) $q->max_num_pages) {
            $product_page = (int) $q->max_num_pages;
            $query_args['paged'] = $product_page;
            $view['paged'] = $product_page;
            $q = new WP_Query($query_args);
        }

        echo '<div class="eda-card"><p class="description">' . esc_html__('Counts and results use the same active language filters. Translations are separate product records; private records are included in All Statuses.', 'eldokan-admin') . '</p>';
        echo '<form method="get" style="display:grid;grid-template-columns:minmax(260px,2fr) minmax(160px,1fr) auto auto;gap:10px;align-items:end">';
        echo '<input type="hidden" name="page" value="eldokan-admin-products">';
        if ($category_id) echo '<input type="hidden" name="category_id" value="'.absint($category_id).'">';
        if ($view['lang'] !== '') echo '<input type="hidden" name="lang" value="'.esc_attr($view['lang']).'">';
        echo '<label><b>' . esc_html__('Search Products', 'eldokan-admin') . '</b><br><input type="search" name="s" value="'.esc_attr($product_search).'" placeholder="Product name" style="width:100%"></label>';
        echo '<label><b>' . esc_html__('Status', 'eldokan-admin') . '</b><br><select name="status" style="width:100%">';
        foreach ([''=>'All Statuses','publish'=>'Live','pending'=>'Pending','draft'=>'Draft','private'=>'Private'] as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected($product_status,$key,false).'>'.esc_html(translate($label, 'eldokan-admin')).'</option>';
        }
        echo '</select></label>';
        echo '<button class="button button-primary">' . esc_html__('Search / Filter', 'eldokan-admin') . '</button>';
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-products')).'">' . esc_html__('Reset', 'eldokan-admin') . '</a>';
        echo '</form></div>';

        echo '<div class="eda-card">';

        if ($category_id) {
            echo '<div class="eda-section-head">';
            echo '<div><h2>'.esc_html($category->name).'</h2><p>'.absint($q->found_posts).' product(s) in this category and its child categories.</p></div>';
            echo '<div>';
            echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-categories')).'">' . esc_html__('← Back to Categories', 'eldokan-admin') . '</a> ';
            echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-products')).'">' . esc_html__('All Products', 'eldokan-admin') . '</a>';
            echo '</div>';
            echo '</div>';
        }

        if (!$q->posts) {
            echo '<p>' . esc_html__('No products found', 'eldokan-admin') . ''.($category_id ? ' in this category' : '').'.</p>';
            echo '</div>';
            $this->footer();
            return;
        }

        echo '<div class="eda-table-scroll"><table class="eda-table eda-commission-table"><tr><th>' . esc_html__('Product', 'eldokan-admin') . '</th><th>' . esc_html__('Category', 'eldokan-admin') . '</th><th>' . esc_html__('Company', 'eldokan-admin') . '</th><th>' . esc_html__('Price', 'eldokan-admin') . '</th><th>' . esc_html__('Stock', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Current Fee', 'eldokan-admin') . '</th><th>' . esc_html__('Fee Control', 'eldokan-admin') . '</th><th></th></tr>';

        foreach($q->posts as $p){
            $product = function_exists('wc_get_product') ? wc_get_product($p->ID) : null;
            $sid = $this->product_owner_id($p->ID);
            $product_terms = get_the_terms($p->ID, 'product_cat');
            $category_names = [];

            if (is_array($product_terms)) {
                foreach ($product_terms as $product_term) {
                    $category_names[] = $product_term->name;
                }
            }

            $product_rule = $this->global_product_rule($p->ID);
            $effective_rule = $this->effective_rule($sid, $p->ID);

            echo '<tr id="product-'.absint($p->ID).'">';
            echo '<td><strong>'.esc_html($p->post_title).'</strong><br>#'.$p->ID.'<br><small>' . esc_html__('SKU:', 'eldokan-admin') . ' '.esc_html($product?$product->get_sku():'—').'</small></td>';
            echo '<td>'.esc_html($category_names ? implode(', ', $category_names) : '—').'</td>';
            echo '<td>'.esc_html($this->company_name($sid)).'</td>';
            echo '<td>' . esc_html__('Regular:', 'eldokan-admin') . ' '.esc_html($product?$product->get_regular_price():'—').'<br>' . esc_html__('Sale:', 'eldokan-admin') . ' '.esc_html($product?$product->get_sale_price():'—').'</td>';
            echo '<td>'.esc_html($product?$product->get_stock_quantity():'—').'</td>';
            echo '<td>'.$this->badge($p->post_status).'</td>';
            echo '<td><strong>'.esc_html($this->commission_rule_value_label($effective_rule)).'</strong><br><small>' . esc_html__('Source:', 'eldokan-admin') . ' '.esc_html($this->commission_rule_source_label($effective_rule)).'</small></td>';
            echo '<td>';
            $commission_form = $this->commission_inline_form('product', $p->ID, $product_rule, $view);
            echo $commission_form;
            echo '</td>';
            if (current_user_can('eldokan_backoffice_manage_products')) {
                echo '<td><a class="button" href="'.esc_url(get_edit_post_link($p->ID)).'">' . esc_html__('Edit', 'eldokan-admin') . '</a></td>';
            } else {
                echo '<td><span class="eda-read-only"><span class="dashicons dashicons-lock"></span>' . esc_html__('View only', 'eldokan-admin') . '</span></td>';
            }
            echo '</tr>';
        }

        echo '</table></div></div>';
        $total_products = (int) $q->found_posts;
        echo '<div class="eda-pagination" aria-label="' . esc_attr__('Product pages', 'eldokan-admin') . '">';
        echo '<span>' . esc_html(sprintf(__('Page %1$d of %2$d · %3$d matching products', 'eldokan-admin'), $product_page, max(1, (int) $q->max_num_pages), $total_products)) . '</span>';
        if ((int) $q->max_num_pages > 1) {
            echo paginate_links([
                'base' => str_replace('999999999', '%#%', add_query_arg(['page' => 'eldokan-admin-products', 'paged' => 999999999, 'category_id' => $category_id, 's' => rawurlencode($product_search), 'status' => $product_status, 'lang'=>$view['lang']], admin_url('admin.php'))),
                'format' => '', 'current' => $product_page, 'total' => (int) $q->max_num_pages,
                'prev_text' => __('Previous', 'eldokan-admin'), 'next_text' => __('Next', 'eldokan-admin'),
            ]);
        }
        echo '</div>';
        echo $this->inline_commission_script();
        $this->footer();
    }

    private function eldokan_official_order_statuses() {
        return [
            'processing' => 'New Order',
            'accepted' => 'Preparing',
            'received-seller' => 'Received from Seller',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            // Exceptional / terminal statuses.
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned',
            'delivery-failed' => 'Delivery Failed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
        ];
    }

    public function orders() {
        if (!current_user_can('eldokan_backoffice_view_orders')) wp_die('Not allowed');
        $this->header('Orders','Customer orders with seller and date filters.');
        if (!empty($_GET['order_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Order status updated. WooCommerce is the canonical order status and Seller Dashboard sync runs on the same status-change event.', 'eldokan-admin') . '</p></div>';
        }

        $selected_seller = isset($_GET['seller_id']) ? absint($_GET['seller_id']) : 0;
        $order_search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $selected_status = sanitize_key($_GET['status'] ?? '');
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $order_page = isset($_GET['order_page']) ? max(1, absint($_GET['order_page'])) : 1;
        $per_page = 50;

        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $from_ts = 0;
        $to_ts = 0;
        try {
            if ($date_from !== '') {
                $from_dt = new DateTimeImmutable($date_from . ' 00:00:00', $tz);
                $from_ts = $from_dt->getTimestamp();
            }
            if ($date_to !== '') {
                $to_dt = new DateTimeImmutable($date_to . ' 23:59:59', $tz);
                $to_ts = $to_dt->getTimestamp();
            }
        } catch (Exception $e) {
            $date_from = '';
            $date_to = '';
            $from_ts = 0;
            $to_ts = 0;
        }

        $sellers = $this->owner_users('', 200, 0);
        echo '<div class="eda-card" style="margin-bottom:16px">';
        echo '<form method="get" style="display:grid;grid-template-columns:minmax(200px,1.5fr) minmax(200px,1.5fr) minmax(150px,1fr) minmax(150px,1fr) minmax(150px,1fr) auto auto;gap:12px;align-items:end">';
        echo '<input type="hidden" name="page" value="eldokan-admin-orders">';
        echo '<label><strong>' . esc_html__('Search', 'eldokan-admin') . '</strong><br><input type="search" name="s" value="'.esc_attr($order_search).'" placeholder="' . esc_attr__('Order #, customer name or email', 'eldokan-admin') . '" style="width:100%"></label>';
        echo '<label><strong>' . esc_html__('Seller', 'eldokan-admin') . '</strong><br><select name="seller_id" style="width:100%"><option value="0">' . esc_html__('All Sellers', 'eldokan-admin') . '</option>';
        foreach ($sellers as $seller) {
            $sid = $this->owner_id($seller->ID);
            if ($sid !== $seller->ID) continue;
            echo '<option value="'.absint($sid).'" '.selected($selected_seller, $sid, false).'>'.esc_html($this->company_name($sid)).' (#'.absint($sid).')</option>';
        }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__('Status', 'eldokan-admin') . '</strong><br><select name="status" style="width:100%"><option value="">' . esc_html__('All Statuses', 'eldokan-admin') . '</option>';
        foreach ($this->eldokan_official_order_statuses() as $status_value=>$status_label) {
            echo '<option value="'.esc_attr($status_value).'" '.selected($selected_status,$status_value,false).'>'.esc_html($status_label).'</option>';
        }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__('From Date', 'eldokan-admin') . '</strong><br><input type="date" name="date_from" value="'.esc_attr($date_from).'" style="width:100%"></label>';
        echo '<label><strong>' . esc_html__('To Date', 'eldokan-admin') . '</strong><br><input type="date" name="date_to" value="'.esc_attr($date_to).'" style="width:100%"></label>';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Filter Orders', 'eldokan-admin') . '</button>';
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-orders')).'">' . esc_html__('Reset', 'eldokan-admin') . '</a>';
        echo '</form></div>';

        $matching = [];
        $batch_page = 1;
        $batch_size = 200;
        $max_pages = 1;

        do {
            $result = wc_get_orders([
                'type' => 'shop_order',
                'limit' => $batch_size,
                'paged' => $batch_page,
                'paginate' => true,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
            ]);

            $orders = is_object($result) && isset($result->orders) ? $result->orders : [];
            $max_pages = is_object($result) && isset($result->max_num_pages) ? max(1, absint($result->max_num_pages)) : 1;

            foreach ((array)$orders as $o) {
                if (!is_object($o) || !is_a($o, 'WC_Order') || is_a($o, 'WC_Order_Refund')) continue;

                $created = $o->get_date_created();
                if (!$created) continue;
                $created_ts = $created->getTimestamp();
                if ($from_ts && $created_ts < $from_ts) continue;
                if ($to_ts && $created_ts > $to_ts) continue;
                if ($selected_status !== '') {
                    $actual_status = $o->get_status();
                    if ($selected_status === 'received-seller') {
                        if (!in_array($actual_status, ['received-seller','received-from-sel','received-from-seller'], true)) continue;
                    } elseif ($actual_status !== $selected_status) {
                        continue;
                    }
                }

                if ($order_search !== '') {
                    $needle = function_exists('mb_strtolower') ? mb_strtolower($order_search) : strtolower($order_search);
                    $order_fields = [
                        (string)$o->get_order_number(),
                        (string)$o->get_billing_first_name().' '.(string)$o->get_billing_last_name(),
                        (string)$o->get_billing_email(),
                        (string)$o->get_billing_phone(),
                    ];
                    $matched_search = false;
                    foreach ($order_fields as $field) {
                        $haystack = function_exists('mb_strtolower') ? mb_strtolower($field) : strtolower($field);
                        if ($field !== '' && strpos($haystack, $needle) !== false) {
                            $matched_search = true;
                            break;
                        }
                    }
                    if (!$matched_search) continue;
                }

                if ($selected_seller) {
                    $seller_match = false;
                    foreach ($o->get_items('line_item') as $it) {
                        if ($this->order_item_display_seller_id($it) === $selected_seller) {
                            $seller_match = true;
                            break;
                        }
                    }
                    if (!$seller_match) continue;
                }

                $matching[] = $o;
            }

            // Orders are sorted newest first. Once an entire batch is older than From Date,
            // later batches cannot contain a match, so stop early.
            if ($from_ts && !empty($orders)) {
                $last = end($orders);
                if (is_object($last) && method_exists($last, 'get_date_created')) {
                    $last_date = $last->get_date_created();
                    if ($last_date && $last_date->getTimestamp() < $from_ts) break;
                }
            }

            $batch_page++;
        } while ($batch_page <= $max_pages);

        $total_matching = count($matching);
        $total_pages = max(1, (int)ceil($total_matching / $per_page));
        if ($order_page > $total_pages) $order_page = $total_pages;
        $page_orders = array_slice($matching, ($order_page - 1) * $per_page, $per_page);

        echo '<div class="eda-card">';
        echo '<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">';
        echo '<strong>'.number_format_i18n($total_matching).' order(s) found</strong>';
        if ($date_from || $date_to || $selected_seller) {
            $parts = [];
            if ($selected_seller) $parts[] = 'Seller: ' . $this->company_name($selected_seller);
            if ($date_from) $parts[] = 'From: ' . $date_from;
            if ($date_to) $parts[] = 'To: ' . $date_to;
            echo '<span style="color:#64748b">'.esc_html(implode(' · ', $parts)).'</span>';
        }
        echo '</div>';

        echo '<div class="eda-table-scroll"><table class="eda-table"><tr><th>' . esc_html__('Order', 'eldokan-admin') . '</th><th>' . esc_html__('Date', 'eldokan-admin') . '</th><th>' . esc_html__('Customer', 'eldokan-admin') . '</th><th>' . esc_html__('Seller(s)', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Total', 'eldokan-admin') . '</th><th></th></tr>';
        if (!$page_orders) {
            echo '<tr><td colspan="7" style="text-align:center;padding:28px">' . esc_html__('No orders found for the selected filters.', 'eldokan-admin') . '</td></tr>';
        }
        foreach ($page_orders as $o) {
            $names=[];
            foreach($o->get_items('line_item') as $it){
                $sid=$this->order_item_display_seller_id($it);
                if($sid)$names[$sid]=$this->company_name($sid);
            }
            $order_number = method_exists($o, 'get_order_number') ? $o->get_order_number() : $o->get_id();
            echo '<tr><td><strong>#'.esc_html($order_number).'</strong></td><td>'.esc_html($o->get_date_created()?$o->get_date_created()->date_i18n('Y-m-d H:i'):'—').'</td><td>'.esc_html($o->get_formatted_billing_full_name()).'<br><small>'.esc_html($o->get_billing_email()).'</small></td><td>'.esc_html(implode(', ',$names)?:'—').'</td><td>'.$this->badge($o->get_status()).'</td><td>'.wp_kses_post($o->get_formatted_order_total()).'</td><td class="eda-row-actions">';
            if (current_user_can('eldokan_backoffice_manage_orders')) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-inline-action-form">';
                echo '<input type="hidden" name="action" value="eldokan_admin_update_order_status"><input type="hidden" name="order_id" value="'.absint($o->get_id()).'">';
                wp_nonce_field('eda_order_status_'.$o->get_id());
                echo '<select name="status">';
                $official_statuses = $this->eldokan_official_order_statuses();
                $current_status = $o->get_status();
                $display_current_status = in_array($current_status, ['received-from-sel','received-from-seller'], true)
                    ? 'received-seller'
                    : $current_status;
                if (!isset($official_statuses[$display_current_status])) {
                    // Historical/internal WooCommerce status: readable but not selectable again.
                    $current_label = function_exists('wc_get_order_status_name') ? wc_get_order_status_name($current_status) : ucwords(str_replace('-', ' ', $current_status));
                    echo '<option value="'.esc_attr($current_status).'" selected disabled>'.esc_html($current_label).' (Legacy / Internal)</option>';
                }
                foreach ($official_statuses as $status_value=>$status_label) {
                    echo '<option value="'.esc_attr($status_value).'" '.selected($display_current_status,$status_value,false).'>'.esc_html($status_label).'</option>';
                }
                echo '</select><button class="button button-primary">' . esc_html__('Update', 'eldokan-admin') . '</button><a class="button" href="'.esc_url($o->get_edit_order_url()).'">' . esc_html__('Open', 'eldokan-admin') . '</a>';
                echo '</form>';
            } else {
                echo '<span class="eda-read-only"><span class="dashicons dashicons-lock"></span>' . esc_html__('Read only', 'eldokan-admin') . '</span>';
            }
            echo '</td></tr>';
        }
        echo '</table></div>';

        if ($total_pages > 1) {
            $base_args = [
                'page' => 'eldokan-admin-orders',
                'seller_id' => $selected_seller,
                's' => $order_search,
                'status' => $selected_status,
                'date_from' => $date_from,
                'date_to' => $date_to,
            ];
            echo '<div style="display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:16px;flex-wrap:wrap">';
            if ($order_page > 1) {
                echo '<a class="button" href="'.esc_url(add_query_arg(array_merge($base_args,['order_page'=>$order_page-1]), admin_url('admin.php'))).'">&laquo; Previous</a>';
            }
            echo '<span style="padding:6px 10px">' . esc_html__('Page', 'eldokan-admin') . ' '.absint($order_page).' of '.absint($total_pages).'</span>';
            if ($order_page < $total_pages) {
                echo '<a class="button" href="'.esc_url(add_query_arg(array_merge($base_args,['order_page'=>$order_page+1]), admin_url('admin.php'))).'">Next &raquo;</a>';
            }
            echo '</div>';
        }

        echo '</div>';
        $this->footer();
    }


    public function update_order_status() {
        if (!current_user_can('eldokan_backoffice_manage_orders')) wp_die('Not allowed');

        $order_id = absint($_POST['order_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        if (!$order_id || $status === '') wp_die('Invalid order status request.');

        check_admin_referer('eda_order_status_'.$order_id);

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order || !is_a($order, 'WC_Order') || is_a($order, 'WC_Order_Refund')) wp_die('Order not found.');

        $allowed = array_keys($this->eldokan_official_order_statuses());
        if (!in_array($status, $allowed, true)) wp_die('Unsupported ElDokan order workflow status.');

        $old_status = $order->get_status();
        $original_order_id = (int)$order->get_id();

        if ($old_status !== $status) {
            $order->update_status($status, 'Updated from ElDokan Admin Control Center.', true);
        }

        $verified_order = wc_get_order($original_order_id);
        if (!$verified_order || (int)$verified_order->get_id() !== $original_order_id) {
            wp_die('Order status update verification failed. The original order was not found after the update.');
        }

        // The existing Seller API listens to woocommerce_order_status_changed,
        // so this single WooCommerce update is automatically mirrored to Seller Dashboard.
        $this->write_audit_log(
            'admin_order_status_changed',
            'Order #'.$order->get_order_number().' status changed from '.$old_status.' to '.$status,
            [
                'source'=>'admin',
                'actor_user_id'=>get_current_user_id(),
                'object_type'=>'order',
                'object_id'=>$order_id,
                'meta'=>['old_status'=>$old_status,'new_status'=>$status],
            ]
        );

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=eldokan-admin-orders');
        wp_safe_redirect(add_query_arg('order_updated', 1, $redirect));
        exit;
    }

    public function categories() {
        if (!current_user_can('eldokan_backoffice_view_categories')) wp_die('Not allowed');
        $this->header(
            'Categories & Fees',
            'Manage marketplace fees directly on each WooCommerce category. Saved fees apply immediately to products in that category.'
        );

        if (!empty($_GET['commission_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Category fee saved successfully and is now active for products in this category.', 'eldokan-admin') . '</p></div>';
        }
        if (!empty($_GET['fee_cleared'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Category fee cleared. Products now use the next available product/seller/global fee rule.', 'eldokan-admin') . '</p></div>';
        }

        if (!empty($_GET['category_attributes']) && is_scalar($_GET['category_attributes'])) {
            ElDokan_Admin_Category_Attributes::panel(absint($_GET['category_attributes']));
            $this->footer();
            return;
        }

        $terms = get_terms([
            'taxonomy'=>'product_cat',
            'hide_empty'=>false,
        ]);

        echo '<div class="eda-card"><div class="eda-table-scroll"><table class="eda-table eda-commission-table">';
        echo '<tr><th>' . esc_html__('Category', 'eldokan-admin') . '</th><th>' . esc_html__('Products', 'eldokan-admin') . '</th><th>' . esc_html__('Current Fee', 'eldokan-admin') . '</th><th>' . esc_html__('Fee Control', 'eldokan-admin') . '</th><th></th></tr>';

        foreach ((array)$terms as $t) {
            $rule = $this->global_category_rule($t->term_id);

            $products_url = add_query_arg(
                [
                    'page'=>'eldokan-admin-products',
                    'category_id'=>$t->term_id,
                ],
                admin_url('admin.php')
            );

            echo '<tr>';
            echo '<td><a href="'.esc_url($products_url).'"><strong>'.esc_html($t->name).'</strong></a><br><small>#'.absint($t->term_id).'</small></td>';
            echo '<td><a href="'.esc_url($products_url).'">'.absint($t->count).' product(s)</a></td>';
            echo '<td><strong>'.esc_html($this->commission_rule_value_label($rule)).'</strong><br><small>'.esc_html($rule ? $this->commission_type_label($rule->calculation_type) : 'No category fee').'</small></td>';
            echo '<td>'.$this->commission_inline_form('category', $t->term_id, $rule).'</td>';
            $attributes_url = add_query_arg(['page'=>'eldokan-admin-categories', 'category_attributes'=>(int)$t->term_id], admin_url('admin.php'));
            echo '<td class="eda-row-actions"><a class="button button-primary" href="'.esc_url($products_url).'">' . esc_html__('View Products', 'eldokan-admin') . '</a>';
            if (current_user_can('eldokan_backoffice_manage_categories')) {
                echo '<a class="button" href="'.esc_url($attributes_url).'">' . esc_html__('Attributes', 'eldokan-admin') . '</a>';
            } else {
                echo '<span class="eda-read-only"><span class="dashicons dashicons-lock"></span>' . esc_html__('View only', 'eldokan-admin') . '</span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</table></div></div>';
        echo $this->inline_commission_script();
        $this->footer();
    }


    private function global_product_rule($product_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->commission_table}
                 WHERE enabled=1
                   AND scope='global'
                   AND target_type='product'
                   AND target_id=%d
                 ORDER BY id DESC
                 LIMIT 1",
                absint($product_id)
            )
        );
    }



    private function commission_inline_form($context, $object_id, $rule=null, $view=[]) {
        $context = $context === 'product' ? 'product' : 'category';
        $object_id = absint($object_id);

        if (!current_user_can('eldokan_backoffice_manage_commissions')) {
            return '<span class="eda-read-only"><span class="dashicons dashicons-lock"></span>' . esc_html__('Read only', 'eldokan-admin') . '</span>';
        }

        $type = $rule ? (string)$rule->calculation_type : 'percentage';
        $value = $rule ? (float)$rule->value : 0;
        $fixed = $rule && isset($rule->fixed_value) ? (float)$rule->fixed_value : 0;

        $action = $context === 'product'
            ? 'eldokan_admin_save_product_commission'
            : 'eldokan_admin_save_category_commission';

        ob_start();

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="eda-inline-commission-form">';
        echo wp_nonce_field('eda_'.$context.'_commission_'.$object_id, '_wpnonce', true, false);
        echo '<input type="hidden" name="action" value="'.esc_attr($action).'">';
        echo '<input type="hidden" name="object_id" value="'.$object_id.'">';

        foreach ($view as $key=>$value) {
            echo '<input type="hidden" name="return_'.esc_attr($key).'" value="'.esc_attr($value).'">';
        }
        echo '<select name="calculation_type" class="eda-inline-commission-type" aria-label="'.esc_attr__('Fee calculation', 'eldokan-admin').'">';
        echo '<option value="percentage" '.selected($type,'percentage',false).'>' . esc_html__('Percentage %', 'eldokan-admin') . '</option>';
        echo '<option value="fixed" '.selected($type,'fixed',false).'>' . esc_html__('Fixed EGP', 'eldokan-admin') . '</option>';
        echo '<option value="hybrid" '.selected($type,'hybrid',false).'>' . esc_html__('Percentage % + Fixed EGP', 'eldokan-admin') . '</option>';
        echo '</select>';

        echo '<div class="eda-inline-commission-values">';
        echo '<label><span class="eda-inline-primary-label">'.esc_html($type==='fixed'?'Fixed EGP':'Percentage %').'</span>';
        echo '<input type="number" min="0" '.(($type==='percentage'||$type==='hybrid')?'max="100"':'').' step="0.01" name="value" value="'.esc_attr($value).'" required></label>';

        echo '<label class="eda-inline-fixed-wrap" style="'.($type==='hybrid'?'':'display:none').'"><span>' . esc_html__('Fixed Add-on EGP', 'eldokan-admin') . '</span>';
        echo '<input type="number" min="0" step="0.01" name="fixed_value" value="'.esc_attr($fixed).'"></label>';
        echo '</div>';

        echo '<button class="button button-primary button-small">' . esc_html__('Save', 'eldokan-admin') . '</button>';

        if ($context === 'category' && $rule) {
            $clear = wp_nonce_url(
                admin_url('admin-post.php?action=eldokan_admin_clear_category_fee&category_id='.$object_id),
                'eda_clear_category_fee_'.$object_id
            );
            echo '<a class="button button-small eda-danger-button" href="'.esc_url($clear).'" onclick="return confirm(\'Clear this category fee? Products will fall back to the next available fee rule.\')">' . esc_html__('Clear Fee', 'eldokan-admin') . '</a>';
        } elseif ($rule && absint($rule->id ?? 0)) {
            $url_view = $view;
            if (isset($url_view['s'])) $url_view['s'] = rawurlencode($url_view['s']);
            $del = wp_nonce_url(
                add_query_arg(array_merge(['action'=>'eldokan_admin_delete_commission','id'=>absint($rule->id),'return_product'=>$context === 'product' ? $object_id : 0], array_combine(array_map(static function($key) { return 'return_'.$key; }, array_keys($url_view)), array_values($url_view))), admin_url('admin-post.php')),
                'eda_del_'.absint($rule->id)
            );
            $disable = add_query_arg('disable', 1, $del);
            echo '<a class="button button-small" href="'.esc_url($disable).'">'.esc_html__('Disable override', 'eldokan-admin').'</a> ';
            echo '<a class="button button-small eda-danger-button" href="'.esc_url($del).'" onclick="return confirm(\'Delete this commission rule?\')">' . esc_html__('Delete', 'eldokan-admin') . '</a>';
        }

        echo '</form>';

        return ob_get_clean();
    }

    private function inline_commission_script() {
        return '<script>
        document.addEventListener("DOMContentLoaded",function(){
          document.querySelectorAll(".eda-inline-commission-form").forEach(function(form){
            const type=form.querySelector(".eda-inline-commission-type");
            const label=form.querySelector(".eda-inline-primary-label");
            const value=form.querySelector("input[name=\\"value\\"]");
            const fixedWrap=form.querySelector(".eda-inline-fixed-wrap");
            const fixed=form.querySelector("input[name=\\"fixed_value\\"]");
            if(!type||!label||!value||!fixedWrap||!fixed)return;

            function sync(){
              if(type.value==="percentage"){
                label.textContent="Percentage %";
                value.setAttribute("max","100");
                fixedWrap.style.display="none";
                fixed.disabled=true;
              }else if(type.value==="fixed"){
                label.textContent="Fixed EGP";
                value.removeAttribute("max");
                fixedWrap.style.display="none";
                fixed.disabled=true;
              }else{
                label.textContent="Percentage %";
                value.setAttribute("max","100");
                fixedWrap.style.display="grid";
                fixed.disabled=false;
              }
            }

            type.addEventListener("change",sync);
            sync();
          });
        });
        </script>';
    }


    private function global_category_rule($term_id) {
        global $wpdb;
        $term_id = absint($term_id);

        $meta_rule = $this->category_fee_rule_from_meta($term_id);
        if ($meta_rule) return $meta_rule;

        // Backward-compatible fallback for category rules created before v1.6.15.
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->commission_table}
             WHERE scope='global' AND target_type='category' AND target_id=%d AND enabled=1
             ORDER BY id DESC LIMIT 1",
            $term_id
        ));
    }



    private function access_permission_catalog() {
        return [
            'view_home' => 'View Home',
            'view_products' => 'View Products',
            'create_products' => 'Create Products',
            'edit_products' => 'Edit Products',
            'quick_edit_products' => 'Quick Edit Products',
            'view_sale_price' => 'View Sale Price',
            'edit_sale_price' => 'Edit Sale Price',
            'edit_sale_dates' => 'Edit Sale Start / End',
            'view_orders' => 'View Orders',
            'accept_orders' => 'Accept Orders',
            'reject_orders' => 'Reject Orders',
            'view_reports' => 'View Reports',
            'view_sales' => 'View Sales',
            'view_commission' => 'View Commission',
            'view_statement' => 'View Statement',
            'view_accounts' => 'View Accounts',
            'manage_accounts' => 'Manage Accounts',
            'view_notifications' => 'View Notifications',
            'view_settings' => 'View Settings',
        ];
    }

    private function builtin_access_role_presets() {
        $all = array_fill_keys(array_keys($this->access_permission_catalog()), 1);
        return [
            'owner' => ['name'=>'Owner', 'permissions'=>$all],
            'admin' => ['name'=>'Admin', 'permissions'=>array_merge($all, ['view_settings'=>0])],
            'operator' => ['name'=>'Operator', 'permissions'=>[
                'view_home'=>1,'view_products'=>1,'create_products'=>1,'edit_products'=>1,
                'quick_edit_products'=>1,'view_sale_price'=>1,'edit_sale_price'=>1,'edit_sale_dates'=>1,
                'view_orders'=>1,'accept_orders'=>1,'reject_orders'=>1,'view_reports'=>0,'view_sales'=>1,
                'view_commission'=>0,'view_statement'=>0,'view_accounts'=>0,'manage_accounts'=>0,
                'view_notifications'=>1,'view_settings'=>0,
            ]],
            'finance' => ['name'=>'Finance', 'permissions'=>[
                'view_home'=>1,'view_products'=>0,'create_products'=>0,'edit_products'=>0,
                'quick_edit_products'=>0,'view_sale_price'=>1,'edit_sale_price'=>0,'edit_sale_dates'=>0,
                'view_orders'=>1,'accept_orders'=>0,'reject_orders'=>0,'view_reports'=>1,'view_sales'=>1,
                'view_commission'=>1,'view_statement'=>1,'view_accounts'=>0,'manage_accounts'=>0,
                'view_notifications'=>1,'view_settings'=>0,
            ]],
            'viewer' => ['name'=>'Viewer', 'permissions'=>[
                'view_home'=>1,'view_products'=>1,'create_products'=>0,'edit_products'=>0,
                'quick_edit_products'=>0,'view_sale_price'=>1,'edit_sale_price'=>0,'edit_sale_dates'=>0,
                'view_orders'=>1,'accept_orders'=>0,'reject_orders'=>0,'view_reports'=>1,'view_sales'=>1,
                'view_commission'=>0,'view_statement'=>0,'view_accounts'=>0,'manage_accounts'=>0,
                'view_notifications'=>1,'view_settings'=>0,
            ]],
        ];
    }

    public function ensure_builtin_access_roles() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        if (!$this->table_exists($this->access_roles_table)) return;
        foreach ($this->builtin_access_role_presets() as $slug => $preset) {
            $role_slug = 'eldokan-' . $slug;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->access_roles_table} WHERE slug=%s LIMIT 1", $role_slug
            ));
            if ($exists) continue;
            $permissions = [];
            foreach ($this->access_permission_catalog() as $key => $label) {
                $permissions[$key] = !empty($preset['permissions'][$key]) ? 1 : 0;
            }
            $wpdb->insert($this->access_roles_table, [
                'name'=>$preset['name'], 'slug'=>$role_slug,
                'permissions'=>wp_json_encode($permissions), 'created_by'=>get_current_user_id(),
                'created_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql'),
            ]);
        }
    }

    private function full_access_permissions() {
        $out = [];
        foreach ($this->access_permission_catalog() as $key => $label) {
            $out[$key] = 1;
        }
        return $out;
    }

    private function access_role_by_id($role_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->access_roles_table} WHERE id=%d LIMIT 1",
                absint($role_id)
            ),
            ARRAY_A
        );
    }

    private function seller_access_role_id($seller_id) {
        return absint(
            get_user_meta(
                absint($seller_id),
                '_eldokan_access_role_id',
                true
            )
        );
    }

    private function seller_access_permissions($seller_id) {
        $seller_id = absint($seller_id);
        $role_id = $this->seller_access_role_id($seller_id);

        if (!$role_id) {
            // Backward-compatible: sellers without a custom role keep full access.
            return $this->full_access_permissions();
        }

        $role = $this->access_role_by_id($role_id);

        if (!$role) {
            return $this->full_access_permissions();
        }

        $permissions = json_decode(
            (string) $role['permissions'],
            true
        );

        if (!is_array($permissions)) {
            return $this->full_access_permissions();
        }

        $final = [];

        foreach ($this->access_permission_catalog() as $key => $label) {
            $final[$key] = !empty($permissions[$key]) ? 1 : 0;
        }

        return $final;
    }

    public function access_roles() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        global $wpdb;

        $this->header(
            'Seller Role Permissions',
            'Create and configure ElDokan role templates here. Assign each role directly inside the seller account page.'
        );

        $roles = $wpdb->get_results(
            "SELECT * FROM {$this->access_roles_table} ORDER BY name ASC",
            ARRAY_A
        );

        echo '<div class="eda-two">';

        echo '<div class="eda-card">';
        echo '<h2>' . esc_html__('Create Access Role', 'eldokan-admin') . '</h2>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo wp_nonce_field('eda_save_access_role','_wpnonce',true,false);
        echo '<input type="hidden" name="action" value="eldokan_admin_save_access_role">';
        echo '<p><label><b>' . esc_html__('Role Name', 'eldokan-admin') . '</b><br><input type="text" name="name" required placeholder="Example: Orders Only" style="width:100%"></label></p>';
        echo '<div class="eda-grid">';

        foreach ($this->access_permission_catalog() as $key => $label) {
            echo '<label class="eda-mini">';
            echo '<input type="checkbox" name="permissions['.esc_attr($key).']" value="1"> ';
            echo '<b>'.esc_html(translate($label, 'eldokan-admin')).'</b>';
            echo '</label>';
        }

        echo '</div>';
        echo '<button class="button button-primary">' . esc_html__('Create Role', 'eldokan-admin') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<div class="eda-card">';
        echo '<h2>' . esc_html__('Existing Roles', 'eldokan-admin') . '</h2>';
        echo '<table class="eda-table">';
        echo '<tr><th>' . esc_html__('Role', 'eldokan-admin') . '</th><th>' . esc_html__('Permissions', 'eldokan-admin') . '</th><th>' . esc_html__('Sellers', 'eldokan-admin') . '</th><th></th></tr>';

        foreach ($roles as $role) {
            $permissions = json_decode($role['permissions'], true);
            $enabled = is_array($permissions)
                ? count(array_filter($permissions))
                : 0;

            $seller_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->usermeta}
                     WHERE meta_key='_eldokan_access_role_id'
                       AND meta_value=%s",
                    (string) $role['id']
                )
            );

            $delete = wp_nonce_url(
                admin_url(
                    'admin-post.php?action=eldokan_admin_delete_access_role&id=' .
                    absint($role['id'])
                ),
                'eda_delete_access_role_' . absint($role['id'])
            );

            echo '<tr>';
            echo '<td><strong>'.esc_html($role['name']).'</strong><br><small>'.esc_html($role['slug']).'</small></td>';
            echo '<td>'.absint($enabled).' enabled</td>';
            echo '<td>'.absint($seller_count).'</td>';
            if (strpos((string)$role['slug'], 'eldokan-') === 0) {
                echo '<td><span class="eda-badge">' . esc_html__('Built-in', 'eldokan-admin') . '</span></td>';
            } else {
                echo '<td><a class="button" href="'.esc_url($delete).'">' . esc_html__('Delete', 'eldokan-admin') . '</a></td>';
            }
            echo '</tr>';
        }

        if (!$roles) {
            echo '<tr><td colspan="4">' . esc_html__('No custom roles yet. Sellers without a role keep full access.', 'eldokan-admin') . '</td></tr>';
        }

        echo '</table>';
        echo '</div>';

        echo '</div>';

        echo '<div class="eda-card">';
        echo '<h2>' . esc_html__('Where to Assign Roles', 'eldokan-admin') . '</h2>';
        echo '<p>' . esc_html__('Configure role permissions here. Assign each role from', 'eldokan-admin') . ' <b>' . esc_html__('ElDokan Admin → Sellers → Manage Seller → Seller Accounts & Roles', 'eldokan-admin') . '</b>.</p>';
        echo '<a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-sellers')).'">' . esc_html__('Open Sellers', 'eldokan-admin') . '</a>';
        echo '</div>';

        $this->footer();
    }

    public function save_access_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('eda_save_access_role');

        global $wpdb;

        $name = sanitize_text_field($_POST['name'] ?? '');

        if ($name === '') {
            wp_die('Role name is required.');
        }

        $slug = sanitize_title($name);

        if ($slug === '') {
            $slug = 'role-' . time();
        }

        $raw = is_array($_POST['permissions'] ?? null)
            ? $_POST['permissions']
            : [];

        $permissions = [];

        foreach ($this->access_permission_catalog() as $key => $label) {
            $permissions[$key] = !empty($raw[$key]) ? 1 : 0;
        }

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->access_roles_table} WHERE slug=%s LIMIT 1",
                $slug
            )
        );

        if ($existing) {
            $slug .= '-' . time();
        }

        $wpdb->insert(
            $this->access_roles_table,
            [
                'name' => $name,
                'slug' => $slug,
                'permissions' => wp_json_encode($permissions),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]
        );

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-access-roles&created=1')
        );
        exit;
    }

    public function delete_access_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $id = absint($_GET['id'] ?? 0);

        check_admin_referer(
            'eda_delete_access_role_' . $id
        );

        global $wpdb;

        $role = $this->access_role_by_id($id);
        if ($role && strpos((string)$role['slug'], 'eldokan-') === 0) {
            wp_die('Built-in ElDokan roles cannot be deleted.');
        }

        // Sellers assigned to a deleted role automatically return to Full Access.
        $wpdb->delete(
            $wpdb->usermeta,
            [
                'meta_key' => '_eldokan_access_role_id',
                'meta_value' => (string) $id,
            ]
        );

        $wpdb->delete(
            $this->access_roles_table,
            ['id' => $id]
        );

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-access-roles&deleted=1')
        );
        exit;
    }

    public function update_seller_account_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $account_id = absint($_POST['account_id'] ?? 0);
        $role_id = absint($_POST['role_id'] ?? 0);

        check_admin_referer('eda_account_role_'.$seller_id.'_'.$account_id);

        if (!$seller_id || !$account_id) {
            wp_die('Invalid seller account.');
        }

        $seller = get_userdata($seller_id);
        $account = get_userdata($account_id);

        if (!$seller || !$account) {
            wp_die('Seller account not found.');
        }

        if ($this->owner_id($account_id) !== $seller_id) {
            wp_die('This account does not belong to the selected seller.');
        }

        if ($role_id) {
            $role = $this->access_role_by_id($role_id);
            if (!$role) {
                wp_die('Access role not found.');
            }

            update_user_meta($account_id, '_eldokan_access_role_id', $role_id);
        } else {
            delete_user_meta($account_id, '_eldokan_access_role_id');
        }

        update_user_meta(
            $account_id,
            '_eldokan_access_role_updated_at',
            current_time('mysql')
        );

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller_id.'&role_saved=1')
        );
        exit;
    }

    public function update_wcfm_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $account_id = absint($_POST['account_id'] ?? 0);
        $wcfm_role = sanitize_key($_POST['wcfm_role'] ?? '');

        check_admin_referer('eda_wcfm_role_'.$seller_id.'_'.$account_id);

        if (!$seller_id || !$account_id) {
            wp_die('Invalid seller account.');
        }

        if ($this->owner_id($account_id) !== $seller_id) {
            wp_die('This account does not belong to the selected seller.');
        }

        $result = $this->replace_user_wcfm_role($account_id, $wcfm_role);
        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller_id.'&wcfm_role_saved=1#seller-accounts')
        );
        exit;
    }

    public function bulk_wcfm_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $wcfm_role = sanitize_key($_POST['wcfm_role'] ?? '');

        check_admin_referer('eda_bulk_wcfm_role_'.$seller_id);

        if (!$seller_id || !$wcfm_role) {
            wp_die('Seller and WCFM role are required.');
        }

        $choices = $this->wcfm_role_choices();
        if (!isset($choices[$wcfm_role])) {
            wp_die('Selected WCFM role is not available.');
        }

        foreach ($this->company_accounts($seller_id) as $account) {
            $result = $this->replace_user_wcfm_role($account->ID, $wcfm_role);
            if (is_wp_error($result)) {
                wp_die(esc_html($result->get_error_message()));
            }
        }

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller_id.'&wcfm_bulk_saved=1#seller-accounts')
        );
        exit;
    }


    public function delete_seller_account() {
        if (!current_user_can('eldokan_backoffice_delete_accounts')) {
            wp_die('Not allowed');
        }

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $account_id = absint($_POST['account_id'] ?? 0);

        check_admin_referer('eda_delete_seller_account_'.$seller_id.'_'.$account_id);

        if (!$seller_id || !$account_id) {
            wp_die('Invalid seller account.');
        }

        if ($account_id === $seller_id) {
            wp_die('The primary seller account is protected and cannot be deleted from this screen.');
        }

        if ($account_id === get_current_user_id()) {
            wp_die('You cannot delete the account you are currently using.');
        }

        $seller = get_userdata($seller_id);
        $account = get_userdata($account_id);

        if (!$seller || !$account) {
            wp_die('Seller account not found.');
        }

        if ($this->owner_id($account_id) !== $seller_id) {
            wp_die('This account does not belong to the selected seller.');
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        // Keep seller content safe: reassign any WordPress-owned content
        // from the deleted team account to the primary seller.
        $deleted = wp_delete_user($account_id, $seller_id);

        if (!$deleted) {
            wp_die('Could not delete seller account.');
        }

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller_id.'&account_deleted=1')
        );
        exit;
    }


    public function assign_access_role() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        check_admin_referer('eda_assign_access_role');

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $role_id = absint($_POST['role_id'] ?? 0);

        if (!$seller_id) {
            wp_die('Select a seller.');
        }

        if ($role_id) {
            $role = $this->access_role_by_id($role_id);

            if (!$role) {
                wp_die('Access role not found.');
            }

            update_user_meta(
                $seller_id,
                '_eldokan_access_role_id',
                $role_id
            );
        } else {
            delete_user_meta(
                $seller_id,
                '_eldokan_access_role_id'
            );
        }

        // Lightweight cache marker for React/API clients.
        update_user_meta(
            $seller_id,
            '_eldokan_access_role_updated_at',
            current_time('mysql')
        );

        wp_safe_redirect(
            admin_url('admin.php?page=eldokan-admin-access-roles&assigned=1')
        );
        exit;
    }


    private function wcfm_migration_state() {
        $state = get_option('eldokan_wcfm_migration_state', []);
        return is_array($state) ? $state : [];
    }

    private function wcfm_migration_roles() {
        $wp_roles = wp_roles();
        if (!$wp_roles || !is_array($wp_roles->roles)) {
            return [];
        }

        $preferred = [
            'wcfm_vendor',
            'vendor',
            'seller',
        ];

        $roles = [];

        foreach ($preferred as $slug) {
            if (isset($wp_roles->roles[$slug])) {
                $roles[] = $slug;
            }
        }

        // Detect custom WCFM/vendor seller roles without pulling ElDokan roles.
        foreach ($wp_roles->roles as $slug => $data) {
            if (strpos($slug, 'eldokan_') === 0) {
                continue;
            }

            $name = strtolower((string)($data['name'] ?? ''));
            $haystack = strtolower($slug . ' ' . $name);

            if (
                strpos($haystack, 'wcfm vendor') !== false ||
                strpos($haystack, 'wcfm_vendor') !== false ||
                $slug === 'wcfm_vendor'
            ) {
                $roles[] = $slug;
            }
        }

        return array_values(array_unique($roles));
    }

    private function wcfm_migration_vendor_query($offset=0, $limit=1) {
        $roles = $this->wcfm_migration_roles();

        if (!$roles) {
            return new WP_User_Query([
                'include' => [0],
                'number' => 1,
                'count_total' => true,
            ]);
        }

        return new WP_User_Query([
            'role__in' => $roles,
            'number' => max(1, min(10, absint($limit))),
            'offset' => max(0, absint($offset)),
            'orderby' => 'ID',
            'order' => 'ASC',
            'count_total' => true,
        ]);
    }

    private function wcfm_migration_company_name($user_id) {
        $company = trim((string)get_user_meta($user_id, 'billing_company', true));

        if ($company !== '') {
            return $company;
        }

        foreach ([
            'wcfmmp_profile_settings',
            '_wcfmmp_profile_settings',
        ] as $key) {
            $settings = get_user_meta($user_id, $key, true);

            if (!is_array($settings)) {
                continue;
            }

            foreach ([
                'store_name',
                'store-name',
                'store',
                'shop_name',
            ] as $name_key) {
                $candidate = trim((string)($settings[$name_key] ?? ''));

                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $direct = trim((string)get_user_meta($user_id, 'wcfmmp_store_name', true));
        if ($direct !== '') {
            return $direct;
        }

        $user = get_userdata($user_id);
        return $user ? ($user->display_name ?: $user->user_login) : '';
    }

    private function wcfm_source_product_ids($vendor_id) {
        $vendor_id = absint($vendor_id);

        if (!$vendor_id) {
            return [];
        }

        $ids = [];

        // Most WCFM products: post_author is the vendor user.
        $ids = array_merge($ids, get_posts([
            'post_type' => 'product',
            'post_status' => ['publish','pending','draft','private'],
            'author' => $vendor_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]));

        // Read common marketplace ownership metadata without changing it.
        foreach (['_wcfm_vendor_id','vendor_id','_vendor_id'] as $meta_key) {
            $ids = array_merge($ids, get_posts([
                'post_type' => 'product',
                'post_status' => ['publish','pending','draft','private'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [[
                    'key' => $meta_key,
                    'value' => $vendor_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ]],
            ]));
        }

        // If WCFM is active, its own resolver is used READ-ONLY.
        if (function_exists('wcfm_get_vendor_id_by_post')) {
            $all = get_posts([
                'post_type' => 'product',
                'post_status' => ['publish','pending','draft','private'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]);

            foreach ((array)$all as $product_id) {
                if (
                    absint(wcfm_get_vendor_id_by_post(absint($product_id)))
                    === $vendor_id
                ) {
                    $ids[] = absint($product_id);
                }
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map('absint', $ids)
                )
            )
        );
    }

    private function add_eldokan_owner_role_preserve_existing($user) {
        if (!$user instanceof WP_User) {
            return new WP_Error('invalid_user', 'Invalid seller user.');
        }

        if (!get_role('eldokan_owner')) {
            return new WP_Error(
                'eldokan_role_missing',
                'ElDokan Owner WordPress role is missing. Activate/update ElDokan Dashboard API first.'
            );
        }

        // Never call set_role(): that would replace WCFM/vendor roles.
        if (!in_array('eldokan_owner', (array)$user->roles, true)) {
            $user->add_role('eldokan_owner');
        }

        // Remove only ElDokan pending; all WCFM/external roles remain untouched.
        if (in_array('eldokan_pending', (array)$user->roles, true)) {
            $user->remove_role('eldokan_pending');
        }

        return true;
    }

    private function migrate_wcfm_order_item_links($vendor_id, $product_ids) {
        if (!function_exists('wc_get_order')) {
            return 0;
        }

        global $wpdb;
        $vendor_id = absint($vendor_id);
        $product_lookup = array_fill_keys(array_map('absint', (array)$product_ids), true);
        $linked_order_items = 0;

        // Preferred: use the WCFM marketplace split table while it still exists.
        $table = $wpdb->prefix . 'wcfm_marketplace_orders';

        if ($this->table_exists($table)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT order_id,item_id,product_id FROM `{$table}` WHERE vendor_id=%d ORDER BY ID ASC",
                    $vendor_id
                ),
                ARRAY_A
            );

            foreach ((array)$rows as $row) {
                $order_id = absint($row['order_id'] ?? 0);
                $item_id = absint($row['item_id'] ?? 0);
                $product_id = absint($row['product_id'] ?? 0);

                if (!$order_id || !$item_id) {
                    continue;
                }

                $order = wc_get_order($order_id);
                if (!$order) {
                    continue;
                }

                $item = $order->get_item($item_id);
                if (!$item) {
                    continue;
                }

                $item->update_meta_data('_eldokan_seller_user_id', $vendor_id);
                $item->update_meta_data('_eldokan_seller_owner', $vendor_id);
                $item->update_meta_data('_eldokan_legacy_source', 'wcfm');
                $item->save();

                $linked_order_items++;
            }

            return $linked_order_items;
        }

        // Fallback: link WooCommerce historical order lines by migrated products.
        if (!$product_lookup) {
            return 0;
        }

        foreach ($this->all_orders(500) as $order) {
            foreach ($order->get_items('line_item') as $item) {
                $product_id = absint($item->get_product_id());

                if (!$product_id || !isset($product_lookup[$product_id])) {
                    continue;
                }

                $item->update_meta_data('_eldokan_seller_user_id', $vendor_id);
                $item->update_meta_data('_eldokan_seller_owner', $vendor_id);
                $item->update_meta_data('_eldokan_legacy_source', 'woocommerce_product_owner');
                $item->save();

                $linked_order_items++;
            }
        }

        return $linked_order_items;
    }

    private function migrate_wcfm_vendor_to_eldokan($user) {
        if (!$user instanceof WP_User) {
            return [
                'error' => true,
                'message' => 'Invalid WCFM seller.',
            ];
        }

        $uid = absint($user->ID);

        $role_result = $this->add_eldokan_owner_role_preserve_existing($user);

        if (is_wp_error($role_result)) {
            return [
                'error' => true,
                'message' => $role_result->get_error_message(),
            ];
        }

        $company = $this->wcfm_migration_company_name($uid);

        // Copy company data into ElDokan/WooCommerce-compatible fields only when
        // those fields are empty. No WCFM user meta is removed or rewritten.
        if (
            trim((string)get_user_meta($uid, 'billing_company', true)) === ''
            && $company !== ''
        ) {
            update_user_meta($uid, 'billing_company', $company);
        }

        update_user_meta($uid, '_eldokan_company_name', $company);
        update_user_meta($uid, '_eldokan_account_status', 'approved');
        update_user_meta($uid, '_eldokan_migrated_from_wcfm', 1);
        update_user_meta($uid, '_eldokan_legacy_account_source', 'wcfm');
        update_user_meta($uid, '_eldokan_migrated_at', current_time('mysql'));

        $product_ids = $this->wcfm_source_product_ids($uid);
        $migrated_products = 0;

        foreach ($product_ids as $product_id) {
            // COPY ownership into ElDokan metadata.
            // post_author and every WCFM field remain unchanged.
            update_post_meta($product_id, '_eldokan_seller_user_id', $uid);
            update_post_meta($product_id, '_eldokan_seller_owner', $uid);
            update_post_meta($product_id, '_eldokan_owner_group', $uid);

            if ($company !== '') {
                update_post_meta($product_id, '_eldokan_company_name', $company);
            }

            update_post_meta($product_id, '_eldokan_legacy_source', 'wcfm');
            update_post_meta($product_id, '_eldokan_legacy_source_user_id', $uid);
            update_post_meta($product_id, '_eldokan_legacy_linked_at', current_time('mysql'));

            $migrated_products++;
        }

        $linked_order_items = $this->migrate_wcfm_order_item_links(
            $uid,
            $product_ids
        );

        update_user_meta(
            $uid,
            '_eldokan_legacy_products_linked',
            $migrated_products
        );
        update_user_meta(
            $uid,
            '_eldokan_legacy_order_items_linked',
            $linked_order_items
        );

        // Refresh the existing ElDokan index as a reporting/cache layer.
        $indexed = $this->wcfm_index_vendor($user);

        return [
            'seller' => $uid,
            'company' => $company,
            'products' => $migrated_products,
            'order_items' => $linked_order_items,
            'indexed_orders' => is_array($indexed) && empty($indexed['error'])
                ? absint($indexed['orders'] ?? 0)
                : 0,
        ];
    }

    private function wcfm_migration_verification_data($include_details=false) {
        global $wpdb;

        $index_sellers = $this->wcfm_vendor_table;
        $index_products = $this->wcfm_product_table;
        $index_orders = $this->wcfm_order_table;

        $index_ready =
            $this->table_exists($index_sellers) &&
            $this->table_exists($index_products) &&
            $this->table_exists($index_orders);

        if (!$index_ready) {
            return [
                'index_ready' => false,
                'source_sellers' => 0,
                'migrated_sellers' => 0,
                'source_products' => 0,
                'current_products' => 0,
                'historical_products' => 0,
                'mapped_products' => 0,
                'source_orders' => 0,
                'current_orders' => 0,
                'historical_orders' => 0,
                'linked_orders' => 0,
                'unmapped_sellers' => 0,
                'unmapped_products' => 0,
                'unlinked_orders' => 0,
                'ready' => false,
                'details' => [],
                'message' => 'WCFM Historical Data Index is not ready. Build/rebuild the index first.',
            ];
        }

        $source_sellers = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT seller_user_id) FROM {$index_sellers}"
        );

        $migrated_sellers = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT s.seller_user_id)
             FROM {$index_sellers} s
             INNER JOIN {$wpdb->usermeta} um
               ON um.user_id=s.seller_user_id
              AND um.meta_key='_eldokan_migrated_from_wcfm'
              AND um.meta_value='1'"
        );

        // Total indexed products includes historical/deleted products.
        $source_products = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT product_id) FROM {$index_products}"
        );

        // Only products that still exist as WooCommerce products must have a
        // live permanent ElDokan mapping.
        $current_products = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT p.product_id)
             FROM {$index_products} p
             INNER JOIN {$wpdb->posts} wp
               ON wp.ID=p.product_id
              AND wp.post_type='product'
              AND wp.post_status NOT IN ('trash','auto-draft')"
        );

        $historical_products = max(0, $source_products - $current_products);

        $mapped_products = (int)$wpdb->get_var(
            "SELECT COUNT(DISTINCT p.product_id)
             FROM {$index_products} p
             INNER JOIN {$wpdb->posts} wp
               ON wp.ID=p.product_id
              AND wp.post_type='product'
              AND wp.post_status NOT IN ('trash','auto-draft')
             INNER JOIN {$wpdb->postmeta} pm
               ON pm.post_id=p.product_id
              AND pm.meta_key='_eldokan_seller_user_id'
              AND CAST(pm.meta_value AS UNSIGNED)=p.seller_user_id"
        );

        // Total seller/order pairs in the preserved historical index.
        $source_orders = (int)$wpdb->get_var(
            "SELECT COUNT(*)
             FROM (
                SELECT DISTINCT seller_user_id, order_id
                FROM {$index_orders}
             ) x"
        );

        /*
         * A "current order pair" means the index still points to at least one
         * WooCommerce order item that exists. Historical-only pairs remain
         * preserved in the ElDokan index and should not block WCFM removal.
         */
        $current_orders = (int)$wpdb->get_var(
            "SELECT COUNT(*)
             FROM (
                SELECT DISTINCT idx.seller_user_id, idx.order_id
                FROM {$index_orders} idx
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                  ON oi.order_item_id=idx.order_item_id
                 AND oi.order_id=idx.order_id
                WHERE idx.order_item_id>0
             ) x"
        );

        $historical_orders = max(0, $source_orders - $current_orders);

        $linked_orders = (int)$wpdb->get_var(
            "SELECT COUNT(*)
             FROM (
                SELECT DISTINCT idx.seller_user_id, idx.order_id
                FROM {$index_orders} idx
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                  ON oi.order_item_id=idx.order_item_id
                 AND oi.order_id=idx.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                  ON oim.order_item_id=oi.order_item_id
                 AND oim.meta_key='_eldokan_seller_user_id'
                 AND CAST(oim.meta_value AS UNSIGNED)=idx.seller_user_id
                WHERE idx.order_item_id>0
             ) x"
        );

        $unmapped_sellers = max(0, $source_sellers - $migrated_sellers);
        $unmapped_products = max(0, $current_products - $mapped_products);
        $unlinked_orders = max(0, $current_orders - $linked_orders);

        $ready =
            $source_sellers > 0 &&
            $unmapped_sellers === 0 &&
            $unmapped_products === 0 &&
            $unlinked_orders === 0;

        $details = [];

        if ($include_details) {
            $seller_rows = $wpdb->get_results(
                "SELECT DISTINCT seller_user_id
                 FROM {$index_sellers}
                 ORDER BY seller_user_id ASC"
            );

            foreach ((array)$seller_rows as $row) {
                $seller_id = absint($row->seller_user_id);
                if (!$seller_id) continue;

                $source_product_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT product_id)
                         FROM {$index_products}
                         WHERE seller_user_id=%d",
                        $seller_id
                    )
                );

                $current_product_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.product_id)
                         FROM {$index_products} p
                         INNER JOIN {$wpdb->posts} wp
                           ON wp.ID=p.product_id
                          AND wp.post_type='product'
                          AND wp.post_status NOT IN ('trash','auto-draft')
                         WHERE p.seller_user_id=%d",
                        $seller_id
                    )
                );

                $mapped_product_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT p.product_id)
                         FROM {$index_products} p
                         INNER JOIN {$wpdb->posts} wp
                           ON wp.ID=p.product_id
                          AND wp.post_type='product'
                          AND wp.post_status NOT IN ('trash','auto-draft')
                         INNER JOIN {$wpdb->postmeta} pm
                           ON pm.post_id=p.product_id
                          AND pm.meta_key='_eldokan_seller_user_id'
                          AND CAST(pm.meta_value AS UNSIGNED)=%d
                         WHERE p.seller_user_id=%d",
                        $seller_id,
                        $seller_id
                    )
                );

                $source_order_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT order_id)
                         FROM {$index_orders}
                         WHERE seller_user_id=%d",
                        $seller_id
                    )
                );

                $current_order_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT idx.order_id)
                         FROM {$index_orders} idx
                         INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                           ON oi.order_item_id=idx.order_item_id
                          AND oi.order_id=idx.order_id
                         WHERE idx.seller_user_id=%d
                           AND idx.order_item_id>0",
                        $seller_id
                    )
                );

                $linked_order_count = (int)$wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(DISTINCT idx.order_id)
                         FROM {$index_orders} idx
                         INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                           ON oi.order_item_id=idx.order_item_id
                          AND oi.order_id=idx.order_id
                         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                           ON oim.order_item_id=oi.order_item_id
                          AND oim.meta_key='_eldokan_seller_user_id'
                          AND CAST(oim.meta_value AS UNSIGNED)=%d
                         WHERE idx.seller_user_id=%d
                           AND idx.order_item_id>0",
                        $seller_id,
                        $seller_id
                    )
                );

                $migrated = get_user_meta(
                    $seller_id,
                    '_eldokan_migrated_from_wcfm',
                    true
                ) === '1';

                $missing_products = max(0, $current_product_count - $mapped_product_count);
                $missing_orders = max(0, $current_order_count - $linked_order_count);

                $seller_ok =
                    $migrated &&
                    $missing_products === 0 &&
                    $missing_orders === 0;

                $details[] = [
                    'seller_id' => $seller_id,
                    'seller_name' => $this->company_name($seller_id),
                    'migrated' => $migrated,
                    'source_products' => $source_product_count,
                    'current_products' => $current_product_count,
                    'historical_products' => max(0, $source_product_count - $current_product_count),
                    'mapped_products' => $mapped_product_count,
                    'source_orders' => $source_order_count,
                    'current_orders' => $current_order_count,
                    'historical_orders' => max(0, $source_order_count - $current_order_count),
                    'linked_orders' => $linked_order_count,
                    'missing_products' => $missing_products,
                    'missing_orders' => $missing_orders,
                    'status' => $seller_ok ? 'OK' : 'NOT_READY',
                ];
            }
        }

        return [
            'index_ready' => true,
            'source_sellers' => $source_sellers,
            'migrated_sellers' => $migrated_sellers,
            'source_products' => $source_products,
            'current_products' => $current_products,
            'historical_products' => $historical_products,
            'mapped_products' => $mapped_products,
            'source_orders' => $source_orders,
            'current_orders' => $current_orders,
            'historical_orders' => $historical_orders,
            'linked_orders' => $linked_orders,
            'unmapped_sellers' => $unmapped_sellers,
            'unmapped_products' => $unmapped_products,
            'unlinked_orders' => $unlinked_orders,
            'ready' => $ready,
            'details' => $details,
            'message' => $ready
                ? 'All current sellers, current products and current WooCommerce order items are permanently mapped. Historical-only records remain preserved in the ElDokan index.'
                : 'Current WooCommerce seller/product/order mappings are still missing. Run Repair Missing Links.',
        ];
    }


    private function repair_eldokan_product_mapping_from_index($seller_id, $product_id) {
        $seller_id = absint($seller_id);
        $product_id = absint($product_id);

        if (!$seller_id || !$product_id || get_post_type($product_id) !== 'product') {
            return false;
        }

        $company = $this->company_name($seller_id);

        update_post_meta($product_id, '_eldokan_seller_user_id', $seller_id);
        update_post_meta($product_id, '_eldokan_seller_owner', $seller_id);
        update_post_meta($product_id, '_eldokan_owner_group', $seller_id);

        if ($company !== '') {
            update_post_meta($product_id, '_eldokan_company_name', $company);
        }

        update_post_meta($product_id, '_eldokan_legacy_source', 'wcfm');
        update_post_meta($product_id, '_eldokan_legacy_source_user_id', $seller_id);
        update_post_meta($product_id, '_eldokan_legacy_linked_at', current_time('mysql'));
        update_post_meta($product_id, '_eldokan_repaired_at', current_time('mysql'));

        return true;
    }

    private function repair_eldokan_order_pair_from_index($seller_id, $order_id) {
        $seller_id = absint($seller_id);
        $order_id = absint($order_id);

        if (!$seller_id || !$order_id || !function_exists('wc_get_order')) {
            return 0;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            // Historical-only order: preserved in ElDokan index.
            return 0;
        }

        global $wpdb;
        $linked_items = 0;

        /*
         * Preferred and most reliable source:
         * exact order_item_id already copied into the ElDokan WCFM index.
         */
        $index_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_item_id, product_id
                 FROM {$this->wcfm_order_table}
                 WHERE seller_user_id=%d
                   AND order_id=%d
                   AND order_item_id>0
                 ORDER BY order_item_id ASC",
                $seller_id,
                $order_id
            ),
            ARRAY_A
        );

        foreach ((array)$index_rows as $row) {
            $item_id = absint($row['order_item_id'] ?? 0);
            $product_id = absint($row['product_id'] ?? 0);

            if (!$item_id) continue;

            $item = $order->get_item($item_id);
            if (!$item) continue;

            $item->update_meta_data('_eldokan_seller_user_id', $seller_id);
            $item->update_meta_data('_eldokan_seller_owner', $seller_id);
            $item->update_meta_data('_eldokan_legacy_source', 'eldokan_wcfm_index');
            $item->update_meta_data('_eldokan_repaired_at', current_time('mysql'));
            $item->save();

            if ($product_id && get_post_type($product_id) === 'product') {
                $this->repair_eldokan_product_mapping_from_index(
                    $seller_id,
                    $product_id
                );
            }

            $linked_items++;
        }

        if ($linked_items > 0) {
            return $linked_items;
        }

        /*
         * Secondary source while WCFM is still installed:
         * exact vendor/order/item rows from WCFM marketplace table, READ ONLY.
         */
        $wcfm_orders_table = $wpdb->prefix . 'wcfm_marketplace_orders';

        if ($this->table_exists($wcfm_orders_table)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT item_id, product_id
                     FROM `{$wcfm_orders_table}`
                     WHERE vendor_id=%d AND order_id=%d",
                    $seller_id,
                    $order_id
                ),
                ARRAY_A
            );

            foreach ((array)$rows as $row) {
                $item_id = absint($row['item_id'] ?? 0);
                $product_id = absint($row['product_id'] ?? 0);

                if (!$item_id) continue;

                $item = $order->get_item($item_id);
                if (!$item) continue;

                $item->update_meta_data('_eldokan_seller_user_id', $seller_id);
                $item->update_meta_data('_eldokan_seller_owner', $seller_id);
                $item->update_meta_data('_eldokan_legacy_source', 'wcfm');
                $item->update_meta_data('_eldokan_repaired_at', current_time('mysql'));
                $item->save();

                if ($product_id && get_post_type($product_id) === 'product') {
                    $this->repair_eldokan_product_mapping_from_index(
                        $seller_id,
                        $product_id
                    );
                }

                $linked_items++;
            }
        }

        if ($linked_items > 0) {
            return $linked_items;
        }

        /*
         * Last fallback: match current order lines by product IDs from the
         * ElDokan historical index.
         */
        $product_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT product_id
                 FROM {$this->wcfm_order_table}
                 WHERE seller_user_id=%d
                   AND order_id=%d
                   AND product_id>0",
                $seller_id,
                $order_id
            )
        );

        $product_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array)$product_ids)
                )
            )
        );

        if (!$product_ids) {
            return 0;
        }

        $lookup = array_fill_keys($product_ids, true);

        foreach ($order->get_items('line_item') as $item) {
            $product_id = absint($item->get_product_id());
            $variation_id = absint($item->get_variation_id());

            if (
                (!$product_id || !isset($lookup[$product_id])) &&
                (!$variation_id || !isset($lookup[$variation_id]))
            ) {
                continue;
            }

            $item->update_meta_data('_eldokan_seller_user_id', $seller_id);
            $item->update_meta_data('_eldokan_seller_owner', $seller_id);
            $item->update_meta_data('_eldokan_legacy_source', 'eldokan_index_product_match');
            $item->update_meta_data('_eldokan_repaired_at', current_time('mysql'));
            $item->save();

            if ($product_id && get_post_type($product_id) === 'product') {
                $this->repair_eldokan_product_mapping_from_index(
                    $seller_id,
                    $product_id
                );
            }

            $linked_items++;
        }

        return $linked_items;
    }


    private function repair_wcfm_migration_links($seller_id=0) {
        global $wpdb;

        $seller_id = absint($seller_id);

        if (
            !$this->table_exists($this->wcfm_vendor_table) ||
            !$this->table_exists($this->wcfm_product_table) ||
            !$this->table_exists($this->wcfm_order_table)
        ) {
            return new WP_Error('index_not_ready', 'WCFM Historical Data Index is not ready.');
        }

        $seller_ids = $seller_id
            ? [$seller_id]
            : $wpdb->get_col(
                "SELECT DISTINCT seller_user_id
                 FROM {$this->wcfm_vendor_table}
                 ORDER BY seller_user_id ASC"
            );

        $summary = [
            'sellers_checked' => 0,
            'products_repaired' => 0,
            'orders_repaired' => 0,
            'order_items_repaired' => 0,
            'unresolved' => [],
        ];

        foreach ((array)$seller_ids as $sid) {
            $sid = absint($sid);
            if (!$sid) continue;

            $summary['sellers_checked']++;

            $product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT p.product_id
                     FROM {$this->wcfm_product_table} p
                     INNER JOIN {$wpdb->posts} wp
                       ON wp.ID=p.product_id
                      AND wp.post_type='product'
                      AND wp.post_status NOT IN ('trash','auto-draft')
                     LEFT JOIN {$wpdb->postmeta} pm
                       ON pm.post_id=p.product_id
                      AND pm.meta_key='_eldokan_seller_user_id'
                      AND CAST(pm.meta_value AS UNSIGNED)=p.seller_user_id
                     WHERE p.seller_user_id=%d
                       AND pm.post_id IS NULL",
                    $sid
                )
            );

            foreach ((array)$product_ids as $product_id) {
                if ($this->repair_eldokan_product_mapping_from_index($sid, $product_id)) {
                    $summary['products_repaired']++;
                }
            }

            $order_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT idx.order_id
                     FROM {$this->wcfm_order_table} idx
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items current_oi
                       ON current_oi.order_item_id=idx.order_item_id
                      AND current_oi.order_id=idx.order_id
                     WHERE idx.seller_user_id=%d
                       AND idx.order_item_id>0
                       AND NOT EXISTS (
                         SELECT 1
                         FROM {$wpdb->prefix}woocommerce_order_items oi
                         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                           ON oim.order_item_id=oi.order_item_id
                          AND oim.meta_key='_eldokan_seller_user_id'
                          AND CAST(oim.meta_value AS UNSIGNED)=%d
                         WHERE oi.order_id=idx.order_id
                       )",
                    $sid,
                    $sid
                )
            );

            foreach ((array)$order_ids as $order_id) {
                $count = $this->repair_eldokan_order_pair_from_index($sid, $order_id);

                if ($count > 0) {
                    $summary['orders_repaired']++;
                    $summary['order_items_repaired'] += $count;
                } else {
                    $summary['unresolved'][] = [
                        'seller_id' => $sid,
                        'order_id' => absint($order_id),
                    ];
                }
            }
        }

        return $summary;
    }

    public function wcfm_migration_repair() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');
        @set_time_limit(90);

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $result = $this->repair_wcfm_migration_links($seller_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message'=>$result->get_error_message()], 409);
        }

        $verification = $this->wcfm_migration_verification_data(false);

        $this->write_audit_log(
            'wcfm_migration_repair',
            $seller_id
                ? 'Migration links repaired for seller #'.$seller_id.'.'
                : 'Missing WCFM to ElDokan migration links repaired.',
            [
                'source' => 'wcfm_migration',
                'seller_user_id' => $seller_id,
                'object_type' => 'migration',
                'meta' => $result,
            ]
        );

        wp_send_json_success([
            'repair' => $result,
            'verification' => $verification,
            'message' =>
                'Repair completed: '
                .absint($result['products_repaired']).' product mappings, '
                .absint($result['orders_repaired']).' seller/order links, '
                .absint($result['order_items_repaired']).' order items updated, '
                .count($result['unresolved']).' unresolved order(s).',
        ]);
    }


    public function wcfm_migration_scan() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');

        $roles = $this->wcfm_migration_roles();
        $q = $this->wcfm_migration_vendor_query(0, 1);

        wp_send_json_success([
            'sellers' => (int)$q->get_total(),
            'roles' => $roles,
            'eldokanOwnerRoleReady' => (bool)get_role('eldokan_owner'),
            'message' => get_role('eldokan_owner')
                ? 'Ready. Migration is copy/link only; WCFM data will not be deleted.'
                : 'ElDokan Owner role is missing. Activate/update ElDokan Dashboard API first.',
        ]);
    }

    public function wcfm_migration_reset() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');

        // Only migration progress is reset. Already migrated data is intentionally kept.
        delete_option('eldokan_wcfm_migration_state');

        wp_send_json_success([
            'message' => 'Migration progress reset. ElDokan data and WCFM source data remain untouched.',
        ]);
    }

    public function wcfm_migration_batch() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');
        @set_time_limit(25);

        if (!get_role('eldokan_owner')) {
            wp_send_json_error([
                'message'=>'ElDokan Owner role is missing. Activate/update ElDokan Dashboard API first.',
            ], 409);
        }

        $state = $this->wcfm_migration_state();

        if (!$state) {
            $q = $this->wcfm_migration_vendor_query(0, 1);
            $state = [
                'offset' => 0,
                'processed' => 0,
                'total' => (int)$q->get_total(),
                'products' => 0,
                'order_items' => 0,
                'finished' => false,
            ];
        }

        if (!empty($state['finished'])) {
            wp_send_json_success([
                'state' => $state,
                'finished' => true,
                'progress' => 100,
                'message' => 'Migration already completed.',
            ]);
        }

        // One seller per request to keep SiteGround/PHP-FPM load controlled.
        $q = $this->wcfm_migration_vendor_query((int)$state['offset'], 1);
        $users = $q->get_results();

        if (!$users) {
            $state['finished'] = true;
            update_option('eldokan_wcfm_migration_state', $state, false);

            wp_send_json_success([
                'state' => $state,
                'finished' => true,
                'progress' => 100,
                'message' => 'WCFM → ElDokan migration completed.',
            ]);
        }

        $result = $this->migrate_wcfm_vendor_to_eldokan($users[0]);

        if (!empty($result['error'])) {
            wp_send_json_error([
                'message' => $result['message'] ?? 'Seller migration failed.',
            ], 500);
        }

        $state['offset']++;
        $state['processed']++;
        $state['products'] += absint($result['products'] ?? 0);
        $state['order_items'] += absint($result['order_items'] ?? 0);
        $state['total'] = (int)$q->get_total();

        if ($state['processed'] >= $state['total']) {
            $state['finished'] = true;
        }

        update_option('eldokan_wcfm_migration_state', $state, false);

        $pct = $state['total']
            ? round(($state['processed'] / $state['total']) * 100, 1)
            : 100;

        wp_send_json_success([
            'state' => $state,
            'finished' => (bool)$state['finished'],
            'progress' => $pct,
            'message' => 'Migrated seller #'.absint($result['seller'])
                .' — '.absint($result['products']).' products, '
                .absint($result['order_items']).' historical order lines linked.',
        ]);
    }

    public function wcfm_migration_verify() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');

        wp_send_json_success(
            $this->wcfm_migration_verification_data()
        );
    }


    public function wcfm_migration_verify_detailed() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'], 403);
        }

        check_ajax_referer('eda_wcfm_migration', 'nonce');

        wp_send_json_success(
            $this->wcfm_migration_verification_data(true)
        );
    }


    private function wcfm_index_state() {
        $state = get_option('eldokan_wcfm_index_state', []);
        return is_array($state) ? $state : [];
    }

    private function wcfm_safe_meta($meta) {
        $out=[];
        foreach((array)$meta as $key=>$values){
            if(preg_match('/pass|password|token|secret|session|nonce|cookie|auth/i',(string)$key)) continue;
            $clean=[];
            foreach((array)$values as $v){
                $clean[] = maybe_unserialize($v);
            }
            $out[$key]=$clean;
        }
        return $out;
    }

    private function wcfm_store_settings($uid) {
        $out=[];
        foreach([
            'wcfmmp_profile_settings','wcfmmp_store_name','wcfm_store_hours',
            'wcfmmp_shipping','wcfmmp_store_policy','wcfmmp_store_seo',
            '_wcfmmp_profile_settings','_wcfm_store_hours'
        ] as $key){
            $v=get_user_meta($uid,$key,true);
            if($v!=='' && $v!==[]) $out[$key]=$v;
        }
        return $out;
    }

    private function wcfm_vendor_query($offset=0,$limit=5) {
        return new WP_User_Query([
            'role__in'=>['wcfm_vendor','vendor','seller','eldokan_owner'],
            'number'=>max(1,min(20,absint($limit))),
            'offset'=>max(0,absint($offset)),
            'orderby'=>'ID','order'=>'ASC','count_total'=>true
        ]);
    }

    private function wcfm_tables() {
        global $wpdb;
        $like=$wpdb->esc_like($wpdb->prefix).'%wcfm%';
        return array_values(array_filter((array)$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$like))));
    }

    private function wcfm_marketplace_order_rows($vendor_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcfm_marketplace_orders';
        if (!$this->table_exists($table)) return [];
        $rows = [];
        $offset = 0;
        $batch = 500;
        do {
            $chunk = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE vendor_id=%d ORDER BY ID ASC LIMIT %d OFFSET %d",
                absint($vendor_id), $batch, $offset
            ), ARRAY_A);
            if (!$chunk) break;
            $rows = array_merge($rows, $chunk);
            $offset += count($chunk);
        } while (count($chunk) === $batch && $offset < 20000);
        return $rows;
    }

    private function wcfm_index_vendor($user) {
        global $wpdb;

        foreach ([
            $this->wcfm_vendor_table,
            $this->wcfm_product_table,
            $this->wcfm_order_table,
            $this->wcfm_raw_table
        ] as $required_table) {
            if (!$this->table_exists($required_table)) {
                return ['error'=>true,'message'=>'Missing ElDokan index table: '.$required_table];
            }
        }

        global $wpdb;
        $uid=absint($user->ID);
        $products=$this->seller_product_ids($uid,500);
        $live=0;
        foreach($products as $pid){ if(get_post_status($pid)==='publish')$live++; }

        $metrics=$this->seller_order_metrics($uid,...$this->period('month'));
        $all=$this->seller_order_metrics($uid,null,null);

        $last_order_at=null;
        foreach($this->all_orders(300) as $order){
            $matched=false;
            foreach($order->get_items('line_item') as $item){
                if($this->item_seller_id($item)===$uid){$matched=true;break;}
            }
            if($matched && $order->get_date_created()){
                $last_order_at=$order->get_date_created()->date('Y-m-d H:i:s');
                break;
            }
        }

        $vendor_write=$wpdb->replace($this->wcfm_vendor_table,[
            'seller_user_id'=>$uid,
            'company_name'=>$this->company_name($uid),
            'email'=>$user->user_email,
            'phone'=>(string)get_user_meta($uid,'billing_phone',true),
            'status'=>(string)(get_user_meta($uid,'_eldokan_account_status',true)?:'active'),
            'registered_at'=>$user->user_registered,
            'store_settings'=>wp_json_encode($this->wcfm_store_settings($uid),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'safe_user_meta'=>wp_json_encode($this->wcfm_safe_meta(get_user_meta($uid)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'products_count'=>count($products),
            'live_products'=>$live,
            'orders_count'=>$all['orders'],
            'units_sold'=>$all['units'],
            'gross_sales'=>$all['gross'],
            'refunded_total'=>0,
            'cancelled_orders'=>$all['cancelled'],
            'last_order_at'=>$last_order_at,
            'last_synced_at'=>current_time('mysql')
        ]);
        if ($vendor_write === false) {
            return ['error'=>true,'message'=>'Vendor index write failed: '.$wpdb->last_error];
        }


        foreach($products as $pid){
            $p=function_exists('wc_get_product')?wc_get_product($pid):null;
            $cats=wp_get_post_terms($pid,'product_cat',['fields'=>'names']);
            $units=0;$sales=0;
            foreach($this->all_orders(300) as $order){
                foreach($order->get_items('line_item') as $item){
                    if((int)$item->get_product_id()===(int)$pid && $this->item_seller_id($item)===$uid){
                        $units+=(float)$item->get_quantity();
                        $sales+=(float)$item->get_total();
                    }
                }
            }
            $wpdb->replace($this->wcfm_product_table,[
                'product_id'=>$pid,'seller_user_id'=>$uid,'title'=>get_the_title($pid),
                'sku'=>$p?$p->get_sku():'','status'=>get_post_status($pid),
                'regular_price'=>$p?(float)$p->get_regular_price():0,
                'sale_price'=>($p&&$p->get_sale_price()!=='')?(float)$p->get_sale_price():null,
                'stock_qty'=>$p?$p->get_stock_quantity():null,
                'categories'=>wp_json_encode($cats,JSON_UNESCAPED_UNICODE),
                'image_id'=>get_post_thumbnail_id($pid),'total_units_sold'=>$units,'total_sales'=>$sales,
                'created_at'=>get_post_field('post_date',$pid),'updated_at'=>get_post_field('post_modified',$pid),
                'last_synced_at'=>current_time('mysql')
            ]);
        }

        $wcfm_order_rows = $this->wcfm_marketplace_order_rows($uid);
        if ($wcfm_order_rows) {
            $seen_order_ids = [];
            foreach ($wcfm_order_rows as $wr) {
                $oid = absint($wr['order_id'] ?? 0);
                if (!$oid) continue;
                $order = function_exists('wc_get_order') ? wc_get_order($oid) : null;
                $item_id = absint($wr['item_id'] ?? 0);
                $item = ($order && $item_id) ? $order->get_item($item_id) : null;
                $pid = absint($wr['product_id'] ?? ($item ? $item->get_product_id() : 0));
                $product = function_exists('wc_get_product') && $pid ? wc_get_product($pid) : null;
                $quantity = isset($wr['quantity']) ? (float)$wr['quantity'] : ($item ? (float)$item->get_quantity() : 0);
                $line_subtotal = isset($wr['item_sub_total']) ? (float)$wr['item_sub_total'] : ($item ? (float)$item->get_subtotal() : 0);
                $line_total = isset($wr['item_total']) ? (float)$wr['item_total'] : ($item ? (float)$item->get_total() : 0);
                $created = $order && $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : ($wr['created'] ?? null);
                $key = $oid . ':' . ($item_id ?: absint($wr['ID'] ?? 0)) . ':' . $uid;
                $meta = ['wcfm_marketplace_order'=>$wr];
                $wpdb->replace($this->wcfm_order_table,[
                    'row_key'=>$key,'order_id'=>$oid,'order_item_id'=>$item_id,
                    'seller_user_id'=>$uid,'product_id'=>$pid,'product_name'=>$item ? $item->get_name() : ($product ? $product->get_name() : ''),
                    'sku'=>$product?$product->get_sku():'','quantity'=>$quantity,
                    'line_subtotal'=>$line_subtotal,'line_total'=>$line_total,
                    'order_total'=>$order?(float)$order->get_total():$line_total,'order_status'=>$order?$order->get_status():'',
                    'customer_name'=>$order?$order->get_formatted_billing_full_name():'',
                    'customer_email'=>$order?$order->get_billing_email():'','payment_method'=>$order?$order->get_payment_method_title():'',
                    'created_at'=>$created,'item_meta'=>wp_json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'last_synced_at'=>current_time('mysql')
                ]);
                $seen_order_ids[$oid] = 1;
            }
            $all['orders'] = count($seen_order_ids);
        } else {
            foreach($this->all_orders(300) as $order){
                $created=$order->get_date_created()?$order->get_date_created()->date('Y-m-d H:i:s'):null;
                foreach($order->get_items('line_item') as $item_id=>$item){
                    if($this->item_seller_id($item)!==$uid)continue;
                    $pid=(int)$item->get_product_id();
                    $product=function_exists('wc_get_product')?wc_get_product($pid):null;
                    $meta=[];
                    foreach($item->get_meta_data() as $md){ $meta[$md->key]=$md->value; }
                    $key=$order->get_id().':'.$item_id.':'.$uid;
                    $wpdb->replace($this->wcfm_order_table,[
                        'row_key'=>$key,'order_id'=>$order->get_id(),'order_item_id'=>$item_id,
                        'seller_user_id'=>$uid,'product_id'=>$pid,'product_name'=>$item->get_name(),
                        'sku'=>$product?$product->get_sku():'','quantity'=>(float)$item->get_quantity(),
                        'line_subtotal'=>(float)$item->get_subtotal(),'line_total'=>(float)$item->get_total(),
                        'order_total'=>(float)$order->get_total(),'order_status'=>$order->get_status(),
                        'customer_name'=>$order->get_formatted_billing_full_name(),
                        'customer_email'=>$order->get_billing_email(),'payment_method'=>$order->get_payment_method_title(),
                        'created_at'=>$created,'item_meta'=>wp_json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        'last_synced_at'=>current_time('mysql')
                    ]);
                }
            }
        }

        // Keep a safe raw snapshot of vendor-related rows from WCFM tables when a vendor-id-like column exists.
        foreach($this->wcfm_tables() as $table){
            $cols=$wpdb->get_col("DESC `{$table}`",0);
            $vendor_col='';
            foreach(['vendor_id','seller_id','author_id','user_id','store_id'] as $candidate){
                if(in_array($candidate,$cols,true)){$vendor_col=$candidate;break;}
            }
            if(!$vendor_col)continue;

            $rows=$wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `{$vendor_col}`=%d LIMIT 500",$uid
            ),ARRAY_A);
            foreach($rows as $i=>$row){
                $source_id='';
                foreach(['ID','id','order_id','product_id'] as $idk){
                    if(isset($row[$idk])){$source_id=(string)$row[$idk];break;}
                }
                if($source_id==='')$source_id=(string)$i;
                $snap=md5($table.'|'.$uid.'|'.$source_id);
                $wpdb->replace($this->wcfm_raw_table,[
                    'snapshot_key'=>$snap,'seller_user_id'=>$uid,'source_table'=>$table,
                    'source_id'=>$source_id,'payload'=>wp_json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                    'last_synced_at'=>current_time('mysql')
                ]);
            }
        }

        return ['seller'=>$uid,'products'=>count($products),'orders'=>$all['orders']];
    }

    public function wcfm_index_scan() {
        if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Not allowed'],403);
        check_ajax_referer('eda_wcfm_index','nonce');
        $q=$this->wcfm_vendor_query(0,1);
        wp_send_json_success([
            'vendors'=>(int)$q->get_total(),
            'wcfmTables'=>count($this->wcfm_tables()),
            'message'=>'Scan complete. Source WCFM data was not changed.'
        ]);
    }

    public function wcfm_index_reset() {
        if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Not allowed'],403);
        check_ajax_referer('eda_wcfm_index','nonce');
        delete_option('eldokan_wcfm_index_state');
        wp_send_json_success(['message'=>'Sync progress reset. Indexed data and WCFM source data remain untouched.']);
    }


    public function wcfm_index_rebuild() {
        if(!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Not allowed'],403);
        }
        check_ajax_referer('eda_wcfm_index','nonce');
        global $wpdb;

        $this->ensure_index_schema();

        foreach ([
            $this->wcfm_vendor_table,
            $this->wcfm_product_table,
            $this->wcfm_order_table,
            $this->wcfm_raw_table
        ] as $table) {
            if ($this->table_exists($table)) {
                $wpdb->query("TRUNCATE TABLE `{$table}`");
            }
        }

        delete_option('eldokan_wcfm_index_state');

        wp_send_json_success([
            'message'=>'ElDokan index cleared and schema verified. WCFM source data was not changed.'
        ]);
    }

    public function wcfm_index_batch() {
        if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'Not allowed'],403);
        check_ajax_referer('eda_wcfm_index','nonce');
        @set_time_limit(20);

        $state=$this->wcfm_index_state();
        if(!$state){
            $q=$this->wcfm_vendor_query(0,1);
            $state=['offset'=>0,'processed'=>0,'total'=>(int)$q->get_total(),'products'=>0,'orders'=>0,'finished'=>false];
        }
        if(!empty($state['finished']))wp_send_json_success(['state'=>$state,'finished'=>true,'progress'=>100]);

        // One seller per request keeps PHP-FPM load low.
        $q=$this->wcfm_vendor_query((int)$state['offset'],1);
        $users=$q->get_results();

        if(!$users){
            $state['finished']=true;
            update_option('eldokan_wcfm_index_state',$state,false);
            wp_send_json_success(['state'=>$state,'finished'=>true,'progress'=>100,'message'=>'Full WCFM index completed.']);
        }

        $r=$this->wcfm_index_vendor($users[0]);
        if (!empty($r['error'])) {
            wp_send_json_error(['message'=>$r['message']??'Index write failed.'],500);
        }
        $state['offset']++;
        $state['processed']++;
        $state['products']+=(int)$r['products'];
        $state['orders']+=(int)$r['orders'];
        $state['total']=(int)$q->get_total();

        if($state['processed'] >= $state['total'])$state['finished']=true;
        update_option('eldokan_wcfm_index_state',$state,false);

        $pct=$state['total']?round(($state['processed']/$state['total'])*100,1):100;
        wp_send_json_success(['state'=>$state,'finished'=>(bool)$state['finished'],'progress'=>$pct,'message'=>'Seller indexed: '.$r['seller']]);
    }

    public function wcfm_index_page() {
        if(!current_user_can('manage_options'))wp_die('Not allowed');
        global $wpdb;
        $this->ensure_index_schema();
        $state=$this->wcfm_index_state();
        $nonce=wp_create_nonce('eda_wcfm_index');
        $schema_ok =
            $this->table_exists($this->wcfm_vendor_table) &&
            $this->table_exists($this->wcfm_product_table) &&
            $this->table_exists($this->wcfm_order_table) &&
            $this->table_exists($this->wcfm_raw_table);
        $vendors=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->wcfm_vendor_table}");
        $products=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->wcfm_product_table}");
        $orders=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->wcfm_order_table}");
        $raw=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->wcfm_raw_table}");

        $this->header('WCFM → ElDokan Migration','Migrate existing WCFM sellers to ElDokan without asking them to register again. This is copy/link only; WCFM source data is never deleted.');
        echo '<div class="eda-grid">';
        foreach(['Indexed Sellers'=>$vendors,'Indexed Products'=>$products,'Indexed Order Lines'=>$orders,'Raw WCFM Rows'=>$raw] as $k=>$v)
            echo '<div class="eda-card"><small>'.esc_html($k).'</small><strong>'.absint($v).'</strong></div>';
        echo '</div>';
        $migration = $this->wcfm_migration_verification_data();
        $migration_state = $this->wcfm_migration_state();
        $migration_nonce = wp_create_nonce('eda_wcfm_migration');

        echo '<div class="eda-card eda-migration-card">';
        echo '<div class="eda-section-head"><div><h2>' . esc_html__('1. Migrate Existing Sellers to ElDokan', 'eldokan-admin') . '</h2><p>' . esc_html__('No seller needs to register again. Existing User ID, email and password stay exactly the same.', 'eldokan-admin') . '</p></div><span class="eda-badge green">' . esc_html__('COPY / LINK ONLY', 'eldokan-admin') . '</span></div>';
        echo '<div class="eda-migration-warning"><strong>' . esc_html__('WCFM Safety:', 'eldokan-admin') . '</strong> ' . esc_html__('This process does NOT delete WCFM users, roles, products, tables, metadata or change product post_author.', 'eldokan-admin') . '</div>';
        echo '<div class="eda-grid">';
        echo '<div class="eda-card"><small>' . esc_html__('Indexed WCFM Sellers', 'eldokan-admin') . '</small><strong id="mSourceSellers">'.absint($migration['source_sellers']).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Migrated Sellers', 'eldokan-admin') . '</small><strong id="mMigratedSellers">'.absint($migration['migrated_sellers']).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Current Products', 'eldokan-admin') . '</small><strong id="mCurrentProducts">'.absint($migration['current_products'] ?? 0).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Mapped Current Products', 'eldokan-admin') . '</small><strong id="mMappedProducts">'.absint($migration['mapped_products']).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Historical Products Preserved', 'eldokan-admin') . '</small><strong id="mHistoricalProducts">'.absint($migration['historical_products'] ?? 0).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Current Seller/Orders', 'eldokan-admin') . '</small><strong id="mCurrentOrders">'.absint($migration['current_orders'] ?? 0).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Linked Current Seller/Orders', 'eldokan-admin') . '</small><strong id="mLinkedOrders">'.absint($migration['linked_orders']).'</strong></div>';
        echo '<div class="eda-card"><small>' . esc_html__('Historical Seller/Orders Preserved', 'eldokan-admin') . '</small><strong id="mHistoricalOrders">'.absint($migration['historical_orders'] ?? 0).'</strong></div>';
        echo '</div>';
        echo '<p><b>' . esc_html__('What migration does for each seller:', 'eldokan-admin') . '</b> ' . esc_html__('keeps the same WordPress account/password, adds', 'eldokan-admin') . ' <code>' . esc_html__('eldokan_owner', 'eldokan-admin') . '</code> ' . esc_html__('without removing the WCFM role, marks the seller Approved, copies product ownership to ElDokan metadata, and links historical WooCommerce order items to the seller.', 'eldokan-admin') . '</p>';
        echo '<p><button class="button" id="mScan">' . esc_html__('Scan Sellers', 'eldokan-admin') . '</button> <button class="button button-primary" id="mRun">' . esc_html__('Start / Resume Migration', 'eldokan-admin') . '</button> <button class="button" id="mVerify">' . esc_html__('Verify Migration', 'eldokan-admin') . '</button> <button class="button" id="mVerifyDetailed">' . esc_html__('Detailed Verification', 'eldokan-admin') . '</button> <button class="button button-primary" id="mRepair">' . esc_html__('Repair Missing Links', 'eldokan-admin') . '</button> <button class="button" id="mReset">' . esc_html__('Reset Progress Only', 'eldokan-admin') . '</button></p>';
        echo '<div id="mMsg"></div>';
        echo '<div class="eda-progress"><div id="mBar" style="width:0"></div></div>';
        echo '<p id="mStats"></p>';
        echo '<div id="mReady" class="'.($migration['ready']?'eda-ready-box':'eda-not-ready-box').'">'.($migration['ready']?'<strong>' . esc_html__('Ownership mappings complete — WCFM retirement NOT approved', 'eldokan-admin') . '</strong><br>' . esc_html__('Seller/product/order mapping checks passed. This does NOT verify WCFM shipping, commissions, payouts, withdrawals, refunds, roles or hooks; keep WCFM active until service-level acceptance is completed.', 'eldokan-admin') . '':'<strong>' . esc_html__('Ownership mapping is incomplete — WCFM must remain active.', 'eldokan-admin') . '</strong><br>' . esc_html__('Verification must reach 0 unmapped sellers, 0 unmapped products and 0 unlinked seller/order pairs.', 'eldokan-admin') . '').'</div>';
        echo '<div id="mDetailWrap" class="eda-verification-wrap" style="display:none"><div class="eda-section-head"><div><h3>' . esc_html__('Detailed Verification', 'eldokan-admin') . '</h3><p>' . esc_html__('Per-seller comparison between the WCFM Historical Data Index and permanent ElDokan mappings.', 'eldokan-admin') . '</p></div><button class="button button-primary" id="mRepairDetailed">' . esc_html__('Repair All Missing', 'eldokan-admin') . '</button></div><div id="mDetailSummary"></div><div class="eda-table-scroll"><table class="eda-table"><thead><tr><th>' . esc_html__('Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Migrated', 'eldokan-admin') . '</th><th>' . esc_html__('Current Products', 'eldokan-admin') . '</th><th>' . esc_html__('Mapped Products', 'eldokan-admin') . '</th><th>' . esc_html__('Historical Products', 'eldokan-admin') . '</th><th>' . esc_html__('Missing Products', 'eldokan-admin') . '</th><th>' . esc_html__('Current Orders', 'eldokan-admin') . '</th><th>' . esc_html__('Linked Orders', 'eldokan-admin') . '</th><th>' . esc_html__('Historical Orders', 'eldokan-admin') . '</th><th>' . esc_html__('Missing Orders', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Repair', 'eldokan-admin') . '</th></tr></thead><tbody id="mDetailBody"></tbody></table></div></div>';
        echo '</div>';

        echo '<div class="eda-card"><div class="eda-section-head"><div><h2>' . esc_html__('2. WCFM Historical Data Index', 'eldokan-admin') . '</h2><p>' . esc_html__('Optional reporting/cache copy of WCFM history inside ElDokan.', 'eldokan-admin') . '</p></div></div><p><b>' . esc_html__('Index Schema:', 'eldokan-admin') . '</b> '.($schema_ok?'<span class="eda-badge green">' . esc_html__('Ready', 'eldokan-admin') . '</span>':'<span class="eda-badge red">' . esc_html__('Missing', 'eldokan-admin') . '</span>').'</p><p><b>' . esc_html__('Copy only:', 'eldokan-admin') . '</b> ' . esc_html__('seller profile/settings, safe user meta, products, prices, stock, categories, images, seller order lines, quantities, sales, statuses, customer/order information and vendor-linked rows discovered in WCFM database tables.', 'eldokan-admin') . '</p><p>' . esc_html__('Passwords, tokens, sessions, cookies and secrets are intentionally excluded.', 'eldokan-admin') . '</p><p><button class="button" id="wScan">' . esc_html__('Scan WCFM Data', 'eldokan-admin') . '</button> <button class="button button-primary" id="wRun">' . esc_html__('Start / Resume Full Index', 'eldokan-admin') . '</button> <button class="button" id="wReset">' . esc_html__('Reset Progress', 'eldokan-admin') . '</button> <button class="button" id="wRebuild">' . esc_html__('Rebuild ElDokan Index', 'eldokan-admin') . '</button></p><div id="wMsg"></div><div style="height:12px;background:#eef1f5;border-radius:999px;overflow:hidden"><div id="wBar" style="height:100%;width:0;background:#111827"></div></div><p id="wStats"></p></div>';
        if($state) echo '<script>window.edaWState='.wp_json_encode($state).';</script>';
        if($migration_state) echo '<script>window.edaMState='.wp_json_encode($migration_state).';</script>';

        echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
          const ajax='.wp_json_encode(admin_url('admin-ajax.php')).',nonce='.wp_json_encode($nonce).',migrationNonce='.wp_json_encode($migration_nonce).';

          let mRunning=false,mBar=document.getElementById("mBar"),mStats=document.getElementById("mStats"),mMsg=document.getElementById("mMsg"),mReady=document.getElementById("mReady");
          function mRender(st,p){st=st||{};mBar.style.width=(p||0)+"%";mStats.textContent="Sellers "+(st.processed||0)+" / "+(st.total||0)+" | Products linked "+(st.products||0)+" | Historical order lines "+(st.order_items||0)}
          async function mPost(action){let b=new URLSearchParams();b.set("action",action);b.set("nonce",migrationNonce);let r=await fetch(ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b.toString()});let d=await r.json();if(!d.success)throw new Error(d.data?.message||"Request failed");return d.data}
          function applyVerify(d){
            document.getElementById("mSourceSellers").textContent=d.source_sellers||0;
            document.getElementById("mMigratedSellers").textContent=d.migrated_sellers||0;
            document.getElementById("mCurrentProducts").textContent=d.current_products||0;
            document.getElementById("mMappedProducts").textContent=d.mapped_products||0;
            document.getElementById("mHistoricalProducts").textContent=d.historical_products||0;
            document.getElementById("mCurrentOrders").textContent=d.current_orders||0;
            document.getElementById("mLinkedOrders").textContent=d.linked_orders||0;
            document.getElementById("mHistoricalOrders").textContent=d.historical_orders||0;
            if(d.index_ready===false){
              mReady.className="eda-not-ready-box";
              mReady.innerHTML="<strong>"+escHtml(window.eldokanAdminUI.wcfmHistory)+"</strong><br>"+escHtml(d.message||window.eldokanAdminUI.wcfmKeep);
            }else if(d.ready){
              mReady.className="eda-ready-box";
              mReady.innerHTML="<strong>"+escHtml(window.eldokanAdminUI.wcfmMapped)+"</strong><br>"+escHtml(window.eldokanAdminUI.wcfmKeep);
            }else{
              mReady.className="eda-not-ready-box";
              mReady.innerHTML="<strong>"+escHtml(window.eldokanAdminUI.wcfmBlocked)+"</strong><br>"+escHtml(window.eldokanAdminUI.wcfmKeep)+" ("+(d.unmapped_sellers||0)+" / "+(d.unmapped_products||0)+" / "+(d.unlinked_orders||0)+")";
            }
          }
          document.getElementById("mScan").onclick=async()=>{try{let d=await mPost("eldokan_wcfm_migration_scan");mMsg.textContent="Detected "+d.sellers+" WCFM sellers. "+d.message}catch(e){mMsg.textContent=e.message}};
          document.getElementById("mRun").onclick=async function(){if(mRunning)return;if(!confirm("Start COPY-ONLY migration? Nothing will be deleted from WCFM."))return;mRunning=true;this.disabled=true;try{while(mRunning){let d=await mPost("eldokan_wcfm_migration_batch");mRender(d.state,d.progress);mMsg.textContent=d.message||"";if(d.finished){mRunning=false;let v=await mPost("eldokan_wcfm_migration_verify");applyVerify(v);break}await new Promise(x=>setTimeout(x,300));}}catch(e){mRunning=false;mMsg.textContent=e.message+" — click Start / Resume to continue."}this.disabled=false};
          document.getElementById("mVerify").onclick=async()=>{try{let d=await mPost("eldokan_wcfm_migration_verify");applyVerify(d);mMsg.textContent=d.message||""}catch(e){mMsg.textContent=e.message}};
          document.getElementById("mVerifyDetailed").onclick=async()=>{try{
            let d=await mPost("eldokan_wcfm_migration_verify_detailed");
            applyVerify(d);
            let wrap=document.getElementById("mDetailWrap"),body=document.getElementById("mDetailBody"),summary=document.getElementById("mDetailSummary");
            wrap.style.display="block";
            body.innerHTML="";
            if(d.index_ready===false){
              summary.innerHTML="<div class=\"eda-not-ready-box\"><strong>Historical Index not available.</strong><br>"+escHtml(d.message||"Build or rebuild the WCFM Historical Data Index first.")+"</div>";
              mMsg.textContent=d.message||"";
              return;
            }
            summary.innerHTML="<p><b>Unmapped Sellers:</b> "+(d.unmapped_sellers||0)+" &nbsp; <b>Unmapped Products:</b> "+(d.unmapped_products||0)+" &nbsp; <b>Unlinked Seller/Orders:</b> "+(d.unlinked_orders||0)+"</p>";
            (d.details||[]).forEach(x=>{
              let tr=document.createElement("tr");
              if(x.status!=="OK")tr.className="eda-verification-bad";
              tr.innerHTML="<td><strong>"+escHtml(x.seller_name||("Seller #"+x.seller_id))+"</strong><br><small>#"+x.seller_id+"</small></td>"
                +"<td>"+(x.migrated?"Yes":"No")+"</td>"
                +"<td>"+x.current_products+"</td>"
                +"<td>"+x.mapped_products+"</td>"
                +"<td>"+x.historical_products+"</td>"
                +"<td>"+x.missing_products+"</td>"
                +"<td>"+x.current_orders+"</td>"
                +"<td>"+x.linked_orders+"</td>"
                +"<td>"+x.historical_orders+"</td>"
                +"<td>"+x.missing_orders+"</td>"
                +"<td>"+(x.status==="OK"?"<span class=\"eda-badge green\">OK</span>":"<span class=\"eda-badge red\">NOT READY</span>")+"</td>"
                +"<td>"+(x.status==="OK"?"—":"<button type=\"button\" class=\"button button-small eda-repair-seller\" data-seller=\""+x.seller_id+"\">Repair</button>")+"</td>";
              body.appendChild(tr);
            });
            wrap.querySelectorAll(".eda-repair-seller").forEach(btn=>{
              btn.onclick=async()=>{let sid=btn.dataset.seller;if(!confirm("Repair missing ElDokan links for seller #"+sid+"? WCFM data will stay unchanged."))return;btn.disabled=true;try{let r=await repairLinks(sid);mMsg.textContent=r.message||"";await document.getElementById("mVerifyDetailed").onclick()}catch(e){mMsg.textContent=e.message}btn.disabled=false};
            });
            wrap.scrollIntoView({behavior:"smooth",block:"start"});
            mMsg.textContent=d.message||"";
          }catch(e){mMsg.textContent=e.message}};
          async function repairLinks(sellerId){
            let b=new URLSearchParams();b.set("action","eldokan_wcfm_migration_repair");b.set("nonce",migrationNonce);if(sellerId)b.set("seller_id",sellerId);
            let r=await fetch(ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b.toString()});
            let d=await r.json();if(!d.success)throw new Error(d.data?.message||"Repair failed");if(d.data?.verification)applyVerify(d.data.verification);return d.data;
          }
          async function runGlobalRepair(){
            if(!confirm("Repair ALL missing product/order links? WCFM data will remain unchanged."))return;
            mMsg.textContent="Repairing missing mappings...";
            try{let d=await repairLinks(0);mMsg.textContent=d.message||"";await document.getElementById("mVerifyDetailed").onclick()}catch(e){mMsg.textContent=e.message}
          }
          document.getElementById("mRepair").onclick=runGlobalRepair;
          document.getElementById("mRepairDetailed").onclick=runGlobalRepair;
          function escHtml(v){let d=document.createElement("div");d.textContent=String(v??"");return d.innerHTML}
          document.getElementById("mReset").onclick=async()=>{if(!confirm("Reset migration progress only? Migrated ElDokan data and all WCFM data will remain untouched."))return;let d=await mPost("eldokan_wcfm_migration_reset");mRender({},0);mMsg.textContent=d.message};
          let mst=window.edaMState||null;if(mst){let mp=mst.total?Math.round(mst.processed/mst.total*1000)/10:0;mRender(mst,mp)}

          const indexNonce=nonce;
          let running=false,bar=document.getElementById("wBar"),stats=document.getElementById("wStats"),msg=document.getElementById("wMsg");
          function render(st,p){st=st||{};bar.style.width=(p||0)+"%";stats.textContent="Sellers "+(st.processed||0)+" / "+(st.total||0)+" | Products "+(st.products||0)+" | Orders "+(st.orders||0)}
          async function post(action){let b=new URLSearchParams();b.set("action",action);b.set("nonce",indexNonce);let r=await fetch(ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:b.toString()});let d=await r.json();if(!d.success)throw new Error(d.data?.message||"Request failed");return d.data}
          document.getElementById("wScan").onclick=async()=>{try{let d=await post("eldokan_wcfm_index_scan");msg.textContent="Detected "+d.vendors+" vendors and "+d.wcfmTables+" WCFM tables."}catch(e){msg.textContent=e.message}};
          document.getElementById("wRun").onclick=async function(){if(running)return;running=true;this.disabled=true;try{while(running){let d=await post("eldokan_wcfm_index_batch");render(d.state,d.progress);msg.textContent=d.message||"";if(d.finished){running=false;break}await new Promise(x=>setTimeout(x,250));}}catch(e){running=false;msg.textContent=e.message+" — click Start / Resume to continue."}this.disabled=false};
          document.getElementById("wReset").onclick=async()=>{if(!confirm("Reset sync progress only?"))return;let d=await post("eldokan_wcfm_index_reset");render({},0);msg.textContent=d.message};
          document.getElementById("wRebuild").onclick=async()=>{if(!confirm("Rebuild ElDokan index only? WCFM source data will NOT be deleted."))return;let d=await post("eldokan_wcfm_index_rebuild");render({},0);msg.textContent=d.message;setTimeout(()=>location.reload(),700)};
          let st=window.edaWState||null;if(st){let p=st.total?Math.round(st.processed/st.total*1000)/10:0;render(st,p)}
        });
        </script>';
        $this->footer();
    }

    private function indexed_seller_details($seller_id) {
        global $wpdb;
        $seller=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->wcfm_vendor_table} WHERE seller_user_id=%d",$seller_id),ARRAY_A);
        if(!$seller)return null;
        $products=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->wcfm_product_table} WHERE seller_user_id=%d ORDER BY total_sales DESC,product_id DESC LIMIT 300",$seller_id),ARRAY_A);
        $orders=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->wcfm_order_table} WHERE seller_user_id=%d ORDER BY created_at DESC LIMIT 500",$seller_id),ARRAY_A);
        $raw_count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->wcfm_raw_table} WHERE seller_user_id=%d",$seller_id));
        return compact('seller','products','orders','raw_count');
    }

    private function dashboard_defaults() {
        return ['home'=>1,'products'=>1,'orders'=>1,'reports'=>1,'accounts'=>1,'notifications'=>1,'settings'=>1,'quick_edit'=>1,'sale_price'=>1,'sale_dates'=>1,'product_create'=>1,'product_edit'=>1,'order_accept'=>1,'order_reject'=>1,'order_response_days'=>2,'cancel_warning_limit'=>3];
    }

    public function dashboard_control() {
        if (!current_user_can('manage_options')) wp_die('Not allowed');
        $this->header('Seller Dashboard Control','Control the React Seller Dashboard from WordPress instead of WCFM.');
        $cfg=get_option('eldokan_dashboard_control_global',$this->dashboard_defaults());
        echo '<div class="eda-card"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('eda_dashboard_control','_wpnonce',true,false).'<input type="hidden" name="action" value="eldokan_admin_save_dashboard_control">';
        echo '<h2>' . esc_html__('Apply To', 'eldokan-admin') . '</h2><div class="eda-form"><label>' . esc_html__('Scope', 'eldokan-admin') . '<select name="scope" id="dcScope"><option value="global">' . esc_html__('All Sellers', 'eldokan-admin') . '</option><option value="seller">' . esc_html__('One Seller', 'eldokan-admin') . '</option></select></label><label id="dcSeller" style="display:none">' . esc_html__('Seller', 'eldokan-admin') . '<select name="seller_id"><option value="0">' . esc_html__('Select seller', 'eldokan-admin') . '</option>';
        foreach($this->owner_users() as $u) echo '<option value="'.$u->ID.'">'.esc_html($this->company_name($u->ID)).'</option>';
        echo '</select></label></div><h2>' . esc_html__('Visible Sections', 'eldokan-admin') . '</h2><div class="eda-grid">';
        foreach(['home'=>'Home','products'=>'Products','orders'=>'Orders','reports'=>'Reports','accounts'=>'Accounts','notifications'=>'Notifications','settings'=>'Settings'] as $k=>$v) echo '<label class="eda-mini"><input type="checkbox" name="cfg['.$k.']" value="1" '.checked(!empty($cfg[$k]),true,false).'> <b>'.$v.'</b></label>';
        echo '</div><h2>' . esc_html__('Permissions', 'eldokan-admin') . '</h2><div class="eda-grid">';
        foreach(['product_create'=>'Create Products','product_edit'=>'Edit Products','quick_edit'=>'Quick Edit','sale_price'=>'Sale Price','sale_dates'=>'Sale Dates','order_accept'=>'Accept Orders','order_reject'=>'Reject Orders'] as $k=>$v) echo '<label class="eda-mini"><input type="checkbox" name="cfg['.$k.']" value="1" '.checked(!empty($cfg[$k]),true,false).'> <b>'.$v.'</b></label>';
        echo '</div><h2>' . esc_html__('Order Policy', 'eldokan-admin') . '</h2><div class="eda-form"><label>' . esc_html__('Maximum response time (days)', 'eldokan-admin') . '<input type="number" min="1" max="30" name="cfg[order_response_days]" value="'.esc_attr($cfg['order_response_days']??2).'"></label><label>' . esc_html__('Cancellation warning limit', 'eldokan-admin') . '<input type="number" min="1" max="100" name="cfg[cancel_warning_limit]" value="'.esc_attr($cfg['cancel_warning_limit']??3).'"></label></div><button class="button button-primary">' . esc_html__('Save Dashboard Control', 'eldokan-admin') . '</button></form></div>';
        echo '<script>document.addEventListener("DOMContentLoaded",()=>{let s=document.getElementById("dcScope"),r=document.getElementById("dcSeller");let f=()=>r.style.display=s.value==="seller"?"grid":"none";s.onchange=f;f()})</script>';
        $this->footer();
    }

    public function save_dashboard_control() {
        if(!current_user_can('manage_options'))wp_die('Not allowed'); check_admin_referer('eda_dashboard_control');
        $r=is_array($_POST['cfg']??null)?$_POST['cfg']:[];$cfg=[];
        foreach(['home','products','orders','reports','accounts','notifications','settings','quick_edit','sale_price','sale_dates','product_create','product_edit','order_accept','order_reject'] as $k)$cfg[$k]=isset($r[$k])?1:0;
        $cfg['order_response_days']=max(1,min(30,absint($r['order_response_days']??2)));$cfg['cancel_warning_limit']=max(1,min(100,absint($r['cancel_warning_limit']??3)));
        if(($_POST['scope']??'global')==='seller'){ $id=absint($_POST['seller_id']??0);if(!$id)wp_die('Select seller');update_user_meta($id,'_eldokan_dashboard_control',$cfg); } else update_option('eldokan_dashboard_control_global',$cfg,false);
        $return_seller=absint($_POST['return_seller_id']??0);
        if($return_seller){wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$return_seller.'#seller-dashboard-access'));exit;}
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-dashboard-control&saved=1'));exit;
    }

    public function commissions() {
        if (!current_user_can('eldokan_backoffice_view_commissions')) wp_die('Not allowed');
        global $wpdb;
        $this->header('Commissions','Global or seller-specific. Apply to all products, a category, or one product. Percentage, Fixed, or Percentage + Fixed together.');
        $can_manage = current_user_can('eldokan_backoffice_manage_commissions');
        if ($can_manage) {
        $sellers=$this->owner_users();$cats=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);$products=get_posts(['post_type'=>'product','post_status'=>['publish','pending','draft'],'numberposts'=>200]);
        echo '<div class="eda-card"><h2>' . esc_html__('Add Commission Rule', 'eldokan-admin') . '</h2><p><b>' . esc_html__('Priority:', 'eldokan-admin') . '</b> ' . esc_html__('Seller Product → Seller Category → Seller All → Global Product → Global Category → Global All. This means a seller-specific rule removes the matching global rule from calculation for that seller.', 'eldokan-admin') . '</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('eda_save_commission','_wpnonce',true,false).'<input type="hidden" name="action" value="eldokan_admin_save_commission"><div class="eda-form"><label>' . esc_html__('Scope', 'eldokan-admin') . '<select name="scope" id="edaScope"><option value="global">' . esc_html__('All Sellers (Global)', 'eldokan-admin') . '</option><option value="seller">' . esc_html__('One Seller', 'eldokan-admin') . '</option></select></label><label id="edaSeller" style="display:none">' . esc_html__('Seller', 'eldokan-admin') . '<select name="seller_user_id"><option value="0">' . esc_html__('Select', 'eldokan-admin') . '</option>';
        foreach($sellers as $s)echo '<option value="'.$s->ID.'">'.esc_html($this->company_name($s->ID)).'</option>';
        echo '</select></label><label>' . esc_html__('Apply To', 'eldokan-admin') . '<select name="target_type" id="edaTarget"><option value="all">' . esc_html__('All Products', 'eldokan-admin') . '</option><option value="category">' . esc_html__('Category', 'eldokan-admin') . '</option><option value="product">' . esc_html__('Product', 'eldokan-admin') . '</option></select></label><label id="edaTargetId" style="display:none">' . esc_html__('Target', 'eldokan-admin') . '<select name="target_id"><option value="0">' . esc_html__('Select', 'eldokan-admin') . '</option>';
        foreach((is_wp_error($cats) ? [] : $cats) as $c)echo '<option data-kind="category" value="'.$c->term_id.'">' . esc_html__('Category:', 'eldokan-admin') . ' '.esc_html($c->name).'</option>';
        foreach($products as $p)echo '<option data-kind="product" value="'.$p->ID.'">' . esc_html__('Product:', 'eldokan-admin') . ' '.esc_html($p->post_title).'</option>';
        echo '</select></label><label>' . esc_html__('Type', 'eldokan-admin') . '<select name="calculation_type" id="edaCommissionType"><option value="percentage">' . esc_html__('Percentage %', 'eldokan-admin') . '</option><option value="fixed">' . esc_html__('Fixed EGP', 'eldokan-admin') . '</option><option value="hybrid">' . esc_html__('Percentage % + Fixed EGP', 'eldokan-admin') . '</option></select></label><label><span id="edaCommissionValueText">' . esc_html__('Percentage %', 'eldokan-admin') . '</span><input type="number" min="0" max="100" step="0.01" name="value" id="edaCommissionValue" required></label><label id="edaCommissionFixedWrap" style="display:none"><span>' . esc_html__('Fixed Add-on EGP', 'eldokan-admin') . '</span><input type="number" min="0" step="0.01" name="fixed_value" id="edaCommissionFixed" value="0"></label></div><p class="description" id="edaCommissionHelp">' . esc_html__('Percentage: ElDokan takes the entered percentage.', 'eldokan-admin') . '</p><button class="button button-primary">' . esc_html__('Save Rule', 'eldokan-admin') . '</button></form></div>';
        }
        $rule_page = max(1, absint($this->request_text($_GET, 'paged', '1')));
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->commission_table}");
        $pages = max(1, (int)ceil($total/50));
        $rule_page = min($rule_page, $pages);
        $rules=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->commission_table} ORDER BY id DESC LIMIT %d OFFSET %d", 50, ($rule_page-1)*50));
        echo '<div class="eda-card"><table class="eda-table"><tr><th>' . esc_html__('Scope', 'eldokan-admin') . '</th><th>' . esc_html__('Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Target', 'eldokan-admin') . '</th><th>' . esc_html__('Type', 'eldokan-admin') . '</th><th>' . esc_html__('Value', 'eldokan-admin') . '</th><th>'.esc_html__('Status', 'eldokan-admin').'</th><th></th></tr>';
        foreach ($rules as $r) {
            $seller = $r->scope === 'seller' ? $this->company_name($r->seller_user_id) : __('All Sellers', 'eldokan-admin');
            echo '<tr><td>'.$this->badge($r->scope).'</td><td>'.esc_html($seller).'</td><td>'.esc_html(ucfirst($r->target_type).' #'.$r->target_id).'</td><td>'.esc_html($this->commission_type_label($r->calculation_type)).'</td><td>'.esc_html($this->commission_rule_value_label($r)).'</td><td>'.esc_html((int)$r->enabled === 1 ? __('Enabled', 'eldokan-admin') : __('Disabled', 'eldokan-admin')).'</td><td>'.$this->commission_rule_actions($r).'</td></tr>';
        }
        if (!$rules) echo '<tr><td colspan="7">'.esc_html__('No fee rules found.', 'eldokan-admin').'</td></tr>';
        $pagination = '<nav class="eda-pagination">'.paginate_links(['base'=>str_replace('999999999','%#%',add_query_arg(['page'=>'eldokan-admin-commissions','paged'=>999999999],admin_url('admin.php'))),'format'=>'','current'=>$rule_page,'total'=>$pages]).'</nav>';
        echo '</table>'.$pagination.'</div><script>document.addEventListener("DOMContentLoaded",()=>{const s=document.getElementById("edaScope"),sr=document.getElementById("edaSeller"),t=document.getElementById("edaTarget"),tr=document.getElementById("edaTargetId"),sel=tr?.querySelector("select"),type=document.getElementById("edaCommissionType"),valueText=document.getElementById("edaCommissionValueText"),value=document.getElementById("edaCommissionValue"),fixedWrap=document.getElementById("edaCommissionFixedWrap"),fixed=document.getElementById("edaCommissionFixed"),help=document.getElementById("edaCommissionHelp");if(!s||!tr||!type)return;function a(){sr.style.display=s.value==="seller"?"grid":"none"}function b(){tr.style.display=t.value==="all"?"none":"grid";[...sel.options].forEach(o=>{if(o.dataset.kind)o.hidden=o.dataset.kind!==t.value});sel.value="0"}function c(){if(type.value==="percentage"){valueText.textContent="Percentage %";value.max="100";fixedWrap.style.display="none";fixed.value="0";help.textContent="Percentage: ElDokan takes the entered percentage."}else if(type.value==="fixed"){valueText.textContent="Fixed EGP";value.removeAttribute("max");fixedWrap.style.display="none";fixed.value="0";help.textContent="Fixed: ElDokan takes the entered fixed EGP amount."}else{valueText.textContent="Percentage %";value.max="100";fixedWrap.style.display="grid";help.textContent="Percentage + Fixed: both are added together. Example: 5% + 20 EGP."}}s.onchange=a;t.onchange=b;type.onchange=c;a();b();c()});</script>';
        $this->footer();
    }

    public function statements() {
        if (!current_user_can('eldokan_backoffice_view_reports')) wp_die('Not allowed');
        $this->header('Statements','Financial statement for ElDokan and every seller.');
        echo '<div class="eda-card"><table class="eda-table"><tr><th>' . esc_html__('Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Today Sales', 'eldokan-admin') . '</th><th>' . esc_html__('7d Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Month Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Month Commission', 'eldokan-admin') . '</th><th>' . esc_html__('Seller Net Proceeds', 'eldokan-admin') . '</th><th>' . esc_html__('Orders', 'eldokan-admin') . '</th></tr>';
        foreach($this->owner_users() as $s){$today=$this->seller_order_metrics($s->ID,...$this->period('today'));$d7=$this->seller_order_metrics($s->ID,...$this->period('7d'));$mo=$this->seller_order_metrics($s->ID,...$this->period('month'));echo '<tr><td><strong>'.esc_html($this->company_name($s->ID)).'</strong></td><td>'.number_format($today['gross'],2).'</td><td>'.number_format($d7['gross'],2).'</td><td>'.number_format($mo['gross'],2).'</td><td>'.number_format($mo['commission'],2).'</td><td>'.number_format($mo['gross']-$mo['commission'],2).'</td><td>'.$mo['orders'].'</td></tr>';}
        echo '</table></div>';$this->footer();
    }

    public function performance() {
        if (!current_user_can('eldokan_backoffice_view_reports')) wp_die('Not allowed');
        $this->header('Seller Performance','Performance shows the manual ElDokan rating and the automatic cancellation rating as two separate values.');
        echo '<div class="eda-card"><table class="eda-table"><tr><th>' . esc_html__('Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Today Orders', 'eldokan-admin') . '</th><th>' . esc_html__('7d Orders', 'eldokan-admin') . '</th><th>' . esc_html__('30d Orders', 'eldokan-admin') . '</th><th>' . esc_html__('30d Sales', 'eldokan-admin') . '</th><th>' . esc_html__('30d Cancels', 'eldokan-admin') . '</th><th>' . esc_html__('Cancel Status', 'eldokan-admin') . '</th><th>' . esc_html__('Admin Rating', 'eldokan-admin') . '</th><th>' . esc_html__('Cancel Rating (Auto)', 'eldokan-admin') . '</th></tr>';
        foreach($this->owner_users() as $s){
            $td=$this->seller_order_metrics($s->ID,...$this->period('today'));
            $d7=$this->seller_order_metrics($s->ID,...$this->period('7d'));
            $d30=$this->seller_order_metrics($s->ID,...$this->period('30d'));
            $auto=$this->automatic_cancel_rating($s->ID,'30d');
            $rating=get_user_meta($s->ID,'_eldokan_admin_rating',true)?:'—';
            echo '<tr><td><a href="'.esc_url(admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$s->ID)).'"><strong>'.esc_html($this->company_name($s->ID)).'</strong></a></td><td>'.$td['orders'].'</td><td>'.$d7['orders'].'</td><td>'.$d30['orders'].'</td><td>'.number_format($d30['gross'],2).' EGP</td><td>'.$d30['cancelled'].'</td><td>'.$this->badge($auto['label']).'</td><td>'.$rating.($rating==='—'?'':'/5').'</td><td>'.($auto['rating']===null?'—':absint($auto['rating']).'/5').'</td></tr>';
        }
        echo '</table></div>';$this->footer();
    }

    private function fee_rule_snapshot_from_item($item) {
        if (!$item || !is_a($item, 'WC_Order_Item_Product')) return null;
        $type = sanitize_key((string)$item->get_meta('_eldokan_fee_snapshot_type', true));
        if (!in_array($type, ['percentage','fixed','hybrid'], true)) return null;
        $rule = new stdClass();
        $rule->calculation_type = $type;
        $rule->value = max(0, (float)$item->get_meta('_eldokan_fee_snapshot_value', true));
        $rule->fixed_value = max(0, (float)$item->get_meta('_eldokan_fee_snapshot_fixed', true));
        $rule->scope = sanitize_key((string)$item->get_meta('_eldokan_fee_snapshot_scope', true));
        $rule->target_type = sanitize_key((string)$item->get_meta('_eldokan_fee_snapshot_target_type', true));
        $rule->target_id = absint($item->get_meta('_eldokan_fee_snapshot_target_id', true));
        $rule->source = 'order_item_snapshot';
        return $rule;
    }

    public function snapshot_order_item_fee_rule($item, $cart_item_key, $values, $order) {
        if (!$item || !is_a($item, 'WC_Order_Item_Product')) return;
        $product_id = absint($item->get_product_id());
        if (!$product_id) return;
        $seller_id = $this->item_seller_id($item);
        if (!$seller_id) $seller_id = $this->product_owner_id($product_id);
        $rule = $this->effective_rule($seller_id, $product_id);
        if (!$rule) return;
        $item->add_meta_data('_eldokan_fee_snapshot_type', sanitize_key((string)$rule->calculation_type), true);
        $item->add_meta_data('_eldokan_fee_snapshot_value', (string)max(0,(float)$rule->value), true);
        $item->add_meta_data('_eldokan_fee_snapshot_fixed', (string)max(0,(float)($rule->fixed_value ?? 0)), true);
        $item->add_meta_data('_eldokan_fee_snapshot_scope', sanitize_key((string)($rule->scope ?? 'global')), true);
        $item->add_meta_data('_eldokan_fee_snapshot_target_type', sanitize_key((string)($rule->target_type ?? 'all')), true);
        $item->add_meta_data('_eldokan_fee_snapshot_target_id', (string)absint($rule->target_id ?? 0), true);
        $item->add_meta_data('_eldokan_fee_snapshot_at', current_time('mysql'), true);
    }

    private function commission_amount_from_rule($rule, $base) {
        if (!$rule) return 0.0;
        $base = max(0, (float)$base);
        $value = max(0, (float)($rule->value ?? 0));
        $fixed = max(0, (float)($rule->fixed_value ?? 0));
        $type = sanitize_key((string)($rule->calculation_type ?? 'percentage'));
        if ($type === 'fixed') return min($base, $value);
        $pct = $base * min(100, $value) / 100;
        if ($type === 'hybrid') return min($base, $pct + $fixed);
        return max(0, $pct);
    }

    private function seller_financial_report_rows($seller_id, $date_from='', $date_to='') {
        $seller_id = absint($seller_id);
        if (!$seller_id || !function_exists('wc_get_orders')) return [];

        $from_ts = ($date_from && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_from)) ? strtotime($date_from.' 00:00:00') : null;
        $to_ts   = ($date_to && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_to)) ? strtotime($date_to.' 23:59:59') : null;
        $args = ['limit'=>200,'paged'=>1,'paginate'=>true,'orderby'=>'date','order'=>'DESC','return'=>'objects'];
        if ($from_ts && $to_ts) $args['date_created'] = $from_ts.'...'.$to_ts;
        elseif ($from_ts) $args['date_created'] = '>='.$from_ts;
        elseif ($to_ts) $args['date_created'] = '<='.$to_ts;

        $out=[];
        do {
            $result = wc_get_orders($args);
            $orders = is_object($result) && isset($result->orders) ? $result->orders : (array)$result;
            foreach ($orders as $order) {
                if (!$order || !is_a($order,'WC_Order') || is_a($order,'WC_Order_Refund')) continue;
                $status = sanitize_key((string)$order->get_status());
                $excluded = in_array($status,['cancelled','refunded','failed','rejected','returned'],true);
                $is_paid_sale = !$excluded && (bool)$order->is_paid();
                $created = $order->get_date_created();
                $date = $created ? $created->date('Y-m-d H:i:s') : '';

                foreach ($order->get_items('line_item') as $item_id=>$item) {
                    if ($this->item_seller_id($item) !== $this->owner_id($seller_id)) continue;
                    $product_id = absint($item->get_product_id());
                    $qty = max(0,(float)$item->get_quantity());
                    $line_ex_tax = max(0,(float)$item->get_total());
                    $line_tax = max(0,(float)$item->get_total_tax());
                    $ordered_value = $line_ex_tax + $line_tax;

                    $refund_ex_tax = abs((float)$order->get_total_refunded_for_item($item_id,'line_item'));
                    $refund_tax = 0.0;
                    if (method_exists($order,'get_tax_refunded_for_item')) {
                        $taxes=(array)$item->get_taxes();
                        foreach ((array)($taxes['total']??[]) as $tax_id=>$tax_amount) {
                            $refund_tax += abs((float)$order->get_tax_refunded_for_item($item_id,$tax_id,'line_item'));
                        }
                    }
                    $realized_sales = $is_paid_sale ? max(0,$ordered_value-$refund_ex_tax-$refund_tax) : 0.0;
                    $commission_base = $is_paid_sale ? max(0,$line_ex_tax-$refund_ex_tax) : 0.0;

                    $snapshot_rule = $this->fee_rule_snapshot_from_item($item);
                    $rule = $snapshot_rule ?: $this->effective_rule($seller_id,$product_id);
                    $commission = $is_paid_sale ? $this->commission_amount_from_rule($rule,$commission_base) : 0.0;
                    $commission = min($realized_sales,max(0,$commission));
                    $seller_net = max(0,$realized_sales-$commission);
                    $cats=$product_id?wp_get_post_terms($product_id,'product_cat',['fields'=>'names']):[]; if(is_wp_error($cats))$cats=[];
                    $product=$item->get_product();

                    $out[]=[
                        'order_id'=>(int)$order->get_id(),'order_item_id'=>(int)$item_id,'date'=>$date,
                        'product_id'=>$product_id,'product'=>(string)$item->get_name(),'sku'=>$product?(string)$product->get_sku():'',
                        'category'=>implode(', ',(array)$cats),'qty'=>$qty,'gross'=>$ordered_value,
                        'realized_sales'=>$realized_sales,
                        'cancelled_value'=>in_array($status,['cancelled','failed','rejected'],true)?$ordered_value:0.0,
                        'refunded_value'=>$status==='refunded'?max($ordered_value,$refund_ex_tax+$refund_tax):($refund_ex_tax+$refund_tax),
                        'fee_type'=>$rule?$this->commission_type_label($rule->calculation_type):'No fee',
                        'fee_rule'=>$rule?$this->commission_rule_value_label($rule):'0',
                        'fee_source'=>$snapshot_rule?'Snapshot':'Legacy/current rule',
                        'commission'=>$commission,'seller_net'=>$seller_net,'status'=>$status,
                        'customer'=>trim($order->get_formatted_billing_full_name()),
                        'payment'=>$order->get_payment_method_title()?:$order->get_payment_method(),
                        'is_cancelled'=>in_array($status,['cancelled','failed','rejected'],true)?1:0,
                        'is_refunded'=>($status==='refunded'||$refund_ex_tax>0||$refund_tax>0)?1:0,
                        'is_realized'=>$is_paid_sale?1:0,'is_paid'=>$order->is_paid()?1:0,
                    ];
                }
            }
            $max_pages = is_object($result) && isset($result->max_num_pages) ? (int)$result->max_num_pages : 1;
            $args['paged']++;
        } while ($args['paged'] <= $max_pages);
        return $out;
    }

    private function seller_financial_report_summary($rows) {
        $s=['gross_ordered'=>0.0,'sales'=>0.0,'commission'=>0.0,'seller_net'=>0.0,'cancelled_value'=>0.0,'refunded_value'=>0.0,
            'orders'=>0,'units'=>0.0,'cancelled_orders'=>0,'refunded_orders'=>0,'successful_orders'=>0,'unpaid_orders'=>0,
            'average_order_value'=>0.0,'success_rate'=>0.0,'return_rate'=>0.0,'cancel_rate'=>0.0,'reconciliation_difference'=>0.0];
        $orders=[];
        foreach((array)$rows as $r){
            $s['gross_ordered']+=(float)$r['gross']; $s['sales']+=(float)$r['realized_sales']; $s['commission']+=(float)$r['commission'];
            $s['seller_net']+=(float)$r['seller_net']; $s['cancelled_value']+=(float)$r['cancelled_value']; $s['refunded_value']+=(float)$r['refunded_value'];
            if(!empty($r['is_realized']))$s['units']+=(float)$r['qty'];
            $oid=absint($r['order_id']); if(!$oid)continue;
            if(!isset($orders[$oid]))$orders[$oid]=['cancelled'=>0,'refunded'=>0,'realized'=>0,'paid'=>0];
            if(!empty($r['is_cancelled']))$orders[$oid]['cancelled']=1;
            if(!empty($r['is_refunded']))$orders[$oid]['refunded']=1;
            if(!empty($r['is_realized']))$orders[$oid]['realized']=1;
            if(!empty($r['is_paid']))$orders[$oid]['paid']=1;
        }
        $s['orders']=count($orders);
        foreach($orders as $o){
            if($o['cancelled'])$s['cancelled_orders']++;
            elseif($o['refunded']&&!$o['realized'])$s['refunded_orders']++;
            elseif($o['realized'])$s['successful_orders']++;
            elseif(!$o['paid'])$s['unpaid_orders']++;
        }
        if($s['successful_orders']>0)$s['average_order_value']=$s['sales']/$s['successful_orders'];
        if($s['orders']>0){$s['success_rate']=$s['successful_orders']/$s['orders']*100;$s['cancel_rate']=$s['cancelled_orders']/$s['orders']*100;$s['return_rate']=$s['refunded_orders']/$s['orders']*100;}
        foreach(['gross_ordered','sales','commission','seller_net','cancelled_value','refunded_value','average_order_value'] as $k)$s[$k]=round($s[$k],2);
        $s['reconciliation_difference']=round($s['sales']-$s['commission']-$s['seller_net'],2);
        $s['reconciled']=abs($s['reconciliation_difference'])<0.01;
        return $s;
    }

    private function seller_report_rating_data($seller_id) {
        $seller_id = absint($seller_id);
        $product_ids = $this->seller_product_ids($seller_id, 500);
        $counts = [1=>0,2=>0,3=>0,4=>0,5=>0];
        $weighted = 0.0; $reviews = 0;
        if (function_exists('wc_get_product')) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                $rating_counts = (array)$product->get_rating_counts();
                foreach ($counts as $star => $unused) {
                    $c = absint($rating_counts[$star] ?? 0);
                    $counts[$star] += $c;
                    $reviews += $c;
                    $weighted += $star * $c;
                }
            }
        }
        $customer_average = $reviews ? ($weighted / $reviews) : 0.0;
        $manual = absint(get_user_meta($seller_id, '_eldokan_admin_rating', true));
        $manual_reason = (string)get_user_meta($seller_id, '_eldokan_admin_rating_reason', true);
        $auto = $this->automatic_cancel_rating($seller_id, '30d');
        return [
            'customer_average'=>$customer_average,'reviews'=>$reviews,'distribution'=>$counts,
            'admin_rating'=>$manual,'admin_reason'=>$manual_reason,'cancel_rating'=>$auto['rating'],
            'cancel_label'=>$auto['label'] ?? '—',
        ];
    }

    private function seller_report_cancel_reason($order_id) {
        $order_id = absint($order_id);
        if (!$order_id || !function_exists('wc_get_order')) return 'No reason recorded';
        $order = wc_get_order($order_id);
        if (!$order) return 'No reason recorded';
        foreach (['_eldokan_cancel_reason','_eldokan_reject_reason','_eldokan_seller_reject_reason','cancel_reason','_cancel_reason'] as $key) {
            $v = trim(wp_strip_all_tags((string)$order->get_meta($key, true)));
            if ($v !== '') return mb_substr($v, 0, 220);
        }
        if (function_exists('wc_get_order_notes')) {
            $notes = wc_get_order_notes(['order_id'=>$order_id,'limit'=>20,'orderby'=>'date_created','order'=>'DESC']);
            foreach ((array)$notes as $note) {
                $txt = trim(wp_strip_all_tags((string)($note->content ?? '')));
                if ($txt !== '' && preg_match('/cancel|reject|رفض|الغاء|إلغاء|ملغي/ui', $txt)) return mb_substr($txt,0,220);
            }
        }
        return 'No reason recorded';
    }

    private function seller_report_cancellations($rows) {
        $orders = [];
        foreach ((array)$rows as $r) {
            if (empty($r['is_cancelled'])) continue;
            $oid = absint($r['order_id']);
            if (!$oid) continue;
            if (!isset($orders[$oid])) $orders[$oid] = ['order_id'=>$oid,'date'=>$r['date'],'value'=>0.0,'reason'=>''];
            $orders[$oid]['value'] += (float)$r['gross'];
        }
        $reason_counts = [];
        foreach ($orders as &$o) {
            $o['reason'] = $this->seller_report_cancel_reason($o['order_id']);
            $reason_counts[$o['reason']] = ($reason_counts[$o['reason']] ?? 0) + 1;
        }
        unset($o);
        arsort($reason_counts);
        return ['orders'=>array_values($orders),'reasons'=>$reason_counts];
    }

    private function seller_report_products($seller_id, $rows) {
        $seller_id = absint($seller_id);
        $sales = [];
        foreach ((array)$rows as $r) {
            $pid = absint($r['product_id']);
            if (!$pid) continue;
            if (!isset($sales[$pid])) $sales[$pid] = ['units'=>0.0,'revenue'=>0.0,'commission'=>0.0,'seller_net'=>0.0,'cancelled'=>0.0,'refunded'=>0.0,'last_sale'=>''];
            if (!empty($r['is_realized'])) {
                $sales[$pid]['units'] += (float)$r['qty'];
                $sales[$pid]['revenue'] += (float)$r['realized_sales'];
                $sales[$pid]['commission'] += (float)$r['commission'];
                $sales[$pid]['seller_net'] += (float)$r['seller_net'];
                if ($sales[$pid]['last_sale'] === '' || $r['date'] > $sales[$pid]['last_sale']) $sales[$pid]['last_sale'] = $r['date'];
            }
            if (!empty($r['is_cancelled'])) $sales[$pid]['cancelled'] += (float)$r['qty'];
            if (!empty($r['is_refunded'])) $sales[$pid]['refunded'] += (float)$r['qty'];
        }
        $product_ids = $this->seller_product_ids($seller_id, 500);
        $out=[]; $summary=['total'=>0,'live'=>0,'pending'=>0,'draft'=>0,'private'=>0,'in_stock'=>0,'out_of_stock'=>0,'sold_products'=>0,'never_sold'=>0,'sell_through'=>0.0];
        foreach ($product_ids as $pid) {
            $post = get_post($pid); if (!$post) continue;
            $status = (string)$post->post_status;
            $p = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $stock_status = $p ? (string)$p->get_stock_status() : '';
            $row = $sales[$pid] ?? ['units'=>0.0,'revenue'=>0.0,'commission'=>0.0,'seller_net'=>0.0,'cancelled'=>0.0,'refunded'=>0.0,'last_sale'=>''];
            $cats = wp_get_post_terms($pid,'product_cat',['fields'=>'names']); if (is_wp_error($cats)) $cats=[];
            $out[] = array_merge($row,[
                'product_id'=>$pid,'product'=>get_the_title($pid),'sku'=>$p ? (string)$p->get_sku() : '',
                'status'=>$status,'stock_status'=>$stock_status,'category'=>implode(', ',(array)$cats),
                'price'=>$p ? (float)$p->get_price() : 0.0,'created_at'=>(string)$post->post_date,
            ]);
            $summary['total']++;
            if ($status==='publish') $summary['live']++; elseif ($status==='pending') $summary['pending']++; elseif ($status==='draft') $summary['draft']++; elseif ($status==='private') $summary['private']++;
            if ($stock_status==='outofstock') $summary['out_of_stock']++; else $summary['in_stock']++;
            if ((float)$row['units'] > 0) $summary['sold_products']++; else $summary['never_sold']++;
        }
        if ($summary['total']) $summary['sell_through'] = ($summary['sold_products']/$summary['total'])*100;
        usort($out, fn($a,$b)=>(float)$b['revenue'] <=> (float)$a['revenue']);
        return ['rows'=>$out,'summary'=>$summary];
    }

    private function seller_report_category_summary($product_rows) {
        $cats=[];
        foreach ((array)$product_rows as $p) {
            $names = array_filter(array_map('trim', explode(',', (string)$p['category'])));
            if (!$names) $names=['Uncategorized'];
            foreach ($names as $name) {
                if (!isset($cats[$name])) $cats[$name]=['category'=>$name,'products'=>0,'units'=>0.0,'revenue'=>0.0,'commission'=>0.0,'seller_net'=>0.0];
                $cats[$name]['products']++;
                $cats[$name]['units'] += (float)$p['units'];
                $cats[$name]['revenue'] += (float)$p['revenue'];
                $cats[$name]['commission'] += (float)$p['commission'];
                $cats[$name]['seller_net'] += (float)$p['seller_net'];
            }
        }
        $out=array_values($cats); usort($out,fn($a,$b)=>(float)$b['revenue'] <=> (float)$a['revenue']); return $out;
    }

    private function seller_report_trend($rows) {
        $trend=[];
        foreach ((array)$rows as $r) {
            $d = substr((string)$r['date'],0,7); if (!preg_match('/^\d{4}-\d{2}$/',$d)) continue;
            if (!isset($trend[$d])) $trend[$d]=['period'=>$d,'sales'=>0.0,'commission'=>0.0,'seller_net'=>0.0,'orders'=>[]];
            $trend[$d]['sales'] += (float)$r['realized_sales'];
            $trend[$d]['commission'] += (float)$r['commission'];
            $trend[$d]['seller_net'] += (float)$r['seller_net'];
            if (!empty($r['is_realized'])) $trend[$d]['orders'][absint($r['order_id'])]=1;
        }
        ksort($trend);
        foreach ($trend as &$t) {$t['orders']=count($t['orders']);} unset($t);
        return array_values($trend);
    }

    private function seller_report_performance_score($summary, $products, $ratings) {
        $rating = (float)($ratings['customer_average'] ?? 0);
        if ($rating <= 0 && !empty($ratings['admin_rating'])) $rating = (float)$ratings['admin_rating'];
        $rating_score = $rating > 0 ? min(100, ($rating/5)*100) : 70;
        $cancel_score = max(0, 100-(float)($summary['cancel_rate'] ?? 0));
        $return_score = max(0, 100-(float)($summary['return_rate'] ?? 0));
        $sell_score = min(100, max(0, (float)($products['summary']['sell_through'] ?? 0)));
        return round(($rating_score*0.35)+($cancel_score*0.30)+($return_score*0.20)+($sell_score*0.15),1);
    }

    public function reports() {
        if (!current_user_can('eldokan_backoffice_view_reports')) wp_die('Not allowed');
        $sellers = $this->owner_users();
        $seller_id = absint($_GET['seller_id'] ?? 0);
        $date_from = sanitize_text_field(wp_unslash($_GET['date_from'] ?? date_i18n('Y-m-01')));
        $date_to = sanitize_text_field(wp_unslash($_GET['date_to'] ?? date_i18n('Y-m-d')));

        $this->header('Seller Intelligence Reports','One seller at a time: financials, orders, cancellations, ratings, products, categories and performance.');
        echo '<div class="eda-card"><form method="get" class="eda-form">';
        echo '<input type="hidden" name="page" value="eldokan-admin-reports">';
        echo '<label>' . esc_html__('Seller', 'eldokan-admin') . '<select name="seller_id" required><option value="">' . esc_html__('Select seller', 'eldokan-admin') . '</option>';
        foreach ($sellers as $seller) echo '<option value="'.absint($seller->ID).'" '.selected($seller_id,$seller->ID,false).'>'.esc_html($this->company_name($seller->ID)).' — '.esc_html($seller->user_email).'</option>';
        echo '</select></label><label>' . esc_html__('From', 'eldokan-admin') . '<input type="date" name="date_from" value="'.esc_attr($date_from).'"></label><label>' . esc_html__('To', 'eldokan-admin') . '<input type="date" name="date_to" value="'.esc_attr($date_to).'"></label><div style="align-self:end"><button class="button button-primary">' . esc_html__('View Report', 'eldokan-admin') . '</button></div></form></div>';
        if (!$seller_id) { echo '<div class="eda-card"><p>' . esc_html__('Select a seller to open the complete report.', 'eldokan-admin') . '</p></div>'; $this->footer(); return; }
        $seller=get_userdata($seller_id); if(!$seller){echo '<div class="eda-card"><p>' . esc_html__('Seller not found.', 'eldokan-admin') . '</p></div>'; $this->footer(); return;}

        $rows=$this->seller_financial_report_rows($seller_id,$date_from,$date_to);
        $sum=$this->seller_financial_report_summary($rows);
        $ratings=$this->seller_report_rating_data($seller_id);
        $cancels=$this->seller_report_cancellations($rows);
        $products=$this->seller_report_products($seller_id,$rows);
        $categories=$this->seller_report_category_summary($products['rows']);
        $trend=$this->seller_report_trend($rows);
        $score=$this->seller_report_performance_score($sum,$products,$ratings);
        $export_url=wp_nonce_url(add_query_arg(['action'=>'eldokan_admin_export_seller_report','seller_id'=>$seller_id,'date_from'=>$date_from,'date_to'=>$date_to],admin_url('admin-post.php')),'eda_export_seller_report_'.$seller_id);
        $pdf_url=wp_nonce_url(add_query_arg(['action'=>'eldokan_admin_export_seller_report_pdf','seller_id'=>$seller_id,'date_from'=>$date_from,'date_to'=>$date_to],admin_url('admin-post.php')),'eda_export_seller_report_pdf_'.$seller_id);

        echo '<div class="eda-section-head"><div><h2>'.esc_html($this->company_name($seller_id)).'</h2><p>'.esc_html($seller->user_email).' · '.esc_html($date_from).' → '.esc_html($date_to).'</p></div><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="button" href="'.esc_url($pdf_url).'">' . esc_html__('Download PDF', 'eldokan-admin') . '</a><a class="button button-primary" href="'.esc_url($export_url).'">' . esc_html__('Download Excel', 'eldokan-admin') . '</a></div></div>';
        echo '<div class="eda-grid">';
        $cards=[['Net Sales',number_format($sum['sales'],2).' EGP'],['Platform Commission',number_format($sum['commission'],2).' EGP'],['Seller Net Proceeds',number_format($sum['seller_net'],2).' EGP'],
            ['Reconciliation',$sum['reconciled']?'OK · 0.00 EGP':'CHECK · '.number_format($sum['reconciliation_difference'],2).' EGP'],['Orders',$sum['orders']],['Units Sold',$sum['units']],['Performance Score',$score.'/100'],['Cancel Rate',number_format($sum['cancel_rate'],1).'%'],['Return Rate',number_format($sum['return_rate'],1).'%'],['Reconciliation',$sum['reconciled']?'OK · 0.00 EGP':'CHECK · '.number_format($sum['reconciliation_difference'],2).' EGP']];
        foreach($cards as $c) echo '<div class="eda-card"><h3>'.esc_html($c[0]).'</h3><p><b>'.esc_html($c[1]).'</b></p></div>';
        echo '</div>';

        echo '<div class="eda-two">';
        echo '<div class="eda-card"><h2>' . esc_html__('Ratings', 'eldokan-admin') . '</h2><p><b>' . esc_html__('Customer product rating:', 'eldokan-admin') . '</b> '.($ratings['reviews']?number_format($ratings['customer_average'],2).'/5':'No reviews').' <small>('.absint($ratings['reviews']).' reviews)</small></p><p><b>' . esc_html__('Admin rating:', 'eldokan-admin') . '</b> '.($ratings['admin_rating']?absint($ratings['admin_rating']).'/5':'Not rated').'</p><p><b>' . esc_html__('Automatic cancel rating:', 'eldokan-admin') . '</b> '.($ratings['cancel_rating']===null?'—':absint($ratings['cancel_rating']).'/5').' · '.esc_html($ratings['cancel_label']).'</p><table class="eda-table"><tr><th>' . esc_html__('Stars', 'eldokan-admin') . '</th><th>' . esc_html__('Reviews', 'eldokan-admin') . '</th></tr>';
        for($star=5;$star>=1;$star--) echo '<tr><td>'.$star.' ★</td><td>'.absint($ratings['distribution'][$star]).'</td></tr>';
        echo '</table></div>';
        echo '<div class="eda-card"><h2>' . esc_html__('Order Health', 'eldokan-admin') . '</h2><p><b>' . esc_html__('Gross Order Value (GOV):', 'eldokan-admin') . '</b> '.number_format($sum['gross_ordered'],2).' EGP</p><p><b>' . esc_html__('Net Sales:', 'eldokan-admin') . '</b> '.number_format($sum['sales'],2).' EGP</p><p><b>' . esc_html__('Successful/non-cancelled orders:', 'eldokan-admin') . '</b> '.absint($sum['successful_orders']).'</p><p><b>' . esc_html__('Cancelled:', 'eldokan-admin') . '</b> '.absint($sum['cancelled_orders']).' ('.number_format($sum['cancel_rate'],1).'%)</p><p><b>' . esc_html__('Refunded:', 'eldokan-admin') . '</b> '.absint($sum['refunded_orders']).' ('.number_format($sum['return_rate'],1).'%)</p><p><b>' . esc_html__('Lost Sales Value:', 'eldokan-admin') . '</b> '.number_format($sum['cancelled_value'],2).' EGP</p><p><b>' . esc_html__('Refunded Sales Value:', 'eldokan-admin') . '</b> '.number_format($sum['refunded_value'],2).' EGP</p><p><b>' . esc_html__('Unpaid/non-realized:', 'eldokan-admin') . '</b> '.absint($sum['unpaid_orders']).'</p><p><b>' . esc_html__('Average Order Value (AOV):', 'eldokan-admin') . '</b> '.number_format($sum['average_order_value'],2).' EGP</p><p><b>' . esc_html__('Money reconciliation:', 'eldokan-admin') . '</b> '.($sum['reconciled']?'OK — 0.00 EGP':'CHECK — '.number_format($sum['reconciliation_difference'],2).' EGP').'</p></div>';
        echo '</div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Products Overview', 'eldokan-admin') . '</h2><div class="eda-grid">';
        foreach([['Total Products',$products['summary']['total']],['Live',$products['summary']['live']],['Pending',$products['summary']['pending']],['Draft',$products['summary']['draft']],['In Stock',$products['summary']['in_stock']],['Out of Stock',$products['summary']['out_of_stock']],['Products Sold',$products['summary']['sold_products']],['Never Sold',$products['summary']['never_sold']],['Sell-through',number_format($products['summary']['sell_through'],1).'%']] as $c) echo '<div class="eda-mini"><b>'.esc_html($c[0]).'</b><br>'.esc_html($c[1]).'</div>';
        echo '</div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Product Performance', 'eldokan-admin') . '</h2><div class="eda-table-scroll"><table class="eda-table"><tr><th>' . esc_html__('Product', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Stock', 'eldokan-admin') . '</th><th>' . esc_html__('Category', 'eldokan-admin') . '</th><th>' . esc_html__('Units', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Platform Commission', 'eldokan-admin') . '</th><th>' . esc_html__('Seller Net Proceeds', 'eldokan-admin') . '</th><th>' . esc_html__('Cancelled Units', 'eldokan-admin') . '</th><th>' . esc_html__('Refunded Units', 'eldokan-admin') . '</th><th>' . esc_html__('Last Sale', 'eldokan-admin') . '</th></tr>';
        if(!$products['rows']) echo '<tr><td colspan="12">' . esc_html__('No products found for this seller.', 'eldokan-admin') . '</td></tr>';
        foreach($products['rows'] as $p) echo '<tr><td><strong>'.esc_html($p['product']).'</strong><br><small>#'.absint($p['product_id']).' · '.esc_html($p['sku']).'</small></td><td>'.$this->badge($p['status']).'</td><td>'.esc_html($p['stock_status']?:'—').'</td><td>'.esc_html($p['category']?:'—').'</td><td>'.esc_html($p['units']).'</td><td>'.number_format($p['revenue'],2).' EGP</td><td>'.number_format($p['commission'],2).' EGP</td><td>'.number_format($p['seller_net'],2).' EGP</td><td>'.esc_html($p['cancelled']).'</td><td>'.esc_html($p['refunded']).'</td><td>'.esc_html($p['last_sale']?:'Never').'</td></tr>';
        echo '</table></div></div>';

        echo '<div class="eda-two"><div class="eda-card"><h2>' . esc_html__('Top Categories', 'eldokan-admin') . '</h2><table class="eda-table"><tr><th>' . esc_html__('Category', 'eldokan-admin') . '</th><th>' . esc_html__('Products', 'eldokan-admin') . '</th><th>' . esc_html__('Units', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Platform Commission', 'eldokan-admin') . '</th></tr>';
        foreach(array_slice($categories,0,15) as $c) echo '<tr><td>'.esc_html($c['category']).'</td><td>'.absint($c['products']).'</td><td>'.esc_html($c['units']).'</td><td>'.number_format($c['revenue'],2).' EGP</td><td>'.number_format($c['commission'],2).' EGP</td></tr>';
        if(!$categories) echo '<tr><td colspan="5">' . esc_html__('No category data.', 'eldokan-admin') . '</td></tr>';
        echo '</table></div><div class="eda-card"><h2>' . esc_html__('Net Sales Trend', 'eldokan-admin') . '</h2><table class="eda-table"><tr><th>' . esc_html__('Month', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Platform Commission', 'eldokan-admin') . '</th><th>' . esc_html__('Seller Net Proceeds', 'eldokan-admin') . '</th><th>' . esc_html__('Orders', 'eldokan-admin') . '</th></tr>';
        foreach($trend as $t) echo '<tr><td>'.esc_html($t['period']).'</td><td>'.number_format($t['sales'],2).'</td><td>'.number_format($t['commission'],2).'</td><td>'.number_format($t['seller_net'],2).'</td><td>'.absint($t['orders']).'</td></tr>';
        if(!$trend) echo '<tr><td colspan="5">' . esc_html__('No sales trend data.', 'eldokan-admin') . '</td></tr>';
        echo '</table></div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Cancellations & Reasons', 'eldokan-admin') . '</h2><div class="eda-table-scroll"><table class="eda-table"><tr><th>' . esc_html__('Order', 'eldokan-admin') . '</th><th>' . esc_html__('Date', 'eldokan-admin') . '</th><th>' . esc_html__('Lost Value', 'eldokan-admin') . '</th><th>' . esc_html__('Reason', 'eldokan-admin') . '</th></tr>';
        if(!$cancels['orders']) echo '<tr><td colspan="4">' . esc_html__('No cancelled orders in this period.', 'eldokan-admin') . '</td></tr>';
        foreach($cancels['orders'] as $c) echo '<tr><td>#'.absint($c['order_id']).'</td><td>'.esc_html($c['date']).'</td><td>'.number_format($c['value'],2).' EGP</td><td>'.esc_html($c['reason']).'</td></tr>';
        echo '</table></div><h3>' . esc_html__('Reason Summary', 'eldokan-admin') . '</h3><table class="eda-table"><tr><th>' . esc_html__('Reason', 'eldokan-admin') . '</th><th>' . esc_html__('Count', 'eldokan-admin') . '</th></tr>';
        foreach($cancels['reasons'] as $reason=>$count) echo '<tr><td>'.esc_html($reason).'</td><td>'.absint($count).'</td></tr>';
        if(!$cancels['reasons']) echo '<tr><td colspan="2">' . esc_html__('No cancellation reasons.', 'eldokan-admin') . '</td></tr>';
        echo '</table></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Order & Financial Detail', 'eldokan-admin') . '</h2><div class="eda-table-scroll"><table class="eda-table"><tr><th>' . esc_html__('Order', 'eldokan-admin') . '</th><th>' . esc_html__('Date', 'eldokan-admin') . '</th><th>' . esc_html__('Product', 'eldokan-admin') . '</th><th>' . esc_html__('Category', 'eldokan-admin') . '</th><th>' . esc_html__('Qty', 'eldokan-admin') . '</th><th>' . esc_html__('Order Line Value', 'eldokan-admin') . '</th><th>' . esc_html__('Net Sales', 'eldokan-admin') . '</th><th>' . esc_html__('Commission Rule', 'eldokan-admin') . '</th><th>' . esc_html__('Commission Source', 'eldokan-admin') . '</th><th>' . esc_html__('Platform Commission', 'eldokan-admin') . '</th><th>' . esc_html__('Seller Net Proceeds', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th></tr>';
        if(!$rows) echo '<tr><td colspan="12">' . esc_html__('No seller order lines found for this period. If orders exist, rebuild the WCFM Data Index first.', 'eldokan-admin') . '</td></tr>';
        foreach($rows as $r) echo '<tr><td>#'.absint($r['order_id']).'</td><td>'.esc_html($r['date']).'</td><td><strong>'.esc_html($r['product']).'</strong><br><small>'.esc_html($r['sku']).'</small></td><td>'.esc_html($r['category']?:'—').'</td><td>'.esc_html($r['qty']).'</td><td>'.number_format($r['gross'],2).' EGP</td><td>'.number_format($r['realized_sales'],2).' EGP</td><td>'.esc_html($r['fee_rule']).'<br><small>'.esc_html($r['fee_type']).'</small></td><td>'.esc_html($r['fee_source']??'—').'</td><td>'.number_format($r['commission'],2).' EGP</td><td>'.number_format($r['seller_net'],2).' EGP</td><td>'.$this->badge($r['status']).'</td></tr>';
        echo '</table></div></div>';
        $this->footer();
    }

    public function export_seller_report_pdf() {
        if (!current_user_can('eldokan_backoffice_export_reports')) wp_die('Not allowed');
        $seller_id=absint($_GET['seller_id']??0); check_admin_referer('eda_export_seller_report_pdf_'.$seller_id);
        $date_from=sanitize_text_field(wp_unslash($_GET['date_from']??''));
        $date_to=sanitize_text_field(wp_unslash($_GET['date_to']??''));
        $seller=get_userdata($seller_id); if(!$seller) wp_die('Seller not found');

        $rows=$this->seller_financial_report_rows($seller_id,$date_from,$date_to);
        $sum=$this->seller_financial_report_summary($rows);
        $ratings=$this->seller_report_rating_data($seller_id);
        $cancels=$this->seller_report_cancellations($rows);
        $products=$this->seller_report_products($seller_id,$rows);
        $categories=$this->seller_report_category_summary($products['rows']);
        $trend=$this->seller_report_trend($rows);
        $score=$this->seller_report_performance_score($sum,$products,$ratings);

        $pdf=new ElDokan_Report_PDF();
        $pdf->title('ElDokan Seller Intelligence Report');
        $pdf->line('Seller: '.$this->company_name($seller_id),11,true);
        $pdf->line('Email: '.$seller->user_email,9);
        $pdf->line('Period: '.$date_from.' to '.$date_to,9);
        $pdf->line('Generated: '.current_time('Y-m-d H:i'),8);
        $pdf->rule();

        $pdf->heading('Executive Summary');
        $pdf->table(['Metric','Value'],[
            ['Gross Order Value (GOV)',number_format($sum['gross_ordered'],2).' EGP'],
            ['Net Sales',number_format($sum['sales'],2).' EGP'],
            ['Platform Commission',number_format($sum['commission'],2).' EGP'],
            ['Seller Net Proceeds',number_format($sum['seller_net'],2).' EGP'],
            ['Reconciliation',$sum['reconciled']?'OK · 0.00 EGP':'CHECK · '.number_format($sum['reconciliation_difference'],2).' EGP'],
            ['Orders',(string)absint($sum['orders'])],
            ['Units Sold',(string)$sum['units']],
            ['Performance Score',$score.'/100'],
            ['Cancellation Rate',number_format($sum['cancel_rate'],1).'%'],
            ['Return Rate',number_format($sum['return_rate'],1).'%'],
            ['Average Order Value',number_format($sum['average_order_value'],2).' EGP'],
        ],[2,1],8.5);

        $pdf->heading('Ratings');
        $pdf->table(['Metric','Value'],[
            ['Customer Product Rating',$ratings['reviews']?number_format($ratings['customer_average'],2).'/5 ('.absint($ratings['reviews']).' reviews)':'No reviews'],
            ['Admin Rating',$ratings['admin_rating']?absint($ratings['admin_rating']).'/5':'Not rated'],
            ['Automatic Cancel Rating',$ratings['cancel_rating']===null?'N/A':absint($ratings['cancel_rating']).'/5 - '.$ratings['cancel_label']],
        ],[2,3],8.5);

        $pdf->heading('Products Overview');
        $ps=$products['summary'];
        $pdf->table(['Metric','Value'],[
            ['Total Products',(string)$ps['total']],['Live',(string)$ps['live']],['Pending',(string)$ps['pending']],['Draft',(string)$ps['draft']],
            ['In Stock',(string)$ps['in_stock']],['Out of Stock',(string)$ps['out_of_stock']],['Products Sold',(string)$ps['sold_products']],
            ['Never Sold',(string)$ps['never_sold']],['Sell-through',number_format($ps['sell_through'],1).'%']
        ],[2,1],8.5);

        $pdf->heading('Product Performance');
        $productRows=[];
        foreach($products['rows'] as $p) $productRows[]=[
            '#'.$p['product_id'].' '.$p['product'], $p['status'], $p['category']?:'-', (string)$p['units'],
            number_format($p['revenue'],2), number_format($p['commission'],2), number_format($p['seller_net'],2), $p['last_sale']?:'Never'
        ];
        $pdf->table(['Product','Status','Category','Units','Revenue','Comm.','Seller Net','Last Sale'],$productRows,[3.0,1,1.5,.7,1,1,1,1.2],6.4);

        $pdf->heading('Top Categories');
        $catRows=[];
        foreach(array_slice($categories,0,15) as $c) $catRows[]=[$c['category'],(string)$c['products'],(string)$c['units'],number_format($c['revenue'],2),number_format($c['commission'],2),number_format($c['seller_net'],2)];
        $pdf->table(['Category','Products','Units','Revenue','Comm.','Seller Net'],$catRows,[2.2,1,1,1.2,1.2,1.2],7);

        $pdf->heading('Monthly Revenue Trend');
        $trendRows=[]; foreach($trend as $t) $trendRows[]=[$t['period'],number_format($t['sales'],2),number_format($t['commission'],2),number_format($t['seller_net'],2),(string)$t['orders']];
        $pdf->table(['Month','Sales','Commission','Seller Net','Orders'],$trendRows,[1.3,1.3,1.3,1.3,1],7.2);

        $pdf->heading('Cancellations and Reasons');
        $cancelRows=[]; foreach($cancels['orders'] as $c) $cancelRows[]=['#'.$c['order_id'],$c['date'],number_format($c['value'],2).' EGP',$c['reason']];
        $pdf->table(['Order','Date','Lost Value','Reason'],$cancelRows,[1,1.4,1.2,4],7);

        $pdf->heading('Order and Financial Detail');
        $detail=[];
        foreach($rows as $r) $detail[]=[
            '#'.$r['order_id'],$r['date'],$r['product'],$r['category']?:'-',(string)$r['qty'],number_format($r['realized_sales'],2),
            $r['fee_rule'],number_format($r['commission'],2),number_format($r['seller_net'],2),$r['status']
        ];
        $pdf->table(['Order','Date','Product','Category','Qty','Net Sale','Fee','Commission','Seller Net','Status'],$detail,[.8,1.1,2.2,1.2,.5,.9,1.2,1,1,1],5.9);

        $safe=sanitize_file_name($this->company_name($seller_id));
        $pdf->output('eldokan-seller-report-'.$safe.'-'.$date_from.'-'.$date_to.'.pdf');
    }

    public function export_seller_report_excel() {
        if (!current_user_can('eldokan_backoffice_export_reports')) wp_die('Not allowed');
        $seller_id=absint($_GET['seller_id']??0); check_admin_referer('eda_export_seller_report_'.$seller_id);
        $date_from=sanitize_text_field(wp_unslash($_GET['date_from']??'')); $date_to=sanitize_text_field(wp_unslash($_GET['date_to']??''));
        $seller=get_userdata($seller_id); if(!$seller) wp_die('Seller not found');
        $rows=$this->seller_financial_report_rows($seller_id,$date_from,$date_to); $sum=$this->seller_financial_report_summary($rows);
        $ratings=$this->seller_report_rating_data($seller_id); $cancels=$this->seller_report_cancellations($rows); $products=$this->seller_report_products($seller_id,$rows); $categories=$this->seller_report_category_summary($products['rows']); $trend=$this->seller_report_trend($rows); $score=$this->seller_report_performance_score($sum,$products,$ratings);
        $safe=sanitize_file_name($this->company_name($seller_id)); $filename='eldokan-seller-complete-report-'.$safe.'-'.$date_from.'-'.$date_to.'.xls';
        nocache_headers(); header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'"'); echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"><style>td,th{padding:5px}th{background:#eee}</style></head><body><h1>ElDokan Complete Seller Report</h1>';
        echo '<table border="1"><tr><th>Seller</th><td>'.esc_html($this->company_name($seller_id)).'</td></tr><tr><th>Email</th><td>'.esc_html($seller->user_email).'</td></tr><tr><th>Period</th><td>'.esc_html($date_from).' to '.esc_html($date_to).'</td></tr><tr><th>Performance Score</th><td>'.esc_html($score).'/100</td></tr></table><br>';
        echo '<h2>Summary</h2><table border="1"><tr><th>Gross Ordered</th><th>Net Sales</th><th>Platform Commission</th><th>Seller Net Proceeds</th><th>Orders</th><th>Units</th><th>Cancelled Orders</th><th>Refunded Orders</th><th>Cancel Rate</th><th>Return Rate</th><th>Reconciliation Difference</th></tr><tr><td>'.number_format($sum['gross_ordered'],2,'.','').'</td><td>'.number_format($sum['sales'],2,'.','').'</td><td>'.number_format($sum['commission'],2,'.','').'</td><td>'.number_format($sum['seller_net'],2,'.','').'</td><td>'.absint($sum['orders']).'</td><td>'.esc_html($sum['units']).'</td><td>'.absint($sum['cancelled_orders']).'</td><td>'.absint($sum['refunded_orders']).'</td><td>'.number_format($sum['cancel_rate'],2,'.','').'%</td><td>'.number_format($sum['return_rate'],2,'.','').'%</td><td>'.number_format($sum['reconciliation_difference'],2,'.','').'</td></tr></table><br>';
        echo '<h2>Ratings</h2><table border="1"><tr><th>Customer Average</th><th>Reviews</th><th>Admin Rating</th><th>Cancel Rating</th></tr><tr><td>'.($ratings['reviews']?number_format($ratings['customer_average'],2,'.',''):'N/A').'</td><td>'.absint($ratings['reviews']).'</td><td>'.($ratings['admin_rating']?absint($ratings['admin_rating']):'N/A').'</td><td>'.($ratings['cancel_rating']===null?'N/A':absint($ratings['cancel_rating'])).'</td></tr></table><br>';
        echo '<h2>Products</h2><table border="1"><tr><th>Product ID</th><th>Product</th><th>SKU</th><th>Status</th><th>Stock</th><th>Category</th><th>Units</th><th>Net Sales</th><th>Platform Commission</th><th>Seller Net Proceeds</th><th>Cancelled Units</th><th>Refunded Units</th><th>Last Sale</th></tr>';
        foreach($products['rows'] as $p) echo '<tr><td>'.absint($p['product_id']).'</td><td>'.esc_html($p['product']).'</td><td>'.esc_html($p['sku']).'</td><td>'.esc_html($p['status']).'</td><td>'.esc_html($p['stock_status']).'</td><td>'.esc_html($p['category']).'</td><td>'.esc_html($p['units']).'</td><td>'.number_format($p['revenue'],2,'.','').'</td><td>'.number_format($p['commission'],2,'.','').'</td><td>'.number_format($p['seller_net'],2,'.','').'</td><td>'.esc_html($p['cancelled']).'</td><td>'.esc_html($p['refunded']).'</td><td>'.esc_html($p['last_sale']).'</td></tr>';
        echo '</table><br><h2>Categories</h2><table border="1"><tr><th>Category</th><th>Products</th><th>Units</th><th>Net Sales</th><th>Platform Commission</th><th>Seller Net Proceeds</th></tr>';
        foreach($categories as $c) echo '<tr><td>'.esc_html($c['category']).'</td><td>'.absint($c['products']).'</td><td>'.esc_html($c['units']).'</td><td>'.number_format($c['revenue'],2,'.','').'</td><td>'.number_format($c['commission'],2,'.','').'</td><td>'.number_format($c['seller_net'],2,'.','').'</td></tr>';
        echo '</table><br><h2>Cancellations</h2><table border="1"><tr><th>Order</th><th>Date</th><th>Lost Value</th><th>Reason</th></tr>';
        foreach($cancels['orders'] as $c) echo '<tr><td>'.absint($c['order_id']).'</td><td>'.esc_html($c['date']).'</td><td>'.number_format($c['value'],2,'.','').'</td><td>'.esc_html($c['reason']).'</td></tr>';
        echo '</table><br><h2>Monthly Trend</h2><table border="1"><tr><th>Month</th><th>Net Sales</th><th>Platform Commission</th><th>Seller Net Proceeds</th><th>Orders</th></tr>';
        foreach($trend as $t) echo '<tr><td>'.esc_html($t['period']).'</td><td>'.number_format($t['sales'],2,'.','').'</td><td>'.number_format($t['commission'],2,'.','').'</td><td>'.number_format($t['seller_net'],2,'.','').'</td><td>'.absint($t['orders']).'</td></tr>';
        echo '</table><br><h2>Order Detail</h2><table border="1"><tr><th>Order</th><th>Date</th><th>Product</th><th>SKU</th><th>Category</th><th>Qty</th><th>Order Line Value</th><th>Net Sales</th><th>Fee Type</th><th>Fee Rule</th><th>Platform Commission</th><th>Seller Net Proceeds</th><th>Status</th><th>Customer</th><th>Payment</th></tr>';
        foreach($rows as $r) echo '<tr><td>'.absint($r['order_id']).'</td><td>'.esc_html($r['date']).'</td><td>'.esc_html($r['product']).'</td><td>'.esc_html($r['sku']).'</td><td>'.esc_html($r['category']).'</td><td>'.esc_html($r['qty']).'</td><td>'.number_format($r['gross'],2,'.','').'</td><td>'.number_format($r['realized_sales'],2,'.','').'</td><td>'.esc_html($r['fee_type']).'</td><td>'.esc_html($r['fee_rule']).'</td><td>'.number_format($r['commission'],2,'.','').'</td><td>'.number_format($r['seller_net'],2,'.','').'</td><td>'.esc_html($r['status']).'</td><td>'.esc_html($r['customer']).'</td><td>'.esc_html($r['payment']).'</td></tr>';
        echo '</table></body></html>'; exit;
    }

    public function activity() {
        if (!current_user_can('eldokan_backoffice_view_activity')) {
            wp_die('Not allowed');
        }

        $this->sync_seller_api_activity();

        global $wpdb;

        $q = sanitize_text_field(wp_unslash($_GET['log_q'] ?? ''));
        $source = sanitize_key($_GET['log_source'] ?? '');
        $action = sanitize_text_field(wp_unslash($_GET['log_action'] ?? ''));
        $actor = absint($_GET['log_actor'] ?? 0);
        $seller = absint($_GET['log_seller'] ?? 0);
        $date_from = sanitize_text_field($_GET['log_from'] ?? '');
        $date_to = sanitize_text_field($_GET['log_to'] ?? '');
        $page_num = max(1, absint($_GET['log_page'] ?? 1));
        $per_page = 100;

        $where = ['1=1'];
        $params = [];

        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = '(action LIKE %s OR description LIKE %s OR actor_name LIKE %s OR actor_email LIKE %s OR object_type LIKE %s OR meta LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($source !== '') {
            $where[] = 'source = %s';
            $params[] = $source;
        }

        if ($action !== '') {
            $where[] = 'action = %s';
            $params[] = $action;
        }

        if ($actor) {
            $where[] = 'actor_user_id = %d';
            $params[] = $actor;
        }

        if ($seller) {
            $where[] = 'seller_user_id = %d';
            $params[] = $seller;
        }

        if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where[] = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where[] = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$this->audit_table} WHERE {$where_sql}";
        $count_prepared = $params ? $wpdb->prepare($count_sql, $params) : $count_sql;
        $total = (int)$wpdb->get_var($count_prepared);
        $pages = max(1, (int)ceil($total / $per_page));
        $page_num = min($page_num, $pages);
        $offset = ($page_num - 1) * $per_page;

        $list_sql = "SELECT * FROM {$this->audit_table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $list_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_params));

        $sources = $wpdb->get_col("SELECT DISTINCT source FROM {$this->audit_table} WHERE source <> '' ORDER BY source");
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM {$this->audit_table} WHERE action <> '' ORDER BY action");

        $this->header(
            'Activity',
            'Complete ElDokan audit trail: Seller API, admin changes, users, products, orders, categories, WCFM index operations and authentication events.'
        );

        echo '<div class="eda-card">';
        echo '<div class="eda-section-head"><div><h2>' . esc_html__('Audit Log', 'eldokan-admin') . '</h2><p>'.number_format_i18n($total).' matching event(s). Logs are stored in a dedicated database table.</p></div>';

        $export_args = [
            'action' => 'eldokan_admin_export_activity',
            'log_q' => $q,
            'log_source' => $source,
            'log_action' => $action,
            'log_actor' => $actor,
            'log_seller' => $seller,
            'log_from' => $date_from,
            'log_to' => $date_to,
        ];
        $export_url = wp_nonce_url(
            add_query_arg($export_args, admin_url('admin-post.php')),
            'eda_export_activity'
        );
        echo '<a class="button button-primary" href="'.esc_url($export_url).'">' . esc_html__('Export CSV', 'eldokan-admin') . '</a></div>';

        echo '<form method="get" class="eda-log-filters">';
        echo '<input type="hidden" name="page" value="eldokan-admin-activity">';
        echo '<label>' . esc_html__('Search', 'eldokan-admin') . '<input type="search" name="log_q" value="'.esc_attr($q).'" placeholder="Action, description, actor, object..."></label>';
        echo '<label>' . esc_html__('Source', 'eldokan-admin') . '<select name="log_source"><option value="">' . esc_html__('All sources', 'eldokan-admin') . '</option>';
        foreach ($sources as $src) {
            echo '<option value="'.esc_attr($src).'" '.selected($source,$src,false).'>'.esc_html(ucwords(str_replace('_',' ',$src))).'</option>';
        }
        echo '</select></label>';
        echo '<label>' . esc_html__('Action', 'eldokan-admin') . '<select name="log_action"><option value="">' . esc_html__('All actions', 'eldokan-admin') . '</option>';
        foreach ($actions as $act) {
            echo '<option value="'.esc_attr($act).'" '.selected($action,$act,false).'>'.esc_html($act).'</option>';
        }
        echo '</select></label>';
        echo '<label>' . esc_html__('Actor User ID', 'eldokan-admin') . '<input type="number" min="0" name="log_actor" value="'.esc_attr($actor ?: '').'"></label>';
        echo '<label>' . esc_html__('Seller User ID', 'eldokan-admin') . '<input type="number" min="0" name="log_seller" value="'.esc_attr($seller ?: '').'"></label>';
        echo '<label>' . esc_html__('From', 'eldokan-admin') . '<input type="date" name="log_from" value="'.esc_attr($date_from).'"></label>';
        echo '<label>' . esc_html__('To', 'eldokan-admin') . '<input type="date" name="log_to" value="'.esc_attr($date_to).'"></label>';
        echo '<div class="eda-log-filter-actions"><button class="button button-primary">' . esc_html__('Filter', 'eldokan-admin') . '</button> <a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-activity')).'">' . esc_html__('Reset', 'eldokan-admin') . '</a></div>';
        echo '</form>';

        if (!$rows) {
            echo '<p>' . esc_html__('No audit events match the selected filters.', 'eldokan-admin') . '</p>';
        } else {
            echo '<div class="eda-table-scroll"><table class="eda-table eda-log-table">';
            echo '<tr><th>' . esc_html__('Date / Time', 'eldokan-admin') . '</th><th>' . esc_html__('Source', 'eldokan-admin') . '</th><th>' . esc_html__('Action', 'eldokan-admin') . '</th><th>' . esc_html__('Actor', 'eldokan-admin') . '</th><th>' . esc_html__('Seller', 'eldokan-admin') . '</th><th>' . esc_html__('Object', 'eldokan-admin') . '</th><th>' . esc_html__('Description', 'eldokan-admin') . '</th><th>' . esc_html__('Status', 'eldokan-admin') . '</th><th>' . esc_html__('Details', 'eldokan-admin') . '</th></tr>';

            foreach ($rows as $row) {
                $seller_name = $row->seller_user_id ? $this->company_name($row->seller_user_id) : '—';
                $object = trim($row->object_type . ($row->object_id ? ' #' . $row->object_id : ''));
                $status = $row->status_code ? (string)$row->status_code : '—';
                $meta = json_decode((string)$row->meta, true);
                if (!is_array($meta)) $meta = [];

                echo '<tr>';
                echo '<td><strong>'.esc_html($row->created_at).'</strong><br><small>#'.absint($row->id).'</small></td>';
                echo '<td>'.$this->badge($row->source).'</td>';
                echo '<td><code>'.esc_html($row->action).'</code></td>';
                echo '<td>'.esc_html($row->actor_name ?: 'Guest/System');
                if ($row->actor_user_id) echo '<br><small>' . esc_html__('User #', 'eldokan-admin') . ''.absint($row->actor_user_id).'</small>';
                echo '</td>';
                echo '<td>'.esc_html($seller_name);
                if ($row->seller_user_id) echo '<br><small>#'.absint($row->seller_user_id).'</small>';
                echo '</td>';
                echo '<td>'.esc_html($object ?: '—').'</td>';
                echo '<td>'.esc_html($row->description ?: '—').'</td>';
                echo '<td>'.esc_html($status).'</td>';
                echo '<td><details><summary>' . esc_html__('View', 'eldokan-admin') . '</summary><div class="eda-log-detail">';
                echo '<p><b>' . esc_html__('IP:', 'eldokan-admin') . '</b> '.esc_html($row->ip_address ?: '—').'</p>';
                echo '<p><b>' . esc_html__('Method:', 'eldokan-admin') . '</b> '.esc_html($row->request_method ?: '—').'</p>';
                echo '<p><b>' . esc_html__('URI:', 'eldokan-admin') . '</b> <code>'.esc_html($row->request_uri ?: '—').'</code></p>';
                echo '<p><b>' . esc_html__('Actor email:', 'eldokan-admin') . '</b> '.esc_html($row->actor_email ?: '—').'</p>';
                if ($meta) {
                    echo '<pre class="eda-pre">'.esc_html(wp_json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>';
                }
                echo '</div></details></td>';
                echo '</tr>';
            }

            echo '</table></div>';
        }

        if ($pages > 1) {
            echo '<div class="eda-pagination">';
            $base_args = [
                'page' => 'eldokan-admin-activity',
                'log_q' => $q,
                'log_source' => $source,
                'log_action' => $action,
                'log_actor' => $actor,
                'log_seller' => $seller,
                'log_from' => $date_from,
                'log_to' => $date_to,
            ];

            if ($page_num > 1) {
                echo '<a class="button" href="'.esc_url(add_query_arg(array_merge($base_args,['log_page'=>$page_num-1]),admin_url('admin.php'))).'">' . esc_html__('← Previous', 'eldokan-admin') . '</a> ';
            }

            echo '<span>' . esc_html__('Page', 'eldokan-admin') . ' '.absint($page_num).' of '.absint($pages).'</span>';

            if ($page_num < $pages) {
                echo ' <a class="button" href="'.esc_url(add_query_arg(array_merge($base_args,['log_page'=>$page_num+1]),admin_url('admin.php'))).'">' . esc_html__('Next →', 'eldokan-admin') . '</a>';
            }

            echo '</div>';
        }

        echo '</div>';
        $this->footer();
    }

    private function audit_remote_ip() {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $ip) {
            $ip = sanitize_text_field(wp_unslash($ip));
            if ($ip !== '') return substr($ip, 0, 100);
        }

        return '';
    }

    private function audit_request_uri() {
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        return substr(sanitize_text_field($uri), 0, 2000);
    }

    private function audit_user_agent() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        return substr(sanitize_text_field($ua), 0, 2000);
    }

    private function audit_actor($actor_user_id = 0, $actor_name = '', $actor_email = '') {
        $actor_user_id = absint($actor_user_id);

        if (!$actor_user_id) {
            $actor_user_id = get_current_user_id();
        }

        if ($actor_user_id) {
            $user = get_userdata($actor_user_id);
            if ($user) {
                if ($actor_name === '') {
                    $actor_name = $user->display_name ?: $user->user_login;
                }
                if ($actor_email === '') {
                    $actor_email = $user->user_email;
                }
            }
        }

        return [
            'id' => $actor_user_id,
            'name' => sanitize_text_field($actor_name),
            'email' => sanitize_email($actor_email),
        ];
    }

    private function write_audit_log($action, $description='', $args=[]) {
        global $wpdb;

        if (!$this->audit_table) {
            return false;
        }

        $defaults = [
            'external_id' => null,
            'source' => 'system',
            'actor_user_id' => 0,
            'actor_name' => '',
            'actor_email' => '',
            'seller_user_id' => 0,
            'object_type' => '',
            'object_id' => 0,
            'meta' => [],
            'status_code' => 0,
            'request_method' => '',
            'request_uri' => '',
            'ip_address' => '',
            'user_agent' => '',
            'created_at' => '',
            'created_at_gmt' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $actor = $this->audit_actor(
            $args['actor_user_id'],
            $args['actor_name'],
            $args['actor_email']
        );

        $external_id = $args['external_id'] ? sanitize_text_field($args['external_id']) : null;

        if ($external_id) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->audit_table} WHERE external_id = %s LIMIT 1",
                    $external_id
                )
            );
            if ($exists) return (int)$exists;
        }

        $created_at = $args['created_at'] ?: current_time('mysql');
        $created_at_gmt = $args['created_at_gmt'] ?: current_time('mysql', true);

        $meta = is_array($args['meta']) ? $args['meta'] : [];
        // Never store secrets from request bodies in the audit table.
        foreach ([
            'password','password_confirmation','token','authorization',
            '_wpnonce','nonce','secret','client_secret','api_key'
        ] as $secret_key) {
            if (array_key_exists($secret_key, $meta)) {
                $meta[$secret_key] = '[REDACTED]';
            }
        }

        $data = [
            'external_id' => $external_id,
            'created_at' => sanitize_text_field($created_at),
            'created_at_gmt' => sanitize_text_field($created_at_gmt),
            'source' => sanitize_key($args['source']) ?: 'system',
            'action' => sanitize_text_field($action),
            'actor_user_id' => absint($actor['id']),
            'actor_name' => substr($actor['name'], 0, 191),
            'actor_email' => substr($actor['email'], 0, 191),
            'seller_user_id' => absint($args['seller_user_id']),
            'object_type' => substr(sanitize_key($args['object_type']), 0, 80),
            'object_id' => absint($args['object_id']),
            'description' => sanitize_textarea_field($description),
            'meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $args['ip_address'] !== '' ? substr(sanitize_text_field($args['ip_address']),0,100) : $this->audit_remote_ip(),
            'user_agent' => $args['user_agent'] !== '' ? substr(sanitize_text_field($args['user_agent']),0,2000) : $this->audit_user_agent(),
            'request_method' => $args['request_method'] !== '' ? substr(sanitize_key(strtolower($args['request_method'])),0,20) : substr(sanitize_key(strtolower($_SERVER['REQUEST_METHOD'] ?? '')),0,20),
            'request_uri' => $args['request_uri'] !== '' ? substr(sanitize_text_field($args['request_uri']),0,2000) : $this->audit_request_uri(),
            'status_code' => absint($args['status_code']),
        ];

        $wpdb->insert($this->audit_table, $data);
        return (int)$wpdb->insert_id;
    }

    public function audit_home_change($action, $description, $args=[]) {
        $this->write_audit_log(
            sanitize_key($action),
            sanitize_text_field($description),
            wp_parse_args($args, [
                'source' => 'admin',
                'object_type' => 'home_content',
            ])
        );
    }

    private function admin_action_description($action) {
        if (strpos($action, 'commission') !== false && http_response_code() >= 400) return 'Commission request failed; success was not confirmed.';
        if ($action === 'eldokan_admin_delete_commission' && ($_REQUEST['disable'] ?? '') === '1') return 'Commission rule disabled.';
        $map = [
            'eldokan_admin_save_commission' => 'Commission rule saved.',
            'eldokan_admin_delete_commission' => 'Commission rule deleted.',
            'eldokan_admin_rate_seller' => 'Seller manual rating changed.',
            'eldokan_admin_seller_status' => 'Seller status changed.',
            'eldokan_admin_save_dashboard_control' => 'Seller dashboard control/settings changed.',
            'eldokan_admin_save_access_role' => 'Seller access role template saved.',
            'eldokan_admin_delete_access_role' => 'Seller access role template deleted.',
            'eldokan_admin_assign_access_role' => 'Seller access role assigned.',
            'eldokan_admin_update_seller_account_role' => 'Seller account ElDokan role changed.',
            'eldokan_admin_delete_seller_account' => 'Seller team/sub-account deleted.',
            'eldokan_admin_update_wcfm_role' => 'Seller account WCFM role changed.',
            'eldokan_admin_bulk_wcfm_role' => 'WCFM role applied to seller accounts.',
            'eldokan_admin_save_email_settings' => 'Seller registration email settings changed.',
            'eldokan_admin_send_test_email' => 'Test seller email sent.',
            'eldokan_wcfm_index_scan' => 'WCFM data index scan executed.',
            'eldokan_wcfm_index_batch' => 'WCFM data index batch executed.',
            'eldokan_wcfm_index_reset' => 'WCFM data index reset.',
            'eldokan_wcfm_index_rebuild' => 'WCFM data index rebuild executed.',
            'eldokan_wcfm_migration_scan' => 'WCFM seller migration scan executed.',
            'eldokan_wcfm_migration_batch' => 'WCFM seller migrated to ElDokan.',
            'eldokan_wcfm_migration_reset' => 'WCFM migration progress reset.',
            'eldokan_wcfm_migration_verify' => 'WCFM to ElDokan migration verified.',
            'eldokan_wcfm_migration_verify_detailed' => 'Detailed WCFM to ElDokan migration verification executed.',
            'eldokan_wcfm_migration_repair' => 'Missing WCFM to ElDokan migration links repaired.',
        ];

        return $map[$action] ?? ('ElDokan action executed: ' . $action);
    }

    public function capture_eldokan_request_audit() {
        static $done = false;
        if ($done) return;
        $done = true;

        $action = sanitize_key($_REQUEST['action'] ?? '');
        if ($action === '') return;

        if (
            strpos($action, 'eldokan_admin_') !== 0 &&
            strpos($action, 'eldokan_wcfm_') !== 0
        ) {
            return;
        }

        // Exporting the audit table is itself logged, but avoid logging the
        // browser download request twice because the export method writes a
        // richer entry before sending the CSV.
        if ($action === 'eldokan_admin_export_activity') {
            return;
        }

        $seller_id = absint($_REQUEST['seller_id'] ?? 0);
        $account_id = absint($_REQUEST['account_id'] ?? 0);
        $object_id = absint(
            $_REQUEST['product_id']
            ?? $_REQUEST['order_id']
            ?? $_REQUEST['category_id']
            ?? $_REQUEST['id']
            ?? $account_id
            ?? 0
        );

        $object_type = '';
        if (!empty($_REQUEST['product_id'])) $object_type = 'product';
        elseif (!empty($_REQUEST['order_id'])) $object_type = 'order';
        elseif (!empty($_REQUEST['category_id'])) $object_type = 'category';
        elseif ($account_id) $object_type = 'user';
        elseif (!empty($_REQUEST['id'])) $object_type = 'record';

        $meta = [];
        foreach ([
            'seller_id','account_id','product_id','order_id','category_id',
            'id','role_id','wcfm_role','scope','target_type','target_id',
            'calculation_type','status','rating'
        ] as $key) {
            if (isset($_REQUEST[$key])) {
                $meta[$key] = sanitize_text_field(wp_unslash($_REQUEST[$key]));
            }
        }

        $this->write_audit_log(
            $action,
            $this->admin_action_description($action),
            [
                'source' => strpos($action, 'eldokan_wcfm_migration_') === 0 ? 'wcfm_migration' : (strpos($action, 'eldokan_wcfm_') === 0 ? 'wcfm_index' : 'admin'),
                'seller_user_id' => $seller_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'meta' => $meta,
                'status_code' => http_response_code(),
            ]
        );
    }

    public function capture_eldokan_rest_audit($response, $handler, $request) {
        if (!$request instanceof WP_REST_Request) {
            return $response;
        }

        $route = (string)$request->get_route();
        if (strpos($route, '/eldokan/v1') !== 0) {
            return $response;
        }

        $status = 200;
        if (is_wp_error($response)) {
            $data = $response->get_error_data();
            if (is_array($data) && !empty($data['status'])) {
                $status = absint($data['status']);
            } else {
                $status = 500;
            }
        } elseif ($response instanceof WP_REST_Response) {
            $status = absint($response->get_status()) ?: 200;
        }

        $method = strtoupper((string)$request->get_method());
        $actor_id = get_current_user_id();
        $seller_id = $actor_id ? $this->owner_id($actor_id) : 0;

        $object_id = 0;
        foreach (['id','user_id','seller_id','product_id','order_id'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $object_id = absint($value);
                if ($object_id) break;
            }
        }

        $object_type = 'api';
        if (strpos($route, '/products') !== false) $object_type = 'product';
        elseif (strpos($route, '/orders') !== false) $object_type = 'order';
        elseif (strpos($route, '/admin/users') !== false || strpos($route, '/owner/team') !== false) $object_type = 'user';
        elseif (strpos($route, '/notifications') !== false) $object_type = 'notification';
        elseif (strpos($route, '/reports') !== false) $object_type = 'report';

        $this->write_audit_log(
            'rest_' . strtolower($method),
            $method . ' ' . $route,
            [
                'source' => 'seller_api',
                'actor_user_id' => $actor_id,
                'seller_user_id' => $seller_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'status_code' => $status,
                'request_method' => $method,
                'request_uri' => $route,
                'meta' => [
                    'route' => $route,
                    'method' => $method,
                    'status' => $status,
                ],
            ]
        );

        return $response;
    }

    public function audit_user_registered($user_id, $userdata=[]) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->write_audit_log(
            'user_registered',
            'User account created: ' . ($user->display_name ?: $user->user_login),
            [
                'source' => 'wordpress',
                'actor_user_id' => get_current_user_id(),
                'seller_user_id' => $this->owner_id($user_id),
                'object_type' => 'user',
                'object_id' => $user_id,
                'meta' => [
                    'roles' => array_values((array)$user->roles),
                    'email' => $user->user_email,
                ],
            ]
        );
    }

    public function audit_user_updated($user_id, $old_user_data, $userdata=[]) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->write_audit_log(
            'user_updated',
            'User account updated: ' . ($user->display_name ?: $user->user_login),
            [
                'source' => 'wordpress',
                'seller_user_id' => $this->owner_id($user_id),
                'object_type' => 'user',
                'object_id' => $user_id,
                'meta' => [
                    'roles' => array_values((array)$user->roles),
                ],
            ]
        );
    }

    public function audit_user_deleted($user_id, $reassign=0, $user=null) {
        if (!$user) $user = get_userdata($user_id);
        $name = $user ? ($user->display_name ?: $user->user_login) : ('User #' . absint($user_id));

        $this->write_audit_log(
            'user_deleted',
            'User account deleted: ' . $name,
            [
                'source' => 'wordpress',
                'object_type' => 'user',
                'object_id' => $user_id,
                'meta' => [
                    'reassigned_to' => absint($reassign),
                    'roles' => $user ? array_values((array)$user->roles) : [],
                ],
            ]
        );
    }

    public function audit_product_saved($post_id, $post, $update) {
        if (
            wp_is_post_revision($post_id) ||
            wp_is_post_autosave($post_id) ||
            !$post ||
            $post->post_type !== 'product'
        ) {
            return;
        }

        $seller_id = $this->product_owner_id($post_id);

        $this->write_audit_log(
            $update ? 'product_saved' : 'product_created',
            ($update ? 'Product saved: ' : 'Product created: ') . $post->post_title,
            [
                'source' => 'woocommerce',
                'seller_user_id' => $seller_id,
                'object_type' => 'product',
                'object_id' => $post_id,
                'meta' => [
                    'status' => $post->post_status,
                    'seller_id' => $seller_id,
                ],
            ]
        );
    }

    public function audit_post_deleted($post_id, $post) {
        if (!$post || $post->post_type !== 'product') return;

        $seller_id = $this->product_owner_id($post_id);

        $this->write_audit_log(
            'product_deleted',
            'Product deleted: ' . $post->post_title,
            [
                'source' => 'woocommerce',
                'seller_user_id' => $seller_id,
                'object_type' => 'product',
                'object_id' => $post_id,
                'meta' => [
                    'status' => $post->post_status,
                    'seller_id' => $seller_id,
                ],
            ]
        );
    }

    public function audit_product_status_changed($new_status, $old_status, $post) {
        if (
            !$post ||
            $post->post_type !== 'product' ||
            $new_status === $old_status
        ) {
            return;
        }

        $seller_id = $this->product_owner_id($post->ID);

        $this->write_audit_log(
            'product_status_changed',
            'Product status changed from ' . $old_status . ' to ' . $new_status . ': ' . $post->post_title,
            [
                'source' => 'woocommerce',
                'seller_user_id' => $seller_id,
                'object_type' => 'product',
                'object_id' => $post->ID,
                'meta' => [
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ],
            ]
        );
    }

    public function audit_product_category_created($term_id, $tt_id=0, $args=[]) {
        $term = get_term($term_id, 'product_cat');
        $this->write_audit_log(
            'category_created',
            'Product category created: ' . ($term && !is_wp_error($term) ? $term->name : ('#'.$term_id)),
            [
                'source' => 'woocommerce',
                'object_type' => 'category',
                'object_id' => $term_id,
            ]
        );
    }

    public function audit_product_category_updated($term_id, $tt_id=0, $args=[]) {
        $term = get_term($term_id, 'product_cat');
        $this->write_audit_log(
            'category_updated',
            'Product category updated: ' . ($term && !is_wp_error($term) ? $term->name : ('#'.$term_id)),
            [
                'source' => 'woocommerce',
                'object_type' => 'category',
                'object_id' => $term_id,
            ]
        );
    }

    public function audit_product_category_deleted($term_id, $tt_id=0, $deleted_term=null, $object_ids=[]) {
        $name = is_object($deleted_term) && !empty($deleted_term->name)
            ? $deleted_term->name
            : ('#'.$term_id);

        $this->write_audit_log(
            'category_deleted',
            'Product category deleted: ' . $name,
            [
                'source' => 'woocommerce',
                'object_type' => 'category',
                'object_id' => $term_id,
            ]
        );
    }

    public function audit_order_created($order_id, $order=null) {
        if (!$order && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
        }

        $this->write_audit_log(
            'order_created',
            'WooCommerce order created #' . absint($order_id),
            [
                'source' => 'woocommerce',
                'object_type' => 'order',
                'object_id' => $order_id,
                'meta' => [
                    'status' => $order ? $order->get_status() : '',
                    'total' => $order ? (float)$order->get_total() : 0,
                    'currency' => $order ? $order->get_currency() : '',
                ],
            ]
        );
    }

    public function audit_order_status_changed($order_id, $from, $to, $order=null) {
        $this->write_audit_log(
            'order_status_changed',
            'Order #' . absint($order_id) . ' status changed from ' . $from . ' to ' . $to,
            [
                'source' => 'woocommerce',
                'object_type' => 'order',
                'object_id' => $order_id,
                'meta' => [
                    'old_status' => $from,
                    'new_status' => $to,
                    'total' => $order ? (float)$order->get_total() : 0,
                ],
            ]
        );
    }

    public function audit_wp_login($user_login, $user) {
        if (!$user instanceof WP_User) return;

        $this->write_audit_log(
            'wp_login',
            'WordPress login: ' . $user_login,
            [
                'source' => 'authentication',
                'actor_user_id' => $user->ID,
                'seller_user_id' => $this->owner_id($user->ID),
                'object_type' => 'user',
                'object_id' => $user->ID,
            ]
        );
    }

    public function audit_wp_logout($user_id=0) {
        $user_id = absint($user_id ?: get_current_user_id());
        $this->write_audit_log(
            'wp_logout',
            'WordPress logout.',
            [
                'source' => 'authentication',
                'actor_user_id' => $user_id,
                'seller_user_id' => $user_id ? $this->owner_id($user_id) : 0,
                'object_type' => 'user',
                'object_id' => $user_id,
            ]
        );
    }

    private function sync_seller_api_activity() {
        global $wpdb;

        $logs = get_option('eldokan_activity_log_v1', []);
        if (!is_array($logs) || !$logs) {
            return;
        }

        // Keep this sync bounded; the Seller API itself currently retains
        // at most 2,000 semantic activity entries.
        foreach (array_slice($logs, 0, 2000) as $log) {
            if (!is_array($log)) continue;

            $actor = is_array($log['actor'] ?? null) ? $log['actor'] : [];
            $meta = is_array($log['meta'] ?? null) ? $log['meta'] : [];
            $external_id = sanitize_text_field($log['id'] ?? '');
            if ($external_id === '') {
                $external_id = 'legacy-' . md5(wp_json_encode($log));
            }

            $object_type = 'seller_api';
            $object_id = 0;

            foreach ([
                'productId' => 'product',
                'orderId' => 'order',
                'userId' => 'user',
                'notificationId' => 'notification',
            ] as $key => $type) {
                if (!empty($meta[$key])) {
                    $object_type = $type;
                    $object_id = absint($meta[$key]);
                    break;
                }
            }

            $this->write_audit_log(
                sanitize_key($log['action'] ?? 'seller_api_activity'),
                sanitize_text_field($log['description'] ?? 'Seller API activity'),
                [
                    'external_id' => 'seller-api:' . $external_id,
                    'source' => 'seller_api_event',
                    'actor_user_id' => absint($actor['id'] ?? 0),
                    'actor_name' => sanitize_text_field($actor['name'] ?? ''),
                    'actor_email' => sanitize_email($actor['email'] ?? ''),
                    'seller_user_id' => absint($log['ownerGroupId'] ?? 0),
                    'object_type' => $object_type,
                    'object_id' => $object_id,
                    'meta' => $meta,
                    'created_at' => sanitize_text_field($log['timestamp'] ?? current_time('mysql')),
                    'created_at_gmt' => sanitize_text_field($log['timestampGmt'] ?? current_time('mysql', true)),
                ]
            );
        }
    }

    private function activity_filters_from_request() {
        return [
            'log_q' => sanitize_text_field(wp_unslash($_REQUEST['log_q'] ?? '')),
            'log_source' => sanitize_key($_REQUEST['log_source'] ?? ''),
            'log_action' => sanitize_text_field(wp_unslash($_REQUEST['log_action'] ?? '')),
            'log_actor' => absint($_REQUEST['log_actor'] ?? 0),
            'log_seller' => absint($_REQUEST['log_seller'] ?? 0),
            'log_from' => sanitize_text_field($_REQUEST['log_from'] ?? ''),
            'log_to' => sanitize_text_field($_REQUEST['log_to'] ?? ''),
        ];
    }

    public function export_activity_csv() {
        if (!current_user_can('eldokan_backoffice_view_activity')) {
            wp_die('Not allowed');
        }

        check_admin_referer('eda_export_activity');
        $this->sync_seller_api_activity();

        global $wpdb;
        $f = $this->activity_filters_from_request();
        $where = ['1=1'];
        $params = [];

        if ($f['log_q'] !== '') {
            $like = '%' . $wpdb->esc_like($f['log_q']) . '%';
            $where[] = '(action LIKE %s OR description LIKE %s OR actor_name LIKE %s OR actor_email LIKE %s OR object_type LIKE %s OR meta LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if ($f['log_source'] !== '') {
            $where[] = 'source = %s';
            $params[] = $f['log_source'];
        }
        if ($f['log_action'] !== '') {
            $where[] = 'action = %s';
            $params[] = $f['log_action'];
        }
        if ($f['log_actor']) {
            $where[] = 'actor_user_id = %d';
            $params[] = $f['log_actor'];
        }
        if ($f['log_seller']) {
            $where[] = 'seller_user_id = %d';
            $params[] = $f['log_seller'];
        }
        if ($f['log_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['log_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $f['log_from'].' 00:00:00';
        }
        if ($f['log_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['log_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $f['log_to'].' 23:59:59';
        }

        $sql = "SELECT * FROM {$this->audit_table} WHERE ".implode(' AND ', $where)." ORDER BY id DESC LIMIT 10000";
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared);

        $this->write_audit_log(
            'audit_exported',
            'ElDokan audit log exported to CSV.',
            [
                'source' => 'admin',
                'object_type' => 'audit',
                'meta' => [
                    'rows' => count($rows),
                    'filters' => $f,
                ],
            ]
        );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=eldokan-audit-log-'.gmdate('Y-m-d-His').'.csv');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'ID','Date','Source','Action','Actor User ID','Actor Name','Actor Email',
            'Seller User ID','Object Type','Object ID','Description','Status Code',
            'Method','URI','IP','Meta'
        ]);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row->id,
                $row->created_at,
                $row->source,
                $row->action,
                $row->actor_user_id,
                $row->actor_name,
                $row->actor_email,
                $row->seller_user_id,
                $row->object_type,
                $row->object_id,
                $row->description,
                $row->status_code,
                $row->request_method,
                $row->request_uri,
                $row->ip_address,
                $row->meta,
            ]);
        }

        fclose($out);
        exit;
    }


    private function email_settings_defaults() {
        return [
            'sender_name' => 'Eldokan',
            'sender_email' => '',
            'logo_id' => 0,
            'logo_width' => 180,
            'pending_subject' => 'Eldokan - Account Under Review',
            'pending_title' => 'Your Eldokan account is under review',
            'pending_message' => "Hello {name},\n\nWe received your registration request and company documents successfully. Your account is currently under review. We will notify you once the review is completed.",
            'approved_subject' => 'Eldokan - Account Approved',
            'approved_title' => 'Your Eldokan account has been approved',
            'approved_message' => "Hello {name},\n\nYour Eldokan seller account has been approved. You can now sign in and access your Seller Dashboard.",
            'rejected_subject' => 'Eldokan - Registration Update',
            'rejected_title' => 'Your Eldokan registration request',
            'rejected_message' => "Hello {name},\n\nYour registration request was not approved. Please contact ElDokan administration if you need more information.",
        ];
    }

    private function get_email_settings_admin() {
        $saved = get_option('eldokan_email_settings', []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->email_settings_defaults());
    }

    private function email_setting_value($settings, $key, $default='') {
        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    public function email_settings() {
        if (!current_user_can('eldokan_backoffice_manage_email')) wp_die('Not allowed');
        $this->header('Email Settings','Customize the automatic email sent to a seller immediately after registration data and documents are submitted.');
        $settings = $this->get_email_settings_admin();
        $logo_id = absint($this->email_setting_value($settings,'logo_id',0));
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id,'medium') : '';

        if (!empty($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Email settings saved successfully.', 'eldokan-admin') . '</p></div>';
        if (!empty($_GET['test'])) {
            $test = sanitize_key(wp_unslash($_GET['test']));
            if ($test === 'sent') echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Test email sent successfully.', 'eldokan-admin') . '</p></div>';
            elseif ($test === 'failed') echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('WordPress could not send the test email. Check SMTP / hosting mail configuration.', 'eldokan-admin') . '</p></div>';
            elseif ($test === 'invalid') echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter a valid test email address.', 'eldokan-admin') . '</p></div>';
        }
        if (!empty($_GET['email_error'])) {
            $err = sanitize_key(wp_unslash($_GET['email_error']));
            $messages = [
                'upload'=>'The logo could not be uploaded.',
                'size'=>'The logo must be 2MB or smaller.',
                'type'=>'Only PNG, JPG and JPEG files are allowed.',
            ];
            if (isset($messages[$err])) echo '<div class="notice notice-error is-dismissible"><p>'.esc_html($messages[$err]).'</p></div>';
        }

        echo '<div class="eda-card eda-email-help"><h2>' . esc_html__('Seller Registration Email', 'eldokan-admin') . '</h2><p>' . esc_html__('This is the', 'eldokan-admin') . ' <b>' . esc_html__('first automatic email', 'eldokan-admin') . '</b> ' . esc_html__('sent after the seller submits the registration form and verification PDF successfully. The current Seller API already reads the same', 'eldokan-admin') . ' <code>' . esc_html__('eldokan_email_settings', 'eldokan-admin') . '</code> ' . esc_html__('option, so changes here are used automatically without changing the React dashboard.', 'eldokan-admin') . '</p><p><b>' . esc_html__('Available variables:', 'eldokan-admin') . '</b> <code>{name}</code> <code>{email}</code> <code>{company}</code> <code>{role}</code> <code>{login_url}</code></p></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Send Test Email', 'eldokan-admin') . '</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="eldokan_admin_send_test_email">';
        wp_nonce_field('eldokan_admin_send_test_email');
        echo '<div class="eda-email-row"><label><b>' . esc_html__('Test Email', 'eldokan-admin') . '</b><input type="email" name="test_email" value="'.esc_attr(wp_get_current_user()->user_email).'" required></label><div class="eda-email-button">';
        submit_button('Send Test Email','secondary','submit',false);
        echo '</div></div></form></div>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
        echo '<input type="hidden" name="action" value="eldokan_admin_save_email_settings">';
        wp_nonce_field('eldokan_admin_save_email_settings');

        echo '<div class="eda-card"><h2>' . esc_html__('Sender', 'eldokan-admin') . '</h2><div class="eda-email-grid">';
        echo '<label><b>' . esc_html__('Sender Name', 'eldokan-admin') . '</b><input type="text" name="email[sender_name]" value="'.esc_attr($this->email_setting_value($settings,'sender_name','Eldokan')).'" placeholder="Eldokan"></label>';
        echo '<label><b>' . esc_html__('Sender Email', 'eldokan-admin') . '</b><input type="email" name="email[sender_email]" value="'.esc_attr($this->email_setting_value($settings,'sender_email','')).'" placeholder="noreply@eldokan.com"><small>' . esc_html__('Your SMTP / hosting provider must allow this From address.', 'eldokan-admin') . '</small></label>';
        echo '</div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Email Logo', 'eldokan-admin') . '</h2><div class="eda-email-grid">';
        echo '<div><b>' . esc_html__('Current Logo', 'eldokan-admin') . '</b>';
        if ($logo_url) echo '<div class="eda-email-logo"><img src="'.esc_url($logo_url).'" alt="Email logo"></div><label class="eda-inline-check"><input type="checkbox" name="remove_logo" value="1"> ' . esc_html__('Remove current logo', 'eldokan-admin') . '</label>';
        else echo '<p>' . esc_html__('No custom email logo uploaded.', 'eldokan-admin') . '</p>';
        echo '</div>';
        echo '<label><b>' . esc_html__('Upload New Logo', 'eldokan-admin') . '</b><input type="file" name="email_logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg"><small>' . esc_html__('PNG/JPG/JPEG, maximum 2MB.', 'eldokan-admin') . '</small></label>';
        echo '<label><b>' . esc_html__('Logo Width', 'eldokan-admin') . '</b><input type="number" min="60" max="400" name="email[logo_width]" value="'.esc_attr(absint($this->email_setting_value($settings,'logo_width',180))).'"><small>' . esc_html__('Recommended: 160–220 px.', 'eldokan-admin') . '</small></label>';
        echo '</div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Registration Submitted / Under Review Email', 'eldokan-admin') . '</h2><div class="eda-email-stack">';
        echo '<label><b>' . esc_html__('Subject', 'eldokan-admin') . '</b><input type="text" name="email[pending_subject]" value="'.esc_attr($this->email_setting_value($settings,'pending_subject','Eldokan - Account Under Review')).'" required></label>';
        echo '<label><b>' . esc_html__('Email Title', 'eldokan-admin') . '</b><input type="text" name="email[pending_title]" value="'.esc_attr($this->email_setting_value($settings,'pending_title','Your Eldokan account is under review')).'" required></label>';
        echo '<label><b>' . esc_html__('Message', 'eldokan-admin') . '</b><textarea name="email[pending_message]" rows="10" required>'.esc_textarea($this->email_setting_value($settings,'pending_message','')).'</textarea><small>' . esc_html__('You can use the variables shown above. Line breaks are preserved.', 'eldokan-admin') . '</small></label>';
        echo '</div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Registration Approved Email', 'eldokan-admin') . '</h2><div class="eda-email-stack">';
        echo '<label><b>' . esc_html__('Subject', 'eldokan-admin') . '</b><input type="text" name="email[approved_subject]" value="'.esc_attr($this->email_setting_value($settings,'approved_subject',$this->email_settings_defaults()['approved_subject'])).'" required></label>';
        echo '<label><b>' . esc_html__('Email Title', 'eldokan-admin') . '</b><input type="text" name="email[approved_title]" value="'.esc_attr($this->email_setting_value($settings,'approved_title',$this->email_settings_defaults()['approved_title'])).'" required></label>';
        echo '<label><b>' . esc_html__('Message', 'eldokan-admin') . '</b><textarea name="email[approved_message]" rows="7" required>'.esc_textarea($this->email_setting_value($settings,'approved_message',$this->email_settings_defaults()['approved_message'])).'</textarea></label>';
        echo '</div></div>';

        echo '<div class="eda-card"><h2>' . esc_html__('Registration Rejected Email', 'eldokan-admin') . '</h2><div class="eda-email-stack">';
        echo '<label><b>' . esc_html__('Subject', 'eldokan-admin') . '</b><input type="text" name="email[rejected_subject]" value="'.esc_attr($this->email_setting_value($settings,'rejected_subject',$this->email_settings_defaults()['rejected_subject'])).'" required></label>';
        echo '<label><b>' . esc_html__('Email Title', 'eldokan-admin') . '</b><input type="text" name="email[rejected_title]" value="'.esc_attr($this->email_setting_value($settings,'rejected_title',$this->email_settings_defaults()['rejected_title'])).'" required></label>';
        echo '<label><b>' . esc_html__('Message', 'eldokan-admin') . '</b><textarea name="email[rejected_message]" rows="7" required>'.esc_textarea($this->email_setting_value($settings,'rejected_message',$this->email_settings_defaults()['rejected_message'])).'</textarea></label>';
        echo '</div></div>';

        submit_button('Save Email Settings','primary','submit');
        echo '</form>';
        $this->footer();
    }

    public function save_email_settings() {
        if (!current_user_can('eldokan_backoffice_manage_email')) wp_die('Not allowed');
        check_admin_referer('eldokan_admin_save_email_settings');

        $posted = isset($_POST['email']) && is_array($_POST['email']) ? wp_unslash($_POST['email']) : [];
        $current = get_option('eldokan_email_settings', []);
        if (!is_array($current)) $current = [];
        $defaults = $this->email_settings_defaults();

        $current['sender_name'] = sanitize_text_field($posted['sender_name'] ?? $defaults['sender_name']);
        $current['sender_email'] = sanitize_email($posted['sender_email'] ?? '');
        $current['logo_width'] = max(60,min(400,absint($posted['logo_width'] ?? 180)));
        $current['pending_subject'] = sanitize_text_field($posted['pending_subject'] ?? $defaults['pending_subject']);
        $current['pending_title'] = sanitize_text_field($posted['pending_title'] ?? $defaults['pending_title']);
        $current['pending_message'] = sanitize_textarea_field($posted['pending_message'] ?? $defaults['pending_message']);
        $current['approved_subject'] = sanitize_text_field($posted['approved_subject'] ?? $defaults['approved_subject']);
        $current['approved_title'] = sanitize_text_field($posted['approved_title'] ?? $defaults['approved_title']);
        $current['approved_message'] = sanitize_textarea_field($posted['approved_message'] ?? $defaults['approved_message']);
        $current['rejected_subject'] = sanitize_text_field($posted['rejected_subject'] ?? $defaults['rejected_subject']);
        $current['rejected_title'] = sanitize_text_field($posted['rejected_title'] ?? $defaults['rejected_title']);
        $current['rejected_message'] = sanitize_textarea_field($posted['rejected_message'] ?? $defaults['rejected_message']);
        $current['logo_id'] = absint($current['logo_id'] ?? 0);

        if (!empty($_POST['remove_logo'])) $current['logo_id'] = 0;

        if (!empty($_FILES['email_logo']) && isset($_FILES['email_logo']['error']) && (int)$_FILES['email_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['email_logo'];
            $redirect = admin_url('admin.php?page=eldokan-admin-email-settings');
            if ((int)$file['error'] !== UPLOAD_ERR_OK) { wp_safe_redirect(add_query_arg('email_error','upload',$redirect)); exit; }
            if ((int)$file['size'] > 2*1024*1024) { wp_safe_redirect(add_query_arg('email_error','size',$redirect)); exit; }
            $allowed = ['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png'];
            $check = wp_check_filetype_and_ext($file['tmp_name'],$file['name'],$allowed);
            if (empty($check['type']) || !in_array($check['type'],array_values($allowed),true)) { wp_safe_redirect(add_query_arg('email_error','type',$redirect)); exit; }
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $upload = wp_handle_upload($file,['test_form'=>false,'mimes'=>$allowed]);
            if (!empty($upload['error'])) { wp_safe_redirect(add_query_arg('email_error','upload',$redirect)); exit; }
            $aid = wp_insert_attachment(['post_mime_type'=>$upload['type'],'post_title'=>sanitize_file_name(pathinfo($file['name'],PATHINFO_FILENAME)),'post_content'=>'','post_status'=>'inherit'],$upload['file']);
            if (is_wp_error($aid)) { wp_delete_file($upload['file']); wp_safe_redirect(add_query_arg('email_error','upload',$redirect)); exit; }
            $meta = wp_generate_attachment_metadata($aid,$upload['file']);
            if ($meta && !is_wp_error($meta)) wp_update_attachment_metadata($aid,$meta);
            $current['logo_id'] = (int)$aid;
        }

        update_option('eldokan_email_settings',$current,false);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-email-settings&saved=1'));
        exit;
    }

    private function render_test_registration_email($settings) {
        $name = wp_get_current_user()->display_name ?: 'Seller';
        $vars = [
            '{name}'=>$name,
            '{email}'=>wp_get_current_user()->user_email,
            '{company}'=>'Example Company',
            '{role}'=>'Owner',
            '{login_url}'=>'https://www.sell.eldokan.com/',
        ];
        $title = strtr((string)$this->email_setting_value($settings,'pending_title','Your Eldokan account is under review'),$vars);
        $message = strtr((string)$this->email_setting_value($settings,'pending_message',''),$vars);
        $logo_id = absint($this->email_setting_value($settings,'logo_id',0));
        $logo = $logo_id ? wp_get_attachment_image_url($logo_id,'full') : '';
        $width = max(60,min(400,absint($this->email_setting_value($settings,'logo_width',180))));
        $logo_html = $logo ? '<p style="text-align:center;margin:0 0 24px"><img src="'.esc_url($logo).'" alt="ElDokan" width="'.$width.'" style="max-width:100%;height:auto"></p>' : '';
        return '<!doctype html><html><body style="margin:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#101828"><div style="max-width:640px;margin:30px auto;background:#fff;border:1px solid #e4e7ec;border-radius:18px;padding:32px">'.$logo_html.'<h2 style="margin:0 0 18px">'.esc_html($title).'</h2><div style="font-size:15px;line-height:1.7">'.wpautop(esc_html($message)).'</div></div></body></html>';
    }

    public function send_test_email() {
        if (!current_user_can('eldokan_backoffice_manage_email')) wp_die('Not allowed');
        check_admin_referer('eldokan_admin_send_test_email');
        $to = sanitize_email(wp_unslash($_POST['test_email'] ?? ''));
        $url = admin_url('admin.php?page=eldokan-admin-email-settings');
        if (!$to || !is_email($to)) { wp_safe_redirect(add_query_arg('test','invalid',$url)); exit; }
        $settings = $this->get_email_settings_admin();
        $subject = strtr((string)$this->email_setting_value($settings,'pending_subject','Eldokan - Account Under Review'),['{name}'=>wp_get_current_user()->display_name ?: 'Seller','{email}'=>$to,'{company}'=>'Example Company','{role}'=>'Owner','{login_url}'=>'https://www.sell.eldokan.com/']);
        $sent = wp_mail($to,$subject,$this->render_test_registration_email($settings),['Content-Type: text/html; charset=UTF-8']);
        wp_safe_redirect(add_query_arg('test',$sent?'sent':'failed',$url));
        exit;
    }

    public function settings() {
        if (!current_user_can('eldokan_backoffice_manage_settings')) {
            wp_die('Not allowed');
        }

        $this->header('Settings','ElDokan Admin is intentionally separate from the React Seller plugin.');
        echo '<div class="eda-card"><h2>' . esc_html__('Integration', 'eldokan-admin') . '</h2><p><b>' . esc_html__('Seller plugin:', 'eldokan-admin') . '</b> ' . esc_html__('ElDokan Dashboard API', 'eldokan-admin') . '</p><p><b>' . esc_html__('Admin plugin:', 'eldokan-admin') . '</b> ' . esc_html__('ElDokan Admin v', 'eldokan-admin') . ''.esc_html(ELDOKAN_ADMIN_VERSION).'</p><p>' . esc_html__('This admin plugin reads the same WordPress users, seller ownership metadata, products and orders used by the current Seller API.', 'eldokan-admin') . '</p></div>';
        echo '<div class="eda-card"><h2>' . esc_html__('Advanced Global Defaults', 'eldokan-admin') . '</h2><p>' . esc_html__('Seller-specific control is done from', 'eldokan-admin') . ' <b>' . esc_html__('Sellers → Manage Seller', 'eldokan-admin') . '</b>' . esc_html__('. Use these only when changing defaults shared across sellers.', 'eldokan-admin') . '</p><p><a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-dashboard-control')).'">' . esc_html__('Global Dashboard Defaults', 'eldokan-admin') . '</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-access-roles')).'">' . esc_html__('Role Permission Templates', 'eldokan-admin') . '</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-categories')).'">' . esc_html__('Category Fees', 'eldokan-admin') . '</a></p></div>';$this->footer();
    }

    public function save_category_commission() {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) wp_die('Not allowed');
        $id = absint($this->request_text($_POST, 'object_id', '0'));
        check_admin_referer('eda_category_commission_'.$id);
        if (!$id || !term_exists($id, 'product_cat')) wp_die('Invalid product category.');
        $values = $this->commission_values($this->request_text($_POST, 'calculation_type', 'percentage'),
            $this->request_text($_POST, 'value'), $this->request_text($_POST, 'fixed_value', '0'));
        $this->require_commission_saved($values);
        [$type, $value, $fixed] = $values;
        $result = $this->commission_write(function () use ($id, $type, $value, $fixed) {
            $saved = $this->upsert_commission_rule('global', 0, 'category', $id, $type, $value, $fixed);
            if (is_wp_error($saved) || $saved === false) return $saved;
            $meta = ['_eldokan_fee_enabled'=>'1','_eldokan_fee_type'=>$type,'_eldokan_fee_value'=>$value,
                '_eldokan_fee_fixed'=>$fixed,'_eldokan_fee_updated_at'=>current_time('mysql'),'_eldokan_fee_updated_by'=>get_current_user_id()];
            foreach ($meta as $key=>$amount) {
                $updated = update_term_meta($id, $key, $amount);
                if (is_wp_error($updated) || ($updated === false && (string)get_term_meta($id, $key, true) !== (string)$amount)) return false;
            }
            return $saved;
        }, $id);
        $this->require_commission_saved($result);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-categories&commission_saved=1'));
        exit;
    }

    public function clear_category_fee() {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) wp_die('Not allowed');
        $id = absint($this->request_text($_GET, 'category_id', '0'));
        check_admin_referer('eda_clear_category_fee_'.$id);
        if (!$id || !term_exists($id, 'product_cat')) wp_die('Invalid product category.');
        global $wpdb;
        $result = $this->commission_write(function () use ($wpdb, $id) {
            if ($wpdb->update($this->commission_table, ['enabled'=>0,'updated_at'=>current_time('mysql')],
                ['scope'=>'global','seller_user_id'=>0,'target_type'=>'category','target_id'=>$id,'enabled'=>1]) === false) return false;
            foreach (['_eldokan_fee_enabled','_eldokan_fee_type','_eldokan_fee_value','_eldokan_fee_fixed','_eldokan_fee_updated_at','_eldokan_fee_updated_by'] as $key) {
                delete_term_meta($id, $key);
                if (metadata_exists('term', $id, $key)) return false;
            }
            return true;
        }, $id);
        $this->require_commission_saved($result);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-categories&fee_cleared=1'));
        exit;
    }

    public function save_product_commission() {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) wp_die('Not allowed');
        $product_id = absint($this->request_text($_POST, 'object_id', '0'));
        check_admin_referer('eda_product_commission_'.$product_id);
        $result = $this->upsert_commission_rule('global', 0, 'product', $product_id,
            $this->request_text($_POST, 'calculation_type', 'percentage'),
            $this->request_text($_POST, 'value'), $this->request_text($_POST, 'fixed_value', '0'));
        $this->require_commission_saved($result);
        $args = array_merge($this->product_view_state($_POST, 'return_'), ['page'=>'eldokan-admin-products','commission_saved'=>1]);
        $args['s'] = rawurlencode($args['s']);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')).'#product-'.$product_id);
        exit;
    }


    public function save_commission() {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) wp_die('Not allowed');
        check_admin_referer('eda_save_commission');
        $result = $this->upsert_commission_rule(
            $this->request_text($_POST, 'scope'), absint($this->request_text($_POST, 'seller_user_id', '0')),
            $this->request_text($_POST, 'target_type'), absint($this->request_text($_POST, 'target_id', '0')),
            $this->request_text($_POST, 'calculation_type'), $this->request_text($_POST, 'value'),
            $this->request_text($_POST, 'fixed_value', '0'));
        $this->require_commission_saved($result);
        $seller = absint($this->request_text($_POST, 'return_seller_id', '0'));
        $url = $seller ? admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller.'#seller-commission') : admin_url('admin.php?page=eldokan-admin-commissions&saved=1');
        wp_safe_redirect($url);
        exit;
    }

    public function delete_commission() {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) wp_die('Not allowed');
        $id = absint($this->request_text($_GET, 'id', '0'));
        check_admin_referer('eda_del_'.$id);
        global $wpdb;
        $disable = $this->request_text($_GET, 'disable') === '1';
        $result = $this->commission_write(function () use ($wpdb, $id, $disable) {
            $rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->commission_table} WHERE id=%d FOR UPDATE", $id));
            if (!$rule) return new WP_Error('commission_missing', __('Fee rule was not found. Reload before retrying.', 'eldokan-admin'));
            if ($rule->scope === 'global' && $rule->target_type === 'category' && (int)$rule->enabled === 1 && (string)get_term_meta($rule->target_id, '_eldokan_fee_enabled', true) === '1') {
                return new WP_Error('commission_category_source', __('Use Clear Fee on Categories to remove the active category fee and its legacy mirror safely.', 'eldokan-admin'));
            }
            return $disable ? $wpdb->update($this->commission_table, ['enabled'=>0,'updated_at'=>current_time('mysql')], ['id'=>$id])
                : $wpdb->delete($this->commission_table, ['id'=>$id]);
        });
        $this->require_commission_saved($result);
        $this->commission_rule_cache = [];
        $product = absint($this->request_text($_GET, 'return_product', '0'));
        $seller = absint($this->request_text($_GET, 'seller_id', '0'));
        if ($product) {
            $args = array_merge($this->product_view_state($_GET, 'return_'), ['page'=>'eldokan-admin-products','commission_changed'=>1]);
            $args['s'] = rawurlencode($args['s']);
            $url = add_query_arg($args, admin_url('admin.php')).'#product-'.$product;
        } else $url = $seller ? admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller.'#seller-commission') : admin_url('admin.php?page=eldokan-admin-commissions&changed=1');
        wp_safe_redirect($url);
        exit;
    }

    public function rate_seller() {
        if(!current_user_can('eldokan_backoffice_manage_sellers'))wp_die('Not allowed');$id=absint($_POST['seller_id']??0);check_admin_referer('eda_rate_'.$id);$rating=max(1,min(5,absint($_POST['rating']??0)));update_user_meta($id,'_eldokan_admin_rating',$rating);update_user_meta($id,'_eldokan_admin_rating_reason',sanitize_textarea_field($_POST['reason']??''));update_user_meta($id,'_eldokan_admin_rating_at',current_time('mysql'));wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$id));exit;
    }

    public function seller_status() {
        if (!current_user_can('eldokan_backoffice_manage_sellers')) wp_die('Not allowed');

        $seller_id = absint($_POST['seller_id'] ?? 0);
        $status = sanitize_key($_POST['status'] ?? '');
        if (!$seller_id || !in_array($status, ['approved','disabled'], true)) wp_die('Invalid seller status.');

        check_admin_referer('eda_seller_status_'.$seller_id);

        $user = get_userdata($seller_id);
        if (!$user) wp_die('Seller not found.');

        update_user_meta($seller_id, '_eldokan_account_status', $status);

        if ($status === 'approved') {
            $user->remove_role('eldokan_pending');
            if (!in_array('eldokan_owner', (array)$user->roles, true)) $user->add_role('eldokan_owner');
        }

        $this->write_audit_log(
            'seller_status_'.$status,
            'Seller account status changed to '.$status.': '.($user->display_name ?: $user->user_login),
            [
                'source'=>'admin',
                'actor_user_id'=>get_current_user_id(),
                'seller_user_id'=>$seller_id,
                'object_type'=>'seller',
                'object_id'=>$seller_id,
                'meta'=>['status'=>$status],
            ]
        );

        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-sellers&seller_id='.$seller_id.'&seller_status_saved=1'));
        exit;
    }

    public function rest_routes() {
        register_rest_route('eldokan-admin/v1','/overview',['methods'=>'GET','permission_callback'=>fn()=>current_user_can('eldokan_backoffice_view_dashboard'),'callback'=>fn()=>rest_ensure_response($this->overall_metrics(sanitize_key($_GET['period']??'30d')))]);
        register_rest_route('eldokan/v1','/dashboard-control',['methods'=>'GET','permission_callback'=>function(){return is_user_logged_in();},'callback'=>function(){ $uid=get_current_user_id();$owner=$this->owner_id($uid);$g=get_option('eldokan_dashboard_control_global',$this->dashboard_defaults());$o=get_user_meta($owner,'_eldokan_dashboard_control',true);return rest_ensure_response(['settings'=>array_merge($g,is_array($o)?$o:[]),'sellerId'=>$owner]);}]);
        register_rest_route('eldokan/v1','/seller-access',['methods'=>'GET','permission_callback'=>function(){return is_user_logged_in();},'callback'=>function(){
            $uid=get_current_user_id();
            $owner=$this->owner_id($uid);
            $access_user = $uid;
            $role_id=$this->seller_access_role_id($access_user);
            $role=$role_id?$this->access_role_by_id($role_id):null;
            return rest_ensure_response([
                'sellerId'=>$owner,
                'roleId'=>$role_id,
                'roleName'=>$role?$role['name']:'Full Access',
                'permissions'=>$this->seller_access_permissions($access_user),
                'accountUserId'=>$access_user,
                'updatedAt'=>get_user_meta($access_user,'_eldokan_access_role_updated_at',true)
            ]);
        }]);
        register_rest_route('eldokan-admin/v1','/sellers',['methods'=>'GET','permission_callback'=>fn()=>current_user_can('eldokan_backoffice_view_sellers'),'callback'=>function(){ $out=[];foreach($this->owner_users() as $s){$m=$this->seller_order_metrics($s->ID,...$this->period('30d'));$out[]=['id'=>$s->ID,'company'=>$this->company_name($s->ID),'email'=>$s->user_email,'products'=>count($this->seller_product_ids($s->ID)),'orders'=>$m['orders'],'sales'=>$m['gross'],'commission'=>$m['commission'],'rating'=>get_user_meta($s->ID,'_eldokan_admin_rating',true)];}return rest_ensure_response(['sellers'=>$out]);}]);
    }
}
