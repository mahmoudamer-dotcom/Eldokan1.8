<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read a boolean-like flag from $_POST.
 *
 * @param string $key POST key.
 * @return bool
 */
function paymob_pixel_post_flag( $key ) {
	if ( ! isset( $_POST[ $key ] ) ) {
		return false;
	}
	$val = strtolower( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
	return ( '' !== $val && '0' !== $val && 'false' !== $val && 'no' !== $val );
}

add_filter('woocommerce_get_settings_checkout', 'paymob_pixel_settings_option', 10, 2);
function paymob_pixel_settings_option($settings, $current_section)
{
    return Paymob_Pixel_Settings::paymob_pixel_settings_option( $settings, $current_section );
}

// Render Custom HTML Table
add_action('woocommerce_admin_field_custom_html', 'paymob_pixel_customization_html_option');

function paymob_pixel_customization_html_option()
{
    return Paymob_Pixel_Customization_Html::paymob_pixel_customization_html_option();
    
}
add_action('woocommerce_update_options_checkout', 'save_paymob_pixel_settings');
/**
 * Save paymob_add_gateway settings.
 *
 * @return void
 */
function save_paymob_pixel_settings()
{
    return Paymob_Save_Pixel_Settings::save_paymob_pixel_settings();
}


add_action('wp_ajax_update_pixel_data', 'update_pixel_data');
add_action('wp_ajax_nopriv_update_pixel_data', 'update_pixel_data');
function update_pixel_data()
{
    return Paymob_Update_Pixel_Data::update_pixel_data();
}

add_action('wp_ajax_create_order', 'create_order');
add_action('wp_ajax_nopriv_create_order', 'create_order');

function create_order() {
    // Check for nonce for security
    check_ajax_referer('update_checkout', 'security');
    try {
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- inside create order' );
        // Get cart data
        $cart = WC()->cart->get_cart();
        if (empty($cart)) {
        Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- cart empty' );
            wp_send_json_error(['message' => 'Cart is empty.']);
            return;
        }
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' ---------after 1st if' );

        // Reuse the pending Pixel order for this intention when present — avoids duplicate
        // Woo orders / Paymob "same merchant id" errors on double Place Order.
        $order              = null;
        $pixel_identifier   = WC()->session ? WC()->session->get( 'pixel_identifier' ) : '';
        $existing_order_id  = 0;
        if ( $pixel_identifier ) {
            global $wpdb;
            $existing_order_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s ORDER BY id DESC LIMIT 1",
                    $pixel_identifier
                )
            );
            if ( $existing_order_id > 0 ) {
                $maybe = wc_get_order( $existing_order_id );
                if ( $maybe && in_array( $maybe->get_status(), array( 'pending', 'pending-payment', 'failed', 'on-hold' ), true ) ) {
                    $order = $maybe;
                    Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log', ' --------- create_order reusing order #' . $existing_order_id );
                }
            }
        }

        if ( ! $order ) {
            $order = wc_create_order();
        } else {
            // Refresh line items from current cart.
            foreach ( $order->get_items() as $item_id => $item ) {
                $order->remove_item( $item_id );
            }
            foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
                $order->remove_item( $item_id );
            }
            foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
                $order->remove_item( $item_id );
            }
            foreach ( $order->get_items( 'coupon' ) as $item_id => $item ) {
                $order->remove_item( $item_id );
            }
            foreach ( $order->get_items( 'tax' ) as $item_id => $item ) {
                $order->remove_item( $item_id );
            }
        }

        // Add products to the order
        foreach ($cart as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $order->add_product(wc_get_product($product_id), $quantity, [
                'subtotal' => $cart_item['line_subtotal'],
                'total'    => $cart_item['line_total'],
            ]);
        }
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- after for cart ietms' );
        // Add applied coupons
        $applied_coupons = WC()->cart->get_applied_coupons();
        if (!empty($applied_coupons)) {
            foreach ($applied_coupons as $coupon_code) {
                $coupon = new WC_Coupon($coupon_code);
                $order->apply_coupon($coupon);
            }
        }

        // Add fees
        $fees = WC()->cart->get_fees();
        foreach ($fees as $fee) {
            $order->add_fee([
                'name'      => $fee->name,
                'amount'    => $fee->amount,
                'tax_class' => $fee->taxable ? $fee->tax_class : '',
            ]);
        }
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- after fees for' );
      
       // Add taxes to the order
        $cart_taxes = WC()->cart->get_cart_contents_taxes(); // Cart item taxes
        $shipping_taxes = WC()->cart->get_shipping_taxes();  // Shipping taxes

        // Combine taxes
        $all_taxes = array_replace_recursive($cart_taxes, $shipping_taxes);

        foreach ($all_taxes as $tax_rate_id => $tax_amount) {
            if ($tax_amount > 0) {
                $tax_item = new WC_Order_Item_Tax();
                $tax_item->set_rate_id($tax_rate_id); // Set the tax rate ID
                $tax_item->set_tax_total($tax_amount); // Set the total tax amount
                $tax_item->set_label(WC_Tax::get_rate_label($tax_rate_id)); // Set the tax label

                // Add tax item to the order
                $order->add_item($tax_item);
            }
        }

        Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- after all tax' );
        // Add shipping
        $shipping = WC()->shipping();
        $shipping_methods = ( $shipping && is_object( $shipping ) ) ? $shipping->get_packages() : array();
        if (!empty($shipping_methods)) {
            foreach ($shipping_methods as $package) {
                foreach ($package['rates'] as $rate) {
                    $order->add_shipping($rate);
                }
            }
        }
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- after shipping' );
        // Check if customer data exists
        if (empty(WC()->customer)) {
        Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- inside wc customer' );
            wp_send_json_error(['message' => 'Billing data is not defined.']);
            return;
        }
Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- after wc customer' );
        // Add billing and shipping details
        $order->set_address([
            'first_name' => WC()->customer->get_billing_first_name(),
            'last_name'  => WC()->customer->get_billing_last_name(),
            'company'    => WC()->customer->get_billing_company(),
            'address_1'  => WC()->customer->get_billing_address_1(),
            'address_2'  => WC()->customer->get_billing_address_2(),
            'city'       => WC()->customer->get_billing_city(),
            'state'      => WC()->customer->get_billing_state(),
            'postcode'   => WC()->customer->get_billing_postcode(),
            'country'    => WC()->customer->get_billing_country(),
            'phone'      => WC()->customer->get_billing_phone(),
            'email'      => WC()->customer->get_billing_email(),
        ], 'billing');

        $order->set_address([
            'first_name' => WC()->customer->get_shipping_first_name(),
            'last_name'  => WC()->customer->get_shipping_last_name(),
            'company'    => WC()->customer->get_shipping_company(),
            'address_1'  => WC()->customer->get_shipping_address_1(),
            'address_2'  => WC()->customer->get_shipping_address_2(),
            'city'       => WC()->customer->get_shipping_city(),
            'state'      => WC()->customer->get_shipping_state(),
            'postcode'   => WC()->customer->get_shipping_postcode(),
            'country'    => WC()->customer->get_shipping_country(),
        ], 'shipping');

        // Calculate totals and save the order
        $order->calculate_totals();

        // Payable amount is always derived from the Woo cart (+ Instant Refund fee only).
        $ir_enabled = WC()->session && '1' === (string) WC()->session->get( 'paymob_instant_refund_enabled', '0' );
        $fee_cents  = $ir_enabled && WC()->session ? (int) WC()->session->get( 'paymob_instant_refund_fee_cents' ) : 0;
        $trusted    = paymob_pixel_trusted_payable_cents( $fee_cents, $ir_enabled );
        $final_cents    = $trusted['final_cents'];
        $discount_cents = 0;
        $fee_cents      = $trusted['fee_cents'];
        $meta           = paymob_pixel_cents_meta();
        $div            = max( 1, (int) $meta['cents'] );

        if ( $final_cents > 0 ) {
            paymob_pixel_apply_order_amounts(
                $order,
                $final_cents,
                $discount_cents,
                $ir_enabled ? $fee_cents : 0,
                $ir_enabled
            );
            if ( WC()->session ) {
                WC()->session->set( 'paymob_final_cents', $final_cents );
                WC()->session->set( 'paymob_discount_cents', 0 );
                WC()->session->set( 'paymob_instant_refund_fee_cents', $fee_cents );
            }
            Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log', ' --------- create_order synced Pixel total ' . $order->get_total() . ' discount ' . $order->get_discount_total() );
        }

        $order->set_payment_method( 'paymob-pixel' );
        // Required for return/callback validateOrderInfo (payment_method must match PaymobPaymentId).
        $order->update_meta_data( 'PaymobPaymentId', 'paymob-pixel' );
        if ( WC()->session ) {
            $intention_id = WC()->session->get( 'PaymobIntentionId' );
            $cents_amount = WC()->session->get( 'PaymobCentsAmount' );
            if ( ! empty( $intention_id ) ) {
                $order->update_meta_data( 'PaymobIntentionId', $intention_id );
            }
            if ( ! empty( $cents_amount ) ) {
                $order->update_meta_data( 'PaymobCentsAmount', $cents_amount );
            } elseif ( $final_cents > 0 ) {
                $order->update_meta_data( 'PaymobCentsAmount', $final_cents );
            }
        }
        $order->save();
        Paymob_Pixel_Checkout::update_paymob_intention_with_orderID($order->get_id(),WC()->session->get('cs'), WC()->session->get('pixel_identifier'));
        // Keep intention amount on Paymob aligned with discounted Woo total (confirmation modal).
        if ( function_exists( 'Paymob_Pixel_Update_Intention' ) || class_exists( 'Paymob_Pixel_Update_Intention' ) ) {
            Paymob_Pixel_Update_Intention::update_intention( $order->get_id(), $order );
        }
        Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- oid'.$order->get_id(). ' pxl idn '. WC()->session->get('pixel_identifier') );
        $session = WC()->session;
        $session->__unset('order_id');
        $session->set( 'order_id',  WC()->session->get('pixel_identifier'));
        Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- merchant oid from session'.$session->get( 'order_id'));
        // Return success response
        wp_send_json_success([
            'message'  => 'Order created successfully!',
            'order_id' => $order->get_id(),
            'total'    => $order->get_total(),
        ]);

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'No order created.'));
    }
}

add_action('wp_enqueue_scripts', 'enqueue_paymob_pixel_checkout');

function enqueue_paymob_pixel_checkout()
{
    if (is_checkout()) {
        return Paymob_Pixel_Checkout::enqueue_paymob_pixel_checkout();
    }
}

add_action( 'admin_enqueue_scripts', 'enqueue_paymob_pixel_styles' );
function enqueue_paymob_pixel_styles() {

    return Paymob_Pixel_Style::enqueue_paymob_pixel_styles();
}

// Register AJAX action for retrieving the order ID
add_action('wp_ajax_get_order_id_from_session', 'get_order_id_from_session');
add_action('wp_ajax_nopriv_get_order_id_from_session', 'get_order_id_from_session'); // For non-logged-in users too
function get_order_id_from_session() {
    // Verify AJAX request and get data
    check_ajax_referer('update_checkout', 'security');
    // Retrieve the order ID from the session
    $order_id = WC()->session->get('order_id');
    if ($order_id) {
        return wp_send_json_success(array('order_id' => $order_id));
    } else {
        return wp_send_json_error(array('message' => 'No order ID found in session.'));
    }
}


add_action('wp_ajax_paymob_apply_discount', 'paymob_apply_discount');
add_action('wp_ajax_nopriv_paymob_apply_discount', 'paymob_apply_discount');

/**
 * Convert a major-unit amount to Paymob minor units without float drift
 * (e.g. avoid intval(round(x,2)*100) turning 0.5-style values into off-by-one).
 *
 * @param float $amount            Amount in major units.
 * @param int   $cents_multiplier  100 (EGP) or 1000 (OMR).
 * @return int
 */
function paymob_pixel_amount_to_cents( $amount, $cents_multiplier = 100 ) {
	return (int) round( (float) $amount * (int) $cents_multiplier );
}

/**
 * Minor-unit divisor for the active Paymob account country.
 *
 * @return array{cents:int,precision:int}
 */
function paymob_pixel_cents_meta() {
	$paymob_options = get_option( 'woocommerce_paymob-main_settings', array() );
	$sec_key        = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
	$country        = class_exists( 'Paymob' ) ? Paymob::getCountryCode( $sec_key ) : '';
	$cents          = ( 'omn' === $country ) ? 1000 : 100;
	$precision      = ( 'omn' === $country ) ? 3 : 2;

	return array(
		'cents'     => $cents,
		'precision' => $precision,
	);
}

/**
 * Convert Paymob minor units → major units with currency precision.
 * Never use round($cents/100) without precision (that turns 0.50 into 1).
 *
 * @param int      $cents   Amount in minor units.
 * @param int|null $divisor Optional override (100 / 1000).
 * @return float
 */
function paymob_pixel_cents_to_major( $cents, $divisor = null ) {
	$meta = paymob_pixel_cents_meta();
	$div  = null !== $divisor ? max( 1, (int) $divisor ) : max( 1, (int) $meta['cents'] );
	$prec = (int) $meta['precision'];

	return round( ( (int) $cents ) / $div, $prec );
}

/**
 * Filter callback: force Woo price decimals while Pixel amounts are written/shown.
 *
 * @param int $decimals Shop decimals.
 * @return int
 */
function paymob_pixel_force_decimals_filter( $decimals ) {
	if ( isset( $GLOBALS['paymob_pixel_force_decimals'] ) ) {
		return max( (int) $decimals, (int) $GLOBALS['paymob_pixel_force_decimals'] );
	}
	return (int) $decimals;
}

/**
 * Temporarily force WooCommerce price decimals so 0.50 is not rounded to 1
 * when the shop is configured with 0 decimal places.
 *
 * @param int|null $precision Paymob precision (2 or 3).
 */
function paymob_pixel_begin_precise_amounts( $precision = null ) {
	if ( null === $precision ) {
		$precision = paymob_pixel_cents_meta()['precision'];
	}
	$GLOBALS['paymob_pixel_force_decimals'] = max( 2, (int) $precision );
	if ( ! has_filter( 'wc_get_price_decimals', 'paymob_pixel_force_decimals_filter' ) ) {
		add_filter( 'wc_get_price_decimals', 'paymob_pixel_force_decimals_filter', 1000 );
	}
}

/**
 * End force-decimals scope started by paymob_pixel_begin_precise_amounts().
 */
function paymob_pixel_end_precise_amounts() {
	unset( $GLOBALS['paymob_pixel_force_decimals'] );
}

/**
 * Server-side cart total in minor units (never trust the browser for this).
 *
 * @return int
 */
function paymob_pixel_server_cart_cents() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	WC()->cart->calculate_totals();
	$meta = paymob_pixel_cents_meta();
	return paymob_pixel_amount_to_cents( WC()->cart->get_total( 'edit' ), max( 1, (int) $meta['cents'] ) );
}

/**
 * Build a trusted payable amount from the WooCommerce cart.
 *
 * Client-supplied discount reductions are ignored (price-manipulation hardening).
 * Instant Refund may increase the total when explicitly enabled, with a fee cap.
 *
 * @param int  $fee_cents   Requested Instant Refund fee in minor units.
 * @param bool $ir_enabled  Whether Instant Refund is enabled for this checkout.
 * @return array{original_cents:int,discount_cents:int,fee_cents:int,final_cents:int}
 */
function paymob_pixel_trusted_payable_cents( $fee_cents = 0, $ir_enabled = false ) {
	$cart_cents = paymob_pixel_server_cart_cents();
	$fee_cents  = $ir_enabled ? max( 0, (int) $fee_cents ) : 0;
	if ( $cart_cents > 0 && $fee_cents > $cart_cents ) {
		$fee_cents = $cart_cents;
	}

	return array(
		'original_cents' => $cart_cents,
		'discount_cents' => 0,
		'fee_cents'      => $fee_cents,
		'final_cents'    => max( 0, $cart_cents + $fee_cents ),
	);
}

/**
 * Format an amount using Paymob currency precision (ignores shop 0-decimal rounding).
 *
 * @param float  $amount   Major-unit amount.
 * @param string $currency Currency code.
 * @return string
 */
function paymob_pixel_format_price( $amount, $currency = '' ) {
	$meta = paymob_pixel_cents_meta();
	$args = array(
		'decimals' => (int) $meta['precision'],
	);
	if ( $currency ) {
		$args['currency'] = $currency;
	}
	return wc_price( (float) $amount, $args );
}

/**
 * Write final / discount / IR fee onto a Woo order using exact cents
 * (avoids shop decimal rounding that turns 4.50→5 and 0.50→1).
 *
 * @param WC_Order $order          Order.
 * @param int      $final_cents    Final payable amount in minor units.
 * @param int      $discount_cents Discount value in minor units.
 * @param int      $fee_cents      Instant Refund fee in minor units.
 * @param bool     $ir_enabled     Whether Instant Refund was applied.
 */
function paymob_pixel_apply_order_amounts( WC_Order $order, $final_cents = 0, $discount_cents = 0, $fee_cents = 0, $ir_enabled = false ) {
	$meta = paymob_pixel_cents_meta();
	paymob_pixel_begin_precise_amounts( $meta['precision'] );

	$final_cents    = (int) $final_cents;
	$discount_cents = (int) $discount_cents;
	$fee_cents      = (int) $fee_cents;

	try {
		if ( $final_cents > 0 ) {
			$order->set_total( paymob_pixel_cents_to_major( $final_cents, $meta['cents'] ) );
			$order->update_meta_data( '_paymob_paid_amount_cents', $final_cents );
			$order->update_meta_data( '_paymob_pixel_total_synced', 1 );
		}
		if ( $discount_cents > 0 ) {
			$order->set_discount_total( paymob_pixel_cents_to_major( $discount_cents, $meta['cents'] ) );
			$order->update_meta_data( '_paymob_discount_amount_cents', $discount_cents );
			$order->update_meta_data( '_paymob_discount_applied', 1 );
		}
		if ( $ir_enabled && $fee_cents > 0 ) {
			$order->update_meta_data( '_paymob_instant_refund_fees', $fee_cents );
			$order->update_meta_data( '_paymob_instant_refund', 1 );
		}
	} finally {
		paymob_pixel_end_precise_amounts();
	}
}

function paymob_apply_discount() {
    check_ajax_referer('update_checkout', 'security');

    $meta = paymob_pixel_cents_meta();
    $div  = max( 1, (int) $meta['cents'] );
    $prec = (int) $meta['precision'];

    $fee_cents              = isset( $_POST['instant_refund_fee_cents'] ) ? intval( $_POST['instant_refund_fee_cents'] ) : 0;
    $instant_refund_enabled = paymob_pixel_post_flag( 'instant_refund_enabled' );
    if ( ! $instant_refund_enabled ) {
        $fee_cents = 0;
    }

    // Never trust client final/discount for the payable amount (retest Critical #3).
    $trusted = paymob_pixel_trusted_payable_cents( $fee_cents, $instant_refund_enabled );
    if ( $trusted['final_cents'] <= 0 ) {
        wp_send_json_error( array( 'message' => 'Cart total unavailable' ) );
    }

    $original_cents = $trusted['original_cents'];
    $discount_cents = $trusted['discount_cents'];
    $final_cents    = $trusted['final_cents'];
    $fee_cents      = $trusted['fee_cents'];

    $original           = paymob_pixel_cents_to_major( $original_cents, $div );
    $discount           = 0;
    $final              = paymob_pixel_cents_to_major( $final_cents, $div );
    $instant_refund_fee = paymob_pixel_cents_to_major( $fee_cents, $div );

    $original = round( (float) $original, $prec );
    $final    = round( (float) $final, $prec );
    $instant_refund_fee = round( (float) $instant_refund_fee, $prec );

    if ( WC()->session ) {
        WC()->session->set( 'paymob_original_amount', $original );
        WC()->session->set( 'paymob_discount', $discount );
        WC()->session->set( 'paymob_final_total', $final );
        WC()->session->set( 'paymob_instant_refund_fee', max( 0, $instant_refund_fee ) );
        WC()->session->set( 'paymob_instant_refund_enabled', $instant_refund_enabled ? '1' : '0' );
        WC()->session->set( 'paymob_original_cents', max( 0, $original_cents ) );
        WC()->session->set( 'paymob_discount_cents', max( 0, $discount_cents ) );
        WC()->session->set( 'paymob_final_cents', max( 0, $final_cents ) );
        WC()->session->set( 'paymob_instant_refund_fee_cents', max( 0, $fee_cents ) );
    }

    wp_send_json_success(
        array(
            'original'               => $original,
            'discount'               => $discount,
            'instant_refund_fee'     => max( 0, $instant_refund_fee ),
            'instant_refund_enabled' => $instant_refund_enabled,
            'final'                  => $final,
            'original_cents'         => max( 0, $original_cents ),
            'discount_cents'         => max( 0, $discount_cents ),
            'final_cents'            => max( 0, $final_cents ),
            'instant_refund_fee_cents' => max( 0, $fee_cents ),
            'session'                => array(
                'orig'  => WC()->session->get( 'paymob_original_amount' ),
                'disc'  => WC()->session->get( 'paymob_discount' ),
                'fee'   => WC()->session->get( 'paymob_instant_refund_fee' ),
                'final' => WC()->session->get( 'paymob_final_total' ),
                'final_cents' => WC()->session->get( 'paymob_final_cents' ),
            ),
        )
    );
}

add_action( 'wp_ajax_paymob_clear_discount', 'paymob_clear_pixel_checkout_adjustments' );
add_action( 'wp_ajax_nopriv_paymob_clear_discount', 'paymob_clear_pixel_checkout_adjustments' );

/**
 * Clear Pixel discount / Instant Refund session keys (and optionally the active intention).
 *
 * @param bool $invalidate_intention When true, also drop client secret so the next load POSTs fresh.
 */
function paymob_pixel_clear_discount_session( $invalidate_intention = false ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	WC()->session->__unset( 'paymob_original_amount' );
	WC()->session->__unset( 'paymob_discount' );
	WC()->session->__unset( 'paymob_final_total' );
	WC()->session->__unset( 'paymob_instant_refund_fee' );
	WC()->session->__unset( 'paymob_instant_refund_enabled' );
	WC()->session->__unset( 'paymob_original_cents' );
	WC()->session->__unset( 'paymob_discount_cents' );
	WC()->session->__unset( 'paymob_final_cents' );
	WC()->session->__unset( 'paymob_instant_refund_fee_cents' );

	if ( $invalidate_intention ) {
		WC()->session->__unset( 'cs' );
		WC()->session->__unset( 'pixel_identifier' );
		WC()->session->__unset( 'PaymobIntentionId' );
		WC()->session->__unset( 'PaymobCentsAmount' );
		WC()->session->__unset( 'intention_order_id' );
		WC()->session->__unset( 'order_id' );
	}
}

/**
 * After a successful Pixel payment, drop intention + discount session so the next
 * checkout cannot reuse a paid client secret ("already paid" on Place Order).
 */
function paymob_pixel_clear_session_after_payment() {
	if ( function_exists( 'paymob_pixel_clear_discount_session' ) ) {
		paymob_pixel_clear_discount_session( true );
	}
}

/**
 * Belt-and-suspenders: clear paid Pixel CS when the customer lands on thank-you.
 */
add_action( 'woocommerce_thankyou', 'paymob_pixel_thankyou_clear_session', 5 );
function paymob_pixel_thankyou_clear_session( $order_id ) {
	if ( ! $order_id ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	$method = $order->get_payment_method();
	if ( 'paymob-pixel' !== $method && false === strpos( (string) $method, 'paymob' ) ) {
		return;
	}
	paymob_pixel_clear_session_after_payment();
}

/**
 * Clear Pixel discount / Instant Refund session when leaving Pixel payment method,
 * after payment failure, or when the cart total changes.
 */
function paymob_clear_pixel_checkout_adjustments() {
	check_ajax_referer( 'update_checkout', 'security' );

	$invalidate = paymob_pixel_post_flag( 'invalidate_intention' );

	paymob_pixel_clear_discount_session( $invalidate );

	$cart_total = 0;
	$currency   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EGP';

	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->calculate_totals();
		$cart_total = (float) WC()->cart->get_total( 'edit' );
	}

	wp_send_json_success(
		array(
			'cleared'     => true,
			'cart_total'  => $cart_total,
			'currency'    => $currency,
			'total_html'  => function_exists( 'wc_price' ) ? wc_price( $cart_total ) : null,
		)
	);
}

/**
 * Build billing payload for Pixel intention create/update.
 *
 * @param array $billing_data Optional POST billing fields.
 * @return array
 */
function paymob_pixel_billing_payload( $billing_data = array() ) {
	$street = 'NA';
	if ( ! empty( $billing_data['address'] ) ) {
		$street = $billing_data['address'];
	} elseif ( ! empty( $billing_data['address_1'] ) ) {
		$street = $billing_data['address_1'];
		if ( ! empty( $billing_data['address_2'] ) ) {
			$street .= ' - ' . $billing_data['address_2'];
		}
	} elseif ( ! empty( $billing_data['address_2'] ) ) {
		$street = $billing_data['address_2'];
	}

	$customer = ( function_exists( 'WC' ) && WC()->customer ) ? WC()->customer : null;

	return array(
		'email'        => ! empty( $billing_data['email'] ) ? $billing_data['email'] : ( $customer ? $customer->get_billing_email() : 'NA' ),
		'first_name'   => ! empty( $billing_data['first_name'] ) ? $billing_data['first_name'] : ( $customer ? ( $customer->get_billing_first_name() ?: 'NA' ) : 'NA' ),
		'last_name'    => ! empty( $billing_data['last_name'] ) ? $billing_data['last_name'] : ( $customer ? ( $customer->get_billing_last_name() ?: 'NA' ) : 'NA' ),
		'street'       => ( 'NA' !== $street ) ? $street : ( $customer ? ( $customer->get_billing_address_1() ?: 'NA' ) : 'NA' ),
		'phone_number' => ! empty( $billing_data['phone'] ) ? $billing_data['phone'] : ( $customer ? ( $customer->get_billing_phone() ?: 'NA' ) : 'NA' ),
		'city'         => ! empty( $billing_data['city'] ) ? $billing_data['city'] : ( $customer ? ( $customer->get_billing_city() ?: 'NA' ) : 'NA' ),
		'country'      => ! empty( $billing_data['country'] ) ? $billing_data['country'] : ( $customer ? ( $customer->get_billing_country() ?: 'NA' ) : 'NA' ),
		'state'        => ! empty( $billing_data['state'] ) ? $billing_data['state'] : ( $customer ? ( $customer->get_billing_state() ?: 'NA' ) : 'NA' ),
		'postal_code'  => ! empty( $billing_data['postcode'] ) ? $billing_data['postcode'] : ( $customer ? ( $customer->get_billing_postcode() ?: 'NA' ) : 'NA' ),
	);
}

/**
 * Resolve the Paymob intention amount in minor units from session (preferred) or cart.
 *
 * @return int
 */
function paymob_pixel_session_amount_cents() {
	$ir_enabled = function_exists( 'WC' ) && WC()->session && '1' === (string) WC()->session->get( 'paymob_instant_refund_enabled', '0' );
	$fee_cents  = $ir_enabled && WC()->session ? (int) WC()->session->get( 'paymob_instant_refund_fee_cents', 0 ) : 0;
	$trusted    = paymob_pixel_trusted_payable_cents( $fee_cents, $ir_enabled );
	if ( $trusted['final_cents'] > 0 ) {
		return $trusted['final_cents'];
	}

	if ( function_exists( 'WC' ) && WC()->cart ) {
		WC()->cart->calculate_totals();
		$meta = paymob_pixel_cents_meta();
		return paymob_pixel_amount_to_cents( WC()->cart->get_total( 'edit' ), (int) $meta['cents'] );
	}

	return 0;
}

add_action( 'wp_ajax_paymob_sync_pixel_intention', 'paymob_sync_pixel_intention' );
add_action( 'wp_ajax_nopriv_paymob_sync_pixel_intention', 'paymob_sync_pixel_intention' );

/**
 * Store discounted Pixel totals in Woo session for create_order.
 * Does not PUT the discounted amount onto the active Pixel intention by default
 * (that caused Order amount to become the previous final and re-discount).
 */
function paymob_sync_pixel_intention() {
	check_ajax_referer( 'update_checkout', 'security' );

	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		wp_send_json_error( 'Session unavailable' );
	}

	// Prefer Instant Refund fee from POST/session, but never trust client final/discount.
	$ir_enabled = paymob_pixel_post_flag( 'instant_refund_enabled' )
		|| ( WC()->session && '1' === (string) WC()->session->get( 'paymob_instant_refund_enabled', '0' ) );
	$fee_cents  = isset( $_POST['instant_refund_fee_cents'] ) ? intval( $_POST['instant_refund_fee_cents'] ) : 0;
	if ( $fee_cents <= 0 && WC()->session ) {
		$fee_cents = (int) WC()->session->get( 'paymob_instant_refund_fee_cents', 0 );
	}
	$trusted = paymob_pixel_trusted_payable_cents( $fee_cents, $ir_enabled );
	if ( $trusted['final_cents'] <= 0 ) {
		wp_send_json_error( 'Cart total unavailable' );
	}

	$meta = paymob_pixel_cents_meta();
	WC()->session->set( 'paymob_original_cents', $trusted['original_cents'] );
	WC()->session->set( 'paymob_discount_cents', 0 );
	WC()->session->set( 'paymob_discount', 0 );
	WC()->session->set( 'paymob_final_cents', $trusted['final_cents'] );
	WC()->session->set( 'paymob_final_total', paymob_pixel_cents_to_major( $trusted['final_cents'], max( 1, (int) $meta['cents'] ) ) );
	WC()->session->set( 'paymob_instant_refund_enabled', $ir_enabled ? '1' : '0' );
	WC()->session->set( 'paymob_instant_refund_fee_cents', $trusted['fee_cents'] );

	$amount = $trusted['final_cents'];
	if ( $amount <= 0 ) {
		wp_send_json_error( 'Missing final amount' );
	}

	// Session-only by default. Charge amount is PUTted in Paymob_Pixel_Update_Intention at create_order.
	$do_put = paymob_pixel_post_flag( 'put_intention' );

	if ( ! $do_put ) {
		wp_send_json_success(
			array(
				'amount_cents' => $amount,
				'final'        => function_exists( 'paymob_pixel_cents_to_major' )
					? paymob_pixel_cents_to_major( $amount )
					: round( $amount / max( 1, (int) paymob_pixel_cents_meta()['cents'] ), (int) paymob_pixel_cents_meta()['precision'] ),
				'session_only' => true,
			)
		);
	}

	$cs = WC()->session->get( 'cs' );
	if ( empty( $cs ) ) {
		wp_send_json_error( 'Missing Pixel client secret' );
	}

	$billing_data = array();
	if ( ! empty( $_POST['billing_data'] ) && is_array( $_POST['billing_data'] ) ) {
		$billing_data = wc_clean( wp_unslash( $_POST['billing_data'] ) );
	}

	$paymob_options     = get_option( 'woocommerce_paymob-main_settings', array() );
	$sec_key            = isset( $paymob_options['sec_key'] ) ? $paymob_options['sec_key'] : '';
	$debug              = ! empty( $paymob_options['debug'] ) ? '1' : '0';
	$log_file           = Paymob::log_dir() . 'paymob-pixel.log';
	$intention_order_id = WC()->session->get( 'intention_order_id' );

	$data = array(
		'amount'       => $amount,
		'billing_data' => paymob_pixel_billing_payload( $billing_data ),
	);
	if ( ! empty( $intention_order_id ) ) {
		$data['accept_order_id'] = $intention_order_id;
	}

	$paymob_req = new Paymob( $debug, $log_file );
	$response   = $paymob_req->createIntention( $sec_key, $data, 'Pixel Sync Amount', $cs, 'PUT' );

	// Keep PaymobCentsAmount at cart base so remount does not treat final as original.
	if ( WC()->cart ) {
		WC()->cart->calculate_totals();
		$meta      = paymob_pixel_cents_meta();
		$cart_base = function_exists( 'paymob_pixel_amount_to_cents' )
			? paymob_pixel_amount_to_cents( WC()->cart->get_total( 'edit' ), $meta['cents'] )
			: (int) round( (float) WC()->cart->get_total( 'edit' ) * (int) $meta['cents'] );
		if ( $cart_base > 0 ) {
			WC()->session->set( 'PaymobCentsAmount', $cart_base );
		}
	}

	if ( empty( $response['success'] ) && empty( $response['cs'] ) && ! empty( $response['message'] ) ) {
		if ( (string) $response['message'] !== (string) $amount && ! is_numeric( $response['message'] ) ) {
			wp_send_json_error( $response['message'] );
		}
	}

	wp_send_json_success(
		array(
			'amount_cents' => $amount,
			'final'        => round( $amount / max( 1, (int) paymob_pixel_cents_meta()['cents'] ), (int) paymob_pixel_cents_meta()['precision'] ),
			'response'     => $response,
		)
	);
}