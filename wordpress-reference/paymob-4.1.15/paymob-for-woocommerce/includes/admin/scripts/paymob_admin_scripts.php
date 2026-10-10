<?php
class Paymob_Scripts {

	public static function paymob_list_gateways() {
		wp_enqueue_script( 'paymob-admin-manual_setup-scripts', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/manual_setup.js', array( 'jquery' ), PAYMOB_VERSION, true );
		wp_enqueue_script( 'paymob-list-scripts', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/paymob_list_gateways.js', array( 'jquery' ), PAYMOB_VERSION, true );

		wp_localize_script(
			'paymob-list-scripts',
			'paymob_list',
			array(
				'ajax_url'                    => admin_url( 'admin-ajax.php' ),
				'delete_nonce'                => wp_create_nonce( 'delete_gateway_nonce' ),
				'toggle_nonce'                => wp_create_nonce( 'toggle_gateway_nonce' ),
				'save_gateway_order_nonce'    => wp_create_nonce( 'save_gateway_order' ),
				'reset_paymob_gateways_nonce' => wp_create_nonce( 'reset_paymob_gateways' ),
				'rg' =>__('Remove Gateway', 'paymob-for-woocommerce'),
				'ays' => __('Are you sure you want to remove this gateway?', 'paymob-for-woocommerce'),
				'ay' => __('Are you sure you want to ', 'paymob-for-woocommerce'),
				'tg' => __(' this gateway?', 'paymob-for-woocommerce'),
				'gat' => __(' Gateway', 'paymob-for-woocommerce'),
				'rp' => __('Reset Payment Methods', 'paymob-for-woocommerce'),
				'arp' => __('Are you sure you want to reset the payment methods?', 'paymob-for-woocommerce'),
			)
		);
	}

	public static function paymob_admin( $params ) {
		$admin_js_path = PAYMOB_PLUGIN_PATH . 'assets/js/admin.js';
		wp_enqueue_script(
			'paymob-admin-js',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/admin.js',
			array( 'jquery' ),
			file_exists( $admin_js_path ) ? PAYMOB_VERSION . '-' . filemtime( $admin_js_path ) : PAYMOB_VERSION,
			true
		);
		wp_enqueue_script( 'color-picker', admin_url() . 'js/color-picker.min.js', array(), PAYMOB_VERSION, true );
		wp_localize_script( 'paymob-admin-js', 'ajax_object', $params );

		wp_enqueue_script( 'paymob-admin-scripts', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/manual_setup.js', array( 'jquery' ), PAYMOB_VERSION, true );
		wp_localize_script(
			'paymob-admin-scripts',
			'paymob_admin_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'manual_setup_nonce' ),
			)
		);
	}

	public static function confirmation_popup() {
		wp_enqueue_script(
			'confirmation-popup',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/confirmation-popup.js', // Adjust the path as necessary.
			array( 'jquery' ),
			PAYMOB_VERSION,
			true
		);
	}

	public static function confirmation_popup_localize( $exist ) {
		wp_localize_script(
			'confirmation-popup',
			'wc_admin_settings',
			array(
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'your_nonce_action' ),
				'exist'                => $exist,
				'paymob_list_gateways' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_list_gateways' ),
			)
		);
	}

	public static function paymob_accordion() {
		wp_enqueue_script( 'jquery-ui-accordion' );
		wp_enqueue_script(
			'paymob-accordion-script',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/accordion.js',
			array( 'jquery', 'jquery-ui-accordion' ),
			PAYMOB_VERSION,
			true
		);
	}

	public static function paymob_frontend() {
		wp_enqueue_script( 'paymob-frontend-js', plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/apple-users.js', array( 'jquery' ), PAYMOB_VERSION, true );
	}

	public static function method_script( $name ) {
		wp_register_script(
			$name . '-blocks-integration',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/blocks/' . $name . '_block.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			PAYMOB_VERSION,
			true
		);
	}

	public static function get_save_card_confirmation_model_script() {
		wp_enqueue_script(
			'paymob-save-card',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/save-card.js',
			array( 'jquery' ),
			PAYMOB_VERSION,
			true
		);
	}

	public static function paymob_pixel_checkout($customize) {
        $paymobOptions = get_option('woocommerce_paymob-main_settings');
        $paymob_pixel = get_option('woocommerce_paymob-pixel_settings');
		// Condition: pixel enabled + on checkout + subscription is not enabled
		$is_subscription_cart = self::paymob_cart_has_subscription();
        if(!empty($paymob_pixel['enabled']) && $paymob_pixel['enabled'] =='yes' && function_exists('is_checkout') && is_checkout()&& ($is_subscription_cart==false)){
			wp_enqueue_style(
				'paymob-pixel-checkout-front',
				plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/pixel-checkout-front.css',
				array(),
				PAYMOB_VERSION
			);
			wp_enqueue_script('paymob-pixel-checkout', plugins_url(PAYMOB_PLUGIN_NAME) . '/assets/js/blocks/paymob-pixel_block.js', array('jquery'), file_exists(PAYMOB_PLUGIN_PATH . 'assets/js/blocks/paymob-pixel_block.js') ? PAYMOB_VERSION . '-' . filemtime(PAYMOB_PLUGIN_PATH . 'assets/js/blocks/paymob-pixel_block.js') : PAYMOB_VERSION, true);
	        $pubKey = isset($paymobOptions['pub_key']) ? $paymobOptions['pub_key'] : '';
	        wp_localize_script('paymob-pixel-checkout', 'pxl_object', array(
	            'ajax_url' => admin_url('admin-ajax.php'),
	            'key' => $pubKey,
	            'appleenabled' => !empty($paymob_pixel['apple_pay_integration_id'])?1:0,
	            'googleenabled' => !empty($paymob_pixel['google_pay_integration_id'])?1:0,
	            'bankinstallmentenabled' => ( PaymobAutoGenerate::is_paymob_egypt_merchant() && !empty($paymob_pixel['bank_installment_integration_id']) && ( !is_array($paymob_pixel['bank_installment_integration_id']) || !empty($paymob_pixel['bank_installment_integration_id'][0]) ) )?1:0,
	            'cardsenabled' => !empty($paymob_pixel['cards_integration_id'])?1:0,
	            'customize' => $customize,
	            'forcesavecard' => (isset($paymob_pixel['force_save_card']) && $paymob_pixel['force_save_card']=='yes') ? true : false,
	            'showsavecard' => (isset($paymob_pixel['show_save_card']) && $paymob_pixel['show_save_card']=='yes') ? true : false,
				'update_checkout_nonce' => wp_create_nonce('update_checkout'),
				'callback' => add_query_arg( array( 'wc-api' => 'paymob_callback' ), home_url() )
	        ));
	    }
	}

	public static function paymob_cart_has_subscription() {
		if (function_exists('wcs_cart_contains_subscription') && wcs_cart_contains_subscription()) {
			return true;
		}

		foreach (WC()->cart->get_cart() as $item) {
			$product = $item['data'];
			if ($product->is_type('subscription') || $product->get_meta('_subscription_period')) {
				return true;
			}
		}

		return false;
	}

	public static function paymob_main_scripts() {
		wp_enqueue_script(
			'paymob-main-script',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/main.js', // Adjust path as needed
			array('jquery'), // Dependencies
			PAYMOB_VERSION, // Version
			true // Load in footer
		);
        $paymobOptions = get_option('woocommerce_paymob-main_settings');
        $popup = 'false';
		if(empty($paymobOptions) && (!empty(Paymob::filterVar('popup', 'GET')) 
			&& Paymob::filterVar('popup', 'GET') == 'true')){
			$popup = 'true';
		}

		$currentURL = Paymob_Main_Partner_Info::get_partner_redirect_url();
		wp_localize_script('paymob-main-script', 'main', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'connect_paymob_nonce' => wp_create_nonce('connect_paymob'),
			'current_url' => urlencode($currentURL),
			'popup' => $popup,
		));

	}

	public static function enqueue_paymob_pixel_script() {
		
		$current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
		if ($current_section === 'paymob_pixel') {
			$js_path = PAYMOB_PLUGIN_PATH . 'assets/js/paymob-pixel-admin.js';
			// Enqueue the script
			wp_enqueue_script(
				'paymob-pixel-custom-script', // Unique handle
				plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/js/paymob-pixel-admin.js', // Path to your JS file
				array( 'jquery', 'paymob-admin-js' ), // Run after admin.js so required stripping wins
				file_exists( $js_path ) ? PAYMOB_VERSION . '-' . filemtime( $js_path ) : PAYMOB_VERSION,
				true // Load in the footer
			);

		}
		
	}

	public static function enqueue_paymob_widget_script() {

		$current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
		if ($current_section !== 'widget') {
			return;
		}

		$css_path = PAYMOB_PLUGIN_PATH . 'assets/css/affordability-widget.css';
		wp_enqueue_style(
			'paymob-affordability-widget',
			plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/css/affordability-widget.css',
			array(),
			file_exists( $css_path ) ? PAYMOB_VERSION . '-' . filemtime( $css_path ) : PAYMOB_VERSION
		);
	}

	

}
