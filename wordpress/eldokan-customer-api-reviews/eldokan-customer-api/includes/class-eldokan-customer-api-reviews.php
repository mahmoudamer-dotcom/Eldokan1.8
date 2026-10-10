<?php
if (!defined('ABSPATH')) { exit; }

/** Public approved reviews; private identity and purchase checks never enter catalog caches. */
final class ElDokan_Customer_API_Reviews {
    public static function register() {
        foreach (['products' => 'prd', 'sellers' => 'sel'] as $resource => $prefix) {
            $base = '/' . $resource . '/(?P<target>' . $prefix . '_[1-9][0-9]*)/reviews';
            register_rest_route(ElDokan_Customer_API::REST_NAMESPACE, $base, [
                ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'listing']],
                ['methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'create']],
            ]);
            register_rest_route(ElDokan_Customer_API::REST_NAMESPACE, $base . '/mine', [
                'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'mine'],
            ]);
            register_rest_route(ElDokan_Customer_API::REST_NAMESPACE, $base . '/(?P<review_id>rev_[1-9][0-9]*)', [
                ['methods' => 'PATCH', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'update']],
                ['methods' => 'DELETE', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'remove']],
            ]);
        }
        register_rest_route(ElDokan_Customer_API::REST_NAMESPACE, '/sellers/(?P<seller_id>sel_[1-9][0-9]*)/products', [
            'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [__CLASS__, 'seller_products'],
        ]);
    }

    private static function response($data, $status = 200, $meta = []) {
        $response = is_wp_error($data) ? ElDokan_Customer_API::from_error($data) : ElDokan_Customer_API::success($data, $meta, $status);
        $response->header('Cache-Control', 'private, no-store');
        $response->header('X-ElDokan-API-Version', ELDOKAN_CUSTOMER_API_VERSION);
        $body = $response->get_data();
        if (isset($body['meta']['request_id'])) { $response->header('X-ElDokan-Request-ID', $body['meta']['request_id']); }
        return $response;
    }

    private static function target($request) {
        $required = ElDokan_Customer_API_Utils::require_woocommerce();
        if (is_wp_error($required)) { return $required; }
        $public = (string) $request['target'];
        $seller = strpos($public, 'sel_') === 0;
        $id = ElDokan_Customer_API_Utils::parse_opaque_id($seller ? 'sel' : 'prd', $public);
        if ($seller) {
            $entity = ElDokan_Customer_API_Sellers::by_public_id($public);
        } else {
            $entity = ElDokan_Customer_API_Products::single_by_public_id($public);
        }
        if (is_wp_error($entity)) { return $entity; }
        return ['id' => $id, 'seller' => $seller, 'type' => $seller ? 'eldokan_seller_review' : 'review'];
    }

    private static function query($target) {
        $args = ['type' => $target['type'], 'parent' => 0];
        if ($target['seller']) {
            $args['meta_query'] = [['key' => '_eldokan_review_seller', 'value' => $target['id']]];
        } else { $args['post_id'] = $target['id']; }
        return $args;
    }

    public static function summary($id, $seller = false) {
        global $wpdb;
        $key = $seller ? 'eldokan_rating' : 'rating';
        $where = $seller
            ? $wpdb->prepare("c.comment_type = 'eldokan_seller_review' AND EXISTS (SELECT 1 FROM {$wpdb->commentmeta} s WHERE s.comment_id=c.comment_ID AND s.meta_key='_eldokan_review_seller' AND s.meta_value=%s)", (string) $id)
            : $wpdb->prepare("c.comment_type IN ('review','') AND c.comment_post_ID=%d", $id);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT CAST(r.meta_value AS UNSIGNED) stars, COUNT(DISTINCT c.comment_ID) total FROM {$wpdb->comments} c JOIN {$wpdb->commentmeta} r ON r.comment_id=c.comment_ID AND r.meta_key=%s WHERE c.comment_approved='1' AND c.comment_parent=0 AND r.meta_value IN ('1','2','3','4','5') AND $where GROUP BY stars", $key));
        $distribution = array_fill(1, 5, 0); $count = 0; $sum = 0;
        foreach ((array) $rows as $row) { $stars = (int) $row->stars; $distribution[$stars] = (int) $row->total; $count += (int) $row->total; $sum += $stars * (int) $row->total; }
        return ['average' => $count ? round($sum / $count, 2) : null, 'count' => $count, 'distribution' => (object) $distribution];
    }

    public static function listing($request) {
        $target = self::target($request); if (is_wp_error($target)) { return self::response($target); }
        $page = (string) ($request->get_param('page') ?? '1');
        $per = (string) ($request->get_param('per_page') ?? '10');
        $rating = (string) ($request->get_param('rating') ?? '0');
        $sort = (string) ($request->get_param('sort') ?? 'newest');
        if (!preg_match('/^[1-9][0-9]*$/D', $page) || (int) $page > 10000 || !preg_match('/^[1-9][0-9]*$/D', $per) || (int) $per > 30 || !preg_match('/^[0-5]$/D', $rating) || !in_array($sort, ['newest','highest','lowest'], true)) {
            return self::response(new WP_Error('invalid_review_query', 'Invalid review filters.', ['status' => 422]));
        }
        $args = self::query($target); $args['status'] = 'approve';
        if ((int) $rating) { $args['meta_query'][] = ['key' => $target['seller'] ? 'eldokan_rating' : 'rating', 'value' => $rating]; }
        $total = (int) get_comments(array_merge($args, ['count' => true]));
        $args['number'] = (int) $per; $args['offset'] = ((int) $page - 1) * (int) $per;
        $args['orderby'] = ['comment_date_gmt' => 'DESC', 'comment_ID' => 'DESC'];
        if ($sort !== 'newest') { $args['meta_key'] = $target['seller'] ? 'eldokan_rating' : 'rating'; $args['orderby'] = ['meta_value_num' => $sort === 'highest' ? 'DESC' : 'ASC', 'comment_ID' => 'DESC']; }
        return self::response(['items' => array_map([__CLASS__, 'serialize'], get_comments($args)), 'summary' => self::summary($target['id'], $target['seller'])], 200,
            ['page' => (int) $page, 'per_page' => (int) $per, 'total' => $total, 'total_pages' => (int) ceil($total / (int) $per)]);
    }

    /** Seller eligibility uses the order's immutable ownership snapshot. */
    private static function purchase($user, $target) {
        if (!$target['seller']) {
            return wc_customer_bought_product($user->user_email, $user->ID, $target['id']) ? $target['id'] : 0;
        }
        if ($user->ID === $target['id']) { return 0; }
        $page = 1;
        do {
            $result = wc_get_orders(['customer_id' => $user->ID, 'status' => ['processing','completed'], 'limit' => 50, 'page' => $page, 'paginate' => true, 'orderby' => 'ID', 'order' => 'DESC']);
            foreach ($result->orders as $order) {
                foreach ($order->get_items() as $item) {
                    if ((int) $item->get_meta('_eldokan_seller_user_id', true) === $target['id']) { return $item->get_product_id(); }
                }
            }
            $page++;
        } while ($page <= $result->max_num_pages);
        return 0;
    }

    private static function own($user, $target) {
        $rows = get_comments(array_merge(self::query($target), ['user_id' => $user->ID, 'status' => 'all', 'number' => 1, 'orderby' => 'comment_ID', 'order' => 'DESC']));
        return $rows ? $rows[0] : null;
    }

    public static function mine($request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request); if (is_wp_error($user)) { return self::response($user); }
        $target = self::target($request); if (is_wp_error($target)) { return self::response($target); }
        $review = self::own($user, $target);
        $enabled = $target['seller'] || (get_option('woocommerce_enable_reviews', 'yes') === 'yes' && comments_open($target['id']));
        $eligible = $enabled && self::purchase($user, $target);
        return self::response(['can_review' => (bool) $eligible, 'reason' => !$enabled ? 'reviews_closed' : (!$eligible ? 'purchase_required' : null), 'review' => $review ? self::serialize($review) : null]);
    }

    private static function input($request) {
        $body = $request->get_json_params();
        if (!is_array($body) || array_diff(array_keys($body), ['rating','title','comment']) || !isset($body['rating'], $body['comment']) || !is_int($body['rating']) || $body['rating'] < 1 || $body['rating'] > 5 || !is_string($body['comment']) || (isset($body['title']) && !is_string($body['title']))) {
            return new WP_Error('invalid_review', 'Choose a rating from one to five and enter your review.', ['status' => 422]);
        }
        $comment = trim(sanitize_textarea_field($body['comment'])); $title = trim(sanitize_text_field($body['title'] ?? ''));
        $len = preg_match_all('/./us', $comment);
        $title_len = preg_match_all('/./us', $title);
        if ($len === false || $title_len === false || $len < 1 || $len > 2000 || $title_len > 100) { return new WP_Error('invalid_review', 'Enter a non-empty review of at most 2000 characters.', ['status' => 422]); }
        return ['rating' => $body['rating'], 'title' => $title, 'comment' => $comment];
    }

    public static function create($request) { return self::save($request, false); }
    public static function update($request) { return self::save($request, true); }
    private static function save($request, $update) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, true); if (is_wp_error($user)) { return self::response($user); }
        $target = self::target($request); if (is_wp_error($target)) { return self::response($target); }
        $input = self::input($request); if (is_wp_error($input)) { return self::response($input); }
        if (!$target['seller'] && (get_option('woocommerce_enable_reviews', 'yes') !== 'yes' || !comments_open($target['id']))) { return self::response(new WP_Error('reviews_closed', 'Reviews are closed for this product.', ['status' => 403])); }
        $product_id = self::purchase($user, $target);
        if (!$product_id) { return self::response(new WP_Error('purchase_required', 'A qualifying purchase is required to review this product or seller.', ['status' => 403])); }
        // Atomic per-customer target admission prevents duplicate reviews on double submission.
        $lock = '_eldokan_review_lock_' . md5($user->ID . ':' . $request['target']);
        if (!add_option($lock, time(), '', false)) {
            $held = (int) get_option($lock);
            if ($held && $held < time() - 120) { delete_option($lock); }
            return self::response(new WP_Error('review_busy', 'Please refresh your review before retrying.', ['status' => 409]));
        }
        try {
            $own = self::own($user, $target);
            if ($update && (!$own || 'rev_' . $own->comment_ID !== (string) $request['review_id'])) { return self::response(new WP_Error('review_not_found', 'Review not found.', ['status' => 404])); }
            if (!$update && $own) { return self::response(new WP_Error('review_exists', 'You already reviewed this item. Edit your existing review.', ['status' => 409])); }
            if ($own && in_array((string) $own->comment_approved, ['spam','trash'], true)) { return self::response(new WP_Error('review_moderated', 'This review is being moderated. Contact the store.', ['status' => 403])); }
            $throttle = 'eldokan_review_write_' . $user->ID;
            if (get_transient($throttle)) { return self::response(new WP_Error('review_rate_limited', 'Wait a minute before submitting another review.', ['status' => 429])); }
            $rating_key = $target['seller'] ? 'eldokan_rating' : 'rating';
            $meta = [$rating_key => $input['rating'], '_eldokan_review_title' => $input['title'], 'verified' => 1];
            if ($target['seller']) { $meta['_eldokan_review_seller'] = $target['id']; }
            if ($update) {
                // An edit returns to moderation; old approved text cannot retain its public score.
                $id = (int) $own->comment_ID;
                $saved = wp_update_comment(wp_slash(['comment_ID' => $id, 'comment_content' => $input['comment'], 'comment_approved' => 0]), true);
                if (is_wp_error($saved) || !$saved) { return self::response(new WP_Error('review_storage_unavailable', 'Review could not be saved.', ['status' => 503])); }
                foreach ($meta as $key => $value) { update_comment_meta($id, $key, $value); }
            } else {
                // WooCommerce consumes the rating field during comment_post. Restore it immediately.
                $previous = $_POST;
                unset($_POST['rating'], $_POST['comment_post_ID']);
                if (!$target['seller']) { $_POST['rating'] = $input['rating']; $_POST['comment_post_ID'] = $product_id; }
                $moderate = static function ($approved) { return in_array((string) $approved, ['spam','trash'], true) ? $approved : 0; };
                add_filter('pre_comment_approved', $moderate, PHP_INT_MAX);
                try {
                    $id = wp_new_comment(wp_slash(['comment_post_ID' => $product_id, 'comment_type' => $target['type'], 'comment_author' => sanitize_text_field($user->display_name), 'comment_author_email' => $user->user_email, 'comment_content' => $input['comment'], 'user_id' => $user->ID, 'comment_meta' => $meta]), true);
                } finally { $_POST = $previous; remove_filter('pre_comment_approved', $moderate, PHP_INT_MAX); }
                if (is_wp_error($id)) { return self::response($id); }
                if (!$id) { return self::response(new WP_Error('review_storage_unavailable', 'Review could not be saved.', ['status' => 503])); }
            }
            set_transient($throttle, 1, 60);
            ElDokan_Customer_API_Cache::invalidate();
            return self::response(self::serialize(get_comment($id)), $update ? 200 : 201);
        } finally { delete_option($lock); }
    }

    public static function remove($request) {
        $user = ElDokan_Customer_API_Auth::require_customer($request, true); if (is_wp_error($user)) { return self::response($user); }
        $target = self::target($request); if (is_wp_error($target)) { return self::response($target); }
        $own = self::own($user, $target);
        if (!$own || 'rev_' . $own->comment_ID !== (string) $request['review_id']) { return self::response(new WP_Error('review_not_found', 'Review not found.', ['status' => 404])); }
        if (!wp_delete_comment($own->comment_ID, true)) { return self::response(new WP_Error('review_storage_unavailable', 'Review could not be deleted.', ['status' => 503])); }
        ElDokan_Customer_API_Cache::invalidate();
        return self::response(['deleted' => true]);
    }

    public static function serialize($comment) {
        $seller = $comment->comment_type === 'eldokan_seller_review';
        return array_merge(['id' => 'rev_' . $comment->comment_ID, 'author' => sanitize_text_field($comment->comment_author), 'rating' => (int) get_comment_meta($comment->comment_ID, $seller ? 'eldokan_rating' : 'rating', true), 'title' => sanitize_text_field(get_comment_meta($comment->comment_ID, '_eldokan_review_title', true)), 'comment' => wp_strip_all_tags($comment->comment_content), 'created_at' => mysql_to_rfc3339($comment->comment_date_gmt), 'verified_purchase' => (bool) get_comment_meta($comment->comment_ID, 'verified', true), 'status' => (string) $comment->comment_approved === '1' ? 'approved' : 'pending'], ElDokan_Customer_API_Commerce::review_extras($comment));
    }

    public static function seller_products($request) {
        foreach (['page' => 10000, 'per_page' => 48] as $key => $max) {
            $value = $request->get_param($key);
            if ($value !== null && (!preg_match('/^[1-9][0-9]*$/D', (string) $value) || (int) $value > $max)) { return self::response(new WP_Error('invalid_product_query', 'Invalid pagination.', ['status' => 422])); }
        }
        $seller = ElDokan_Customer_API_Sellers::by_public_id($request['seller_id']); if (is_wp_error($seller)) { return self::response($seller); }
        $request->set_param('seller', $request['seller_id']);
        $result = ElDokan_Customer_API_Language::run($request, static function () use ($request) { return ElDokan_Customer_API_Products::list($request); });
        if (is_wp_error($result)) { return self::response($result); }
        $items = $result['items']; unset($result['items']);
        return self::response($items, 200, $result);
    }
}
