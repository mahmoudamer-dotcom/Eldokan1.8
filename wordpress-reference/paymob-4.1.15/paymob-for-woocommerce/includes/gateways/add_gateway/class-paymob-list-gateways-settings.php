<?php

class Paymob_List_Gateways_Settings
{

	public static function paymob_list_gateways_section_settings($settings, $current_section)
	{
		global $wpdb;

		$paymobOptions = get_option('woocommerce_paymob-main_settings');
		self::paymob_gateways_setting();
		//  echo "<pre>";print_r(self::paymob_setting());exit;
		$mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : null;

		    if (!empty($mode) && 'paymob_list_gateways' === $current_section) {
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}paymob_gateways 
						WHERE mode = %s 
						AND gateway_id != %s
						ORDER BY CASE WHEN gateway_id = 'paymob-pixel' THEN 0 ELSE 1 END, ordering",
						$mode,
						'paymob-subscription'
					),
					OBJECT
			);
		
			$custom_settings = include __DIR__ . '/../../admin/paymob-custom_list_setting.php';
			$table_body = '';
			// print_r($results); die;
			if (!empty($results)) {
				foreach ($results as $gateway) {

					$table_body .= Paymob_List_Gateways::paymob_list_gateways_table($gateway);
				}
			} else {
				$table_body .= Paymob_List_Gateways::paymob_not_found_record_table();
			}

			echo '<script>window.paymob_gateways_table_body = ' . wp_json_encode($table_body) . ';</script>';
			include_once __DIR__ . '/../../admin/views/htmlsviews/html_loader_paymob.php';
			$settings = array_merge($settings, $custom_settings);
		}

		return $settings;
	}

	public static function paymob_gateways_setting()
	{
		global $wpdb;
		$paymobOptions = get_option('woocommerce_paymob-main_settings');
		$mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : null;
		// $pixel_enabled = isset($paymobOptions['pixel_payment']) ? $paymobOptions['pixel_payment'] : 'no';
		$pixel_enabled ='yes';
		$integration_id_hidden = isset($paymobOptions['integration_id_hidden']) ? $paymobOptions['integration_id_hidden'] : '';

		$lines = explode("\n", trim($integration_id_hidden));
		$filtered_data = [];
		$ids = [];
		$pxl_integrations = [];
		$card_integrations = [];
		// Process each line and filter based on mode
		foreach ($lines as $line) {
			if (preg_match('/^(\d+) : (.*?)(\(.*: (\w+) \))/', $line, $matches)) {
				$integration_id = $matches[1]; // Integration ID
				$details = trim($matches[2]);  // Details
				$current_mode = strtolower($matches[4]); // Mode (test or live)
				$parts = explode(':', $matches[3]);
				$label = trim(str_replace("(", "", $parts[0]));
				$name = trim($matches[2]);
				// Filter by the selected mode
				if ($current_mode === $mode) {
					$filtered_data[$integration_id] = $line; // Use the full original line
					$ids[] = trim($integration_id);
					if (strpos($label, 'Card') !== false && strpos($name, 'bank-installments') === false && strpos($name, 'MIGS-apple_pay') === false && strpos($name, 'google-pay') === false && strpos($name, 'Google-pay') === false) {
						$pxl_integrations[] = $integration_id;
						$card_integrations[] = $integration_id;
					}
					if (strpos($name, 'MIGS-apple_pay') !== false || strpos($name, 'google-pay') !== false || strpos($name, 'Google-pay') !== false) {

						$pxl_integrations[] = $integration_id;
					}
				}
			}
		}
		$pxl_ids = implode(', ', array_unique($pxl_integrations));
		// return $card_integrations;
		// Prepare integration IDs for storage
		$integration_ids = implode(', ', array_unique($ids));

		// Get current stored data
		$current_paymob_settings = get_option('woocommerce_paymob_settings', array());
		$current_integration_ids = isset($current_paymob_settings['integration_id']) ? $current_paymob_settings['integration_id'] : [];
		$current_hidden_data = isset($current_paymob_settings['integration_id_hidden']) ? $current_paymob_settings['integration_id_hidden'] : '';

		// Check for changes
		$needs_update = $current_integration_ids !== $ids || $current_hidden_data !== implode("\n", $filtered_data);

		if ($needs_update) {
			// Update the database
			if (!empty($integration_ids)) {
				$existing_paymob_gateway = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", 'paymob' ), OBJECT );
				if ( empty( $existing_paymob_gateway )) {
					// Insert Paymob gateway only if it does not exist.
					$wpdb->insert(
						$wpdb->prefix . 'paymob_gateways',
						array(
							'gateway_id'           => 'paymob',
							'file_name'            => 'class-gateway-paymob.php',
							'class_name'           => 'Paymob',
							'checkout_title'       => 'Pay with Paymob',
							'checkout_description' => 'Pay with Paymob',
							'integration_id'       => $integration_ids,
							'is_manual'            => '0',
							'ordering'             => 30,
							'mode'                 =>$mode
						)
					);
				} elseif ( $existing_paymob_gateway[0]->integration_id !== $integration_ids ) {
						$wpdb->update(
							$wpdb->prefix . 'paymob_gateways',
							array(
								'integration_id' => $integration_ids,
								'ordering'       => 30,
								'mode'                 =>$mode
							),
							array( 'gateway_id' => 'paymob' )
						);
				}

				// Update WooCommerce settings
				$paymob_settings = $current_paymob_settings; // Reuse existing settings
				$paymob_settings['integration_id_hidden'] = implode("\n", $filtered_data);
				$paymob_settings['integration_id'] = $ids;
				// update_option('woocommerce_paymob_settings', $paymob_settings);
			}
			
			if ($pixel_enabled && !empty($pxl_ids)) {

				$wpdb->update(
					$wpdb->prefix . 'paymob_gateways',
					array(
						'integration_id' => $pxl_ids,
						'mode' => $mode,
					),
					array('gateway_id' => 'paymob-pixel')
				);

				$paymobPixel = get_option('woocommerce_paymob-pixel_settings', array());

				$paymobPixel['cards_integration_id'] = $card_integrations;
				$paymobPixel['enabled'] = $pixel_enabled;

				// update_option('woocommerce_paymob-pixel_settings', $paymobPixel);

			}
		}
	}
}
