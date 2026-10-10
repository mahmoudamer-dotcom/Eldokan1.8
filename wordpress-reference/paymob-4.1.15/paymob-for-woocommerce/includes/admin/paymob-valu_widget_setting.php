<?php
if (!defined('ABSPATH')) {
    exit;
}
// Fetch the ValU integration IDs
$valu_integration_ids = PaymobAutoGenerate::get_valu_integration_ids();
// Determine the description based on whether integration IDs exist
$integration_description = empty($valu_integration_ids)
    ? '<strong style="color: red;">Please enable a ValU Integration ID from the Payment Integrations section to use the ValU Widget.</strong>'
    : 'Choose the Integration ID for the ValU Widget.';

$tabs = include __DIR__ . '/paymob-admin-tabs.php';
            
$settings = array(
    'tab'=>array(
        'name' => '',
        'type' => 'title',
        'desc' => $tabs,
    ),
    'section_title' => array(
        'title' => __( 'ValU Widget Settings', 'paymob-for-woocommerce' ),
        'type'  => 'title',
        'id'    => 'valu_widget_section_title',
    ),
    'enable_widget' => array(
        'title'       => __( 'Enable ValU Widget', 'paymob-for-woocommerce' ),
        'type'        => 'checkbox',
        'id'          => 'enable',  // ✅ Added ID
        'default'     => 'no',
        'label'       => ' ',
        'description' => __( 'Enable or disable the ValU widget.', 'paymob-for-woocommerce' ),
    ),
    'integration_id' => array(
        'title'       => __( 'Select Integration ID for ValU Widget', 'paymob-for-woocommerce' ),
        'type'        => 'select',
        'id'          => 'integration_id',  // ✅ Added ID
        'options'     => $valu_integration_ids,
        'default'     => '',
        'label'       => ' ',
      
        'description' => empty($valu_integration_ids) 
        ? '<div style="background: #ffecec; border-left: 4px solid red; padding: 10px; margin-bottom: 10px;">
                <strong style="color: red;">Warning:</strong> Please enable a ValU Integration ID from the Payment Integrations section to use the ValU Widget.
           </div>' 
        : 'Choose the Integration ID for the ValU Widget.',
  
        
    ),
    'dark_mode' => array(
        'title'       => __( 'Enable Dark Mode', 'paymob-for-woocommerce' ),
        'type'        => 'checkbox',
        'id'          => 'dark_mode',  // ✅ Added ID
        'default'     => 'no',
        'label'       => ' ',
        'description' => __( 'This controls the style mode for ValuWidget.', 'paymob-for-woocommerce' ),
    ),
    'section_end' => array(
        'type' => 'sectionend',
        'id'   => 'valu_widget_section_end',
    ),
);

return $settings;



