<?php
if (!defined('ABSPATH')) exit;

/** Local Admin stabilization helpers; no schema changes or startup writes. */
trait ElDokan_Admin_RC5 {
    private $commission_write_active = false;
    private $commission_rule_cache = [];
    private $product_category_cache = [];
    private $product_count_cache = [];
    private $pending_registration_cache = null;

    private function request_text($source, $key, $default='') {
        return isset($source[$key]) && is_scalar($source[$key])
            ? sanitize_text_field(wp_unslash((string)$source[$key])) : $default;
    }

    private function product_view_state($source, $prefix='') {
        $status = sanitize_key($this->request_text($source, $prefix.'status'));
        if (!in_array($status, ['', 'publish','pending','draft','private'], true)) $status = '';
        $lang = sanitize_key($this->request_text($source, $prefix.'lang'));
        if ($lang === '') $lang = sanitize_key((string)apply_filters('wpml_current_language', ''));
        return [
            'paged'=>max(1, absint($this->request_text($source, $prefix.'paged', '1'))),
            'category_id'=>absint($this->request_text($source, $prefix.'category_id', '0')),
            's'=>$this->request_text($source, $prefix.'s'), 'status'=>$status, 'lang'=>$lang,
        ];
    }

    private function product_query_args($view) {
        $args = [
            'post_type'=>'product',
            'post_status'=>$view['status'] !== '' ? [$view['status']] : ['publish','pending','draft','private'],
            'posts_per_page'=>100, 'paged'=>$view['paged'], 's'=>$view['s'],
            'orderby'=>'ID', 'order'=>'DESC', 'suppress_filters'=>false,
        ];
        // Keep WPML's current admin context and its query filters intact.
        if ($view['lang'] !== '') $args['lang'] = $view['lang'];
        if ($view['category_id']) $args['tax_query'] = [[
            'taxonomy'=>'product_cat','field'=>'term_id','terms'=>[$view['category_id']], 'include_children'=>true,
        ]];
        return $args;
    }

    private function product_record_count($status='') {
        $view = $this->product_view_state(['status'=>$status]);
        $key = $status.':'.$view['lang'];
        if (!array_key_exists($key, $this->product_count_cache)) {
            $args = $this->product_query_args($view);
            $args['posts_per_page'] = 1;
            $args['fields'] = 'ids';
            $args['update_post_meta_cache'] = false;
            $args['update_post_term_cache'] = false;
            $query = new WP_Query($args);
            $this->product_count_cache[$key] = (int)$query->found_posts;
        }
        return $this->product_count_cache[$key];
    }

    public function customers() {
        if (!current_user_can('list_users')) wp_die(esc_html__('Not allowed', 'eldokan-admin'));
        $page = max(1, absint($this->request_text($_GET, 'paged', '1')));
        $search = $this->request_text($_GET, 's');
        $args = ['role__in'=>['customer','subscriber'], 'number'=>50, 'offset'=>($page-1)*50,
            'count_total'=>true, 'orderby'=>'ID', 'order'=>'DESC', 'fields'=>['ID','display_name','user_email']];
        if ($search !== '') $args['search'] = '*'.$search.'*';
        $query = new WP_User_Query($args);
        $total = (int)$query->get_total();
        $pages = max(1, (int)ceil($total/50));
        if ($page > $pages) {
            $page = $pages; $args['offset'] = ($page-1)*50; $query = new WP_User_Query($args);
        }
        $this->header('Customer Accounts', 'Registered customer/subscriber accounts; guest checkouts are not user accounts.');
        echo '<div class="eda-card"><form method="get"><input type="hidden" name="page" value="eldokan-admin-customers">';
        echo '<label>'.esc_html__('Search accounts', 'eldokan-admin').' <input type="search" name="s" value="'.esc_attr($search).'"></label> <button class="button">'.esc_html__('Search', 'eldokan-admin').'</button></form>';
        echo '<p>'.esc_html(sprintf(__('%d matching accounts', 'eldokan-admin'), $total)).'</p>';
        echo '<div class="eda-table-scroll"><table class="eda-table"><tr><th>ID</th><th>'.esc_html__('Name', 'eldokan-admin').'</th><th>'.esc_html__('Email', 'eldokan-admin').'</th></tr>';
        foreach ($query->get_results() as $user) {
            echo '<tr><td>'.absint($user->ID).'</td><td>'.esc_html($user->display_name).'</td><td><bdi>'.esc_html($user->user_email).'</bdi></td></tr>';
        }
        if (!$total) echo '<tr><td colspan="3">'.esc_html__('No accounts found.', 'eldokan-admin').'</td></tr>';
        echo '</table></div><nav class="eda-pagination" aria-label="'.esc_attr__('Customer account pages', 'eldokan-admin').'">';
        echo paginate_links(['base'=>str_replace('999999999', '%#%', add_query_arg([
            'page'=>'eldokan-admin-customers','s'=>rawurlencode($search),'paged'=>999999999,
        ], admin_url('admin.php'))), 'format'=>'', 'current'=>$page, 'total'=>$pages]);
        echo '</nav></div>';
        $this->footer();
    }

    private function commission_write($operation, $term_id=0) {
        global $wpdb;
        if ($this->commission_write_active) return $operation();
        $tables = [$this->commission_table];
        if ($term_id) $tables[] = $wpdb->termmeta;
        foreach ($tables as $table) {
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s', $table));
            if (!$status || strtolower((string)($status->Engine ?? '')) !== 'innodb') {
                return new WP_Error('commission_storage', __('Safe fee updates require transactional storage. No rules were changed; ask the site administrator to review storage support.', 'eldokan-admin'));
            }
        }
        if ($wpdb->query('START TRANSACTION') === false) return new WP_Error('commission_start', __('Could not start a safe fee update.', 'eldokan-admin'));
        $this->commission_write_active = true;
        try {
            $result = $operation();
            if (is_wp_error($result) || $result === false) {
                return $this->commission_rollback_error(is_wp_error($result) ? $result : new WP_Error('commission_write', __('Fee update failed; previous rules were retained.', 'eldokan-admin')));
            }
            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('commission_commit', __('The fee update could not be confirmed. Reload and verify the saved rule before retrying.', 'eldokan-admin'));
            }
            $this->commission_rule_cache = [];
            return $result;
        } catch (Throwable $error) {
            return $this->commission_rollback_error(new WP_Error('commission_exception', __('Fee update failed; previous rules were retained.', 'eldokan-admin')));
        } finally {
            $this->commission_write_active = false;
            // WordPress meta caches are not rolled back by the database.
            if ($term_id) wp_cache_delete($term_id, 'term_meta');
        }
    }

    private function commission_rollback_error($error) {
        global $wpdb;
        if ($wpdb->query('ROLLBACK') === false) {
            return new WP_Error('commission_rollback', __('Rollback could not be confirmed. Stop retrying and ask the site administrator to verify the saved rules.', 'eldokan-admin'));
        }
        return $error;
    }

    private function commission_values($type, $value, $fixed_value) {
        if (!is_string($type) || !in_array($type, ['percentage','fixed','hybrid'], true) ||
            !is_scalar($value) || !is_scalar($fixed_value) || !is_numeric($value) || !is_numeric($fixed_value)) {
            return new WP_Error('commission_input', __('Enter a valid fee type and numeric amounts.', 'eldokan-admin'));
        }
        $value = (float)$value; $fixed_value = (float)$fixed_value;
        if (!is_finite($value) || !is_finite($fixed_value) || $value < 0 || $fixed_value < 0 ||
            ($type !== 'fixed' && $value > 100)) {
            return new WP_Error('commission_range', __('Fee amounts must be non-negative; percentages cannot exceed 100.', 'eldokan-admin'));
        }
        return [$type, $value, $type === 'hybrid' ? $fixed_value : 0];
    }

    private function upsert_commission_rule($scope, $seller_id, $target_type, $target_id, $type, $value, $fixed_value=0) {
        global $wpdb;
        if (!in_array($scope, ['global','seller'], true) || !in_array($target_type, ['all','category','product'], true)) {
            return new WP_Error('commission_target', __('Invalid fee scope or target.', 'eldokan-admin'));
        }
        $seller_id = $scope === 'seller' ? absint($seller_id) : 0;
        $target_id = $target_type === 'all' ? 0 : absint($target_id);
        if (($scope === 'seller' && (!$seller_id || !get_userdata($seller_id))) ||
            ($target_type === 'product' && (!$target_id || get_post_type($target_id) !== 'product')) ||
            ($target_type === 'category' && (!$target_id || !term_exists($target_id, 'product_cat')))) {
            return new WP_Error('commission_object', __('Select an existing seller, product or category.', 'eldokan-admin'));
        }
        $values = $this->commission_values($type, $value, $fixed_value);
        if (is_wp_error($values)) return $values;
        [$type, $value, $fixed_value] = $values;
        return $this->commission_write(function () use ($wpdb, $scope, $seller_id, $target_type, $target_id, $type, $value, $fixed_value) {
            $where = $wpdb->prepare("scope=%s AND seller_user_id=%d AND target_type=%s AND target_id=%d", $scope, $seller_id, $target_type, $target_id);
            $wpdb->get_results("SELECT id FROM {$this->commission_table} WHERE {$where} FOR UPDATE");
            if ($wpdb->last_error !== '') return false;
            $saved = $wpdb->insert($this->commission_table, [
                'scope'=>$scope,'seller_user_id'=>$seller_id,'target_type'=>$target_type,'target_id'=>$target_id,
                'calculation_type'=>$type,'value'=>$value,'fixed_value'=>$fixed_value,'enabled'=>1,
                'created_by'=>get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'),
            ]);
            if ($saved === false) return false;
            $id = (int)$wpdb->insert_id;
            if (!$id) return false;
            // Retire previous revisions only after the replacement was inserted.
            if ($wpdb->query($wpdb->prepare("UPDATE {$this->commission_table} SET enabled=0, updated_at=%s WHERE {$where} AND enabled=1 AND id<>%d", current_time('mysql'), $id)) === false) return false;
            return $id;
        });
    }

    private function require_commission_saved($result) {
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), '', ['response'=>500, 'back_link'=>true]);
        if ($result === false) wp_die(esc_html__('The fee operation failed. Reload and verify before retrying.', 'eldokan-admin'), '', ['response'=>500, 'back_link'=>true]);
    }

    private function commission_rule_actions($rule, $return_args=[]) {
        if (!current_user_can('eldokan_backoffice_manage_commissions')) return esc_html__('Read only', 'eldokan-admin');
        if ($rule->scope === 'global' && $rule->target_type === 'category' &&
            (string)get_term_meta($rule->target_id, '_eldokan_fee_enabled', true) === '1' && (int)$rule->enabled === 1) {
            return '<a class="button" href="'.esc_url(admin_url('admin.php?page=eldokan-admin-categories')).'">'.esc_html__('Manage category fee', 'eldokan-admin').'</a>';
        }
        $url = wp_nonce_url(add_query_arg(array_merge($return_args, [
            'action'=>'eldokan_admin_delete_commission', 'id'=>absint($rule->id),
        ]), admin_url('admin-post.php')), 'eda_del_'.absint($rule->id));
        $html = '';
        if ((int)$rule->enabled === 1) $html .= '<a class="button" href="'.esc_url(add_query_arg('disable', 1, $url)).'">'.esc_html__('Disable', 'eldokan-admin').'</a> ';
        return $html.'<a class="button eda-danger-button" href="'.esc_url($url).'">'.esc_html__('Delete', 'eldokan-admin').'</a>';
    }
}
