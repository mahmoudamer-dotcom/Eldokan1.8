<?php
if (!defined('ABSPATH')) { exit; }

/** A4 private order-bound access and initiation journal. Never a payment authority. */
final class ElDokan_Customer_API_Payment_Store {
    const VERSION = '1';
    const OPTION = 'eldokan_customer_api_payment_schema';
    public static function table() { global $wpdb; return $wpdb->prefix . 'eldokan_customer_payments'; }
    public static function bindings() { global $wpdb; return $wpdb->prefix . 'eldokan_customer_paymob_bindings'; }
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            order_id varchar(68) NOT NULL,
            woo_order_id bigint(20) unsigned NOT NULL,
            owner_hash char(64) NOT NULL,
            guest_hash char(64) DEFAULT NULL,
            gateway_id varchar(191) NOT NULL,
            generation bigint(20) unsigned NOT NULL DEFAULT 0,
            state varchar(20) NOT NULL DEFAULT 'prepared',
            redirect_cipher longtext DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (order_id),
            UNIQUE KEY woo_order (woo_order_id)
        ) ENGINE=InnoDB {$charset};");
        $bindings=self::bindings();
        dbDelta("CREATE TABLE {$bindings} (
            provider_order_id varchar(191) NOT NULL,
            order_id varchar(68) NOT NULL,
            woo_order_id bigint(20) unsigned NOT NULL,
            generation bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (provider_order_id),
            UNIQUE KEY order_generation (order_id,generation)
        ) ENGINE=InnoDB {$charset};");
        if (self::valid(false)) { update_option(self::OPTION,self::VERSION,false); }
    }
    public static function maybe_upgrade() {
        if (defined('WP_CLI') && WP_CLI) { return; }
        if ((string)get_option(self::OPTION,'') !== self::VERSION) { self::install(); }
    }
    public static function valid($version=true) {
        global $wpdb;
        if ($version && (string)get_option(self::OPTION,'') !== self::VERSION) { return false; }
        $table=self::table(); $old=$wpdb->suppress_errors(true);
        try {
            $columns=$wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
            if ($wpdb->last_error || array_diff(['order_id','woo_order_id','owner_hash','guest_hash','gateway_id','generation','state','redirect_cipher','expires_at','updated_at'],(array)$columns)) { return false; }
            $indexes=$wpdb->get_results("SHOW INDEX FROM `{$table}`",ARRAY_A);
            if ($wpdb->last_error) { return false; }
            foreach (['PRIMARY'=>'order_id','woo_order'=>'woo_order_id'] as $key=>$column) {
                $parts=array_values(array_filter((array)$indexes,static function($v)use($key){return $v['Key_name']===$key;}));
                if(count($parts)!==1 || $parts[0]['Column_name']!==$column || (int)$parts[0]['Non_unique']!==0 || !empty($parts[0]['Sub_part'])) { return false; }
            }
            $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$table),ARRAY_A);
            if($wpdb->last_error || strcasecmp((string)($status['Engine']??''),'InnoDB')!==0){return false;}
            $bindings=self::bindings();$columns=$wpdb->get_col("SHOW COLUMNS FROM `{$bindings}`");
            if($wpdb->last_error || array_diff(['provider_order_id','order_id','woo_order_id','generation'],(array)$columns)){return false;}
            $indexes=$wpdb->get_results("SHOW INDEX FROM `{$bindings}`",ARRAY_A);
            if($wpdb->last_error){return false;}
            foreach(['PRIMARY'=>['provider_order_id'],'order_generation'=>['order_id','generation']] as $name=>$required){
                $parts=array_values(array_filter((array)$indexes,static function($v)use($name){return $v['Key_name']===$name;}));
                usort($parts,static function($a,$b){return (int)$a['Seq_in_index']<=>(int)$b['Seq_in_index'];});
                if(array_column($parts,'Column_name')!==$required){return false;}foreach($parts as $part){if((int)$part['Non_unique']!==0 || !empty($part['Sub_part'])){return false;}}
            }
            $status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$bindings),ARRAY_A);
            return !$wpdb->last_error && strcasecmp((string)($status['Engine']??''),'InnoDB')===0;
        } finally { $wpdb->suppress_errors($old); }
    }
    public static function participants($tables) { $tables[]=self::table();$tables[]=self::bindings(); return $tables; }
    public static function bind($row,$generation,$provider_order_id) {
        global $wpdb;
        if(!is_scalar($provider_order_id) || !preg_match('/^[1-9][0-9]{0,39}$/D',(string)$provider_order_id)){throw new RuntimeException('Native order binding unavailable.');}
        ElDokan_Customer_API_Order_Store::execute($wpdb->prepare('INSERT INTO '.self::bindings().' (provider_order_id,order_id,woo_order_id,generation) VALUES (%s,%s,%d,%d)',(string)$provider_order_id,$row['order_id'],$row['woo_order_id'],$generation));
    }
    public static function bound($row,$provider_order_id) {
        global $wpdb;
        if(!is_scalar($provider_order_id) || !preg_match('/^[1-9][0-9]{0,39}$/D',(string)$provider_order_id)){return false;}
        $rows=self::rows($wpdb->prepare('SELECT * FROM '.self::bindings().' WHERE provider_order_id=%s AND order_id=%s AND woo_order_id=%d',(string)$provider_order_id,$row['order_id'],$row['woo_order_id']));
        return count($rows)===1;
    }
    public static function rows($sql) {
        global $wpdb; $old=$wpdb->suppress_errors(true);
        try { $rows=$wpdb->get_results($sql,ARRAY_A); if($wpdb->last_error){throw new RuntimeException('Order read unavailable.');} return (array)$rows; }
        finally { $wpdb->suppress_errors($old); }
    }
    public static function read($id) {
        global $wpdb;
        if(!self::valid()){throw new RuntimeException('Payment journal unavailable.');}
        $rows=self::rows($wpdb->prepare('SELECT * FROM '.self::table().' WHERE order_id=%s',$id));
        return $rows[0]??null;
    }
    public static function credential($row) {
        // Independently random ord plus a server-only cryptographic key; deterministic redelivery to
        // the original checkout owner after response loss. Only its digest is stored in the journal.
        return 'gok_'.hash_hmac('sha256','a4-guest|'.$row['order_id'].'|'.$row['woo_order_id'].'|'.$row['owner_hash'],wp_salt('secure_auth'));
    }
    public static function csrf($credential,$id) { return hash_hmac('sha256','a4-payment|'.$id,$credential); }
    public static function record($row,$order) {
        global $wpdb;
        if(!self::valid()){throw new RuntimeException('Payment journal unavailable.');}
        $row['woo_order_id']=$order->get_id();
        $guest=(int)$order->get_customer_id()===0 ? hash('sha256',self::credential($row)) : '';
        ElDokan_Customer_API_Order_Store::execute($wpdb->prepare('INSERT INTO '.self::table().' (order_id,woo_order_id,owner_hash,guest_hash,gateway_id,generation,state,updated_at) VALUES (%s,%d,%s,%s,%s,0,%s,%s)',
            $row['order_id'],$order->get_id(),$row['owner_hash'],$guest,$order->get_payment_method(),'prepared',gmdate('Y-m-d H:i:s')));
    }
    public static function update($id,$generation,$state,$cipher=null,$expiry=null) {
        global $wpdb;
        if(!in_array($state,['initiating','ready','ambiguous'],true)){throw new RuntimeException('Invalid journal state.');}
        // Caller owns the connection-scoped payment lock. A generation identifies a retry request,
        // never a provider intention and never an authorization credential.
        $expiry_sql=$expiry===null?'NULL':$wpdb->prepare('%s',$expiry);
        $cipher_sql=$cipher===null?'NULL':$wpdb->prepare('%s',$cipher);
        ElDokan_Customer_API_Order_Store::execute($wpdb->prepare('UPDATE '.self::table()." SET generation=%d,state=%s,redirect_cipher={$cipher_sql},expires_at={$expiry_sql},updated_at=%s WHERE order_id=%s",
            $generation,$state,gmdate('Y-m-d H:i:s'),$id));
        $actual=self::read($id);
        if(!$actual || (int)$actual['generation']!==$generation || $actual['state']!==$state || $actual['redirect_cipher']!==$cipher || $actual['expires_at']!==$expiry){throw new RuntimeException('Journal acknowledgement unavailable.');}
    }
    public static function seal($url,$id) {
        if(!function_exists('openssl_encrypt')){throw new RuntimeException('Secure redirect storage unavailable.');}
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($url,'aes-256-gcm',hash_hmac('sha256','a4-redirect',wp_salt('secure_auth'),true),OPENSSL_RAW_DATA,$iv,$tag,$id);
        if($cipher===false){throw new RuntimeException('Secure redirect storage unavailable.');}
        return base64_encode($iv.$tag.$cipher);
    }
    public static function unseal($cipher,$id) {
        $raw=base64_decode((string)$cipher,true);
        if($raw===false || strlen($raw)<29 || !function_exists('openssl_decrypt')){return null;}
        $url=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash_hmac('sha256','a4-redirect',wp_salt('secure_auth'),true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$id);
        return $url===false?null:$url;
    }
}
