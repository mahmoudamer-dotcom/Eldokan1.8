<?php

class Paymob_Main_Connect_Account {

	public static function connect_paymob_account_handler() {
		 // Verify nonce for security
		 $security = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		 if ( ! $security || ! wp_verify_nonce( $security, 'connect_paymob' ) ) {
			wp_send_json_error(array('message' => 'Invalid nonce.'));
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
			return;
		}

		Paymob_Main_Partner_Info::mark_partner_connect_started();
		// Get country code
		$base_country_code = get_option('woocommerce_default_country');
		$base_country_code = explode(':', $base_country_code)[0];
		$country_code = 'unk';
		
		if (class_exists('WC_Countries')) {
			$countries = new WC_Countries();
			$country_name = $countries->countries[$base_country_code] ?? 'Unknown Country';
			$country_code = strtolower(substr($country_name, 0, 3));
		}
		
		// Data for Paymob request
		$data = [
			'partner' => 'woocommerce',
			 'clt'     => Paymob_Main_Partner_Info::get_public_ip(),
			// 'clt'     => '41.40.92.52',
		];
		// Paymob Request
		$paymobReq = new Paymob( Paymob::debug_flag(), Paymob::log_dir() . 'paymob-auth.log' );
		$response = $paymobReq->getOnboardingUrl('egy', $data);
		// Check for errors in Paymob response
		$currentURL = Paymob_Main_Partner_Info::get_partner_redirect_url();
		$encoded_url = urlencode( $currentURL );
		$url='https://onboarding.paymob.com/auth/country-selection?partner=woocommerce&redirect_url='.$encoded_url;
		if (isset($response->error) || isset($response->detail)) {
			wp_send_json_error(array('url' => $url));
		}
		
		// If successful, return the URL
		if (isset($response->url)) {
			wp_send_json_success(array('url' => $response->url));
		} else {
			// Handle unexpected responses
			wp_send_json_error(array('url' => $url));
		}
		exit;
	}
}
