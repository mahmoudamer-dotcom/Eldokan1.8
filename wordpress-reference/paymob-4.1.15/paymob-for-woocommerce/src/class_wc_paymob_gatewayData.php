<?php
/**
 * Paymob Gateway Data
 */
class WC_Paymob_GatewayData
{

	public static function getPaymobGatewayData()
	{
		$gatewayData = get_option('woocommerce_paymob_gateway_data');
		$lastFailure = get_option('woocommerce_paymob_gateway_data_failure');

		// Only proceed if there's no cached data and no recent failure
		if (empty($gatewayData) && empty($lastFailure)) {
			$mainOptions = get_option('woocommerce_paymob-main_settings');
			if (!empty($mainOptions)) {
				$debug = isset($mainOptions['debug']) ? $mainOptions['debug'] : '';
				$debug = 'yes' === $debug ? '1' : '0';
				try {
					$paymobReq = new Paymob($debug, Paymob::log_dir() . 'paymob-auth.log');
					$conf['secKey'] = isset($mainOptions['sec_key']) ? $mainOptions['sec_key'] : '';
					$result = $paymobReq->authToken( $conf );
					$token  = ( is_array( $result ) && isset( $result['token'] ) ) ? $result['token'] : '';
					$gatewayData = $paymobReq->getPaymobGateways($conf['secKey'], PAYMOB_PLUGIN_PATH . 'assets/img/', $token);
					update_option('woocommerce_paymob_gateway_data', $gatewayData);
					delete_option( 'woocommerce_paymob_gateway_data_failure' );
				} catch (\Exception $e) {
					WC_Admin_Settings::add_error( esc_html( $e->getMessage() ) );
					update_option('woocommerce_paymob_gateway_data_failure', current_time('timestamp')); // Record failure time
				}
			}
		}else {
			if (!empty($gatewayData)) {
				foreach ($gatewayData as $key => $gateway) {
					$logoPath = PAYMOB_PLUGIN_PATH . 'assets/img/' . strtolower($key) . '.png';
					// Skip downloading the logo if the logo URL is empty
					if ( ! empty( $gateway['logo'] ) ) {
						if ( ! file_exists( $logoPath ) ) {
							$response = wp_remote_get( $gateway['logo'] );
							if ( ! is_wp_error( $response ) ) {
								$logo_data = wp_remote_retrieve_body( $response );
								if ( '' !== $logo_data ) {
									// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Saving downloaded gateway logo to plugin assets.
									file_put_contents( $logoPath, $logo_data );
								}
							}
						}
					}
				}
			}
		}
	}
}