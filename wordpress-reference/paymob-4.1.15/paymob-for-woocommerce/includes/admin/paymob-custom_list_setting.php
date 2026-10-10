<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	array(
		'type' => 'title',
		'name' => __( 'List of Payment Method Integrations', 'paymob-for-woocommerce' ),
	),
	array(
		'type' => 'table',
		'id'   => 'paymob_custom_gateways',
		'css'  => ' ',
	),
	array(
		'type' => 'sectionend',
		'id'   => 'paymob_list_gateways',
	),
);
