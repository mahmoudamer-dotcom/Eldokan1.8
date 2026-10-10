<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
class Paymob_Payment extends WC_Payment_Gateway {


	public $has_fields;
	public $id;
	public $method_title;
	public $method_description;
	public $supports;
	public $title;
	public $description;
	public $config_note;
	public $callback;
	public $sec_key;
	public $pub_key;
	public $api_key;
	public $integration_id;
	public $integration_id_hidden;
	public $single_integration_id;
	public $hmac;
	public $hmac_hidden;
	public $debug;
	public $empty_cart;
	public $logo;
	public $addlog;
	public $cents;
	public $notify_url;
	public $amount_cents;
	public $has_items;
	public $test_sec_key;
	public $live_pub_key;
	public $live_sec_key;
	public $mode;
	public $pixel_payment;
	public $cards_integration_id;
	public $test_pub_key;
	public $google_pay_integration_id;
	public $show_save_card;
	public $font_family;
	public $font_size;
	public $font_weight;
	public $color_container;
	public $color_border;
	public $radius_border;
	public $color_disabled;
	public $color_error;
	public $vertical_spacing_between_components;
	public $vertical_padding_for_text_fields;
	public $color_for_text_placeholder;
	public $width_of_container;
	public $color_for_input_fields;
	public $color_for_primary;
	public $color_for_container;
	public $color_input_fields;
	public $color_primary;
	public $apple_pay_integration_id;
	public $bank_installment_integration_id;
	public $force_save_card;
	public $vertical_padding_for_button;
	public $customization_div;
	public $container_padding;
	public $vertical_padding;
	public $text_color_for_input_fields;
	public $text_color_for_payment_button;
	public $text_color_for_label;
	public $color_border_payment_button;
	public $color_border_input_fields;
	public $font_weight_payment_button;
	public $font_weight_input_fields;
	public $font_weight_label;
	public $font_size_payment_button;
	public $font_size_input_fields;
	public $font_size_label;
	public $dark_mode;
    public $enabled_widget;
	public $is_moto;
	public $tabs;
	public $is_3DS;
	public $ds3_integration_ids;
	public $moto_integration_id;
	public $allow_cancel;
	private $paymobToken = null;

	public function __construct() {
		// config
		$this->has_fields = true;
		$this->is_moto    = $this->get_option( 'is_moto' );
		$this->supports   = array(  'products',
									'refunds',
									'subscriptions',
									'subscription_cancellation',
									'subscription_suspension',
									'subscription_reactivation',
									'subscription_amount_changes',
									'subscription_date_changes',
									'subscription_payment_method_change',
									'subscription_payment_method_change_customer',
									'subscription_payment_method_change_admin',
									'multiple_subscriptions',
								    'gateway_scheduled_payments');

		$this->init_settings();
		$this->init_form_fields();
		// fields
		foreach ( $this->settings as $key => $val ) {
			$this->$key = $val;
		}

		$paymobOptions = get_option( 'woocommerce_paymob-main_settings' );
		if ( $paymobOptions ) {
			$this->sec_key    = isset( $paymobOptions['sec_key'] ) ? $paymobOptions['sec_key'] : '';
			$this->pub_key    = isset( $paymobOptions['pub_key'] ) ? $paymobOptions['pub_key'] : '';
			$this->api_key    = isset( $paymobOptions['api_key'] ) ? $paymobOptions['api_key'] : '';
			$this->empty_cart = isset( $paymobOptions['empty_cart'] ) ? $paymobOptions['empty_cart'] : '';
			$this->has_items  = isset( $paymobOptions['has_items'] ) ? $paymobOptions['has_items'] : '';
			$this->debug      = ( isset( $paymobOptions['debug'] ) && $paymobOptions['debug'] == 'yes' ) ? '1' : '0';
		}
		$this->description           = $this->get_option( 'description' );
		$this->logo                  = $this->get_option( 'logo' );
		$this->single_integration_id = $this->get_option( 'single_integration_id' );
		$this->addlog                = Paymob::log_dir() . $this->id . '.log';
		$this->cents                 = 100;
		// callback
		$this->notify_url = WC()->api_request_url( 'wc-paymob-card' );
		add_action( 'admin_enqueue_scripts', array( $this, 'paymob_admin_enqueue' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'paymob_frontend_enqueue' ) );
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function () {
				$this->process_admin_options();
			}
		);
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'on_subscription_cancelled' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'on_subscription_suspended' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_active',array( $this, 'on_subscription_reactivated' ),10,1);
		add_action( 'woocommerce_subscription_status_pending-cancel', array( $this, 'on_subscription_pending_cancelled' ), 10, 1 );
		//stop default behaviour on renew 
		add_action('woocommerce_scheduled_subscription_payment_paymob-subscription',array( $this, 'handle_scheduled_subscription_payment' ),10, 2);
		add_filter( 'wcs_renewal_order_created', '__return_false', 100 );
		add_filter( 'woocommerce_subscriptions_process_renewal_payment', '__return_false', 100 );

			}

	public function paymob_frontend_enqueue() {
		if ( is_checkout() ) {
			Paymob_Scripts::paymob_frontend();
		}
	}

	public function paymob_admin_enqueue() {
		$params = array(
			'gateway'      => $this->id,
			'callback_url' => $this->notify_url,
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
		);

		$gateway_ids = array();
		$gateways    = PaymobAutoGenerate::get_db_gateways_data();
		foreach ( $gateways as $gateway ) {
			$gateway_ids[] = $gateway->gateway_id;
		}
		if ( 
				Paymob::filterVar( 'section' ) &&
				( 
					in_array( Paymob::filterVar( 'section' ), $gateway_ids ) || 
					Paymob::filterVar( 'section' ) == 'paymob-main' || 
					Paymob::filterVar( 'section' ) == 'paymob_add_gateway' || 
					Paymob::filterVar( 'section' ) == 'paymob_list_gateways' || 
					Paymob::filterVar( 'section' ) == 'paymob_subscription' ||
					Paymob::filterVar( 'section' ) == 'paymob_pixel' ||
					Paymob::filterVar( 'section' ) == 'widget'
				) 
			) {
				Paymob_Scripts::paymob_admin( $params );
				Paymob_Style::paymob_admin();
			}

	}

	public function admin_options() {
		parent::admin_options();
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
	}

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon = '<img id="paymob-logo" src="' . $this->logo . '"/>';
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	public function process_payment( $orderId ) {
		// $pxl_submit = Paymob::filterVar('pxl_submit', 'POST') ?? Paymob::filterVar('pxl_submit', 'POST');
		$paymobOrder = new PaymobOrder( $orderId, $this );
		$order = wc_get_order( $orderId );
		if('paymob-pixel' !== $this->id){
			$status      = $paymobOrder->createPayment();
			if ( ! $status['success'] ) {
				$errorMsg = $status['message'];
				if ( 'Unsupported currency' == $errorMsg) {
					$paymobOptions   = get_option( 'woocommerce_paymob_settings' );
					$integration_ids = explode( ',', $paymobOptions['integration_id_hidden'] );
					$currencies      = array(); // Initialize array to store matching values
					// Loop through each entry in the second array
					foreach ( $integration_ids as $entry ) {
						// Split the entry by ':'
						$parts = explode( ':', $entry );
						$id    = trim( $parts[0] );
						if ( isset( $parts[2] ) ) {
							if ( in_array( $id, $paymobOptions['integration_id'] ) ) {
								$currencies[] = trim( substr( $parts[2], strpos( $parts[2], '(' ) + 1 ) );
							}
						}
					}
					$errorMsg = __( 'Given currency is not supported. ', 'paymob-for-woocommerce' );
					if ( ! empty( $currencies ) ) {
						$errorMsg .= __( 'Currency supported : ', 'paymob-for-woocommerce' ) . implode( ',', array_unique( $currencies ) );
					}
				}
				return $paymobOrder->throwErrors( $errorMsg );
			}

			$paymobReq   = new Paymob( $this );
			$countryCode = $paymobReq->getCountryCode( $this->pub_key );
			$apiUrl      = $paymobReq->getApiUrl( $countryCode );
			$cs          = $status['cs'];

			$to    = $apiUrl . "unifiedcheckout/?publicKey=$this->pub_key&clientSecret=$cs";
			
			$order->update_meta_data( 'PaymobIntentionId', $status['intentionId'] );
			$order->update_meta_data( 'PaymobCentsAmount', $status['centsAmount'] );
			$order->update_meta_data( 'PaymobPaymentId', $this->id );
			$order->save();

			$paymobOrder->processOrder();

			return array(
				'result'   => 'success',
				'redirect' => $to,
			);
		// }elseif($pxl_submit=='pxl_submit'){
		// 	echo 123; die;
		}else{
			$status = Paymob_Pixel_Update_Intention::update_intention($orderId,$order);
			if (!$status['success']) {
				if ( class_exists( 'Paymob_Error_Logs' ) ) {
					Paymob_Error_Logs::add( $status['message'], 'pixel_checkout', 'gateway' );
				}
				wc_add_notice( $status['message'], 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
				
			}
			$order->update_status( 'pending-payment' );	
			$paymobOrder->processOrder();
			$order->update_meta_data('PaymobIntentionId', WC()->session->get('PaymobIntentionId'));
			$order->update_meta_data('PaymobCentsAmount',  WC()->session->get('PaymobCentsAmount'));
			$order->update_meta_data('PaymobPaymentId', $this->id);
			$order->save();
			Paymob_Pixel_Checkout::update_paymob_intention_with_orderID($orderId,WC()->session->get('cs'), WC()->session->get('pixel_identifier'));
		    $session=WC()->session;
         	$session->__unset('order_id');
		    $session->set( 'order_id',  WC()->session->get('pixel_identifier'));

			return array(
				'result'   => 'success',
				'redirect' => '#',
			);
		}
	}
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$country = Paymob::getCountryCode( $this->sec_key );
		$cents   = 100;
		$round   = 2;
		if ( 'omn' === $country ) {
			$round = 3;
			$cents = 1000;
		}
		$order = wc_get_order( $order_id );
		// Check if the order exists
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Order not found.', 'paymob-for-woocommerce' ) );
		}

		$ir_fee_cents = (int) $order->get_meta( '_paymob_instant_refund_fees' );
		$ir_fee_major = $ir_fee_cents > 0 ? round( $ir_fee_cents / $cents, $round ) : 0;
		$refundable   = max( 0, round( (float) $order->get_total() - $ir_fee_major, $round ) );

		if ( null === $amount ) {
			$amount = $refundable;
		}
		$amount = round( (float) $amount, $round );

		if ( $ir_fee_major > 0 && $amount > $refundable ) {
			$currency = $order->get_currency();
			// Plain text only — WooCommerce refund UI shows WP_Error via browser alert() (no HTML).
			$fee_label = html_entity_decode(
				wp_strip_all_tags( wc_price( $ir_fee_major, array( 'currency' => $currency ) ) ),
				ENT_QUOTES,
				'UTF-8'
			);
			$max_label = html_entity_decode(
				wp_strip_all_tags( wc_price( $refundable, array( 'currency' => $currency ) ) ),
				ENT_QUOTES,
				'UTF-8'
			);
			return new WP_Error(
				'paymob_ir_non_refundable',
				sprintf(
					/* translators: 1: non-refundable fee, 2: max refundable */
					__( 'Instant Refund Fee %1$s is non-refundable. Maximum refundable amount is %2$s.', 'paymob-for-woocommerce' ),
					$fee_label,
					$max_label
				)
			);
		}

		$transactionId   = $order->get_meta( 'PaymobTransactionId', true );
		$PaymobPaymentId = $order->get_meta( 'PaymobPaymentId', true );
		$addlog          = Paymob::log_dir() . $PaymobPaymentId . '.log';
		$data            = array(
			'transaction_id' => $transactionId,
			'amount_cents'   => (int) round( $amount * $cents ),
		);
		$paymobReq       = new Paymob( $this->debug, $addlog );
		$status          = $paymobReq->refundPayment( $this->sec_key, $data );

		if ( ! $status['success'] ) {
			return new WP_Error( 'error', __( 'Refund failed: ', 'paymob-for-woocommerce' ) . $status['message'] );
		} else {
			$paymob_refund_id = $status['refund_id'];
			$msg              = $this->can_refund_orders( $order, $amount, $paymob_refund_id );
			Paymob::addLogs( $this->debug, $addlog, 'For Order # ' . $order_id . ' ' . $msg );
		}
		// If the refund is successful, return true.
		return true;
	}

	public function can_refund_orders( $order, $amount, $paymob_refund_id ) {
		$refunds          = $order->get_refunds(); // Get all refunds associated with the order
		$recent_refund_id = '';
		$msg              = __( 'Paymob : Refund of ', 'paymob-for-woocommerce' ) . $amount;

		if ( ! empty( $refunds ) ) {
			usort(
				$refunds,
				function ( $a, $b ) {
					return strtotime( $b->get_date_created() ) - strtotime( $a->get_date_created() );
				}
			);
			$recent_refund    = reset( $refunds );
			$recent_refund_id = $recent_refund->get_id();
		}

		$order_total = $order->get_total();
		if ( $amount < $order_total ) {
			// Partial refund
			$msg = __( 'Paymob : Partial refund of ', 'paymob-for-woocommerce' ) . $amount;
		} elseif ( $amount == $order_total ) {
			// Full refund
			$msg = __( 'Paymob : Full refund of ', 'paymob-for-woocommerce' ) . $amount;
		}
		$info = "<br/>Woo Order Refund ID: {$recent_refund_id}<br/>Transaction Refund ID : {$paymob_refund_id}";
		$order->add_order_note( $msg . $info );
		return $msg;
	}
	public function payment_fields() {
		if('paymob-pixel' !== $this->id){
			if ( $this->description ) {
				echo wp_kses_post( wpautop( esc_html( $this->description ) ) );
			}
		}else{
			if (function_exists('is_checkout') && is_checkout()) {
				include __DIR__ . '/../admin/scripts/pixel_checkout.php';
			}
		}
	}

	public function init_form_fields() {
		$this->form_fields = include __DIR__ . '/../admin/paymob-single-gateway.php';
		
	}

	/**
	 * Don't enable Paymob payment method, if there is no public and secret keys
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return string
	 */
	public function validate_enabled_field( $key, $value ) {

		if ( is_null( $value ) ) {
			return 'no';
		}
		$paymobOptions = get_option( 'woocommerce_paymob-main_settings' );
		$pubKey        = $paymobOptions['pub_key'];
		$secKey        = $paymobOptions['sec_key'];
		$apiKey        = $paymobOptions['api_key'];
		if ( empty( $pubKey ) || empty( $secKey ) || empty( $apiKey ) ) {
			WC_Admin_Settings::add_error( __( 'Please ensure you are entering API, public and secret keys in the main Paymob configuration.', 'paymob-for-woocommerce' ) );
			return 'no';
		}

		$integrationId = $this->get_field_value( 'single_integration_id', $this->form_fields['single_integration_id'] );
		if ( empty( $integrationId ) ) {
			WC_Admin_Settings::add_error(
				sprintf(
					/* translators: %s: payment method title */
					__( 'Please, ensure adding (%s) integration ID.', 'paymob-for-woocommerce' ),
					$this->method_title
				)
			);
			return 'no';
		}
		return 'yes';
	}

	/**
	 * Return whether or not Paymob payment method requires setup.
	 *
	 * @return bool
	 */
	public function needs_setup() {
		$paymobOptions = get_option( 'woocommerce_paymob-main_settings' );
		$pubKey        = $paymobOptions['pub_key'];
		$secKey        = $paymobOptions['sec_key'];
		$apiKey        = $paymobOptions['api_key'];
		if ( empty( $pubKey ) || empty( $secKey ) || empty( $apiKey ) ) {
			return true;
		}

		return false;
	}
	

	/**
	 * Get Paymob Auth Token (cached per request)
	 */
	private function getPaymobAuthToken() {
		if ($this->paymobToken) return $this->paymobToken;

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$conf = [
			'apiKey' => $mainOptions['api_key'] ?? '',
			'pubKey' => $mainOptions['pub_key'] ?? '',
			'secKey' => $mainOptions['sec_key'] ?? '',
		];

		// check transient first
		$token = get_transient('paymob_auth_token');
		if (!$token) {
			$paymobReq = new Paymob($this->debug, $this->addlog);
			$tokenData = $paymobReq->authToken($conf);
			if (empty($tokenData['token'])) return false;

			$token = $tokenData['token'];
			set_transient('paymob_auth_token', $token, 50*60);
		}

		$this->paymobToken = $token;
		return $token;
	}

	/**
	 * Get Paymob Subscription ID for order (cached)
	 */
	private function getPaymobSubscriptionID($order) {
		$cached = $order->get_meta('PaymobSubscriptionID');
		if (!empty($cached)) return $cached;

		$transactionID = $order->get_meta('PaymobTransactionId');
		if (empty($transactionID)) return false;

		$token = $this->getPaymobAuthToken();
		if (!$token) return false;

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$secKey = $mainOptions['sec_key'] ?? '';

		$paymobReq = new Paymob($this->debug, $this->addlog);
		$response = $paymobReq->TransactionSubscriptionID($token, $secKey, $transactionID);

		if (!empty($response->results) && is_array($response->results)) {
			$subId = $response->results[0]->id;
			$order->update_meta_data('PaymobSubscriptionID', $subId);
			$order->save();
			return $subId;
		}

		return false;
	}

	/**
	 * General method to handle subscription actions
	 */
	private function handleSubscriptionAction($subscription, $action) {
		$order = $subscription->get_parent();
		if (!$order) return;

		$subId = $this->getPaymobSubscriptionID($order);
		if (!$subId) return;

		$token = $this->getPaymobAuthToken();
		if (!$token) return;

		$mainOptions = get_option('woocommerce_paymob-main_settings');
		$secKey = $mainOptions['sec_key'] ?? '';

		$paymobReq = new Paymob($this->debug, $this->addlog);

		switch($action) {
			case 'cancel':
			case 'pending_cancel':
				$paymobReq->cancelSubscription($token, $secKey, $subId);
				$msg = $action === 'cancel' ? 'Subscription cancelled successfully' : 'Subscription pending cancel';
				break;
			case 'suspend':
				$paymobReq->suspendSubscription($token, $secKey, $subId);
				$msg = 'Subscription paused successfully';
				break;
			case 'activate':
				$paymobReq->activateSubscription($token, $secKey, $subId);
				$msg = 'Subscription resumed successfully';
				break;
			default: return;
		}

		set_transient('paymob_flash_notice', [
			'type' => 'success',
			'message' => $msg
		], 30);
	}

	/**
	 * Hooks
	 */
	public function on_subscription_cancelled($subscription) {
		$this->handleSubscriptionAction($subscription, 'cancel');
	}
	public function on_subscription_pending_cancelled($subscription) {
		$this->handleSubscriptionAction($subscription, 'pending_cancel');
	}
	public function on_subscription_suspended($subscription) {
		$this->handleSubscriptionAction($subscription, 'suspend');
	}
	public function on_subscription_reactivated($subscription) {
		$this->handleSubscriptionAction($subscription, 'activate');
	}




}
