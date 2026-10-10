<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$tabs = include __DIR__ . '/paymob-admin-tabs.php';
            
return array(
	array(
        'name' => '',
        'type' => 'title',
        'desc' => $tabs,
    ),
	array(
		'type' => 'title',
		'name' => __( 'Add Payment Integration', 'paymob-for-woocommerce' ),
	),
	array(
		'name'     => __( 'Enable', 'paymob-for-woocommerce' ),
		'type'     => 'checkbox',
		'id'       => 'payment_enabled',
		'desc_tip' => true,
		'default'  => 'no',
	),
	array(
		'name'              => __( 'Payment Method', 'paymob-for-woocommerce' ),
		'type'              => 'text',
		'id'                => 'payment_integrations_type',
		'desc_tip'          => true,
		'custom_attributes' => array( 'required' => 'required' ),
	),
	array(
		'name'              => __( 'Paymob Integration ID', 'paymob-for-woocommerce' ),
		'type'              => 'select',
		'id'                => 'integration_id',
		'desc_tip'          => true,
		'custom_attributes' => array( 'required' => 'required' ),
		'options'           => PaymobAutoGenerate::get_integration_ids(), // Dynamically loaded options.
	),
	array(
		'name'              => __( 'Payment Method -  Title', 'paymob-for-woocommerce' ),
		'type'              => 'text',
		'id'                => 'checkout_title',
		'desc_tip'          => true,
		'custom_attributes' => array( 'required' => 'required' ),
	),
	array(
		'name'              => __( 'Payment Method -  Description', 'paymob-for-woocommerce' ),
		'type'              => 'textarea',
		'id'                => 'checkout_description',
		'desc_tip'          => true,
		'custom_attributes' => array( 'required' => 'required' ),
	),
	array(
		'name'              => __( 'Payment Method - Logo URL', 'paymob-for-woocommerce' ),
		'type'              => 'text',
		'id'                => 'payment_logo',
		'desc_tip'          => true,
		'default'           => plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png',
		'custom_attributes' => array( 'required' => 'required' ),
	),
	array(
		'type' => 'sectionend',
		'id'   => 'paymob_add_gateway',
	),
);
