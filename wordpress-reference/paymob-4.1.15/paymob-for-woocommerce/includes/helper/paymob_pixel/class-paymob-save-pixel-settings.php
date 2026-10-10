<?php
class Paymob_Save_Pixel_Settings {
	public static function save_paymob_pixel_settings() {
        global $current_section, $wpdb;

        if ('paymob_pixel' !== $current_section) {
            return;
        }
        delete_option('cards_integration_id');
        delete_option('apple_pay_integration_id');
        delete_option('google_pay_integration_id');
        delete_option('bank_installment_integration_id');
        delete_option('show_save_card');
        delete_option('title');
        $pixel_settings = get_option('woocommerce_paymob-pixel_settings', array());
       
        $cards_integration_id = Paymob::filterVar('cards_integration_id', 'POST');
        $apple_pay_integration_id = Paymob::filterVar('apple_pay_integration_id', 'POST');
        $google_pay_integration_id = Paymob::filterVar('google_pay_integration_id', 'POST');
        $bank_installment_integration_id = Paymob::filterVar('bank_installment_integration_id', 'POST');

        // Bank Installment is a single integration ID. Stored as a one-element array.
        if ( PaymobAutoGenerate::is_paymob_egypt_merchant() ) {
            $bank_ids   = PaymobAutoGenerate::get_pixel_bank_installment_integration_ids( false );
            $bank_count = count( $bank_ids );
            if ( 1 === $bank_count ) {
                $bank_installment_integration_id = array( (string) array_key_first( $bank_ids ) );
            } elseif ( $bank_count > 1 ) {
                if ( is_array( $bank_installment_integration_id ) ) {
                    $bank_installment_integration_id = (string) reset( $bank_installment_integration_id );
                }
                $bank_installment_integration_id = (string) $bank_installment_integration_id;
                if ( isset( $bank_ids[ $bank_installment_integration_id ] ) ) {
                    $bank_installment_integration_id = array( $bank_installment_integration_id );
                } else {
                    $bank_installment_integration_id = array();
                }
            } else {
                $bank_installment_integration_id = array();
            }
        } else {
            $bank_installment_integration_id = array();
        }

        if (empty($cards_integration_id[0]) && empty($apple_pay_integration_id) && empty($google_pay_integration_id) && empty($bank_installment_integration_id[0])) {
            WC_Admin_Settings::add_error(__('Please enable at least one Payment Method with an integration ID.', 'paymob-for-woocommerce'));
            // return;
        }
        $title = Paymob::filterVar('title', 'POST') ? sanitize_text_field(Paymob::filterVar('title', 'POST')) : 'Debit/Credit Card Payment';
        $show_save_card = Paymob::filterVar('show_save_card', 'POST') ? 'yes' : 'no';
        $force_save_card = Paymob::filterVar('force_save_card', 'POST') ? 'yes' : 'no';

        $customization_div = Paymob::filterVar('customization_div', 'POST') ? 'yes' : 'no';
        $font_family = Paymob::filterVar('woocommerce_paymob_pixel_font_family', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_family', 'POST')) : '';
        
        $font_size_label = Paymob::filterVar('woocommerce_paymob_pixel_font_size_label', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_size_label', 'POST')) : '';
        $font_size_input_fields = Paymob::filterVar('woocommerce_paymob_pixel_font_size_input_fields', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_size_input_fields', 'POST')) : '';
        $font_size_payment_button = Paymob::filterVar('woocommerce_paymob_pixel_font_size_payment_button', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_size_payment_button', 'POST')) : '';

        $font_weight_label = Paymob::filterVar('woocommerce_paymob_pixel_font_weight_label', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_weight_label', 'POST')) : '';
        $font_weight_input_fields = Paymob::filterVar('woocommerce_paymob_pixel_font_weight_input_fields', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_weight_input_fields', 'POST')) : '';
        $font_weight_payment_button = Paymob::filterVar('woocommerce_paymob_pixel_font_weight_payment_button', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_font_weight_payment_button', 'POST')) : '';

        $color_container = Paymob::filterVar('woocommerce_paymob_pixel_color_container', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_container', 'POST')) : '';
        
        $color_border_input_fields = Paymob::filterVar('woocommerce_paymob_pixel_color_border_input_fields', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_border_input_fields', 'POST')) : '';
        $color_border_payment_button = Paymob::filterVar('woocommerce_paymob_pixel_color_border_payment_button', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_border_payment_button', 'POST')) : '';
        
        
        $radius_border = Paymob::filterVar('woocommerce_paymob_pixel_radius_border', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_radius_border', 'POST')) : '';
        
        $color_disabled = Paymob::filterVar('woocommerce_paymob_pixel_color_disabled', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_disabled', 'POST')) : '';
        $color_error = Paymob::filterVar('woocommerce_paymob_pixel_color_error', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_error', 'POST')) : '';
        $color_primary = Paymob::filterVar('woocommerce_paymob_pixel_color_primary', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_primary', 'POST')) : '';
        $color_input_fields = Paymob::filterVar('woocommerce_paymob_pixel_color_input_fields', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_input_fields', 'POST')) : '';

        $text_color_for_label = Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_label', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_label', 'POST')) : '';
        $text_color_for_payment_button = Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_payment_button', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_payment_button', 'POST')) : '';
        $text_color_for_input_fields = Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_input_fields', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_text_color_for_input_fields', 'POST')) : '';
        $color_for_text_placeholder = Paymob::filterVar('woocommerce_paymob_pixel_color_for_text_placeholder', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_color_for_text_placeholder', 'POST')) : '';


        $width_of_container = Paymob::filterVar('woocommerce_paymob_pixel_width_of_container', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_width_of_container', 'POST')) : '';
        $vertical_padding = Paymob::filterVar('woocommerce_paymob_pixel_vertical_padding', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_vertical_padding', 'POST')) : '';
        $vertical_spacing_between_components = Paymob::filterVar('woocommerce_paymob_pixel_vertical_spacing_between_components', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_vertical_spacing_between_components', 'POST')) : '';

        $container_padding = Paymob::filterVar('woocommerce_paymob_pixel_container_padding', 'POST') ? sanitize_text_field(Paymob::filterVar('woocommerce_paymob_pixel_container_padding', 'POST')) : '';
    
        

        $pixel_settings = get_option('woocommerce_paymob-pixel_settings', array());
        $pixel_settings['title'] = $title;
        $pixel_settings['cards_integration_id'] = $cards_integration_id;
        $pixel_settings['apple_pay_integration_id'] = $apple_pay_integration_id;
        $pixel_settings['google_pay_integration_id'] = $google_pay_integration_id;
        $pixel_settings['bank_installment_integration_id'] = $bank_installment_integration_id;
        $pixel_settings['show_save_card'] = $show_save_card;
        $pixel_settings['force_save_card'] = $force_save_card;
        $pixel_settings['customization_div'] = $customization_div;
        $pixel_settings['font_family'] = $font_family;
        
        $pixel_settings['font_size_label'] = $font_size_label;
        $pixel_settings['font_size_input_fields'] = $font_size_input_fields;
        $pixel_settings['font_size_payment_button'] = $font_size_payment_button;

        $pixel_settings['font_weight_label'] = $font_weight_label;
        $pixel_settings['font_weight_input_fields'] = $font_weight_input_fields;
        $pixel_settings['font_weight_payment_button'] = $font_weight_payment_button;

        $pixel_settings['color_container'] = $color_container;


        $pixel_settings['color_border_input_fields'] = $color_border_input_fields;
        $pixel_settings['color_border_payment_button'] = $color_border_payment_button;

        $pixel_settings['radius_border'] = $radius_border;
        $pixel_settings['color_disabled'] = $color_disabled;
        $pixel_settings['color_error'] = $color_error;
        $pixel_settings['color_primary'] = $color_primary;
        $pixel_settings['color_input_fields'] = $color_input_fields;


        $pixel_settings['text_color_for_label'] = $text_color_for_label;
        $pixel_settings['text_color_for_payment_button'] = $text_color_for_payment_button;
        $pixel_settings['text_color_for_input_fields'] = $text_color_for_input_fields;
        $pixel_settings['color_for_text_placeholder'] = $color_for_text_placeholder;

        $pixel_settings['width_of_container'] = $width_of_container;
        $pixel_settings['vertical_padding'] = $vertical_padding;
        $pixel_settings['vertical_spacing_between_components'] = $vertical_spacing_between_components;
        $pixel_settings['container_padding'] = $container_padding;
        //echo "<pre>";print_r($pixel_settings);exit;
        update_option('woocommerce_paymob-pixel_settings', $pixel_settings);
       
	}
}
