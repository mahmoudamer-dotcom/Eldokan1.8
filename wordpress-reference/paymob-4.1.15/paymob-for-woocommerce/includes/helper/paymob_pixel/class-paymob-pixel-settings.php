<?php
class Paymob_Pixel_Settings {
	public static function paymob_pixel_settings_option($settings, $current_section) {
		if ($current_section == 'paymob_pixel') {
            $pixel_settings = get_option('woocommerce_paymob-pixel_settings', array());
            $appleArray=array();
            $omanNet = PaymobAutoGenerate::get_pixel_integration_ids('OMANNET');
            $card= PaymobAutoGenerate::get_pixel_integration_ids('Card');
            $all_cards=$card+$omanNet ;
            $ggle1= PaymobAutoGenerate::get_pixel_integration_ids('google-pay');
            $ggle2= PaymobAutoGenerate::get_pixel_integration_ids('Google-pay');
            $googlepayIDs = $ggle1+ $ggle2;

            $applepayIDs = PaymobAutoGenerate::get_pixel_integration_ids('apple_pay');
            $bankInstallmentIDs = array();
            $bankInstallmentArray = array();

            // PRD: Bank Installment in Pixel Embedding Settings is Egypt-only.
            // One integration ID only. A single available ID is pre-selected.
            if ( PaymobAutoGenerate::is_paymob_egypt_merchant() ) {
                $bankInstallmentIDs = PaymobAutoGenerate::get_pixel_bank_installment_integration_ids( false );
                $bank_installment_count = count( $bankInstallmentIDs );

                if ( $bank_installment_count > 0 ) {
                    $stored_bank = isset( $pixel_settings['bank_installment_integration_id'] ) ? $pixel_settings['bank_installment_integration_id'] : '';
                    if ( ! is_array( $stored_bank ) ) {
                        $stored_bank = ( '' === (string) $stored_bank ) ? array() : array( (string) $stored_bank );
                    }
                    $stored_bank = array_values(
                        array_filter(
                            array_map( 'strval', $stored_bank ),
                            function ( $id ) use ( $bankInstallmentIDs ) {
                                return isset( $bankInstallmentIDs[ $id ] );
                            }
                        )
                    );
                    $stored_bank_id = isset( $stored_bank[0] ) ? (string) $stored_bank[0] : '';

                    if ( 1 === $bank_installment_count ) {
                        $stored_bank_id = (string) array_key_first( $bankInstallmentIDs );
                    }

                    $stored_as_single = ( '' === $stored_bank_id ) ? array() : array( $stored_bank_id );
                    if ( (array) ( isset( $pixel_settings['bank_installment_integration_id'] ) ? $pixel_settings['bank_installment_integration_id'] : array() ) !== $stored_as_single ) {
                        $pixel_settings['bank_installment_integration_id'] = $stored_as_single;
                        update_option( 'woocommerce_paymob-pixel_settings', $pixel_settings );
                    }

                    $bank_options = $bankInstallmentIDs;
                    if ( $bank_installment_count > 1 ) {
                        $bank_options = array( '' => __( 'Select an Integration ID', 'paymob-for-woocommerce' ) ) + $bankInstallmentIDs;
                    }

                    $bankInstallmentArray = array(
                        'name'     => __( 'Bank Installment', 'paymob-for-woocommerce' ),
                        'type'     => 'select',
                        'id'       => 'bank_installment_integration_id',
                        'options'  => $bank_options,
                        'desc_tip' => true,
                        'default'  => $stored_bank_id,
                    );
                }
            } elseif ( ! empty( $pixel_settings['bank_installment_integration_id'] ) ) {
                // Clear stale value for non-Egypt merchants.
                $pixel_settings['bank_installment_integration_id'] = array();
                update_option( 'woocommerce_paymob-pixel_settings', $pixel_settings );
            }

            if(!empty($applepayIDs) && count($applepayIDs)>1){
                $appleArray=array(
                        'name' => __('Apple Pay', 'paymob-for-woocommerce'),
                        'type' => 'select',
                        'id' => 'apple_pay_integration_id',
                        'options' => $applepayIDs,
                        'desc_tip' => true,
                        'default' => !empty($pixel_settings['apple_pay_integration_id'])?$pixel_settings['apple_pay_integration_id']:null
                    );
            }

            $googleArray=array();
            if(!empty($googlepayIDs)&& count($googlepayIDs)>1){
                $googleArray=array(
                        'name' => __('Google Pay', 'paymob-for-woocommerce'),
                        'type' => 'select',
                        'id' => 'google_pay_integration_id',
                        'options' => $googlepayIDs,
                        'desc_tip' => true,
                        'default' => !empty($pixel_settings['google_pay_integration_id'])?$pixel_settings['google_pay_integration_id']:null
                    );
            }
        $tabs = include __DIR__ . '/../../admin/paymob-admin-tabs.php';
            $custom_settings = array(
                array(
                    'name' => '',
                    'type' => 'title',
                    'desc' => $tabs,
                ),
                array(
                    'type'  => 'title',
                    'class' => 'payment-feature-description',
                    'name'  => __( 'About Feature', 'paymob-for-woocommerce' ),
                    'desc'  => '<div style="background-color: #f0f8ff; border: 1px solid #ddd; padding: 15px; margin-top: 20px; border-radius: 8px; font-family: Arial, sans-serif; color: #333; width:70%; margin-left:1%">
                                    '.__( 'Feature enables consumers to complete their payments directly on your WooCommerce store. It is enabled by default on your store. To disable it, navigate to the Payment Integrations section and disable "paymob-pixel". If you wish to hide a specific payment method, simply avoid selecting its integration ID.<br/><br/>
                                    
                                    For card payments, select the required integration ID. By default, all integration IDs will be pre-selected. <br/><br/>

                                    For Bank Installments, if only one integration ID is available, it will be pre-selected by default. If multiple integration IDs are available, please select the one you\'d like to use. <br/><br/>
                                    
                                    <span style="font-weight: bold; color: #007bff;">ℹ️ For Apple Pay and Google Pay:</span> Certain actions must be completed on Paymob\'s side. Please reach out to your account manager or <span style="white-space: nowrap;">contact us at <a href="mailto:support@paymob.com" style="color: #007bff; font-weight: bold;">support@paymob.com</a></span>. Make sure to receive confirmation from Paymob before enabling Apple Pay or Google Pay.', 'paymob-for-woocommerce' ).'
                                </div>',
                ),
                array(
                    'type' => 'title',
                    'name' => __('Section : Payment Methods', 'paymob-for-woocommerce'),
                ),
                array(
                    'name' => __('Payment Method -  Title', 'paymob-for-woocommerce'),
                    'type' => 'text',
                    'id' => 'title',
                    'desc_tip' => true,
                    'default' => isset($pixel_settings['title']) ? $pixel_settings['title'] : '',
                ),
                array(
                    'name' => __('Cards', 'paymob-for-woocommerce'),
                    'type' => 'multiselect',
                    'id' => 'cards_integration_id',
                    'options' => $all_cards,
                    'desc_tip' => true,
                    'custom_attributes' => array(
                            'multiple' => 'multiple',
                        ),
                    'default' => isset($pixel_settings['cards_integration_id']) ? $pixel_settings['cards_integration_id'] : ''
                ),
                $bankInstallmentArray,
                $appleArray,
                $googleArray,
                array(
                    'type' => 'sectionend',
                    'id' => 'payment_methods_end',
                ),
                array(
                    'type' => 'title',
                    'name' => __('Section : Settings', 'paymob-for-woocommerce'),
                ),
                array(
                    'name' => __('Show Save Card', 'paymob-for-woocommerce'),
                    'type' => 'checkbox',
                    'id' => 'show_save_card',
                    'desc_tip' => true,
                    'default' => isset($pixel_settings['show_save_card']) ? $pixel_settings['show_save_card'] : 'yes'
                ),
                array(
                    'name' => __('Force Save Card', 'paymob-for-woocommerce'),
                    'type' => 'checkbox',
                    'id' => 'force_save_card',
                    'desc_tip' => true,
                    'default' => isset($pixel_settings['force_save_card']) ? $pixel_settings['force_save_card'] : 'no'
                ),
                // array(
                //     'name' => __('Customize Pixel', 'paymob-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'id' => 'customization_div',
                //     'desc_tip' => true,
                //     'default' => isset($pixel_settings['customization_div']) ? $pixel_settings['customization_div'] : ''
                // ),
                array(
                    'type' => 'custom_html',
                    'id' => 'paymob_pixel_customization',
                    'css' => ' ',
                ),
                array(
                    'type' => 'sectionend',
                    'id' => 'settings_end',
                ),
            );
    
            // Merge custom settings with existing settings.
            $settings = array_merge($settings, $custom_settings);
        }
    
        return $settings;
	}
}
