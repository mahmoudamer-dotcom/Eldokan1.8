<?php
class Paymob_Update_Pixel_Data {
	public static function update_pixel_data() {
		check_ajax_referer( 'update_checkout', 'security' );
		if ( ! Paymob::filterVar( 'billing_data', 'POST' ) || ! Paymob::filterVar( 'total_amount', 'POST' ) ) {
			wp_send_json_error( 'Missing data' );
			return;
		}
		if ( sizeof( Paymob_Saved_Cards_Tokens::getUserTokens() ) > 3 ) {
			$url = wc_get_endpoint_url( 'saved-cards', '', get_permalink( wc_get_page_id( 'myaccount' ) ) );
			$url = '<a href="' . $url . '">' . esc_html( __( 'Paymob Saved Cards', 'paymob-for-woocommerce' ) ) . '</a>';
			$url = esc_html( __( 'Please remove your cards from', 'paymob-for-woocommerce' ) ) . ' ' . $url . ' ' . esc_html( __( 'to complete your purchase', 'paymob-for-woocommerce' ) );
			$msg = esc_html( __( 'Ops,Max number of card tokens is 3.', 'paymob-for-woocommerce' ) ) . '<br>' . $url;
			wp_send_json_error( $msg );
			return;
		}

		$paymobOptions = get_option( 'woocommerce_paymob-main_settings' );
		$secKey        = isset( $paymobOptions['sec_key'] ) ? $paymobOptions['sec_key'] : '';
		$debug         = isset( $paymobOptions['debug'] ) ? sanitize_text_field( $paymobOptions['debug'] ) : '';
		$debug         = $debug ? '1' : '0';
		$addlog        = Paymob::log_dir() . 'paymob-pixel.log';

		$force_new      = self::post_flag( 'force_new' );
		$reset_discount = self::post_flag( 'reset_discount' ) || $force_new;

		if ( $force_new && function_exists( 'paymob_pixel_clear_discount_session' ) ) {
			paymob_pixel_clear_discount_session( true );
		} elseif ( $reset_discount && function_exists( 'paymob_pixel_clear_discount_session' ) ) {
			paymob_pixel_clear_discount_session( false );
		}

		$billing_data = wc_clean( Paymob::filterVar( 'billing_data', 'POST' ) );
		WC()->cart->calculate_totals();
		$cart_total = (float) WC()->cart->get_total( 'edit' );

		$country = Paymob::getCountryCode( $secKey );
		$cents   = ( 'omn' === $country ) ? 1000 : 100;

		$cart_cents = function_exists( 'paymob_pixel_amount_to_cents' )
			? paymob_pixel_amount_to_cents( $cart_total, $cents )
			: (int) round( $cart_total * $cents );

		// Pixel intention base must ALWAYS be the live cart.
		// Using a previous discounted final (e.g. 450) as the base makes Paymob
		// re-apply BIN discount on top (450 → 405) and causes confirmation/id churn.
		$amount = $cart_cents;

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

		$billing = array(
			'email'        => ! empty( $billing_data['email'] ) ? $billing_data['email'] : 'customer@example.com',
			'first_name'   => ! empty( $billing_data['first_name'] ) ? $billing_data['first_name'] : 'NA',
			'last_name'    => ! empty( $billing_data['last_name'] ) ? $billing_data['last_name'] : 'NA',
			'street'       => $street,
			'phone_number' => ! empty( $billing_data['phone'] ) ? $billing_data['phone'] : 'NA',
			'city'         => ! empty( $billing_data['city'] ) ? $billing_data['city'] : 'NA',
			'country'      => ! empty( $billing_data['country'] ) ? $billing_data['country'] : 'NA',
			'state'        => ! empty( $billing_data['state'] ) ? $billing_data['state'] : 'NA',
			'postal_code'  => ! empty( $billing_data['postcode'] ) ? $billing_data['postcode'] : 'NA',
		);

		$paymobReq          = new Paymob( $debug, $addlog );
		$existing_cs        = WC()->session ? (string) WC()->session->get( 'cs' ) : '';
		$existing_amount    = WC()->session ? (int) WC()->session->get( 'PaymobCentsAmount' ) : 0;
		$intention_order_id = WC()->session ? WC()->session->get( 'intention_order_id' ) : '';
		$session_final      = WC()->session ? (int) WC()->session->get( 'paymob_final_cents' ) : 0;
		$pixel_identifier   = WC()->session ? (string) WC()->session->get( 'pixel_identifier' ) : '';

		// Never reuse a CS already linked to a paid/completed Woo order (Place Order → "already paid").
		if ( ! $force_new && '' !== $existing_cs && self::session_intention_already_paid( $pixel_identifier ) ) {
			$force_new = true;
			if ( function_exists( 'paymob_pixel_clear_discount_session' ) ) {
				paymob_pixel_clear_discount_session( true );
			}
			$existing_cs        = '';
			$existing_amount    = 0;
			$intention_order_id = '';
			$session_final      = 0;
			$pixel_identifier   = '';
		}

		// Intention was left at a previous discounted amount → reset to cart + clear discount.
		$was_discounted_base = ( $session_final > 0 && $existing_amount > 0 && $existing_amount === $session_final && abs( $existing_amount - $cart_cents ) > 1 )
			|| ( $existing_amount > 0 && $existing_amount < $cart_cents - 1 );

		if ( $was_discounted_base && function_exists( 'paymob_pixel_clear_discount_session' ) ) {
			paymob_pixel_clear_discount_session( false );
			$session_final = 0;
		}

		// Reuse existing intention when amount is unchanged at cart base.
		if ( ! $force_new && '' !== $existing_cs && $existing_amount > 0 && $existing_amount === (int) $amount && ! $was_discounted_base ) {
			wp_send_json_success(
				array(
					'cs'                 => $existing_cs,
					'intentionId'        => WC()->session->get( 'PaymobIntentionId' ),
					'centsAmount'        => $existing_amount,
					'intention_order_id' => $intention_order_id,
					'reused'             => true,
				)
			);
		}

		if ( ! $force_new && '' !== $existing_cs ) {
			$put_data = array(
				'amount'       => $amount,
				'billing_data' => $billing,
			);
			if ( ! empty( $intention_order_id ) ) {
				$put_data['accept_order_id'] = $intention_order_id;
			}
			$put_status = $paymobReq->createIntention( $secKey, $put_data, 'Update Pixel', $existing_cs, 'PUT' );
			$put_ok     = ! empty( $put_status['success'] )
				|| ! empty( $put_status['cs'] )
				|| ( isset( $put_status['message'] ) && is_numeric( $put_status['message'] ) );

			if ( $put_ok ) {
				WC()->session->set( 'PaymobCentsAmount', $amount );
				$cs_out = ! empty( $put_status['cs'] ) ? $put_status['cs'] : $existing_cs;
				wp_send_json_success(
					array(
						'cs'                 => $cs_out,
						'intentionId'        => WC()->session->get( 'PaymobIntentionId' ),
						'centsAmount'        => $amount,
						'intention_order_id' => $intention_order_id,
						'updated'            => true,
						'reset_to_cart'      => $was_discounted_base,
					)
				);
			}
			// PUT failed (often "already being processed" / duplicate) — force a brand-new intention id.
			$force_new = true;
			if ( function_exists( 'paymob_pixel_clear_discount_session' ) ) {
				paymob_pixel_clear_discount_session( true );
			}
			$existing_cs = '';
		}

		// Always unique — avoids Paymob "duplicate order with same id" on special_reference.
		$pixel_identifier = 'pixel_' . str_replace( '.', '', uniqid( (string) wp_rand( 1000, 9999 ), true ) ) . '_' . (string) time();

		$data = array(
			'amount'            => $amount,
			'currency'          => get_woocommerce_currency(),
			'payment_methods'   => self::getIntegrationIds(),
			'billing_data'      => $billing,
			'extras'            => array( 'merchant_intention_id' => $pixel_identifier ),
			'special_reference' => $pixel_identifier,
		);
		$data['card_tokens'] = Paymob_Saved_Cards_Tokens::getUserTokens();

		$status = $paymobReq->createIntention( $secKey, $data, 'Loading Pixel', '', 'POST' );
		if ( empty( $status['cs'] ) ) {
			wp_send_json_error( ! empty( $status['message'] ) ? $status['message'] : __( 'Error in creating Paymob Intension, please try again.', 'paymob-for-woocommerce' ) );
		}

		$session = WC()->session;
		$session->__unset( 'cs' );
		$session->__unset( 'pixel_identifier' );
		$session->__unset( 'PaymobIntentionId' );
		$session->__unset( 'PaymobCentsAmount' );

		WC()->session->set( 'cs', $status['cs'] );
		WC()->session->set( 'pixel_identifier', $pixel_identifier );
		WC()->session->set( 'PaymobIntentionId', $status['intentionId'] );
		// Cart base only — discounted finals live in paymob_final_cents for create_order.
		WC()->session->set( 'PaymobCentsAmount', $amount );
		Paymob_Pixel_Checkout::create_paymob_intention_and_insert( $status['cs'], $pixel_identifier );

		WC()->session->set( 'intention_order_id', $status['intention_order_id'] );
		$status['centsAmount'] = $amount;
		$status['created']     = true;
		wp_send_json_success( $status );
	}

	private static function post_flag( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return false;
		}
		$val = strtolower( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		return ( '1' === $val || 'true' === $val || 'yes' === $val );
	}

	/**
	 * True when session pixel_identifier is mapped to a Woo order that is already paid.
	 *
	 * @param string $pixel_identifier Session merchant intention id.
	 * @return bool
	 */
	private static function session_intention_already_paid( $pixel_identifier ) {
		$pixel_identifier = (string) $pixel_identifier;
		if ( '' === $pixel_identifier ) {
			return false;
		}

		global $wpdb;
		$woo_order_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT merchant_order_id FROM {$wpdb->prefix}paymob_pixel_intentions WHERE pixel_identifier = %s ORDER BY id DESC LIMIT 1",
				$pixel_identifier
			)
		);
		if ( $woo_order_id <= 0 ) {
			return false;
		}

		$order = wc_get_order( $woo_order_id );
		if ( ! $order ) {
			return false;
		}

		$status = $order->get_status();
		// Reusable only while still open for payment.
		return ! in_array( $status, array( 'pending', 'pending-payment', 'failed', 'on-hold', 'checkout-draft' ), true );
	}

	public static function getIntegrationIds() {
		$pixelOptions                      = get_option( 'woocommerce_paymob-pixel_settings' );
		$integration_ids                   = array();
		$cards_integration_id              = isset( $pixelOptions['cards_integration_id'] ) ? $pixelOptions['cards_integration_id'] : '';
		$apple_pay_integration_id          = isset( $pixelOptions['apple_pay_integration_id'] ) ? $pixelOptions['apple_pay_integration_id'] : '';
		$google_pay_integration_id         = isset( $pixelOptions['google_pay_integration_id'] ) ? $pixelOptions['google_pay_integration_id'] : '';
		$bank_installment_integration_id   = isset( $pixelOptions['bank_installment_integration_id'] ) ? $pixelOptions['bank_installment_integration_id'] : '';

		if ( ! empty( $cards_integration_id ) ) {
			foreach ( $cards_integration_id as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$integration_ids[] = $id;
				}
			}
		}
		if ( ! empty( $apple_pay_integration_id ) ) {
			$integration_ids[] = (int) $apple_pay_integration_id;
		}
		if ( ! empty( $google_pay_integration_id ) ) {
			$integration_ids[] = (int) $google_pay_integration_id;
		}
		// PRD: Bank Installment on Pixel is Egypt-only.
		if ( PaymobAutoGenerate::is_paymob_egypt_merchant() ) {
			$bank_ids = PaymobAutoGenerate::get_pixel_bank_installment_integration_ids( false );
			$chosen_bank_id = '';
			if ( 1 === count( $bank_ids ) ) {
				$chosen_bank_id = (string) array_key_first( $bank_ids );
			} else {
				$bank_candidates = is_array( $bank_installment_integration_id )
					? $bank_installment_integration_id
					: array( (string) $bank_installment_integration_id );
				foreach ( $bank_candidates as $id ) {
					$id = (string) $id;
					if ( isset( $bank_ids[ $id ] ) ) {
						$chosen_bank_id = $id;
						break;
					}
				}
			}
			if ( '' !== $chosen_bank_id ) {
				$integration_ids[] = (int) $chosen_bank_id;
			}
		}
		return $integration_ids;
	}
}
