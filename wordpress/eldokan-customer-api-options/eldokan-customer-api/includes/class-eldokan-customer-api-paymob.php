<?php
if (!defined('ABSPATH')) { exit; }

/** Only calls the installed gateway; Woo plus its authenticated POST callback own payment state. */
final class ElDokan_Customer_API_Paymob {
    /** Public concepts map to explicitly configured native single-integration gateways. */
    public static function checkout_options() {
        $configured = get_option('eldokan_checkout_paymob_options', []);
        if (!is_array($configured)) { return []; }
        $result = [];
        foreach (['card' => 'Card payment', 'bank_installments' => 'Bank installments'] as $option => $name) {
            $id = $configured[$option] ?? null;
            if (!is_string($id) || $id === '' || $id === 'paymob' || $id === 'paymob-pixel') { continue; }
            $gateway = self::gateway($id);
            if (!is_wp_error($gateway)) { $result[] = ['id' => $option, 'name' => $name, 'available' => true]; }
        }
        return $result;
    }
    public static function checkout_gateway($option) {
        if ($option === null) { return self::gateway(); }
        if (!in_array($option, ['card', 'bank_installments'], true)) { return self::issue('payment_option_invalid'); }
        $configured = get_option('eldokan_checkout_paymob_options', []);
        $id = is_array($configured) ? ($configured[$option] ?? null) : null;
        if (!is_string($id) || $id === '' || in_array($id, ['paymob', 'paymob-pixel'], true)) { return self::issue('payment_option_unavailable'); }
        return self::gateway($id);
    }
    public static function issue($code='paymob_unavailable',$status=422) {
        return new WP_Error($code,'Payment is unavailable for this order.',['status'=>$status,'issues'=>[['code'=>$code,'message'=>'Payment is unavailable for this order.']]]);
    }
    public static function frontend_origin() {
        $origin=get_option('eldokan_customer_api_frontend_origin','');
        if(!is_string($origin) || strlen($origin)>2048 || !filter_var($origin,FILTER_VALIDATE_URL)){return null;}
        $p=wp_parse_url($origin);
        if(!$p || ($p['scheme']??'')!=='https' || empty($p['host']) || isset($p['user'],$p['pass']) || isset($p['user']) || isset($p['query']) || isset($p['fragment']) || !in_array($p['path']??'',['','/'],true)){return null;}
        return rtrim($origin,'/');
    }
    public static function allowed_origins($origins) {
        $origin=self::frontend_origin();if($origin){$origins=(array)$origins;$origins[]=$origin;}
        return array_values(array_unique((array)$origins));
    }
    private static function merchant_order($merchant) {
        global $wpdb;
        $id=Paymob::getIntentionId($merchant);
        // Read the native alias mapping too, so an external Pixel alias cannot bypass A4 guards.
        // A4 never creates a Pixel session or writes its table.
        if(strpos($id,'pixel')!==false){$id=$wpdb->get_var($wpdb->prepare('SELECT merchant_order_id FROM '.$wpdb->prefix.'paymob_pixel_intentions WHERE pixel_identifier=%s',$merchant));}
        return $id?wc_get_order($id):false;
    }
    public static function gateway($id=null) {
        if(!defined('PAYMOB_VERSION') || PAYMOB_VERSION!=='4.1.15' || !class_exists('Paymob_Payment') || !class_exists('Paymob') || !self::frontend_origin() || !function_exists('openssl_encrypt') || get_option('eldokan_customer_api_paymob_callback_profile','')!=='accept_transaction'){return self::issue();}
        $id=$id??get_option('eldokan_customer_api_paymob_gateway','paymob');
        if(!is_string($id) || $id==='paymob-pixel'){return self::issue('paymob_hosted_required');}
        $gateways=WC()->payment_gateways()->get_available_payment_gateways();$g=$gateways[$id]??null;
        if(!$g || !is_a($g,'Paymob_Payment') || $g->id!==$id || !$g->is_available() || $g->needs_setup()){return self::issue();}
        // Generated native gateways inherit this same hosted implementation. Pixel and any overridden
        // payment implementation cannot cross this adapter's audited boundary.
        if((new ReflectionMethod($g,'process_payment'))->getDeclaringClass()->getName()!=='Paymob_Payment'){return self::issue('paymob_hosted_required');}
        if($id==='paymob') {
            $settings=get_option('woocommerce_paymob_settings',[]);
            if(empty($settings['integration_id']) || !is_array($settings['integration_id'])){return self::issue();}
        } elseif(empty($g->single_integration_id) || !preg_match('/^[0-9]+$/D',(string)$g->single_integration_id) || (int)$g->single_integration_id<1){return self::issue();}
        return $g;
    }
    public static function redirect_valid($url,$gateway) {
        if(!is_string($url) || strlen($url)>4096 || preg_match('/[\x00-\x20\x7f]/',$url)){return false;}
        try {$base=Paymob::getApiUrl(Paymob::getCountryCode($gateway->pub_key));}catch(Throwable $e){return false;}
        $allowed=['https://accept.paymob.com/','https://uae.paymob.com/','https://pakistan.paymob.com/','https://ksa.paymob.com/','https://oman.paymob.com/'];
        if(!in_array($base,$allowed,true)){return false;}
        $p=wp_parse_url($url);$expected=wp_parse_url($base);
        if(!$p || ($p['scheme']??'')!=='https' || ($p['host']??'')!==$expected['host'] || ($p['path']??'')!=='/unifiedcheckout/' || isset($p['port']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])){return false;}
        $q=[];parse_str($p['query']??'',$q);
        return count($q)===2 && isset($q['publicKey'],$q['clientSecret']) && is_string($q['publicKey']) && is_string($q['clientSecret'])
            && hash_equals((string)$gateway->pub_key,$q['publicKey']) && preg_match('/^[A-Za-z0-9_-]{16,512}$/D',$q['clientSecret'])===1;
    }
    /** Isolated order-pay context: no requote, checkout hooks, browser basket or stock replay. */
    private static function context($order) {
        if(!class_exists('ElDokan_Customer_API_Quote_Cart')){require_once ELDOKAN_CUSTOMER_API_DIR.'includes/class-eldokan-customer-api-checkout-woo.php';}
        $wc=WC();$original=[$wc->cart,$wc->customer,$wc->session,$wc->shipping,$wc->shipping()];
        $wc->session=new ElDokan_Customer_API_Quote_Session($order->get_customer_id()?:bin2hex(random_bytes(32)));
        $wc->customer=new WC_Customer($order->get_customer_id(),false);
        foreach(['billing','shipping'] as $kind){foreach($order->get_address($kind) as $key=>$value){$setter='set_'.$kind.'_'.$key;if(is_callable([$wc->customer,$setter])){$wc->customer->$setter($value);}}}
        $wc->customer->set_calculated_shipping(true);$wc->shipping=new WC_Shipping(); ElDokan_Customer_API_Shipping_Context::use_engine($wc->shipping);
        $wc->cart=new ElDokan_Customer_API_Quote_Cart(true,true);$lines=[];
        foreach($order->get_items() as $item){$p=$item->get_product();if($p){$lines[$item->get_meta('_eldokan_customer_cart_item_id',true)]=['product_id'=>$item->get_product_id(),'variation_id'=>$item->get_variation_id(),'quantity'=>$item->get_quantity(),'data'=>$p,'variation'=>[],'line_subtotal'=>$item->get_subtotal(),'line_total'=>$item->get_total()];}}
        $wc->cart->set_cart_contents($lines);$wc->cart->set_totals(['total'=>(float)$order->get_total()]);
        $shipping=[];foreach($order->get_items('shipping') as $item){$shipping[]=$item->get_method_id().':'.$item->get_instance_id();}
        $wc->session->set('chosen_shipping_methods',$shipping);$wc->session->set('chosen_payment_method',$order->get_payment_method());
        return $original;
    }
    public static function run($row,$options=[]) {
        global $wpdb;
        try {
            // Never release a lock supplied by an active purchase to make this check pass.
            ElDokan_Customer_API_Order_Store::assert_external_work_unlocked();
            if((string)$wpdb->get_var('SELECT @@autocommit')!=='1'){return self::issue('payment_storage_unavailable',503);}
            $locked=ElDokan_Customer_API_Order_Store::lock('payment|'.$row['order_id']);if(is_wp_error($locked)){return $locked;}
            $order=wc_get_order($row['woo_order_id']);
            if(!$order || $order->get_meta('_eldokan_customer_public_order_id',true)!==$row['order_id']){return self::issue('order_not_found',404);}
            if($order->get_meta('_eldokan_customer_payment_concept',true)!=='paymob'){return self::issue('payment_not_eligible');}
            $state=ElDokan_Customer_API_Order_Read::payment($order);
            $base=['order_id'=>$row['order_id'],'order_status'=>ElDokan_Customer_API_Order_Read::status($order),'payment_status'=>$state,'payment_method'=>'paymob','requires_redirect'=>false,'redirect_url'=>null,'retryable'=>false,'generation'=>0,'expires_at'=>null];
            if($order->is_paid() || $order->has_status(['cancelled','refunded']) || !$order->needs_payment() || !$order->has_status(['pending','failed'])){
                if(!$order->is_paid()){$base['issue']='payment_not_eligible';}return $base;
            }
            if($order->get_meta('_eldokan_customer_payment_concept',true)!=='paymob' || (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) || (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order))){return self::issue('payment_not_eligible');}
            $journal=ElDokan_Customer_API_Payment_Store::read($row['order_id']);
            if(!$journal || (int)$journal['woo_order_id']!==(int)$row['woo_order_id'] || !hash_equals($row['owner_hash'],$journal['owner_hash']) || $journal['gateway_id']!==$order->get_payment_method()){return self::issue('payment_storage_unavailable',503);}
            $base['generation']=(int)$journal['generation'];$base['retryable']=true;
            $wc=WC();$original=[$wc->cart,$wc->customer,$wc->session,$wc->shipping,$wc->shipping()];$hook=null;$response_hook=null;$initiated=false;
            try {
                self::context($order);
                $g=self::gateway($journal['gateway_id']);if(is_wp_error($g)){return $g;}
                $url=ElDokan_Customer_API_Payment_Store::unseal($journal['redirect_cipher'],$row['order_id']);
                $valid_expiry=$journal['expires_at'] && strtotime($journal['expires_at'].' UTC')>time()+30;
                if($journal['state']==='ready' && $valid_expiry && self::redirect_valid($url,$g)){
                    return array_merge($base,['requires_redirect'=>true,'redirect_url'=>$url,'expires_at'=>gmdate('c',strtotime($journal['expires_at'].' UTC'))]);
                }
                $first=$journal['state']==='prepared' && (int)$journal['generation']===0;
                // A lost result BEFORE journal save is ambiguous. No automatic new intention.
                if(!$first && (empty($options['retry']) || ($options['expected_generation']??null)!==(int)$journal['generation'])){
                    $base['issue']='payment_retry_confirmation_required';return $base;
                }
                $generation=(int)$journal['generation']+1;
                ElDokan_Customer_API_Payment_Store::update($row['order_id'],$generation,'initiating');$base['generation']=$generation;$initiated=true;
                $expiry=null;
                $hook=static function($data,$context)use($order,&$expiry){
                    if(($context['context']??'')==='createPayment' && (int)($context['order_id']??0)===$order->get_id()){
                        ElDokan_Customer_API_Order_Store::assert_external_work_unlocked();
                        $text=$data['expires_at']??null;
                        if(is_string($text) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$text) && strtotime($text)>time()+30){$expiry=gmdate('Y-m-d H:i:s',strtotime($text));}
                    }return $data;
                };
                add_filter('paymob_intention_data',$hook,PHP_INT_MAX,2);
                $bound=false;
                // Observe the response to the gateway's OWN authenticated HTTP request. No API call,
                // copied merchant credential, signature calculation, or alternative payment engine.
                // 4.1.15 already reads intention_order_id but does not persist it on the Woo order.
                $response_hook=static function($response,$args,$url)use($row,$generation,$g,&$bound){
                    $expected=Paymob::getApiUrl(Paymob::getCountryCode($g->pub_key)).'v1/intention/';
                    if($url!==$expected || strtoupper($args['method']??'POST')!=='POST'){return $response;}
                    $request_data=json_decode($args['body']??'',true);
                    if(!is_array($request_data) || Paymob::getIntentionId((string)($request_data['special_reference']??''))!==(string)$row['woo_order_id']){return $response;}
                    if(is_wp_error($response) || wp_remote_retrieve_response_code($response)<200 || wp_remote_retrieve_response_code($response)>=300){return $response;}
                    $data=json_decode(wp_remote_retrieve_body($response),true);
                    if(is_array($data) && !empty($data['client_secret']) && isset($data['intention_order_id'])){
                        ElDokan_Customer_API_Payment_Store::bind($row,$generation,$data['intention_order_id']);$bound=true;
                    }return $response;
                };
                add_filter('http_response',$response_hook,PHP_INT_MAX,3);
                ElDokan_Customer_API_Order_Store::assert_external_work_unlocked();
                $result=$g->process_payment($order->get_id());
                if(!$bound || !is_array($result) || ($result['result']??'')!=='success' || !self::redirect_valid($result['redirect']??null,$g)){throw new RuntimeException('Native payment initiation unavailable.');}
                $cipher=ElDokan_Customer_API_Payment_Store::seal($result['redirect'],$row['order_id']);
                ElDokan_Customer_API_Payment_Store::update($row['order_id'],$generation,'ready',$cipher,$expiry);
                $current=wc_get_order($row['woo_order_id']);
                if($current->is_paid() || !$current->needs_payment()){$base['order_status']=ElDokan_Customer_API_Order_Read::status($current);$base['payment_status']=ElDokan_Customer_API_Order_Read::payment($current);$base['retryable']=false;return $base;}
                return array_merge($base,['requires_redirect'=>true,'redirect_url'=>$result['redirect'],'expires_at'=>$expiry?gmdate('c',strtotime($expiry.' UTC')):null]);
            } catch(Throwable $error) {
                if($initiated){try{ElDokan_Customer_API_Payment_Store::update($row['order_id'],$base['generation'],'ambiguous');}catch(Throwable $ignored){}}
                $base['issue']='payment_initiation_unavailable';return $base;
            } finally {
                if($hook){remove_filter('paymob_intention_data',$hook,PHP_INT_MAX);}
                if($response_hook){remove_filter('http_response',$response_hook,PHP_INT_MAX);}
                if(class_exists("ElDokan_Customer_API_Shipping_Context",false)){ElDokan_Customer_API_Shipping_Context::use_engine($original[4]);} [$wc->cart,$wc->customer,$wc->session,$wc->shipping]=$original;
            }
        } catch(Throwable $error){return self::issue('payment_storage_unavailable',503);}
    }
    /** Read-only admission before the existing plugin's verifier. Does not authenticate a callback,
     * reconcile a transaction, or write payment/stock/status. Native Accept HMAC signs obj.order.id
     * but NOT merchant_order_id. Native Flash HMAC signs amount+intention.id, NOT success/status.
     * A4 accepts only the plugin's fully signed TRANSACTION/Accept branch with a durable mapping.
     */
    public static function callback_admission($data) {
        if(!is_array($data)){return true;}
        $order=null;
        // Inspect both native shapes. A conflicting legacy-looking obj must not mask a Flash
        // target: callWebhookAction selects its branch independently of the merchant reference.
        foreach([$data['obj']['order']['merchant_order_id']??null,$data['intention']['extras']['creation_extras']['merchant_intention_id']??null] as $merchant){
            if(!is_string($merchant) || strlen($merchant)>256){continue;}
            $candidate=self::merchant_order($merchant);
            if($candidate && $candidate->get_meta('_eldokan_customer_flow',true)==='phase2c-a3' && $candidate->get_meta('_eldokan_customer_payment_concept',true)==='paymob'){
                if($order && $order->get_id()!==$candidate->get_id()){return false;}$order=$candidate;
            }
        }
        if(!$order){return true;}
        if(isset($data['subscription_data']) || ($data['type']??null)!=='TRANSACTION' || !isset($data['obj']) || !is_array($data['obj']) || !Paymob::filterVar('hmac','REQUEST')){return false;}
        $obj=$data['obj'];
        foreach(['error_occured','has_parent_transaction','is_3d_secure','is_auth','is_capture','is_refunded','is_standalone_payment','is_voided','pending','success'] as $key){if(!isset($obj[$key]) || !is_bool($obj[$key])){return false;}}
        foreach(['is_refund','is_void'] as $key){if(isset($obj[$key]) && !is_bool($obj[$key])){return false;}}
        if(!isset($obj['order']['merchant_order_id']) || !is_string($obj['order']['merchant_order_id'])){return false;}
        $selected=self::merchant_order($obj['order']['merchant_order_id']);
        if(!$selected || (string)$order->get_id()!==(string)$selected->get_id()){return false;}
        // The native branch also consumes unsigned refund/void aliases. Never let an alias override
        // the corresponding signed boolean. Refund initiation is outside A4.
        if((!empty($obj['is_refund']) && empty($obj['is_refunded'])) || (!empty($obj['is_void']) && empty($obj['is_voided']))){return false;}
        try {
            $resolved=ElDokan_Customer_API_Order_Read::row($order->get_meta('_eldokan_customer_public_order_id',true));
            return !is_wp_error($resolved) && ElDokan_Customer_API_Payment_Store::bound($resolved[0],$obj['order']['id']??null);
        }catch(Throwable $error){return false;}
    }
    public static function native_callback_admission() {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || !class_exists('Paymob')){return;}
        $raw=file_get_contents('php://input');
        if(!is_string($raw) || strlen($raw)>1048576){wp_die('Payment callback unavailable.','',['response'=>413]);}
        if(!self::callback_admission(json_decode($raw,true))){wp_die('Payment callback unavailable.','',['response'=>403]);}
    }
    /** GET compatibility guard on the ORIGINAL WC API action, not a new callback engine.
     * 4.1.15 mutates total/failed before HMAC and completes on signed browser returns.
     * For Phase 2C orders bypass all GET mutations; only its POST handlers can confirm payment.
     */
    public static function browser_return() {
        if(($_SERVER['REQUEST_METHOD']??'')!=='GET' || !class_exists('Paymob')){return;}
        $merchant=$_GET['merchant_order_id']??'';
        if(!is_string($merchant) || strlen($merchant)>256){return;}
        $order=self::merchant_order($merchant);
        if(!$order || $order->get_meta('_eldokan_customer_flow',true)!=='phase2c-a3'){return;}
        $public=$order->get_meta('_eldokan_customer_public_order_id',true);
        if(!is_string($public) || !preg_match('/^ord_[a-f0-9]{64}$/D',$public)){wp_die('Order return unavailable.','', ['response'=>400]);}
        $origin=self::frontend_origin();
        nocache_headers();header('Referrer-Policy: no-referrer');
        if(!$origin){wp_die('Order return unavailable.','', ['response'=>503]);}
        // Fixed path. No credential, status, transaction, host, origin, or requested destination.
        wp_redirect($origin.'/orders/'.$public,303,'ElDokan');exit;
    }
}
