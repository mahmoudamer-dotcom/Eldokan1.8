<?php
/**
 * Unified gateway settings fields.
 *
 * Included from gateway init_form_fields().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tabs = include __DIR__ . '/paymob-admin-tabs.php';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- section is used only for default logo path.
$gateway_id = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'paymob';
            
return array(
	'tabs'=> array(
        'name' => '',
        'type' => 'title',
        'description' => $tabs,
    ),
	'integration_id' => array(
		'title'             => __( 'Paymob Integration ID(s)', 'paymob-for-woocommerce' ),
		'type'              => 'multiselect',
		'options'           => PaymobAutoGenerate::get_integration_ids(),
		'custom_attributes' => array(
			'required' => 'required',
			'multiple' => 'multiple',
        ),
	),
	'title'          => array(
		'title'             => __( 'Payment Method - Title', 'paymob-for-woocommerce' ),
		'type'              => 'text',
		'description'       => __( 'This controls the title which the user sees during checkout.', 'paymob-for-woocommerce' ),
		'default'           => __( 'Pay with Paymob', 'paymob-for-woocommerce' ),
		'sanitize_callback' => 'sanitize_text_field',
		'custom_attributes' => array( 'required' => 'required' ),
	),
	'description'    => array(
		'title'             => __( 'Payment Method - Description', 'paymob-for-woocommerce' ),
		'type'              => 'textarea',
		'default'           => __( 'Pay with Paymob', 'paymob-for-woocommerce' ),
		'description'       => __( 'This controls the description which the user sees during checkout.', 'paymob-for-woocommerce' ),
		'sanitize_callback' => 'sanitize_text_field',
		'custom_attributes' => array( 'required' => 'required' ),
	),
	'logo'           => array(
		'title'             => __( 'Payment Method - Logo URL', 'paymob-for-woocommerce' ),
		'default'           => plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/' . $gateway_id . '.png',
		'type'              => 'text',
		'description'       => __( 'Add a Logo URL for checkout icon.', 'paymob-for-woocommerce' ),
		'sanitize_callback' => 'sanitize_url',
		'custom_attributes' => array( 'required' => 'required' ),
	),
);
