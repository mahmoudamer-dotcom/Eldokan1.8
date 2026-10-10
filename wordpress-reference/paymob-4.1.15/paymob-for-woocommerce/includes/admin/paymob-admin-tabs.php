<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'paymob-main';

$output = '<div class="paymob-admin-tab">
  <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob-main' ) ) . '" class="tablinks ' . esc_attr( 'paymob-main' === $current_section ? 'active' : '' ) . '">' . esc_html__( 'Main Configuration', 'paymob-for-woocommerce' ) . '</a>
  <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_list_gateways' ) ) . '" class="tablinks ' . esc_attr( 'paymob_list_gateways' === $current_section ? 'active' : '' ) . '">' . esc_html__( 'Payment Integrations', 'paymob-for-woocommerce' ) . '</a>
  <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_pixel' ) ) . '" class="tablinks ' . esc_attr( 'paymob_pixel' === $current_section ? 'active' : '' ) . '">' . esc_html__( 'Card Embedded Settings', 'paymob-for-woocommerce' ) . '</a>';

if ( class_exists( 'WC_Subscriptions' ) ) {
	$output .= '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_subscription' ) ) . '" class="tablinks ' . esc_attr( 'paymob_subscription' === $current_section ? 'active' : '' ) . '">' . esc_html__( 'Subscription', 'paymob-for-woocommerce' ) . '</a>';
}

$output .= '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=widget' ) ) . '" class="tablinks ' . esc_attr( 'widget' === $current_section ? 'active' : '' ) . '"><span class="paymob-admin-tab__label">' . esc_html__( 'Affordability Widget', 'paymob-for-woocommerce' ) . '</span><span class="paymob-tab-new-badge">' . esc_html__( 'New', 'paymob-for-woocommerce' ) . '</span></a>';

// $output .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=paymob_add_gateway') . '" class="tablinks ' . ($current_section === 'paymob_add_gateway' ? 'active' : '') . '">' . esc_html__( 'Add Payment Integration', 'paymob-for-woocommerce') . '</a>';
$output .= '</div>';

return $output;
