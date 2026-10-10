<?php
if (!defined('ABSPATH')) { exit; }

/** A2 checkout orchestrator. All native IDs stay inside the adapter. */
final class ElDokan_Customer_API_Checkout {
    const ADDRESS_FIELDS = ['first_name', 'last_name', 'phone', 'email', 'state', 'city', 'street_address', 'company', 'address_extra', 'country'];

    public static function get(WP_REST_Request $request) {
        return self::run($request, false);
    }

    public static function quote(WP_REST_Request $request) {
        return self::run($request, true);
    }

    // A3 internal preparation reuses every A2 validation/calculation boundary.
    public static function prepare(WP_REST_Request $request, &$context) {
        $capture = static function ($native) use (&$context) { $context = $native; };
        return self::run($request, true, $capture);
    }

    private static function run(WP_REST_Request $request, $mutation, $capture = null) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) { return $required; }
        $states = WC()->countries->get_states('EG');
        if (!is_array($states) || !$states) { return new WP_Error('address_states_unavailable', 'Address governorates are temporarily unavailable.', ['status' => 503]); }
        $snapshot = ElDokan_Customer_API_Cart::checkout_snapshot($request, $mutation);
        if (is_wp_error($snapshot)) { return $snapshot; }
        $user = ElDokan_Customer_API_Auth::current_customer();
        $saved = ['items' => [], 'count' => 0, 'default_address_id' => null];
        if ($user) {
            $saved = ElDokan_Customer_API_Addresses::get($request);
            if (is_wp_error($saved)) { return $saved; }
        }
        $address = null;
        $selected = null;
        $payment_selection = null;
        $issues = [];
        if ($mutation) {
            $body = $request->get_json_params();
            if (!is_array($body) || !$body || array_diff(array_keys($body), ['address_id', 'address', 'shipping_method_id', 'payment_method', 'paymob_option_id'])) {
                return new WP_Error('invalid_checkout_fields', 'Provide an address and optional shipping method in a JSON object.', ['status' => 422]);
            }
            if (array_key_exists('payment_method', $body)) {
                if (!in_array($body['payment_method'], ['cod', 'paymob'], true)) { return new WP_Error('payment_method_invalid', 'Choose an eligible payment method.', ['status' => 422]); }
                $payment_selection = ['method' => $body['payment_method'], 'option' => $body['paymob_option_id'] ?? null];
            }
            if (array_key_exists('paymob_option_id', $body) && (!$payment_selection || $payment_selection['method'] !== 'paymob' || !in_array($body['paymob_option_id'], ['card', 'bank_installments'], true))) { return new WP_Error('payment_option_invalid', 'Choose an eligible Paymob option.', ['status' => 422]); }
            if (array_key_exists('address_id', $body) && array_key_exists('address', $body)) {
                return new WP_Error('invalid_address', 'Choose one address mode.', ['status' => 422]);
            }
            if (array_key_exists('address_id', $body)) {
                if (!$user) { return new WP_Error('authentication_required', 'Sign in to use a saved address.', ['status' => 401]); }
                if (!is_string($body['address_id']) || !preg_match('/^adr_[a-f0-9]{64}$/D', $body['address_id'])) {
                    return new WP_Error('invalid_address_id', 'Invalid address ID.', ['status' => 400]);
                }
                // Only search the authenticated book, never a global ID index.
                foreach ($saved['items'] as $item) {
                    if ($item['id'] === $body['address_id']) { $address = $item; break; }
                }
                if (!$address) { return new WP_Error('address_not_found', 'Address not found.', ['status' => 404]); }
                $address = self::validate_address(array_intersect_key($address, array_flip(self::ADDRESS_FIELDS)), $address['id']);
            } elseif (array_key_exists('address', $body)) {
                $address = self::validate_address($body['address']);
            }
            if (is_wp_error($address)) { return $address; }
            if (array_key_exists('shipping_method_id', $body)) {
                if (!is_string($body['shipping_method_id']) || !preg_match('/^shp_[a-f0-9]{64}$/D', $body['shipping_method_id'])) {
                    return new WP_Error('shipping_method_invalid', 'Invalid shipping method.', ['status' => 422]);
                }
                $selected = $body['shipping_method_id'];
            }
        } else {
            foreach ($saved['items'] as $item) {
                if ($item['is_default']) {
                    $address = self::validate_address(array_intersect_key($item, array_flip(self::ADDRESS_FIELDS)), $item['id']);
                    if (is_wp_error($address)) {
                        $issues[] = self::issue(self::address_issue($address), 'The saved default address must be corrected.');
                        $address = null;
                    }
                    break;
                }
            }
        }
        if (!$address) { $issues[] = self::issue('address_required', 'Provide a complete Egypt checkout address.'); }
        $lines = self::validate_cart($snapshot, $request);
        if (is_wp_error($lines)) { return $lines; }
        $issues = array_merge($issues, $lines['issues']);
        $cart = ['items' => $lines['items'], 'count' => count($lines['items']), 'valid' => $lines['valid'], 'owner_type' => $snapshot['owner_type'], 'csrf_token' => $snapshot['csrf_token']];
        if (!$cart['count']) { $issues[] = self::issue('empty_cart', 'Add an item before checkout.'); }
        require_once ELDOKAN_CUSTOMER_API_DIR . 'includes/class-eldokan-customer-api-checkout-woo.php';
        $native = ElDokan_Customer_API_Checkout_Woo::calculate($lines['native'], $address, $selected, $user ? $user->ID : 0, $lines['valid'], $capture, $payment_selection);
        if (is_wp_error($native)) { return $native; }
        // Refresh the public per-line prices after Woo pricing hooks, using only native calculation results.
        foreach ($cart['items'] as &$item) {
            if (isset($native['_line_money'][$item['id']])) {
                $item = array_merge($item, $native['_line_money'][$item['id']]);
                $observed = $snapshot['observed_prices'][$item['id']] ?? null;
                if ($observed && self::price_differs($observed, $item['unit_price']) && !in_array('price_changed', array_column($item['issues'], 'code'), true)) {
                    $warning = self::issue('price_changed', 'The current price differs from the last cart view. Review this quote.', $item['id'], false);
                    $item['issues'][] = $warning; $issues[] = $warning;
                }
            }
        }
        unset($item, $native['_line_money']);
        $issues = array_merge($issues, $native['issues']);
        unset($native['issues']);
        $blocked = array_filter($issues, static function ($issue) { return $issue['blocking']; });
        return array_merge([
            'cart' => $cart,
            'ready' => $cart['count'] > 0 && !$blocked,
            'issues' => $issues,
            'address' => $address,
            'address_requirements' => [
                'country' => 'EG',
                'required_fields' => ['first_name', 'last_name', 'phone', 'email', 'state', 'city', 'street_address'],
                'state_codes' => array_keys(WC()->countries->get_states('EG')),
                'required' => true,
            ],
            'saved_addresses' => $saved,
            'stock_reserved' => false,
        ], $native);
    }

    private static function price_differs($observed, $current) {
        foreach (['amount', 'currency', 'decimals'] as $key) {
            if (($observed[$key] ?? null) !== ($current[$key] ?? null)) { return true; }
        }
        return false;
    }

    private static function address_issue($error) {
        return $error->get_error_code() === 'invalid_state' ? 'invalid_state' : 'invalid_address';
    }

    private static function validate_address($body, $id = null) {
        if (!is_array($body) || !$body || array_diff(array_keys($body), self::ADDRESS_FIELDS)) {
            return new WP_Error('invalid_address', 'Provide supported checkout address fields.', ['status' => 422]);
        }
        $required = ['first_name', 'last_name', 'phone', 'email', 'state', 'city', 'street_address'];
        if (array_diff($required, array_keys($body))) { return new WP_Error('invalid_address', 'Required address fields are missing.', ['status' => 422]); }
        $max = ['first_name' => 100, 'last_name' => 100, 'phone' => 32, 'email' => 254, 'state' => 32, 'city' => 150, 'street_address' => 500, 'company' => 150, 'address_extra' => 500, 'country' => 2];
        $result = ['company' => null, 'address_extra' => null, 'country' => 'EG'];
        if ($id !== null) { $result['id'] = $id; }
        foreach ($body as $field => $value) {
            if ($value === null && in_array($field, ['company', 'address_extra'], true)) { continue; }
            if (!is_string($value) || strlen($value) > $max[$field]) { return new WP_Error('invalid_address', 'An address field is invalid.', ['status' => 422]); }
            $value = trim(sanitize_text_field($value));
            if (in_array($field, $required, true) && $value === '') { return new WP_Error('invalid_address', 'An address field is empty.', ['status' => 422]); }
            if ($field === 'country' && $value !== 'EG') { return new WP_Error('invalid_address', 'Only Egypt addresses are supported.', ['status' => 422]); }
            if (($field === 'email' && !is_email($value)) || ($field === 'phone' && (!preg_match('/^\+?[0-9 ()-]{6,32}$/D', $value) || strlen(preg_replace('/\D/', '', $value)) < 6))) {
                return new WP_Error('invalid_address', 'Email or phone is invalid.', ['status' => 422]);
            }
            $result[$field] = $value;
        }
        $states = WC()->countries->get_states('EG');
        if (!is_array($states) || !$states) { return new WP_Error('address_states_unavailable', 'Address governorates are temporarily unavailable.', ['status' => 503]); }
        if (!array_key_exists($result['state'], $states)) { return new WP_Error('invalid_state', 'Use a valid Egypt governorate code.', ['status' => 422]); }
        return $result;
    }

    public static function issue($code, $message, $item_id = null, $blocking = true) {
        $issue = ['code' => $code, 'message' => $message, 'blocking' => (bool) $blocking];
        if ($item_id !== null) { $issue['item_id'] = $item_id; }
        return $issue;
    }

    private static function validate_cart($snapshot, $request) {
        $items = []; $native = []; $issues = []; $stock = []; $valid = true;
        foreach ($snapshot['entries'] as $entry) {
            $id = $entry['item_id'];
            $line = ElDokan_Customer_API_Products::cart_line($entry['product_id'], $entry['variation_id'], $entry['quantity'], ElDokan_Customer_API_Utils::requested_language($request), false, false);
            $line_issues = [];
            if (is_wp_error($line)) {
                $code = in_array($line->get_error_code(), ['invalid_variation', 'variation_required'], true) ? 'invalid_variation' : 'product_unavailable';
                $line_issues[] = self::issue($code, 'This cart selection is no longer available.', $id);
                // Preserve the public cart line identity and quantity, never deleted source rows.
                $line = ['quantity' => $entry['quantity'], 'name' => null, 'unit_price' => null, 'line_subtotal' => null, 'selected_attributes' => []];
            } else {
                foreach ($line['issues'] as $problem) {
                    $mapping = ['not_purchasable' => 'product_unavailable', 'out_of_stock' => 'insufficient_stock'];
                    $line_issues[] = self::issue($mapping[$problem['code']] ?? $problem['code'], $problem['message'], $id);
                }
                $parent = wc_get_product($entry['product_id']);
                $product = $entry['variation_id'] ? wc_get_product($entry['variation_id']) : $parent;
                if ($entry['quantity'] < (int) $product->get_min_purchase_quantity()) { $line_issues[] = self::issue('purchase_quantity_limit', 'The requested quantity is below the current purchase minimum.', $id); }
                if ($product->is_sold_individually() && $entry['quantity'] > 1) { $line_issues[] = self::issue('sold_individually', 'Only one unit of this item can be purchased.', $id); }
                $seller = $parent->get_meta('_eldokan_seller_user_id', true, 'edit');
                $seller_user = is_scalar($seller) && preg_match('/^[1-9][0-9]*$/D', (string) $seller) ? get_user_by('id', (int) $seller) : false;
                if (!is_scalar($seller) || !preg_match('/^[1-9][0-9]*$/D', (string) $seller) || !$seller_user || !array_intersect((array) $seller_user->roles, ['eldokan_owner', 'wcfm_vendor', 'seller', 'vendor'])) {
                    $line_issues[] = self::issue('seller_missing', 'Seller ownership is unavailable for this item.', $id);
                }
                // Do not leak the legacy Seller projection (which uses marketplace fallbacks).
                unset($line['seller']);
                $attributes = [];
                if ($entry['variation_id']) {
                    $attributes = $product->get_variation_attributes();
                    $options = $parent->get_variation_attributes();
                    foreach ($options as $key => $values) {
                        $value = $attributes['attribute_' . $key] ?? '';
                        // A1 stores variation IDs, not arbitrary selections. Wildcards need a future explicit selection contract.
                        if ($value === '' || !in_array($value, $values, true)) {
                            $line_issues[] = self::issue('invalid_variation', 'Select a variation with valid explicit attributes.', $id);
                            break;
                        }
                    }
                    foreach ($attributes as $key => $value) {
                        if (!array_key_exists(preg_replace('/^attribute_/', '', $key), $options)) {
                            $line_issues[] = self::issue('invalid_variation', 'The selected attributes are no longer valid.', $id);
                            break;
                        }
                    }
                }
                if (isset($snapshot['observed_prices'][$id]) && self::price_differs($snapshot['observed_prices'][$id], $line['unit_price'])) {
                    $line_issues[] = self::issue('price_changed', 'The current price differs from the last cart view. Review this quote.', $id, false);
                }
                $stock_id = $product->get_stock_managed_by_id();
                if (!isset($stock[$stock_id])) { $stock[$stock_id] = ['quantity' => 0, 'items' => []]; }
                $stock[$stock_id]['quantity'] += $entry['quantity'];
                $stock[$stock_id]['items'][] = $id;
                $native[$id] = ['key' => $id, 'product_id' => $entry['product_id'], 'variation_id' => $entry['variation_id'], 'quantity' => $entry['quantity'], 'variation' => $attributes, 'data' => clone $product, 'data_hash' => wc_get_cart_item_data_hash($product)];
            }
            // Existing public catalog IDs are preserved; sensitive identities never use native integers.
            $line['id'] = $id;
            $line['product_id'] = ElDokan_Customer_API_Utils::opaque_id('prd', $entry['product_id']);
            $line['variation_id'] = $entry['variation_id'] ? ElDokan_Customer_API_Utils::opaque_id('var', $entry['variation_id']) : null;
            $line['issues'] = $line_issues;
            $line['valid'] = !array_filter($line_issues, static function ($i) { return $i['blocking']; });
            $valid = $valid && $line['valid'];
            $items[] = $line;
            $issues = array_merge($issues, $line_issues);
        }
        // Different variations can share one parent's stock. Validate combined demand.
        foreach ($stock as $stock_id => $demand) {
            $product = wc_get_product($stock_id);
            if ($product && !$product->has_enough_stock($demand['quantity'])) {
                foreach ($items as &$item) {
                    if (in_array($item['id'], $demand['items'], true)) {
                        $problem = self::issue('insufficient_stock', 'Combined cart quantity exceeds available stock.', $item['id']);
                        $item['issues'][] = $problem; $item['valid'] = false; $issues[] = $problem;
                    }
                }
                unset($item); $valid = false;
            }
        }
        return ['items' => $items, 'native' => $native, 'issues' => $issues, 'valid' => $valid];
    }
}
