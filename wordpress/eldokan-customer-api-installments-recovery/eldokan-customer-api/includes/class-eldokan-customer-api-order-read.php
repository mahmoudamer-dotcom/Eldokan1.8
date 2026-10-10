<?php
if (!defined('ABSPATH')) { exit; }

/** Private Phase 2C projections. The purchase ledger locates orders; native Woo owns status. */
final class ElDokan_Customer_API_Order_Read {
    const ACCESS_HEADER = 'X-ElDokan-Order-Access';
    const STATUSES = ['pending_payment','processing','awaiting_pickup','shipped','delivered','cancelled','failed','refunded'];
    public static function missing() { return new WP_Error('order_not_found','Order not found.',['status'=>404]); }
    public static function row($id) {
        global $wpdb;
        if(!is_string($id) || !preg_match('/^ord_[a-f0-9]{64}$/D',$id)){return new WP_Error('invalid_order_id','Invalid order ID.',['status'=>400]);}
        if(!ElDokan_Customer_API_Order_Store::schema_valid()){return ElDokan_Customer_API_Order_Store::unavailable();}
        $rows=ElDokan_Customer_API_Payment_Store::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('attempts').' WHERE order_id=%s AND state=%s',$id,'completed'));
        $row=$rows[0]??null;
        if(!$row){return self::missing();}
        $order=wc_get_order($row['woo_order_id']);
        if(!$order || $order->get_meta('_eldokan_customer_checkout_attempt',true)!==$row['attempt_id'] || $order->get_meta('_eldokan_customer_public_order_id',true)!==$id || $order->get_meta('_eldokan_customer_flow',true)!=='phase2c-a3'){return self::missing();}
        return [$row,$order];
    }
    public static function authorize($request,$mutation=false) {
        $origin=ElDokan_Customer_API_Auth::validate_request_origin();if(is_wp_error($origin)){return $origin;}
        $resolved=self::row($request->get_param('order_id'));if(is_wp_error($resolved)){return $resolved;}
        [$row,$order]=$resolved;
        $user=ElDokan_Customer_API_Auth::current_customer();
        if($user && (int)$order->get_customer_id()===(int)$user->ID){
            if($mutation){$ok=ElDokan_Customer_API_Auth::require_customer($request,true);if(is_wp_error($ok)){return $ok;}}
            return $resolved;
        }
        // A logged-in staff/seller never becomes a guest through a supplied capability.
        if((wp_validate_auth_cookie('','logged_in') && !$user) || (int)$order->get_customer_id()!==0){return self::missing();}
        $token=$request->get_header(self::ACCESS_HEADER);
        if(!is_string($token) || !preg_match('/^gok_[a-f0-9]{64}$/D',$token)){return self::missing();}
        $journal=ElDokan_Customer_API_Payment_Store::read($row['order_id']);
        if(!$journal || (int)$journal['woo_order_id']!==(int)$row['woo_order_id'] || !hash_equals($row['owner_hash'],$journal['owner_hash']) || !is_string($journal['guest_hash']) || !hash_equals($journal['guest_hash'],hash('sha256',$token))){return self::missing();}
        if($mutation){$csrf=$request->get_header('X-ElDokan-CSRF');if(!is_string($csrf) || !hash_equals(ElDokan_Customer_API_Payment_Store::csrf($token,$row['order_id']),$csrf)){return new WP_Error('invalid_csrf_token','The order security token is missing or invalid.',['status'=>403]);}}
        return $resolved;
    }
    public static function status($order) {
        $map=['pending'=>'pending_payment','processing'=>'processing','on-hold'=>'processing','cancelled'=>'cancelled','failed'=>'failed','refunded'=>'refunded'];
        $configured=get_option('eldokan_customer_api_order_status_map',[]);
        if(is_array($configured)){foreach($configured as $native=>$public){if(is_string($native) && is_string($public) && in_array($public,self::STATUSES,true) && !in_array($native,['pending','cancelled','failed','refunded'],true)){$map[$native]=$public;}}}
        $status=$order->get_status();
        if(!isset($map[$status])){throw new RuntimeException('Native status requires a verified mapping.');}
        return $map[$status];
    }
    public static function payment($order) {
        if($order->has_status('refunded')){return 'refunded';}
        if($order->has_status('cancelled')){return 'cancelled';}
        if($order->is_paid()){return 'paid';}
        if($order->has_status('failed')){return 'failed';}
        if($order->has_status('on-hold')){return 'pending';}
        return 'unpaid';
    }
    private static function money($order,$amount,$decimals) {
        if($amount===null || $amount===''){ $amount=0; }
        $normalized=wc_format_decimal($amount,$decimals);$negative=strpos($normalized,'-')===0;
        $parts=explode('.',ltrim($normalized,'-'),2);
        $minor=(int)$parts[0]*(10**$decimals)+(int)str_pad(substr($parts[1]??'',0,$decimals),$decimals,'0');
        if($negative){$minor=-$minor;}
        return ['amount'=>$minor,'currency'=>$order->get_currency(),'decimals'=>$decimals,'formatted'=>ElDokan_Customer_API_Utils::clean_text(wc_price($amount,['currency'=>$order->get_currency(),'decimals'=>$decimals]))];
    }
    public static function detail($row,$order) {
        global $wpdb;
        $receipt=ElDokan_Customer_API_Orders::recover($row);if(is_wp_error($receipt)){throw new RuntimeException('Order receipt unavailable.');}
        $decimals=$receipt['total']['decimals'];
        $money=static function($amount)use($order,$decimals){return self::money($order,$amount,$decimals);};
        $lines=[];$count=0;
        foreach($order->get_items() as $item){
            $snapshot=$item->get_meta('_eldokan_customer_seller_snapshot',true);$quantity=(int)$item->get_quantity();$count+=$quantity;
            if(!is_array($snapshot) || !isset($snapshot['id'],$snapshot['name'])){throw new RuntimeException('Historical seller snapshot unavailable.');}
            $lines[]=['product_id'=>ElDokan_Customer_API_Utils::opaque_id('prd',$item->get_product_id()),'variation_id'=>$item->get_variation_id()?ElDokan_Customer_API_Utils::opaque_id('var',$item->get_variation_id()):null,
                'name'=>sanitize_text_field($item->get_name()),'quantity'=>$quantity,'subtotal'=>$money($item->get_subtotal()),'total'=>$money($item->get_total()),'tax'=>$money($item->get_total_tax()),'seller'=>['id'=>$snapshot['id'],'name'=>$snapshot['name']]];
        }
        $fees=[];foreach($order->get_items('fee') as $fee){$fees[]=['name'=>sanitize_text_field($fee->get_name()),'total'=>$money($fee->get_total()),'tax'=>$money($fee->get_total_tax())];}
        $fulfillments=[];
        $rows=ElDokan_Customer_API_Payment_Store::rows($wpdb->prepare('SELECT * FROM '.ElDokan_Customer_API_Order_Store::table('fulfillments').' WHERE woo_order_id=%d',$order->get_id()));
        // A4 never infers delivery from internal pending fulfillment rows. Operational states
        // require trusted configuration and cannot override the authoritative aggregate Woo state.
        $fmap=get_option('eldokan_customer_api_fulfillment_status_map',['pending'=>'pending']);
        foreach($rows as $f){$s=json_decode($f['seller_json'],true);$public=is_array($fmap)?($fmap[$f['state']]??null):null;
            if(!is_string($public) || !in_array($public,array_merge(['pending'],self::STATUSES),true) || !is_array($s) || !isset($s['id'],$s['name'])){throw new RuntimeException('Fulfillment state requires a verified mapping.');}
            $fulfillments[]=['seller'=>['id'=>$s['id'],'name'=>$s['name']],'status'=>$public];
        }
        $address=$order->get_meta('_eldokan_customer_address_snapshot',true);
        if(!is_array($address)){throw new RuntimeException('Historical address unavailable.');}
        $address=array_intersect_key($address,array_flip(ElDokan_Customer_API_Checkout::ADDRESS_FIELDS));
        $modified=$order->get_date_modified();
        $installment = $order->get_meta('_eldokan_customer_installment_plan', true);
        $installment_public = is_array($installment) && isset($installment['id']) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $installment['id']) ? ['id' => (string) $installment['id'], 'provider' => 'paymob'] : null;
        $display = $order->get_meta('_eldokan_customer_installment_display', true);
        if ($installment_public && $order->get_currency() === 'EGP' && is_array($display) && isset($display['tenure'], $display['monthly_amount']) && is_int($display['tenure']) && $display['tenure'] >= 1 && $display['tenure'] <= 120 && is_int($display['monthly_amount']) && $display['monthly_amount'] >= 1 && $display['monthly_amount'] <= 1000000000000) {
            $installment_public['selected_quote'] = ['tenure' => $display['tenure'], 'monthly_amount' => $display['monthly_amount'], 'currency' => 'EGP', 'decimals' => 2, 'source' => 'checkout_widget', 'provider_confirmed' => false];
        }
        return ['id'=>$row['order_id'],'created_at'=>$order->get_date_created()->date('c'),'updated_at'=>$modified?$modified->date('c'):null,
            'status'=>self::status($order),'payment_status'=>self::payment($order),'payment_method'=>$order->get_meta('_eldokan_customer_payment_concept',true)==='paymob'?'paymob':($order->get_payment_method()==='cod'?'cod':'unavailable'),
            'installment_plan' => $installment_public,
            'currency'=>$order->get_currency(),'subtotal'=>$money($order->get_subtotal()),'discount'=>$money($order->get_discount_total()),'fees'=>$fees,
            'shipping_total'=>$money($order->get_shipping_total()),'tax'=>$money($order->get_total_tax()),'total'=>$money($order->get_total()),
            'address'=>$address,'lines'=>$lines,'item_count'=>$count,'shipping'=>$receipt['shipping'],'order_notes'=>sanitize_textarea_field($order->get_customer_note('edit')),'fulfillments'=>$fulfillments];
    }
    public static function get($request) {
        try{$resolved=self::authorize($request);return is_wp_error($resolved)?$resolved:self::detail($resolved[0],$resolved[1]);}
        catch(Throwable $error){return new WP_Error('order_read_unavailable','Order details are temporarily unavailable.',['status'=>503]);}
    }
    public static function listing($request) {
        global $wpdb;
        $origin=ElDokan_Customer_API_Auth::validate_request_origin();if(is_wp_error($origin)){return $origin;}
        $user=ElDokan_Customer_API_Auth::require_customer($request);if(is_wp_error($user)){return $user;}
        $page=$request->get_param('page')??1;$limit=$request->get_param('per_page')??20;
        if(!is_scalar($page) || !is_scalar($limit) || !preg_match('/^[1-9][0-9]{0,4}$/D',(string)$page) || !preg_match('/^[1-9][0-9]?$/D',(string)$limit) || (int)$page>10000 || (int)$limit>50){return new WP_Error('invalid_pagination','Use page 1–10000 and per_page 1–50.',['status'=>400]);}
        $page=(int)$page;$limit=(int)$limit;
        try {
            if(!ElDokan_Customer_API_Order_Store::schema_valid()){throw new RuntimeException('Order storage unavailable.');}
            $a=ElDokan_Customer_API_Order_Store::table('attempts');
            // Query only existing Phase 2C mappings. Native authoritative storage supplies ownership;
            // no session hash as account history, no historical order scanning or ID migration.
            if(ElDokan_Customer_API_Order_Store::hpos()){$join=' JOIN '.$wpdb->prefix.'wc_orders n ON n.id=a.woo_order_id';$owner='n.customer_id';}
            else{$join=' JOIN '.$wpdb->prefix.'postmeta n ON n.post_id=a.woo_order_id AND n.meta_key=\'_customer_user\'';$owner='n.meta_value';}
            $where=$wpdb->prepare(" WHERE a.state=%s AND {$owner}=%s",'completed',(string)$user->ID);
            $total_rows=ElDokan_Customer_API_Payment_Store::rows("SELECT COUNT(DISTINCT a.order_id) AS total FROM {$a} a{$join}{$where}");
            $total=(int)$total_rows[0]['total'];
            $rows=ElDokan_Customer_API_Payment_Store::rows("SELECT DISTINCT a.* FROM {$a} a{$join}{$where} ORDER BY a.created_at DESC,a.order_id DESC LIMIT ".(int)$limit.' OFFSET '.(int)(($page-1)*$limit));
            $items=[];foreach($rows as $row){$order=wc_get_order($row['woo_order_id']);if(!$order || (int)$order->get_customer_id()!==(int)$user->ID || $order->get_meta('_eldokan_customer_public_order_id',true)!==$row['order_id']){throw new RuntimeException('Order ownership changed.');}
                $detail=self::detail($row,$order);$items[]=array_intersect_key($detail,array_flip(['id','created_at','updated_at','status','payment_status','payment_method','currency','total','item_count','shipping','fulfillments']));}
            return ['items'=>$items,'pagination'=>['page'=>$page,'per_page'=>$limit,'total'=>$total,'total_pages'=>(int)ceil($total/$limit)]];
        }catch(Throwable $error){return new WP_Error('order_read_unavailable','Order history is temporarily unavailable.',['status'=>503]);}
    }
    /** Original checkout owner authorizes credential redelivery; no Order access is inferred from email. */
    public static function placed($request) {
        $result=ElDokan_Customer_API_Orders::place($request);if(is_wp_error($result)){return $result;}
        try {
            $resolved=self::row($result['order_id']);if(is_wp_error($resolved)){return $resolved;}[$row,$order]=$resolved;
            // Old Phase 2C guest receipts may gain only a private capability record on an authorized
            // same-checkout retry. No Woo order or public identity is rewritten.
            if((int)$order->get_customer_id()===0){
                $journal=ElDokan_Customer_API_Payment_Store::read($row['order_id']);
                if(!$journal){ElDokan_Customer_API_Payment_Store::record($row,$order);$journal=ElDokan_Customer_API_Payment_Store::read($row['order_id']);}
                $credential=ElDokan_Customer_API_Payment_Store::credential($row);
                if(!hash_equals((string)$journal['guest_hash'],hash('sha256',$credential))){throw new RuntimeException('Guest capability unavailable.');}
                $result['guest_access']=['credential'=>$credential,'csrf_token'=>ElDokan_Customer_API_Payment_Store::csrf($credential,$row['order_id']),'header'=>self::ACCESS_HEADER];
            }
            if($result['payment_method']['id']==='paymob'){
                ElDokan_Customer_API_Order_Store::release_locks();
                ElDokan_Customer_API_Order_Store::assert_external_work_unlocked();
                $payment=ElDokan_Customer_API_Paymob::run($row);
                $result['payment']=is_wp_error($payment)?['order_id'=>$row['order_id'],'requires_redirect'=>false,'redirect_url'=>null,'retryable'=>true,'issue'=>$payment->get_error_code()]:$payment;
            }
            return $result;
        }catch(Throwable $error){
            // Purchase succeeded. Never turn a projection/initiation failure into recreation permission.
            $result['payment']=['order_id'=>$result['order_id'],'requires_redirect'=>false,'redirect_url'=>null,'retryable'=>true,'issue'=>'order_followup_unavailable'];return $result;
        }
    }
    public static function pay($request) {
        try {
            $resolved=self::authorize($request,true);if(is_wp_error($resolved)){return $resolved;}
            $body=$request->get_json_params();$body=$body??[];
            if(!is_array($body) || array_diff(array_keys($body),['retry','expected_generation']) || (isset($body['retry']) && !is_bool($body['retry'])) || (isset($body['expected_generation']) && (!is_int($body['expected_generation']) || $body['expected_generation']<0)) || (!empty($body['retry']) && !isset($body['expected_generation']))){return new WP_Error('invalid_payment_fields','Use retry and expected_generation only.',['status'=>422]);}
            return ElDokan_Customer_API_Paymob::run($resolved[0],$body);
        }catch(Throwable $error){return ElDokan_Customer_API_Paymob::issue('payment_storage_unavailable',503);}
    }
}
