<?php
/** Internal Admin Staff module: assigns the canonical product seller read by the ElDokan Customer API. */
if (!defined('ABSPATH')) { exit; }

final class ElDokan_Product_Seller {
    const META = '_eldokan_seller_user_id';
    const ROLES = ['eldokan_owner', 'wcfm_vendor', 'seller', 'vendor'];
    const STORE_OPTION = 'eldokan_product_seller_store_user';

    public static function init() {
        add_action('add_meta_boxes_product', [__CLASS__, 'add_box']);
        add_action('save_post_product', [__CLASS__, 'save_box'], 10, 2);
        add_filter('manage_edit-product_columns', [__CLASS__, 'add_column']);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_eldokan_product_seller_assign', [__CLASS__, 'assign_missing']);
        add_action('admin_post_eldokan_product_seller_owner', [__CLASS__, 'make_owner']);
    }

    /** Users the Customer API accepts as sellers. */
    private static function sellers() {
        return get_users(['role__in' => self::ROLES, 'orderby' => 'display_name', 'number' => 500]);
    }

    private static function is_seller($user_id) {
        $user = $user_id ? get_user_by('id', $user_id) : false;
        return $user && array_intersect((array) $user->roles, self::ROLES);
    }

    private static function label($user) {
        $company = trim((string) get_user_meta($user->ID, '_eldokan_company_name', true));
        return $company !== '' ? $company . ' (' . $user->display_name . ')' : $user->display_name;
    }

    private static function select($name, $selected) {
        $html = '<select name="' . esc_attr($name) . '" style="width:100%"><option value="">— No seller —</option>';
        foreach (self::sellers() as $user) {
            $html .= '<option value="' . esc_attr($user->ID) . '" ' . selected((int) $selected, $user->ID, false) . '>' . esc_html(self::label($user)) . '</option>';
        }
        return $html . '</select>';
    }

    public static function add_box() {
        add_meta_box('eldokan-product-seller', 'ElDokan seller', [__CLASS__, 'render_box'], 'product', 'side', 'high');
    }

    public static function render_box($post) {
        wp_nonce_field('eldokan_product_seller_' . $post->ID, 'eldokan_product_seller_nonce');
        $current = absint(get_post_meta($post->ID, self::META, true));
        echo self::select('eldokan_product_seller', $current); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in select().
        if (!self::sellers()) {
            echo '<p>No seller accounts exist yet. Open <a href="' . esc_url(admin_url('edit.php?post_type=product&page=eldokan-product-seller')) . '">Products → ElDokan sellers</a> to set one up.</p>';
        } elseif (!$current || !self::is_seller($current)) {
            echo '<p style="color:#b32d2e">Customers cannot buy this product until a seller is chosen.</p>';
        }
    }

    public static function save_box($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!isset($_POST['eldokan_product_seller_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eldokan_product_seller_nonce'])), 'eldokan_product_seller_' . $post_id)) { return; }
        if (!current_user_can('edit_product', $post_id)) { return; }
        $seller = absint($_POST['eldokan_product_seller'] ?? 0);
        if ($seller && self::is_seller($seller)) { update_post_meta($post_id, self::META, (string) $seller); }
        elseif (!$seller) { delete_post_meta($post_id, self::META); }
    }

    public static function add_column($columns) {
        $columns['eldokan_seller'] = 'ElDokan seller';
        return $columns;
    }

    public static function render_column($column, $post_id) {
        if ($column !== 'eldokan_seller') { return; }
        $id = absint(get_post_meta($post_id, self::META, true));
        $user = $id && self::is_seller($id) ? get_user_by('id', $id) : false;
        echo $user ? esc_html(self::label($user)) : '<span style="color:#b32d2e">Missing</span>';
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=product', 'ElDokan sellers', 'ElDokan sellers', 'manage_woocommerce', 'eldokan-product-seller', [__CLASS__, 'page']);
    }

    /** Products that the Customer API currently refuses to sell. */
    private static function missing_ids() {
        $ids = get_posts(['post_type' => 'product', 'post_status' => ['publish', 'private', 'draft', 'pending'], 'fields' => 'ids', 'numberposts' => -1, 'suppress_filters' => true]);
        return array_values(array_filter($ids, function ($id) {
            return !self::is_seller(absint(get_post_meta($id, self::META, true)));
        }));
    }

    public static function page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        $missing = self::missing_ids();
        $done = isset($_GET['assigned']) ? absint($_GET['assigned']) : null;
        echo '<div class="wrap"><h1>ElDokan sellers</h1>';
        if ($done !== null) { echo '<div class="notice notice-success"><p>' . esc_html($done) . ' product(s) updated.</p></div>'; }
        echo '<p>The storefront only sells products that have a seller. You can also change the seller of a single product from its edit screen.</p>';

        if (!self::is_seller((int) get_option(self::STORE_OPTION))) {
            echo '<h2>Optional: sell as the store</h2><p>Skip this if every product belongs to a marketplace seller. If the store sells some products itself (not a marketplace seller), create a store seller account and assign those products to it. It is a separate account with a random password and no dashboard use; staff accounts are not changed.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('eldokan_product_seller_owner');
            echo '<input type="hidden" name="action" value="eldokan_product_seller_owner"><p><label>Store name shown to customers<br><input type="text" name="company" required maxlength="100" value="' . esc_attr(get_bloginfo('name')) . '" class="regular-text"></label></p>';
            submit_button('Create the store seller account');
            echo '</form>';
        }
        if (!self::sellers()) { echo '</div>'; return; }

        echo '<h2>Products without a seller: ' . count($missing) . '</h2>';
        if ($missing) {
            $linkable = count(array_filter($missing, [__CLASS__, 'author_seller']));
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('eldokan_product_seller_assign');
            echo '<input type="hidden" name="action" value="eldokan_product_seller_assign">';
            echo '<h3>1. Link products to the seller who created them</h3><p>' . esc_html($linkable) . ' of these products were created by a seller account. This links each of them to its own seller (the "Created by" column). Products created by staff are left unchanged.</p>';
            echo '<p><button type="submit" name="mode" value="author" class="button button-primary"' . disabled($linkable, 0, false) . '>Link ' . esc_html($linkable) . ' product(s) to their sellers</button></p>';
            echo '<h3>2. Or choose a seller for selected products</h3>';
            echo '<table class="widefat striped" style="max-width:960px"><thead><tr><td class="check-column"><input type="checkbox" onclick="this.closest(\'table\').querySelectorAll(\'tbody input\').forEach(function(box){box.checked=this.checked}.bind(this))"></td><th>Product</th><th>Status</th><th>Created by</th></tr></thead><tbody>';
            foreach (array_slice($missing, 0, 300) as $id) {
                $author = get_userdata((int) get_post_field('post_author', $id));
                $note = $author ? self::label($author) . (self::author_seller($id) ? '' : ' — not a seller account') : '—';
                echo '<tr><th class="check-column"><input type="checkbox" name="products[]" value="' . esc_attr($id) . '"></th><td><a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html(get_the_title($id)) . '</a> #' . esc_html($id) . '</td><td>' . esc_html(get_post_status($id)) . '</td><td>' . esc_html($note) . '</td></tr>';
            }
            echo '</tbody></table>';
            if (count($missing) > 300) { echo '<p>Showing the first 300. Assign them, then reload for the rest.</p>'; }
            echo '<p><label>Assign the selected products to<br>' . self::select('seller', 0) . '</label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in select().
            echo '<p><button type="submit" name="mode" value="selected" class="button">Assign seller to selected products</button></p>';
            echo '</form>';
        }
        echo '</div>';
    }

    /** The product's WordPress author, when that account is an eligible seller. */
    private static function author_seller($product_id) {
        $author = (int) get_post_field('post_author', $product_id);
        return self::is_seller($author) ? $author : 0;
    }

    public static function assign_missing() {
        if (!current_user_can('manage_woocommerce')) { wp_die('Not allowed.'); }
        check_admin_referer('eldokan_product_seller_assign');
        $missing = self::missing_ids();
        $count = 0;
        if (($_POST['mode'] ?? '') === 'author') {
            // Explicit staff action: each product keeps the seller that created it.
            foreach ($missing as $id) {
                $author = self::author_seller($id);
                if ($author) { update_post_meta($id, self::META, (string) $author); $count++; }
            }
        } else {
            $seller = absint($_POST['seller'] ?? 0);
            $selected = array_intersect(array_map('absint', (array) ($_POST['products'] ?? [])), $missing);
            if ($seller && self::is_seller($seller)) {
                foreach ($selected as $id) { update_post_meta($id, self::META, (string) $seller); $count++; }
            }
        }
        wp_safe_redirect(admin_url('edit.php?post_type=product&page=eldokan-product-seller&assigned=' . $count));
        exit;
    }

    /**
     * A dedicated seller account for products the store sells itself. Staff
     * accounts must not receive seller roles: ElDokan Admin Staff withdraws
     * back-office capabilities from any account that holds one.
     */
    public static function make_owner() {
        if (!current_user_can('manage_woocommerce')) { wp_die('Not allowed.'); }
        check_admin_referer('eldokan_product_seller_owner');
        $company = sanitize_text_field(wp_unslash($_POST['company'] ?? ''));
        if ($company === '') { $company = get_bloginfo('name'); }
        if (!self::is_seller((int) get_option(self::STORE_OPTION))) {
            if (!get_role('eldokan_owner')) { add_role('eldokan_owner', 'ElDokan owner', ['read' => true]); }
            $login = 'eldokan-store';
            for ($i = 2; username_exists($login); $i++) { $login = 'eldokan-store-' . $i; }
            $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(32, true, true), 'display_name' => $company, 'role' => 'eldokan_owner']);
            if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
            // The Customer API shows a seller publicly only when it has a store name.
            update_user_meta($id, '_eldokan_company_name', $company);
            update_user_meta($id, '_eldokan_account_status', 'approved');
            update_option(self::STORE_OPTION, (int) $id, false);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=product&page=eldokan-product-seller'));
        exit;
    }
}

ElDokan_Product_Seller::init();
