<?php
class Paymob_Gateway extends Paymob_Payment {

	public $id;
	public $method_title;
	public $method_description;
	public $has_fields;
	public function __construct() {
		$this->id                 = 'paymob';
		$this->method_title       = $this->title = __( 'Pay with Paymob', 'paymob-for-woocommerce' );
		$this->method_description = $this->description = __( 'Pay with Paymob', 'paymob-for-woocommerce' );
		parent::__construct();
		// config
		$this->init_settings();
	}
	public function init_form_fields() {
		$this->form_fields = include __DIR__ . '/../admin/paymob-unified.php';
	}
	public function admin_options() {
		PaymobAutoGenerate::gateways_method_title( $this->method_title, $this, null );
	}
}
