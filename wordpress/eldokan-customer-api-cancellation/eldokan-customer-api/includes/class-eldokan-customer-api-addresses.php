<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Addresses {
    const MAX_ADDRESSES = 20;
    const FIELDS = ['first_name', 'last_name', 'phone', 'email', 'company', 'country', 'state', 'city', 'street_address', 'address_extra', 'is_default'];

    private static function customer(WP_REST_Request $request, $mutation) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, $mutation);
        if (is_wp_error($user)) {
            return $user;
        }
        $origin_error = ElDokan_Customer_API_Auth::validate_request_origin();
        return is_wp_error($origin_error) ? $origin_error : $user;
    }

    public static function get(WP_REST_Request $request) {
        $user = self::customer($request, false);
        if (is_wp_error($user)) {
            return $user;
        }
        $book = ElDokan_Customer_API_Address_Store::read($user->ID);
        return is_wp_error($book) ? $book : self::listing($book['items']);
    }

    public static function create(WP_REST_Request $request) {
        $user = self::customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }
        $data = self::validate($request->get_json_params(), false);
        if (is_wp_error($data)) {
            return $data;
        }
        try {
            $id = 'adr_' . bin2hex(random_bytes(32));
        } catch (Throwable $error) {
            return new WP_Error('address_id_unavailable', 'The address could not be created.', ['status' => 503]);
        }
        return ElDokan_Customer_API_Address_Store::mutate($user->ID, static function ($items) use ($data, $id) {
            if (count($items) >= self::MAX_ADDRESSES) {
                return new WP_Error('address_limit_reached', 'The Address Book supports up to 20 addresses.', ['status' => 422]);
            }
            foreach ($items as $item) {
                if ($item['id'] === $id) {
                    return new WP_Error('address_id_unavailable', 'The address could not be created.', ['status' => 503]);
                }
            }
            $address = array_merge(['id' => $id], $data);
            // The first address always becomes default; subsequent addresses only on explicit true.
            $address['is_default'] = !$items || $address['is_default'];
            if ($address['is_default']) {
                $items = self::clear_default($items);
            }
            $items[] = $address;
            return ['items' => $items, 'response' => self::project($address)];
        });
    }

    public static function update(WP_REST_Request $request) {
        $user = self::customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }
        $id = self::address_id($request);
        if (is_wp_error($id)) {
            return $id;
        }
        $data = self::validate($request->get_json_params(), true);
        if (is_wp_error($data)) {
            return $data;
        }
        return ElDokan_Customer_API_Address_Store::mutate($user->ID, static function ($items) use ($data, $id) {
            foreach ($items as $key => $address) {
                if ($address['id'] !== $id) {
                    continue;
                }
                if (!empty($data['is_default'])) {
                    $items = self::clear_default($items);
                }
                $items[$key] = array_merge($items[$key], $data);
                return ['items' => $items, 'response' => self::project($items[$key])];
            }
            return self::not_found();
        });
    }

    public static function remove(WP_REST_Request $request) {
        $user = self::customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }
        $id = self::address_id($request);
        if (is_wp_error($id)) {
            return $id;
        }
        return ElDokan_Customer_API_Address_Store::mutate($user->ID, static function ($items) use ($id) {
            foreach ($items as $key => $address) {
                if ($address['id'] !== $id) {
                    continue;
                }
                unset($items[$key]);
                $items = self::ordered(array_values($items));
                if ($address['is_default'] && $items) {
                    $items = self::clear_default($items);
                    $items[0]['is_default'] = true;
                }
                return ['items' => $items, 'response' => ['deleted' => true, 'id' => $id, 'default_address_id' => self::default_id($items)]];
            }
            return self::not_found();
        });
    }

    private static function address_id(WP_REST_Request $request) {
        // URL parameters only: body/query parameters cannot shadow the target address.
        $id = $request->get_url_params()['address_id'] ?? null;
        return is_string($id) && preg_match('/^adr_[a-f0-9]{64}$/D', $id)
            ? $id : new WP_Error('invalid_address_id', 'Invalid address ID.', ['status' => 400]);
    }

    private static function not_found() {
        // Missing and foreign IDs have the same response, with no cross-customer lookup.
        return new WP_Error('address_not_found', 'Address not found.', ['status' => 404]);
    }

    private static function validate($body, $partial) {
        if (!is_array($body) || !$body || array_diff(array_keys($body), self::FIELDS)) {
            return new WP_Error('invalid_address_fields', 'Provide supported address fields in a JSON object.', ['status' => 422]);
        }
        $required = ['first_name', 'last_name', 'phone', 'email', 'country', 'state', 'city', 'street_address'];
        if (!$partial && array_diff($required, array_keys($body))) {
            return new WP_Error('missing_address_field', 'Required address fields are missing.', ['status' => 422]);
        }
        $result = [];
        $maximums = ['first_name' => 100, 'last_name' => 100, 'phone' => 32, 'email' => 254, 'company' => 150, 'country' => 2, 'state' => 32, 'city' => 150, 'street_address' => 500, 'address_extra' => 500];
        foreach ($body as $field => $value) {
            if ($field === 'is_default') {
                if (!is_bool($value)) {
                    return self::invalid_field();
                }
                $result[$field] = $value;
                continue;
            }
            if ($value === null && in_array($field, ['company', 'address_extra'], true)) {
                $result[$field] = null;
                continue;
            }
            if (!is_string($value) || strlen($value) > $maximums[$field]) {
                return self::invalid_field();
            }
            $value = trim(sanitize_text_field($value));
            if (in_array($field, $required, true) && $value === '') {
                return self::invalid_field();
            }
            if ($field === 'country' && $value !== 'EG') {
                return new WP_Error('unsupported_address_country', 'Only Egypt addresses are supported.', ['status' => 422]);
            }
            if ($field === 'email' && !is_email($value)) {
                return self::invalid_field();
            }
            // Accept local/international phone formatting without inventing a launch-only mandatory registration rule.
            if ($field === 'phone' && !preg_match('/^\+?[0-9 ()-]{6,32}$/D', $value)) {
                return self::invalid_field();
            }
            if ($field === 'phone' && strlen(preg_replace('/\D/', '', $value)) < 6) {
                return self::invalid_field();
            }
            if ($field === 'state') {
                $countries = function_exists('WC') && WC() ? WC()->countries : null;
                $states = $countries ? $countries->get_states('EG') : [];
                if (!is_array($states) || !$states) {
                    return new WP_Error('address_states_unavailable', 'Address governorates are temporarily unavailable.', ['status' => 503]);
                }
                if (!array_key_exists($value, $states)) {
                    return new WP_Error('invalid_address_state', 'Use a valid Egypt governorate code.', ['status' => 422]);
                }
            }
            $result[$field] = $value;
        }
        return $partial ? $result : array_merge(['company' => null, 'address_extra' => null, 'is_default' => false], $result);
    }

    private static function invalid_field() {
        return new WP_Error('invalid_address_field', 'An address field is invalid.', ['status' => 422]);
    }

    private static function clear_default($items) {
        foreach ($items as &$item) {
            $item['is_default'] = false;
        }
        unset($item);
        return $items;
    }

    private static function ordered($items) {
        usort($items, static function ($a, $b) { return strcmp($a['id'], $b['id']); });
        return $items;
    }

    private static function default_id($items) {
        foreach ($items as $item) {
            if ($item['is_default']) {
                return $item['id'];
            }
        }
        return null;
    }

    private static function project($address) {
        // Explicit public whitelist: storage ownership, revision, and internal metadata never leave the adapter.
        $result = ['id' => (string) $address['id']];
        foreach (self::FIELDS as $field) {
            $result[$field] = $address[$field];
        }
        return $result;
    }

    private static function listing($items) {
        return ['items' => array_map([__CLASS__, 'project'], self::ordered($items)), 'count' => count($items), 'default_address_id' => self::default_id($items)];
    }
}
