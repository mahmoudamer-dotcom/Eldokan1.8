<?php
class Paymob_Subscription_Gateway extends Paymob_Payment {

	public $id;
	public $method_title;
	public $method_description;
	public $has_fields;
	public function __construct() {
		$this->id                 = 'paymob-subscription';
		$this->method_title       = $this->title = __( 'Debit/Credit Card', 'paymob-for-woocommerce' );
		$this->method_description = $this->description = __( 'Recurring Payment via Paymob.', 'paymob-for-woocommerce' );
		parent::__construct();
		// config
		$this->init_settings();
	}
	public function admin_options() {
		PaymobAutoGenerate::gateways_method_title( $this->method_title, $this, $this->get_option( 'single_integration_id' ) );
	}
}
