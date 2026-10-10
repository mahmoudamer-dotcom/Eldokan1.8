<?php

class Paymob_Main_Partner_Info
{

	const PARTNER_CONNECT_NONCE_ACTION = 'paymob_partner_connect';

	public static function get_partner_redirect_url() {
		$url = str_replace( 'amp;', '', self_admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob-main&popup=true' ) );
		return add_query_arg(
			'paymob_partner_nonce',
			wp_create_nonce( self::PARTNER_CONNECT_NONCE_ACTION ),
			$url
		);
	}

	public static function mark_partner_connect_started() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			set_transient( 'paymob_partner_connect_' . $user_id, 1, 15 * MINUTE_IN_SECONDS );
		}
	}

	private static function verify_partner_connect_request() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$nonce = isset( $_GET['paymob_partner_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['paymob_partner_nonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::PARTNER_CONNECT_NONCE_ACTION ) ) {
			$transient_key = 'paymob_partner_connect_' . $user_id;
			if ( get_transient( $transient_key ) ) {
				delete_transient( $transient_key );
				return true;
			}
			return false;
		}

		return true;
	}

	/**
	 * Allow Paymob onboarding hosts for wp_safe_redirect().
	 *
	 * @param string $url Redirect URL.
	 */
	public static function safe_redirect( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( $host ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $host ) {
					$hosts[] = $host;
					return array_unique( $hosts );
				}
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function partner_info()
	{
		global $wpdb;
		if ( empty( Paymob::filterVar( 'woocode', 'GET' ) ) ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! self::verify_partner_connect_request() ) {
			return;
		}

		$main_settings = get_option('woocommerce_paymob-main_settings', array());
			$testPubKey = isset($main_settings['test_pub_key']) ? sanitize_text_field($main_settings['test_pub_key']) : '';
			$livePubKey = isset($main_settings['live_pub_key']) ? sanitize_text_field($main_settings['live_pub_key']) : '';
			$testSecKey = isset($main_settings['test_sec_key']) ? sanitize_text_field($main_settings['test_sec_key']) : '';
			$livesecKey = isset($main_settings['live_sec_key']) ? sanitize_text_field($main_settings['live_sec_key']) : '';
			$apiKey = isset($main_settings['api_key']) ? sanitize_text_field($main_settings['api_key']) : '';
			$main_settings['enabled'] = isset($main_settings['enabled']) ? ($main_settings['enabled']) :'yes';

			// if (
			// 	!empty(Paymob::filterVar('woocode', 'GET'))
			// ) {
			// echo Paymob::filterVar('woocode', 'GET');exit;
			try {
				$woo_code = Paymob::filterVar('woocode', 'GET');
				$data = [
					'partner' => 'woocommerce',
					'clt' => Paymob_Main_Partner_Info::get_public_ip(),
				];
				// echo "<pre>";print_r($data);exit;
				// Paymob Request
				$paymobReq = new Paymob( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log' );
				$status = $paymobReq->getPartnerInfo($woo_code, $data);
				$status = (array) $status;

				if (!empty($status['api_key'])) {
					get_plugin_data(__FILE__);
					$currentURL = self::get_partner_redirect_url();
					$encoded_url = urlencode( $currentURL );
					// Replace with your desired custom URL
					if (isset($status['error']) || isset($status['detail'])) {
						self::safe_redirect('https://onboarding.paymob.com/auth/country-selection?partner=woocommerce&redirect_url=' . $encoded_url);
					}

					$conf['apiKey'] = $main_settings['api_key'] = $status['api_key'];
					$conf['pubKey'] = $main_settings['pub_key'] = $status['is_live'] ? $status['pk_key_live'] : $status['pk_key_test'];
					$conf['secKey'] = $main_settings['sec_key'] = $status['is_live'] ? $status['sk_key_live'] : $status['sk_key_test'];

					$result = $paymobReq->authToken($conf);
					Paymob::addLogs( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log', __('Merchant configuration: ', 'paymob-for-woocommerce'), $result);
					$gatewayData = $paymobReq->getPaymobGateways($main_settings['sec_key'], PAYMOB_PLUGIN_PATH . 'assets/img/', isset( $result['token'] ) ? $result['token'] : '');
					update_option('woocommerce_paymob_gateway_data', $gatewayData);

					if ($testPubKey !== $status['pk_key_test'] && $livePubKey !== $status['pk_key_live']) {
						Paymob_Unset_Old_Setting::unset_old_settings();
						$main_settings['enabled'] = 'yes';
					}

					update_option('woocommerce_paymob_country', Paymob::getCountryCode($main_settings['pub_key']));
					delete_option('woocommerce_paymob_gateway_data_failure');
					delete_option('woocommerce_paymob_widget_settings');
					// Generate gateways 
					PaymobAutoGenerate::create_gateways($result, 1, $gatewayData);

					$ids = array();
					$main_integration_id_hidden = array();
					$integration_id_hidden = array();
					foreach ($result['integrationIDs'] as $value) {
                        $text = $value['id'] . ' : ' . $value['name'] . ' (' . $value['type'] . ' : ' . $value['currency'] . ' : ' . $value['mode'] . ' : ' . $value['is_moto'] . ' : ' . $value['is_3DS'] . ' )';
						$main_integration_id_hidden[] = $text . ',';
						if (isset($value['mode']) && $value['mode'] == $status['is_live'] ? 'live' : 'test') {
							$integration_id_hidden[] = $text . ',';
							$ids[] = trim($value['id']);
						}
						// Update webhook Url by default
						$webhhok = $paymobReq->getIntegrationID($conf, $value['id']);
						if ( false !== strpos( $webhhok->transaction_processed_callback, 'api/acceptance/post_pay' ) ) {
							$data = array(
								'transaction_processed_callback' => add_query_arg(array('wc-api' => 'paymob_callback'), home_url()),
								'transaction_response_callback' => add_query_arg(array('wc-api' => 'paymob_callback'), home_url())
							);

							$paymobReq->updateWebHookUrl($conf, $value['id'], $data);
						}
					}
					// var_dump($ids);die;
					if (!empty($ids)) {
						$main_integration_id_hidden = implode("\n", $main_integration_id_hidden);
						$integration_id_hidden = implode("\n", $integration_id_hidden);
						$paymob_default_settings = array(
							'enabled' => 'no',
							'sec_key' => $conf['secKey'],
							'pub_key' => $conf['pubKey'],
							'api_key' => $conf['apiKey'],
							'title' => 'Pay with Paymob',
							'description' => 'Pay with Paymob',
							'integration_id' => $ids,
							'integration_id_hidden' => $integration_id_hidden,
							'hmac_hidden' => $result['hmac'],
							'empty_cart' => 'no',
							'debug' => 'no',
							'logo' => plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/img/paymob.png',
						);
						$main_settings['integration_id_hidden'] = $main_integration_id_hidden;
						$main_settings['enabled'] = 'yes';
						update_option('woocommerce_paymob_settings', $paymob_default_settings);

						Paymob_Unset_Old_Setting::paymob_setting($status['is_live'] ? 'live' : 'test', $ids);
					} else {
						$wpdb->delete($wpdb->prefix . 'paymob_gateways', array('gateway_id' => 'paymob'));
					}

					$main_settings['test_pub_key'] = $status['pk_key_test'];
					$main_settings['live_pub_key'] = $status['pk_key_live'];

					$main_settings['test_sec_key'] = $status['sk_key_test'];
					$main_settings['live_sec_key'] = $status['sk_key_live'];
					$main_settings['mode'] = $status['is_live'] ? 'live' : 'test';
					$main_settings['debug'] = 'yes';
					update_option('woocommerce_paymob-main_settings', $main_settings);
					Paymob_Manual_Setup_Save::pixel_settings($main_settings['mode']);
					Paymob_Manual_Setup_Save::deactive_payment($result);
					return true;
				} else {
					// Redirect with error message
					$currentURL = add_query_arg(
						'error-msg',
						rawurlencode( 'an error has been occured, please try again' ),
						self_admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob-main' )
					);
					self::safe_redirect($currentURL);

				}

			} catch (\Exception $e) {
				WC_Admin_Settings::add_error( esc_html( $e->getMessage() ) );
			}
	}
	public static function get_public_ip()
	{
		$response = wp_remote_get('https://api.ipify.org?format=json');
		$response = json_decode(wp_remote_retrieve_body($response));
		return !empty($response->ip)?$response->ip : WC_Admin_Settings::add_error(__('Error while retrieving the IP.', 'paymob-for-woocommerce')) ;
	}
}
