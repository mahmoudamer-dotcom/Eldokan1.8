<?php

class Paymob {


	public $debug_order;
	public $file;

	public function __construct( $debug_order = false, $file = null ) {
		$this->debug_order = $debug_order;
		$this->file        = $file;
	}

	public function HttpRequest( $apiPath, $method, $header = array(), $data = array() ) {
		$agent = self::filterVar( 'HTTP_USER_AGENT', 'SERVER' );
		ini_set( 'precision', 14 );
		ini_set( 'serialize_precision', -1 );

		$request_headers = array();
		foreach ( (array) $header as $header_line ) {
			if ( is_string( $header_line ) && false !== strpos( $header_line, ':' ) ) {
				list( $header_name, $header_value ) = explode( ':', $header_line, 2 );
				$request_headers[ trim( $header_name ) ] = trim( $header_value );
			}
		}

		$args = array(
			'headers'    => $request_headers,
			'timeout'    => 60,
			'user-agent' => $agent,
		);

		if ( 'GET' === $method ) {
			$response = wp_remote_get( $apiPath, $args );
		} elseif ( 'PUT' === $method ) {
			$args['method'] = 'PUT';
			$args['body']   = wp_json_encode( $data );
			$response       = wp_remote_request( $apiPath, $args );
		} else {
			$args['body'] = wp_json_encode( $data );
			$response     = wp_remote_post( $apiPath, $args );
		}

		if ( is_wp_error( $response ) ) {
			$curl_error = $response->get_error_message();
			if ( class_exists( 'Paymob_Error_Logs' ) ) {
				Paymob_Error_Logs::log_http_raw_response(
					'HTTP request failed: ' . $curl_error,
					(string) $apiPath,
					(string) $method,
					0,
					''
				);
			}
			throw new Exception( esc_html( 'HTTP request error: ' . $curl_error ) );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = (string) wp_remote_retrieve_body( $response );

		$decoded  = json_decode( $raw_body, false );
		$json_err = json_last_error();

		if ( JSON_ERROR_NONE !== $json_err ) {
			if ( class_exists( 'Paymob_Error_Logs' ) ) {
				Paymob_Error_Logs::log_http_raw_response(
					'JSON decode failed: ' . json_last_error_msg() . ' â€” raw body stored below.',
					(string) $apiPath,
					(string) $method,
					$http_code,
					$raw_body
				);
			}
			return null;
		}

		if ( $http_code >= 400 ) {
			if ( class_exists( 'Paymob_Error_Logs' ) ) {
				Paymob_Error_Logs::log_http_raw_response(
					'HTTP ' . $http_code . ' â€” raw Paymob body stored below.',
					(string) $apiPath,
					(string) $method,
					$http_code,
					$raw_body
				);
			}
		}

		return $decoded;
	}

	public function authToken( $conf ) {
		$this->matchCountries( $conf );
		$this->addLogs( $this->debug_order, $this->file, ' Authenticate Paymob configuration' );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $conf['secKey'] ) );
		$tokenRes = $this->HttpRequest( $apiUrl . 'api/auth/tokens', 'POST', array( 'Content-Type: application/json' ), array( 'api_key' => $conf['apiKey'] ) );

		// Never log tokens / profile secrets — status only.
		$this->addLogs(
			$this->debug_order,
			$this->file,
			' In api/auth/tokens Response: token_received=' . ( isset( $tokenRes->token ) ? 'yes' : 'no' )
		);

		if ( isset( $tokenRes->token ) ) {
			$hmacRes     = $this->getHmac( $tokenRes->token, $apiUrl );
			$integIDsRes = $this->getIntegrationIDs( $tokenRes->token, $apiUrl, $this->matchMode( $conf ) );
			$data        = array(
				'hmac'           => $hmacRes,
				'integrationIDs' => $integIDsRes,
				'token'=>$tokenRes->token,
			);

			return $data;
		} else {
			throw new Exception( 'Cannot get Token from PayMob account' );
		}
	}

	public function getHmac( $token, $apiUrl ) {
		$hmacRes = $this->HttpRequest( $apiUrl . 'api/auth/hmac_secret/get_hmac', 'GET', array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token ) );
		$this->addLogs(
			$this->debug_order,
			$this->file,
			' In api/auth/hmac_secret/get_hmac Response: hmac_received=' . ( isset( $hmacRes->hmac_secret ) ? 'yes' : 'no' )
		);
		if ( isset( $hmacRes->hmac_secret ) ) {
			return $hmacRes->hmac_secret;
		} else {
			throw new Exception( 'Cannot get HMAC from PayMob account' );
		}
	}

	public function getIntegrationIDs( $token, $apiUrl, $isTest = false ) {
		$intRes = $this->HttpRequest( $apiUrl . 'api/ecommerce/integrations?is_plugin=true&is_next=yes&page_size=500&is_deprecated=false&is_standalone=false&is_shopify=false', 'GET', array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token ) );
		$this->addLogs( $this->debug_order, $this->file, ' In api/ecommerce/integrations Response: ' . json_encode( $intRes ) );

		if ( ! empty( $intRes ) ) {
			$IntegrationIDs = array();
			foreach ( $intRes->results as $key => $integration ) {
				$type = $integration->gateway_type;
				// var_dump($integration);
				if ( 'VPC' == $type ) {
					$type = 'Card';
				} elseif ( 'CAGG' == $type ) {
					$type = 'Aman';
				} elseif ( 'UIG' == $type ) {
					$type = 'Wallet';
				}
				if($integration->integration_type =="moto"){
					$is_moto="yes";
				}
				else{
					$is_moto="no";
				}

				if (
					($integration->gateway_type == 'VPC' ||$integration->gateway_type == 'MIGS' ) &&
					$integration->integration_type == 'online' &&
					$integration->is_auth == false&&
					$integration->installments==null
				) {
					$is_3DS = "yes";
				} else {
					$is_3DS = "no";
				}
				if ( false == $integration->is_standalone ) {
					if($integration->is_live==false){
						$mode='test';
					}
					else
					{
						$mode='live';
					}

					$IntegrationIDs[ $integration->id ] = array(
						'id'           => $integration->id,
						'type'         => $type,
						'gateway_type' => $integration->gateway_type,
						'name'         => empty( $integration->installments ) ? $integration->integration_name : 'bank-installments',
						'currency'     => $integration->currency,
						'mode'         => $mode,
						'is_moto'      =>$is_moto,
						'is_3DS'       =>$is_3DS

					);
				}
				
			}
			// die;
			return $IntegrationIDs;
		} else {
			throw new Exception( 'Cannot get available integration IDs from PayMob account' );
		}
	}

	public function createIntention( $secKey, $data, $orderId ,$cs,$method) {
		$flash  = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$header = array( 'Content-Type: application/json', 'Authorization: Token ' . $secKey );
		$this->addLogs( $this->debug_order, $this->file, print_r( $data, 1 ) );
		$intention = $this->HttpRequest( $flash . 'v1/intention/'.$cs, $method, $header, $data );
		$text= (!empty($cs)) ? 'Update ' : '';
		$note_i    = $text.'Intention response for order # ' . $orderId;
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $intention, 1 ) );
		if ( empty( $intention->payment_keys ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $intention );
		}
		$status = array(
			'cs'      => null,
			'success' => false,
		);

		if ( isset( $intention->amount_cents ) ) {
			$status['message'] = $intention->amount_cents;
			return $status;
		}
		if ( isset( $intention->detail ) ) {
			$status['message'] = $intention->detail;
			return $status;
		}
		if ( isset( $intention->amount ) ) {
			$status['message'] = $intention->amount[0];
			return $status;
		}
		if ( isset( $intention->billing_data ) ) {
			$status['message'] = 'Ops, there is missing billing information!';
			return $status;
		}

		if ( isset( $intention->integrations ) ) {
			$status['message'] = $intention->integrations[0];
			return $status;
		}

		if ( isset( $intention->client_secret ) ) {
			$status['success']     = true;
			$status['cs']          = $intention->client_secret;
			$status['intentionId'] = $intention->id;
			$status['centsAmount'] = $intention->intention_detail->amount;
			if(empty($cs)){
				$status['intention_order_id'] = $intention->intention_order_id;
			}
		} else {
			$status['message'] = ( isset( $intention->code ) ) ? $intention->code : 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $status ) );
		return $status;
	}
	/**
	 * Get a list of Paymob Gateways, their Logos, and names.
	 *
	 * @return array of Paymob data
	 */
	public function getPaymobGateways( $secKey, $path, $token = '' ) {
		// get gateways data with Bearer token auth (same auth flow as other endpoints)
		$flash = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$header = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $token,
		);

		$getways = $this->HttpRequest( $flash . 'api/ecommerce/gateways', 'GET', $header );
		$this->addLogs( $this->debug_order, $this->file, 'In api/ecommerce/gateways Response: ', json_encode( $getways ) );
		// Handle invalid or empty responses
		if ( is_null( $getways ) || ! isset( $getways->result ) ) {
			$this->addLogs( $this->debug_order, $this->file, 'In api/ecommerce/gateways Response: Cannot get Gateways Data from PayMob account.' );
			throw new Exception( 'Cannot get Gateways Data from PayMob account' );
		}

		// Process the gateways data if available
		$gateways = json_decode( json_encode( $getways->result, true ), true );
		return $this->extractGatewaysData( $gateways, $path );
	}

	private static function sanitize_gateway_code( $code ) {
		$code = strtolower( (string) $code );
		return preg_replace( '/[^a-z0-9_-]/', '', $code );
	}

	private static function is_allowed_paymob_logo_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		return (bool) preg_match( '/(^|\.)paymob\.com$/', strtolower( $host ) );
	}

	private static function is_valid_logo_image( $data ) {
		if ( empty( $data ) || ! function_exists( 'getimagesizefromstring' ) ) {
			return false;
		}

		$info = @getimagesizefromstring( $data );
		if ( false === $info ) {
			return false;
		}

		return in_array( $info[2], array( IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP ), true );
	}

	private static function get_safe_gateway_logo_path( $assets_dir, $code ) {
		$assets_dir = trailingslashit( wp_normalize_path( $assets_dir ) );
		$logo_path  = wp_normalize_path( $assets_dir . $code . '.png' );

		if ( 0 !== strpos( $logo_path, $assets_dir ) ) {
			return '';
		}

		$real_assets = realpath( $assets_dir );
		if ( false !== $real_assets ) {
			$parent_dir = dirname( $logo_path );
			if ( ! is_dir( $parent_dir ) ) {
				wp_mkdir_p( $parent_dir );
			}
			$real_parent = realpath( $parent_dir );
			if ( false === $real_parent || 0 !== strpos( wp_normalize_path( $real_parent ), wp_normalize_path( $real_assets ) ) ) {
				return '';
			}
		}

		return $logo_path;
	}

	private static function download_gateway_logo( $logo_url, $logo_path ) {
		if ( empty( $logo_url ) || empty( $logo_path ) || file_exists( $logo_path ) ) {
			return;
		}

		$response = wp_safe_remote_get(
			$logo_url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$data = wp_remote_retrieve_body( $response );
		if ( ! self::is_valid_logo_image( $data ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing validated image bytes to plugin assets cache.
		file_put_contents( $logo_path, $data );
	}

	public static function extractGatewaysData( $gateways, $path ) {
		$gatewaysData = array();

		// Check if $gateways is an array or object before processing
		if ( ! is_array( $gateways ) && ! is_object( $gateways ) ) {
			return $gatewaysData; // Return empty data if $gateways is not valid
		}

		foreach ( $gateways as $gateway ) {
			$code = self::sanitize_gateway_code( isset( $gateway['code'] ) ? $gateway['code'] : '' );
			if ( '' === $code ) {
				continue;
			}

			$logoPath = self::get_safe_gateway_logo_path( $path, $code );
			$logo_url = '';

			if ( ! empty( $gateway['logo'] ) ) {
				$logo_url = esc_url_raw( $gateway['logo'], array( 'https' ) );
				if ( $logo_url && self::is_allowed_paymob_logo_url( $logo_url ) ) {
					self::download_gateway_logo( $logo_url, $logoPath );
				} else {
					$logoPath = '';
				}
			} else {
				$logoPath = '';
			}

			$gatewaysData[ $code ] = array(
				'title' => isset( $gateway['label'] ) ? $gateway['label'] : '',
				'desc'  => isset( $gateway['description'] ) ? $gateway['description'] : '',
				'logo'  => $logo_url ? $logo_url : ( isset( $gateway['logo'] ) ? $gateway['logo'] : '' ),
			);
		}

		return $gatewaysData;
	}
	public function registerFramework( $secKey, $data ) {
		$flash       = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$header      = array( 'Content-Type: application/json', 'Authorization: Token ' . $secKey );
		$registerRes = $this->HttpRequest( $flash . 'api/ecommerce/plugins', 'POST', $header, $data );
		$this->addLogs( $this->debug_order, $this->file, ' In api/ecommerce/plugins: ' . json_encode( $registerRes ) );
		return $registerRes;
	}

	/**
	 * Fetch ValU installment tenures for a given amount.
	 *
	 * @param string $secKey Secret key.
	 * @param array  $data   Request payload (expects amount).
	 * @return object|null
	 */
	public function valuWidget( $secKey, $data ) {
		$api_url = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$header  = array( 'Content-Type: application/json', 'Authorization: Token ' . $secKey );
		$this->addLogs( $this->debug_order, $this->file, 'ValU Widget request', $data );
		$response = $this->HttpRequest( $api_url . 'api/acceptance/valu', 'POST', $header, $data );
		$this->addLogs( $this->debug_order, $this->file, 'ValU Widget response', $response );
		return $response;
	}
	public function refundPayment( $secKey, $data ) {
		$flash  = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$header = array( 'Content-Type: application/json', 'Authorization: Token ' . $secKey );
		$this->addLogs( $this->debug_order, $this->file, print_r( $data, 1 ) );
		$refundRes = $this->HttpRequest( $flash . 'api/acceptance/void_refund/refund', 'POST', $header, $data );
		$this->addLogs( $this->debug_order, $this->file, ' In api/acceptance/void_refund/refund: ' . json_encode( $refundRes ) );

		if ( isset( $refundRes->detail ) ) {
			$status['message'] = $refundRes->detail;
			return $status;
		}
		if ( isset( $refundRes->message ) ) {
			$status['message'] = $refundRes->message;
			return $status;
		}
		if ( isset( $refundRes->amount_cents[0] ) ) {
			$status['message'] = $refundRes->amount_cents[0] ;
			return $status;
		} 
		if ( isset( $refundRes->success ) ) {
			$status['success']   = true;
			$status['refund_id'] = $refundRes->id;
		} else {
			$status['success'] = false;
			$status['message'] = 'Something went wrong';
		}
		return $status;
	}
	public function getOnboardingUrl( $code, $data ) {
		$flash       = $this->getApiUrl($code);
		$header = array( 'Content-Type: application/json');
		$this->addLogs( $this->debug_order, $this->file, ' In api/onboarding/partners-utils/country_url request', self::redact_for_log( $data ) );
		$onboardingRes = $this->HttpRequest( $flash . 'api/onboarding/partners-utils/country_url', 'POST', $header, $data );
		// Log URL host only — never woocode / tokens embedded in the onboarding URL.
		$url_host = '';
		if ( is_object( $onboardingRes ) && ! empty( $onboardingRes->url ) && is_string( $onboardingRes->url ) ) {
			$parts    = wp_parse_url( $onboardingRes->url );
			$url_host = isset( $parts['host'] ) ? $parts['host'] : '';
		}
		$this->addLogs(
			$this->debug_order,
			$this->file,
			' In api/onboarding/partners-utils/country_url: host=' . $url_host . ' url_received=' . ( $url_host ? 'yes' : 'no' )
		);
		return $onboardingRes;
	}
	public function getPartnerInfo( $woo_code, $data ) {
		// return $this->getCountryCode( $woo_code );
		$flash       = $this->getApiUrl( strtolower($this->getCountryCode( $woo_code )) );
		$header      = array( 'Content-Type: application/json', 'Authorization:' . $woo_code );
		$this->addLogs( $this->debug_order, $this->file, ' In api/onboarding/partners-utils/merchant_info request', self::redact_for_log( $data ) );
		$partnerInfoRes = $this->HttpRequest( $flash . 'api/onboarding/partners-utils/merchant_info', 'POST', $header, $data );
		// Never log api_key / secrets from merchant_info — ids / flags only.
		$safe_info = array();
		if ( is_object( $partnerInfoRes ) ) {
			if ( isset( $partnerInfoRes->merchant_id ) ) {
				$safe_info['merchant_id'] = $partnerInfoRes->merchant_id;
			}
			if ( isset( $partnerInfoRes->is_live ) ) {
				$safe_info['is_live'] = $partnerInfoRes->is_live;
			}
			if ( isset( $partnerInfoRes->error ) ) {
				$safe_info['error'] = $partnerInfoRes->error;
			}
			$safe_info['has_api_key'] = isset( $partnerInfoRes->api_key );
		} elseif ( is_array( $partnerInfoRes ) ) {
			$safe_info = self::redact_for_log( $partnerInfoRes );
		}
		$this->addLogs( $this->debug_order, $this->file, ' In api/onboarding/partners-utils/merchant_info: ', $safe_info );
		return $partnerInfoRes;
	}
	public function matchMode( $conf ) {
		$pubKeyMode = $this->getMode( $conf['pubKey'] );
		$secKeyMode = $this->getMode( $conf['secKey'] );

		if ( $secKeyMode != $pubKeyMode ) {
			throw new Exception( 'Public and Secret Keys does not belong to the ( live/test ) mode' );
		}
		return ( 'live' == $pubKeyMode );
	}

	public function matchCountries( $conf ) {
		$pubKey = $this->getCountryCode( $conf['pubKey'] );
		$secKey = $this->getCountryCode( $conf['secKey'] );
		if ( $pubKey != $secKey ) {
			throw new Exception( 'Public and Secret Keys does not belong to the same country' );
		}
		return true;
	}

	public function getMode( $code ) {
		return substr( $code, 7, 4 );
	}

	public static function getCountryCode( $code ) {
		return substr( $code, 0, 3 );
	}

	public static function getIntentionId( $merchantIntentionId ) {
		return substr( $merchantIntentionId, 0, -11 );
	}

	public static function verifyHmac( $key, $data, $intention = null, $hmac = null,$is_subscription=false ) {
		if ( isset( $hmac) && $is_subscription==true  ) {
			return self::verifysubscriptionHmac( $key, $data, $hmac );
		}elseif(isset( $hmac) && $is_subscription==false ){
			return self::verifyAcceptHmac( $key, $data, $hmac );
		} else {
			return self::verifyFlashHmac( $key, $data, $intention );
		}
	}

	public static function verifysubscriptionHmac( $key, $subscription_data, $hmac ) {
		if (empty($subscription_data['trigger_type']) || empty($subscription_data['subscription_data']['id'])) {
			return false; // Invalid input
		}

		$concatenated_string = $subscription_data['trigger_type'] . 'for' . $subscription_data['subscription_data']['id'];

		// Step 2: Calculate HMAC using SHA-512 and the secret key
		$hash = hash_hmac('sha512', $concatenated_string, $key);

		return $hash === $hmac;
	}

	public static function verifyFlashHmac( $key, $data, $intention = null ) {

		if ( empty( $intention ) ) {
			// callback GET
			$str  = $data['amount_cents']
				. $data['created_at']
				. $data['currency']
				. $data['error_occured']
				. $data['has_parent_transaction']
				. $data['id']
				. $data['integration_id']
				. $data['is_3d_secure']
				. $data['is_auth']
				. $data['is_capture']
				. $data['is_refunded']
				. $data['is_standalone_payment']
				. $data['is_voided']
				. $data['order']
				. $data['owner']
				. $data['pending']
				. $data['source_data_pan']
				. $data['source_data_sub_type']
				. $data['source_data_type']
				. $data['success'];
			$hash = hash_hmac( 'sha512', $str, $key );
		} else {
			// webhook POST
			$amount = ( $intention['amount'] / $intention['cents'] );
			if ( is_float( $amount ) ) {
				$amountArr = explode( '.', $amount );
				if ( strlen( $amountArr[1] ) == 1 ) {
					$amount = $amount . '0';
				}
			} else {
				$amount = $amount . '.00';
			}
			$str  = $amount . $intention['id'];
			$hash = hash_hmac( 'sha512', $str, $key, false );
		}

		$hmac = $data['hmac'];

		return ( $hmac === $hash );
	}

	public static function verifyAcceptHmac( $key, $json_data, $hmac ) {
		$data                           = $json_data['obj'];
		$data['order']                  = $data['order']['id'];
		$data['is_3d_secure']           = ( true === $data['is_3d_secure'] ) ? 'true' : 'false';
		$data['is_auth']                = ( true === $data['is_auth'] ) ? 'true' : 'false';
		$data['is_capture']             = ( true === $data['is_capture'] ) ? 'true' : 'false';
		$data['is_refunded']            = ( true === $data['is_refunded'] ) ? 'true' : 'false';
		$data['is_standalone_payment']  = ( true === $data['is_standalone_payment'] ) ? 'true' : 'false';
		$data['is_voided']              = ( true === $data['is_voided'] ) ? 'true' : 'false';
		$data['success']                = ( true === $data['success'] ) ? 'true' : 'false';
		$data['error_occured']          = ( true === $data['error_occured'] ) ? 'true' : 'false';
		$data['has_parent_transaction'] = ( true === $data['has_parent_transaction'] ) ? 'true' : 'false';
		$data['pending']                = ( true === $data['pending'] ) ? 'true' : 'false';
		$data['source_data_pan']        = $data['source_data']['pan'];
		$data['source_data_type']       = $data['source_data']['type'];
		$data['source_data_sub_type']   = $data['source_data']['sub_type'];

		$str  = '';
		$str  = $data['amount_cents'] .
			$data['created_at'] .
			$data['currency'] .
			$data['error_occured'] .
			$data['has_parent_transaction'] .
			$data['id'] .
			$data['integration_id'] .
			$data['is_3d_secure'] .
			$data['is_auth'] .
			$data['is_capture'] .
			$data['is_refunded'] .
			$data['is_standalone_payment'] .
			$data['is_voided'] .
			$data['order'] .
			$data['owner'] .
			$data['pending'] .
			$data['source_data_pan'] .
			$data['source_data_sub_type'] .
			$data['source_data_type'] .
			$data['success'];
		$hash = hash_hmac( 'sha512', $str, $key );
		return $hash === $hmac;
	}

	public static function getApiUrl( $countryCode ) {
		$domain = 'paymob.com/';
		if ( 'are' == $countryCode || 'uae' == $countryCode ) {
			return 'https://uae.' . $domain;
		} elseif ( 'egy' == $countryCode ) {
			return 'https://accept.' . $domain;
		} elseif ( 'pak' == $countryCode ) {
			return 'https://pakistan.' . $domain;
		} elseif ( 'ksa' == $countryCode || 'sau' == $countryCode ) {
			return 'https://ksa.' . $domain;
		} elseif ( 'omn' == $countryCode ) {
			return 'https://oman.' . $domain;
		} else {
			throw new Exception( 'Another country' );
		}
	}

	public static function getTimeZone( $country ) {
		switch ( $country ) {
			case 'omn':
				return 'Asia/Muscat';
			case 'pak':
				return 'Asia/Karachi';
			case 'ksa':
			case 'sau':
				return 'Asia/Riyadh';
			case 'are':
			case 'uae':
				return 'Asia/Dubai';
			case 'egy':
			default:
				return 'Africa/Cairo';
		}
	}

	/**
	 * Filter the GLOBAL variables
	 *
	 * @param string $name The field name the need to be filter.
	 * @param string $global value could be (GET, POST, REQUEST, COOKIE, SERVER).
	 *
	 * @return string|null
	 */
	public static function filterVar( $name, $global = 'GET' ) {
		if ( isset( $GLOBALS[ '_' . $global ][ $name ] ) ) {
			if ( is_array( $GLOBALS[ '_' . $global ][ $name ] ) ) {
				return $GLOBALS[ '_' . $global ][ $name ];
			}
			return htmlspecialchars( $GLOBALS[ '_' . $global ][ $name ], ENT_QUOTES );
		}
		return null;
	}

	public static function sanitizeVar( $type = 'GET' ) {
		return $GLOBALS[ '_' . $type ];
	}

	/**
	 * WooCommerce log directory with trailing slash (PHPStan-safe; avoids WC_LOG_DIR).
	 *
	 * @return string
	 */
	public static function log_dir() {
		if ( function_exists( 'wc_get_log_dir' ) ) {
			return trailingslashit( wc_get_log_dir() );
		}

		$upload_dir = wp_upload_dir( null, false );
		$base       = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';

		return trailingslashit( $base ) . 'wc-logs/';
	}

	/**
	 * Private Paymob log directory. Log files are PHP scripts that return HTTP 403
	 * when requested over the web (works on Nginx + Apache; .htaccess alone is ignored by Nginx).
	 *
	 * @return string
	 */
	public static function secure_log_dir() {
		$upload_dir = wp_upload_dir( null, false );
		$base       = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';
		$dir        = trailingslashit( $base ) . 'paymob-private-logs';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		self::ensure_log_dir_denied( $dir );

		return trailingslashit( $dir );
	}

	/**
	 * Write Apache/Nginx-friendly deny stubs into a log directory.
	 *
	 * @param string $dir Absolute directory path.
	 */
	public static function ensure_log_dir_denied( $dir ) {
		$dir = trailingslashit( $dir );

		$htaccess = $dir . '.htaccess';
		$ht_body  = "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
		if ( ! file_exists( $htaccess ) || false === strpos( (string) file_get_contents( $htaccess ), 'Deny from all' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, $ht_body, LOCK_EX );
		}

		$index_php = $dir . 'index.php';
		$deny_php  = "<?php\nhttp_response_code( 403 );\nheader( 'Content-Type: text/plain; charset=utf-8' );\nheader( 'X-Content-Type-Options: nosniff' );\nexit( 'Forbidden' );\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $index_php, $deny_php, LOCK_EX );

		$index_html = $dir . 'index.html';
		if ( ! file_exists( $index_html ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_html, '', LOCK_EX );
		}

		// Nginx sample (hosts must include it); documents the required 403 for web servers that ignore .htaccess.
		$nginx = $dir . 'nginx-deny.conf';
		if ( ! file_exists( $nginx ) ) {
			$snippet = "# Include from your Nginx server block to deny HTTP access to Paymob/WooCommerce logs:\n"
				. "# include " . $dir . "nginx-deny.conf;\n"
				. "location ~* /wp-content/uploads/(wc-logs|paymob-private-logs)/ {\n"
				. "    deny all;\n"
				. "    return 403;\n"
				. "}\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $nginx, $snippet, LOCK_EX );
		}
	}

	/**
	 * Site-specific hash for a log channel (filename has no guessable "paymob-pixel" etc.).
	 *
	 * @param string $source Log source key.
	 * @return string Hex hash (64 chars).
	 */
	public static function log_source_hash( $source ) {
		$source = strtolower( sanitize_file_name( (string) $source ) );
		if ( '' === $source ) {
			$source = 'paymob';
		}

		$payload = 'paymob-log|' . $source;
		if ( function_exists( 'wp_salt' ) ) {
			return hash_hmac( 'sha256', $payload, (string) wp_salt( 'auth' ) );
		}
		if ( function_exists( 'wp_hash' ) ) {
			return hash( 'sha256', wp_hash( $payload ) );
		}

		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'paymob';
		return hash( 'sha256', $payload . '|' . $salt );
	}

	/**
	 * PHP file path for a log source.
	 * Pattern: {source}-{Y-m-d}-{hash}.php (readable channel + date + unguessable hash).
	 * HTTP access still executes PHP → 403.
	 *
	 * @param string $source Log source key.
	 * @return string
	 */
	public static function secure_log_file( $source ) {
		$source = strtolower( sanitize_file_name( (string) $source ) );
		if ( '' === $source ) {
			$source = 'paymob';
		}
		// Normalize common aliases for clearer filenames in the private logs folder.
		$source = str_replace( '_', '-', $source );
		if ( 0 !== strpos( $source, 'paymob' ) ) {
			$source = 'paymob-' . $source;
		}

		$date = gmdate( 'Y-m-d' );
		$hash = self::log_source_hash( $source . '|' . $date );

		return self::secure_log_dir() . $source . '-' . $date . '-' . $hash . '.php';
	}

	/**
	 * Remove legacy predictable *.log files that Nginx would serve as static downloads.
	 */
	public static function purge_public_paymob_logs() {
		$dirs = array_unique(
			array_filter(
				array(
					self::log_dir(),
					defined( 'PAYMOB_PLUGIN_PATH' ) ? PAYMOB_PLUGIN_PATH : '',
					defined( 'PAYMOB_PLUGIN_PATH' ) ? PAYMOB_PLUGIN_PATH . 'logs/' : '',
				)
			)
		);

		foreach ( $dirs as $dir ) {
			$dir = trailingslashit( $dir );
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$patterns = array(
				$dir . 'paymob*.log',
				$dir . 'paymob-auth.log',
				$dir . 'paymob-pixel.log',
				$dir . 'paymob-token.log',
				$dir . 'paymob-subscription.log',
				$dir . 'paymob.log',
			);

			foreach ( $patterns as $pattern ) {
				$matches = glob( $pattern );
				if ( empty( $matches ) ) {
					continue;
				}
				foreach ( $matches as $file ) {
					if ( is_file( $file ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
						@unlink( $file );
					}
				}
			}

			// Reinforce deny on WooCommerce log dir (Apache); Nginx needs return 403 via server config / PHP-guarded files.
			if ( false !== strpos( $dir, 'wc-logs' ) ) {
				self::ensure_log_dir_denied( $dir );
			}
		}

		// Remove only legacy predictable PHP log names (exact), never dated/hashed files.
		$upload  = wp_upload_dir( null, false );
		$base    = ( ! empty( $upload['basedir'] ) ) ? $upload['basedir'] : ( WP_CONTENT_DIR . '/uploads' );
		$private = trailingslashit( $base ) . 'paymob-private-logs/';
		if ( is_dir( $private ) ) {
			$legacy_exact = array(
				'paymob.php',
				'paymob-auth.php',
				'paymob-pixel.php',
				'paymob-token.php',
				'paymob-subscription.php',
				'paymob.log.php',
			);
			foreach ( $legacy_exact as $name ) {
				$file = $private . $name;
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					@unlink( $file );
				}
			}
		}
	}

	/**
	 * If the current HTTP request targets Paymob/public log paths, end with 403.
	 * Covers Apache and Nginx setups where the request is routed through WordPress/PHP.
	 */
	public static function forbid_public_log_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$uri  = rawurldecode( $uri );
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = $uri;
		}

		// Only block direct file access under known log directories / legacy log filenames.
		$is_log_file = (bool) preg_match( '#\.log$#i', $path );
		$under_logs  = (bool) preg_match( '#/wp-content/uploads/(?:wc-logs|paymob-private-logs)/#i', $path );
		$legacy_name = (bool) preg_match( '#/(?:paymob(?:-auth|-pixel|-token|-subscription)?|paymob)\.log$#i', $path );
		$private_php = (bool) preg_match( '#/wp-content/uploads/paymob-private-logs/.+\.php$#i', $path );

		if ( ! ( ( $under_logs && $is_log_file ) || $legacy_name || $private_php || ( $under_logs && preg_match( '#/index\.php$#i', $path ) ) ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			status_header( 403 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Content-Type-Options: nosniff' );
		}
		exit( 'Forbidden' );
	}

	/**
	 * One-shot hardening: purge public logs + ensure deny stubs + block URI.
	 */
	public static function harden_logging() {
		self::forbid_public_log_request();
		self::purge_public_paymob_logs();
		self::secure_log_dir();
	}

	/**
	 * Whether Paymob debug logging is enabled in main settings.
	 *
	 * @return string '1' or '0'
	 */
	public static function debug_flag() {
		$opts = get_option( 'woocommerce_paymob-main_settings', array() );
		return ( isset( $opts['debug'] ) && 'yes' === $opts['debug'] ) ? '1' : '0';
	}

	/**
	 * Strip sensitive values before writing logs (omit entirely — never log secrets, even as placeholders).
	 *
	 * @param mixed $data Data to sanitize.
	 * @return mixed
	 */
	public static function redact_for_log( $data ) {
		$sensitive_keys = array(
			'token',
			'api_key',
			'apikey',
			'sec_key',
			'seckey',
			'secret',
			'secret_key',
			'pub_key',
			'pubkey',
			'hmac',
			'hmac_secret',
			'authorization',
			'password',
			'client_secret',
			'cs',
			'jwt',
			'refresh_token',
			'access_token',
			'pk_key_live',
			'pk_key_test',
			'sk_key_live',
			'sk_key_test',
			'woocode',
			'secretkey',
			'public_key',
			'private_key',
		);

		if ( is_array( $data ) ) {
			$clean = array();
			foreach ( $data as $key => $value ) {
				$key_l = is_string( $key ) ? strtolower( $key ) : $key;
				if ( is_string( $key_l ) && in_array( $key_l, $sensitive_keys, true ) ) {
					// Omit sensitive fields completely (do not log [REDACTED] placeholders).
					continue;
				}
				$clean[ $key ] = self::redact_for_log( $value );
			}
			return $clean;
		}

		if ( is_object( $data ) ) {
			$obj = clone $data;
			foreach ( get_object_vars( $obj ) as $key => $value ) {
				$key_l = strtolower( (string) $key );
				if ( in_array( $key_l, $sensitive_keys, true ) ) {
					unset( $obj->$key );
					continue;
				}
				$obj->$key = self::redact_for_log( $value );
			}
			return $obj;
		}

		if ( is_string( $data ) ) {
			// Remove sensitive JSON fields entirely (key + value).
			$data = preg_replace(
				'/,?\s*"(api_key|apikey|token|hmac_secret|hmac|sec_key|seckey|pub_key|pubkey|password|authorization|client_secret|access_token|refresh_token|pk_key_live|pk_key_test|sk_key_live|sk_key_test|cs|secret|secret_key|woocode|secretkey|public_key|private_key)"\s*:\s*"(?:\\\\.|[^"\\\\])*"/i',
				'',
				$data
			);
			// Non-string JSON values for the same keys (numbers/bools/null/objects — drop common scalar forms).
			$data = preg_replace(
				'/,?\s*"(api_key|apikey|token|hmac_secret|hmac|sec_key|seckey|secret|secret_key|cs|woocode)"\s*:\s*(?:true|false|null|[0-9]+)/i',
				'',
				$data
			);
			// Tidy broken commas after removals.
			$data = preg_replace( '/\{\s*,/', '{', $data );
			$data = preg_replace( '/,\s*\}/', '}', $data );
			$data = preg_replace( '/\[\s*,/', '[', $data );
			$data = preg_replace( '/,\s*\]/', ']', $data );
			$data = preg_replace( '/,\s*,+/', ',', $data );

			// Drop onboarding woocode query values.
			$data = preg_replace( '/([?&])woocode=[^&\s"\\\\]*/i', '$1', $data );

			// Drop JWTs / long tokens / key prefixes entirely (no placeholder).
			$data = preg_replace( '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/', '', $data );
			$data = preg_replace( '/\bZXlK[A-Za-z0-9+\/=_-]{20,}/', '', $data );
			$data = preg_replace( '/\bBearer\s+[A-Za-z0-9\-_\.=]+/i', '', $data );
			$data = preg_replace( '/\b(sk_|pk_|egyp_|egy_|omn_|ksa_|uae_)[A-Za-z0-9_\-]+/', '', $data );

			return trim( preg_replace( '/\s{2,}/', ' ', $data ) );
		}

		return $data;
	}

	public static function addLogs( $debug, $file, $note, $data = false ) {
		if ( '1' !== (string) $debug ) {
			return;
		}

		$note = self::redact_for_log( (string) $note );

		if ( is_bool( $data ) ) {
			$message = $note;
		} else {
			// If callers pass already-encoded JSON/print_r strings, still redact patterns.
			if ( is_string( $data ) ) {
				$payload = self::redact_for_log( $data );
			} else {
				$payload = wp_json_encode( self::redact_for_log( $data ) );
			}
			$message = $note . ' -- ' . $payload;
		}

		$source = is_string( $file ) ? sanitize_file_name( str_replace( '.log', '', basename( $file ) ) ) : 'paymob';
		$source = strtolower( (string) $source );
		if ( '' === $source ) {
			$source = 'paymob';
		}

		// Write only to hashed PHP-guarded files (HTTP 403). Filenames are not guessable (no paymob-pixel.log).
		$path = self::secure_log_file( $source );
		if ( ! file_exists( $path ) || filesize( $path ) < 32 ) {
			$header  = "<?php\nhttp_response_code( 403 );\nheader( 'Content-Type: text/plain; charset=utf-8' );\nheader( 'X-Content-Type-Options: nosniff' );\nexit( 'Forbidden' );\n";
			$header .= '// Paymob secure log — keep the PHP header above. Filename is hashed; channel is for admins only.' . "\n";
			$header .= '// channel: ' . $source . "\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, $header, LOCK_EX );
		}

		$safe_line = str_replace( array( "\r", "\n" ), ' ', (string) $message );
		$line      = '// ' . gmdate( 'c' ) . ' ' . $safe_line . "\n";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	public function getIntegrationID( $conf,$IntegrationID ) {
		$this->matchCountries( $conf );
		$this->addLogs( $this->debug_order, $this->file, ' Authenticate Paymob configuration' );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $conf['secKey'] ) );

		$tokenRes = $this->HttpRequest( $apiUrl . 'api/auth/tokens', 'POST', array( 'Content-Type: application/json' ), array( 'api_key' => $conf['apiKey'] ) );
		if ( isset( $tokenRes->token ) )
		{
			$IntegrationID = $this->HttpRequest( $apiUrl . 'api/ecommerce/integrations/'.$IntegrationID, 'GET', array( 'Content-Type: application/json', 'Authorization: Bearer ' . $tokenRes->token ) );
			$this->addLogs( $this->debug_order, $this->file, ' In api/ecommerce/integrations Response: ' . json_encode( $IntegrationID ) );
			if ( ! empty( $IntegrationID ) ) 
			{
				return $IntegrationID;
			}else
			{
				throw new Exception( 'Cannot get this integration ID from PayMob account' );
			}

		}
		else 
		{
			throw new Exception( 'Cannot get Token from PayMob account' );
		}
		
	}

	public function updateWebHookUrl( $conf,$IntegrationID,$data ) {
		$this->matchCountries( $conf );
		$this->addLogs( $this->debug_order, $this->file, ' Authenticate Paymob configuration' );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $conf['secKey'] ) );
		$tokenRes = $this->HttpRequest( $apiUrl . 'api/auth/tokens', 'POST', array( 'Content-Type: application/json' ), array( 'api_key' => $conf['apiKey'] ) );
		if ( isset( $tokenRes->token ) )
		{ 
			$result = $this->HttpRequest( $apiUrl . 'api/ecommerce/integrations/'.$IntegrationID, 'PUT', array( 'Content-Type: application/json', 'Authorization: Bearer ' . $tokenRes->token ),$data);
			$this->addLogs( $this->debug_order, $this->file, ' In api/ecommerce/integrations Response: ' . json_encode( $result ) );
			if ( ! empty( $result ) ) 
			{
				return $result;
			}else
			{
				throw new Exception( 'Cannot update webhook url' );
			}

		}
		else 
		{
			throw new Exception( 'Cannot get Token from PayMob account' );
		}
		
	}

	public function createSubscriptionPlan($token,$secKey, $data)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$this->addLogs( $this->debug_order, $this->file, print_r( $data, 1 ) );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscription-plans', 'POST', $header, $data );
		$note_i    = 'subscriptionPlans response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}

	public function suspendSubscription($token,$secKey,$planId)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscriptions/'.$planId.'/suspend', 'POST', $header );
		$note_i    = 'Suspend subscriptionPlans response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}

	public function activateSubscription($token,$secKey,$planId)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscriptions/'.$planId.'/resume', 'POST', $header);
		$note_i    = 'Activate subscriptionPlans response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}


	public function cancelSubscription($token,$secKey, $planId)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscriptions/'.$planId.'/cancel', 'POST', $header );
		$note_i    = 'Cancel subscriptionPlans response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}
	
	public function updateSubscription($token,$secKey, $data, $subscriptionId)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$this->addLogs( $this->debug_order, $this->file, print_r( $data, 1 ) );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscriptions/'.$subscriptionId, 'PUT', $header, $data );
		$note_i    = 'update subscription response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}
	
	public function TransactionSubscriptionID($token,$secKey, $transactionID)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscriptions?transaction='.$transactionID, 'GET', $header );
		$note_i    = ' Transaction subscriptionPlans ID response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}

	public function updateSubscriptionPlan($token,$secKey, $data,$planId)
	{
		$header = array( 'Content-Type: application/json', 'Authorization: Bearer ' . $token );
		$this->addLogs( $this->debug_order, $this->file, print_r( $data, 1 ) );
		$apiUrl = $this->getApiUrl( $this->getCountryCode( $secKey ) );
		$plans = $this->HttpRequest( $apiUrl.'api/acceptance/subscription-plans/'.$planId, 'PUT', $header, $data );
		$note_i    = 'Update subscriptionPlans response ';
		$this->addLogs( $this->debug_order, $this->file, $note_i, print_r( $plans, 1 ) );
		if ( empty( $plans ) ) {
			$this->addLogs( $this->debug_order, $this->file, $note_i, $plans );
			$plans = 'Something went wrong';
		}
		$this->addLogs( $this->debug_order, $this->file, $note_i, json_encode( $plans ) );
		return $plans;
	}
}


