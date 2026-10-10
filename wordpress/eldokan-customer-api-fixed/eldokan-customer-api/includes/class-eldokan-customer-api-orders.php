<?php
if (!defined('ABSPATH')) { exit; }

/** A3 COD-only placement; quotation stays owned by the A2 orchestrator. */
final class ElDokan_Customer_API_Orders {
    public static function attempt(WP_REST_Request $request) {
        $owner = ElDokan_Customer_API_Cart::checkout_owner($request);
        if (is_wp_error($owner)) { return $owner; }
        $input = self::input($request, false);
        if (is_wp_error($input)) { return $input; }
        $prepared = self::prepare($request,$input);
        if (is_wp_error($prepared)) { return $prepared; }
        try {
            $id = ElDokan_Customer_API_Order_Store::create($owner,self::hash($input),$prepared['material']);
            return ['checkout_attempt_id'=>$id,'state'=>'prepared','stock_reserved'=>false];
        } catch (Throwable $error) { return ElDokan_Customer_API_Order_Store::unavailable(); }
    }
    public static function place(WP_REST_Request $request) {
        $owner = ElDokan_Customer_API_Cart::checkout_owner($request);
        if (is_wp_error($owner)) { return $owner; }
        $input = self::input($request, true);
        if (is_wp_error($input)) { return $input; }
        $id = $input['checkout_attempt_id']; unset($input['checkout_attempt_id']);
        $row = ElDokan_Customer_API_Order_Store::read($id,$owner['binding']);
        if (is_wp_error($row)) { return $row; }
        if (!hash_equals($row['request_hash'],self::hash($input))) {
            return new WP_Error('checkout_attempt_conflict','This attempt belongs to a different checkout request.',['status'=>409]);
        }
        // Check the durable result before inspecting Cart, address book, stock or current gateways.
        if ($row['state'] === 'completed') { return self::recover($row); }
        if ($row['state'] !== 'prepared') { return self::recovery_required(); }
        // Serialize different A3 attempts too. Native Woo reservation remains responsible for other flows.
        $locked = ElDokan_Customer_API_Order_Store::lock('placement');
        if (is_wp_error($locked)) { return $locked; }
        $prepared = self::prepare($request,$input);
        if (is_wp_error($prepared)) { return $prepared; }
        $old_material = json_decode($row['material_json'],true);
        $changes = [];
        foreach ($prepared['material'] as $key => $value) {
            if (!isset($old_material[$key]) || !hash_equals($old_material[$key],$value)) {
                $changes[] = ElDokan_Customer_API_Checkout::issue($key . '_changed','Checkout changed. Review a new quote and prepare a new attempt.');
            }
        }
        if ($changes) { return new WP_Error('checkout_changed','Checkout changed before placement.',['status'=>409,'issues'=>$changes]); }
        // Known admission failures precede the processing fence: repair storage and retry this prepared attempt.
        try { ElDokan_Customer_API_Order_Store::check_transaction_participants(); }
        catch (Throwable $error) { return ElDokan_Customer_API_Order_Store::unavailable(); }
        $wc = WC(); $original = [$wc->cart,$wc->customer,$wc->session,$wc->shipping,$wc->shipping()];
        [$wc->cart,$wc->customer,$wc->session,$wc->shipping] = $prepared['context'];
        ElDokan_Customer_API_Shipping_Context::use_engine($wc->shipping);
        $line_hook = null; $order_hook = null; $customer_filter = null;
        $started = false;
        try {
            // A committed processing fence survives crashes/ambiguous COMMIT. Never recreate a fenced attempt.
            ElDokan_Customer_API_Order_Store::processing($id);
            ElDokan_Customer_API_Order_Store::begin(); $started = true;
            $seen = [];
            $line_hook = static function ($item,$key,$values,$order) use (&$seen,$prepared) {
                if (!isset($prepared['sellers'][$key])) { throw new RuntimeException('Unexpected checkout line.'); }
                $snapshot = self::seller($values['product_id']);
                if (is_wp_error($snapshot) || self::hash($snapshot) !== self::hash($prepared['sellers'][$key])) { throw new RuntimeException('Seller changed.'); }
                $item->add_meta_data('_eldokan_seller_user_id',$snapshot['user_id'],true);
                $item->add_meta_data('_eldokan_customer_seller_snapshot',$snapshot,true);
                $item->add_meta_data('_eldokan_customer_cart_item_id',$key,true);
                $seen[$key] = true;
            };
            $order_hook = static function ($order,$data) use ($row,$prepared,$owner,$input) {
                $order->set_customer_id($owner['customer_id']); // Guest never linked by email or a third-party customer filter.
                $order->add_meta_data('_eldokan_customer_flow','phase2c-a3',true);
                $order->add_meta_data('_eldokan_customer_checkout_attempt',$row['attempt_id'],true);
                $order->add_meta_data('_eldokan_customer_public_order_id',$row['order_id'],true);
                $order->add_meta_data('_eldokan_customer_address_snapshot',$prepared['quote']['address'],true);
                $order->add_meta_data('_eldokan_customer_owner_binding',$row['owner_hash'],true);
                $order->add_meta_data('_eldokan_customer_cart_binding',$row['cart_owner_hash'],true);
                if ($input['payment_method'] === 'paymob') { $order->add_meta_data('_eldokan_customer_payment_concept','paymob',true); }
            };
            $customer_filter = static function ($value) use ($owner) { return $owner['customer_id']; };
            add_filter('woocommerce_checkout_customer_id',$customer_filter,PHP_INT_MAX,1);
            add_action('woocommerce_checkout_create_order_line_item',$line_hook,PHP_INT_MIN,4);
            add_action('woocommerce_checkout_create_order',$order_hook,PHP_INT_MIN,2);
            $gateway = $input['payment_method'] === 'cod' ? self::cod() : ElDokan_Customer_API_Paymob::gateway($prepared['gateway_id']);
            if (is_wp_error($gateway)) { throw new RuntimeException('Payment unavailable.'); }
            // Also run native checkout stock/purchasability checks (including stock held by other Woo Orders).
            if (method_exists($wc->cart,'check_cart_items')) { $wc->cart->check_cart_items(); }
            if (function_exists('wc_notice_count') && wc_notice_count('error')) { throw new RuntimeException('Native checkout correction required.'); }
            $wc->session->set('chosen_payment_method',$gateway->id);
            $data = self::native_data($prepared['quote']['address']);
            $data['payment_method'] = $gateway->id;
            $data['order_comments'] = $input['order_notes'] ?? '';
            $order_id = WC()->checkout()->create_order($data);
            if (is_wp_error($order_id) || !$order_id) { throw new RuntimeException('Native order creation failed.'); }
            $order = wc_get_order($order_id);
            if (!$order || $order->get_meta('_eldokan_customer_checkout_attempt',true) !== $id
                || count($seen) !== count($prepared['sellers']) || count($order->get_items()) !== count($seen)) {
                throw new RuntimeException('Required native checkout lifecycle was bypassed.');
            }
            self::verify_order($row,$order,$prepared,$owner);
            // Persist the unique mapping, line memberships and receipts in the same native DB transaction.
            ElDokan_Customer_API_Order_Store::record_order($row,$order);
            if (class_exists('ElDokan_Customer_API_Payment_Store')) { ElDokan_Customer_API_Payment_Store::record($row,$order); }
            do_action('woocommerce_checkout_order_processed',$order_id,$data,$order);
            if ($input['payment_method'] === 'cod') {
                $paid = $gateway->process_payment($order_id); // Native COD status/stock; its redirect is deliberately private.
                if (!is_array($paid) || ($paid['result'] ?? '') !== 'success') { throw new RuntimeException('Native COD did not complete.'); }
            }
            $order = wc_get_order($order_id);
            if (!$order || ($input['payment_method'] === 'cod' && $order->has_status(['pending','failed','cancelled']))
                || ($input['payment_method'] === 'paymob' && (!$order->has_status('pending') || !$order->needs_payment()))) { throw new RuntimeException('Native order not placed.'); }
            self::verify_order($row,$order,$prepared,$owner);
            if ((string)$order->get_customer_note('edit') !== ($input['order_notes'] ?? '')) { throw new RuntimeException('Customer note changed.'); }
            $result = self::result($row,$order,$prepared);
            ElDokan_Customer_API_Order_Store::save_recovery($id, ElDokan_Customer_API_Checkout_Recovery::evidence($row,$order,$result));
            ElDokan_Customer_API_Order_Store::complete($id,$result);
            ElDokan_Customer_API_Order_Store::finish(true); $started = false;
            // If a response is lost here, receipts already consumed the purchased Cart and the result is durable.
            ElDokan_Customer_API_Cart::checkout_consume($owner['owner']);
            return $result;
        } catch (Throwable $error) {
            if ($started) {
                try { ElDokan_Customer_API_Order_Store::finish(false); } catch (Throwable $rollback_error) { /* Fence remains durable. */ }
            }
            // COMMIT acknowledgement may be lost; only a durably completed ledger is a success.
            $persisted = ElDokan_Customer_API_Order_Store::read($id,$owner['binding']);
            if (!is_wp_error($persisted) && $persisted['state'] === 'completed') { return self::recover($persisted); }
            return self::recovery_required();
        } finally {
            if ($line_hook) { remove_action('woocommerce_checkout_create_order_line_item',$line_hook,PHP_INT_MIN); }
            if ($order_hook) { remove_action('woocommerce_checkout_create_order',$order_hook,PHP_INT_MIN); }
            if ($customer_filter) { remove_filter('woocommerce_checkout_customer_id',$customer_filter,PHP_INT_MAX); }
            ElDokan_Customer_API_Shipping_Context::use_engine($original[4]); [$wc->cart,$wc->customer,$wc->session,$wc->shipping] = $original;
        }
    }
    private static function verify_order($row,$order,$prepared,$owner) {
        if ((int)$order->get_customer_id() !== (int)$owner['customer_id']
            || $order->get_meta('_eldokan_customer_checkout_attempt',true) !== $row['attempt_id']
            || $order->get_meta('_eldokan_customer_public_order_id',true) !== $row['order_id']
            || $order->get_meta('_eldokan_customer_flow',true) !== 'phase2c-a3'
            || self::hash($order->get_meta('_eldokan_customer_address_snapshot',true)) !== self::hash($prepared['quote']['address'])
            || $order->get_currency() !== $prepared['quote']['totals']['total']['currency']
            || $order->get_payment_method() !== ($prepared['gateway_id'] ?? 'cod')
            || ElDokan_Customer_API_Utils::money($order->get_total()) !== $prepared['quote']['totals']['total']) { throw new RuntimeException('Native Order identity, address or totals changed.'); }
        $seen = [];
        foreach ($order->get_items() as $item) {
            $key = $item->get_meta('_eldokan_customer_cart_item_id',true);
            if (!isset($prepared['sellers'][$key],$prepared['lines'][$key]) || isset($seen[$key])
                || (int)$item->get_meta('_eldokan_seller_user_id',true) !== $prepared['sellers'][$key]['user_id']
                || self::hash($item->get_meta('_eldokan_customer_seller_snapshot',true)) !== self::hash($prepared['sellers'][$key])) { throw new RuntimeException('Order Seller membership changed.'); }
            $line = $prepared['lines'][$key];
            if ((int)$item->get_product_id() !== (int)$line['product_id'] || (int)$item->get_variation_id() !== (int)$line['variation_id']
                || (int)$item->get_quantity() !== (int)$line['quantity']
                || ElDokan_Customer_API_Utils::money($item->get_subtotal()) !== ElDokan_Customer_API_Utils::money($line['line_subtotal'])
                || ElDokan_Customer_API_Utils::money($item->get_total()) !== ElDokan_Customer_API_Utils::money($line['line_total'])) { throw new RuntimeException('Native Order line changed.'); }
            $seen[$key] = true;
        }
        if (count($seen) !== count($prepared['lines'])) { throw new RuntimeException('Native Order lost lines.'); }
    }

    private static function recovery_required() {
        return new WP_Error('checkout_recovery_required','This attempt needs store-side recovery. Do not create another attempt for this purchase.',['status'=>503]);
    }
    public static function recover($row) {
        $result = json_decode($row['response_json'],true);
        if (!self::safe_result($result) || ($result['order_id'] ?? '') !== $row['order_id'] || ($result['checkout_attempt_id'] ?? '') !== $row['attempt_id']) { return ElDokan_Customer_API_Order_Store::unavailable(); }
        return $result;
    }
    public static function safe_result($result) {
        $keys = ['order_id','checkout_attempt_id','status','payment_method','total','currency','lines','shipping','created_at'];
        $exact = static function ($v,$fields) { return is_array($v) && count($v) === count($fields) && !array_diff($fields,array_keys($v)); };
        $money = static function ($v) use ($exact) { return $exact($v,['amount','currency','decimals','formatted']) && is_int($v['amount']) && is_string($v['currency']) && is_int($v['decimals']) && is_string($v['formatted']); };
        if (!$exact($result,$keys) || !is_string($result['status']) || !preg_match('/^[a-z0-9_-]{1,64}$/D',$result['status'])
            || !$money($result['total']) || !is_string($result['currency']) || $result['currency'] !== $result['total']['currency']
            || !is_string($result['created_at']) || !is_array($result['lines']) || !$result['lines']
            || !in_array($result['payment_method'],[['id'=>'cod','name'=>'Cash on Delivery','requires_redirect'=>false],['id'=>'paymob','name'=>'Paymob','requires_redirect'=>true]],true)) { return false; }
        foreach ($result['lines'] as $line) {
            if (!$exact($line,['name','quantity','subtotal','total','seller']) || !is_string($line['name']) || !is_int($line['quantity']) || $line['quantity'] < 1
                || !$money($line['subtotal']) || !$money($line['total']) || !$exact($line['seller'],['id','name'])
                || !is_string($line['seller']['id']) || !preg_match('/^sel_[1-9][0-9]*$/D',$line['seller']['id']) || !is_string($line['seller']['name'])) { return false; }
        }
        if ($result['shipping'] !== null) {
            $v = $result['shipping'];
            if (!$exact($v,['id','name','description','amount','available','type']) || !is_string($v['id']) || !preg_match('/^shp_[a-f0-9]{64}$/D',$v['id'])
                || !is_string($v['name']) || !is_string($v['description']) || !$money($v['amount']) || $v['available'] !== true || !in_array($v['type'],['pickup','door_delivery'],true)) { return false; }
        }
        return true;
    }

    private static function input($request,$placement) {
        $body = $request->get_json_params();
        $allowed = ['address_id','address','shipping_method_id','payment_method','order_notes'];
        if ($placement) { $allowed[] = 'checkout_attempt_id'; }
        if (!is_array($body) || !$body || array_diff(array_keys($body),$allowed) || !isset($body['payment_method']) || !array_key_exists('shipping_method_id',$body)
            || (isset($body['address']) === isset($body['address_id']))) {
            return new WP_Error('invalid_placement_fields','Provide one address, shipping_method_id and payment_method.',['status'=>422]);
        }
        if ($placement && (!isset($body['checkout_attempt_id']) || !is_string($body['checkout_attempt_id']) || !preg_match('/^chk_[a-f0-9]{64}$/D',$body['checkout_attempt_id']))) { return new WP_Error('invalid_checkout_attempt_id','Invalid checkout attempt ID.',['status'=>400]); }
        if (!in_array($body['payment_method'],['cod','paymob'],true)) { return new WP_Error('payment_method_invalid','Choose an eligible payment method.',['status'=>422]); }
        if ($body['payment_method'] === 'paymob' && !class_exists('ElDokan_Customer_API_Paymob')) { return new WP_Error('paymob_unavailable','Paymob is unavailable.',['status'=>422]); }
        if (array_key_exists('order_notes',$body)) {
            // Bound raw bytes too, before sanitizing. 1,000 Unicode characters, at most 4,000 input bytes.
            if (!is_string($body['order_notes']) || strlen($body['order_notes']) > 4000 || !preg_match('//u',$body['order_notes'])) { return new WP_Error('invalid_order_notes','Order notes must be text of at most 1,000 characters.',['status'=>422]); }
            $note = trim(sanitize_textarea_field(str_replace(["\r\n","\r"],"\n",$body['order_notes'])));
            if (preg_match_all('/./us',$note) > 1000) { return new WP_Error('invalid_order_notes','Order notes must be text of at most 1,000 characters.',['status'=>422]); }
            unset($body['order_notes']);
            // Empty/missing notes intentionally share the original A3 fingerprint for upgrade compatibility.
            if ($note !== '') { $body['order_notes'] = $note; }
        }
        if ($body['shipping_method_id'] !== null && (!is_string($body['shipping_method_id']) || !preg_match('/^shp_[a-f0-9]{64}$/D',$body['shipping_method_id']))) { return new WP_Error('shipping_method_invalid','Invalid shipping method.',['status'=>422]); }
        if (isset($body['address_id']) && (!is_string($body['address_id']) || !preg_match('/^adr_[a-f0-9]{64}$/D',$body['address_id']))) { return new WP_Error('invalid_address_id','Invalid address ID.',['status'=>400]); }
        if (isset($body['address'])) {
            if (!is_array($body['address']) || array_diff(array_keys($body['address']),ElDokan_Customer_API_Checkout::ADDRESS_FIELDS)) { return new WP_Error('invalid_address','Invalid address.',['status'=>422]); }
            foreach ($body['address'] as $key => $value) {
                if (!is_string($value) && !($value === null && in_array($key,['company','address_extra'],true))) { return new WP_Error('invalid_address','Invalid address field.',['status'=>422]); }
                $body['address'][$key] = $value === null ? null : trim(sanitize_text_field($value));
            }
            $body['address'] += ['country'=>'EG','company'=>null,'address_extra'=>null];
        }
        return $body;
    }
    private static function prepare($request,$input) {
        $body = array_intersect_key($input,array_flip(['address','address_id','shipping_method_id']));
        if (($body['shipping_method_id'] ?? null) === null) { unset($body['shipping_method_id']); }
        $quote_request = clone $request; $quote_request->set_body_params([]); $quote_request->set_body(wp_json_encode($body));
        $context = null;
        $quote = ElDokan_Customer_API_Checkout::prepare($quote_request,$context);
        if (is_wp_error($quote)) { return $quote; }
        if (!$quote['ready'] || !$context) { return new WP_Error('checkout_not_ready','Correct checkout before placement.',['status'=>422,'issues'=>$quote['issues']]); }
        if ($quote['shipping_required'] && !$input['shipping_method_id']) { return new WP_Error('shipping_method_required','Select a shipping method from the current quote.',['status'=>422]); }
        $wc = WC(); $original = [$wc->cart,$wc->customer,$wc->session,$wc->shipping,$wc->shipping()];
        [$wc->cart,$wc->customer,$wc->session,$wc->shipping] = $context;
        ElDokan_Customer_API_Shipping_Context::use_engine($wc->shipping);
        try {
            $gateway = $input['payment_method'] === 'cod' ? self::cod() : ElDokan_Customer_API_Paymob::gateway(); if (is_wp_error($gateway)) { return $gateway; }
            if (method_exists($wc->cart,'check_cart_items')) { $ok = $wc->cart->check_cart_items(); }
            if ((isset($ok) && !$ok) || (function_exists('wc_notice_count') && wc_notice_count('error'))) {
                return new WP_Error('checkout_not_ready','Native Woo checkout validation requires correction.',['status'=>422,'issues'=>[ElDokan_Customer_API_Checkout::issue('stock_unavailable','Native checkout stock or availability changed.')]]);
            }
            $sellers = []; $cart = []; $stock = []; $prices = [];
            foreach ($wc->cart->get_cart() as $key => $line) {
                $seller = self::seller($line['product_id']); if (is_wp_error($seller)) { return $seller; }
                $sellers[$key] = $seller;
                $product = $line['data']; $stock_product = wc_get_product($product->get_stock_managed_by_id());
                $cart[$key] = [$line['product_id'],$line['variation_id'],$line['quantity'],$line['variation'],$product->get_min_purchase_quantity(),$product->get_max_purchase_quantity(),$product->is_sold_individually()];
                $stock[$key] = [$product->get_stock_managed_by_id(),$stock_product ? $stock_product->get_stock_quantity() : null,$product->get_stock_status(),$product->backorders_allowed()];
                $prices[$key] = [ElDokan_Customer_API_Utils::money($product->get_price()),$line['line_subtotal'],$line['line_total'],$line['line_tax_data']];
            }
            $fees = [];
            foreach ($wc->cart->get_fees() as $fee) { $fees[] = [$fee->name,$fee->amount,$fee->taxable,$fee->tax_class,$fee->total,$fee->tax_data]; }
            $discounts = [$wc->cart->get_applied_coupons(),$wc->cart->get_discount_total(),$wc->cart->get_discount_tax()];
            $material = [
                'cart'=>self::hash($cart), 'address'=>self::hash($quote['address']), 'seller'=>self::hash($sellers),
                'price'=>self::hash($prices), 'stock'=>self::hash($stock),
                'shipping'=>self::hash([$quote['selected_shipping_method'],$wc->session->get('chosen_shipping_methods')]),
                'totals'=>self::hash($quote['totals']), 'fees'=>self::hash($fees), 'discounts'=>self::hash($discounts),
            ];
            if ($input['payment_method'] === 'paymob') { $material['totals'] = self::hash([$quote['totals'],$gateway->id]); }
            return ['quote'=>$quote,'context'=>$context,'sellers'=>$sellers,'material'=>$material,'lines'=>$wc->cart->get_cart(),'gateway_id'=>$gateway->id];
        } finally { ElDokan_Customer_API_Shipping_Context::use_engine($original[4]); [$wc->cart,$wc->customer,$wc->session,$wc->shipping] = $original; }
    }
    private static function cod() {
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $gateway = $gateways['cod'] ?? null;
        // A configurable public concept cannot turn an arbitrary provider into COD.
        if (!$gateway || !is_a($gateway,'WC_Gateway_COD') || !$gateway->is_available()) {
            return new WP_Error('cod_unavailable','Cash on Delivery is unavailable for this checkout.',['status'=>422,'issues'=>[ElDokan_Customer_API_Checkout::issue('cod_unavailable','Choose an eligible Cash on Delivery checkout.')]]);
        }
        return $gateway;
    }
    private static function seller($product_id) {
        $product = wc_get_product($product_id);
        $value = $product ? $product->get_meta('_eldokan_seller_user_id',true,'edit') : null;
        $id = is_scalar($value) && preg_match('/^[1-9][0-9]*$/D',(string)$value) ? (int)$value : 0;
        $user = $id ? get_user_by('id',$id) : false;
        if (!$user || !array_intersect(['eldokan_owner','wcfm_vendor','seller','vendor'],(array)$user->roles)) { return new WP_Error('seller_unavailable','An item has no valid canonical Seller.',['status'=>422,'issues'=>[ElDokan_Customer_API_Checkout::issue('seller_unavailable','Canonical Seller ownership is required.')]]); }
        return ['user_id'=>$id,'id'=>ElDokan_Customer_API_Utils::opaque_id('sel',$id),'name'=>sanitize_text_field((string)$user->display_name)];
    }
    private static function native_data($address) {
        $data = [];
        foreach (['billing','shipping'] as $kind) {
            foreach (['first_name'=>'first_name','last_name'=>'last_name','company'=>'company','country'=>'country','state'=>'state','city'=>'city','address_1'=>'street_address','address_2'=>'address_extra'] as $native => $public) { $data[$kind.'_'.$native] = (string)($address[$public] ?? ''); }
            $data[$kind.'_postcode'] = '';
        }
        $data['shipping_phone'] = $address['phone']; $data['billing_phone'] = $address['phone']; $data['billing_email'] = $address['email'];
        return $data;
    }
    private static function hash($value) {
        $sort = static function ($v) use (&$sort) {
            if (is_array($v)) { if (array_keys($v) !== range(0,count($v)-1)) { ksort($v); } foreach ($v as $k=>$item) { $v[$k] = $sort($item); } }
            return $v;
        };
        return hash('sha256',wp_json_encode($sort($value)));
    }
    public static function result($row,$order,$prepared) {
        $lines = [];
        foreach ($order->get_items() as $item) {
            $snapshot = $item->get_meta('_eldokan_customer_seller_snapshot',true);
            $lines[] = ['name'=>sanitize_text_field($item->get_name()),'quantity'=>(int)$item->get_quantity(),
                'subtotal'=>ElDokan_Customer_API_Utils::money($item->get_subtotal()),
                'total'=>ElDokan_Customer_API_Utils::money($item->get_total()),
                'seller'=>['id'=>$snapshot['id'],'name'=>$snapshot['name']]];
        }
        return ['order_id'=>$row['order_id'],'checkout_attempt_id'=>$row['attempt_id'],'status'=>$order->get_status(),
            'payment_method'=>$order->get_meta('_eldokan_customer_payment_concept',true)==='paymob' ? ['id'=>'paymob','name'=>'Paymob','requires_redirect'=>true] : ['id'=>'cod','name'=>'Cash on Delivery','requires_redirect'=>false],
            'total'=>ElDokan_Customer_API_Utils::money($order->get_total()),
            'currency'=>$order->get_currency(),'lines'=>$lines,'shipping'=>$prepared['quote']['selected_shipping_method'],
            'created_at'=>$order->get_date_created()->date('c')];
    }
}
