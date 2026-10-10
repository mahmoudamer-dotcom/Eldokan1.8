<?php
if (!defined('ABSPATH')) { exit; }

/** Trusted operator service. No creation, payment, stock mutation or lifecycle dispatch. */
final class ElDokan_Customer_API_Checkout_Recovery {
    public static function digest($value) {
        $sort = static function ($v) use (&$sort) {
            if (is_array($v)) { if (array_keys($v) !== range(0,count($v)-1)) { ksort($v); } foreach ($v as $k=>$x) { $v[$k]=$sort($x); } }
            return $v;
        };
        return hash('sha256',wp_json_encode($sort($value)));
    }
    private static function metadata($object) {
        $values = [];
        foreach ($object->get_meta_data() as $meta) {
            $data = $meta->get_data();
            // IDs are storage identities, not business values. Preserve every key/value, including fee snapshots.
            $values[] = ['key'=>$data['key'],'value'=>$data['value']];
        }
        usort($values, static function ($a,$b) { return strcmp(self::digest($a),self::digest($b)); });
        return $values;
    }
    private static function snapshot($order) {
        $fields = ['customer_id','currency','payment_method','customer_note','total','total_tax','discount_total','discount_tax','shipping_total','shipping_tax','cart_tax'];
        $values = [];
        foreach ($fields as $field) { $values[$field] = $order->{'get_'.$field}('edit'); }
        $values['billing'] = $order->get_address('billing');
        $values['shipping'] = $order->get_address('shipping');
        $values['meta'] = self::metadata($order);
        $values['status'] = $order->get_status('edit');
        $values['created_at'] = $order->get_date_created()->date('c');
        $values['stock_reduced'] = (bool)$order->get_data_store()->get_stock_reduced($order->get_id());
        $values['items'] = [];
        foreach (['line_item','fee','shipping','tax','coupon'] as $type) {
            foreach ($order->get_items($type) as $id=>$item) {
                $data = $item->get_data();
                unset($data['id'],$data['order_id'],$data['meta_data']);
                $values['items'][(int)$id] = ['type'=>$type,'data'=>$data,'meta'=>self::metadata($item)];
            }
        }
        return $values;
    }
    /** Read the authoritative physical rows too: proof must never rely on an object/persistent-cache hit. */
    private static function storage($order_id,$lock=false) {
        global $wpdb;
        $p=$wpdb->prefix; $id=(int)$order_id; $tail=$lock ? ' FOR UPDATE' : '';
        $spec = ElDokan_Customer_API_Order_Store::hpos()
            ? ['wc_orders'=>'id','wc_order_addresses'=>'order_id','wc_order_operational_data'=>'order_id','wc_orders_meta'=>'order_id']
            : ['posts'=>'ID','postmeta'=>'post_id'];
        if (ElDokan_Customer_API_Order_Store::synchronized_orders()) {
            $spec += ['posts'=>'ID','postmeta'=>'post_id','wc_orders'=>'id','wc_order_addresses'=>'order_id','wc_order_operational_data'=>'order_id','wc_orders_meta'=>'order_id'];
        }
        $spec['woocommerce_order_items']='order_id';
        $spec['wc_reserved_stock']='order_id';
        $data=[];
        foreach ($spec as $table=>$column) { $data[$table]=self::rows("SELECT * FROM `{$p}{$table}` WHERE `{$column}`={$id}{$tail}"); }
        $item_ids=array_map(static function($v){return (int)$v['order_item_id'];},$data['woocommerce_order_items']);
        $data['woocommerce_order_itemmeta']=$item_ids ? self::rows("SELECT * FROM `{$p}woocommerce_order_itemmeta` WHERE order_item_id IN (".implode(',',$item_ids)."){$tail}") : [];
        // Row order has no business meaning, unlike membership arrays. Sort physical rows by canonical digest.
        foreach ($data as &$rows) { usort($rows,static function($a,$b){return strcmp(self::digest($a),self::digest($b));}); } unset($rows);
        return $data;
    }
    /** Captured only after native COD succeeded, stock lifecycle ran and prepared invariants were checked. */
    public static function evidence($row,$order,$result) {
        if (!ElDokan_Customer_API_Orders::safe_result($result)) { throw new RuntimeException('Unsafe recovery response.'); }
        $snapshot = self::snapshot($order);
        if ($result['payment_method']['id'] === 'cod' && get_option('woocommerce_manage_stock') === 'yes') {
            $managed=false;
            foreach ($order->get_items() as $item) {
                $product=$item->get_product();
                if ($product && $product->managing_stock()) {
                    $managed=true;
                    if ((float)$item->get_meta('_reduced_stock',true)!==(float)$item->get_quantity()) { throw new RuntimeException('Native stock lifecycle evidence missing.'); }
                }
            }
            if ($managed && !$snapshot['stock_reduced']) { throw new RuntimeException('Native stock reduction flag missing.'); }
        }
        $groups = [];
        foreach ($order->get_items() as $id=>$item) {
            $seller = $item->get_meta('_eldokan_customer_seller_snapshot',true);
            $sid = (int)$seller['user_id'];
            if (!isset($groups[$sid])) { $groups[$sid] = ['seller'=>$seller,'ids'=>[]]; }
            $groups[$sid]['ids'][] = (int)$id;
        }
        $proof = ['version'=>1,'attempt_id'=>$row['attempt_id'],'order_id'=>$row['order_id'],
            'woo_order_id'=>(int)$order->get_id(),'owner_hash'=>$row['owner_hash'],'cart_owner_hash'=>$row['cart_owner_hash'],
            'request_hash'=>$row['request_hash'],'material_digest'=>self::digest(json_decode($row['material_json'],true)),
            'snapshot'=>$snapshot,'snapshot_digest'=>self::digest($snapshot),'storage_digest'=>self::digest(self::storage($order->get_id())),'groups'=>$groups,'result'=>$result];
        $proof['signature']=hash_hmac('sha256',self::digest($proof),wp_salt('secure_auth'));
        return $proof;
    }
    private static function report($id,$state,$reason,$row=null) {
        $report = ['checkout_attempt_id'=>$id,'state'=>$state,'reason'=>$reason,'changed'=>false];
        if ($row) { $report['ledger_state']=$row['state']; $report['order_id']=$row['order_id']; }
        return $report;
    }
    /** Strictly read-only. Optional owner binding is for a future trusted caller's scoping, never a REST route. */
    public static function inspect($id,$binding=null) {
        global $wpdb;
        if (!is_string($id) || !preg_match('/^chk_[a-f0-9]{64}$/D',$id)) { return new WP_Error('invalid_checkout_attempt_id','Invalid checkout attempt ID.',['status'=>400]); }
        if (!ElDokan_Customer_API_Order_Store::schema_valid()) { return ElDokan_Customer_API_Order_Store::unavailable(); }
        try {
            $row = self::row($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE attempt_id=%s',$id));
            if (!$row || ($binding !== null && (!is_string($binding) || !hash_equals($row['owner_hash'],$binding)))) { return new WP_Error('checkout_attempt_not_found','Checkout attempt not found.',['status'=>404]); }
            $validated = ElDokan_Customer_API_Order_Store::read($id,$row['owner_hash']);
            if (is_wp_error($validated)) { return self::report($id,'conflict','invalid_ledger',$row); }
            $ids = self::candidates($row);
            if (count($ids)>1) { return self::report($id,'conflict','multiple_order_evidence',$row); }
            if (!$ids) { return self::report($id,'unresolved','no_committed_order',$row); }
            if (!$row['woo_order_id'] || (int)$row['woo_order_id'] !== $ids[0]) { return self::report($id,'conflict','mapping_conflict',$row); }
            $other = self::rows($wpdb->prepare('SELECT attempt_id FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE (woo_order_id=%d OR order_id=%s) AND attempt_id<>%s',$ids[0],$row['order_id'],$id));
            if ($other) { return self::report($id,'conflict','ledger_mapping_conflict',$row); }
            if ($row['state'] === 'completed') {
                if (is_wp_error(ElDokan_Customer_API_Orders::recover($row))) { return self::report($id,'conflict','invalid_completed_result',$row); }
                $order=wc_get_order($ids[0]);
                if (!$order || $order->get_meta('_eldokan_customer_checkout_attempt',true)!==$id || $order->get_meta('_eldokan_customer_public_order_id',true)!==$row['order_id']) { return self::report($id,'conflict','completed_mapping_mismatch',$row); }
                // A completed receipt survives legitimate later native status/stock/fulfillment transitions.
                return self::report($id,'completed','already_completed',$row);
            }
            if (!in_array($row['state'],['processing','recovery_required'],true)) { return self::report($id,'conflict','unexpected_prepared_order',$row); }
            $proof = json_decode((string)($row['recovery_json'] ?? ''),true);
            if (!$proof) { return self::report($id,'unresolved','insufficient_durable_evidence',$row); }
            $order = wc_get_order($ids[0]);
            if (!$order || !self::valid($row,$proof,$order)) { return self::report($id,'conflict','order_invariants_mismatch',$row); }
            return self::report($id,'recoverable','exact_committed_order_verified',$row);
        } catch (Throwable $error) { return self::report($id,'unresolved','inspection_unavailable'); }
    }
    private static function rows($sql) {
        global $wpdb;
        $old = $wpdb->suppress_errors(true);
        try { $rows = $wpdb->get_results($sql,ARRAY_A); if ($wpdb->last_error) { throw new RuntimeException('Recovery read failed.'); } return (array)$rows; }
        finally { $wpdb->suppress_errors($old); }
    }
    private static function row($sql) { $rows = self::rows($sql); return $rows[0] ?? null; }
    private static function candidates($row) {
        global $wpdb;
        // Exact indexed marker lookup in the authoritative store, including public-ID conflicts; no legacy migration.
        $table = $wpdb->prefix . (ElDokan_Customer_API_Order_Store::hpos() ? 'wc_orders_meta' : 'postmeta');
        $column = ElDokan_Customer_API_Order_Store::hpos() ? 'order_id' : 'post_id';
        $rows = self::rows($wpdb->prepare("SELECT DISTINCT {$column} AS id FROM `{$table}` WHERE (meta_key=%s AND meta_value=%s) OR (meta_key=%s AND meta_value=%s)", '_eldokan_customer_checkout_attempt',$row['attempt_id'],'_eldokan_customer_public_order_id',$row['order_id']));
        $ids = array_map(static function ($v) { return (int)$v['id']; },$rows);
        if ((int)$row['woo_order_id']) { $ids[]=(int)$row['woo_order_id']; }
        return array_values(array_unique($ids));
    }
    private static function valid($row,$proof,$order) {
        global $wpdb;
        $keys = ['version','attempt_id','order_id','woo_order_id','owner_hash','cart_owner_hash','request_hash','material_digest','snapshot','snapshot_digest','storage_digest','groups','result','signature'];
        if (!is_array($proof) || count($proof)!==count($keys) || array_diff($keys,array_keys($proof)) || $proof['version']!==1) { return false; }
        $signed=$proof;unset($signed['signature']);
        if (!is_string($proof['signature']) || !hash_equals(hash_hmac('sha256',self::digest($signed),wp_salt('secure_auth')),$proof['signature'])) { return false; }
        foreach (['attempt_id','order_id','owner_hash','cart_owner_hash','request_hash'] as $key) { if ($proof[$key] !== $row[$key]) { return false; } }
        if ($proof['woo_order_id'] !== (int)$row['woo_order_id'] || $proof['material_digest'] !== self::digest(json_decode($row['material_json'],true))
            || !ElDokan_Customer_API_Orders::safe_result($proof['result']) || $proof['result']['order_id']!==$row['order_id'] || $proof['result']['checkout_attempt_id']!==$row['attempt_id']
            || $order->get_meta('_eldokan_customer_flow',true)!=='phase2c-a3'
            || $order->get_meta('_eldokan_customer_checkout_attempt',true)!==$row['attempt_id']
            || $order->get_meta('_eldokan_customer_public_order_id',true)!==$row['order_id']
            || $order->get_meta('_eldokan_customer_owner_binding',true)!==$row['owner_hash']
            || $order->get_meta('_eldokan_customer_cart_binding',true)!==$row['cart_owner_hash']
            || ($proof['result']['payment_method']['id']==='cod' ? $order->get_payment_method('edit')!=='cod' : $order->get_meta('_eldokan_customer_payment_concept',true)!=='paymob') || $order->get_status('edit')!==$proof['result']['status']
            || $proof['snapshot_digest']!==self::digest($proof['snapshot']) || $proof['snapshot_digest']!==self::digest(self::snapshot($order))
            || $proof['storage_digest']!==self::digest(self::storage($order->get_id()))) { return false; }
        if (self::digest($proof['result']) !== self::digest(ElDokan_Customer_API_Orders::result($row,$order,['quote'=>['selected_shipping_method'=>$proof['result']['shipping']]]))) { return false; }
        $groups=[]; $expected_receipts=[];
        foreach ($order->get_items() as $id=>$item) {
            $seller = $item->get_meta('_eldokan_customer_seller_snapshot',true);
            $key = $item->get_meta('_eldokan_customer_cart_item_id',true);
            if (!is_array($seller) || !isset($seller['user_id']) || (int)$item->get_meta('_eldokan_seller_user_id',true)!==(int)$seller['user_id']
                || !preg_match('/^cit_[a-f0-9]{32}$/D',(string)$key) || isset($expected_receipts[$key])) { return false; }
            $sid=(int)$seller['user_id'];
            if (!isset($groups[$sid])) { $groups[$sid]=['seller'=>$seller,'ids'=>[]]; }
            if ($groups[$sid]['seller']!==$seller) { return false; }
            $groups[$sid]['ids'][]=(int)$id; $expected_receipts[$key]=$row['cart_owner_hash'];
        }
        if (!$groups || self::digest($groups)!==self::digest($proof['groups'])) { return false; }
        $fulfillments = self::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('fulfillments').' WHERE woo_order_id=%d',$order->get_id()));
        if (count($fulfillments)!==count($groups)) { return false; }
        foreach ($fulfillments as $f) {
            $sid=(int)$f['seller_user_id'];
            if (!isset($groups[$sid]) || $f['state']!=='pending' || (int)$f['revision']!==0
                || self::digest(json_decode($f['seller_json'],true))!==self::digest($groups[$sid]['seller'])
                || self::digest(json_decode($f['line_ids_json'],true))!==self::digest($groups[$sid]['ids'])) { return false; }
        }
        $receipts = self::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('receipts').' WHERE attempt_id=%s',$row['attempt_id']));
        if (count($receipts)!==count($expected_receipts)) { return false; }
        foreach ($receipts as $r) { if (($expected_receipts[$r['item_id']] ?? null)!==$r['cart_owner_hash']) { return false; } unset($expected_receipts[$r['item_id']]); }
        return !$expected_receipts;
    }
    public static function reconcile($id,$binding=null) {
        global $wpdb;
        $report = self::inspect($id,$binding);
        if (is_wp_error($report) || $report['state']!=='recoverable') { return $report; }
        try { $row = self::row($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE attempt_id=%s',$id)); }
        catch (Throwable $error) { return self::report($id,'unresolved','inspection_unavailable'); }
        if (!$row) { return self::report($id,'unresolved','inspection_unavailable'); }
        // Same lock order as checkout: Cart then global placement; serialize against in-flight placement.
        foreach (['cart|'.$row['cart_owner_hash'],'placement'] as $identity) {
            $locked = ElDokan_Customer_API_Order_Store::lock($identity);
            if (is_wp_error($locked)) { return $locked; }
        }
        $started=false;
        try {
            ElDokan_Customer_API_Order_Store::begin(); $started=true;
            $current=self::row($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE attempt_id=%s FOR UPDATE',$id));
            self::storage((int)$current['woo_order_id'],true);
            self::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('fulfillments').' WHERE woo_order_id=%d FOR UPDATE',$current['woo_order_id']));
            self::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('receipts').' WHERE attempt_id=%s FOR UPDATE',$id));
            $report=self::inspect($id,$binding);
            if (is_wp_error($report) || $report['state']!=='recoverable') { ElDokan_Customer_API_Order_Store::finish(false); $started=false; return $report; }
            $row=self::row($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE attempt_id=%s',$id));
            $proof=json_decode($row['recovery_json'],true);
            ElDokan_Customer_API_Order_Store::complete($id,$proof['result']);
            ElDokan_Customer_API_Order_Store::finish(true); $started=false;
            $report['state']='completed'; $report['reason']='ledger_reconciled'; $report['ledger_state']='completed'; $report['changed']=true;
            return $report;
        } catch (Throwable $error) {
            if ($started) { try { ElDokan_Customer_API_Order_Store::finish(false); } catch (Throwable $ignored) {} }
            // Also handle a lost COMMIT acknowledgement; never infer success from an in-memory Order.
            $after=self::inspect($id,$binding);
            return !is_wp_error($after) && $after['state']==='completed' ? $after : self::report($id,'unresolved','reconciliation_unavailable');
        }
    }
}
