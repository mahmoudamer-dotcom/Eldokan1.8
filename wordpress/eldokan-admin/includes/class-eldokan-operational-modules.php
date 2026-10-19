<?php
if (!defined('ABSPATH')) exit;

/** Internal modules defer to any active legacy helper; no automatic deactivation. */
final class ElDokan_Operational_Modules {
    const CHECKOUT_CAP = 'eldokan_backoffice_manage_checkout';
    const STORIES_CAP = 'eldokan_backoffice_manage_stories';
    const CAP_SCHEMA = 'eldokan_admin_operational_caps_v1';
    private static $loaded = false;

    public static function boot() {
        add_action('init', [__CLASS__, 'install_admin_caps'], 6);
        add_filter('map_meta_cap', [__CLASS__, 'staff_capabilities'], 20, 4);
        // All active plugin files and the legacy checkout init (priority 20)
        // have executed before deciding which implementation owns each module.
        add_action('plugins_loaded', [__CLASS__, 'load'], 90);
    }

    public static function install_admin_caps() {
        if (get_option(self::CAP_SCHEMA) === '1') return;
        $administrator = get_role('administrator');
        if (!$administrator) return;
        $administrator->add_cap(self::CHECKOUT_CAP);
        $administrator->add_cap(self::STORIES_CAP);
        update_option(self::CAP_SCHEMA, '1', false);
    }

    public static function staff_capabilities($caps, $cap, $user_id, $args) {
        if (!in_array($cap, [self::CHECKOUT_CAP, self::STORIES_CAP], true)) return $caps;
        $user = get_userdata($user_id);
        $seller_roles = ['customer', 'eldokan_owner', 'eldokan_admin', 'eldokan_operator', 'eldokan_finance', 'eldokan_viewer', 'eldokan_editor', 'wcfm_vendor', 'vendor', 'seller', 'dc_vendor', 'wcfm_shop_manager', 'wcfm_delivery_boy'];
        if (!$user || array_intersect($seller_roles, (array) $user->roles) || !user_can($user, 'eldokan_backoffice_access')) {
            return ['do_not_allow'];
        }
        // Keep the primitive capability check too; staff access alone is insufficient.
        return $caps;
    }

    private static function enabled($constant) {
        return !defined($constant) || constant($constant) === true;
    }

    private static function legacy_notice($module) {
        add_action('admin_notices', static function () use ($module) {
            if (!current_user_can('activate_plugins')) return;
            echo '<div class="notice notice-warning"><p><strong>ElDokan Admin Staff:</strong> ' . esc_html($module) . ' is still provided by an active standalone helper. The embedded module is paused to avoid duplicate hooks/routes. Verify the staged replacement before manually deactivating that helper. Saved data is retained.</p></div>';
        });
    }

    public static function load() {
        if (self::$loaded) return;
        self::$loaded = true;
        if (self::enabled('ELDOKAN_ADMIN_EMBED_CHECKOUT')) {
            $legacy = false;
            foreach (['ElDokan_Checkout_Setup', 'ElDokan_Checkout_Setup_English', 'ElDokan_Checkout_Setup_Diagnostics', 'ElDokan_Checkout_Setup_Payment_Options', 'ElDokan_Checkout_Setup_Payment_Diagnostics'] as $class) {
                if (class_exists($class, false)) { $legacy = true; break; }
            }
            if ($legacy) self::legacy_notice('Checkout');
            else require_once dirname(__DIR__) . '/modules/checkout/eldokan-checkout-setup.php';
        }
        if (self::enabled('ELDOKAN_ADMIN_EMBED_STORIES')) {
            if (class_exists('ElDokan_Stories', false) || class_exists('ElDokan_Stories_Picker', false)) self::legacy_notice('Stories');
            else require_once dirname(__DIR__) . '/modules/stories/eldokan-stories.php';
        }
        if (self::enabled('ELDOKAN_ADMIN_EMBED_PRODUCT_SELLER')) {
            if (class_exists('ElDokan_Product_Seller', false)) self::legacy_notice('Product seller');
            else require_once dirname(__DIR__) . '/modules/product-seller/eldokan-product-seller.php';
        }
    }
}
