<?php
if (!defined('ABSPATH')) exit;

final class ElDokan_Backoffice_Roles {

    const SCHEMA_VERSION = '1.2.0';
    const SCHEMA_OPTION  = 'eldokan_backoffice_roles_schema';
    const INIT_OPTION    = 'eldokan_backoffice_roles_initialized';

    public function __construct() {
        add_action('init', [__CLASS__, 'maybe_install'], 5);

        add_action(
            'admin_menu',
            [$this, 'register_permissions_page'],
            80
        );

        add_action(
            'admin_post_eldokan_backoffice_save_role',
            [__CLASS__, 'save_role_permissions']
        );

        add_action(
            'admin_post_eldokan_backoffice_reset_role',
            [__CLASS__, 'reset_role_permissions']
        );
        add_filter(
            'woocommerce_prevent_admin_access',
             [__CLASS__, 'allow_backoffice_admin_access']
        );
    }
    public static function allow_backoffice_admin_access($prevent_access) {
    if (
        is_user_logged_in() &&
        current_user_can('eldokan_backoffice_access')
    ) {
        return false;
    }

    return $prevent_access;
}
    
    public static function roles() {
        return [

            'eldokan_backoffice_manager' => [
                'label' => 'ElDokan Manager',
                'upload_files' => true,
                'caps' => [
                    'eldokan_backoffice_access',
                    'eldokan_backoffice_view_dashboard',

                    'eldokan_backoffice_view_sellers',
                    'eldokan_backoffice_manage_sellers',

                    'eldokan_backoffice_view_registrations',
                    'eldokan_backoffice_manage_registrations',

                    'eldokan_backoffice_view_products',
                    'eldokan_backoffice_manage_products',

                    'eldokan_backoffice_view_orders',
                    'eldokan_backoffice_manage_orders',

                    'eldokan_backoffice_view_categories',
                    'eldokan_backoffice_manage_categories',

                    'eldokan_backoffice_view_reports',
                    'eldokan_backoffice_export_reports',

                    'eldokan_backoffice_view_commissions',
                    'eldokan_backoffice_manage_commissions',

                    'eldokan_backoffice_view_activity',
                    'eldokan_backoffice_view_home',
                    'eldokan_backoffice_manage_home',
                ],
            ],

            'eldokan_backoffice_developer' => [
                'label' => 'ElDokan Developer',
                'upload_files' => true,
                'caps' => [
                    'eldokan_backoffice_access',
                    'eldokan_backoffice_view_dashboard',

                    'eldokan_backoffice_view_sellers',
                    'eldokan_backoffice_view_registrations',

                    'eldokan_backoffice_view_products',
                    'eldokan_backoffice_manage_products',

                    'eldokan_backoffice_view_orders',

                    'eldokan_backoffice_view_categories',
                    'eldokan_backoffice_manage_categories',

                    'eldokan_backoffice_view_reports',
                    'eldokan_backoffice_view_activity',
                    'eldokan_backoffice_view_home',
                ],
            ],

            'eldokan_backoffice_staff' => [
                'label' => 'ElDokan Staff',
                'upload_files' => true,
                'caps' => [
                    'eldokan_backoffice_access',
                    'eldokan_backoffice_view_dashboard',

                    'eldokan_backoffice_view_sellers',
                    'eldokan_backoffice_view_registrations',

                    'eldokan_backoffice_view_products',
                    'eldokan_backoffice_manage_products',

                    'eldokan_backoffice_view_orders',
                    'eldokan_backoffice_manage_orders',

                    'eldokan_backoffice_view_categories',
                    'eldokan_backoffice_view_reports',
                    'eldokan_backoffice_view_home',
                ],
            ],

            'eldokan_backoffice_viewer' => [
                'label' => 'ElDokan Viewer',
                'upload_files' => false,
                'caps' => [
                    'eldokan_backoffice_access',
                    'eldokan_backoffice_view_dashboard',

                    'eldokan_backoffice_view_sellers',
                    'eldokan_backoffice_view_registrations',

                    'eldokan_backoffice_view_products',
                    'eldokan_backoffice_view_orders',

                    'eldokan_backoffice_view_categories',
                    'eldokan_backoffice_view_reports',
                    'eldokan_backoffice_view_home',
                ],
            ],

        ];
    }

    public static function capability_groups() {
        return [

            'General' => [
                'eldokan_backoffice_access'
                    => 'Access ElDokan Admin',

                'eldokan_backoffice_view_dashboard'
                    => 'View Dashboard',

                'upload_files'
                    => 'Upload media / product images',
            ],

            'Sellers & Registrations' => [
                'eldokan_backoffice_view_sellers'
                    => 'View sellers',

                'eldokan_backoffice_manage_sellers'
                    => 'Manage seller status and rating',

                'eldokan_backoffice_view_registrations'
                    => 'View registrations',

                'eldokan_backoffice_manage_registrations'
                    => 'Approve / reject registrations',

                'eldokan_backoffice_delete_accounts'
                    => 'Permanently delete seller accounts',
            ],

            'Products' => [
                'eldokan_backoffice_view_products'
                    => 'View products',

                'eldokan_backoffice_manage_products'
                    => 'Create and edit products',
            ],

            'Orders' => [
                'eldokan_backoffice_view_orders'
                    => 'View orders',

                'eldokan_backoffice_manage_orders'
                    => 'Update order workflow/status',
            ],

            'Categories' => [
                'eldokan_backoffice_view_categories'
                    => 'View categories',

                'eldokan_backoffice_manage_categories'
                    => 'Manage categories and attributes',
            ],

            'Home content' => [
                'eldokan_backoffice_view_home'
                    => 'View Home content',

                'eldokan_backoffice_manage_home'
                    => 'Manage Home content',
            ],

            'Reports' => [
                'eldokan_backoffice_view_reports'
                    => 'View reports',

                'eldokan_backoffice_export_reports'
                    => 'Export seller reports',
            ],

            'Commissions' => [
                'eldokan_backoffice_view_commissions'
                    => 'View commissions',

                'eldokan_backoffice_manage_commissions'
                    => 'Change commission rules',
            ],

            'Operational modules (explicit grant)' => [
                'eldokan_backoffice_manage_checkout' => 'Manage sensitive checkout/payment configuration',
                'eldokan_backoffice_manage_stories' => 'Manage bilingual Stories',
            ],

            'Administration' => [
                'eldokan_backoffice_view_activity'
                    => 'View activity log',

                'eldokan_backoffice_manage_email'
                    => 'Manage email settings',

                'eldokan_backoffice_manage_settings'
                    => 'Manage ElDokan settings',
            ],

        ];
    }

    public static function custom_caps() {
        $caps = [];

        foreach (self::capability_groups() as $group) {
            foreach ($group as $cap => $label) {

                if (
                    strpos(
                        $cap,
                        'eldokan_backoffice_'
                    ) === 0
                ) {
                    $caps[] = $cap;
                }
            }
        }

        return array_values(
            array_unique($caps)
        );
    }

    private static function sync_admin_access_bridge($role) {
    if (!$role instanceof WP_Role) {
        return;
    }

    $role->add_cap('view_admin_dashboard');
}

    private static function sync_product_native_bridge($role) {

        if (!$role instanceof WP_Role) {
            return;
        }

        /*
         * These are the minimum native WordPress/WooCommerce
         * product permissions needed for the normal WooCommerce
         * product editor.
         *
         * We DO NOT grant:
         *
         * manage_options
         * manage_woocommerce
         * delete_products
         * user-management capabilities
         */

        $native_product_caps = [
            'edit_products',
            'edit_others_products',
            'edit_published_products',
            'edit_private_products',
            'publish_products',
            'read_private_products',
        ];

        $enabled =
    $role->has_cap(
        'eldokan_backoffice_manage_products'
    );

        foreach ($native_product_caps as $cap) {

            if ($enabled) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }
    }
    
    private static function sync_category_native_bridge($role) {

    if (!$role instanceof WP_Role) {
        return;
    }

    $can_manage_categories =
        $role->has_cap(
            'eldokan_backoffice_manage_categories'
        );

    $can_edit_products =
        $role->has_cap(
            'eldokan_backoffice_manage_products'
    );
    /*
     * Product editors need to be able to assign
     * existing product categories.
     */
    if ($can_manage_categories || $can_edit_products) {
        $role->add_cap('assign_product_terms');
    } else {
        $role->remove_cap('assign_product_terms');
    }

    /*
     * Category managers can create/edit categories,
     * but we intentionally do NOT grant
     * delete_product_terms for now.
     */
    if ($can_manage_categories) {
        $role->add_cap('manage_product_terms');
        $role->add_cap('edit_product_terms');
    } else {
        $role->remove_cap('manage_product_terms');
        $role->remove_cap('edit_product_terms');
    }

    $role->remove_cap('delete_product_terms');
}

    public static function activate() {
        self::install();
    }

    public static function maybe_install() {

        if (
            get_option(self::SCHEMA_OPTION)
            !== self::SCHEMA_VERSION
        ) {
            self::install();
        }
    }

    private static function install() {

        $initialized =
            (bool) get_option(
                self::INIT_OPTION,
                false
            );
        $is_schema_upgrade = get_option(self::SCHEMA_OPTION) !== self::SCHEMA_VERSION;

        foreach (
            self::roles()
            as $slug => $definition
        ) {

            $role = get_role($slug);

            $is_new = !$role;

            if ($is_new) {

                $role = add_role(
                    $slug,
                    $definition['label'],
                    [
                        'read' => true,
                    ]
                );
            }

            if (!$role) {
                continue;
            }

            $role->add_cap('read');

            /*
             * Defaults only on first creation.
             *
             * After that the choices you make
             * from Team Permissions are preserved.
             */

            if ($is_new || !$initialized) {

                foreach (
                    self::custom_caps()
                    as $cap
                ) {
                    $role->remove_cap($cap);
                }

                foreach (
                    $definition['caps']
                    as $cap
                ) {
                    $role->add_cap($cap);
                }

                if (
                    !empty(
                        $definition['upload_files']
                    )
                ) {
                    $role->add_cap(
                        'upload_files'
                    );
                } else {
                    $role->remove_cap(
                        'upload_files'
                    );
                }
            }

            /*
             * 1.2.0 introduces Home capabilities. Apply only those new
             * defaults during upgrade so existing Team Permissions choices
             * for every older capability remain untouched.
             */
            if ($is_schema_upgrade && !$is_new && $initialized) {
                foreach (['eldokan_backoffice_view_home', 'eldokan_backoffice_manage_home'] as $home_cap) {
                    if (in_array($home_cap, $definition['caps'], true)) {
                        $role->add_cap($home_cap);
                    } else {
                        $role->remove_cap($home_cap);
                    }
                }
            }

            self::sync_admin_access_bridge($role);
            self::sync_product_native_bridge($role);
            self::sync_category_native_bridge($role);

        }

        /*
         * Administrator remains the native
         * WordPress Administrator.
         *
         * We only ADD ElDokan capabilities.
         */

        $administrator =
            get_role('administrator');

        if ($administrator) {

            foreach (
                self::custom_caps()
                as $cap
            ) {
                $administrator->add_cap(
                    $cap
                );
            }
        }

        update_option(
            self::INIT_OPTION,
            1,
            false
        );

        update_option(
            self::SCHEMA_OPTION,
            self::SCHEMA_VERSION,
            false
        );
    }

    public function register_permissions_page() {

        add_submenu_page(
            'eldokan-admin',
            'Team Permissions',
            'Team Permissions',
            'manage_options',
            'eldokan-admin-team-permissions',
            [$this, 'render_permissions_page']
        );
    }

    public function render_permissions_page() {

        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $roles = self::roles();

        $selected_slug =
            sanitize_key(
                $_GET['role']
                ?? 'eldokan_backoffice_manager'
            );

        if (!isset($roles[$selected_slug])) {

            $selected_slug =
                'eldokan_backoffice_manager';
        }

        $role =
            get_role($selected_slug);

        $definition =
            $roles[$selected_slug];

        echo '<div class="wrap eda eda-permissions-page">';

        echo '<section class="eda-hero eda-hero-compact"><div class="eda-hero-copy"><span class="eda-eyebrow">' . esc_html__('Access Control', 'eldokan-admin') . '</span><h1>' . esc_html__('Team Permissions', 'eldokan-admin') . '</h1><p>' . esc_html__('Manage ElDokan employee access without changing WordPress, WooCommerce or Seller roles.', 'eldokan-admin') . '</p></div><div class="eda-hero-meta"><span class="eda-version-pill">' . esc_html__('Admin only', 'eldokan-admin') . '</span></div></section>';


        if (!empty($_GET['saved'])) {

            echo '
            <div class="notice notice-success is-dismissible">
                <p>
                    ' . esc_html__('Permissions saved successfully.', 'eldokan-admin') . '
                </p>
            </div>
            ';
        }

        if (!empty($_GET['reset'])) {

            echo '
            <div class="notice notice-success is-dismissible">
                <p>
                    ' . esc_html__('Default permissions restored.', 'eldokan-admin') . '
                </p>
            </div>
            ';
        }

        echo '
        <nav
            class="nav-tab-wrapper eda-role-tabs"
        >
        ';

        foreach (
            $roles
            as $slug => $data
        ) {

            $url =
                admin_url(
                    'admin.php?page=eldokan-admin-team-permissions&role='
                    . rawurlencode($slug)
                );

            $class =
                $slug === $selected_slug
                ? 'nav-tab nav-tab-active'
                : 'nav-tab';

            echo '
            <a
                class="'.esc_attr($class).'"
                href="'.esc_url($url).'"
            >
                '.esc_html($data['label']).'
            </a>
            ';
        }

        echo '</nav>';

        if (!$role) {

            echo '
            <div class="notice notice-error">
                <p>
                    ' . esc_html__('This role does not exist.', 'eldokan-admin') . '
                </p>
            </div>
            </div>
            ';

            return;
        }

        echo '<div class="eda-permissions-shell">';

echo '<div class="card eda-card eda-permissions-card">';
echo '<div class="eda-section-head"><div><span class="eda-card-kicker">' . esc_html__('Employee role', 'eldokan-admin') . '</span><h2>'.esc_html(translate($definition['label'], 'eldokan-admin')).'</h2><p><code>'.esc_html($selected_slug).'</code></p></div><span class="eda-badge">' . esc_html__('Administrator protected', 'eldokan-admin') . '</span></div>';

        echo '
        <p>
            <strong>' . esc_html__('Important:', 'eldokan-admin') . '</strong>
            ' . esc_html__('Administrator always keeps
            full ElDokan access.', 'eldokan-admin') . '
        </p>
        ';

        echo '
        <form
            method="post"
            action="'.esc_url(
                admin_url('admin-post.php')
            ).'"
        >
        ';

        echo '
        <input
            type="hidden"
            name="action"
            value="eldokan_backoffice_save_role"
        >
        ';

        echo '
        <input
            type="hidden"
            name="role_slug"
            value="'.esc_attr(
                $selected_slug
            ).'"
        >
        ';

        wp_nonce_field(
            'eldokan_backoffice_role_'
            . $selected_slug
        );

        foreach (
            self::capability_groups()
            as $group_name => $caps
        ) {

echo '<h3 class="eda-permission-group-title">'.esc_html(translate($group_name, 'eldokan-admin')).'</h3>';
echo '<div class="eda-permission-grid">';
            foreach (
                $caps
                as $cap => $label
            ) {

                $checked =
                    $role->has_cap($cap);

                $danger =
                    in_array(
                        $cap,
                        [
                            'eldokan_backoffice_delete_accounts',
                            'eldokan_backoffice_manage_settings',
                            'eldokan_backoffice_manage_commissions',
                        ],
                        true
                    );

echo '<label class="eda-permission-item">';
                echo '
                <input
                    type="checkbox"
                    name="caps[]"
                    value="'.esc_attr($cap).'"
                    '.checked(
                        $checked,
                        true,
                        false
                    ).'
                >
                ';

                echo '<span>';

                echo esc_html(translate($label, 'eldokan-admin'));

                if ($danger) {

echo '<strong class="eda-sensitive">' . esc_html__('Sensitive', 'eldokan-admin') . '</strong>';                }

echo '<br><code class="eda-cap-code">'.esc_html($cap).'</code>';
                echo '</span>';

                echo '</label>';
            }

            echo '</div>';
        }

        echo '
        <p style="margin-top:24px">
        ';

        submit_button(
            __('Save Permissions', 'eldokan-admin'),
            'primary',
            'submit',
            false
        );

        echo '</p>';

        echo '</form>';

        echo '
        <hr style="margin:28px 0">
        ';

        echo '
        <form
            method="post"
            action="'.esc_url(
                admin_url('admin-post.php')
            ).'"
            onsubmit="
                return confirm(
                    \'Restore the original default permissions for this ElDokan role?\'
                );
            "
        >
        ';

        echo '
        <input
            type="hidden"
            name="action"
            value="eldokan_backoffice_reset_role"
        >
        ';

        echo '
        <input
            type="hidden"
            name="role_slug"
            value="'.esc_attr(
                $selected_slug
            ).'"
        >
        ';

        wp_nonce_field(
            'eldokan_backoffice_reset_role_'
            . $selected_slug
        );

        submit_button(
            __('Restore Default Permissions', 'eldokan-admin'),
            'secondary',
            'submit',
            false
        );

        echo '</form>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public static function save_role_permissions() {

        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $slug =
            sanitize_key(
                $_POST['role_slug'] ?? ''
            );

        $roles = self::roles();

        if (!isset($roles[$slug])) {
            wp_die('Invalid ElDokan role.');
        }

        check_admin_referer(
            'eldokan_backoffice_role_'
            . $slug
        );

        $role =
            get_role($slug);

        if (!$role) {
            wp_die(
                'ElDokan role not found.'
            );
        }

        $posted =
            isset($_POST['caps'])
            && is_array($_POST['caps'])
            ? array_map(
                'sanitize_key',
                wp_unslash($_POST['caps'])
            )
            : [];

        $allowed_custom =
            self::custom_caps();

        foreach (
            $allowed_custom
            as $cap
        ) {

            if (
                in_array(
                    $cap,
                    $posted,
                    true
                )
            ) {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }

        if (
            in_array(
                'upload_files',
                $posted,
                true
            )
        ) {
            $role->add_cap(
                'upload_files'
            );
        } else {
            $role->remove_cap(
                'upload_files'
            );
        }

        $role->add_cap('read');

        self::sync_admin_access_bridge($role);
        self::sync_product_native_bridge($role);
        self::sync_category_native_bridge($role);

        update_option(
            'eldokan_backoffice_roles_last_updated',
            current_time('mysql'),
            false
        );

        wp_safe_redirect(
            admin_url(
                'admin.php?page='
                .'eldokan-admin-team-permissions'
                .'&role='.$slug
                .'&saved=1'
            )
        );

        exit;
    }

    public static function reset_role_permissions() {

        if (!current_user_can('manage_options')) {
            wp_die('Not allowed');
        }

        $slug =
            sanitize_key(
                $_POST['role_slug'] ?? ''
            );

        $roles = self::roles();

        if (!isset($roles[$slug])) {
            wp_die('Invalid ElDokan role.');
        }

        check_admin_referer(
            'eldokan_backoffice_reset_role_'
            . $slug
        );

        $role =
            get_role($slug);

        if (!$role) {
            wp_die(
                'ElDokan role not found.'
            );
        }

        foreach (
            self::custom_caps()
            as $cap
        ) {
            $role->remove_cap($cap);
        }

        foreach (
            $roles[$slug]['caps']
            as $cap
        ) {
            $role->add_cap($cap);
        }

        if (
            !empty(
                $roles[$slug]['upload_files']
            )
        ) {
            $role->add_cap(
                'upload_files'
            );
        } else {
            $role->remove_cap(
                'upload_files'
            );
        }

        $role->add_cap('read');

        self::sync_admin_access_bridge($role);
        self::sync_product_native_bridge($role);
        self::sync_category_native_bridge($role);

        update_option(
            'eldokan_backoffice_roles_last_updated',
            current_time('mysql'),
            false
        );

        wp_safe_redirect(
            admin_url(
                'admin.php?page='
                .'eldokan-admin-team-permissions'
                .'&role='.$slug
                .'&reset=1'
            )
        );

        exit;
    }
}
