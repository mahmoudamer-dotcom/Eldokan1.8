<?php
/**
 * Single gateway settings fields.
 *
 * Included from Paymob_Payment::init_form_fields().
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$single_integration_id_properties = PaymobAutoGenerate::disable_single_integration_id_field();
$tabs = include __DIR__ . '/paymob-admin-tabs.php';
$title_default       = '';
$description_default = '';
            
    
$settings = array(
    'tabs'=> array(
        'name' => '',
        'type' => 'title',
        'description' => $tabs,
    ),
    'single_integration_id' => array(
        'title'             => $single_integration_id_properties['title'],
        'type'              => $single_integration_id_properties['type'],  // Set the type dynamically
        'options'           => PaymobAutoGenerate::get_integration_ids(),
        'custom_attributes' => $single_integration_id_properties['custom_attributes'],  // Set custom attributes dynamically
    ),
    'title' => array(
        'title'             => __( 'Payment Method - Title', 'paymob-for-woocommerce' ),
        'type'              => 'text',
        'description'       => __( 'This controls the title which the user sees during checkout.', 'paymob-for-woocommerce' ),
        'default'           => $title_default,
        'sanitize_callback' => 'sanitize_text_field',
        'custom_attributes' => array( 'required' => 'required' ),
    ),
    'description' => array(
        'title'             => __( 'Payment Method - Description', 'paymob-for-woocommerce' ),
        'type'              => 'textarea',
        'default'           => $description_default,
        'description'       => __( 'This controls the description which the user sees during checkout.', 'paymob-for-woocommerce' ),
        'sanitize_callback' => 'sanitize_text_field',
        'custom_attributes' => array( 'required' => 'required' ),
    ),
    'logo' => array(
        'title'             => __( 'Payment Method - Logo URL', 'paymob-for-woocommerce' ),
        'default'           => plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png',
        'type'              => 'text',
        'description'       => __( 'Add a Logo URL for checkout icon.', 'paymob-for-woocommerce' ),
        'sanitize_callback' => 'sanitize_url',
        'custom_attributes' => array( 'required' => 'required' ),
    ),
);

return $settings;
