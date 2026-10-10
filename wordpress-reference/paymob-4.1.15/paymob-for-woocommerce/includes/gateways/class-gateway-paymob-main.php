<?php

if (!defined('ABSPATH')) {
	exit;
}

#[\AllowDynamicProperties]
class Paymob_Main_Gateway extends Paymob_Payment
{


	public $id;
	public $method_title;
	public $method_description;
	public $has_fields;
	public $callback_note;
	public $has_items_note;
	public $extra_note;
	public $save_changes_note;
	public function __construct()
	{

		// config
		$this->id = 'paymob-main';
		$this->method_title = $this->title = __('Paymob', 'paymob-for-woocommerce');
		$this->method_description = __('Accept payment through Paymob payment provider.', 'paymob-for-woocommerce');
		$this->description = __('Main Configuration', 'paymob-for-woocommerce');
		parent::__construct();
		// config
		$this->init_settings();
		// fields
		foreach ($this->settings as $key => $val) {
			$this->$key = $val;
		}
		// add_action( 'wp_ajax_reset_paymob_gateways', array( $this, 'reset_paymob_gateways' ), 1 );
	}
	public function init_form_fields()
	{
		$paymobOptions = get_option('woocommerce_paymob-main_settings');
		if(!empty($paymobOptions))
		{
           $this->form_fields = include __DIR__ . '/../admin/paymob-main.php';	
		}
		else
		{
			$this->form_fields = include __DIR__ . '/../admin/paymob-connect.php';
		}

		
		
	}
	/**
	 * Return whether or not Paymob payment method requires setup.
	 *
	 * @return bool
	 */
	public function needs_setup()
	{
		if (empty($this->pub_key) || empty($this->sec_key) || empty($this->api_key)) {
			return true;
		}
		return false;
	}
	 

	 /**
	 * Don't enable this payment, if there is no configuration keys
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 *
	 * @return string
	 */
    public function validate_enabled_field($key, $value) {
        if (is_null($value)) {
            return 'no';
        }
   		return 'yes';

    }

	public function process_admin_options()
	{
		global $wpdb;
		// Fetch the posted values
		$post_data = $this->get_post_data();
// echo "<pre>";print_r($_POST);exit;
		// Get current settings
		$paymobOptions = get_option('woocommerce_paymob-main_settings');
		$default_enabled = isset($paymobOptions['enabled']) ? $paymobOptions['enabled'] : '';
		$conf['pubKey'] = $pubKey = isset($paymobOptions['pub_key']) ? $paymobOptions['pub_key'] : '';
		$conf['apiKey'] = $apiKey = isset($paymobOptions['api_key']) ? $paymobOptions['api_key'] : '';
		$conf['secKey'] = $secKey = isset($paymobOptions['sec_key']) ? $paymobOptions['sec_key'] : '';
		$empty_cart = isset($post_data['woocommerce_paymob-main_empty_cart']) ? sanitize_text_field($post_data['woocommerce_paymob-main_empty_cart']) : '';
		$debug = isset($post_data['woocommerce_paymob-main_debug']) ? sanitize_text_field($post_data['woocommerce_paymob-main_debug']) : '';
		$paymob_mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : '';
		
		try {
			
			$this->pixel_settings($paymob_mode);
			$this->migrate_old_settings();
			// Save the rest of the settings using the parent method
			parent::process_admin_options();
			
		} catch (\Exception $e) {
			WC_Admin_Settings::add_error( esc_html( $e->getMessage() ) );
		}
		return true;
	}

	public function pixel_settings($paymob_mode)
	{
		global $wpdb;
		$pixel_settings = get_option('woocommerce_paymob-pixel_settings', array());
		$pixel_enabled = $pixel_settings['enabled'] = isset($pixel_settings['enabled'])? $pixel_settings['enabled'] : 'yes';
		$pixel_settings['show_save_card'] = isset($pixel_settings['show_save_card'])? $pixel_settings['show_save_card'] : 'yes';
		$pixel_settings['force_save_card'] = isset($pixel_settings['force_save_card'])? $pixel_settings['force_save_card'] : 'no';

		$pixel_settings['title'] = empty($pixel_settings['title']) ? 'Debit/Credit Card Payment': $pixel_settings['title'];
		if ($pixel_enabled) {
			$card_id1 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card'));
			$card_id2 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('OMANNET'));
			$card_ids = array_merge($card_id1,$card_id2);
			//echo "<pre> Card  - ";print_r($card_ids).'</br>';exit;
			$apple_pay_ids = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('apple_pay'));
			$google_pay1 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('google-pay'));
			$google_pay2 = array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Google-pay'));
			$google_pay_ids = array_merge($google_pay1 , $google_pay2);
			$bank_installment_ids = array_keys( PaymobAutoGenerate::get_pixel_bank_installment_integration_ids() );
			$all_ids = array_merge($card_ids, $apple_pay_ids, $google_pay_ids, $bank_installment_ids);
			$all_ids = array_filter($all_ids);
			$integration_ids = implode(',', $all_ids);
			$card_integrations1 = array_map('strval', array_keys(PaymobAutoGenerate::get_pixel_integration_ids('Card')));
			$card_integrations2 = array_map('strval', array_keys(PaymobAutoGenerate::get_pixel_integration_ids('OMANNET')));
			$card_integrations = $card_integrations1+$card_integrations2 ;
			$existing = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}paymob_gateways WHERE gateway_id = %s", 'paymob-pixel'), OBJECT);
			if (empty($existing)) {
				$wpdb->insert(
					$wpdb->prefix . 'paymob_gateways',
					array(
						'gateway_id' => 'paymob-pixel',
						'file_name' => 'class-gateway-paymob-pixel.php',
						'class_name' => 'Paymob_Pixel',
						'checkout_title' => 'Debit/Credit Card Payment',
						'checkout_description' => 'Debit/Credit Card Payment',
						'integration_id' => $integration_ids,
						'is_manual' => '0',
						'ordering' => 35,
						'mode' => $paymob_mode
					)
				);
				if (empty($pixel_settings['cards_integration_id'])) {
					$pixel_settings['cards_integration_id'] = $card_integrations;
				}
			} else {
				if (($pixel_settings['cards_integration_id'] !== $card_integrations)) {
					$wpdb->update(
						$wpdb->prefix . 'paymob_gateways',
						array(
							'integration_id' => $integration_ids,
							'ordering' => 35,
							'mode' => $paymob_mode
						),
						array('gateway_id' => 'paymob-pixel')
					);
					$pixel_settings['cards_integration_id'] = $card_integrations;
				}
			}

			PaymobAutoGenerate::update_paymob_gateway_order();
		} else {
			$wpdb->delete($wpdb->prefix . 'paymob_gateways', array('gateway_id' => 'paymob-pixel'));
		}
		update_option('woocommerce_paymob-pixel_settings', $pixel_settings);
	}
	public function migrate_old_settings()
	{
		$paymobOptions = get_option('woocommerce_paymob-main_settings');
		$pubKey = isset($paymobOptions['pub_key']) ? $paymobOptions['pub_key'] : '';
		$secKey = isset($paymobOptions['sec_key']) ? $paymobOptions['sec_key'] : '';
		$mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : '';
		if($mode=='')
		{
			$parts = explode('_', $secKey);

			// Get the word "test" (3rd part)
			$mode = $parts[2];
			if($mode=='test'){
				$paymobOptions['test_pub_key'] = $pubKey;
				$paymobOptions['test_sec_key'] = $secKey;
				$paymobOptions['mode'] = $mode;
	
			}
			if($mode=='live'){
				$paymobOptions['live_pub_key'] = $pubKey;
				$paymobOptions['live_sec_key'] = $secKey;
				$paymobOptions['mode'] = $mode;
			}
			update_option('woocommerce_paymob-main_settings', $paymobOptions);
			Paymob_Manual_Setup_Save::pixel_settings($mode);

		}
		
	}
}
function enqueue_paymob_accordion_scripts($hook)
{
	if ((Paymob::filterVar('section')) && Paymob::filterVar('section') == 'paymob-main') {
		Paymob_Scripts::paymob_accordion();

	}
}
add_action('admin_enqueue_scripts', 'enqueue_paymob_accordion_scripts');
function check_paymob_main_gateway_enabled($old_value, $new_value)
{
	// Check if the 'enabled' option exists in both old and new values.
	if (isset($new_value['enabled']) && $new_value['enabled'] === 'yes') {
		// If the old value was either 'no' or not set, this means the gateway was just enabled.
		if (!isset($old_value['enabled']) || $old_value['enabled'] === 'no') {
			$paymob_options = get_option('woocommerce_paymob-main_settings');
			$debug = isset($paymob_options['debug']) ? sanitize_text_field($paymob_options['debug']) : '0';
			try {
				$conf['pubKey'] = isset($paymob_options['pub_key']) ? sanitize_text_field($paymob_options['pub_key']) : '';
				$conf['secKey'] = isset($paymob_options['sec_key']) ? sanitize_text_field($paymob_options['sec_key']) : '';

				$conf['apiKey'] = isset($paymob_options['api_key']) ? sanitize_text_field($paymob_options['api_key']) : '';

				$addlog = Paymob::log_dir() . 'paymob-auth.log';
				$paymobReq = new Paymob($debug, $addlog);
				$result = $paymobReq->authToken($conf);
				$ids = array();
				foreach ($result['integrationIDs'] as $value) {
					$ids[] = trim($value['id']);
				}
				PaymobAutoGenerate::register_framework($ids);
			} catch (\Exception $e) {
				WC_Admin_Settings::add_error( esc_html( $e->getMessage() ) );
			}
		}
	}
}
add_action('update_option_woocommerce_paymob-main_settings', 'check_paymob_main_gateway_enabled', 10, 2);
