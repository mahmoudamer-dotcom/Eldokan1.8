<?php

class Paymob_Unset_Old_Setting {

	public static function unset_old_settings()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'paymob_gateways';
		$gateways = PaymobAutoGenerate::get_db_gateways_data();
		// Track if any gateway deletion fails
		$all_deleted = true;

		foreach ($gateways as $gateway) {
			$gateway_file_path = PAYMOB_PLUGIN_PATH . 'includes/gateways/' . $gateway->file_name;
			$gateway_block_file_path = PAYMOB_PLUGIN_PATH . 'includes/blocks/' . $gateway->gateway_id . '-block.php';
			$gateway_js_file_path = PAYMOB_PLUGIN_PATH . 'assets/js/blocks/' . $gateway->gateway_id . '_block.js';

			// Unlink the files if they exist and are not the main paymob gateway
			if (!in_array($gateway->gateway_id, ['paymob', 'paymob-pixel'])) {
				if ( file_exists( $gateway_file_path ) ) {
					if ( ! wp_delete_file( $gateway_file_path ) ) {
						$all_deleted = false;
					}
				}

				if ( file_exists( $gateway_block_file_path ) ) {
					if ( ! wp_delete_file( $gateway_block_file_path ) ) {
						$all_deleted = false;
					}
				}

				if ( file_exists( $gateway_js_file_path ) ) {
					if ( ! wp_delete_file( $gateway_js_file_path ) ) {
						$all_deleted = false;
					}
				}

				// Delete the gateway record from the database
				$wpdb->delete($table_name, array('gateway_id' => $gateway->gateway_id));
				// Delete the gateway settings from WooCommerce options
				if (!delete_option('woocommerce_' . $gateway->gateway_id . '_settings')) {
					delete_option('woocommerce_paymob-pixel_settings');
					$all_deleted = false;
				}
			}
		}
		// Return true if all deletions were successful, otherwise false
		return $all_deleted;
	}
	public static function paymob_setting($mode,$ids){
        global $wpdb;
        $existing_paymob_gateway = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", 'paymob' ), OBJECT );
			// $integration_ids         = implode( ', ', array_unique( $ids ) ); // Combine unique IDs.
			$integration_ids         = implode( ', ', array_unique($ids ) ); // Combine unique IDs.
			
			if ( empty( $existing_paymob_gateway ) ) {
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
    }

	
}
