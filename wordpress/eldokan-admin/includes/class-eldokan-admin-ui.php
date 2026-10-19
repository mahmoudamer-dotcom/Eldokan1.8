<?php
if (!defined('ABSPATH')) exit;

/** Presentation and guarded page dispatch only. No persistent writes or capability grants. */
final class ElDokan_Admin_UI {
    private static $registered_routes = [];
    private static $sidebar_targets = [];
    public static function boot() {
        add_action('init', [__CLASS__, 'translations']);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
        add_filter('post_type_labels_eldokan_story', [__CLASS__, 'story_labels']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets'], 30);
        add_action('admin_menu', [__CLASS__, 'simplify_menu'], 999);
        add_filter('parent_file', [__CLASS__, 'parent_file']);
        add_filter('submenu_file', [__CLASS__, 'submenu_file']);
        // Run after standard WordPress notices, never wrap or suppress them.
        add_action('admin_notices', [__CLASS__, 'navigation'], 999);
    }

    public static function translations() {
        load_plugin_textdomain('eldokan-admin', false, dirname(plugin_basename(dirname(__DIR__) . '/eldokan-admin.php')) . '/languages');
    }

    public static function story_labels($labels) {
        $labels->name = __('Store Stories', 'eldokan-admin');
        $labels->singular_name = __('Store Story', 'eldokan-admin');
        $labels->add_new = __('Add Store Story', 'eldokan-admin');
        $labels->add_new_item = __('Add Store Story', 'eldokan-admin');
        $labels->edit_item = __('Edit Store Story', 'eldokan-admin');
        $labels->search_items = __('Search Stories', 'eldokan-admin');
        $labels->all_items = __('All Stories', 'eldokan-admin');
        return $labels;
    }

    public static function relevant() {
        $screen = get_current_screen();
        if (!$screen) return false;
        if ($screen->post_type === 'eldokan_story' && in_array($screen->base, ['edit', 'post'], true)) return true;
        // Exact allowlist: never match another plugin by a substring in its hook.
        return in_array($screen->id, [
            'toplevel_page_eldokan-admin',
            'admin_page_eldokan-admin-dashboard-control', 'admin_page_eldokan-admin-access-roles',
            'admin_page_eldokan-admin-commissions', 'admin_page_eldokan-admin-statements', 'admin_page_eldokan-admin-performance',
        ], true) || in_array($screen->id, array_map(static function ($slug) {
            return 'eldokan-admin-staff_page_' . $slug;
        }, self::page_slugs()), true) || self::registered_screen($screen);
    }

    private static function page_slugs() {
        return ['eldokan-admin-customers', 'eldokan-admin', 'eldokan-admin-sellers', 'eldokan-admin-registrations', 'eldokan-admin-products', 'eldokan-admin-orders', 'eldokan-admin-categories', 'eldokan-admin-wcfm-index', 'eldokan-admin-reports', 'eldokan-admin-activity', 'eldokan-admin-email-settings', 'eldokan-admin-settings', 'eldokan-admin-home', 'eldokan-admin-team-permissions', 'eldokan-checkout-setup'];
    }

    private static function registered_screen($screen) {
        // WordPress derives hook prefixes from the translated parent menu label.
        // Resolve actual registered hooks rather than assuming that prefix.
        foreach (self::page_slugs() as $slug) {
            if ($screen->id === get_plugin_page_hookname($slug, 'eldokan-admin') || $screen->id === 'admin_page_' . $slug) return true;
        }
        return false;
    }

    public static function body_class($classes) {
        return self::relevant() ? $classes . ' eda-admin-screen' : $classes;
    }

    public static function assets() {
        if (!self::relevant()) return;
        wp_enqueue_style('eldokan-admin', ELDOKAN_ADMIN_URL . 'assets/admin.css', [], ELDOKAN_ADMIN_VERSION);
        wp_enqueue_style('eldokan-admin-workspace', ELDOKAN_ADMIN_URL . 'assets/workspace.css', ['eldokan-admin'], ELDOKAN_ADMIN_VERSION);
        wp_enqueue_script('eldokan-admin-workspace', ELDOKAN_ADMIN_URL . 'assets/workspace.js', [], ELDOKAN_ADMIN_VERSION, true);
        wp_localize_script('eldokan-admin-workspace', 'eldokanAdminUI', [
            'table' => __('Data table. Scroll horizontally to see all columns.', 'eldokan-admin'),
            'empty' => __('No records to display.', 'eldokan-admin'),
            'find' => __('Find in loaded rows', 'eldokan-admin'),
            'loaded' => __('Search applies to this page of rows only.', 'eldokan-admin'),
            'noMatches' => __('No matching rows on this page.', 'eldokan-admin'),
            'compact' => __('Compact rows', 'eldokan-admin'),
            'confirm' => __('Confirm this action', 'eldokan-admin'),
            'delete' => __('Continue with this destructive action? Review the selected record before continuing.', 'eldokan-admin'),
            'cancel' => __('Cancel', 'eldokan-admin'),
            'continue' => __('Continue', 'eldokan-admin'),
            'invalid' => __('Review the highlighted field before saving.', 'eldokan-admin'),
            'expandSections' => __('Expand seller sections', 'eldokan-admin'),
            'collapseSections' => __('Collapse seller sections', 'eldokan-admin'),
            'wcfmMapped' => __('Ownership mappings complete — WCFM retirement NOT approved', 'eldokan-admin'),
            'wcfmBlocked' => __('Ownership mapping is incomplete — WCFM must remain active.', 'eldokan-admin'),
            'wcfmKeep' => __('WCFM must remain active. Mapping checks are not retirement approval.', 'eldokan-admin'),
            'wcfmHistory' => __('Historical Index not available.', 'eldokan-admin'),
        ]);
    }

    /**
     * Navigation only: all historical page slugs and their registered callbacks
     * remain available through direct links and original capability checks.
     * Do not remove actions or change any database or REST contract.
     */
    public static function simplify_menu() {
        global $submenu, $_registered_pages;
        if (!isset($submenu['eldokan-admin']) || !is_array($submenu['eldokan-admin'])) return;
        $original = [];
        foreach (array_merge($submenu['eldokan-admin'], $submenu[''] ?? []) as $entry) {
            if (isset($entry[2])) $original[$entry[2]] = $entry;
        }
        self::$registered_routes = [];
        self::$sidebar_targets = [];
        // Capture the real registered callbacks before changing menu presentation.
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                [$slug, $label, $cap] = $item;
                if (!current_user_can($cap) || strpos($slug, '.php?') !== false) continue;
                foreach (array_keys((array) $_registered_pages) as $hook) {
                    if (substr($hook, -strlen('_page_' . $slug)) === '_page_' . $slug && has_action($hook)) {
                        self::$registered_routes[$slug] = ['hook' => $hook, 'cap' => $cap];
                        break;
                    }
                }
            }
        }
        $visible = [];
        foreach (self::sections() as $key => $section) {
            foreach ($section['items'] as $item) {
                if (!current_user_can($item[2]) || !self::route_available($item[0])) continue;
                $entry = $original[$item[0]] ?? [$item[1], $item[2], $item[0], $item[1]];
                $entry[0] = $section['label'];
                $visible[] = $entry;
                self::$sidebar_targets[$key] = $item[0];
                break;
            }
        }
        $submenu['eldokan-admin'] = $visible;
        // Removing a submenu changes WP's inferred parent/hook on direct URLs.
        // Keep original hooks and add a guarded native admin_page alias. Merely
        // removing the link (RC3) leaves some registered pages unreachable.
        foreach (self::$registered_routes as $slug => $route) {
            $alias = 'admin_page_' . $slug;
            if (isset($_registered_pages[$alias]) && has_action($alias)) continue;
            $_registered_pages[$alias] = true;
            add_action($alias, static function () use ($route) {
                if (!current_user_can($route['cap'])) wp_die(esc_html__('Not allowed', 'eldokan-admin'));
                do_action($route['hook']);
            });
        }
    }

    private static function route_available($slug) {
        if ($slug === 'edit.php?post_type=eldokan_story') {
            $type = get_post_type_object('eldokan_story');
            return $type && $type->show_ui;
        }
        return isset(self::$registered_routes[$slug]);
    }

    private static function current_slug() {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'eldokan_story') return 'edit.php?post_type=eldokan_story';
        return isset($_GET['page']) && is_string($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    private static function current_section() {
        $current = self::current_slug();
        foreach (self::sections() as $key => $section) {
            foreach ($section['items'] as $item) if ($item[0] === $current) return $key;
        }
        return null;
    }

    public static function parent_file($parent) {
        return self::relevant() && self::current_section() !== null ? 'eldokan-admin' : $parent;
    }

    public static function submenu_file($file) {
        if (!self::relevant()) return $file;
        return self::$sidebar_targets[self::current_section() ?? ''] ?? $file;
    }

    public static function reporting_note() {
        if (!in_array(self::current_slug(), ['eldokan-admin', 'eldokan-admin-sellers', 'eldokan-admin-statements', 'eldokan-admin-performance'], true)) return;
        echo '<aside class="eda-metric-note" role="note"><strong>' . esc_html__('Summary data scope', 'eldokan-admin') . '</strong><p>' . esc_html__('Short summaries inspect at most the latest 300 orders before applying the date filter. Line values include all statuses and use current fee rules; they are not paid revenue, a seller balance or an approved settlement. Product records can include language translations; customer counts exclude guest checkouts.', 'eldokan-admin') . '</p>';
        if (current_user_can('eldokan_backoffice_view_reports')) echo '<a href="' . esc_url(admin_url('admin.php?page=eldokan-admin-reports')) . '">' . esc_html__('Open detailed seller reports', 'eldokan-admin') . '</a>';
        echo '</aside>';
    }

    private static function sections() {
        return [
            'dashboard' => [
                'label' => __('Dashboard', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin', __('Overview', 'eldokan-admin'), 'eldokan_backoffice_view_dashboard'],
                    ['eldokan-admin-customers', __('Customer Accounts', 'eldokan-admin'), 'list_users'],
                ],
            ],
            'sellers' => [
                'label' => __('Sellers', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin-sellers', __('All Sellers', 'eldokan-admin'), 'eldokan_backoffice_view_sellers'],
                    ['eldokan-admin-registrations', __('Registrations', 'eldokan-admin'), 'eldokan_backoffice_view_registrations'],
                ],
            ],
            'orders' => [
                'label' => __('Orders', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin-orders', __('All Orders', 'eldokan-admin'), 'eldokan_backoffice_view_orders'],
                ],
            ],
            'products' => [
                'label' => __('Products', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin-products', __('All Products', 'eldokan-admin'), 'eldokan_backoffice_view_products'],
                    ['eldokan-admin-categories', __('Categories & Attributes', 'eldokan-admin'), 'eldokan_backoffice_view_categories'],
                    ['eldokan-admin-home', __('Home, slides & banners', 'eldokan-admin'), 'eldokan_backoffice_view_home'],
                    ['edit.php?post_type=eldokan_story', __('Store Stories', 'eldokan-admin'), 'eldokan_backoffice_manage_stories'],
                ],
            ],
            'reports' => [
                'label' => __('Reports', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin-reports', __('Overview & Reports', 'eldokan-admin'), 'eldokan_backoffice_view_reports'],
                    ['eldokan-admin-commissions', __('Commission Rules', 'eldokan-admin'), 'eldokan_backoffice_view_commissions'],
                    ['eldokan-admin-statements', __('Seller Statements', 'eldokan-admin'), 'eldokan_backoffice_view_reports'],
                    ['eldokan-admin-performance', __('Seller Performance', 'eldokan-admin'), 'eldokan_backoffice_view_reports'],
                ],
            ],
            'settings' => [
                'label' => __('Settings', 'eldokan-admin'),
                'items' => [
                    ['eldokan-admin-settings', __('General', 'eldokan-admin'), 'eldokan_backoffice_manage_settings'],
                    ['eldokan-checkout-setup', __('Checkout & Payments', 'eldokan-admin'), 'eldokan_backoffice_manage_checkout'],
                    ['eldokan-admin-email-settings', __('Email & Notifications', 'eldokan-admin'), 'eldokan_backoffice_manage_email'],
                    ['eldokan-admin-team-permissions', __('Team Permissions', 'eldokan-admin'), 'manage_options'],
                    ['eldokan-admin-access-roles', __('Seller Role Templates', 'eldokan-admin'), 'manage_options'],
                    ['eldokan-admin-dashboard-control', __('Seller Dashboard Controls', 'eldokan-admin'), 'manage_options'],
                    ['eldokan-admin-activity', __('Activity & Audit', 'eldokan-admin'), 'eldokan_backoffice_view_activity'],
                    ['eldokan-admin-wcfm-index', __('WCFM Data Index', 'eldokan-admin'), 'manage_options'],
                ],
            ],
        ];
    }

    public static function navigation() {
        if (!self::relevant()) return;
        $screen = get_current_screen();
        // WP common.js inserts notices after wp-header-end; keep that anchor
        // outside all hero/cards. Native Stories already has its own WP anchor.
        if ($screen->post_type !== 'eldokan_story') {
            echo '<div class="eda-notice-region"><hr class="wp-header-end"></div>';
        }
        $current = self::current_slug();
        $active = self::current_section();
        if ($active === null) return;
        $section = self::sections()[$active];
        $available = array_values(array_filter($section['items'], static function ($item) {
            return current_user_can($item[2]) && self::route_available($item[0]);
        }));
        if (!$available) return;
        $current_label = $section['label'];
        foreach ($available as $item) if ($item[0] === $current) $current_label = $item[1];
        echo '<div class="eda-page-context"><nav class="eda-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'eldokan-admin') . '"><span>ElDokan</span><span aria-hidden="true">/</span><span>' . esc_html($section['label']) . '</span>';
        if ($current_label !== $section['label']) echo '<span aria-hidden="true">/</span><span aria-current="page">' . esc_html($current_label) . '</span>';
        echo '</nav>';
        if (count($available) > 1) {
            echo '<nav class="eda-context-nav" aria-label="' . esc_attr($section['label']) . '">';
            $storefront = false;
            foreach ($available as $item) {
                $content_item = in_array($item[0], ['eldokan-admin-home', 'edit.php?post_type=eldokan_story'], true);
                if ($content_item && !$storefront) {
                    echo '<span class="eda-context-group">' . esc_html__('Storefront Content', 'eldokan-admin') . '</span>';
                    $storefront = true;
                }
                $url = strpos($item[0], '.php?') !== false ? admin_url($item[0]) : admin_url('admin.php?page=' . rawurlencode($item[0]));
                echo '<a href="' . esc_url($url) . '"' . ($current === $item[0] ? ' aria-current="page"' : '') . '>' . esc_html($item[1]) . '</a>';
            }
            echo '</nav>';
        }
        echo '</div>';
    }

}
