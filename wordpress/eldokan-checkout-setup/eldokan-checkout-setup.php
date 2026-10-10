<?php
/**
 * Plugin Name: ElDokan Checkout Setup
 * Description: English admin settings for the existing ElDokan Customer API 0.8.0 checkout adapter.
 * Version: 0.1.2
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) { exit; }

final class ElDokan_Checkout_Setup_Diagnostics {
    private const MAP = 'eldokan_checkout_setup_shipping_types';
    private static $collecting = false;
    private static $packages = [];
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_eldokan_checkout_setup_save', [__CLASS__, 'save']);
        add_filter('eldokan_customer_api_checkout_shipping_type', [__CLASS__, 'shipping_type'], 20, 2);
        add_filter('rest_request_before_callbacks', [__CLASS__, 'begin_quote'], 10, 3);
        add_filter('woocommerce_shipping_packages', [__CLASS__, 'capture_packages'], PHP_INT_MAX);
        add_filter('rest_request_after_callbacks', [__CLASS__, 'finish_quote'], 10, 3);
    }
    public static function begin_quote($response, $handler, $request) {
        if (in_array($request->get_route(), ['/eldokan-customer/v1/checkout', '/eldokan-customer/v1/checkout/quote'], true)) {
            self::$packages = [];
            self::$collecting = (int) get_option('eldokan_checkout_diagnostics_until', 0) > time();
        }
        return $response;
    }
    public static function capture_packages($packages) {
        if (!self::$collecting || !is_array($packages)) { return $packages; }
        $snapshot = [];
        foreach (array_slice(array_values($packages), 0, 30) as $package) {
            if (!is_array($package) || !$package) { continue; }
            $rates = [];
            foreach (array_slice(array_values((array) ($package['rates'] ?? [])), 0, 30) as $rate) {
                if (!is_a($rate, 'WC_Shipping_Rate')) { continue; }
                $method = (string) $rate->get_method_id();
                $default = $method === 'local_pickup' ? 'pickup' : (in_array($method, ['flat_rate', 'free_shipping'], true) ? 'door_delivery' : null);
                $type = apply_filters('eldokan_customer_api_checkout_shipping_type', $default, $method);
                $rates[] = [
                    'method' => substr(sanitize_key($method), 0, 100),
                    'instance' => (int) $rate->get_instance_id(),
                    'cost' => (string) $rate->get_cost(),
                    'classification' => in_array($type, ['door_delivery', 'pickup'], true) ? $type : 'unsupported',
                ];
            }
            $snapshot[] = ['rates' => $rates];
        }
        self::$packages = $snapshot;
        return $packages;
    }
    public static function finish_quote($response, $handler, $request) {
        if (!in_array($request->get_route(), ['/eldokan-customer/v1/checkout', '/eldokan-customer/v1/checkout/quote'], true) || !self::$collecting) { return $response; }
        self::$collecting = false;
        // Store only shipping metadata. Never store the request, cart, address,
        // payment credentials, customer identity, or the complete response.
        $data = $response instanceof WP_REST_Response ? $response->get_data() : [];
        $reference = is_array($data) ? ($data['meta']['request_id'] ?? '') : '';
        if (!is_string($reference) || !preg_match('/^req_[a-f0-9]{32}$/D', $reference)) { return $response; }
        $common = self::$packages ? ['door_delivery', 'pickup'] : [];
        $problems = [];
        if (!self::$packages) { $problems[] = 'No calculated shipping packages observed. Check shipping countries, address validation and package-generation hooks.'; }
        foreach (self::$packages as $index => $package) {
            $types = array_values(array_unique(array_filter(array_column($package['rates'], 'classification'), static function ($type) { return $type !== 'unsupported'; })));
            $common = array_values(array_intersect($common, $types));
            if (!$package['rates']) { $problems[] = 'Package ' . ($index + 1) . ': WooCommerce returned no rate. Check the matching zone, enabled methods and vendor shipping configuration.'; }
            elseif (!$types) { $problems[] = 'Package ' . ($index + 1) . ': all returned methods are unsupported. Classify actual delivery methods as Door delivery below.'; }
        }
        if (self::$packages && !$common && !$problems) { $problems[] = 'Packages do not share a delivery type. Every package must offer Door delivery, or every package must offer Store pickup.'; }
        if (!$problems) { $problems[] = 'Compatible shipping types were observed. Check the API quote issues and any later rate changes if checkout remains blocked.'; }
        $reports = get_transient('eldokan_checkout_shipping_reports');
        $reports = is_array($reports) ? $reports : [];
        array_unshift($reports, ['request_id' => $reference, 'utc' => gmdate('c'), 'packages' => self::$packages, 'common_types' => $common, 'findings' => $problems]);
        set_transient('eldokan_checkout_shipping_reports', array_slice($reports, 0, 5), 2 * HOUR_IN_SECONDS);
        self::$packages = [];
        return $response;
    }
    private static function methods() {
        if (!function_exists('WC') || !WC()->shipping()) { return []; }
        WC()->shipping()->load_shipping_methods();
        return WC()->shipping()->get_shipping_methods();
    }
    private static function gateways() {
        return function_exists('WC') ? WC()->payment_gateways()->payment_gateways() : [];
    }
    public static function shipping_type($default, $method) {
        $map = get_option(self::MAP, []);
        $type = is_array($map) ? ($map[$method] ?? '') : '';
        return in_array($type, ['door_delivery', 'pickup'], true) ? $type : $default;
    }
    public static function menu() {
        add_submenu_page('woocommerce', 'ElDokan Checkout', 'ElDokan Checkout', 'manage_woocommerce', 'eldokan-checkout-setup', [__CLASS__, 'page']);
    }
    private static function redirect_error($code) {
        wp_safe_redirect(add_query_arg('setup_error', $code, admin_url('admin.php?page=eldokan-checkout-setup')));
        exit;
    }
    public static function save() {
        if (!current_user_can('manage_woocommerce')) { wp_die('Not allowed.', '', ['response' => 403]); }
        check_admin_referer('eldokan_checkout_setup_save');
        $origin = isset($_POST['frontend_origin']) && is_string($_POST['frontend_origin']) ? trim(wp_unslash($_POST['frontend_origin'])) : '';
        $parts = wp_parse_url($origin);
        if ($origin !== '' && (strlen($origin) > 2048 || !filter_var($origin, FILTER_VALIDATE_URL) || !$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true))) {
            self::redirect_error('invalid_origin');
        }
        $id = isset($_POST['gateway_id']) && is_string($_POST['gateway_id']) ? sanitize_text_field(wp_unslash($_POST['gateway_id'])) : '';
        $gateways = self::gateways();
        if ($id !== '' && (!isset($gateways[$id]) || !is_a($gateways[$id], 'Paymob_Payment') || is_a($gateways[$id], 'Paymob_Main_Gateway') || is_a($gateways[$id], 'Paymob_Subscription_Gateway') || $id === 'paymob-pixel')) {
            self::redirect_error('invalid_gateway');
        }
        $submitted = isset($_POST['shipping_types']) && is_array($_POST['shipping_types']) ? wp_unslash($_POST['shipping_types']) : [];
        $map = [];
        foreach (self::methods() as $method) {
            $method_id = $method->id;
            $type = $submitted[$method_id] ?? '';
            if (is_string($type) && in_array($type, ['door_delivery', 'pickup'], true)) { $map[$method_id] = $type; }
        }
        update_option('eldokan_customer_api_frontend_origin', rtrim($origin, '/'), false);
        if ($id !== '') { update_option('eldokan_customer_api_paymob_gateway', $id, false); }
        update_option('eldokan_customer_api_paymob_callback_profile', isset($_POST['accept_transaction']) && $_POST['accept_transaction'] === '1' ? 'accept_transaction' : '', false);
        update_option(self::MAP, $map, false);
        update_option('eldokan_checkout_diagnostics_until', isset($_POST['shipping_diagnostics']) && $_POST['shipping_diagnostics'] === '1' ? time() + 2 * HOUR_IN_SECONDS : 0, false);
        if (empty($_POST['shipping_diagnostics'])) { delete_transient('eldokan_checkout_shipping_reports'); }
        wp_safe_redirect(admin_url('admin.php?page=eldokan-checkout-setup&saved=1'));
        exit;
    }
    public static function page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        $origin = get_option('eldokan_customer_api_frontend_origin', '');
        $gateway_id = get_option('eldokan_customer_api_paymob_gateway', 'paymob');
        $profile = get_option('eldokan_customer_api_paymob_callback_profile', '');
        $map = get_option(self::MAP, []);
        $map = is_array($map) ? $map : [];
        $error = isset($_GET['setup_error']) && is_string($_GET['setup_error']) ? sanitize_key(wp_unslash($_GET['setup_error'])) : '';
        $errors = [
            'invalid_origin' => 'Settings were not saved. Storefront origin must contain only the HTTPS scheme and domain, for example https://www.eldokan.com. Enter the address of your actual storefront. A callback URL contains a query string and belongs in the Paymob webhook settings.',
            'invalid_gateway' => 'Settings were not saved. Select an installed native hosted Paymob gateway.',
        ];
        $callback = add_query_arg('wc-api', 'paymob_callback', home_url('/'));
        echo '<div class="wrap" dir="ltr" lang="en"><h1>ElDokan Checkout Setup</h1>';
        if (isset($errors[$error])) { echo '<div class="notice notice-error" role="alert"><p>' . esc_html($errors[$error]) . '</p></div>'; }
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Checkout settings saved.</p></div>'; }
        echo '<p>Configure the existing Customer API checkout adapter. Shipping prices come from WooCommerce shipping zones. Payments use the installed native Paymob gateway.</p>';
        echo '<h2>Requirements</h2><ul>';
        echo '<li>Customer API: <strong>' . esc_html(defined('ELDOKAN_CUSTOMER_API_VERSION') ? ELDOKAN_CUSTOMER_API_VERSION : 'Not active') . '</strong></li>';
        echo '<li>Paymob: <strong>' . esc_html(defined('PAYMOB_VERSION') ? PAYMOB_VERSION : 'Not active') . '</strong> &mdash; Customer API 0.8.0 requires version 4.1.15 exactly.</li>';
        echo '<li>WooCommerce: <strong>' . esc_html(defined('WC_VERSION') ? WC_VERSION : 'Not active') . '</strong></li></ul>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="eldokan_checkout_setup_save">';
        wp_nonce_field('eldokan_checkout_setup_save');
        echo '<h2>Paymob connection</h2><p><label for="eldokan-storefront-origin"><strong>Storefront origin (HTTPS)</strong></label><br><input id="eldokan-storefront-origin" type="url" name="frontend_origin" class="regular-text" dir="ltr" aria-describedby="eldokan-origin-help" placeholder="https://www.eldokan.com" value="' . esc_attr($origin) . '"></p>';
        echo '<p id="eldokan-origin-help" class="description">Enter the HTTPS origin of your Next.js storefront, for example <code>https://www.eldokan.com</code> if that domain serves your storefront. Payments return to <code>/orders/...</code> on this origin. Use an HTTPS development URL when working locally.</p>';
        echo '<p><label for="eldokan-paymob-callback"><strong>WordPress Paymob callback URL</strong></label><br><input id="eldokan-paymob-callback" type="url" class="large-text code" dir="ltr" readonly value="' . esc_attr($callback) . '" aria-describedby="eldokan-callback-help"></p>';
        echo '<p id="eldokan-callback-help" class="description">Copy this URL to the transaction callback/webhook settings in Paymob. This read-only field shows the native WordPress callback; changing it is handled in your Paymob account.</p>';
        echo '<p><label for="eldokan-paymob-gateway"><strong>Native hosted Paymob gateway</strong></label><br><select id="eldokan-paymob-gateway" name="gateway_id"><option value="">Choose a gateway</option>';
        foreach (self::gateways() as $id => $gateway) {
            if (!is_a($gateway, 'Paymob_Payment') || is_a($gateway, 'Paymob_Main_Gateway') || is_a($gateway, 'Paymob_Subscription_Gateway') || $id === 'paymob-pixel') { continue; }
            echo '<option value="' . esc_attr($id) . '" ' . selected($gateway_id, $id, false) . '>' . esc_html($gateway->get_method_title() . ' [' . $id . ']') . '</option>';
        }
        echo '</select></p>';
        echo '<p><label><input type="checkbox" name="accept_transaction" value="1" ' . checked($profile, 'accept_transaction', false) . '> I have verified that Paymob sends native Accept TRANSACTION callbacks to the original WooCommerce Paymob plugin.</label></p>';
        echo '<p class="description">This checkbox records compatibility with the callback profile. Enable and configure the gateway and its credentials in the original Paymob settings.</p>';
        echo '<h2>Shipping method classification</h2><p>Keep standard methods on Automatic. Classify an installed delivery method as Door delivery, or an actual collection method as Store pickup. Rates and shipping zones are configured in WooCommerce.</p><table class="widefat striped"><thead><tr><th scope="col">Method</th><th scope="col">WooCommerce ID</th><th scope="col">Classification</th></tr></thead><tbody>';
        foreach (self::methods() as $method) {
            $id = $method->id;
            echo '<tr><td>' . esc_html($method->get_method_title()) . '</td><td><code>' . esc_html($id) . '</code></td><td><select aria-label="' . esc_attr('Classification for ' . $method->get_method_title()) . '" name="shipping_types[' . esc_attr($id) . ']">';
            foreach (['' => 'Automatic / No override', 'door_delivery' => 'Door delivery', 'pickup' => 'Store pickup'] as $value => $label) {
                echo '<option value="' . esc_attr($value) . '" ' . selected($map[$id] ?? '', $value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">For installed WCFM delivery methods (Store Shipping, Marketplace Shipping by Country or Weight), select Door delivery if these methods provide home delivery. A classification cannot fix a package with no returned shipping rate.</p>';
        echo '<h2>Shipping diagnostics</h2><p><label><input type="checkbox" name="shipping_diagnostics" value="1" ' . checked((int) get_option('eldokan_checkout_diagnostics_until', 0) > time(), true, false) . '> Capture shipping metadata for Customer API quotes for the next two hours.</label></p><p>Save, click Refresh shipping and payment in the storefront, then reload this admin page. Match the Quote reference below. Only the latest five reports are retained for two hours; reports contain method IDs, instance IDs, costs and classifications. Customer details are not recorded. Uncheck and save to clear reports.</p>';
        $reports = get_transient('eldokan_checkout_shipping_reports');
        if (is_array($reports) && $reports) {
            echo '<label for="eldokan-shipping-report"><strong>Shipping report (copy for support)</strong></label><textarea id="eldokan-shipping-report" class="large-text code" rows="18" readonly>' . esc_textarea(wp_json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</textarea>';
        } else { echo '<p>No recent report. Enable capture and request a new checkout quote.</p>'; }
        submit_button('Save checkout settings');
        echo '</form><p><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')) . '">Shipping zones and rates</a> &middot; <a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')) . '">Payment methods</a></p></div>';
    }
}
// Older ZIPs may have been installed in a versioned directory. Avoid duplicate
// menus/save handlers while the old plugin is still active during replacement.
add_action('plugins_loaded', static function () {
    if (class_exists('ElDokan_Checkout_Setup', false) || class_exists('ElDokan_Checkout_Setup_English', false)) {
        add_action('admin_notices', static function () {
            if (!current_user_can('manage_woocommerce')) { return; }
            echo '<div class="notice notice-warning"><p>ElDokan Checkout Setup 0.1.2 is ready. Deactivate the older ElDokan Checkout Setup helper to use the updated settings page. Your saved settings are retained.</p></div>';
        });
        return;
    }
    ElDokan_Checkout_Setup_Diagnostics::init();
}, 20);
