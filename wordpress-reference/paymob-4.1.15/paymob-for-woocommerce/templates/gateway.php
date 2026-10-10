<?php
class class_name_Gateway extends Paymob_Payment {

	public $id;
	public $method_title;
	public $method_description;
	public $has_fields;
	public function __construct() {
		$this->id                 = 'gateway_id';
		$this->method_title       = $this->title = __( 'checkout_title', 'paymob-for-woocommerce' );
		$this->method_description = $this->description = __( 'checkout_description', 'paymob-for-woocommerce' );
		parent::__construct();
		// config
		$this->init_settings();
	}
	public function admin_options() {
		PaymobAutoGenerate::gateways_method_title( $this->method_title, $this, $this->get_option( 'single_integration_id' ) );
	}
}
