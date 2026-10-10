<?php
/**
 * WC Paymob ValuWidget
 */
class WC_Paymob_ValuWidget {

	public static function AddValuWidget()
    {
		if (is_cart() || is_checkout()) {
    		global $woocommerce;
		}
        global $product;
		global $wpdb;
		
		$option_valu_widget = get_option('woocommerce_valu_widget_settings');
		$valuOption = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%-valu%'
			)
		);
		$valuEnabled = false;
		if(!empty($valuOption[0])){
			$valu = maybe_unserialize($valuOption[0]->option_value);
			$valuEnabled = $valu['enabled'];
		}
		if($valuEnabled == true && !empty($option_valu_widget['enabled_widget']) && $option_valu_widget['enabled_widget']=='yes'){
			
			wp_enqueue_style( 'valuWidget', plugins_url( PAYMOB_PLUGIN_NAME ) .'/assets/css/valuWidget.css', array(), PAYMOB_VERSION );
			if(!isset($option_valu_widget['dark_mode']) ||$option_valu_widget['dark_mode']=="yes" ){
				$close_icon='<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none"><path d="M23.1484 21.5781C23.5703 22.0469 23.5703 22.75 23.1484 23.1719C22.6797 23.6406 21.9766 23.6406 21.5547 23.1719L16.0234 17.5938L10.4453 23.1719C9.97656 23.6406 9.27344 23.6406 8.85156 23.1719C8.38281 22.75 8.38281 22.0469 8.85156 21.5781L14.4297 16L8.85156 10.4219C8.38281 9.95312 8.38281 9.25 8.85156 8.82812C9.27344 8.35938 9.97656 8.35938 10.3984 8.82812L16.0234 14.4531L21.6016 8.875C22.0234 8.40625 22.7266 8.40625 23.1484 8.875C23.6172 9.29688 23.6172 10 23.1484 10.4688L17.5703 16L23.1484 21.5781Z" fill="white"/></svg>';
				wp_enqueue_style( 'valuWidgetoption', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/dark_widget_options.css', array(), PAYMOB_VERSION );
	
			}
			else
			{
				$close_icon='<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
								<path d="M23.1484 21.5781C23.5703 22.0469 23.5703 22.75 23.1484 23.1719C22.6797 23.6406 21.9766 23.6406 21.5547 23.1719L16.0234 17.5938L10.4453 23.1719C9.97656 23.6406 9.27344 23.6406 8.85156 23.1719C8.38281 22.75 8.38281 22.0469 8.85156 21.5781L14.4297 16L8.85156 10.4219C8.38281 9.95312 8.38281 9.25 8.85156 8.82812C9.27344 8.35938 9.97656 8.35938 10.3984 8.82812L16.0234 14.4531L21.6016 8.875C22.0234 8.40625 22.7266 8.40625 23.1484 8.875C23.6172 9.29688 23.6172 10 23.1484 10.4688L17.5703 16L23.1484 21.5781Z" fill="#667085"/>
							</svg>';
				wp_enqueue_style( 'valuWidgetoption', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/light_widget_options.css', array(), PAYMOB_VERSION );
	
			}
			wp_enqueue_style('google-font-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat&display=swap', array(), null);
			wp_enqueue_style('google-font-inter', 'https://fonts.googleapis.com/css2?family=Inter&display=swap', array(), null);
			wp_enqueue_script(
				'valuWidget',
				plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/valuWidget.js', // Adjust the path as necessary.
				array( 'jquery' ),
				PAYMOB_VERSION,
				true
			);
			if ( is_a( $product, 'WC_Product' ) ) {
				//////////////get price of product////
				$product = wc_get_product( get_the_ID() );

				if ( ! $product ) {
					return;
				}

				if ( $product instanceof WC_Product_Variable ) {
					// Product has attributes (variations)
					$available_variations = $product->get_available_variations();
					$variation_prices = array_map( function( $variation ) {
						return $variation['display_price']; // Get variation price
					}, $available_variations );
	
					// Example: Get the lowest price among variations
					$price = (float) min( $variation_prices );
	
				} else {
					// Simple product
					$price = (float) $product->get_price();
				}
				wp_localize_script(
					'valuWidget',
					'wc_admin_settings',
					array(
						'ajax_url'             => admin_url( 'admin-ajax.php' ),
						'nonce'                => wp_create_nonce( 'your_nonce_action' ),
						'price'                => $price,
						'image_url' => plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/img/valuWidget.png', // Pass image URL
						'checkout_url' => wc_get_checkout_url(), // Gets the dynamic checkout URL
						'shop_url'     => wc_get_page_permalink('shop'), // Gets the shop URL
						'close_icon'   => $close_icon,
						'integration_id'=>$option_valu_widget['integration_id'],
						'product_id'    => $product->get_id(), // Ensure product ID is available

					)
				);
				echo "<button  class='single_add_to_cart_button button alt valuWidget /' id='valuWidget'>". esc_html__( 'Buy with Paymob', 'paymob-for-woocommerce' )."</button>";
						echo "<div class='render_widget' id='render_widget'></div>";

						echo"<div id='myModal' class='modal' style='display:none;'>
						 <div id='modal-content' class='modal-content'></div></div>";
			}        
			if (is_cart() || is_checkout()) {
				// Retrieve the cart total price
                $price = WC()->cart->get_total('');
				wp_localize_script(
                        'valuWidget',
                        'wc_admin_settings',
                        array(
                            'ajax_url' => admin_url( 'admin-ajax.php' ),
                            'nonce'    => wp_create_nonce( 'your_nonce_action' ),
                            'price'    =>(float)$price,
                            'image_url' => plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/img/valuWidget.png' ,// Pass image URL
                            'checkout_url' => wc_get_checkout_url(), // Gets the dynamic checkout URL
                            'shop_url'     => wc_get_page_permalink('shop') ,// Gets the shop URL
                            'close_icon'   => $close_icon,
                            'integration_id'=>$option_valu_widget['integration_id'],
                            
                        )
                    );
				echo "<button class='valuWidget' id='valuWidget'>" . esc_html__( 'Buy with Paymob', 'paymob-for-woocommerce' ) . "</button>";
	            echo "<div id='render_widget'></div>";

	            echo "<div id='myModal' class='modal' style='display:none;'>
	                    <div class='modal-content'>
	                        <div id='modal-content'></div>
	                    </div>
	                  </div>";
			}
		
		}
		
	}
}
