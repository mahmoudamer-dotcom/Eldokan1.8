<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Customer edits of delivery details before fulfillment: the address (inside the
 * same governorate, so shipping and totals stay valid), phone and order notes.
 * Lines, quantities, payment and totals are never changed here.
 */
final class ElDokan_Customer_API_Order_Edit {
    const META = '_eldokan_customer_address_update';

    /** Same window as online cancellation: before any seller starts fulfilling. */
    public static function policy($order) {
        $cancellation = ElDokan_Customer_API_Order_Cancellation::policy($order);
        return ['available' => (bool) $cancellation['available'], 'same_governorate_only' => true];
    }

    /** The current delivery address: the customer's latest edit, else the checkout snapshot. */
    public static function address($order) {
        $updated = $order->get_meta(self::META, true);
        if (is_array($updated) && isset($updated['address']) && is_array($updated['address'])) { return $updated['address']; }
        return $order->get_meta('_eldokan_customer_address_snapshot', true);
    }

    public static function update($request) {
        try {
            $resolved = ElDokan_Customer_API_Order_Read::authorize($request, true);
            if (is_wp_error($resolved)) { return $resolved; }
            [$row, $order] = $resolved;
            $body = $request->get_json_params();
            if (!is_array($body) || !$body || array_diff(array_keys($body), ['address', 'order_notes'])) { return new WP_Error('invalid_order_update', 'Send the address and/or order notes to update.', ['status' => 422]); }

            // Shares the payment and cancellation lock so edits cannot race them.
            $lock = ElDokan_Customer_API_Order_Store::lock('payment|' . $row['order_id']);
            if (is_wp_error($lock)) { return $lock; }
            $order = wc_get_order($row['woo_order_id']);
            if (!$order || $order->get_meta('_eldokan_customer_public_order_id', true) !== $row['order_id']) { return ElDokan_Customer_API_Order_Read::missing(); }
            if (!self::policy($order)['available']) { return new WP_Error('order_edit_unavailable', 'This order can no longer be changed online. Contact the store.', ['status' => 409]); }

            $current = self::address($order);
            if (!is_array($current)) { throw new RuntimeException('Historical address unavailable.'); }
            $changes = [];
            if (array_key_exists('address', $body)) {
                $address = ElDokan_Customer_API_Checkout::validate_address($body['address']);
                if (is_wp_error($address)) { return $address; }
                if (($address['state'] ?? '') !== ($current['state'] ?? '')) { return new WP_Error('order_edit_governorate', 'The governorate cannot change after ordering because shipping depends on it. Cancel and order again for a different governorate.', ['status' => 422]); }
                $order->update_meta_data(self::META, ['address' => $address, 'updated_at' => gmdate('c')]);
                foreach (['billing', 'shipping'] as $type) {
                    $order->{"set_{$type}_first_name"}($address['first_name']);
                    $order->{"set_{$type}_last_name"}($address['last_name']);
                    $order->{"set_{$type}_address_1"}($address['street_address']);
                    $order->{"set_{$type}_address_2"}((string) ($address['address_extra'] ?? ''));
                    $order->{"set_{$type}_city"}($address['city']);
                    $order->{"set_{$type}_state"}($address['state']);
                    $order->{"set_{$type}_country"}('EG');
                    $order->{"set_{$type}_phone"}($address['phone']);
                }
                $order->set_billing_email($address['email']);
                $changes[] = 'delivery address: ' . implode(', ', array_filter([$address['first_name'] . ' ' . $address['last_name'], $address['phone'], $address['street_address'], $address['address_extra'] ?? '', $address['city'], $address['state']]));
            }
            if (array_key_exists('order_notes', $body)) {
                $notes = $body['order_notes'];
                if (!is_string($notes) || strlen($notes) > 4000 || !preg_match('//u', $notes)) { return new WP_Error('invalid_order_notes', 'Use at most 1000 characters for order notes.', ['status' => 422]); }
                $notes = trim(sanitize_textarea_field($notes));
                if (preg_match_all('/./us', $notes) > 1000) { return new WP_Error('invalid_order_notes', 'Use at most 1000 characters for order notes.', ['status' => 422]); }
                $order->set_customer_note($notes);
                $changes[] = 'order notes: ' . ($notes !== '' ? $notes : '(removed)');
            }
            $order->save();
            $order->add_order_note('Customer updated the order online — ' . implode(' | ', $changes));
            return ElDokan_Customer_API_Order_Read::detail($row, wc_get_order($order->get_id()));
        } catch (Throwable $error) { return new WP_Error('order_edit_uncertain', 'Refresh this order to check whether the change was saved.', ['status' => 503]); }
    }
}
