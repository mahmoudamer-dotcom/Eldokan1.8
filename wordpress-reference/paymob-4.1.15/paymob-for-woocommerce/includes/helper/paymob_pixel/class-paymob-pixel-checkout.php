<?php
class Paymob_Pixel_Checkout {
	public static function enqueue_paymob_pixel_checkout() {
		Paymob_Scripts::paymob_pixel_checkout(self::pixel_customize());
	}
    public static function pixel_customize()
    {
        $paymob_pixel = get_option('woocommerce_paymob-pixel_settings');
        $customize['font_family'] = isset($paymob_pixel['font_family']) ? $paymob_pixel['font_family'] : '';
        $customize['font_size_label'] = isset($paymob_pixel['font_size_label']) ? $paymob_pixel['font_size_label'] : '';
        $customize['font_size_input_fields'] = isset($paymob_pixel['font_size_input_fields']) ? $paymob_pixel['font_size_input_fields'] : '';
        $customize['font_size_payment_button'] = isset($paymob_pixel['font_size_payment_button']) ? $paymob_pixel['font_size_payment_button'] : '';

        $customize['font_weight_label'] = isset($paymob_pixel['font_weight_label']) ? $paymob_pixel['font_weight_label'] : '';
        $customize['font_weight_input_fields'] = isset($paymob_pixel['font_weight_input_fields']) ? $paymob_pixel['font_weight_input_fields'] : '';
        $customize['font_weight_payment_button'] = isset($paymob_pixel['font_weight_payment_button']) ? $paymob_pixel['font_weight_payment_button'] : '';

        $customize['color_container'] = isset($paymob_pixel['color_container']) ? $paymob_pixel['color_container'] : '';
       
        $customize['color_border_input_fields'] = isset($paymob_pixel['color_border_input_fields']) ? $paymob_pixel['color_border_input_fields'] : '';
        $customize['color_border_payment_button'] = isset($paymob_pixel['color_border_payment_button']) ? $paymob_pixel['color_border_payment_button'] : '';
  
        
        $customize['radius_border'] = isset($paymob_pixel['radius_border']) ? $paymob_pixel['radius_border'] : '';
        $customize['color_disabled'] = isset($paymob_pixel['color_disabled']) ? $paymob_pixel['color_disabled'] : '';
        $customize['color_error'] = isset($paymob_pixel['color_error']) ? $paymob_pixel['color_error'] : '';
        $customize['color_primary'] = isset($paymob_pixel['color_primary']) ? $paymob_pixel['color_primary'] : '';
        $customize['color_input_fields'] = isset($paymob_pixel['color_input_fields']) ? $paymob_pixel['color_input_fields'] : '';
        
        $customize['text_color_for_label'] = isset($paymob_pixel['text_color_for_label']) ? $paymob_pixel['text_color_for_label'] : '';
        $customize['text_color_for_payment_button'] = isset($paymob_pixel['text_color_for_payment_button']) ? $paymob_pixel['text_color_for_payment_button'] : '';
        $customize['text_color_for_input_fields'] = isset($paymob_pixel['text_color_for_input_fields']) ? $paymob_pixel['text_color_for_input_fields'] : '';
        $customize['color_for_text_placeholder'] = isset($paymob_pixel['color_for_text_placeholder']) ? $paymob_pixel['color_for_text_placeholder'] : '';
        
        $customize['width_of_container'] = isset($paymob_pixel['width_of_container']) ? $paymob_pixel['width_of_container'] : '';
        $customize['vertical_padding'] = isset($paymob_pixel['vertical_padding']) ? $paymob_pixel['vertical_padding'] : '';
        $customize['vertical_spacing_between_components'] = isset($paymob_pixel['vertical_spacing_between_components']) ? $paymob_pixel['vertical_spacing_between_components'] : '';
        $customize['container_padding'] = isset($paymob_pixel['container_padding']) ? $paymob_pixel['container_padding'] : '';
        return $customize;
    }

    public static function create_paymob_intention_and_insert($cs,$pixel_identifier) {
        global $wpdb;
    
        // Table name
        $table_name = $wpdb->prefix . 'paymob_pixel_intentions';
        // Mocked data for example purposes (replace with actual logic as needed)
        $merchant_order_id = null; // Default NULL (replace with actual order ID if available)
        $response_cs = $cs;
    
        // Insert data into the table
        $wpdb->insert(
            $table_name,
            [
                'pixel_identifier' => $pixel_identifier,
                'merchant_order_id' => $merchant_order_id,
                'response_cs' => $response_cs,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s', // pixel_identifier
                '%d', // merchant_order_id
                '%s', // response_cs
                '%s', // created_at
            ]
        );
    }


    public static function update_paymob_intention_with_orderID( $order_id,$cs,$pixel_identifier)
    {
        
            global $wpdb;
            // Table name
            $table_name = $wpdb->prefix . 'paymob_pixel_intentions';
        
            // Update the database
            $updated = $wpdb->update(
                $table_name,
                ['merchant_order_id' => $order_id], // Data to update
                [
                    'response_cs' => $cs, // Assuming `cs` is stored in the `response_cs` column
                    'pixel_identifier' => $pixel_identifier, // Assuming `cs` is stored in the `response_cs` column
                ],
                ['%d'], // Format for `merchant_order_id`
                ['%s', '%s'] // Formats for `pixel_identifier` and `response_cs`
            );
        
            
       
        
    }

    
    
}
