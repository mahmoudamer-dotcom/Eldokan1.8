<?php
if (!defined('ABSPATH')) { exit; }

/** Align native WC()->shipping() calls with the adapter's request-only engine.
 * WC_Cart uses WC_Shipping::instance(), not a dynamic WC()->shipping property.
 * Preserve and restore the singleton in every quote/order/payment scope.
 */
final class ElDokan_Customer_API_Shipping_Context extends WC_Shipping {
    public static function use_engine(WC_Shipping $engine) {
        parent::$_instance = $engine;
    }
}

/** Request-only session: no DB, cookies, initialization or shutdown hooks. */
final class ElDokan_Customer_API_Quote_Session extends WC_Session {
    public function __construct($customer_id) { $this->_customer_id = (string) $customer_id; }
    public function has_session() { return true; }
}

/** Retain native WC totals/fees/tax/shipping, omit persistent browser cart hooks. */
final class ElDokan_Customer_API_Quote_Cart extends WC_Cart {
    private $address_ready;
    // A3 request Cart only: native COD must not erase a separate persistent Woo basket.
    private $placement = false;
    // End A3 placement flag.
    public function __construct($address_ready, $placement = false) {
        $this->address_ready = (bool) $address_ready;
        // A3 placement Cart.
        $this->placement = (bool) $placement;
        // End A3 placement Cart.
        $this->session = new WC_Cart_Session($this); // deliberately no init().
        $this->fees_api = new WC_Cart_Fees();
    }
    public function get_cart() { return $this->get_cart_contents(); }
    public function show_shipping() { return $this->address_ready && parent::show_shipping(); }
    // A3 keep native empty-Cart hooks, without destroying an unrelated browser basket.
    public function empty_cart($clear_persistent_cart = true) {
        return parent::empty_cart($this->placement ? false : $clear_persistent_cart);
    }
    // End A3 native empty Cart.
}

final class ElDokan_Customer_API_Checkout_Woo {
    const MAX_SHIPPING_CHOICES = 100;

    public static function calculate($lines, $address, $selected_id, $customer_id, $cart_valid, $capture = null, $payment_selection = null) {
        $wc = WC();
        $original = [$wc->cart, $wc->customer, $wc->session, $wc->shipping, $wc->shipping()];
        try {
            $wc->session = new ElDokan_Customer_API_Quote_Session($customer_id ?: bin2hex(random_bytes(32)));
            $wc->customer = new WC_Customer($customer_id, false);
            // Always clear inherited address fields; a previous browser destination must not affect a quote.
            $fields = ['first_name' => 'first_name', 'last_name' => 'last_name', 'company' => 'company', 'country' => 'country', 'state' => 'state', 'city' => 'city', 'address_1' => 'street_address', 'address_2' => 'address_extra', 'postcode' => null];
            foreach (['billing', 'shipping'] as $kind) {
                foreach ($fields as $native => $public) {
                    $setter = 'set_' . $kind . '_' . $native;
                    $wc->customer->$setter($public !== null ? (string) ($address[$public] ?? '') : '');
                }
            }
            $wc->customer->set_billing_email((string) ($address['email'] ?? ''));
            $wc->customer->set_billing_phone((string) ($address['phone'] ?? ''));
            $wc->customer->set_calculated_shipping((bool) $address);
            $wc->shipping = new WC_Shipping();
            ElDokan_Customer_API_Shipping_Context::use_engine($wc->shipping);
            $wc->cart = new ElDokan_Customer_API_Quote_Cart((bool) $address, (bool) $capture);
            $cart = $wc->cart;
            $cart->set_cart_contents($lines);
            if ($payment_selection) {
                $gateway_id = 'cod';
                if ($payment_selection['method'] === 'paymob') {
                    $option_map = get_option('eldokan_checkout_paymob_options', []);
                    $gateway_id = $payment_selection['option'] === null ? get_option('eldokan_customer_api_paymob_gateway', 'paymob') : (is_array($option_map) ? ($option_map[$payment_selection['option']] ?? '') : '');
                }
                if (!is_string($gateway_id) || $gateway_id === '') { return new WP_Error('payment_option_unavailable', 'The selected payment option is unavailable.', ['status' => 422]); }
                $wc->session->set('chosen_payment_method', $gateway_id);
            }
            $requires_shipping = false;
            foreach ($lines as $line) { $requires_shipping = $requires_shipping || $line['data']->needs_shipping(); }
            // Invokes WC_Cart_Totals and native totals hooks; never adds/orders/pays/reserves.
            if ($lines) { $cart->calculate_totals(); }
            $issues = []; $choices = []; $selected = null;
            $shipping_ready = !$requires_shipping;
            if ($requires_shipping && $address && $cart_valid) {
                $countries = $wc->countries->get_shipping_countries();
                if (!wc_shipping_enabled() || !array_key_exists('EG', $countries)) {
                    $issues[] = self::issue('shipping_unavailable', 'Shipping is not available to this destination.');
                } else {
                    $packages = $wc->shipping->get_packages();
                    $choices = self::shipping_choices($packages, $address, $lines);
                    if (is_wp_error($choices)) { return $choices; }
                    if (!$choices) { $issues[] = self::issue('shipping_unavailable', 'No supported shipping method covers the complete cart.'); }
                    elseif ($selected_id) {
                        foreach ($choices as $choice) { if (hash_equals($choice['public']['id'], $selected_id)) { $selected = $choice; break; } }
                        if (!$selected) { $issues[] = self::issue('shipping_method_invalid', 'The selected shipping method is not currently available.'); }
                    } elseif (count($choices) === 1) { $selected = $choices[0]; }
                    else { $issues[] = self::issue('shipping_method_required', 'Choose an available shipping method.'); }
                    if ($selected) {
                        $wc->session->set('chosen_shipping_methods', $selected['native']);
                        $shipping_ready = true;
                        $cart->calculate_totals();
                        // Validate selected rate still exists after totals hooks/rate recalculation.
                        foreach ($wc->shipping->get_packages() as $key => $package) {
                            if (!isset($selected['native'][$key], $package['rates'][$selected['native'][$key]])) {
                                $shipping_ready = false;
                                $issues[] = self::issue('shipping_method_invalid', 'Shipping availability changed. Request a new quote.');
                            }
                        }
                        $fresh = self::shipping_choices($wc->shipping->get_packages(), $address, $lines);
                        if (is_wp_error($fresh)) { return $fresh; }
                        $still_available = false;
                        foreach ($fresh as $choice) {
                            if (hash_equals($choice['public']['id'], $selected['public']['id'])) { $still_available = true; $selected = $choice; break; }
                        }
                        $choices = $fresh;
                        if (!$still_available) {
                            $shipping_ready = false;
                            $issues[] = self::issue('shipping_method_invalid', 'Shipping rates changed. Request a new quote.');
                        }
                    }
                }
            } elseif ($requires_shipping) {
                $issues[] = self::issue('shipping_address_required', 'Shipping is calculated after a valid address is supplied.');
            } elseif ($selected_id) {
                $issues[] = self::issue('shipping_method_invalid', 'This cart does not require shipping.');
            }
            $complete = (bool) $address && $cart_valid && $shipping_ready;
            $payments = $complete ? self::payments($wc->payment_gateways()->get_available_payment_gateways()) : [];
            $paymob_options = $complete ? ElDokan_Customer_API_Paymob::checkout_options() : [];
            if ($complete && $payment_selection && (!in_array($payment_selection['method'], array_column($payments, 'id'), true) || ($payment_selection['option'] !== null && !in_array($payment_selection['option'], array_column($paymob_options, 'id'), true)))) {
                $issues[] = self::issue('payment_option_unavailable', 'The selected payment option is unavailable.');
                $complete = false;
            }
            if ($complete && $cart->needs_payment() && !$payments) {
                $issues[] = self::issue('payment_method_unavailable', 'No supported payment method is currently available.');
            }
            // Gateways may add Woo notices. Use a generic safe issue, never raw plugin text/configuration.
            if (function_exists('wc_notice_count') && wc_notice_count('error') > 0) {
                $issues[] = self::issue('checkout_unavailable', 'The store requires a checkout correction.');
                $complete = false;
            }
            $totals = [
                'subtotal' => $cart_valid ? ElDokan_Customer_API_Utils::money($cart->get_subtotal()) : null,
                'shipping' => $cart_valid && $shipping_ready ? ElDokan_Customer_API_Utils::money($cart->get_shipping_total()) : null,
                'tax' => !wc_tax_enabled() ? ElDokan_Customer_API_Utils::money(0) : ($complete ? ElDokan_Customer_API_Utils::money($cart->get_total_tax()) : null),
                'fees' => $complete ? ElDokan_Customer_API_Utils::money($cart->get_fee_total()) : null,
                'discount' => $complete ? ElDokan_Customer_API_Utils::money($cart->get_discount_total()) : null,
                'total' => $complete ? ElDokan_Customer_API_Utils::money($cart->get_total('edit')) : null,
                'taxes_enabled' => (bool) wc_tax_enabled(),
                'calculable' => $complete,
            ];
            $line_money = [];
            foreach ($cart->get_cart_contents() as $id => $line) {
                $line_money[$id] = [
                    'unit_price' => ElDokan_Customer_API_Utils::money($line['data']->get_price()),
                    'line_subtotal' => isset($line['line_subtotal']) ? ElDokan_Customer_API_Utils::money($line['line_subtotal']) : null,
                ];
            }
            // A3 internal capture is never included in the public quote.
            if ($capture) { $capture([$wc->cart, $wc->customer, $wc->session, $wc->shipping]); }
            return [
                '_line_money' => $line_money,
                'shipping_required' => $requires_shipping,
                'shipping_calculable' => (bool) $address && $cart_valid && $shipping_ready,
                'shipping_methods' => array_column($choices, 'public'),
                'selected_shipping_method' => $shipping_ready && $selected ? $selected['public'] : null,
                'payment_methods' => $payments,
                'paymob_options' => $paymob_options,
                'selected_payment_method' => $payment_selection['method'] ?? null,
                'selected_paymob_option' => $payment_selection['option'] ?? null,
                'payment_availability_calculable' => $complete,
                'payment_required' => $complete ? (bool) $cart->needs_payment() : null,
                'totals' => $totals,
                'issues' => $issues,
            ];
        } catch (Throwable $error) {
            // Never include exception text; providers sometimes embed merchant configuration there.
            return new WP_Error('checkout_calculation_unavailable', 'Checkout calculation is temporarily unavailable.', ['status' => 503]);
        } finally {
            ElDokan_Customer_API_Shipping_Context::use_engine($original[4]);
            [$wc->cart, $wc->customer, $wc->session, $wc->shipping] = $original;
        }
    }

    private static function issue($code, $message) { return ElDokan_Customer_API_Checkout::issue($code, $message); }

    private static function shipping_choices($packages, $address, $lines) {
        if (!$packages) { return []; }
        $sets = [['native' => [], 'cost' => 0, 'type' => null]];
        foreach ($packages as $key => $package) {
            $next = [];
            foreach (($package['rates'] ?? []) as $rate_id => $rate) {
                $method = $rate->get_method_id();
                $default = $method === 'local_pickup' ? 'pickup' : (in_array($method, ['flat_rate', 'free_shipping'], true) ? 'door_delivery' : null);
                // Deployment may explicitly classify an installed Woo method; never guess by display name.
                $type = apply_filters('eldokan_customer_api_checkout_shipping_type', $default, $method);
                if (!in_array($type, ['pickup', 'door_delivery'], true)) { continue; }
                foreach ($sets as $set) {
                    if ($set['type'] !== null && $set['type'] !== $type) { continue; }
                    $set['type'] = $type; $set['native'][$key] = $rate_id;
                    $set['cost'] += (float) $rate->get_cost();
                    $next[] = $set;
                    if (count($next) > self::MAX_SHIPPING_CHOICES) {
                        return new WP_Error('shipping_choices_unavailable', 'Shipping configuration requires fewer checkout choices.', ['status' => 503]);
                    }
                }
            }
            $sets = $next;
            if (!$sets) { break; }
        }
        $fingerprint = [];
        foreach ($lines as $id => $line) { $fingerprint[$id] = [$line['product_id'], $line['variation_id'], $line['quantity'], $line['variation']]; }
        foreach ($sets as &$set) {
            $set['public'] = [
                'id' => 'shp_' . hash_hmac('sha256', wp_json_encode([$address, $fingerprint, $set['native'], $set['cost']]), wp_salt('auth')),
                'name' => sanitize_text_field((string) apply_filters('eldokan_customer_api_checkout_shipping_name', $set['type'] === 'pickup' ? 'Pickup From Branches' : 'Door Delivery', $set['type'], $set['native'])),
                // Generic public descriptions prevent gateway/method settings or internal identifiers leaking.
                'description' => $set['type'] === 'pickup' ? 'Collect your items from the available branches.' : 'Delivery to your checkout address.',
                'amount' => ElDokan_Customer_API_Utils::money($set['cost']),
                'available' => true,
                'type' => $set['type'],
            ];
        }
        unset($set);
        return $sets;
    }

    private static function payments($available) {
        // Explicit native gateway mapping. Override with the actual installed Paymob gateway ID(s) on staging.
        $map = apply_filters('eldokan_customer_api_checkout_gateway_map', ['cod' => 'cod', 'paymob' => 'paymob', 'paymob-pixel' => 'paymob']);
        $result = [];
        foreach ($available as $native_id => $gateway) {
            // A4 exposes only the audited, configured native hosted gateway. No inline Pixel offer.
            if (class_exists('ElDokan_Customer_API_Paymob') && ($native_id === 'paymob' || $native_id === 'paymob-pixel' || (is_array($map) && ($map[$native_id] ?? null) === 'paymob') || is_a($gateway, 'Paymob_Payment'))) {
                $hosted = ElDokan_Customer_API_Paymob::gateway();
                $allowed = !is_wp_error($hosted) && $native_id === $hosted->id;
                if (!$allowed) {
                    foreach (ElDokan_Customer_API_Paymob::checkout_options() as $option) {
                        $option_gateway = ElDokan_Customer_API_Paymob::checkout_gateway($option['id']);
                        if (!is_wp_error($option_gateway) && $native_id === $option_gateway->id) { $allowed = true; break; }
                    }
                }
                if (!$allowed) { continue; }
            }
            $concept = is_array($map) ? ($map[$native_id] ?? null) : null;
            // Native Paymob also generates gateway IDs. Class identity handles that family without ID guessing.
            if ($concept === null && is_a($gateway, 'Paymob_Payment') && !is_a($gateway, 'Paymob_Main_Gateway') && !is_a($gateway, 'Paymob_Subscription_Gateway')) { $concept = 'paymob'; }
            $redirect = $concept === 'paymob' && $native_id !== 'paymob-pixel';
            if (!in_array($concept, ['cod', 'paymob'], true) || isset($result[$concept])) { continue; }
            $result[$concept] = [
                'id' => $concept,
                'name' => $concept === 'cod' ? 'Cash on Delivery' : 'Paymob',
                'description' => $concept === 'cod' ? 'Pay when you receive your purchase.' : ($redirect ? 'Pay through the secure hosted payment page.' : 'Pay through the secure Paymob checkout.'),
                'available' => true,
                'requires_redirect' => $redirect,
            ];
        }
        return array_values($result);
    }
}
