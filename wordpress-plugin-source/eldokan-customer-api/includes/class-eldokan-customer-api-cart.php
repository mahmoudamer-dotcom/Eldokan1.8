<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Server-owned guest/customer Cart for Phase 2B.
 *
 * Guest ownership is an opaque HttpOnly cookie backed by a transient. Customer
 * ownership is private user meta. Neither storage key nor Woo cart keys are
 * part of the public contract.
 */
final class ElDokan_Customer_API_Cart {

    const USER_META_KEY = '_eldokan_customer_cart_v1';
    const GUEST_COOKIE = 'eldokan_guest_cart';
    const GUEST_TTL = 2592000; // 30 days.
    const MUTATION_LIMIT = 120;
    const GUEST_IP_MUTATION_LIMIT = 240;
    const MUTATION_WINDOW = 300;
    const LINE_LIMIT = 100;

    public static function get(WP_REST_Request $request) {
        $owner = self::owner(true);
        // A3 read may prune: serialize it with placement and Cart mutations.
        $locked = self::checkout_lock($owner);
        if (is_wp_error($locked)) { return $locked; }
        // End A3 read lock.
        return self::serialize($owner, $request);
    }

    /** A2 read boundary: never invokes the pruning Cart serializer. */
    public static function checkout_snapshot(WP_REST_Request $request, $mutation = false) {
        $origin = ElDokan_Customer_API_Auth::validate_request_origin();
        if (is_wp_error($origin)) {
            return $origin;
        }
        $owner = $mutation ? self::mutation_owner($request) : self::owner(true);
        if (is_wp_error($owner)) {
            return $owner;
        }
        if (!$owner) {
            return new WP_Error('cart_session_required', 'Restore the cart session first.', ['status' => 401]);
        }
        $observed = get_transient(self::checkout_price_key($owner));
        return [
            'entries' => self::entries($owner),
            'owner_type' => $owner['type'],
            'csrf_token' => self::csrf_token($owner),
            'observed_prices' => is_array($observed) ? $observed : [],
        ];
    }

    // A3 internal owner/consumption boundary. No identity is publicly serialized.
    public static function checkout_owner(WP_REST_Request $request) {
        $owner = self::mutation_owner($request);
        if (is_wp_error($owner)) { return $owner; }
        $session = $owner['type'] === 'customer' ? wp_get_session_token() : $owner['token'];
        return ['binding' => hash_hmac('sha256', self::checkout_cart_key($owner) . '|' . $session, wp_salt('auth')),
            'cart_key' => self::checkout_cart_key($owner), 'customer_id' => $owner['user_id'] ?? 0,
            'owner' => $owner];
    }
    private static function checkout_cart_key($owner) {
        $identity = $owner['type'] === 'customer' ? 'u:' . $owner['user_id'] : 'g:' . $owner['token'];
        return hash_hmac('sha256', 'cart|' . $identity, wp_salt('auth'));
    }
    private static function checkout_lock($owner) {
        if (!$owner) { return true; }
        if (!class_exists('ElDokan_Customer_API_Order_Store')) { return true; }
        return ElDokan_Customer_API_Order_Store::lock('cart|' . self::checkout_cart_key($owner));
    }
    public static function checkout_consume($owner) {
        // Committed receipts already hide bought cit_* IDs. Keep any fresh items.
        self::save($owner, self::entries($owner));
    }
    // End A3 internal owner/consumption boundary.

    private static function checkout_price_key($owner) {
        $identity = $owner['type'] === 'customer' ? 'u:' . $owner['user_id'] : 'g:' . $owner['token'];
        return 'eldokan_quote_prices_' . substr(hash_hmac('sha256', $identity, wp_salt('auth')), 0, 32);
    }

    /** Price observations are advisory only; never a money authority. */
    private static function observe_checkout_prices($owner, $items) {
        $prices = [];
        foreach ($items as $item) {
            $prices[$item['id']] = $item['unit_price'];
        }
        set_transient(self::checkout_price_key($owner), $prices, self::GUEST_TTL);
    }

    public static function add(WP_REST_Request $request) {
        $owner = self::mutation_owner($request);
        if (is_wp_error($owner)) {
            return $owner;
        }

        $product_id = ElDokan_Customer_API_Utils::parse_opaque_id(
            'prd',
            sanitize_text_field((string) $request->get_param('product_id'))
        );
        $variation_value = sanitize_text_field((string) $request->get_param('variation_id'));
        $variation_id = $variation_value === ''
            ? 0
            : ElDokan_Customer_API_Utils::parse_opaque_id('var', $variation_value);
        $quantity = self::positive_quantity($request->get_param('quantity'));

        if (!$product_id) {
            return new WP_Error('invalid_product_id', 'Invalid product ID.', ['status' => 400]);
        }
        if ($variation_value !== '' && !$variation_id) {
            return new WP_Error('invalid_variation_id', 'Invalid variation ID.', ['status' => 400]);
        }
        if (is_wp_error($quantity)) {
            return $quantity;
        }

        $validated = ElDokan_Customer_API_Products::cart_line(
            $product_id,
            $variation_id,
            $quantity,
            null,
            true
        );
        if (is_wp_error($validated)) {
            return $validated;
        }

        $entries = self::entries($owner);
        $selection_key = self::selection_key($product_id, $variation_id);
        $changed = true;
        foreach ($entries as $entry) {
            if (self::selection_key($entry['product_id'], $entry['variation_id']) === $selection_key) {
                // POST is retry-safe. Quantity changes use PATCH explicitly.
                $changed = false;
                break;
            }
        }

        if ($changed) {
            if (count($entries) >= self::LINE_LIMIT) {
                return new WP_Error(
                    'cart_line_limit_exceeded',
                    'A cart cannot contain more than 100 distinct items.',
                    ['status' => 422]
                );
            }
            $entries[] = [
                'item_id' => self::new_item_id(),
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
            ];
            self::save($owner, $entries);
        }

        return self::mutation_result($owner, $request, $changed);
    }

    public static function update(WP_REST_Request $request) {
        $owner = self::mutation_owner($request);
        if (is_wp_error($owner)) {
            return $owner;
        }
        $item_id = sanitize_text_field((string) $request->get_param('item_id'));
        if (!preg_match('/^cit_[a-f0-9]{32}$/', $item_id)) {
            return new WP_Error('invalid_cart_item_id', 'Invalid cart item ID.', ['status' => 400]);
        }
        $quantity = self::positive_quantity($request->get_param('quantity'));
        if (is_wp_error($quantity)) {
            return $quantity;
        }

        $entries = self::entries($owner);
        $found = false;
        foreach ($entries as &$entry) {
            if ($entry['item_id'] !== $item_id) {
                continue;
            }
            $found = true;
            $validated = ElDokan_Customer_API_Products::cart_line(
                $entry['product_id'],
                $entry['variation_id'],
                $quantity,
                null,
                true
            );
            if (is_wp_error($validated)) {
                return $validated;
            }
            $entry['quantity'] = $quantity;
            break;
        }
        unset($entry);

        if (!$found) {
            return new WP_Error('cart_item_not_found', 'Cart item not found.', ['status' => 404]);
        }
        self::save($owner, $entries);
        return self::mutation_result($owner, $request, true);
    }

    public static function remove(WP_REST_Request $request) {
        $owner = self::mutation_owner($request);
        if (is_wp_error($owner)) {
            return $owner;
        }
        $item_id = sanitize_text_field((string) $request->get_param('item_id'));
        if (!preg_match('/^cit_[a-f0-9]{32}$/', $item_id)) {
            return new WP_Error('invalid_cart_item_id', 'Invalid cart item ID.', ['status' => 400]);
        }

        $entries = self::entries($owner);
        $remaining = array_values(array_filter($entries, function ($entry) use ($item_id) {
            return $entry['item_id'] !== $item_id;
        }));
        $changed = count($remaining) !== count($entries);
        if ($changed) {
            self::save($owner, $remaining);
        }
        return self::mutation_result($owner, $request, $changed);
    }

    public static function merge_guest_into_customer($user_id) {
        $token = self::cookie_token(false);
        $user_id = absint($user_id);
        if (!$token || !$user_id) {
            return;
        }

        $guest = ['type' => 'guest', 'token' => $token];
        $customer = ['type' => 'customer', 'user_id' => $user_id];
        // A3 login merge shares placement locks, in deterministic order.
        $owners = [self::checkout_cart_key($guest) => $guest, self::checkout_cart_key($customer) => $customer];
        ksort($owners);
        foreach ($owners as $merge_owner) {
            if (is_wp_error(self::checkout_lock($merge_owner))) { return; }
        }
        // End A3 merge locks.
        $target = self::entries($customer);
        $index = [];
        foreach ($target as $position => $entry) {
            $index[self::selection_key($entry['product_id'], $entry['variation_id'])] = $position;
        }

        $valid_guest = [];
        foreach (self::entries($guest) as $entry) {
            $valid = ElDokan_Customer_API_Products::cart_line(
                $entry['product_id'],
                $entry['variation_id'],
                $entry['quantity'],
                null,
                true
            );
            if (is_wp_error($valid)) {
                continue;
            }
            $valid_guest[] = $entry;
        }

        $remaining_guest = [];

        // Consume duplicate selections first so line capacity cannot prevent
        // a safe quantity merge.
        foreach ($valid_guest as $entry) {
            $key = self::selection_key($entry['product_id'], $entry['variation_id']);
            if (!isset($index[$key])) {
                continue;
            }

            $position = $index[$key];
            $combined = self::merge_quantity(
                $entry,
                $target[$position]['quantity'] + $entry['quantity']
            );
            $validated = ElDokan_Customer_API_Products::cart_line(
                $entry['product_id'],
                $entry['variation_id'],
                $combined,
                null,
                true
            );
            if (!is_wp_error($validated)) {
                $target[$position]['quantity'] = $combined;
            } else {
                $remaining_guest[] = $entry;
            }
        }

        // Add unique selections only while capacity remains. Valid overflow
        // stays in the guest session for a later merge instead of being lost.
        foreach ($valid_guest as $entry) {
            $key = self::selection_key($entry['product_id'], $entry['variation_id']);
            if (isset($index[$key])) {
                continue;
            }
            if (count($target) >= self::LINE_LIMIT) {
                $remaining_guest[] = $entry;
                continue;
            }
            $target[] = $entry;
            $index[$key] = count($target) - 1;
        }

        self::save($customer, $target);
        if ($remaining_guest) {
            self::save($guest, $remaining_guest);
        } else {
            delete_transient(self::guest_storage_key($token));
            self::clear_guest_cookie();
        }
    }

    private static function mutation_result($owner, WP_REST_Request $request, $changed) {
        $cart = self::serialize($owner, $request);
        if (is_wp_error($cart)) {
            return $cart;
        }
        $cart['changed'] = (bool) $changed;
        return $cart;
    }

    private static function serialize($owner, WP_REST_Request $request) {
        $items = [];
        $kept = [];
        $valid = true;
        $language = ElDokan_Customer_API_Utils::requested_language($request);

        foreach (self::entries($owner) as $entry) {
            $line = ElDokan_Customer_API_Products::cart_line(
                $entry['product_id'],
                $entry['variation_id'],
                $entry['quantity'],
                $language,
                false
            );
            if (is_wp_error($line)) {
                // Only an invalid source product/variation is pruned. A missing
                // translation never reaches this branch because cart_line falls
                // back at projection time.
                continue;
            }
            $kept[] = $entry;
            $line['id'] = $entry['item_id'];
            $items[] = $line;
            $valid = $valid && $line['valid'];
        }

        if ($kept !== self::entries($owner)) {
            self::save($owner, $kept);
        }

        self::observe_checkout_prices($owner, $items);

        return [
            'items' => $items,
            'count' => count($items),
            'valid' => (bool) $valid,
            'owner_type' => $owner['type'],
            'csrf_token' => self::csrf_token($owner),
        ];
    }

    private static function mutation_owner(WP_REST_Request $request) {
        $origin = ElDokan_Customer_API_Auth::validate_request_origin();
        if (is_wp_error($origin)) {
            return $origin;
        }
        $owner = self::owner(false);
        if (!$owner) {
            return new WP_Error('cart_session_required', 'Restore the cart session before modifying it.', ['status' => 401]);
        }
        $token = (string) $request->get_header('X-ElDokan-CSRF');
        if ($owner['type'] === 'customer') {
            $user = ElDokan_Customer_API_Auth::require_customer($request, true);
            if (is_wp_error($user)) {
                return $user;
            }
        } elseif (!$token || !hash_equals(self::csrf_token($owner), $token)) {
            return new WP_Error('invalid_csrf_token', 'The cart security token is missing or invalid.', ['status' => 403]);
        }

        $limited = self::check_rate_limit($owner);
        if (is_wp_error($limited)) {
            return $limited;
        }
        // A3 serialize this owner through the end of the HTTP request.
        $locked = self::checkout_lock($owner);
        if (is_wp_error($locked)) { return $locked; }
        // End A3 mutation lock.
        return $owner;
    }

    private static function owner($create_guest) {
        $user = ElDokan_Customer_API_Auth::current_customer();
        if ($user) {
            return ['type' => 'customer', 'user_id' => $user->ID];
        }
        $token = self::cookie_token($create_guest);
        return $token ? ['type' => 'guest', 'token' => $token] : null;
    }

    private static function cookie_token($create) {
        $token = sanitize_text_field((string) ($_COOKIE[self::GUEST_COOKIE] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $token;
        }
        if (!$create || headers_sent()) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $same_site = (string) apply_filters('eldokan_customer_api_cookie_samesite', 'Lax');
        if (!in_array($same_site, ['Lax', 'Strict', 'None'], true) || ($same_site === 'None' && !is_ssl())) {
            $same_site = 'Lax';
        }
        setcookie(self::GUEST_COOKIE, $token, [
            'expires' => time() + self::GUEST_TTL,
            'path' => '/',
            'domain' => defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => $same_site,
        ]);
        $_COOKIE[self::GUEST_COOKIE] = $token;
        return $token;
    }

    private static function clear_guest_cookie() {
        if (!headers_sent()) {
            setcookie(self::GUEST_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[self::GUEST_COOKIE]);
    }

    private static function csrf_token($owner) {
        if ($owner['type'] === 'customer') {
            return ElDokan_Customer_API_Auth::csrf_token();
        }
        return hash_hmac('sha256', 'cart_csrf|' . $owner['token'], wp_salt('nonce'));
    }

    private static function entries($owner) {
        $value = $owner['type'] === 'customer'
            ? get_user_meta($owner['user_id'], self::USER_META_KEY, true)
            : get_transient(self::guest_storage_key($owner['token']));
        if (!is_array($value)) {
            return [];
        }
        // A3 durable receipts make consumption recoverable even with external caches.
        if (class_exists('ElDokan_Customer_API_Order_Store')) {
            $value = ElDokan_Customer_API_Order_Store::unconsumed(self::checkout_cart_key($owner), $value);
        }
        // End A3 durable receipts.
        $entries = [];
        foreach ($value as $entry) {
            if (!is_array($entry) || !preg_match('/^cit_[a-f0-9]{32}$/', (string) ($entry['item_id'] ?? ''))) {
                continue;
            }
            $product_id = absint($entry['product_id'] ?? 0);
            $quantity = absint($entry['quantity'] ?? 0);
            if (!$product_id || !$quantity) {
                continue;
            }
            $entries[] = [
                'item_id' => (string) $entry['item_id'],
                'product_id' => $product_id,
                'variation_id' => absint($entry['variation_id'] ?? 0),
                'quantity' => min(999, $quantity),
            ];
            if (count($entries) >= self::LINE_LIMIT) {
                break;
            }
        }
        return $entries;
    }

    private static function save($owner, $entries) {
        $entries = array_slice(array_values($entries), 0, self::LINE_LIMIT);
        if ($owner['type'] === 'customer') {
            update_user_meta($owner['user_id'], self::USER_META_KEY, $entries);
            return;
        }
        set_transient(self::guest_storage_key($owner['token']), $entries, self::GUEST_TTL);
    }

    private static function positive_quantity($value) {
        $value = (string) $value;
        if (!preg_match('/^[1-9][0-9]*$/', $value) || (int) $value > 999) {
            return new WP_Error('invalid_quantity', 'Quantity must be an integer between 1 and 999.', ['status' => 422]);
        }
        return (int) $value;
    }

    private static function selection_key($product_id, $variation_id) {
        return absint($product_id) . ':' . absint($variation_id);
    }

    private static function merge_quantity($entry, $combined) {
        $purchasable_id = !empty($entry['variation_id'])
            ? absint($entry['variation_id'])
            : absint($entry['product_id']);
        $product = $purchasable_id ? wc_get_product($purchasable_id) : false;
        if (!$product) {
            return 0;
        }

        $quantity = min(999, max(1, absint($combined)));
        if ($product->is_sold_individually()) {
            return 1;
        }

        $maximum = (int) $product->get_max_purchase_quantity();
        if ($maximum > 0) {
            $quantity = min($quantity, $maximum);
        }

        // WooCommerce remains authoritative for stock/backorder semantics.
        while ($quantity > 0 && !$product->has_enough_stock($quantity)) {
            $quantity--;
        }
        return $quantity;
    }

    private static function new_item_id() {
        return 'cit_' . str_replace('-', '', wp_generate_uuid4());
    }

    private static function guest_storage_key($token) {
        return 'eldokan_guest_cart_' . substr(hash_hmac('sha256', $token, wp_salt('auth')), 0, 32);
    }

    private static function check_rate_limit($owner) {
        $limits = [
            ['identity' => $owner['type'] === 'customer' ? 'u:' . $owner['user_id'] : 'g:' . $owner['token'], 'limit' => self::MUTATION_LIMIT],
        ];
        if ($owner['type'] === 'guest') {
            // Match Auth's conservative source-IP model; never trust forwarded headers.
            $limits[] = ['identity' => 'ip:' . self::client_ip(), 'limit' => self::GUEST_IP_MUTATION_LIMIT];
        }

        foreach ($limits as $limit) {
            if (absint(get_transient(self::rate_key($limit['identity']))) >= $limit['limit']) {
                return new WP_Error('too_many_attempts', 'Too many cart changes. Try again later.', ['status' => 429]);
            }
        }
        foreach ($limits as $limit) {
            $key = self::rate_key($limit['identity']);
            set_transient($key, absint(get_transient($key)) + 1, self::MUTATION_WINDOW);
        }
        return true;
    }

    private static function rate_key($identity) {
        return 'eldokan_cart_rate_' . substr(hash_hmac('sha256', (string) $identity, wp_salt('auth')), 0, 32);
    }

    private static function client_ip() {
        return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
}
