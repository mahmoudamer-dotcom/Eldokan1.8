<?php

class PaymobOrder {


	public $config;
	public $gateway;
	public $order;
	public $billing;

	public function __construct( $orderId, $config ) {
		$this->config = $config;
		$this->order  = self::getOrder( $orderId );
		$country      = Paymob::getCountryCode( $this->config->sec_key );
		$cents        = 100;
		$round        = 2;
		if ( 'omn' == $country ) {
			$cents = 1000;
		}

		$this->config->amount_cents = round( $this->order->get_total(), $round ) * $cents;

		$this->billing = array(
			'email'        => $this->order->get_billing_email(),
			'first_name'   => ( $this->order->get_billing_first_name() ) ? $this->order->get_billing_first_name() : 'NA',
			'last_name'    => ( $this->order->get_billing_last_name() ) ? $this->order->get_billing_last_name() : 'NA',
			'street'       => ( $this->order->get_billing_address_1() ) ? $this->order->get_billing_address_1() . ' - ' . $this->order->get_billing_address_2() : 'NA',
			'phone_number' => ( $this->order->get_billing_phone() ) ? $this->order->get_billing_phone() : 'NA',
			'city'         => ( $this->order->get_billing_city() ) ? $this->order->get_billing_city() : 'NA',
			'country'      => ( $this->order->get_billing_country() ) ? $this->order->get_billing_country() : 'NA',
			'state'        => ( $this->order->get_billing_state() ) ? $this->order->get_billing_state() : 'NA',
			'postal_code'  => ( $this->order->get_billing_postcode() ) ? $this->order->get_billing_postcode() : 'NA',
		);

		$this->gateway = new Paymob_Gateway();
	}

	public static function getOrder( $orderId ) {
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $orderId );
		} else {
			$order = new WC_Order( $orderId );
		}
		if ( empty( $order ) ) {
			die( 'can not verify order' );
		}
		return $order;
	}

	public function processOrder() {
		global $woocommerce;
		$this->order->add_order_note( __( 'Paymob : Awaiting Payment', 'paymob-for-woocommerce' ) );
		$this->order->save();
		if ( 'yes' == $this->config->empty_cart ) {
			$woocommerce->cart->empty_cart();
		}
	}

	public function throwErrors( $error ) {
		if ( class_exists( 'Paymob_Error_Logs' ) ) {
			Paymob_Error_Logs::add( $error, 'order', 'gateway' );
		}
		if ( Paymob::filterVar( 'pay_for_order', 'REQUEST' ) ) {
			wc_add_notice( $error, 'error' );
		} else {
			throw new Exception( esc_html( $error ) );
		}
	}

	public function createPayment() {
		// Check if the order contains a subscription
		if (
			( function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($this->order) ) ||
			( function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($this->order) )
		) {
			return $this->createSubscriptionPayment();
		}
		else
		{
			if (sizeof(Paymob_Saved_Cards_Tokens::getUserTokens()) > 3) {
				$url = wc_get_endpoint_url('saved-cards', '', get_permalink(wc_get_page_id('myaccount')));
				$url = '<a href="' . $url . '">'.esc_html( __( 'Paymob Saved Cards', 'paymob-for-woocommerce' ) ).'</a>';
				$url = esc_html( __( 'Please remove your cards from', 'paymob-for-woocommerce' ) ).' ' . $url . ' '.esc_html( __( 'to complete your purchase', 'paymob-for-woocommerce' ) );
				$msg = esc_html( __( 'Ops,Max number of card tokens is 3.', 'paymob-for-woocommerce' ) ).'<br>'.$url;
				return [ 'message' => $msg ];
			} else {
			
				$totalAmount = (int) (string) $this->config->amount_cents;
				$itemsArr    = null;
				if ( 'yes' == $this->config->has_items ) {
					$items       = $this->getInvoiceItems();
					$itemsArr    = $items['items'];
					$totalAmount = $items['total'];
				}
				$data = array(
					'amount'            => $totalAmount,
					'currency'          => $this->order->get_currency(),
					'payment_methods'   => $this->getIntegrationIds(),
					'billing_data'      => $this->billing,
					'expires_at'        => $this->getExpiryTime(),
					'extras'            => array( 'merchant_intention_id' => $this->order->get_id() . '_' . time() ),
					'special_reference' => $this->order->get_id() . '_' . time(),
				);

				if ( ! empty( $items ) ) {
					$data['items'] = $itemsArr;
				}
				$data['card_tokens'] = Paymob_Saved_Cards_Tokens::getUserTokens();
				$data = apply_filters(
					'paymob_intention_data',
					$data,
					array(
						'order_id' => $this->order->get_id(),
						'context'  => 'createPayment',
					)
				);
				$paymobReq = new Paymob( $this->config->debug, $this->config->addlog );
				return $paymobReq->createIntention( $this->config->sec_key, $data, $this->order->get_id() ,$cs='','POST');
			}

		}
	}
	
	private function getIntegrationIds() {
		$omannet = strpos( $this->config->id, 'omannet' );
		if ( false !== $omannet ) {
			// get migs or vpc IDs
			$omannetArr[] = (int) $this->config->single_integration_id;
			$gateways     = PaymobAutoGenerate::get_db_gateways_data();
			foreach ( $gateways as $gateway ) {
				if ( ( false !== strpos( $gateway->gateway_id, 'vpc' ) || false !== strpos( $gateway->gateway_id, 'migs' ) )
				&& false === strpos( $gateway->gateway_id, 'apple-pay' )
				&& false === strpos( strtolower($gateway->gateway_id), 'google-pay' )
				&& '0' === $gateway->is_manual ) {
					$omannetArr[] = (int) $gateway->integration_id;
				}
			}
			return $omannetArr;
		}
		if ( 'paymob' != $this->config->id ) {
			return array( (int) $this->config->single_integration_id );
		}
		$paymobOptions   = get_option( 'woocommerce_paymob_settings' );
		$integration_id_hidden = explode( ',', $paymobOptions['integration_id_hidden'] );
		$matching_ids          = array();
		$integration_ids       = array();

		foreach ( $integration_id_hidden as $entry ) {
			$parts = explode( ':', $entry );
			$id    = trim( $parts[0] );
			if ( isset( $parts[2] ) ) {
				$currency = trim( substr( $parts[2], strpos( $parts[2], '(' ) + 1, -2 ) );
				if ( in_array( $id, $paymobOptions['integration_id'] ) && $currency === $this->order->get_currency() ) {
					$matching_ids[] = $id;
				}
			}
		}
		if ( ! empty( $matching_ids ) ) {
			foreach ( $matching_ids as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					array_push( $integration_ids, $id );
				}
			}
		}

		if ( empty( $integration_ids ) ) {
			foreach ( $paymobOptions['integration_id'] as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					array_push( $integration_ids, $id );
				}
			}
		}
		return $integration_ids;
	}

	public function getInvoiceItems() {
		$country = Paymob::getCountryCode( $this->config->sec_key );
		$cents   = 100;
		$round   = 2;
		if ( 'omn' === $country ) {
			$round = 3;
			$cents = 1000;
		}
		$Items  = array();
		$amount = 0;

		// Product items
		$items = $this->order->get_items();
		foreach ( $items as $item ) {
			$itemName          = esc_html( mb_strimwidth( $item->get_name(), 0, 45, '...' ) );
			$itemSubtotalPrice = $this->order->get_line_subtotal( $item, false );

			if ( ! is_numeric( $itemSubtotalPrice ) ) {
				/* translators: %s: product item name */
				$errMsg = sprintf( __( 'The "%s" Item has a non-numeric unit price.', 'paymob-for-woocommerce' ), $itemName );
				throw new Exception( esc_html( $errMsg ) );
			}

			$itemPrice = round( $itemSubtotalPrice / $item->get_quantity(), $round );
			$amount   += round( $itemPrice * $cents, $round ) * $item->get_quantity();
			$Items[]   = array(
				'name'     => $itemName,
				'quantity' => $item->get_quantity(),
				'amount'   => round( $itemPrice * $cents, $round ),  // Ensure it's an integer
			);
		}

		// Shipping
		$shipping = round( $this->order->get_shipping_total(), $round );
		if ( $shipping ) {
			$rateLabel = esc_html( mb_strimwidth( $this->order->get_shipping_method(), 0, 45, '...' ) );
			$amount   += round( $shipping * $cents, $round );
			$Items[]   = array(
				'name'     => $rateLabel,
				'quantity' => '1',
				'amount'   => round( $shipping * $cents, $round ),  // Ensure it's an integer
			);
		}

		// Discounts and Coupons
		$discount = round( $this->order->get_discount_total(), $round );
		if ( $discount ) {
			$amount -= round( $discount * $cents, $round );
			$Items[] = array(
				'name'     => __( 'Discount', 'paymob-for-woocommerce' ),
				'quantity' => '1',
				'amount'   => round( -$discount * $cents, $round ),  // Ensure it's an integer
			);
		}

		// Other Fees
		foreach ( $this->order->get_items( 'fee' ) as $item_fee ) {
			$total_fees = round( $item_fee->get_total(), $round );
			$amount    += round( $total_fees * $cents, $round );
			$Items[]    = array(
				'name'     => esc_html( mb_strimwidth( $item_fee->get_name(), 0, 45, '...' ) ),
				'quantity' => '1',
				'amount'   => round( $total_fees * $cents, $round ),  // Ensure it's an integer
			);
		}

		// Gift Cards
		foreach ( $this->order->get_items( 'pw_gift_card' ) as $line ) {
			$gifPrice   = round( $line->get_amount(), $round );
			$giftAmount = round( -$gifPrice * $cents, $round );
			$amount    -= $giftAmount;
			$Items[]    = array(
				'name'     => __( 'Gift Card', 'paymob-for-woocommerce' ),
				'quantity' => '1',
				'amount'   => $giftAmount,  // Ensure it's an integer
			);
		}
		// Tax
		$tax = round( $this->order->get_total() - ( $amount / $cents ), $round );
		if ( $tax ) {
			$amount += round( $tax * $cents, $round );
			$Items[] = array(
				'name'     => __( 'Remaining Cart Items Amount', 'paymob-for-woocommerce' ),
				'quantity' => '1',
				'amount'   => round( $tax * $cents, $round ),  // Ensure it's an integer
			);
		}
		return array(
			'items' => array_reverse( $Items ),
			'total' => $amount,
		);
	}
	public function getExpiryTime() {
		$expiryDate = '';

		if ( class_exists( 'WC_Admin_Settings' ) ) {
			$country         = Paymob::getCountryCode( $this->config->sec_key );
			$date            = new DateTime( 'now', new DateTimeZone( Paymob::getTimeZone( $country ) ) );
			$currentDateTime = $date->format( 'Y-m-d\TH:i:s\Z' );

			if ( 'egy' === $country ) {
				$currentDateTime = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '3 hours' ) );
			}

			$stock_minutes = get_option( 'woocommerce_hold_stock_minutes' ) ? get_option( 'woocommerce_hold_stock_minutes' ) : 60;

			$expiresAt  = strtotime( "$currentDateTime + $stock_minutes minutes" );
			$expiryDate = gmdate( 'Y-m-d\TH:i:s\Z', $expiresAt );
		}
		return $expiryDate;
	}

	public static function validateOrderInfo( $orderId, $PaymentId ) {
		if ( empty( $orderId ) || is_null( $orderId ) || false === $orderId || '' === $orderId ) {
			wp_die( esc_html( __( 'Ops. you are accessing wrong order.', 'paymob-for-woocommerce' ) ) );
		}
		$order = self::getOrder( $orderId );

		$paymentMethod = $order->get_payment_method();
		// Pixel AJAX create_order may omit PaymobPaymentId on older orders — treat paymob-pixel as valid.
		if ( empty( $PaymentId ) && 'paymob-pixel' === $paymentMethod ) {
			$order->update_meta_data( 'PaymobPaymentId', 'paymob-pixel' );
			$order->save();
			return $order;
		}
		if ( $PaymentId != $paymentMethod ) {
			die( esc_html( __( 'Ops. you are accessing wrong order.', 'paymob-for-woocommerce' ) ) );
		}
		return $order;
	}

	public function createSubscriptionPlans($orderId) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return array( 'error' => 'WooCommerce Subscriptions is not active.' );
		}
		$subscriptions = wcs_get_subscriptions_for_order($orderId, ['order_type' => 'any']);

		foreach ($subscriptions as $subscription) {
			foreach ($subscription->get_items() as $item_id => $item) {
				$product = $item->get_product();
				$product_id = $product ? $product->get_id() : 0;
				$quantity = $item->get_quantity();
				
				// Extract interval & period using post meta to support renewal orders
				$interval = get_post_meta($product_id, '_subscription_period_interval', true);
				$period   = get_post_meta($product_id, '_subscription_period', true);

				if (empty($interval) || empty($period)) {
					return ['error' => 'Subscription product metadata missing.'];
				}

				$subscription_price = floatval( $subscription->get_total() );;
				$sign_up_fee        = floatval(get_post_meta($product_id, '_subscription_sign_up_fee', true))*$quantity;
				$parent_order_id    = $subscription->get_parent_id();
				$parent_order       = wc_get_order($parent_order_id);
				$shipping_total     = $parent_order ? floatval($parent_order->get_shipping_total()) : 0;


				$has_trial = !empty($subscription->get_date('trial_end'));

				// if ($has_trial) {
				// 	$total = $sign_up_fee + $shipping_total; // Both are already 0 if not set
				// } else {
				// 	$total = $subscription_price + $sign_up_fee + $shipping_total;
				// }

				// // Apply discount from the parent order (if any)
				// if ($parent_order && $parent_order->get_discount_total() > 0) {
				// 	$discount_total = floatval($parent_order->get_discount_total());
				// 	$total -= $discount_total;
				// }
				$total = $parent_order ? floatval( $parent_order->get_total() ) : 0;
				$total = max(0.1, $total);
				// Currency conversion config
				$country = Paymob::getCountryCode($this->config->sec_key);
				$cents   = ($country === 'omn') ? 1000 : 100;
				$round   = ($country === 'omn') ? 3 : 2;

				$subscription_price_cents = round($subscription_price, $round) * $cents;
				$start_date_raw = $subscription->get_date('trial_end') ?: $subscription->get_date('start');
				$start_date = gmdate( 'Y-m-d', strtotime( $start_date_raw ) );
				$use_transaction_amount=false;
				// Set to null if the date is today
				if ( $start_date === gmdate( 'Y-m-d' ) ) {
					$use_transaction_amount=true;
					$start_date = null; // fallback in case next_payment is also unavailable	
				}
				// Valid frequencies
				$valid_frequencies = [
					'week:1'  => 'Weekly',
					'month:1' => 'Monthly',
					'month:2' => 'BiMonthly',
					'month:3' => 'Quarterly',
					'month:6' => 'Half annual',
					// 'year:1' => 'Yearly',
				];

				$frequency_key = $period . ':' . $interval;

				if (!isset($valid_frequencies[$frequency_key])) {
					return ['error' => "Invalid subscription frequency: '{$interval} {$period}'. Allowed frequencies are: Weekly, Monthly, Quarterly, Half annual."];
				}

				$frequency = $this->convert_subscription_to_days($interval, $period);
				$subscription_length = get_post_meta($product_id, '_subscription_length', true);

				$number_of_deductions = null;
				if (!empty($subscription_length) && intval($subscription_length) > 0) {
					$frequency_in_days = $this->convert_subscription_to_days($interval, $period);
					$stop_in_days = $this->convert_subscription_to_days($subscription_length, $period);
					$number_of_deductions = floor($stop_in_days / $frequency_in_days);
				}

				// Dynamic reminder/retrial days based on frequency
				$frequency_defaults = [
					'week:1'  => ['reminder' => 1, 'retrial' => 7],
					'month:1' => ['reminder' => 3, 'retrial' => 30],
					'month:2' => ['reminder' => 5, 'retrial' => 30],
					'month:3' => ['reminder' => 7, 'retrial' => 45],
					'month:6' => ['reminder' => 10, 'retrial' => 60],
				];

				$defaults = $frequency_defaults[$frequency_key] ?? ['reminder' => 3, 'retrial' => 30];

				$reminder_days = $defaults['reminder'];
				$retrial_days  = $defaults['retrial'];

				$subscription_settings = get_option('woocommerce_paymob-subscription_settings', []);
				if (empty($subscription_settings['moto_integration_id'])) {
					return ['error' => 'You must Set Moto Integration ID'];
				}
				$moto_integration_id = $subscription_settings['moto_integration_id'];
				
				// var_dump($start_date);die;
				$data = [
					'frequency'              => $frequency,
					'name'                   => $product ? $product->get_name() : 'Paymob Subscription',
					'reminder_days'          => null,
					'retrial_days'           => null,
					'amount_cents'           => (int)(string)$subscription_price_cents,
					'is_active'              => true,
					'integration'            => $moto_integration_id,
					'webhook_url'            => add_query_arg(['wc-api' => 'paymob_callback'], home_url()),
					// 'webhook_url'            =>"https://webhook.site/237843cc-b5d1-47b7-a840-e704e096a1f4",
					'number_of_deductions'   => $number_of_deductions,
					'use_transaction_amount' => $use_transaction_amount,
				];

				$mainOptions = get_option('woocommerce_paymob-main_settings');
				$conf = [
					'apiKey' => $mainOptions['api_key'] ?? '',
					'pubKey' => $mainOptions['pub_key'] ?? '',
					'secKey' => $mainOptions['sec_key'] ?? '',
				];

				$paymobReq = new Paymob($this->config->debug, $this->config->addlog);
				$token = $paymobReq->authToken($conf);

				if (empty($token['token'])) {
					return ['error' => 'Unable to authenticate with Paymob.'];
				}

				$existing_plan_id = get_post_meta($product_id, '_paymob_plan_id', true);
				if(!empty($existing_plan_id))
				{
					$response = $paymobReq->updateSubscriptionPlan($token['token'], $conf['secKey'], $data,$existing_plan_id);
					// Handle the "subscription plan doesn't exist" message
					if (
						( is_object( $response ) && isset( $response->message ) && $response->message === "subscription plan doesn't exist" )
						|| 
						( is_array( $response ) && isset( $response['message'] ) && $response['message'] === "subscription plan doesn't exist" )
					) {
						// Fallback to creating the subscription plan
						$response = $paymobReq->createSubscriptionPlan( $token['token'], $conf['secKey'], $data );
					}
					
				}
				else{
					$response = $paymobReq->createSubscriptionPlan($token['token'], $conf['secKey'], $data);
				}
					

				if (!empty($response->id)) {
					$plan_id = $response->id;

					update_post_meta($product_id, '_paymob_plan_id', $plan_id);
					update_post_meta($product_id, '_paymob_start_date', $start_date ?? '');
					
					return [
						'subscription_id'         => $subscription->get_id(),
						'subscription_plan_id'    => $plan_id,
						'subscription_start_date' => $start_date,
						'amount_cents'            => round($total, $round) * $cents,
						'currency'                => $this->order->get_currency(),
					];
				} else {
					return ['error' => 'Failed to create subscription plan.'];
				}
				
			}
		}
		return ['error' => 'No subscription product found in order.'];
	}


	

	public function convert_subscription_to_days( $interval, $period ) {
	
		$interval = intval( $interval );
		switch ( $period ) {
			case 'day':
				return $interval;
			case 'week':
				return $interval * 7;
			case 'month':
				return $interval * 30;
			case 'year':
				return $interval * 365;
			default:
				return 0;
		}
	}

	public function createSubscriptionPayment(){
		
		$planData = $this->createSubscriptionPlans($this->order->get_id());

		if (!empty($planData['error'])) {
			return ['message' => $planData['error']];
		}
	   
		$country      = Paymob::getCountryCode( $this->config->sec_key );
		$cents   = 100;
		$round   = 2;
		if ( 'omn' === $country ) {
			$round = 3;
			$cents = 1000;
		}

		$totalAmount = max(0.1, $planData['amount_cents']);
		$totalAmount = round($totalAmount, $round);
		

		$data = array(
			'amount'                 => $totalAmount,
			'currency'               => $planData['currency'],
			'payment_methods'        => $this->get3DSIntegrationIds(),
			'billing_data'           => $this->billing,
			'expires_at'             => $this->getExpiryTime(),
			'subscription_plan_id'   => $planData['subscription_plan_id'],
			'card_tokens'            => Paymob_Saved_Cards_Tokens::getUserTokens(),
			'special_reference'      => $this->order->get_id() . '_' . time(),
			'extras'                 => ['merchant_intention_id' => $this->order->get_id() . '_' . time()],
		);
		// Conditionally add subscription_start_date only if present
		if (!empty($planData['subscription_start_date'])) {
			$data['subscription_start_date'] = $planData['subscription_start_date'];
		}
		$paymobReq = new Paymob($this->config->debug, $this->config->addlog);
		$response = $paymobReq->createIntention($this->config->sec_key, $data, $this->order->get_id(), $cs = '', 'POST');
		return $response;
		
		
	}

	private function get3DSIntegrationIds() {
		$settings = get_option('woocommerce_paymob-subscription_settings', []);
		$ds3_ids = isset($settings['ds3_integration_ids']) ? (array) $settings['ds3_integration_ids'] : [];
		// Sanitize and return as integers
		return array_filter(array_map('intval', $ds3_ids));
	}
}
