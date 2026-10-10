<?php

class Paymob_Gateway_Settings {

	public static function paymob_add_gateway_settings( $settings, $current_section ) {
		if ( 'paymob_add_gateway' === $current_section ) {

			$custom_settings = include __DIR__ . '/../../admin/paymob-custom_setting.php';
			// Merge custom settings with existing settings.
			$settings = array_merge( $settings, $custom_settings );
		}

		return $settings;
	}

	public static function paymob_subscription_settings( $settings, $current_section ){
		if ( 'paymob_subscription' === $current_section ) {
			$custom_settings = include __DIR__ . '/../../admin/paymob_subscription.php';
			// Merge custom settings with existing settings.
			$settings = array_merge( $settings, $custom_settings );
		}
	
		return $settings;
	}
}
