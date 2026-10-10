<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sections                         = array();
$sections['paymob-main']          = __( 'Main configuration', 'paymob-for-woocommerce' );
if ( !empty( $pub_key ) && !empty( $sec_key ) && !empty( $api_key ) ) {	
    $sections['paymob_list_gateways'] = __( 'Payment Integrations', 'paymob-for-woocommerce' );
    $sections['paymob_pixel']   = __( 'Card Embedded Settings', 'paymob-for-woocommerce' );
    $sections['paymob_add_gateway']   = __( 'Add Payment Integration', 'paymob-for-woocommerce' );    
}
return $sections;
