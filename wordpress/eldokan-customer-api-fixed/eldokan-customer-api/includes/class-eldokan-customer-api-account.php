<?php
if (!defined('ABSPATH')) {
    exit;
}

final class ElDokan_Customer_API_Account {

    const PUBLIC_ID_META = '_eldokan_customer_public_id';

    public static function get(WP_REST_Request $request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, false);
        return is_wp_error($user) ? $user : self::serialize($user);
    }

    public static function update(WP_REST_Request $request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, true);
        if (is_wp_error($user)) {
            return $user;
        }

        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $allowed = ['first_name', 'last_name', 'display_name', 'phone'];
        $unknown = array_diff(array_keys($body), $allowed);
        if ($unknown) {
            return new WP_Error('unsupported_account_field', 'The account update contains unsupported fields.', ['status' => 422]);
        }

        $has_phone = array_key_exists('phone', $body);
        $phone = $has_phone ? self::normalize_phone($body['phone']) : null;
        if (is_wp_error($phone)) {
            return $phone;
        }
        $updates = ['ID' => $user->ID];
        foreach (['first_name', 'last_name', 'display_name'] as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $value = sanitize_text_field((string) $body[$field]);
            $maximum = $field === 'display_name' ? 250 : 100;
            if (strlen($value) > $maximum) {
                return new WP_Error('invalid_account_field', 'An account field exceeds its maximum length.', ['status' => 422]);
            }
            $updates[$field] = $value;
        }

        if (count($updates) === 1 && !$has_phone) {
            return new WP_Error('empty_account_update', 'Provide at least one supported account field.', ['status' => 422]);
        }

        if (count($updates) > 1) {
            $result = wp_update_user($updates);
            if (is_wp_error($result)) {
                return new WP_Error('account_update_failed', 'The account could not be updated.', ['status' => 422]);
            }
        }
        if ($has_phone) {
            $saved = self::save_phone($user->ID, $phone);
            if (is_wp_error($saved)) { return $saved; }
        }

        $updated_user = get_user_by('id', $user->ID);
        if (!$updated_user) {
            return new WP_Error('account_update_failed', 'The account could not be updated.', ['status' => 422]);
        }

        return self::serialize($updated_user);
    }

    public static function serialize(WP_User $user) {
        $phone = self::read_phone($user->ID);
        if (is_wp_error($phone)) { return $phone; }
        return [
            'id' => self::public_id($user->ID),
            'first_name' => sanitize_text_field((string) $user->first_name),
            'last_name' => sanitize_text_field((string) $user->last_name),
            'display_name' => sanitize_text_field((string) $user->display_name),
            'email' => sanitize_email((string) $user->user_email),
            'phone' => $phone,
        ];
    }

    /** Formatting is retained; phone is neither verified nor an account identifier. */
    public static function normalize_phone($value) {
        if (!is_string($value) || strlen($value) > 32 || preg_match('/[^0-9+ ()-]/', $value)) {
            return new WP_Error('invalid_phone', 'Enter a valid phone string of at most 32 characters.', ['status' => 422]);
        }
        $value = trim($value, ' ');
        if ($value === '') { return ''; }
        if (!preg_match('/^\+?[0-9 ()-]+$/D', $value) || strlen(preg_replace('/[^0-9]/', '', $value)) < 6) {
            return new WP_Error('invalid_phone', 'Enter a valid phone string with at least six digits.', ['status' => 422]);
        }
        return sanitize_text_field($value);
    }

    public static function read_phone($user_id) {
        try {
            // Without Woo, retain the existing WordPress-only auth fallback using the SAME
            // canonical Woo user-meta key. Never maintain a parallel profile-phone value.
            $value = class_exists('WC_Customer')
                ? (new WC_Customer($user_id, false))->get_billing_phone('edit')
                : get_user_meta($user_id, 'billing_phone', true);
            if (!is_string($value)) { throw new RuntimeException('Phone storage unavailable.'); }
            return sanitize_text_field($value);
        } catch (Throwable $error) {
            return new WP_Error('phone_storage_unavailable', 'The account phone is temporarily unavailable.', ['status' => 503]);
        }
    }

    public static function save_phone($user_id, $phone) {
        try {
            if (class_exists('WC_Customer')) {
                $customer = new WC_Customer($user_id, false);
                $customer->set_billing_phone($phone);
                if ((int) $customer->save() !== (int) $user_id) { throw new RuntimeException('Phone storage unavailable.'); }
            } else {
                update_user_meta($user_id, 'billing_phone', $phone);
            }
            $stored = self::read_phone($user_id);
            if (is_wp_error($stored) || $stored !== $phone) { throw new RuntimeException('Phone storage unavailable.'); }
            return true;
        } catch (Throwable $error) {
            return new WP_Error('phone_storage_unavailable', 'The account phone is temporarily unavailable.', ['status' => 503]);
        }
    }

    private static function public_id($user_id) {
        $public_id = (string) get_user_meta($user_id, self::PUBLIC_ID_META, true);
        if (preg_match('/^cus_[a-f0-9]{32}$/', $public_id)) {
            return $public_id;
        }

        do {
            $public_id = 'cus_' . str_replace('-', '', wp_generate_uuid4());
            $existing = get_users([
                'meta_key' => self::PUBLIC_ID_META,
                'meta_value' => $public_id,
                'number' => 1,
                'fields' => 'ids',
            ]);
        } while (!empty($existing));

        update_user_meta($user_id, self::PUBLIC_ID_META, $public_id);
        return $public_id;
    }
}
