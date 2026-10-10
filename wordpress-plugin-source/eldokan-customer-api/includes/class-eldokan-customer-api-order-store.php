<?php
if (!defined('ABSPATH')) { exit; }

/** A3 durable placement ledger. No historical Woo Orders are scanned or rewritten. */
final class ElDokan_Customer_API_Order_Store {
    const SCHEMA_VERSION = '2';
    const SCHEMA_OPTION = 'eldokan_customer_api_order_schema';
    private static $locks = [];
    private static $shutdown = false;
    private static $placement_locked = false;
    private static $engines = [];
    private static $transaction = false;

    public static function table($kind) {
        global $wpdb;
        $names = ['attempts' => 'eldokan_checkout_attempts', 'fulfillments' => 'eldokan_seller_fulfillments', 'receipts' => 'eldokan_checkout_receipts'];
        if (!isset($names[$kind])) { throw new RuntimeException('Unknown placement table.'); }
        return $wpdb->prefix . $names[$kind];
    }
    public static function unavailable($code = 'order_storage_unavailable') {
        return new WP_Error($code, 'Order placement is temporarily unavailable. Retry the same checkout attempt.', ['status' => 503]);
    }
    public static function maybe_upgrade() {
        // Operator commands must not implicitly migrate storage during an inspection/reconciliation boot.
        if (defined('WP_CLI') && WP_CLI) { return; }
        if ((string) get_option(self::SCHEMA_OPTION, '') !== self::SCHEMA_VERSION) { self::install(); }
    }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $a = self::table('attempts'); $f = self::table('fulfillments'); $r = self::table('receipts');
        dbDelta("CREATE TABLE {$a} (
            attempt_id varchar(68) NOT NULL,
            owner_hash char(64) NOT NULL,
            cart_owner_hash char(64) NOT NULL,
            request_hash char(64) NOT NULL,
            material_json longtext NOT NULL,
            state varchar(20) NOT NULL,
            order_id varchar(68) NOT NULL,
            woo_order_id bigint(20) unsigned DEFAULT NULL,
            response_json longtext DEFAULT NULL,
            recovery_json longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (attempt_id),
            UNIQUE KEY public_order (order_id),
            UNIQUE KEY woo_order (woo_order_id),
            KEY owner_state (owner_hash,state)
        ) ENGINE=InnoDB {$charset};");
        dbDelta("CREATE TABLE {$f} (
            woo_order_id bigint(20) unsigned NOT NULL,
            seller_user_id bigint(20) unsigned NOT NULL,
            seller_json longtext NOT NULL,
            line_ids_json longtext NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            revision bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (woo_order_id,seller_user_id)
        ) ENGINE=InnoDB {$charset};");
        dbDelta("CREATE TABLE {$r} (
            cart_owner_hash char(64) NOT NULL,
            item_id varchar(36) NOT NULL,
            attempt_id varchar(68) NOT NULL,
            PRIMARY KEY  (cart_owner_hash,item_id),
            KEY attempt (attempt_id)
        ) ENGINE=InnoDB {$charset};");
        if (self::schema_valid(false)) { update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false); }
    }
    public static function schema_valid($version = true) {
        global $wpdb;
        if ($version && (string) get_option(self::SCHEMA_OPTION, '') !== self::SCHEMA_VERSION) { return false; }
        $spec = [
            'attempts' => [['attempt_id','owner_hash','cart_owner_hash','request_hash','material_json','state','order_id','woo_order_id','response_json','recovery_json','created_at','updated_at'], ['PRIMARY'=>['attempt_id'],'public_order'=>['order_id'],'woo_order'=>['woo_order_id']]],
            'fulfillments' => [['woo_order_id','seller_user_id','seller_json','line_ids_json','state','revision','created_at','updated_at'], ['PRIMARY'=>['woo_order_id','seller_user_id']]],
            'receipts' => [['cart_owner_hash','item_id','attempt_id'], ['PRIMARY'=>['cart_owner_hash','item_id']]],
        ];
        $old = $wpdb->suppress_errors(true);
        try {
            foreach ($spec as $kind => [$required,$keys]) {
                $table = self::table($kind);
                $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
                if ($wpdb->last_error || array_diff($required, (array) $columns)) { return false; }
                $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
                if ($wpdb->last_error) { return false; }
                foreach ($keys as $name => $fields) {
                    $parts = array_values(array_filter((array) $indexes, static function ($v) use ($name) { return ($v['Key_name'] ?? '') === $name; }));
                    usort($parts, static function ($a,$b) { return (int)$a['Seq_in_index'] <=> (int)$b['Seq_in_index']; });
                    if (array_column($parts,'Column_name') !== $fields) { return false; }
                    foreach ($parts as $part) { if ((int)$part['Non_unique'] !== 0 || !empty($part['Sub_part'])) { return false; } }
                }
                $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table), ARRAY_A);
                if ($wpdb->last_error || strcasecmp((string)($status['Engine'] ?? ''), 'InnoDB') !== 0) { return false; }
            }
            return true;
        } finally { $wpdb->suppress_errors($old); }
    }
    /** MySQL connection-scoped locks; bounded wait, held until the HTTP request ends. */
    public static function lock($identity) {
        global $wpdb;
        if (!self::schema_valid()) { return self::unavailable(); }
        $name = 'edca3_' . substr(hash('sha256', $wpdb->prefix . '|' . $identity), 0, 56);
        if (isset(self::$locks[$name])) { return true; }
        $old = $wpdb->suppress_errors(true);
        try { $ok = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $name)); }
        finally { $wpdb->suppress_errors($old); }
        if ((string) $ok !== '1') { return self::unavailable('checkout_busy'); }
        self::$locks[$name] = true;
        if ($identity === 'placement') {
            self::$placement_locked = true;
            add_filter('pre_http_request', [__CLASS__, 'block_network'], PHP_INT_MAX, 3);
        }
        if (!self::$shutdown) { register_shutdown_function([__CLASS__, 'release_locks']); self::$shutdown = true; }
        return true;
    }
    public static function release_locks() {
        global $wpdb;
        foreach (array_keys(self::$locks) as $name) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
            if (!$wpdb->last_error) { unset(self::$locks[$name]); }
        }
        $placement = 'edca3_' . substr(hash('sha256', $wpdb->prefix . '|placement'), 0, 56);
        self::$placement_locked = isset(self::$locks[$placement]);
        if (!self::$placement_locked) { remove_filter('pre_http_request', [__CLASS__, 'block_network'], PHP_INT_MAX); }
    }
    /** Mandatory entry guard for future provider adapters, including adapters outside WP HTTP. */
    public static function assert_external_work_unlocked() {
        if (self::$placement_locked || self::$transaction) { throw new RuntimeException('External work forbidden while placement lock is held.'); }
    }
    public static function block_network($preempt, $args, $url) {
        return self::$placement_locked || self::$transaction ? new WP_Error('checkout_external_work_locked', 'External work forbidden during serialized placement.') : $preempt;
    }
    public static function hpos() {
        return class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    public static function synchronized_orders() {
        return get_option('woocommerce_custom_orders_table_data_sync_enabled', 'no') === 'yes';
    }
    public static function participants() {
        global $wpdb;
        // Product CPT/stock and order items remain shared in both storage modes.
        $suffixes = ['posts','postmeta','comments','commentmeta','terms','term_taxonomy','term_relationships','termmeta','woocommerce_order_items','woocommerce_order_itemmeta','wc_product_meta_lookup','wc_reserved_stock'];
        if (self::hpos() || self::synchronized_orders()) { $suffixes = array_merge($suffixes, ['wc_orders','wc_order_addresses','wc_order_operational_data','wc_orders_meta']); }
        $tables = array_map(static function ($s) use ($wpdb) { return $wpdb->prefix . $s; }, $suffixes);
        foreach (['attempts','fulfillments','receipts'] as $kind) { $tables[] = self::table($kind); }
        // Extensions may declare their write participants before placement; never convert their engines.
        $tables = apply_filters('eldokan_checkout_transaction_tables', $tables);
        if (!is_array($tables) || !$tables) { throw new RuntimeException('Invalid transaction participant configuration.'); }
        foreach ($tables as $table) { if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException('Invalid participant table.'); } }
        // A filter can add required participants but cannot remove the core guarantees.
        return array_values(array_unique(array_merge($tables, array_map(static function ($s) use ($wpdb) { return $wpdb->prefix . $s; }, $suffixes), [self::table('attempts'),self::table('fulfillments'),self::table('receipts')])));
    }
    public static function execute($sql) {
        global $wpdb;
        $old = $wpdb->suppress_errors(true);
        try {
            $r = $wpdb->query($sql);
            if ($r === false || $wpdb->last_error) { throw new RuntimeException('Placement persistence failed.'); }
            return $r;
        } finally { $wpdb->suppress_errors($old); }
    }
    /** Only required participants gate admission. Actual extension writes are checked before SQL executes. */
    public static function check_transaction_participants() {
        global $wpdb;
        if (!self::schema_valid() || (string)$wpdb->get_var('SELECT @@autocommit') !== '1') { throw new RuntimeException('Transactional storage required.'); }
        $tables = $wpdb->get_results('SHOW TABLE STATUS', ARRAY_A);
        if (!$tables || $wpdb->last_error) { throw new RuntimeException('Transactional storage unavailable.'); }
        self::$engines = [];
        foreach ($tables as $table) { self::$engines[$table['Name']] = strtolower((string)$table['Engine']); }
        foreach (self::participants() as $table) { if ((self::$engines[$table] ?? '') !== 'innodb') { throw new RuntimeException('Required transaction participant unavailable.'); } }
    }
    public static function begin() {
        self::check_transaction_participants();
        self::execute('START TRANSACTION');
        self::$transaction = true;
        add_filter('query', [__CLASS__, 'guard_query'], PHP_INT_MAX);
    }
    /** Fail closed on implicit commits, nested transactions and unsupported multi-table writes. */
    public static function guard_query($sql) {
        if (!self::$transaction) { return $sql; }
        $text = trim($sql);
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $text)) { return $sql; }
        if (preg_match('/^(?:INSERT(?:\s+(?:IGNORE|LOW_PRIORITY|HIGH_PRIORITY|DELAYED))?|REPLACE)\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*\(/i', $text, $m)
            || preg_match('/^UPDATE\s+`?([A-Za-z0-9_]+)`?\s+SET\s/i', $text, $m)
            || preg_match('/^DELETE\s+FROM\s+`?([A-Za-z0-9_]+)`?\s+(?:WHERE|LIMIT|ORDER)\b/i', $text, $m)) {
            if ((self::$engines[$m[1]] ?? '') !== 'innodb') { throw new RuntimeException('Nontransactional or undeclared write target.'); }
            return $sql;
        }
        throw new RuntimeException('Unsupported SQL inside checkout transaction.');
    }
    public static function finish($commit) {
        // Only the owning service may end this transaction; hook SQL cannot implicitly commit it.
        remove_filter('query', [__CLASS__, 'guard_query'], PHP_INT_MAX);
        self::execute($commit ? 'COMMIT' : 'ROLLBACK');
        self::$transaction = false;
    }
    public static function save_recovery($id, $evidence) {
        global $wpdb;
        $n = self::execute($wpdb->prepare('UPDATE '.self::table('attempts').' SET recovery_json=%s WHERE attempt_id=%s AND state=%s AND woo_order_id IS NOT NULL', wp_json_encode($evidence), $id, 'processing'));
        if ($n !== 1) { throw new RuntimeException('Recovery evidence persistence failed.'); }
    }
    public static function create($owner, $request_hash, $material) {
        global $wpdb;
        $id = 'chk_' . bin2hex(random_bytes(32)); $ord = 'ord_' . bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        self::execute($wpdb->prepare('INSERT INTO ' . self::table('attempts') . ' (attempt_id,owner_hash,cart_owner_hash,request_hash,material_json,state,order_id,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)', $id,$owner['binding'],$owner['cart_key'],$request_hash,wp_json_encode($material),'prepared',$ord,$now,$now));
        return $id;
    }
    public static function read($id, $binding) {
        global $wpdb;
        if (!self::schema_valid()) { return self::unavailable(); }
        $old = $wpdb->suppress_errors(true);
        try { $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('attempts').' WHERE attempt_id=%s AND owner_hash=%s', $id,$binding), ARRAY_A); $error = $wpdb->last_error; }
        finally { $wpdb->suppress_errors($old); }
        if ($error) { return self::unavailable(); }
        if (!$row) { return new WP_Error('checkout_attempt_not_found','Checkout attempt not found.',['status'=>404]); }
        if (!preg_match('/^ord_[a-f0-9]{64}$/D',$row['order_id']) || !preg_match('/^[a-f0-9]{64}$/D',$row['request_hash']) || !is_array(json_decode($row['material_json'],true)) || !in_array($row['state'],['prepared','processing','completed','recovery_required'],true)) { return self::unavailable(); }
        $material = json_decode($row['material_json'],true);
        if (count($material) !== 9 || array_diff(['cart','address','seller','price','stock','shipping','totals','fees','discounts'],array_keys($material))) { return self::unavailable(); }
        foreach ($material as $digest) { if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D',$digest)) { return self::unavailable(); } }
        if ($row['state'] === 'completed' && (!(int)$row['woo_order_id'] || !is_array(json_decode((string)$row['response_json'],true)))) { return self::unavailable(); }
        return $row;
    }
    public static function processing($id) {
        global $wpdb;
        $n = self::execute($wpdb->prepare('UPDATE '.self::table('attempts').' SET state=%s,updated_at=%s WHERE attempt_id=%s AND state=%s','processing',gmdate('Y-m-d H:i:s'),$id,'prepared'));
        if ($n !== 1) { throw new RuntimeException('Attempt is already processing.'); }
    }
    public static function record_order($row, $order) {
        global $wpdb;
        $n = self::execute($wpdb->prepare('UPDATE '.self::table('attempts').' SET woo_order_id=%d,updated_at=%s WHERE attempt_id=%s AND state=%s AND woo_order_id IS NULL',$order->get_id(),gmdate('Y-m-d H:i:s'),$row['attempt_id'],'processing'));
        if ($n !== 1) { throw new RuntimeException('Order mapping failed.'); }
        $groups = [];
        foreach ($order->get_items() as $line_id => $item) {
            $snapshot = $item->get_meta('_eldokan_customer_seller_snapshot',true);
            if (!is_array($snapshot) || empty($snapshot['user_id'])) { throw new RuntimeException('Seller snapshot missing.'); }
            $seller = (int)$snapshot['user_id'];
            if (!isset($groups[$seller])) { $groups[$seller] = ['snapshot'=>$snapshot,'ids'=>[]]; }
            $groups[$seller]['ids'][] = (int)$line_id;
            $item_id = $item->get_meta('_eldokan_customer_cart_item_id',true);
            if (!preg_match('/^cit_[a-f0-9]{32}$/D',(string)$item_id)) { throw new RuntimeException('Cart membership missing.'); }
            self::execute($wpdb->prepare('INSERT INTO '.self::table('receipts').' (cart_owner_hash,item_id,attempt_id) VALUES (%s,%s,%s)',$row['cart_owner_hash'],$item_id,$row['attempt_id']));
        }
        if (!$groups) { throw new RuntimeException('Order lines missing.'); }
        $now = gmdate('Y-m-d H:i:s');
        foreach ($groups as $seller => $group) {
            self::execute($wpdb->prepare('INSERT INTO '.self::table('fulfillments').' (woo_order_id,seller_user_id,seller_json,line_ids_json,state,revision,created_at,updated_at) VALUES (%d,%d,%s,%s,%s,0,%s,%s)',$order->get_id(),$seller,wp_json_encode($group['snapshot']),wp_json_encode($group['ids']),'pending',$now,$now));
        }
    }
    public static function complete($id, $result) {
        global $wpdb;
        $n = self::execute($wpdb->prepare('UPDATE '.self::table('attempts').' SET state=%s,response_json=%s,updated_at=%s WHERE attempt_id=%s AND state IN (%s,%s) AND woo_order_id IS NOT NULL','completed',wp_json_encode($result),gmdate('Y-m-d H:i:s'),$id,'processing','recovery_required'));
        if ($n !== 1) { throw new RuntimeException('Result persistence failed.'); }
    }
    public static function unconsumed($owner_hash, $entries) {
        global $wpdb;
        if (!self::schema_valid()) { throw new RuntimeException('Order storage unavailable.'); }
        $old = $wpdb->suppress_errors(true);
        try { $ids = $wpdb->get_col($wpdb->prepare('SELECT item_id FROM '.self::table('receipts').' WHERE cart_owner_hash=%s',$owner_hash)); $error = $wpdb->last_error; }
        finally { $wpdb->suppress_errors($old); }
        if ($error) { throw new RuntimeException('Cart receipts unavailable.'); }
        return array_values(array_filter($entries, static function ($entry) use ($ids) { return !in_array($entry['item_id'] ?? '', $ids,true); }));
    }
}
