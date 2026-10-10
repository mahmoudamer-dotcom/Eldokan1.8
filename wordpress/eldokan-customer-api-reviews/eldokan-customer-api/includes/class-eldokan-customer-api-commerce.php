<?php
if (!defined('ABSPATH')) { exit; }

/** Customer extensions; WooCommerce remains authoritative for payments and refunds. */
final class ElDokan_Customer_API_Commerce {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('add_meta_boxes', [__CLASS__, 'boxes']);
        add_action('woocommerce_process_shop_order_meta', [__CLASS__, 'save_return'], 30);
        add_action('eldokan_product_alerts', [__CLASS__, 'send_alerts']);
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('wp_mail_failed', static function () { self::record_event('mail_delivery', 503, null); });
        add_action('delete_comment', static function ($id) { foreach ((array) get_comment_meta($id, '_eldokan_review_images', true) as $attachment) { if ((int) $attachment > 0) { wp_delete_attachment((int) $attachment, true); } } });
        add_action('woocommerce_order_status_failed', static function ($id) { $order = wc_get_order($id); if ($order && $order->get_meta('_eldokan_customer_flow', true) === 'phase2c-a3') { self::record_event('/orders/payment-failed', 500, null); } });
        add_filter('rest_post_dispatch', [__CLASS__, 'observe'], 40, 3);
        if (!wp_next_scheduled('eldokan_product_alerts')) { wp_schedule_event(time() + 300, 'hourly', 'eldokan_product_alerts'); }
    }
    public static function routes() {
        $routes = [
            '/auth/forgot-password' => ['POST'], '/auth/reset-password' => ['POST'],
            '/orders/(?P<order_id>ord_[a-f0-9]{64})/returns' => ['GET', 'POST'],
            '/me/product-alerts' => ['GET', 'POST'], '/me/product-alerts/(?P<product_id>prd_[1-9][0-9]*)' => ['DELETE'],
            '/me/decision' => ['GET', 'POST'], '/reviews/(?P<review_id>rev_[1-9][0-9]*)/feedback' => ['POST'],
            '/reviews/(?P<review_id>rev_[1-9][0-9]*)/images' => ['POST'], '/catalog/sitemap' => ['GET'],
        ];
        foreach ($routes as $path => $methods) { register_rest_route(ElDokan_Customer_API::REST_NAMESPACE, $path, ['methods' => $methods, 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'dispatch']]); }
    }
    public static function dispatch($request) {
        try {
            $origin = ElDokan_Customer_API_Auth::validate_request_origin();
            if (is_wp_error($origin)) { return self::response($origin); }
            $path = $request->get_route();
            if (strpos($path, '/auth/') !== false) { $data = self::password($request); }
            elseif (strpos($path, '/returns') !== false) { $data = self::returns($request); }
            elseif (strpos($path, '/catalog/sitemap') !== false) {
                $page = filter_var($request->get_param('page') ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
                if (!$page) { return self::response(new WP_Error('invalid_page', 'Invalid page.', ['status' => 400])); }
                $query = new WP_Query(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 200, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'tax_query' => [['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => 'NOT IN']]]);
                $data = ['items' => array_map(static function ($id) { return ['id' => 'prd_' . $id, 'updated_at' => get_post_modified_time('c', true, $id) ?: null]; }, $query->posts), 'total_pages' => (int) $query->max_num_pages, 'categories' => []];
                if ($page === 1) { $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]); if (!is_wp_error($terms)) { $data['categories'] = array_map(static function ($term) { return ['slug' => $term->slug]; }, $terms); } }
            } else {
                $user = ElDokan_Customer_API_Auth::require_customer($request, $request->get_method() !== 'GET');
                if (is_wp_error($user)) { return self::response($user); }
                if (strpos($path, '/product-alerts') !== false) { $data = self::alerts($request, $user); }
                elseif (strpos($path, '/decision') !== false) { $data = self::decision($request, $user); }
                else { $data = self::review_action($request, $user); }
            }
            return self::response($data);
        } catch (Throwable $error) { return self::response(new WP_Error('commerce_unavailable', 'This service is temporarily unavailable.', ['status' => 503])); }
        finally { ElDokan_Customer_API_Order_Store::release_locks(); }
    }
    private static function response($data) {
        $response = is_wp_error($data) ? ElDokan_Customer_API::from_error($data) : ElDokan_Customer_API::success($data);
        $response->header('Cache-Control', 'private, no-store');
        return $response;
    }
    private static function limit($scope, $identity, $max, $seconds) {
        $key = 'eld_c_' . hash('sha256', $scope . '|' . $identity);
        $count = (int) get_transient($key);
        if ($count >= $max) { return new WP_Error('rate_limited', 'Please wait before trying again.', ['status' => 429]); }
        set_transient($key, $count + 1, $seconds); return true;
    }
    private static function password($request) {
        $limited = self::limit('password_ip', $_SERVER['REMOTE_ADDR'] ?? '', 50, HOUR_IN_SECONDS);
        if (is_wp_error($limited)) { return $limited; }
        $body = $request->get_json_params();
        if (!is_array($body)) { return new WP_Error('invalid_input', 'Invalid input.', ['status' => 422]); }
        if (strpos($request->get_route(), '/forgot-password') !== false) {
            $email = $body['email'] ?? '';
            if (!is_string($email) || !is_email($email)) { return new WP_Error('invalid_email', 'Enter a valid email address.', ['status' => 422]); }
            $origin = ElDokan_Customer_API_Paymob::frontend_origin();
            if (!$origin) { return new WP_Error('password_delivery_unavailable', 'Configure the HTTPS storefront origin first.', ['status' => 503]); }
            $allowed = self::limit('password_email', strtolower($email), 3, HOUR_IN_SECONDS);
            if (!is_wp_error($allowed)) {
                $user = get_user_by('email', $email);
                if ($user && in_array('customer', (array) $user->roles, true)) {
                    $key = get_password_reset_key($user);
                    if (!is_wp_error($key)) {
                        $url = $origin . '/reset-password?' . http_build_query(['login' => $user->user_login, 'key' => $key]);
                        $sent = wp_mail($user->user_email, 'الدكان | ElDokan: إعادة تعيين كلمة المرور / Reset password', "إعادة تعيين كلمة المرور / Reset your password:\n" . $url . "\nلو ما طلبتش تغيير كلمة المرور، تجاهل الرسالة.\nIf you did not request this, ignore this email.");
                        if (!$sent) { do_action('eldokan_commerce_mail_failed', 'password_reset'); }
                    }
                }
            }
            return ['accepted' => true];
        }
        $login = $body['login'] ?? ''; $key = $body['key'] ?? ''; $password = $body['password'] ?? '';
        if (!is_string($login) || !is_string($key) || !is_string($password) || strlen($password) < 10 || strlen($password) > 256 || strlen($login) > 100 || strlen($key) > 100) { return new WP_Error('invalid_password_reset', 'Use a valid reset link and a password of 10 to 256 characters.', ['status' => 422]); }
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user) || !in_array('customer', (array) $user->roles, true)) { return new WP_Error('invalid_password_reset', 'This password reset link is invalid or expired.', ['status' => 422]); }
        reset_password($user, $password);
        WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        return ['reset' => true];
    }
    private static function return_summary($order) {
        $saved = $order->get_meta('_eldokan_return_request', true);
        return ['available' => !$saved && ElDokan_Customer_API_Order_Read::status($order) === 'delivered', 'request' => is_array($saved) ? $saved : null, 'items' => array_values(array_map(static function ($item) { return ['item_id' => (int) $item->get_id(), 'name' => sanitize_text_field($item->get_name()), 'quantity' => (int) $item->get_quantity()]; }, $order->get_items())), 'refunded_amount' => wc_format_decimal($order->get_total_refunded(), wc_get_price_decimals()), 'currency' => $order->get_currency()];
    }
    private static function returns($request) {
        $resolved = ElDokan_Customer_API_Order_Read::authorize($request, $request->get_method() !== 'GET');
        if (is_wp_error($resolved)) { return $resolved; }
        [$row, $order] = $resolved;
        if ($request->get_method() === 'GET') { return self::return_summary($order); }
        $lock = ElDokan_Customer_API_Order_Store::lock('return|' . $row['order_id']);
        if (is_wp_error($lock)) { return $lock; }
        try {
            $order = wc_get_order($order->get_id()); $summary = self::return_summary($order);
            if ($summary['request']) { return $summary; }
            if (!$summary['available']) { return new WP_Error('return_unavailable', 'Returns can be requested after delivery. Contact the store for help.', ['status' => 409]); }
            $body = $request->get_json_params(); $reason = $body['reason'] ?? ''; $details = $body['details'] ?? ''; $lines = $body['lines'] ?? [];
            if (!in_array($reason, ['damaged', 'wrong_item', 'missing_parts', 'not_as_described', 'changed_mind', 'other'], true) || !is_string($details) || strlen($details) > 2000 || !is_array($lines) || !$lines || count($lines) > 100 || ($reason === 'other' && !trim($details))) { return new WP_Error('invalid_return', 'Choose items and a return reason.', ['status' => 422]); }
            $items = $order->get_items(); $validated = [];
            foreach ($lines as $line) {
                $id = $line['item_id'] ?? null; $quantity = $line['quantity'] ?? null;
                if (!is_int($id) || !isset($items[$id]) || !is_int($quantity) || $quantity < 1 || $quantity > $items[$id]->get_quantity() || isset($validated[$id])) { return new WP_Error('invalid_return_items', 'Invalid return items.', ['status' => 422]); }
                $validated[$id] = ['item_id' => $id, 'quantity' => $quantity];
            }
            $order->update_meta_data('_eldokan_return_request', ['id' => 'ret_' . wp_generate_uuid4(), 'state' => 'requested', 'reason' => $reason, 'details' => sanitize_textarea_field($details), 'staff_note' => '', 'lines' => array_values($validated), 'created_at' => gmdate('c'), 'updated_at' => gmdate('c')]);
            $order->save(); $order->add_order_note('Customer submitted a return request. Review the ElDokan return panel. No refund has been issued.');
            return self::return_summary($order);
        } finally { ElDokan_Customer_API_Order_Store::release_locks(); }
    }
    private static function alerts($request, $user) {
        if ($request->get_method() !== 'GET') { $lock = ElDokan_Customer_API_Order_Store::lock('alerts|' . $user->ID); if (is_wp_error($lock)) { return $lock; } }
        $saved = get_user_meta($user->ID, '_eldokan_product_alerts', true); $saved = is_array($saved) ? $saved : [];
        if ($request->get_method() === 'DELETE') { unset($saved[$request['product_id']]); }
        elseif ($request->get_method() === 'POST') {
            $body = $request->get_json_params(); $id = $body['product_id'] ?? ''; $kind = $body['kind'] ?? ''; $price = $body['target_price'] ?? null;
            if (!is_string($id) || !preg_match('/^prd_[1-9][0-9]*$/D', $id) || !in_array($kind, ['stock', 'price'], true) || ($kind === 'price' && (!is_numeric($price) || (float) $price <= 0 || (float) $price > 100000000))) { return new WP_Error('invalid_alert', 'Choose a valid product and target price.', ['status' => 422]); }
            $product = wc_get_product((int) substr($id, 4));
            if (!$product || $product->get_status() !== 'publish' || $product->get_type() !== 'simple') { return new WP_Error('alert_unavailable', 'Alerts currently support published simple products.', ['status' => 422]); }
            if ($kind === 'price' && ($product->get_price() === '' || (float) $price >= (float) $product->get_price())) { return new WP_Error('alert_price_not_lower', 'Choose a target below the current product price.', ['status' => 422]); }
            if ($kind === 'stock' && $product->is_in_stock()) { return new WP_Error('alert_already_in_stock', 'This product is already in stock.', ['status' => 422]); }
            if (count($saved) >= 50 && !isset($saved[$id])) { return new WP_Error('alert_limit', 'You can save up to 50 alerts.', ['status' => 422]); }
            $saved[$id] = ['product_id' => $id, 'name' => wp_strip_all_tags($product->get_name()), 'kind' => $kind, 'target_price' => $kind === 'price' ? (float) $price : null, 'notified_at' => null];
        }
        if ($request->get_method() !== 'GET') { update_user_meta($user->ID, '_eldokan_product_alerts', $saved); }
        return ['items' => array_values($saved)];
    }
    private static function decision($request, $user) {
        $empty = ['category' => '', 'budget' => '', 'keywords' => '', 'priority' => 'price', 'compare_ids' => []];
        if ($request->get_method() === 'POST') {
            $body = $request->get_json_params(); if (!is_array($body)) { return new WP_Error('invalid_decision', 'Invalid selection.', ['status' => 422]); }
            $saved = $empty;
            foreach (['category', 'budget', 'keywords', 'priority'] as $field) { if (!is_string($body[$field] ?? '') || strlen($body[$field] ?? '') > 400) { return new WP_Error('invalid_decision', 'Invalid selection.', ['status' => 422]); } $saved[$field] = sanitize_text_field($body[$field] ?? ''); }
            $saved['compare_ids'] = array_values(array_unique(array_slice(array_filter((array) ($body['compare_ids'] ?? []), static function ($id) { return is_string($id) && preg_match('/^prd_[1-9][0-9]*$/D', $id); }), 0, 4)));
            update_user_meta($user->ID, '_eldokan_decision', $saved);
        }
        $saved = get_user_meta($user->ID, '_eldokan_decision', true); return is_array($saved) ? $saved : $empty;
    }
    public static function review_extras($comment) {
        global $wpdb;
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id=%d AND meta_key LIKE %s", $comment->comment_ID, $wpdb->esc_like('_eldokan_helpful_') . '%'));
        $images = [];
        foreach ((array) get_comment_meta($comment->comment_ID, '_eldokan_review_images', true) as $id) { $url = wp_get_attachment_image_url((int) $id, 'large'); if ($url) { $images[] = ['url' => $url, 'alt' => 'Customer review photo']; } }
        return ['helpful_count' => $count, 'images' => $images];
    }
    private static function review_action($request, $user) {
        $limited = self::limit('feedback', (string) $user->ID, 30, HOUR_IN_SECONDS); if (is_wp_error($limited)) { return $limited; }
        $id = (int) substr($request['review_id'], 4); $comment = get_comment($id);
        $lock = ElDokan_Customer_API_Order_Store::lock('feedback|' . $id); if (is_wp_error($lock)) { return $lock; }
        if (!$comment || !in_array($comment->comment_type, ['', 'review', 'eldokan_seller_review'], true) || ($comment->comment_type !== 'eldokan_seller_review' && (get_post_type($comment->comment_post_ID) !== 'product' || (int) get_comment_meta($id, 'rating', true) < 1))) { return new WP_Error('review_not_found', 'Review not found.', ['status' => 404]); }
        $body = $request->get_json_params();
        if (strpos($request->get_route(), '/images') !== false) {
            if ((int) $comment->user_id !== (int) $user->ID || in_array((string) $comment->comment_approved, ['spam', 'trash'], true)) { return new WP_Error('review_not_found', 'Review not found.', ['status' => 404]); }
            $lock = ElDokan_Customer_API_Order_Store::lock('review-image|' . $id); if (is_wp_error($lock)) { return $lock; }
            try {
                $images = get_comment_meta($id, '_eldokan_review_images', true); $images = is_array($images) ? $images : [];
                $encoded = $body['image'] ?? '';
                if (!is_string($encoded) || strlen($encoded) > 4200000 || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $encoded, $match)) { return new WP_Error('invalid_review_image', 'Use up to three JPEG, PNG or WebP images, each under 3 MB.', ['status' => 422]); }
                $bytes = base64_decode($match[2], true); $info = $bytes ? @getimagesizefromstring($bytes) : false;
                if (!$info || strlen($bytes) > 3000000 || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 24000000 || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) { return new WP_Error('invalid_review_image', 'Invalid review image.', ['status' => 422]); }
                $hashes = get_comment_meta($id, '_eldokan_review_image_hashes', true); $hashes = is_array($hashes) ? $hashes : [];
                $hash = hash('sha256', $bytes);
                if (in_array($hash, $hashes, true)) { return self::review_extras(get_comment($id)); }
                if (count($images) >= 3) { return new WP_Error('invalid_review_image', 'A review supports up to three images.', ['status' => 422]); }
                $upload = wp_upload_bits('eldokan-review-' . wp_generate_uuid4() . '.' . ($info['mime'] === 'image/jpeg' ? 'jpg' : ($info['mime'] === 'image/png' ? 'png' : 'webp')), null, $bytes);
                if ($upload['error']) { return new WP_Error('image_upload_unavailable', 'Image upload failed.', ['status' => 503]); }
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $editor = wp_get_image_editor($upload['file']);
                if (is_wp_error($editor)) { wp_delete_file($upload['file']); return new WP_Error('image_upload_unavailable', 'Image processing failed.', ['status' => 503]); }
                $editor->resize(1600, 1600, false); $processed = $editor->save($upload['file']);
                if (is_wp_error($processed)) { wp_delete_file($upload['file']); return $processed; }
                $attachment = wp_insert_attachment(['post_mime_type' => $info['mime'], 'post_title' => 'Customer review image', 'post_status' => 'inherit', 'post_author' => $user->ID], $upload['file'], $comment->comment_post_ID, true);
                if (is_wp_error($attachment)) { wp_delete_file($upload['file']); return $attachment; }
                wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $upload['file']));
                wp_set_comment_status($id, 'hold');
                $images[] = $attachment; update_comment_meta($id, '_eldokan_review_images', $images);
                $hashes[] = $hash; update_comment_meta($id, '_eldokan_review_image_hashes', $hashes);
                return self::review_extras(get_comment($id));
            } finally { ElDokan_Customer_API_Order_Store::release_locks(); }
        }
        if ((string) $comment->comment_approved !== '1') { return new WP_Error('review_not_found', 'Review not found.', ['status' => 404]); }
        if (($body['action'] ?? '') === 'helpful') {
            if ((int) $comment->user_id === (int) $user->ID) { return new WP_Error('own_review_vote', 'You cannot vote on your own review.', ['status' => 422]); }
            add_comment_meta($id, '_eldokan_helpful_' . $user->ID, 1, true);
        } elseif (($body['action'] ?? '') === 'report') {
            $reason = $body['reason'] ?? '';
            if (!is_string($reason) || !trim($reason) || strlen($reason) > 1000) { return new WP_Error('report_reason_required', 'Explain why you are reporting this review.', ['status' => 422]); }
            add_comment_meta($id, '_eldokan_report_' . $user->ID, sanitize_textarea_field($reason), true);
        } else { return new WP_Error('invalid_feedback', 'Invalid action.', ['status' => 422]); }
        return ['accepted' => true, 'helpful_count' => self::review_extras($comment)['helpful_count']];
    }
    public static function send_alerts() {
        $offset = (int) get_option('eldokan_alert_offset', 0);
        $users = get_users(['role' => 'customer', 'meta_key' => '_eldokan_product_alerts', 'number' => 100, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => ['ID', 'user_email']]);
        update_option('eldokan_alert_offset', count($users) === 100 ? $offset + 100 : 0, false);
        $origin = ElDokan_Customer_API_Paymob::frontend_origin(); if (!$origin) { return; }
        foreach ($users as $user) {
            $lock = ElDokan_Customer_API_Order_Store::lock('alerts|' . $user->ID); if (is_wp_error($lock)) { continue; }
            try {
            $saved = get_user_meta($user->ID, '_eldokan_product_alerts', true); if (!is_array($saved)) { continue; }
            foreach ($saved as $id => &$alert) {
                if (!empty($alert['notified_at'])) { continue; }
                $product = wc_get_product((int) substr($id, 4));
                if (!$product || $product->get_status() !== 'publish' || !$product->is_in_stock() || $product->get_price() === '') { continue; }
                if ($alert['kind'] === 'price' && (float) $product->get_price() > (float) $alert['target_price']) { continue; }
                $message = ($alert['kind'] === 'stock' ? 'المنتج متوفر الآن / Product back in stock' : 'سعر المنتج وصل للسعر المستهدف / Product reached your target price') . "\n" . wp_strip_all_tags($product->get_name()) . "\n" . $origin . '/product/' . $id . "\nإدارة التنبيهات من حسابك / Manage alerts:\n" . $origin . '/account';
                if (wp_mail($user->user_email, 'الدكان | ElDokan: تنبيه منتج / Product alert', $message)) { $alert['notified_at'] = gmdate('c'); }
            }
            unset($alert); update_user_meta($user->ID, '_eldokan_product_alerts', $saved);
            } finally { ElDokan_Customer_API_Order_Store::release_locks(); }
        }
    }
    public static function boxes() { foreach (['shop_order', 'woocommerce_page_wc-orders'] as $screen) { add_meta_box('eldokan-return', 'ElDokan customer return', [__CLASS__, 'return_box'], $screen, 'side'); } }
    public static function return_box($object) {
        if (!current_user_can('manage_woocommerce')) { return; }
        $order = $object instanceof WC_Order ? $object : wc_get_order($object->ID); $saved = $order ? $order->get_meta('_eldokan_return_request', true) : null;
        if (!is_array($saved)) { echo '<p>No return request.</p>'; return; }
        wp_nonce_field('eldokan_return_' . $order->get_id(), 'eldokan_return_nonce');
        echo '<p>' . esc_html($saved['reason'] . ': ' . $saved['details']) . '</p><ul>';
        foreach ($saved['lines'] as $line) { $item = $order->get_item($line['item_id']); echo '<li>' . esc_html(($item ? $item->get_name() : 'Item') . ' x ' . $line['quantity']) . '</li>'; }
        echo '</ul><select name="eldokan_return_state">';
        foreach (['requested', 'approved', 'received', 'closed', 'rejected'] as $state) { echo '<option value="' . esc_attr($state) . '" ' . selected($state, $saved['state'], false) . '>' . esc_html(ucfirst($state)) . '</option>'; }
        echo '</select><p><label>Reply visible to the customer<textarea name="eldokan_return_note" rows="3" maxlength="500" style="width:100%">' . esc_textarea($saved['staff_note'] ?? '') . '</textarea></label></p><p>Refund separately through WooCommerce and your payment provider. This status never issues a refund.</p>';
    }
    public static function save_return($id) {
        if (!current_user_can('manage_woocommerce') || !isset($_POST['eldokan_return_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eldokan_return_nonce'])), 'eldokan_return_' . $id)) { return; }
        $order = wc_get_order($id); $saved = $order ? $order->get_meta('_eldokan_return_request', true) : null; $state = sanitize_key($_POST['eldokan_return_state'] ?? '');
        $next = ['requested' => ['approved', 'rejected'], 'approved' => ['received', 'rejected'], 'received' => ['closed', 'rejected'], 'closed' => [], 'rejected' => []];
        if (!is_array($saved) || ($state !== $saved['state'] && !in_array($state, $next[$saved['state']] ?? [], true))) { return; }
        $note = wp_unslash($_POST['eldokan_return_note'] ?? '');
        if (is_string($note) && strlen($note) <= 2000) { $saved['staff_note'] = sanitize_textarea_field($note); }
        $saved['state'] = $state; $saved['updated_at'] = gmdate('c'); $order->update_meta_data('_eldokan_return_request', $saved); $order->save(); $order->add_order_note('ElDokan return status: ' . $state);
    }
    public static function observe($response, $server, $request) {
        if (strpos($request->get_route(), '/' . ElDokan_Customer_API::REST_NAMESPACE . '/') !== 0 || !($response instanceof WP_REST_Response) || $response->get_status() < 500) { return $response; }
        $body = $response->get_data();
        self::record_event(preg_replace('#/(ord_|prd_|sel_|rev_)[^/]+#', '/{id}', $request->get_route()), $response->get_status(), $body['meta']['request_id'] ?? null);
        return $response;
    }
    private static function record_event($resource, $status, $request_id) {
        $records = get_transient('eldokan_commerce_errors'); $records = is_array($records) ? $records : [];
        $records = array_values(array_filter($records, static function ($record) { return strtotime($record['utc']) >= time() - DAY_IN_SECONDS; }));
        $records[] = ['utc' => gmdate('c'), 'status' => $status, 'resource' => $resource, 'request_id' => $request_id];
        set_transient('eldokan_commerce_errors', array_slice($records, -100), DAY_IN_SECONDS);
    }
    public static function menu() { add_submenu_page('woocommerce', 'ElDokan service health', 'ElDokan service health', 'manage_woocommerce', 'eldokan-service-health', [__CLASS__, 'health_page']); }
    public static function search_alias($query) {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($query)) : strtolower(trim($query));
        $aliases = ['ايفون' => 'iphone', 'آيفون' => 'iphone', 'سامسونج' => 'samsung', 'ديل' => 'dell', 'iphnoe' => 'iphone', 'ipone' => 'iphone', 'samsng' => 'samsung'];
        foreach (explode("\n", (string) get_option('eldokan_search_aliases', '')) as $line) { $pair = explode('=', $line, 2); if (count($pair) === 2) { $aliases[trim($pair[0])] = trim($pair[1]); } }
        return $aliases[$normalized] ?? $query;
    }
    public static function search_outcome($empty) {
        $key = 'eldokan_search_' . gmdate('Ymd'); $stats = get_transient($key); $stats = is_array($stats) ? $stats : ['reads' => 0, 'empty' => 0];
        $stats['reads']++; if ($empty) { $stats['empty']++; } set_transient($key, $stats, 2 * DAY_IN_SECONDS);
    }
    public static function health_page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        if (isset($_POST['eldokan_search_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eldokan_search_nonce'])), 'eldokan_search_aliases')) {
            $aliases = sanitize_textarea_field(wp_unslash($_POST['eldokan_search_aliases'] ?? ''));
            update_option('eldokan_search_aliases', substr($aliases, 0, 10000), false); ElDokan_Customer_API_Cache::invalidate();
            $native = sanitize_key(wp_unslash($_POST['eldokan_delivered_status'] ?? ''));
            $statuses = wc_get_order_statuses();
            if ($native === '' || (isset($statuses['wc-' . $native]) && !in_array($native, ['pending', 'on-hold', 'processing', 'cancelled', 'refunded', 'failed'], true))) {
                $mapping = get_option('eldokan_customer_api_order_status_map', []); $mapping = is_array($mapping) ? $mapping : [];
                foreach ($mapping as $key => $value) { if ($value === 'delivered') { unset($mapping[$key]); } }
                if ($native !== '') { $mapping[$native] = 'delivered'; } update_option('eldokan_customer_api_order_status_map', $mapping, false);
            }
        }
        echo '<div class="wrap"><h1>ElDokan service health</h1><p>Recent API server failures (last 24 hours). Request bodies, passwords and payment keys are never recorded.</p><p>Password resets and product alerts require working WordPress email delivery. Alerts require WP-Cron or a real cron runner.</p><h2>Review reports</h2>';
        echo '<h2>Search aliases</h2><p>One alias=search phrase per line. Used only when the original search has no results. Today\'s uncached first-page API search outcomes: ' . esc_html(wp_json_encode(get_transient('eldokan_search_' . gmdate('Ymd')) ?: ['reads' => 0, 'empty' => 0])) . '</p><form method="post">';
        wp_nonce_field('eldokan_search_aliases', 'eldokan_search_nonce');
        echo '<textarea name="eldokan_search_aliases" rows="6" cols="60">' . esc_textarea(get_option('eldokan_search_aliases', '')) . '</textarea><h2>Confirmed delivery status</h2><p>Choose only the WooCommerce status your operations set after confirmed delivery. Completed does not automatically prove delivery. Returns become available after this recorded status.</p><select name="eldokan_delivered_status"><option value="">No delivery mapping</option>';
        $mapping = get_option('eldokan_customer_api_order_status_map', []);
        foreach (wc_get_order_statuses() as $key => $label) { $native = substr($key, 3); if (!in_array($native, ['pending', 'on-hold', 'processing', 'cancelled', 'refunded', 'failed'], true)) { echo '<option value="' . esc_attr($native) . '" ' . selected(is_array($mapping) && ($mapping[$native] ?? '') === 'delivered', true, false) . '>' . esc_html($label) . '</option>'; } }
        echo '</select><p><button class="button button-primary">Save service settings</button></p></form>';
        global $wpdb;
        $reports = $wpdb->get_results($wpdb->prepare("SELECT comment_id,meta_value FROM {$wpdb->commentmeta} WHERE meta_key LIKE %s ORDER BY meta_id DESC LIMIT 50", $wpdb->esc_like('_eldokan_report_') . '%'));
        foreach ($reports as $report) { echo '<p><a href="' . esc_url(admin_url('comment.php?action=editcomment&c=' . (int) $report->comment_id)) . '">Review #' . (int) $report->comment_id . '</a>: ' . esc_html($report->meta_value) . '</p>'; }
        echo '<h2>Recent API errors</h2><pre>' . esc_html(wp_json_encode(get_transient('eldokan_commerce_errors') ?: [], JSON_PRETTY_PRINT)) . '</pre></div>';
    }
}
