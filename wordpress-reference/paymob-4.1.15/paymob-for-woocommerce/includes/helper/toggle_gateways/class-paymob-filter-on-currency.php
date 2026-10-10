<?php
class Paymob_Filter_currency
{

	public static function filter_payment_gateways_based_on_currency($available_gateways)
	{
		global $wpdb;

		// Retrieve all gateways from the paymob_gateways table.
		$gateways = PaymobAutoGenerate::get_db_gateways_data();
		$paymob_options = get_option('woocommerce_paymob-main_settings');
		$default_enabled = isset($paymob_options['enabled']) ? $paymob_options['enabled'] : 'no';
		$mode = isset($paymob_options['mode']) ? $paymob_options['mode'] : '';
		$mismatched_ids = array();
		$mismatched_currencies = array();
		$mismatched_integration_ids = array();
		$gateway_ids = array();
		foreach ($gateways as $gateway) {
			if ($gateway->mode === $mode) {
				$integration_ids = explode(',', $gateway->integration_id);
				$gateway_id = $gateway->gateway_id;
				// Check each integration ID individually.
				foreach ($integration_ids as $integration_id) {
					check_integration_id(
						trim($integration_id),
						$gateway_id,
						$mismatched_ids,
						$mismatched_currencies,
						$mismatched_integration_ids
					);
				}
			} else {
				$gateway_ids[] = $gateway->gateway_id;
			}
		}

		// Filter out only the non-Paymob gateways that are mismatched or have default settings as 'no'.
		foreach ($available_gateways as $gateway_id => $gateway) {
			if (
				!in_array($gateway_id, array('paymob', 'paymob-pixel'), true) &&
				(in_array($gateway_id, array_column($gateways, 'gateway_id'), true) &&
					(in_array($gateway_id, $mismatched_ids, true) || 'no' === $default_enabled))
			) {
				unset($available_gateways[$gateway_id]);
			}
			if (
				in_array($gateway_id, $gateway_ids, true)
			) {
				unset($available_gateways[$gateway_id]);
			}
		}

		// Check if the Paymob gateway should be shown.
		if (isset($available_gateways['paymob'])) {
			$paymob_gateway = $available_gateways['paymob'];
			$integration_id = $paymob_gateway->integration_id;
			// Check if the integration ID matches the store currency.
			if (!check_integration_id_match($integration_id, get_woocommerce_currency()) || 'no' === $default_enabled) {
				unset($available_gateways['paymob']);
			}
		}

		if (isset($available_gateways['paymob-pixel'])) {
			$paymob_gateway = $available_gateways['paymob-pixel'];
			if (isset($paymob_gateway->cards_integration_id)) {
				$cards_integration_id = $paymob_gateway->cards_integration_id;
			}
			if (isset($paymob_gateway->apple_pay_integration_id)) {
				$apple_pay_integration_id = $paymob_gateway->apple_pay_integration_id;
			}
			if (isset($paymob_gateway->google_pay_integration_id)) {
				$google_pay_integration_id = $paymob_gateway->google_pay_integration_id;
			}
			if (isset($paymob_gateway->bank_installment_integration_id)) {
				$bank_installment_integration_id = $paymob_gateway->bank_installment_integration_id;
			}
			$integration_ids = [];
			// Check and merge only non-empty arrays
			if (!empty($cards_integration_id)) {
				$integration_ids = array_merge($integration_ids, (array) $cards_integration_id);
			}
			if (!empty($apple_pay_integration_id)) {
				$integration_ids = array_merge($integration_ids, (array) $apple_pay_integration_id);
			}
			if (!empty($google_pay_integration_id)) {
				$integration_ids = array_merge($integration_ids, (array) $google_pay_integration_id);
			}
			if (!empty($bank_installment_integration_id)) {
				$integration_ids = array_merge($integration_ids, (array) $bank_installment_integration_id);
			}
			if (!check_integration_id_match($integration_ids, get_woocommerce_currency()) || 'no' === $default_enabled) {
				unset($available_gateways['paymob-pixel']);
			}
		}

		static $error_message_displayed = false;
		if (
			!$error_message_displayed &&
			('paymob-main' === Paymob::filterVar('section') ||
				'paymob_add_gateway' === Paymob::filterVar('section') ||
				'paymob_list_gateways' === Paymob::filterVar('section'))
		) {
			if (!empty($mismatched_integration_ids) && !empty($mismatched_currencies)) {
				$mismatched_ids_string = implode(', ', array_unique($mismatched_integration_ids));
				$mismatched_currencies_string = implode(', ', array_unique($mismatched_currencies)); // Use unique to avoid duplicate currency entries.

				$message = sprintf(
					/* translators: %1$s is a comma-separated list of integration IDs. %2$s is a comma-separated list of currencies. */
					__('Payment Method(s) with the Integration ID(s)', 'paymob-for-woocommerce') . ' (%1$s) ' . __('require(s) the store currency to be set to:', 'paymob-for-woocommerce') . ' %2$s.',
					$mismatched_ids_string,
					$mismatched_currencies_string
				);

				add_action(
					'admin_notices',
					function () use ($message) {
						echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
					}
				);

				$error_message_displayed = true;
			}
		}

		return $available_gateways;
	}
}