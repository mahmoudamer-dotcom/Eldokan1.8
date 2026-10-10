<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_class_name_Blocks' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'gateway-blocks.php';

	final class WC_class_name_Blocks extends Paymob_Gateway_Blocks {

		public function __construct() {
			$this->name = 'gateway_id';
		}
	}

}
