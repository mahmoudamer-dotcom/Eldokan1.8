<?php
/**
 * Paymob WooCommerce Class
 */
class Paymob_WooCommerce {

	/**
	 * Constructor
	 */
	public $gateway;
	public $id;
	public $hmac_hidden;
	public $debug;
	public $addlog;

	public function __construct( $id ) {
		$this->id      = $id;
		$this->gateway = ucwords( str_replace( '-', '_', $id ), '_' ) . '_Gateway';
		$this->debug   = '0';
		$this->addlog  = Paymob::log_dir() . 'paymob-auth.log';
		// filters
		add_filter( 'plugin_action_links_' . PAYMOB_PLUGIN, array( $this, 'add_plugin_links' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register' ), 0 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'paymob_add_fees_to_order_totals_display' ), 20, 2 );
		add_filter( 'woocommerce_get_formatted_order_total', array( $this, 'paymob_formatted_order_total' ), 20, 4 );
		add_filter( 'wc_price_args', array( $this, 'paymob_wc_price_args_force_decimals' ), 20 );
		add_action( 'woocommerce_admin_order_totals_after_discount', array( $this, 'paymob_admin_order_instant_refund_fee_row' ), 20 );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'paymob_admin_refund_non_refundable_notice' ), 20 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'paymob_admin_force_precise_discount_display' ), 5 );
		add_action( 'woocommerce_api_paymob_callback', array( $this, 'callback' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_enqueue_scripts' ) );
		add_action( 'admin_head', array( $this, 'hide_block_main_gateway' ) );
		$paymob_u_Options  = get_option( 'woocommerce_paymob_settings' );
		$this->hmac_hidden = isset( $paymob_u_Options['hmac_hidden'] ) ? sanitize_text_field( $paymob_u_Options['hmac_hidden'] ) : '';
	}

	/**
	 * Register the gateway to WooCommerce
	 */
	public function register( $gateways ) {
		$file = __DIR__ . '/../includes/gateways/class-gateway-' . $this->id . '.php';
		if ( ! file_exists( $file ) && class_exists( 'PaymobAutoGenerate' ) ) {
			PaymobAutoGenerate::ensure_generated_gateway_files();
		}
		if ( ! file_exists( $file ) ) {
			return $gateways;
		}
		include_once __DIR__ . '/../includes/gateways/class-paymob-payment.php';
		include_once $file;
		if ( ! isset( $gateways[ $this->id ] ) ) {
			$gateways[] = $this->gateway;
		}
		return $gateways;
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	public function add_plugin_links( $links ) {
		$paymobSetting = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob-main' ) . '">' . __( 'PayMob Settings', 'paymob-for-woocommerce' ) . '</a>';
		$plugin_links  = array( __( 'Paymob Settings', 'paymob-for-woocommerce' ) => $paymobSetting );
		return ( array_merge( $links, $plugin_links ) );
	}

	public function callback() {
		$this->gateway = new Paymob_Gateway();
		if ( Paymob::filterVar( 'REQUEST_METHOD', 'SERVER' ) === 'POST' ) {
			$this->callWebhookAction();
		} elseif ( Paymob::filterVar( 'REQUEST_METHOD', 'SERVER' ) === 'GET' ) {
			$this->callReturnAction();
		}
	}

	public function callWebhookAction() {
		$post_data = file_get_contents( 'php://input' );
		$json_data = json_decode( $post_data, true );
		$country = Paymob::getCountryCode( $this->gateway->sec_key );
		$url     = Paymob::getApiUrl( $country );
	
	    if(isset( $json_data['subscription_data'] )){
            $this->subscriptionWebhook( $json_data, $url, $country);
		}
		elseif ( isset( $json_data['type'] ) && Paymob::filterVar( 'hmac', 'REQUEST' ) && 'TRANSACTION' === $json_data['type'] ) {
			$this->acceptWebhook( $json_data, $url );

		}
		elseif(isset( $json_data['type'] ) && $json_data['obj']['payment_key_claims']['subscription_plan_id'] && 'TRANSACTION' === $json_data['type']){
            $this->subscriptionTransactionWebhook( $json_data, $url, $country );
		}
		elseif ( isset( $json_data['type'] ) && 'TOKEN' === $json_data['type'] ) {
			$addlog          = Paymob::log_dir()  . 'paymob-token.log';
			Paymob::addLogs( $this->gateway->debug, $addlog, ' In TOKEN REQUEST >>>> ', wp_json_encode( $json_data ) );
			$this->saveCardToken( $json_data );

		} else {

			$this->flashWebhook( $json_data, $url, $country );
		}
	}

	public function acceptWebhook( $json_data, $url ) {
		$obj     = $json_data['obj'];
		$type    = $json_data['type'];
		$orderId = substr( $obj['order']['merchant_order_id'], 0, -11 );
		$merchant_order_id = sanitize_text_field( (string) $obj['order']['merchant_order_id'] );
		if ( strpos( $orderId, 'pixel' ) !== false ) {
			global $wpdb;
			$orderId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s",
					$merchant_order_id
				)
			);

		}
		if ( Paymob::verifyHmac( $this->hmac_hidden, $json_data, null, Paymob::filterVar( 'hmac', 'REQUEST' ) ) ) {

			$order           = wc_get_order( $orderId );
			$PaymobPaymentId = $order->get_meta( 'PaymobPaymentId', true );
			$addlog          = Paymob::log_dir() . $PaymobPaymentId . '.log';
			Paymob::addLogs( $this->gateway->debug, $addlog, ' In Webhook action, for order# ' . $orderId, wp_json_encode( $json_data ) );
			$order  = PaymobOrder::validateOrderInfo( $orderId, $PaymobPaymentId );
			$status = $order->get_status();

			$integrationId = $obj['integration_id'];
			$type          = $obj['source_data']['type'] ?? '';
			$subType       = $obj['source_data']['sub_type'] ?? '';
			$transaction   = $obj['id'];
			$paymobId      = $obj['order']['id'];

			$msg = __( 'Paymob  Webhook for Order #', 'paymob-for-woocommerce' ) . $orderId;

			// Instant Refund / refunds from Paymob dashboard arrive after the order is paid.
			// Allow status updates for refund/void even when the Woo order is already processing/completed.
			$is_refund_event = ( ! empty( $obj['is_refunded'] ) || ! empty( $obj['is_refund'] ) );
			$is_void_event   = ( ! empty( $obj['is_voided'] ) || ! empty( $obj['is_void'] ) );
			if ( ! $is_refund_event && ! $is_void_event && 'pending' != $status && 'failed' != $status && 'on-hold' != $status ) {
				die( esc_html( "can not change status of order: $orderId" ) );
			}

			if ( $is_refund_event ) {
				$this->mark_order_refunded_from_paymob( $order, $obj, $msg, $addlog );
				$order->update_meta_data( 'PaymobTransactionId', $transaction );
				$order->save();
				die( esc_html( "Order refunded: $orderId" ) );
			}

			if (
				true  === $obj['success'] &&
				false === $obj['is_voided'] &&
				false === $obj['is_refunded'] &&
				false === $obj['pending'] &&
				false === $obj['is_void'] &&
				false === $obj['is_refund'] &&
				false === $obj['error_occured']
			) {
				$note = __( 'Paymob  Webhook: Transaction Approved', 'paymob-for-woocommerce' );
				$msg  = $msg . ' ' . $note;
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
				Paymob::addLogs( $this->gateway->debug, $addlog, 'aml fares accept ');
				$note .= "<br/>Payment Method ID: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
				$order->add_order_note( $note );
				$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
				$order->add_order_note( $note2);
				// Handle CAF logic
				$this->update_order_total_after_discount( $order, $obj );
				$this->handle_caf_logic( $order, $json_data );
				$this->handle_instant_refund_logic( $order, $obj);
				$this->scrub_stale_pixel_adjustments_from_transaction( $order, $obj );
				// Dashboard amount is the source of truth (avoids Woo 28 vs Paymob 29 mismatches).
				if ( ! empty( $obj['amount_cents'] ) ) {
					$country = Paymob::getCountryCode( $this->gateway->sec_key );
					$this->sync_order_total_from_paymob_cents( $order, (int) $obj['amount_cents'], 'omn' === $country ? 1000 : 100 );
				}
				$order->payment_complete( $orderId );


				$paymentMethod      = $order->get_payment_method();
				$paymentMethodTitle = 'Paymob - ' . ucwords( $type );
				$order->set_payment_method_title( $paymentMethodTitle );
			} else {
				$order->update_status( 'failed' );
				$note = __( 'Paymob Webhook: Payment is not completed ', 'paymob-for-woocommerce' );
				$msg  = $msg . ' ' . $note;
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
				$note .= "<br/>Payment Method ID: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
				$order->add_order_note( $note );
				$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
				$order->add_order_note( $note2);
			}
			$order->update_meta_data( 'PaymobTransactionId', $transaction );
			$order->update_meta_data( 'PaymobMerchantOrderID',$merchant_order_id);
			update_post_meta( $orderId, 'PaymobMerchantOrderID', $merchant_order_id );
            update_post_meta( $orderId, 'PaymobTransactionId', $transaction );

			$order->save();
			die( esc_html( "Order updated: $orderId" ) );
		} else {
			die( esc_html( "can not verify order: $orderId" ) );
		}
	}

	public function flashWebhook( $json_data, $url, $country ) {
		$orderId          = Paymob::getIntentionId( $json_data['intention']['extras']['creation_extras']['merchant_intention_id'] );
		$merchant_order_id = sanitize_text_field( (string) $json_data['intention']['extras']['creation_extras']['merchant_intention_id'] );
		if ( strpos( $orderId, 'pixel' ) !== false ) {
			global $wpdb;
			$orderId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s",
					$merchant_order_id
				)
			);

		}
		$order            = wc_get_order( $orderId );
		$OrderIntensionId = $order->get_meta( 'PaymobIntentionId', true );
		$OrderAmount      = $order->get_meta( 'PaymobCentsAmount', true );
		$PaymobPaymentId  = $order->get_meta( 'PaymobPaymentId', true );
		$addlog           = Paymob::log_dir() . $PaymobPaymentId . '.log';

		Paymob::addLogs( $this->gateway->debug, $addlog, ' In Webhook action, for order# ' . $orderId, wp_json_encode( $json_data ) );
	   
		if ( $OrderIntensionId != $json_data['intention']['id'] ) {
			die( esc_html( "intention ID is not matched for order: $orderId" ) );
		}

		if ( $OrderAmount != $json_data['intention']['intention_detail']['amount'] ) {
			die( esc_html( "intension amount are not matched for order : $orderId" ) );
		}

		$cents = 100;
		if ( 'omn' == $country ) {
			$cents = 1000;
		}
		if (
			! Paymob::verifyHmac(
				$this->hmac_hidden,
				$json_data,
				array(
					'id'     => $OrderIntensionId,
					'amount' => $OrderAmount,
					'cents'  => $cents,
				)
			)
		) {
			die( esc_html( "can not verify order: $orderId" ) );
		}

		$order  = PaymobOrder::validateOrderInfo( $orderId, $PaymobPaymentId );
		$status = $order->get_status();
		$msg    = __( 'Paymob  Webhook for Order #', 'paymob-for-woocommerce' ) . $orderId;

		if ( ! empty( $json_data['transaction'] ) ) {
			$trans         = $json_data['transaction'];
			$integrationId = $json_data['transaction']['integration_id'];
			$type          = $json_data['transaction']['source_data']['type'];
			$subType       = $json_data['transaction']['source_data']['sub_type'];
			$transaction   = $json_data['transaction']['id'] ?? null;

			$is_refund_event = ( ! empty( $trans['is_refunded'] ) || ! empty( $trans['is_refund'] ) );
			$is_void_event   = ( ! empty( $trans['is_voided'] ) || ! empty( $trans['is_void'] ) );

			// Allow Instant Refund / refund webhooks after the order is already paid.
			if ( ! $is_refund_event && ! $is_void_event && 'pending' != $status && 'failed' != $status && 'on-hold' != $status ) {
				die( esc_html( "can not change status of order: $orderId" ) );
			}

			if ( $is_refund_event ) {
				$this->mark_order_refunded_from_paymob( $order, $trans, $msg, $addlog );
				if ( $transaction ) {
					$order->update_meta_data( 'PaymobTransactionId', $transaction );
				}
				$order->save();
				die( esc_html( "Order refunded: $orderId" ) );
			}

			if (
				true === $trans['success'] &&
				false === $trans['is_voided'] &&
				false === $trans['is_refunded'] &&
				false === $trans['is_capture']
			) {
				$note = __( 'Paymob  Webhook: Transaction Approved', 'paymob-for-woocommerce' );
				$msg  = $msg . ' ' . $note;
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
				$transaction = $json_data['transaction']['id'];
				$paymobId    = $json_data['transaction']['order']['id'];
				$note       .= "<br/>Payment Method IDs: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
				$order->add_order_note( $note );
				$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
				$order->add_order_note( $note2);
				$this->update_order_total_after_discount( $order, $json_data );
				$this->handle_caf_logic( $order, $json_data);
				$this->handle_instant_refund_logic( $order, $json_data);
				if ( ! empty( $trans['amount_cents'] ) ) {
					$this->sync_order_total_from_paymob_cents( $order, (int) $trans['amount_cents'], $cents );
				}

				$order->payment_complete( $orderId );
				$paymentMethod = $order->get_payment_method();

				$paymentMethodTitle = 'Paymob - ' . ucwords( $type );
				$order->set_payment_method_title( $paymentMethodTitle );

			} elseif (
				false === $trans['success'] &&
				true === $trans['is_refunded'] &&
				false === $trans['is_voided'] &&
				false === $trans['is_capture']
			) {
				$order->update_status( 'refunded' );
				$note = __( 'Paymob  Webhook: Payment Refunded', 'paymob-for-woocommerce' );
				$msg  = $msg . ' ' . $note;
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
				$order->add_order_note( $note );
			} elseif (
				false === $trans['success'] &&
				false === $trans['is_voided'] &&
				false === $trans['is_refunded'] &&
				false === $trans['is_capture']
			) {
				$order->update_status( 'failed' );
				$note = __( 'Paymob Webhook: Payment is not completed ', 'paymob-for-woocommerce' );
				$msg  = $msg . ' ' . $note;
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
				$transaction = $json_data['transaction']['id'];
				$paymobId    = $json_data['transaction']['order']['id'];
				$note       .= "<br/>Payment Method ID: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
				$order->add_order_note( $note );
				$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
				$order->add_order_note( $note2);
			}
			$order->update_meta_data( 'PaymobTransactionId', $transaction );
			$order->update_meta_data( 'PaymobMerchantOrderID',$merchant_order_id);
			update_post_meta( $orderId, 'PaymobMerchantOrderID', $merchant_order_id );
            update_post_meta( $orderId, 'PaymobTransactionId', $transaction );

			$order->save();
			die( esc_html( "Order updated: $orderId" ) );
		}
	}
	public function saveCardToken( $json_data ) {
		global $wpdb;

		$hmac = Paymob::filterVar( 'hmac', 'REQUEST' );
		$obj  = isset( $json_data['obj'] ) && is_array( $json_data['obj'] ) ? $json_data['obj'] : array();
		$token_str = ( isset( $obj['card_subtype'] ) ? $obj['card_subtype'] : '' )
			. ( isset( $obj['created_at'] ) ? $obj['created_at'] : '' )
			. ( isset( $obj['email'] ) ? $obj['email'] : '' )
			. ( isset( $obj['id'] ) ? $obj['id'] : '' )
			. ( isset( $obj['masked_pan'] ) ? $obj['masked_pan'] : '' )
			. ( isset( $obj['merchant_id'] ) ? $obj['merchant_id'] : '' )
			. ( isset( $obj['order_id'] ) ? $obj['order_id'] : '' )
			. ( isset( $obj['token'] ) ? $obj['token'] : '' );
		if ( empty( $hmac ) || hash_hmac( 'sha512', $token_str, $this->hmac_hidden ) !== $hmac ) {
			die( esc_html( 'can not verify order' ) );
		}

		$table_name = $wpdb->prefix . 'paymob_cards_token';
		$addlog     = Paymob::log_dir() . 'paymob-auth.log';
		Paymob::addLogs( $this->gateway->debug, $addlog, ' In save Card Token Webhook', wp_json_encode( $json_data ) );

		if ( empty( $obj['token'] ) || empty( $obj['masked_pan'] ) ) {
			Paymob::addLogs( $this->gateway->debug, $addlog, 'Token webhook missing token/masked_pan' );
			die( esc_html( 'Token payload incomplete' ) );
		}

		$email = '';
		if ( ! empty( $obj['email'] ) ) {
			$email = sanitize_email( $obj['email'] );
		} elseif ( ! empty( $obj['order']['shipping_data']['email'] ) ) {
			$email = sanitize_email( $obj['order']['shipping_data']['email'] );
		} elseif ( ! empty( $json_data['email'] ) ) {
			$email = sanitize_email( $json_data['email'] );
		}

		$user = $email ? get_user_by( 'email', $email ) : false;

		// Fallback: resolve Woo user from merchant order / pixel intention.
		if ( ! $user ) {
			$merchant_order_id = $obj['order']['merchant_order_id']
				?? ( $json_data['order']['merchant_order_id'] ?? '' );
			if ( $merchant_order_id ) {
				$order_id = Paymob::getIntentionId( $merchant_order_id );
				if ( false !== strpos( (string) $order_id, 'pixel' ) ) {
					$order_id = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s",
							$merchant_order_id
						)
					);
				}
				$order = $order_id ? wc_get_order( $order_id ) : false;
				if ( $order ) {
					$user_id = $order->get_user_id();
					if ( $user_id ) {
						$user = get_user_by( 'id', $user_id );
					}
					if ( ! $user && $order->get_billing_email() ) {
						$user  = get_user_by( 'email', $order->get_billing_email() );
						$email = $order->get_billing_email();
					}
				}
			}
		}

		if ( ! $user ) {
			Paymob::addLogs( $this->gateway->debug, $addlog, 'No User Found for token. email=' . $email );
			die( esc_html( 'No User Found with this email: ' . $email ) );
		}

		$card_subtype = ! empty( $obj['card_subtype'] ) ? $obj['card_subtype'] : 'CARD';
		$token        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}paymob_cards_token WHERE user_id = %d AND card_subtype = %s AND masked_pan = %s",
				$user->ID,
				$card_subtype,
				$obj['masked_pan']
			),
			OBJECT
		);

		if ( ! $token ) {
			$wpdb->insert(
				$table_name,
				array(
					'user_id'      => $user->ID,
					'token'        => $obj['token'],
					'masked_pan'   => $obj['masked_pan'],
					'card_subtype' => $card_subtype,
				)
			);
		} else {
			$wpdb->update(
				$table_name,
				array(
					'token' => $obj['token'],
				),
				array(
					'user_id'      => $user->ID,
					'card_subtype' => $card_subtype,
					'masked_pan'   => $obj['masked_pan'],
				)
			);
		}

		Paymob::addLogs( $this->gateway->debug, $addlog, 'Token Saved for user id ' . $user->ID . ' email ' . $email );
		die( esc_html( "Token Saved: user id: $user->ID, user email: " . $email ) );
	}

	public function subscriptionTransactionWebhook( $json_data, $url, $country ) {
		$obj               = $json_data['obj'];
		$type              = $json_data['type'];
		$orderId           = Paymob::getIntentionId( $json_data['obj']['order']['merchant_order_id'] );
		$merchant_order_id = $json_data['obj']['order']['merchant_order_id'];

		if ( ! Paymob::verifyHmac( $this->hmac_hidden, $json_data, null, Paymob::filterVar( 'hmac', 'REQUEST' ) ) ) {
			die( esc_html( "can not verify order: $orderId" ) );
		}
		
		$order            = wc_get_order( $orderId );
		$OrderIntensionId = $order->get_meta( 'PaymobIntentionId', true );
		$OrderAmount      = $order->get_meta( 'PaymobCentsAmount', true );
		$PaymobPaymentId  = $order->get_meta( 'PaymobPaymentId', true );
		$addlog           = Paymob::log_dir() . $PaymobPaymentId . '.log';

		Paymob::addLogs( $this->gateway->debug, $addlog, ' In Webhook action, for order# ' . $orderId, wp_json_encode( $json_data ) );
		$cents = 100;
		if ( 'omn' == $country ) {
			$cents = 1000;
		}
	  
		$order           = wc_get_order( $orderId );
		$PaymobPaymentId = $order->get_meta( 'PaymobPaymentId', true );
		$addlog          = Paymob::log_dir() . $PaymobPaymentId . '.log';
		Paymob::addLogs( $this->gateway->debug, $addlog, ' In Webhook action, for order# ' . $orderId, wp_json_encode( $json_data ) );
		$order  = PaymobOrder::validateOrderInfo( $orderId, $PaymobPaymentId );
		$status = $order->get_status();

		if ( 'pending' != $status && 'failed' != $status && 'on-hold' != $status ) {
			die( esc_html( "can not change status of order: $orderId" ) );
		}

		$integrationId = $obj['integration_id'];
		$type          = $obj['source_data']['type'];
		$subType       = $obj['source_data']['sub_type'];
		$transaction   = $obj['id'];
		$paymobId      = $obj['order']['id'];

		$msg = __( 'Paymob  Webhook for Order #', 'paymob-for-woocommerce' ) . $orderId;
		if (
			true ===  $obj['success'] &&
			false === $obj['is_voided'] &&
			false === $obj['is_refunded'] &&
			false === $obj['pending'] &&
			false === $obj['is_void'] &&
			false === $obj['is_refund'] &&
			false === $obj['error_occured']
		) {
			$note = __( 'Paymob  Webhook: Transaction Approved', 'paymob-for-woocommerce' );
			$msg  = $msg . ' ' . $note;
			Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
			$note .= "<br/>Payment Method ID: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
			$order->add_order_note( $note );
			$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
			$order->add_order_note( $note2);
			$order->payment_complete( $orderId );
			$paymentMethod      = $order->get_payment_method();
			$paymentMethodTitle = 'Paymob - ' . ucwords( $type );
			$order->set_payment_method_title( $paymentMethodTitle );
		} else {
			$order->update_status( 'failed' );
			$note = __( 'Paymob Webhook: Payment is not completed ', 'paymob-for-woocommerce' );
			$msg  = $msg . ' ' . $note;
			Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
			$note .= "<br/>Payment Method ID: { $integrationId } <br/>Transaction done by: { $type } / { $subType }</br> Transaction ID:  <b style='color:DodgerBlue;'>{ $transaction }</b></br> Order ID: <b style='color:DodgerBlue;'>{ $paymobId }</b> </br> <a href=' {$url} portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
			$order->add_order_note( $note );
			$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
			$order->add_order_note( $note2);
		}
		$order->update_meta_data( 'PaymobTransactionId', $transaction );
		$order->update_meta_data( 'PaymobMerchantOrderID',$merchant_order_id);
		update_post_meta( $orderId, 'PaymobMerchantOrderID', $merchant_order_id );
		update_post_meta( $orderId, 'PaymobTransactionId', $transaction );
		$order->save();
		die( esc_html( "Order updated: $orderId" ) );
	}

	public function subscriptionWebhook( $json_data, $url, $country ) {

		$subscription_data = $json_data['subscription_data'] ?? [];
		$trigger_type      = $json_data['trigger_type'] ?? '';
		$transaction_id    = intval( $json_data['transaction_id'] ?? 0 );
		$storedHmac        = $json_data['hmac'] ?? '';
		$is_subscription   = true;

		$normalized = strtolower( trim( $trigger_type ) );

		$is_success = ( $normalized === 'successful transaction' ||
		                $normalized ==="resumed"||
						$normalized ==="updated"||
						false !== strpos( $normalized, 'successful' ) );

		$is_failed  = ( $normalized === 'failed transaction' ||
						$normalized === 'failed overdue transaction' ||
						$normalized === 'suspended' ||
						$normalized === 'canceled'||
					    false !== strpos( $normalized, 'failed' ) );

		$is_failed_payment = $is_failed;

		
		$next_billing_str = $subscription_data['next_billing'] ?? null;
		$next_billing     = $next_billing_str ? strtotime( $next_billing_str ) : 0;
		$today            = strtotime( gmdate( 'Y-m-d' ) );

		// ===== LOG FILE =====
		$addlog = Paymob::log_dir() . 'paymob-subscription.log';

		Paymob::addLogs(
			$this->gateway->debug,
			$addlog,
			'Incoming Subscription Webhook',
			wp_json_encode( $json_data )
		);
		Paymob::addLogs(
			$this->gateway->debug,
			$addlog,
			'Trigger Evaluation',
			'normalized=' . $normalized .
			' | success=' . ( $is_success ? 'yes' : 'no' ) .
			' | failed=' . ( $is_failed_payment ? 'yes' : 'no' )
		);

		// ===== VERIFY HMAC =====
		if ( ! Paymob::verifyHmac( $this->hmac_hidden, $json_data, null, $storedHmac, $is_subscription ) ) {
			Paymob::addLogs( $this->gateway->debug, $addlog, 'Invalid HMAC – webhook rejected' );
			die( 'Invalid HMAC' );
		}

		// ===== TRANSACTION CHECK =====
		if ( empty( $transaction_id ) ) {
			Paymob::addLogs( $this->gateway->debug, $addlog, 'No transaction_id – skipping webhook' );
			die( 'No transaction id' );
		}

		if ( $this->paymob_renewal_exists( $transaction_id ) ) {
			Paymob::addLogs(
				$this->gateway->debug,
				$addlog,
				'Renewal already exists for transaction ' . $transaction_id
			);
			die( 'Renewal already exists' );
		}

		$initial_transaction = intval( $subscription_data['initial_transaction'] ?? 0 );
		if ( ! $initial_transaction || $initial_transaction === $transaction_id ) {
			Paymob::addLogs(
				$this->gateway->debug,
				$addlog,
				'Initial transaction webhook – no renewal needed'
			);
			die( 'Initial transaction' );
		}

		// ===== FIND ORIGINAL ORDER =====
		$orders = wc_get_orders([
			'limit'      => 1,
			'meta_key'   => 'PaymobTransactionId',
			'meta_value' => $initial_transaction,
			'return'     => 'objects',
		]);

		if ( empty( $orders ) ) {
			Paymob::addLogs(
				$this->gateway->debug,
				$addlog,
				'Original order not found for transaction ' . $initial_transaction
			);
			die( 'Original order not found' );
		}

		$order = $orders[0];

		Paymob::addLogs(
			$this->gateway->debug,
			$addlog,
			'Processing subscriptions for order #' . $order->get_id()
		);

		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			die( 'WooCommerce Subscriptions is not active' );
		}

		$subscriptions = wcs_get_subscriptions_for_order( $order, [ 'order_type' => 'any' ] );

		foreach ( $subscriptions as $subscription ) {

			Paymob::addLogs(
				$this->gateway->debug,
				$addlog,
				'Handling subscription #' . $subscription->get_id()
			);

			// ===== SUCCESSFUL PAYMENT =====
			if ( $is_success ) {

				Paymob::addLogs(
					$this->gateway->debug,
					$addlog,
					'Successful renewal detected – updating subscription & creating renewal order'
				);

				// Update dates ONLY on success
				$subscription->update_dates( array_filter([
					'start'        => ! empty( $subscription_data['starts_at'] )
						? gmdate( 'Y-m-d H:i:s', strtotime( $subscription_data['starts_at'] ) )
						: null,

					'next_payment' => ! empty( $subscription_data['next_billing'] )
						? gmdate( 'Y-m-d H:i:s', strtotime( $subscription_data['next_billing'] ) )
						: null,

					'end'          => ! empty( $subscription_data['ends_at'] )
						? gmdate( 'Y-m-d H:i:s', strtotime( $subscription_data['ends_at'] ) )
						: null,
				]) );

				$subscription->save();
				$renewal_order_id = $this->paymob_create_renewal_order(
					$subscription_data,
					$json_data,
					$subscription->get_id()
				);

				if ( $renewal_order_id ) {
					$subscription->add_order_note(
						'Paymob Renewal Successful. Order ID: ' . $renewal_order_id
					);

					Paymob::addLogs(
						$this->gateway->debug,
						$addlog,
						'Renewal order created: ' . $renewal_order_id
					);
				}

			// ===== FAILED PAYMENT =====
			} elseif ( $is_failed_payment ) {

				Paymob::addLogs(
					$this->gateway->debug,
					$addlog,
					'Failed renewal detected – creating failed order only'
				);

				$failed_order_id = $this->paymob_create_failed_renewal_order(
					$subscription_data,
					$json_data,
					$subscription->get_id()
				);

				if ( $failed_order_id ) {
					$subscription->add_order_note(
						'Paymob Renewal Failed. Failed Order ID: ' . $failed_order_id
					);

					Paymob::addLogs(
						$this->gateway->debug,
						$addlog,
						'Failed renewal order created: ' . $failed_order_id
					);
				}
			} else {
				Paymob::addLogs(
					$this->gateway->debug,
					$addlog,
					'Webhook ignored – trigger_type: ' . $trigger_type
				);
			}
		}

		Paymob::addLogs(
			$this->gateway->debug,
			$addlog,
			'Subscription webhook finished successfully'
		);

		die( 'Subscription webhook processed' );
	}

	function paymob_create_renewal_order( $subscription_data, $json_data, $subscription_id ) {

		$transaction_id = intval( $json_data['transaction_id'] );
		if ( $this->paymob_renewal_exists( $transaction_id ) ) {
			return false;
		}

		if ( ! function_exists( 'wcs_get_subscription' ) || ! function_exists( 'wcs_create_renewal_order' ) ) {
			return false;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		$renewal_order = wcs_create_renewal_order( $subscription );
		if ( ! $renewal_order ) {
			return false;
		}

		$total = $subscription_data['amount_cents'] / 100;

		foreach ( $renewal_order->get_items() as $item ) {
			$item->set_subtotal( $total );
			$item->set_total( $total );
			$item->save();
		}

		$renewal_order->set_total( $total );

		$renewal_order->update_meta_data( 'PaymobTransactionId', $transaction_id );
		$renewal_order->update_meta_data( '_paymob_is_renewal', 'yes' );

		$renewal_order->update_status( 'processing', 'Paymob renewal webhook' );
		$renewal_order->save();

		return $renewal_order->get_id();
	}

	private function paymob_renewal_exists( $transaction_id ) {
		$orders = wc_get_orders([
			'limit'      => 1,
			'meta_key'   => 'PaymobTransactionId',
			'meta_value' => $transaction_id,
			'return'     => 'ids',
		]);

		return ! empty( $orders );
	}

	// create a failed renewal order
	function paymob_create_failed_renewal_order( $subscription_data, $json_data, $subscription_id ) {

		$transaction_id = intval( $json_data['transaction_id'] );

		if ( ! function_exists( 'wcs_get_subscription' ) || ! function_exists( 'wcs_create_renewal_order' ) ) {
			return false;
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		$renewal_order = wcs_create_renewal_order( $subscription );
		if ( ! $renewal_order ) {
			return false;
		}

		$total = $subscription_data['amount_cents'] / 100;

		foreach ( $renewal_order->get_items() as $item ) {
			$item->set_subtotal( $total );
			$item->set_total( $total );
			$item->save();
		}

		$renewal_order->set_total( $total );

		$renewal_order->update_meta_data( 'PaymobTransactionId', $transaction_id );
		$renewal_order->update_meta_data( '_paymob_is_renewal', 'yes' );

		// Temporarily remove the hook that sets status to on-hold
		remove_action( 'woocommerce_order_status_changed', 
			'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10 );

		$renewal_order->update_status( 'failed', 'Paymob renewal failed webhook' );
		$renewal_order->save();

		// Re-add the hook
		add_action( 'woocommerce_order_status_changed', 
			'WC_Subscriptions_Renewal_Order::maybe_record_subscription_payment', 10, 3 );

		return $renewal_order->get_id();
	}

	public function callReturnAction() {
		
		$orderId         = Paymob::getIntentionId( Paymob::filterVar( 'merchant_order_id' ) );
		
		$merchant_order_id = sanitize_text_field( (string) Paymob::filterVar( 'merchant_order_id' ) );
		Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log',' --------- merchant order id '. $merchant_order_id );
		if ( strpos( $orderId, 'pixel' ) !== false ) {
			global $wpdb;
			$orderId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s",
					$merchant_order_id
				)
			);
			
		}
		Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log', ' --------- order id'.$orderId );
		Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log', ' --------- request method ' . ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) );
		
		Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-pixel.log', ' --------- errmsg ' . Paymob::filterVar( 'errmsg' ) );
		$order           = wc_get_order( $orderId );
		
		if(!$order ){
			wp_safe_redirect(wc_get_checkout_url().'?gatewayerror='. __( 'Sorry, no order found. Please try again.', 'paymob-for-woocommerce' ));
			exit();
		}
		$amount_cents = Paymob::filterVar('amount_cents');
		if ( $amount_cents && (float) $amount_cents > 0 ) {
			$cents_meta = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
			$div        = max( 1, (int) $cents_meta['cents'] );
			$prec       = (int) $cents_meta['precision'];
			$amount     = function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( (int) $amount_cents, $div )
				: round( ( (int) $amount_cents ) / $div, $prec );

			// Always align Woo total to Paymob paid amount on success redirect (keeps 4.50 not 5).
			if ( 'true' === Paymob::filterVar( 'success' ) || floatval( $order->get_total() ) == 0 ) {
				if ( function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
					paymob_pixel_begin_precise_amounts( $prec );
				}
				$order->set_total( $amount );
				$order->update_meta_data( '_paymob_paid_amount_cents', (int) $amount_cents );
				$order->update_meta_data( '_paymob_pixel_total_synced', 1 );
				$order->save();
				if ( function_exists( 'paymob_pixel_end_precise_amounts' ) ) {
					paymob_pixel_end_precise_amounts();
				}
			}
		}

		if(Paymob::filterVar( 'errmsg' ) && Paymob::filterVar( 'errmsg' ) !=='undefined'){
			$error = Paymob::filterVar( 'errmsg' );
			$order->update_status( 'failed' );
			$order->add_order_note( 'Paymob :' . $error );
			$order->update_meta_data( 'PaymobMerchantOrderID',$merchant_order_id);
			update_post_meta( $orderId, 'PaymobMerchantOrderID', $merchant_order_id );

			$err = '?gatewayerror='.$error ;
			$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
			$order->add_order_note( $note2);
			$order->save();
			// Bug 1: reset Pixel discount + intention so retry starts from cart base (e.g. 30 not 27).
			if ( function_exists( 'paymob_pixel_clear_discount_session' ) ) {
				paymob_pixel_clear_discount_session( true );
			}
			wp_safe_redirect(wc_get_checkout_url().$err);
			exit();
		}
		$PaymobPaymentId = $order->get_meta( 'PaymobPaymentId', true );
		$addlog          = Paymob::log_dir() . $PaymobPaymentId . '.log';

		if ( ! Paymob::verifyHmac( $this->hmac_hidden, Paymob::sanitizeVar() ) ) {
			$checkout_url = wc_get_checkout_url().'?gatewayerror='. __( 'Sorry, you are accessing wrong data due to mismatch verification.', 'paymob-for-woocommerce' );
			if(Paymob::filterVar( 'afterpayment' )){
				wp_send_json_success(array('url' => $checkout_url));
			}
			else{
				wp_safe_redirect( $checkout_url );
			}
			exit();
		}
		$err = null;
		Paymob::addLogs( $this->gateway->debug, $addlog, ' In Callback action, for order# ' . $orderId, wp_json_encode( Paymob::sanitizeVar() ) );

		$order         = PaymobOrder::validateOrderInfo( $orderId, $PaymobPaymentId );
		$country       = Paymob::getCountryCode( $this->gateway->sec_key );
		$url           = Paymob::getApiUrl( $country );
		$integrationId = Paymob::filterVar( 'integration_id' );
		$type          = Paymob::filterVar( 'source_data_type' );
		$subType       = Paymob::filterVar( 'source_data_sub_type' );
		$id            = Paymob::filterVar( 'id' );
		$paymobOrdr    = Paymob::filterVar( 'order' );
		$info          = "<br/>Payment Method ID: {$integrationId}<br/>Transaction done by: {$type} /  {$subType}</br>Transaction ID: <b style='color:DodgerBlue;'>{$id}</b> </br> Order ID:  <b style='color:DodgerBlue;'>{$paymobOrdr}</b></br><a href='{$url}portal2/en/transactions' target='_blank'>Visit Paymob Dashboard</a>";
		if (
			'true' === Paymob::filterVar( 'success' ) &&
			'false' === Paymob::filterVar( 'is_voided' ) &&
			'false' === Paymob::filterVar( 'is_refunded' )
		) {
			$status = $order->get_status();
			if ( 'pending' !== $status && 'failed' !== $status && 'on-hold' !== $status ) {
				// Already paid — clear session so next checkout does not reuse this CS.
				if ( function_exists( 'paymob_pixel_clear_session_after_payment' ) ) {
					paymob_pixel_clear_session_after_payment();
				}
				$received_url=$order->get_checkout_order_received_url();
				if(Paymob::filterVar( 'afterpayment' )){
					wp_send_json_success(array('url' => $received_url));
				}else{
					wp_safe_redirect( $order->get_checkout_order_received_url() );
				}
				exit();
			}
			$note = __( 'Paymob : Transaction ', 'paymob-for-woocommerce' ) . Paymob::filterVar( 'data_message' );
			$msg  = __( 'In callback action, for order #', 'paymob-for-woocommerce' ) . ' ' . $orderId . ' ' . $note;
			Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
			$order->add_order_note( $note . $info );
			$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
			$order->add_order_note( $note2);
			$order->payment_complete( $orderId );
			$paymentMethod      = $order->get_payment_method();
			$paymentMethodTitle = 'Paymob - ' . ucwords( $type );
			$order->set_payment_method_title( $paymentMethodTitle );
			$redirect_url = $order->get_checkout_order_received_url();
			// Drop paid intention from session (fixes next Place Order seeing "already paid").
			if ( function_exists( 'paymob_pixel_clear_session_after_payment' ) ) {
				paymob_pixel_clear_session_after_payment();
			}
		} else {
			$redirect_url = wc_get_checkout_url();
			$gatewayError = Paymob::filterVar( 'data_message' );
			$error        = __( 'Payment is not completed due to ', 'paymob-for-woocommerce' ) . $gatewayError;
			$msg          = __( 'In callback action, for order #', 'paymob-for-woocommerce' ) . ' ' . $orderId . ' ' . $error;
			Paymob::addLogs( $this->gateway->debug, $addlog, $msg );
			$order->update_status( 'failed' );
			$order->add_order_note( 'Paymob :' . $error . $info );
			$err = '?gatewayerror='.$error ;
			$note2= __( 'Paymob : Merchant Order ID Is ', 'paymob-for-woocommerce' ) . $merchant_order_id; 
			$order->add_order_note( $note2);
			$order->save();
			// Bug 1: do not remount Pixel on already-discounted intention amount.
			if ( function_exists( 'paymob_pixel_clear_discount_session' ) ) {
				paymob_pixel_clear_discount_session( true );
			}
		}
		$order->update_meta_data( 'PaymobTransactionId', $id ); 
		$order->update_meta_data( 'PaymobMerchantOrderID',$merchant_order_id);
		update_post_meta( $orderId, 'PaymobMerchantOrderID', $merchant_order_id );
        update_post_meta( $orderId, 'PaymobTransactionId', $id );

		$order->save();
		$existing_subscription_id = $order->get_meta('PaymobSubscriptionID');
		if ( empty( $existing_subscription_id ) ) {
			$this->TransactionSubscriptionID( $order, $id );
		}
	    WC()->session->set( 'cart', WC()->cart->get_cart() );
		WC()->session->set( 'chosen_shipping_methods', array() );
        WC()->session->set( 'chosen_payment_method', '' );
	    WC()->session->set( 'order_awaiting_payment', null );

		// Ensure paid Pixel CS is never left for the next checkout (afterpayment path).
		if ( empty( $err ) && function_exists( 'paymob_pixel_clear_session_after_payment' ) ) {
			paymob_pixel_clear_session_after_payment();
		}

		if(Paymob::filterVar( 'afterpayment' )){
			$session = WC()->session;
			$session->__unset('order_id');
   			wp_send_json_success(array('url' => $redirect_url.$err));
		}else{
			wp_safe_redirect( $redirect_url.$err );
		}
		
		exit();
	}
	public function add_enqueue_scripts() {
		if ( ! Paymob_Context::should_enqueue_frontend_assets() ) {
			return;
		}

		Paymob_Style::paymob_enqueue();
	}

	public function hide_block_main_gateway() {

		Paymob_Style::hide_main_gateway_enqueue();
	}

	public function TransactionSubscriptionID($order, $transactionID) {

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$conf['apiKey'] = $mainOptions['api_key'] ?? '';
		$conf['pubKey'] = $mainOptions['pub_key'] ?? '';
		$conf['secKey'] = $mainOptions['sec_key'] ?? '';
		$PaymobPaymentId = $order->get_meta('PaymobPaymentId', true);
		$addlog = Paymob::log_dir() . $PaymobPaymentId . '.log';
		$paymobReq = new Paymob($this->debug, $this->addlog);
		// Get auth token
		$token = $paymobReq->authToken($conf);
		if (empty($token['token'])) {
			return ['error' => 'Unable to authenticate with Paymob.'];
		}
	
		// Get subscription data by transaction ID
		$response = $paymobReq->TransactionSubscriptionID($token['token'], $conf['secKey'], $transactionID);
		if (!empty($response->results) && is_array($response->results)) {
			$subscription = $response->results[0];
			$order->update_meta_data('PaymobSubscriptionID', $subscription->id);
			$order->save();
			return $subscription->id;
			
		}
		else{
			return false;
		}


	}

	public function updateSubscriptionamount($order,$subscription_total,$sub_id) {

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$conf['apiKey'] = $mainOptions['api_key'] ?? '';
		$conf['pubKey'] = $mainOptions['pub_key'] ?? '';
		$conf['secKey'] = $mainOptions['sec_key'] ?? '';
		$PaymobPaymentId = $order->get_meta('PaymobPaymentId', true);
		$addlog = Paymob::log_dir() . $PaymobPaymentId . '.log';
		$paymobReq = new Paymob($this->debug, $this->addlog);
		// Get auth token
		$token = $paymobReq->authToken($conf);
		if (empty($token['token'])) {
			return ['error' => 'Unable to authenticate with Paymob.'];
		}
		$country      = Paymob::getCountryCode( $conf['secKey']);
		$cents   = 100;
		$round   = 2;
		if ( 'omn' === $country ) {
			$round = 3;
			$cents = 1000;
		}
		
		$data = [
			'amount_cents' => round( $subscription_total, $round ) * $cents
		];
		//update subscription amount 
		$response = $paymobReq->updateSubscription($token['token'], $conf['secKey'],$data, $sub_id);
		return $response;
	}

	private function handle_caf_logic( WC_Order $order, $json_data ) {
		// Merchants only need Instant Refund details in Woo — skip noisy CAF notes/totals.
		if ( $order->get_meta( '_paymob_caf_handled' ) ) {
			return;
		}
		$order->update_meta_data( '_paymob_caf_handled', 1 );
		if ( ! empty( $json_data['caf'] ) ) {
			$order->update_meta_data( 'paymob_caf_applied', ! empty( $json_data['caf']['convenience_fee_applied'] ) );
		}
		$order->save();
	}

	private function handle_instant_refund_logic( WC_Order $order, $json_data ) {
		// Normalize Accept vs Flash webhook shapes.
		$transaction = $json_data;
		if ( isset( $json_data['transaction'] ) && is_array( $json_data['transaction'] ) ) {
			$transaction = $json_data['transaction'];
		}

		$source = $transaction['source_data'] ?? array();
		if ( empty( $source['instant_refund'] ) ) {
			return;
		}

		if ( $order->get_meta( '_paymob_instant_refund_handled' ) ) {
			return;
		}

		$extra = $transaction['payment_key_claims']['extra']
			?? $json_data['payment_key_claims']['extra']
			?? array();

		$instant_refund_applied = ! empty( $extra['instant_refund_applied'] );
		$instant_refund_fees    = (int) ( $extra['instant_refund_fees'] ?? 0 );
		$original_amount_cents  = (int) ( $extra['original_amount_cents'] ?? 0 );

		// Only keep checkout fee meta when Instant Refund was actually applied on the transaction.
		if ( $instant_refund_fees <= 0 && $instant_refund_applied ) {
			$instant_refund_fees = (int) $order->get_meta( '_paymob_instant_refund_fees' );
		}
		if ( ! $instant_refund_applied && $instant_refund_fees <= 0 ) {
			$order->delete_meta_data( '_paymob_instant_refund_fees' );
			$order->delete_meta_data( '_paymob_instant_refund' );
			$order->delete_meta_data( '_paymob_instant_refund_note_added' );
			$order->update_meta_data( '_paymob_instant_refund_handled', 1 );
			$order->save();
			return;
		}

		$fee_major = $instant_refund_fees > 0
			? ( function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( $instant_refund_fees )
				: round( $instant_refund_fees / 100, 2 ) )
			: 0;

		$note  = '<b>Paymob Instant Refund</b><br/>';
		$note .= 'Instant Refund: Yes<br/>';
		$note .= 'Applied: ' . ( $instant_refund_applied ? 'Yes' : 'No' ) . '<br/>';

		if ( $original_amount_cents > 0 ) {
			$orig_major = function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( $original_amount_cents )
				: round( $original_amount_cents / 100, 2 );
			$note .= 'Original Amount: ' . (
				function_exists( 'paymob_pixel_format_price' )
					? paymob_pixel_format_price( $orig_major, $order->get_currency() )
					: wc_price( $orig_major )
			) . '<br/>';
		}

		if ( $instant_refund_fees > 0 ) {
			$note .= 'Instant Refund Fee: ' . (
				function_exists( 'paymob_pixel_format_price' )
					? paymob_pixel_format_price( $fee_major, $order->get_currency() )
					: wc_price( $fee_major, array( 'decimals' => 2 ) )
			) . ' <b>(non-refundable)</b><br/>';
		}

		$order->add_order_note( $note );

		$order->update_meta_data( '_paymob_instant_refund', 1 );
		$order->update_meta_data( '_paymob_instant_refund_applied', $instant_refund_applied ? 1 : 0 );
		if ( $instant_refund_fees > 0 ) {
			$order->update_meta_data( '_paymob_instant_refund_fees', $instant_refund_fees );
		}
		$order->update_meta_data( '_paymob_original_amount_cents', $original_amount_cents );
		$order->update_meta_data( '_paymob_instant_refund_handled', 1 );
		$order->save();
	}



	

	/**
	 * Force 2dp on Paymob Pixel order price formatting (admin Discount 1.50 not 2).
	 *
	 * @param array $args wc_price args.
	 * @return array
	 */
	public function paymob_wc_price_args_force_decimals( $args ) {
		if ( empty( $GLOBALS['paymob_pixel_force_decimals'] ) ) {
			return $args;
		}
		$args['decimals'] = max(
			isset( $args['decimals'] ) ? (int) $args['decimals'] : 0,
			(int) $GLOBALS['paymob_pixel_force_decimals']
		);
		return $args;
	}

	/**
	 * On Edit Order: keep Pixel discount/totals at Paymob precision (e.g. 1.50 not rounded to 2).
	 *
	 * @param WC_Order $order Order.
	 */
	public function paymob_admin_force_precise_discount_display( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$discount_cents = (int) $order->get_meta( '_paymob_discount_amount_cents' );
		$paid_cents     = (int) $order->get_meta( '_paymob_paid_amount_cents' );
		$fee_cents      = (int) $order->get_meta( '_paymob_instant_refund_fees' );
		if ( $discount_cents <= 0 && $paid_cents <= 0 && $fee_cents <= 0 ) {
			return;
		}

		$meta = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$div  = max( 1, (int) $meta['cents'] );
		$prec = (int) $meta['precision'];

		if ( function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
			paymob_pixel_begin_precise_amounts( $prec );
		}

		$changed = false;
		if ( $discount_cents > 0 ) {
			$exact = function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( $discount_cents, $div )
				: round( $discount_cents / $div, $prec );
			if ( abs( (float) $order->get_discount_total() - $exact ) > 0.001 ) {
				$order->set_discount_total( $exact );
				$changed = true;
			}
		}
		if ( $paid_cents > 0 ) {
			$exact_total = function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( $paid_cents, $div )
				: round( $paid_cents / $div, $prec );
			if ( abs( (float) $order->get_total() - $exact_total ) > 0.001 ) {
				$order->set_total( $exact_total );
				$changed = true;
			}
		}
		if ( $changed ) {
			$order->save();
		}
	}

	/**
	 * Order overview Total (thank-you header) uses get_formatted_order_total().
	 * Align it with Paymob paid cents so it matches the Order details Total row (e.g. 4.50 not 5).
	 *
	 * @param string   $formatted_total Formatted total HTML.
	 * @param WC_Order $order           Order.
	 * @param string   $tax_display     Tax display mode.
	 * @param bool     $display_refunded Whether refunded totals are shown.
	 * @return string
	 */
	public function paymob_formatted_order_total( $formatted_total, $order, $tax_display = '', $display_refunded = true ) {
		if ( ! $order instanceof WC_Order ) {
			return $formatted_total;
		}

		$paid_cents = (int) $order->get_meta( '_paymob_paid_amount_cents' );
		if ( $paid_cents <= 0 ) {
			return $formatted_total;
		}

		$is_paymob = ( 'paymob-pixel' === $order->get_payment_method() )
			|| (bool) $order->get_meta( '_paymob_pixel_total_synced' )
			|| (bool) $order->get_meta( '_paymob_discount_applied' );
		if ( ! $is_paymob ) {
			return $formatted_total;
		}

		$meta     = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$div      = max( 1, (int) $meta['cents'] );
		$prec     = (int) $meta['precision'];
		$currency = $order->get_currency();
		$paid     = function_exists( 'paymob_pixel_cents_to_major' )
			? paymob_pixel_cents_to_major( $paid_cents, $div )
			: round( $paid_cents / $div, $prec );

		// Persist once per request so thank-you overview and future views stay aligned.
		static $fixed_totals = array();
		$oid = $order->get_id();
		$current = (float) $order->get_total();
		if ( $oid && empty( $fixed_totals[ $oid ] ) && abs( $current - $paid ) > 0.001 ) {
			if ( function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
				paymob_pixel_begin_precise_amounts( $prec );
			}
			$order->set_total( $paid );
			$order->save();
			if ( function_exists( 'paymob_pixel_end_precise_amounts' ) ) {
				paymob_pixel_end_precise_amounts();
			}
			$fixed_totals[ $oid ] = true;
		}

		return function_exists( 'paymob_pixel_format_price' )
			? paymob_pixel_format_price( $paid, $currency )
			: wc_price( $paid, array( 'currency' => $currency, 'decimals' => $prec ) );
	}

	/**
	 * Show Instant Refund fee on thank-you, emails, and order view totals.
	 *
	 * @param array    $totals Order totals rows.
	 * @param WC_Order $order  Order object.
	 * @return array
	 */
	public function paymob_add_fees_to_order_totals_display( $totals, $order ) {

		if ( ! $order instanceof WC_Order ) {
			return $totals;
		}

		$meta = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$div  = max( 1, (int) $meta['cents'] );
		$prec = (int) $meta['precision'];
		$currency = $order->get_currency();

		$discount_cents = (int) $order->get_meta( '_paymob_discount_amount_cents' );
		$paid_cents     = (int) $order->get_meta( '_paymob_paid_amount_cents' );
		$instant_refund_fees = (int) $order->get_meta( '_paymob_instant_refund_fees' );

		$needs_precise = ( $discount_cents > 0 || $paid_cents > 0 || $instant_refund_fees > 0 )
			&& (
				'paymob-pixel' === $order->get_payment_method()
				|| $order->get_meta( '_paymob_pixel_total_synced' )
				|| $order->get_meta( '_paymob_discount_applied' )
				|| $order->get_meta( '_paymob_instant_refund' )
			);

		// Always force Paymob precision while printing these rows (Discount 1.50 not 2).
		if ( $needs_precise && function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
			paymob_pixel_begin_precise_amounts( $prec );
		}

		// Rebuild Discount / Total from cents so shop "0 decimals" cannot show 0.50/1.50 as 1/2.
		if ( $needs_precise ) {
			if ( $discount_cents > 0 && ! empty( $totals['discount'] ) ) {
				$discount_major = function_exists( 'paymob_pixel_cents_to_major' )
					? paymob_pixel_cents_to_major( $discount_cents, $div )
					: round( $discount_cents / $div, $prec );
				$totals['discount']['value'] = '-' . (
					function_exists( 'paymob_pixel_format_price' )
						? paymob_pixel_format_price( $discount_major, $currency )
						: wc_price( $discount_major, array( 'currency' => $currency, 'decimals' => $prec ) )
				);
			}
			if ( $paid_cents > 0 && ! empty( $totals['order_total'] ) ) {
				$paid_major = function_exists( 'paymob_pixel_cents_to_major' )
					? paymob_pixel_cents_to_major( $paid_cents, $div )
					: round( $paid_cents / $div, $prec );
				$totals['order_total']['value'] = function_exists( 'paymob_pixel_format_price' )
					? paymob_pixel_format_price( $paid_major, $currency )
					: wc_price( $paid_major, array( 'currency' => $currency, 'decimals' => $prec ) );
			}
		}

		if ( $instant_refund_fees <= 0 || ! empty( $totals['paymob_instant_refund_fee'] ) ) {
			return $totals;
		}

		$fee_major = function_exists( 'paymob_pixel_cents_to_major' )
			? paymob_pixel_cents_to_major( $instant_refund_fees, $div )
			: round( $instant_refund_fees / $div, $prec );

		$extra = array(
			'paymob_instant_refund_fee' => array(
				'label' => __( 'Instant Refund Fee (non-refundable):', 'paymob-for-woocommerce' ),
				'value' => function_exists( 'paymob_pixel_format_price' )
					? paymob_pixel_format_price( $fee_major, $currency )
					: wc_price( $fee_major, array( 'currency' => $currency, 'decimals' => $prec ) ),
			),
		);

		$new_totals = array();
		$inserted   = false;

		foreach ( $totals as $key => $total ) {
			if ( 'order_total' === $key && ! $inserted ) {
				foreach ( $extra as $extra_key => $extra_row ) {
					$new_totals[ $extra_key ] = $extra_row;
				}
				$inserted = true;
			}
			$new_totals[ $key ] = $total;
		}

		if ( ! $inserted ) {
			$new_totals = array_merge( $new_totals, $extra );
		}

		return $new_totals;
	}

	/**
	 * Show Instant Refund Fee row on the WooCommerce admin Edit Order screen.
	 *
	 * @param int $order_id Order ID.
	 */
	public function paymob_admin_order_instant_refund_fee_row( $order_id ) {
		static $rendered = array();

		$order_id = (int) $order_id;
		if ( $order_id <= 0 || isset( $rendered[ $order_id ] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$instant_refund_fees = (int) $order->get_meta( '_paymob_instant_refund_fees' );
		if ( $instant_refund_fees <= 0 ) {
			return;
		}

		$rendered[ $order_id ] = true;
		$meta     = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$fee_html = function_exists( 'paymob_pixel_format_price' )
			? paymob_pixel_format_price(
				function_exists( 'paymob_pixel_cents_to_major' )
					? paymob_pixel_cents_to_major( $instant_refund_fees, $meta['cents'] )
					: round( $instant_refund_fees / max( 1, (int) $meta['cents'] ), (int) $meta['precision'] ),
				$order->get_currency()
			)
			: wc_price( $instant_refund_fees / 100, array( 'currency' => $order->get_currency(), 'decimals' => (int) $meta['precision'] ) );
		?>
		<tr>
			<td class="label">
				<?php esc_html_e( 'Instant Refund Fee:', 'paymob-for-woocommerce' ); ?>
				<br/>
				<small style="font-weight:400;color:#646970;">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: fee amount */
							__( '%s is non-refundable', 'paymob-for-woocommerce' ),
							wp_strip_all_tags( $fee_html )
						)
					);
					?>
				</small>
			</td>
			<td width="1%"></td>
			<td class="total">
				<?php echo wp_kses_post( $fee_html ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Notice above refund UI: Instant Refund fee cannot be refunded.
	 *
	 * @param WC_Order $order Order.
	 */
	public function paymob_admin_refund_non_refundable_notice( $order ) {
		static $shown = false;
		if ( $shown || ! $order instanceof WC_Order ) {
			return;
		}
		$fee_cents = (int) $order->get_meta( '_paymob_instant_refund_fees' );
		if ( $fee_cents <= 0 ) {
			return;
		}
		$shown = true;

		$meta      = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$div       = max( 1, (int) $meta['cents'] );
		$prec      = (int) $meta['precision'];
		$currency  = $order->get_currency();
		$fee_major = function_exists( 'paymob_pixel_cents_to_major' )
			? paymob_pixel_cents_to_major( $fee_cents, $div )
			: round( $fee_cents / $div, $prec );
		$max = max( 0, round( (float) $order->get_total() - $fee_major, $prec ) );

		$fee_label = function_exists( 'paymob_pixel_format_price' )
			? paymob_pixel_format_price( $fee_major, $currency )
			: wc_price( $fee_major, array( 'currency' => $currency, 'decimals' => $prec ) );
		$max_label = function_exists( 'paymob_pixel_format_price' )
			? paymob_pixel_format_price( $max, $currency )
			: wc_price( $max, array( 'currency' => $currency, 'decimals' => $prec ) );
		?>
		<div class="paymob-ir-refund-notice" role="note">
			<div class="paymob-ir-refund-notice__inner">
				<span class="paymob-ir-refund-notice__badge" aria-hidden="true">!</span>
				<div class="paymob-ir-refund-notice__body">
					<p class="paymob-ir-refund-notice__title">
						<?php esc_html_e( 'Paymob Instant Refund', 'paymob-for-woocommerce' ); ?>
					</p>
					<ul class="paymob-ir-refund-notice__list">
						<li>
							<span class="paymob-ir-refund-notice__label"><?php esc_html_e( 'Instant Refund Fee (non-refundable)', 'paymob-for-woocommerce' ); ?></span>
							<strong class="paymob-ir-refund-notice__value"><?php echo wp_kses_post( $fee_label ); ?></strong>
						</li>
						<li>
							<span class="paymob-ir-refund-notice__label"><?php esc_html_e( 'Max refundable from Woo', 'paymob-for-woocommerce' ); ?></span>
							<strong class="paymob-ir-refund-notice__value"><?php echo wp_kses_post( $max_label ); ?></strong>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Align Woo order total with Paymob transaction amount (dashboard source of truth).
	 *
	 * @param WC_Order $order        Order.
	 * @param int      $amount_cents Amount in minor units.
	 * @param int      $cents        Currency minor multiplier.
	 */
	private function sync_order_total_from_paymob_cents( WC_Order $order, $amount_cents, $cents = 100 ) {
		$amount_cents = (int) $amount_cents;
		$cents        = (int) $cents > 0 ? (int) $cents : 100;
		if ( $amount_cents <= 0 ) {
			return;
		}

		$precision = ( 1000 === $cents ) ? 3 : 2;
		if ( function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
			paymob_pixel_begin_precise_amounts( $precision );
		}
		$total = function_exists( 'paymob_pixel_cents_to_major' )
			? paymob_pixel_cents_to_major( $amount_cents, $cents )
			: round( $amount_cents / $cents, $precision );
		$order->set_total( $total );
		$order->update_meta_data( '_paymob_paid_amount_cents', $amount_cents );
		if ( function_exists( 'paymob_pixel_end_precise_amounts' ) ) {
			paymob_pixel_end_precise_amounts();
		}
	}

	/**
	 * Mark WooCommerce order as refunded when Paymob Instant Refund / refund webhook arrives.
	 *
	 * @param WC_Order $order  Order.
	 * @param array    $trans  Transaction payload.
	 * @param string   $msg    Log prefix.
	 * @param string   $addlog Log file path.
	 */
	private function mark_order_refunded_from_paymob( $order, $trans, $msg, $addlog ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$status = $order->get_status();
		if ( in_array( $status, array( 'refunded', 'cancelled' ), true ) ) {
			$order->add_order_note( __( 'Paymob: Refund webhook received (order already refunded).', 'paymob-for-woocommerce' ) );
			return;
		}

		$country = Paymob::getCountryCode( $this->gateway->sec_key );
		$cents   = ( 'omn' === $country ) ? 1000 : 100;
		$refund_cents = 0;
		if ( isset( $trans['amount_cents'] ) ) {
			$refund_cents = abs( (int) $trans['amount_cents'] );
		} elseif ( isset( $trans['refunded_amount_cents'] ) ) {
			$refund_cents = abs( (int) $trans['refunded_amount_cents'] );
		}

		$refund_amount = $refund_cents > 0
			? round( $refund_cents / $cents, ( 1000 === $cents ) ? 3 : 2 )
			: (float) $order->get_remaining_refund_amount();

		$trx_id = $trans['id'] ?? ( $trans['transaction_id'] ?? '' );
		$note   = __( 'Paymob Webhook: Payment Refunded / Instant Refund', 'paymob-for-woocommerce' );
		if ( $trx_id ) {
			$note .= '<br/>Transaction ID: <b style="color:DodgerBlue;">' . esc_html( (string) $trx_id ) . '</b>';
		}
		if ( $refund_amount > 0 ) {
			$note .= '<br/>Refunded amount: ' . wc_price( $refund_amount, array( 'currency' => $order->get_currency() ) );
		}

		// Create a Woo refund record when possible so admin status/totals stay aligned.
		$remaining = (float) $order->get_remaining_refund_amount();
		if ( $refund_amount > 0 && $remaining > 0 && function_exists( 'wc_create_refund' ) ) {
			$create_amount = min( $refund_amount, $remaining );
			try {
				$refund = wc_create_refund(
					array(
						'amount'         => $create_amount,
						'reason'         => 'Paymob Instant Refund / dashboard refund',
						'order_id'       => $order->get_id(),
						'refund_payment' => false,
						'restock_items'  => false,
					)
				);
				if ( is_wp_error( $refund ) ) {
					Paymob::addLogs( $this->gateway->debug, $addlog, $msg . ' refund create failed: ' . $refund->get_error_message() );
					$order->update_status( 'refunded', $note );
				} else {
					$order->add_order_note( $note );
				}
			} catch ( Exception $e ) {
				Paymob::addLogs( $this->gateway->debug, $addlog, $msg . ' refund exception: ' . $e->getMessage() );
				$order->update_status( 'refunded', $note );
			}
		} else {
			$order->update_status( 'refunded', $note );
		}

		$order->update_meta_data( '_paymob_dashboard_refunded', 1 );
		if ( $trx_id ) {
			$order->update_meta_data( '_paymob_refund_transaction_id', $trx_id );
		}
		$order->save();

		Paymob::addLogs( $this->gateway->debug, $addlog, $msg . ' ' . $note );
	}

	/**
	 * Strip Instant Refund / Discount order meta when the paid transaction does not include them.
	 * Prevents Test Mode / stale session values from sticking on the order page.
	 *
	 * @param WC_Order $order    Order.
	 * @param array    $payload  Transaction / webhook payload.
	 */
	private function scrub_stale_pixel_adjustments_from_transaction( WC_Order $order, $payload ) {
		$transaction = $payload;
		if ( isset( $payload['transaction'] ) && is_array( $payload['transaction'] ) ) {
			$transaction = $payload['transaction'];
		}

		$source  = $transaction['source_data'] ?? array();
		$extra   = $transaction['payment_key_claims']['extra']
			?? ( $payload['payment_key_claims']['extra'] ?? array() );
		$has_ir  = ! empty( $source['instant_refund'] )
			|| ! empty( $extra['instant_refund_applied'] )
			|| ( ! empty( $extra['instant_refund_fees'] ) && (int) $extra['instant_refund_fees'] > 0 );

		$discount_details = $payload['discount_details']
			?? ( $transaction['discount_details'] ?? array() );
		$has_discount     = ! empty( $discount_details );

		if ( ! $has_ir ) {
			$order->delete_meta_data( '_paymob_instant_refund_fees' );
			$order->delete_meta_data( '_paymob_instant_refund' );
			$order->delete_meta_data( '_paymob_instant_refund_applied' );
			$order->delete_meta_data( '_paymob_instant_refund_note_added' );
			$order->delete_meta_data( '_paymob_instant_refund_handled' );
		}

		if ( ! $has_discount && ! $order->get_meta( '_paymob_pixel_total_synced' ) ) {
			$order->delete_meta_data( '_paymob_discount_amount_cents' );
			$order->delete_meta_data( '_paymob_discount_applied' );
		}

		$order->save();
	}

	private function update_order_total_after_discount( WC_Order $order, $json_data ) {

		$discount_details = $json_data['discount_details']
			?? ( $json_data['transaction']['discount_details'] ?? array() );

		if ( empty( $discount_details ) || ! is_array( $discount_details ) ) {
			return;
		}

		$detail = $discount_details[0];
		$meta   = function_exists( 'paymob_pixel_cents_meta' ) ? paymob_pixel_cents_meta() : array( 'cents' => 100, 'precision' => 2 );
		$div    = max( 1, (int) $meta['cents'] );
		$prec   = (int) $meta['precision'];

		// Pixel / API: discounted_amount_cents = discount value; discount_amount_cents = final amount.
		$discount_cents = 0;
		if ( isset( $detail['discounted_amount_cents'] ) ) {
			$discount_cents = (int) $detail['discounted_amount_cents'];
		} elseif ( isset( $detail['discount_amount_cents'] ) ) {
			// Legacy: some payloads only send discount_amount_cents as the discount value.
			$candidate     = (int) $detail['discount_amount_cents'];
			$current_cents = (int) round( (float) $order->get_total() * $div );
			if ( $candidate > 0 && $candidate < $current_cents ) {
				$discount_cents = $candidate;
			}
		}

		if ( $discount_cents <= 0 ) {
			return;
		}

		// Keep fractional discounts (0.50) — never round(cents/100) without precision (=1).
		$discount_amount = function_exists( 'paymob_pixel_cents_to_major' )
			? paymob_pixel_cents_to_major( $discount_cents, $div )
			: round( $discount_cents / $div, $prec );

		$final_cents = 0;
		if ( isset( $detail['discount_amount_cents'] ) ) {
			$maybe_final = (int) $detail['discount_amount_cents'];
			if ( $maybe_final > 0 && $maybe_final !== $discount_cents ) {
				$final_cents = $maybe_final;
			}
		}

		$discount_already = (bool) $order->get_meta( '_paymob_discount_applied' );
		$total_synced     = (bool) $order->get_meta( '_paymob_pixel_total_synced' );

		if ( function_exists( 'paymob_pixel_begin_precise_amounts' ) ) {
			paymob_pixel_begin_precise_amounts( $prec );
		}

		$order->update_meta_data( '_paymob_discount_amount_cents', $discount_cents );
		$order->update_meta_data( '_paymob_discount_applied', 1 );
		$order->set_discount_total( $discount_amount );

		$price = function ( $amount ) use ( $order, $prec ) {
			return function_exists( 'paymob_pixel_format_price' )
				? paymob_pixel_format_price( $amount, $order->get_currency() )
				: wc_price( $amount, array( 'currency' => $order->get_currency(), 'decimals' => $prec ) );
		};

		// Discount line already recorded — only refresh precise discount_total, do not re-note / re-subtract.
		if ( $discount_already || $total_synced ) {
			if ( ! $discount_already ) {
				$order->add_order_note(
					sprintf(
						'Paymob Discount: -%s (total already synced from Pixel)',
						$price( $discount_amount )
					)
				);
			}
			if ( function_exists( 'paymob_pixel_end_precise_amounts' ) ) {
				paymob_pixel_end_precise_amounts();
			}
			$order->save();
			return;
		}

		$current_total = (float) $order->get_total();
		if ( $final_cents > 0 ) {
			$new_total = function_exists( 'paymob_pixel_cents_to_major' )
				? paymob_pixel_cents_to_major( $final_cents, $div )
				: round( $final_cents / $div, $prec );
			$order->update_meta_data( '_paymob_paid_amount_cents', $final_cents );
		} else {
			$new_total = max( 0, round( $current_total - $discount_amount, $prec ) );
		}
		$order->set_total( $new_total );
		$order->update_meta_data( '_paymob_pixel_total_synced', 1 );

		$order->add_order_note(
			sprintf(
				'Paymob Discount Applied: -%s (Old total: %s → New total: %s)',
				$price( $discount_amount ),
				$price( $current_total ),
				$price( $new_total )
			)
		);

		if ( function_exists( 'paymob_pixel_end_precise_amounts' ) ) {
			paymob_pixel_end_precise_amounts();
		}

		$order->save();
	}




}
