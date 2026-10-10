<?php
if (!defined('ABSPATH')) { exit; }

/** Customer-owned cancellation before fulfillment; native Woo owns status and stock. */
final class ElDokan_Customer_API_Order_Cancellation {
    const REASONS = ['changed_mind' => 'Changed my mind', 'ordered_by_mistake' => 'Ordered by mistake', 'delivery_time' => 'Delivery time', 'found_better_price' => 'Found a better price', 'other' => 'Other'];

    public static function admin_actions($actions, $order = null) {
        if (!$order || !current_user_can('manage_woocommerce')) { return $actions; }
        $saved = $order->get_meta('_eldokan_customer_cancellation', true);
        if (is_array($saved) && ($saved['state'] ?? '') === 'requested') { $actions['eldokan_decline_customer_cancellation'] = 'Decline customer cancellation request'; }
        return $actions;
    }
    public static function decline($order) {
        if (!current_user_can('manage_woocommerce') || !$order || $order->has_status(['cancelled', 'refunded'])) { return; }
        $saved = $order->get_meta('_eldokan_customer_cancellation', true);
        if (!is_array($saved) || ($saved['state'] ?? '') !== 'requested') { return; }
        $saved['state'] = 'rejected'; $saved['reviewed_at'] = gmdate('c');
        $order->update_meta_data('_eldokan_customer_cancellation', $saved); $order->save();
        $order->add_order_note('Customer cancellation request declined by store staff.');
    }

    public static function policy($order) {
        $saved = $order->get_meta('_eldokan_customer_cancellation', true);
        $eligible = $order->has_status(['pending', 'on-hold', 'processing']);
        if ($eligible) {
            global $wpdb;
            $rows = ElDokan_Customer_API_Payment_Store::rows($wpdb->prepare('SELECT state FROM ' . ElDokan_Customer_API_Order_Store::table('fulfillments') . ' WHERE woo_order_id=%d', $order->get_id()));
            foreach ($rows as $row) { if (($row['state'] ?? '') !== 'pending') { $eligible = false; break; } }
            if (!in_array(ElDokan_Customer_API_Order_Read::status($order), ['pending_payment', 'processing'], true)) { $eligible = false; }
        }
        $state = $order->has_status('refunded') ? 'refunded' : ($order->has_status('cancelled') ? 'cancelled' : (is_array($saved) ? ($saved['state'] ?? null) : null));
        return ['available' => $eligible && (!$state || $state === 'cancelling'), 'mode' => $order->get_payment_method() === 'cod' && !$order->is_paid() ? 'direct' : 'request', 'state' => $state,
            'reason_code' => is_array($saved) ? ($saved['reason_code'] ?? null) : null, 'details' => is_array($saved) ? ($saved['details'] ?? '') : '', 'requested_at' => is_array($saved) ? ($saved['requested_at'] ?? null) : null];
    }

    public static function cancel($request) {
        try {
            $resolved = ElDokan_Customer_API_Order_Read::authorize($request, true);
            if (is_wp_error($resolved)) { return $resolved; }
            [$row, $order] = $resolved;
            $body = $request->get_json_params();
            if (!is_array($body) || array_diff(array_keys($body), ['reason_code', 'details']) || !isset($body['reason_code']) || !is_string($body['reason_code']) || !isset(self::REASONS[$body['reason_code']])) { return new WP_Error('cancellation_reason_required', 'Choose a reason for cancellation.', ['status' => 422]); }
            $details = $body['details'] ?? '';
            if (!is_string($details) || strlen($details) > 2000 || !preg_match('//u', $details)) { return new WP_Error('cancellation_reason_invalid', 'Use at most 500 characters.', ['status' => 422]); }
            $details = trim(sanitize_textarea_field($details));
            if (preg_match_all('/./us', $details) > 500 || ($body['reason_code'] === 'other' && $details === '')) { return new WP_Error('cancellation_reason_invalid', 'Explain your cancellation reason in at most 500 characters.', ['status' => 422]); }
            // Shares the payment initiation lock; another API payment cannot start while checked.
            $lock = ElDokan_Customer_API_Order_Store::lock('payment|' . $row['order_id']);
            if (is_wp_error($lock)) { return $lock; }
            $order = wc_get_order($row['woo_order_id']);
            if (!$order || $order->get_meta('_eldokan_customer_public_order_id', true) !== $row['order_id']) { return ElDokan_Customer_API_Order_Read::missing(); }
            $policy = self::policy($order);
            // Retries never create a second cancellation or duplicate stock changes.
            if (in_array($policy['state'], ['requested', 'cancelled'], true)) { return ElDokan_Customer_API_Order_Read::detail($row, $order); }
            if (!$policy['available']) { return new WP_Error('order_cancellation_unavailable', 'This order can no longer be cancelled online. Contact the store.', ['status' => 409]); }
            $state = $policy['mode'] === 'direct' ? 'cancelled' : 'requested';
            $record = ['state' => $state === 'cancelled' ? 'cancelling' : 'requested', 'reason_code' => $body['reason_code'], 'details' => $details, 'requested_at' => gmdate('c')];
            $order->update_meta_data('_eldokan_customer_cancellation', $record);
            $order->save();
            $note = 'Customer cancellation ' . ($state === 'cancelled' ? 'reason' : 'request (requires staff review)') . ': ' . self::REASONS[$body['reason_code']] . ($details !== '' ? ' — ' . $details : '');
            if ($state === 'cancelled') {
                if (!$order->update_status('cancelled', $note)) { throw new RuntimeException('Native cancellation was not saved.'); }
                $record['state'] = 'cancelled';
                $order->update_meta_data('_eldokan_customer_cancellation', $record);
                $order->save();
            }
            else { $order->add_order_note($note); }
            // No refund API or provider cancellation is implied by a review request.
            return ElDokan_Customer_API_Order_Read::detail($row, wc_get_order($order->get_id()));
        } catch (Throwable $error) { return new WP_Error('order_cancellation_uncertain', 'Refresh this order before retrying the same cancellation.', ['status' => 503]); }
    }
}
