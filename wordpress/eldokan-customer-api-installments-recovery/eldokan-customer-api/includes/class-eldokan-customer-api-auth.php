<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Auth {

    const CSRF_ACTION = 'eldokan_customer_api';
    const LOGIN_LIMIT = 5;
    const LOGIN_IP_LIMIT = 30;
    const LOGIN_WINDOW = 900;
    const REGISTER_LIMIT = 5;
    const REGISTER_WINDOW = 3600;

    private static $pending_logged_in_cookie = null;

    public static function init() {
        add_action('set_logged_in_cookie', [__CLASS__, 'capture_logged_in_cookie'], 10, 6);
        add_filter('rest_pre_serve_request', [__CLASS__, 'secure_account_cors'], 20, 4);
    }

    public static function capture_logged_in_cookie($cookie, $expire, $expiration, $user_id, $scheme, $token) {
        self::$pending_logged_in_cookie = [
            'cookie' => (string) $cookie,
            'expire' => (int) $expire,
            'secure' => is_ssl(),
            'token' => (string) $token,
        ];
    }

    public static function register(WP_REST_Request $request) {
        $origin_error = self::validate_request_origin();
        if (is_wp_error($origin_error)) {
            return $origin_error;
        }

        $limited = self::check_rate_limit('register', self::client_ip(), self::REGISTER_LIMIT);
        if (is_wp_error($limited)) {
            return $limited;
        }

        $email = strtolower(sanitize_email((string) $request->get_param('email')));
        $password = (string) $request->get_param('password');
        $first_name = self::clean_name($request->get_param('first_name'));
        $last_name = self::clean_name($request->get_param('last_name'));
        $display_name = self::clean_display_name($request->get_param('display_name'));

        if (!$email || !is_email($email)) {
            return self::validation_error('invalid_email', 'Enter a valid email address.');
        }
        if (strlen($password) < 10 || strlen($password) > 128) {
            return self::validation_error('invalid_password', 'Password must be between 10 and 128 characters.');
        }
        if (is_wp_error($first_name)) {
            return $first_name;
        }
        if (is_wp_error($last_name)) {
            return $last_name;
        }
        if (is_wp_error($display_name)) {
            return $display_name;
        }
        $has_phone = $request->has_param('phone');
        $phone = $has_phone ? ElDokan_Customer_API_Account::normalize_phone($request->get_param('phone')) : '';
        if (is_wp_error($phone)) { return $phone; }
        self::record_rate_attempt('register', self::client_ip(), self::REGISTER_WINDOW);
        if (email_exists($email)) {
            return new WP_Error('account_exists', 'An account already exists for this email.', ['status' => 409]);
        }

        $username = self::customer_username($email);
        $args = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name ?: trim($first_name . ' ' . $last_name),
        ];

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, $username, $password, $args);
        } else {
            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $args['display_name'] ?: $email,
                'role' => 'customer',
            ]);
        }

        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', 'The account could not be created.', ['status' => 422]);
        }

        $user = get_user_by('id', absint($user_id));
        if (!$user) {
            return new WP_Error('registration_failed', 'The account could not be created.', ['status' => 422]);
        }

        if (!in_array('customer', (array) $user->roles, true)) {
            $user->set_role('customer');
        }

        if ($has_phone) {
            $saved = ElDokan_Customer_API_Account::save_phone($user->ID, $phone);
            if (is_wp_error($saved)) { return $saved; }
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, false, is_ssl());
        self::harden_logged_in_cookie();
        if (class_exists('ElDokan_Customer_API_Cart')) {
            ElDokan_Customer_API_Cart::merge_guest_into_customer($user->ID);
        }

        return self::session_payload($user);
    }

    public static function login(WP_REST_Request $request) {
        $origin_error = self::validate_request_origin();
        if (is_wp_error($origin_error)) {
            return $origin_error;
        }

        $email = strtolower(sanitize_email((string) $request->get_param('email')));
        $password = (string) $request->get_param('password');
        $client_ip = self::client_ip();
        $rate_identity = $client_ip . '|' . $email;
        $ip_limited = self::check_rate_limit('login_ip', $client_ip, self::LOGIN_IP_LIMIT);
        if (is_wp_error($ip_limited)) {
            return $ip_limited;
        }
        $limited = self::check_rate_limit('login', $rate_identity, self::LOGIN_LIMIT);
        if (is_wp_error($limited)) {
            return $limited;
        }

        if (!$email || !is_email($email) || $password === '') {
            self::record_rate_attempt('login_ip', $client_ip, self::LOGIN_WINDOW);
            self::record_rate_attempt('login', $rate_identity, self::LOGIN_WINDOW);
            return self::invalid_credentials();
        }

        $user = wp_signon([
            'user_login' => $email,
            'user_password' => $password,
            'remember' => false,
        ], is_ssl());

        if (is_wp_error($user) || !self::is_customer($user)) {
            if (!is_wp_error($user)) {
                self::destroy_pending_session($user->ID);
                wp_clear_auth_cookie();
                wp_set_current_user(0);
            }
            self::record_rate_attempt('login_ip', $client_ip, self::LOGIN_WINDOW);
            self::record_rate_attempt('login', $rate_identity, self::LOGIN_WINDOW);
            return self::invalid_credentials();
        }

        self::clear_rate_limit('login', $rate_identity);
        wp_set_current_user($user->ID);
        self::harden_logged_in_cookie();
        if (class_exists('ElDokan_Customer_API_Cart')) {
            ElDokan_Customer_API_Cart::merge_guest_into_customer($user->ID);
        }

        return self::session_payload($user);
    }

    public static function session(WP_REST_Request $request) {
        $user = self::require_customer($request, false);
        if (is_wp_error($user)) {
            return $user;
        }

        return self::session_payload($user);
    }

    public static function logout(WP_REST_Request $request) {
        $user = self::require_customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }

        wp_destroy_current_session();
        wp_clear_auth_cookie();
        wp_set_current_user(0);

        return ['logged_out' => true];
    }

    public static function require_customer(WP_REST_Request $request, $require_csrf = false) {
        $user_id = wp_validate_auth_cookie('', 'logged_in');
        if (!$user_id) {
            return self::unauthenticated();
        }

        $user = get_user_by('id', absint($user_id));
        if (!$user || !self::is_customer($user)) {
            return self::unauthenticated();
        }

        wp_set_current_user($user->ID);

        if ($require_csrf) {
            $token = (string) $request->get_header('X-ElDokan-CSRF');
            if (!$token || !wp_verify_nonce($token, self::CSRF_ACTION)) {
                return new WP_Error('invalid_csrf_token', 'The session security token is missing or invalid.', ['status' => 403]);
            }
        }

        return $user;
    }

    public static function current_customer() {
        $user_id = wp_validate_auth_cookie('', 'logged_in');
        $user = $user_id ? get_user_by('id', absint($user_id)) : false;
        if (!$user || !self::is_customer($user)) {
            return null;
        }
        wp_set_current_user($user->ID);
        return $user;
    }

    public static function csrf_token() {
        return wp_create_nonce(self::CSRF_ACTION);
    }

    public static function secure_account_cors($served, $result, $request, $server) {
        if (!($request instanceof WP_REST_Request)) {
            return $served;
        }
        if (!preg_match('#^/' . preg_quote(ElDokan_Customer_API::REST_NAMESPACE, '#') . '/(?:auth(?:/|$)|me(?:/|$)|wishlist(?:/|$)|cart(?:/|$)|checkout(?:/|$)|orders(?:/|$))#', $request->get_route())) {
            return $served;
        }

        $origin = get_http_origin();
        if (!$origin) {
            return $served;
        }

        foreach (['Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers'] as $header) {
            header_remove($header);
        }

        if (!self::origin_allowed($origin)) {
            return $served;
        }

        $server->send_header('Access-Control-Allow-Origin', esc_url_raw($origin));
        $server->send_header('Access-Control-Allow-Credentials', 'true');
        $server->send_header('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PATCH, DELETE');
        $server->send_header('Access-Control-Allow-Headers', 'Content-Type, X-ElDokan-CSRF, X-ElDokan-Order-Access');
        $server->send_header('Vary', 'Origin', false);

        return $served;
    }

    private static function session_payload(WP_User $user) {
        $customer = ElDokan_Customer_API_Account::serialize($user);
        if (is_wp_error($customer)) { return $customer; }
        return [
            'customer' => $customer,
            'csrf_token' => self::csrf_token(),
        ];
    }

    private static function harden_logged_in_cookie() {
        if (!self::$pending_logged_in_cookie || headers_sent()) {
            return;
        }

        $same_site = (string) apply_filters('eldokan_customer_api_cookie_samesite', 'Lax');
        if (!in_array($same_site, ['Lax', 'Strict', 'None'], true)) {
            $same_site = 'Lax';
        }
        if ($same_site === 'None' && !self::$pending_logged_in_cookie['secure']) {
            $same_site = 'Lax';
        }

        $paths = array_unique(array_filter([
            defined('COOKIEPATH') ? COOKIEPATH : '/',
            defined('SITECOOKIEPATH') ? SITECOOKIEPATH : '/',
        ]));

        foreach ($paths as $path) {
            setcookie(LOGGED_IN_COOKIE, self::$pending_logged_in_cookie['cookie'], [
                'expires' => self::$pending_logged_in_cookie['expire'],
                'path' => $path,
                'domain' => defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '',
                'secure' => self::$pending_logged_in_cookie['secure'],
                'httponly' => true,
                'samesite' => $same_site,
            ]);
        }

        // wp_create_nonce() reads the current request cookie to bind the nonce
        // to the newly-created WordPress session token.
        $_COOKIE[LOGGED_IN_COOKIE] = self::$pending_logged_in_cookie['cookie'];

        self::$pending_logged_in_cookie = null;
    }

    private static function destroy_pending_session($user_id) {
        if (!empty(self::$pending_logged_in_cookie['token']) && class_exists('WP_Session_Tokens')) {
            WP_Session_Tokens::get_instance(absint($user_id))->destroy(
                self::$pending_logged_in_cookie['token']
            );
        }
        self::$pending_logged_in_cookie = null;
    }

    private static function is_customer(WP_User $user) {
        $allowed = (array) apply_filters('eldokan_customer_api_customer_roles', ['customer']);
        return (bool) array_intersect($allowed, (array) $user->roles);
    }

    private static function origin_allowed($origin) {
        $allowed = [home_url(), site_url()];
        $allowed = (array) apply_filters('eldokan_customer_api_allowed_origins', $allowed);
        $normalized_origin = self::normalize_origin($origin);

        foreach ($allowed as $candidate) {
            if ($normalized_origin && hash_equals($normalized_origin, self::normalize_origin($candidate))) {
                return true;
            }
        }

        return false;
    }

    public static function validate_request_origin() {
        $origin = get_http_origin();
        if ($origin && !self::origin_allowed($origin)) {
            return new WP_Error(
                'origin_not_allowed',
                'This request origin is not allowed.',
                ['status' => 403]
            );
        }
        return true;
    }

    private static function normalize_origin($url) {
        $parts = wp_parse_url((string) $url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . absint($parts['port']);
        }
        return $origin;
    }

    private static function clean_name($value) {
        $value = sanitize_text_field((string) $value);
        if (strlen($value) > 100) {
            return self::validation_error('invalid_name', 'Name fields must not exceed 100 characters.');
        }
        return $value;
    }

    private static function clean_display_name($value) {
        $value = sanitize_text_field((string) $value);
        if (strlen($value) > 250) {
            return self::validation_error('invalid_display_name', 'Display name must not exceed 250 characters.');
        }
        return $value;
    }

    private static function customer_username($email) {
        $base = 'customer_' . substr(hash_hmac('sha256', $email, wp_salt('auth')), 0, 20);
        $candidate = $base;
        $suffix = 0;
        while (username_exists($candidate)) {
            $suffix++;
            $candidate = $base . '_' . $suffix;
        }
        return $candidate;
    }

    private static function invalid_credentials() {
        return new WP_Error('invalid_credentials', 'Email or password is incorrect.', ['status' => 401]);
    }

    private static function unauthenticated() {
        return new WP_Error('authentication_required', 'Customer authentication is required.', ['status' => 401]);
    }

    private static function validation_error($code, $message) {
        return new WP_Error($code, $message, ['status' => 422]);
    }

    private static function client_ip() {
        return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }

    private static function rate_key($scope, $identity) {
        return 'eldokan_customer_' . sanitize_key($scope) . '_' . substr(
            hash_hmac('sha256', (string) $identity, wp_salt('auth')),
            0,
            32
        );
    }

    private static function check_rate_limit($scope, $identity, $limit) {
        $attempts = absint(get_transient(self::rate_key($scope, $identity)));
        if ($attempts >= absint($limit)) {
            return new WP_Error('too_many_attempts', 'Too many attempts. Try again later.', ['status' => 429]);
        }
        return true;
    }

    private static function record_rate_attempt($scope, $identity, $window) {
        $key = self::rate_key($scope, $identity);
        set_transient($key, absint(get_transient($key)) + 1, absint($window));
    }

    private static function clear_rate_limit($scope, $identity) {
        delete_transient(self::rate_key($scope, $identity));
    }
}
