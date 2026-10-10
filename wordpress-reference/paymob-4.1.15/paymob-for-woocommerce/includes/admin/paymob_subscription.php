<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include admin tabs (if needed)
$tabs = include __DIR__ . '/paymob-admin-tabs.php';

$moto_options = PaymobAutoGenerate::get_moto_integration_ids();
$threeds_options = PaymobAutoGenerate::get_ds3_integration_ids();

return array(
	array(
		'name' => '',
		'type' => 'title',
		'desc' => $tabs,
	),
	
	array(
		'type'  => 'title',
		'class' => 'payment-feature-description',
		'name'  => __( 'About Feature', 'paymob-for-woocommerce' ),
		'desc'  => '<div style="background-color: #f0f8ff; border: 1px solid #ddd; padding: 15px; margin-top: 20px; border-radius: 8px; font-family: Arial, sans-serif; color: #333; width:70%; margin-left:1%">
						'.__( '<span style="font-weight: bold; color: #28a745;">Enable Recurring Payments:</span><br/>
						Use Paymob’s Subscription Module to automatically bill customers for your products or services.<br/><br/>
						Supports WooCommerce product types: <span style="font-weight: bold;">Simple Subscription</span> and <span style="font-weight: bold;">Variable Subscription</span>.<br/><br/>
						For support, reach out to your account manager or email <a href="mailto:support@paymob.com" style="color: #007bff; font-weight: bold;">support@paymob.com</a>.', 'paymob-for-woocommerce' ).'
					</div>',
	),

	array(
		'name'     => __( 'Enable Subscription', 'paymob-for-woocommerce' ),
		'type'     => 'checkbox',
		'id'       => 'woocommerce_paymob-subscription_settings[enabled]',
		'desc_tip' => true,
		'default'  => 'no',
	),

	array(
		'name'     => __( 'Subscription Title', 'paymob-for-woocommerce' ),
		'type'     => 'text',
		'id'       => 'woocommerce_paymob-subscription_settings[title]',
		'desc_tip' => true,
		'default'  => 'Debit/Credit Card',
	),

	array(
		'name'     => __( 'Subscription Description', 'paymob-for-woocommerce' ),
		'type'     => 'textarea',
		'id'       => 'woocommerce_paymob-subscription_settings[description]',
		'desc_tip' => true,
		'default'  => __( 'Recurring Payment via Paymob.', 'paymob-for-woocommerce' ),
	),

	array(
		'name'     => __( 'MOTO Integration ID', 'paymob-for-woocommerce' ),
		'type'     => 'select',
		'id'       => 'woocommerce_paymob-subscription_settings[moto_integration_id]',
		'desc_tip' => true,
		'options'  => $moto_options,
		'default'  => '',
		'class'    => 'wc-enhanced-select',
	),

	array(
		'name'     => __( '3DS Integration ID', 'paymob-for-woocommerce' ),
		'type'     => 'select',
		'id'       => 'woocommerce_paymob-subscription_settings[ds3_integration_ids]',
		'desc_tip' => true,
		'options'  => $threeds_options,
		'class'    => 'wc-enhanced-select',
		'default'  => '',
	),

	array(
		'name'     => __( 'Allow Customer Cancel', 'paymob-for-woocommerce' ),
		'type'     => 'checkbox',
		'id'       => 'woocommerce_paymob-subscription_settings[allow_cancel]',
		'label'    => __( 'Show the "Cancel Subscription" button to customers', 'paymob-for-woocommerce' ),
		'default'  => 'no',
		'desc'     => __( 'If enabled, customers can cancel their subscriptions from My Account > Subscriptions.', 'paymob-for-woocommerce' ),
	),

	array(
		'type' => 'sectionend',
		'id'   => 'paymob_subscription',
	),
);
