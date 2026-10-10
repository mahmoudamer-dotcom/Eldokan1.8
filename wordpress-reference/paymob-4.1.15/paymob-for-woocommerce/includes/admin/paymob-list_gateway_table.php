<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Paymob_List_Gateways {

	public static function paymob_list_gateways_table( $gateway ) {
		if( 'paymob-pixel' === $gateway->gateway_id ){
			$edit_url       = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paymob_pixel' );
		}
		else
		{
			$edit_url       = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gateway->gateway_id );

		}
		$gateway_id     = $gateway->gateway_id;
		$gateway_option = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		$title          = $gateway_option['title'] ?? 's';
		$description    = ( 'paymob-pixel' === $gateway_id ) ? $gateway->checkout_description : ($gateway_option['description'] ?? '');
		$logo           = $gateway_option['logo'] ?? plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/paymob.png';
		$integration_id = in_array($gateway_id, ['paymob', 'paymob-pixel'], true) ? $gateway->integration_id : ($gateway_option['single_integration_id'] ?? '');
		$enabled        = $gateway_option['enabled'] ?? 'no';
		$checked        = 'yes' === $enabled ? 'checked' : '';
		// Shorten long descriptions and add "Show More" link if needed.
		$is_long_description = strlen( $description ) > 100;
		$short_description   = $is_long_description ? substr( $description, 0, 100 ) . '...' : $description;
		$show_more_link      = $is_long_description ? '<a href="javascript:void(0);" class="show-more">Show More</a>' : '';
		$webhook_url       = get_post_meta( $gateway_id, '_webhook_url', true );  // Retrieve webhook URL from the meta
		if(( 'paymob-pixel' !== $gateway_id ) && ( 'paymob' !== $gateway_id )){
			$webhook_button    = '<button type="button" class="button show-webhook-url-form" data-gateway-id="' . $gateway_id . '"> Webhook URL</button>';
		}
		else
		{
			$webhook_button    = '<button type="button" disabled="disabled" class="button show-webhook-url-form" data-gateway-id="' . $gateway_id . '"> Webhook URL</button>';


		}
       
		// Generate HTML row.
		$row_html  = '<tr data-gateway-id="' . esc_attr( $gateway_id ) . '">';
		$row_html .= '<td class="column-reorder" data-label="Re-Order"><span class="dashicons dashicons-editor-justify"></span></td>';

		// Checkbox with conditional disabled attribute for "paymob-pixel".
		$checkbox_disabled = 'paymob-pixel' === $gateway_id ? 'disabled="disabled"' : '';
		$row_html .= '<td data-label="Enable / Disable" ><input type="checkbox" class="enable-checkbox" data-gateway-id="' . esc_attr( $gateway_id ) . '" data-integration-id="' . esc_attr( $integration_id ) . '" ' . $checked .  ' /></td>';
		
		// Populate row with gateway details.
		$row_html .= '<td data-label="Payment Method">' . esc_html( $gateway_id ) . '</td>';
		$row_html .= '<td data-label="Title">' . esc_html( $title ) . '</td>';
		$row_html .= '<td data-label="Description"><span class="short-description">' . esc_html( $short_description ) . '</span><span class="full-description" style="display:none;">' . esc_html( $description ) . '</span>' . $show_more_link . '</td>';
		$row_html .= '<td class="column-integration-id" data-label="Integration ID">' . esc_html( $integration_id ) . '</td>';
		$row_html .= '<td class="column-logo" data-label="Logo"><img style="max-width: 70px;" src="' . esc_url( $logo ) . '" /></td>';
		
		// Edit button with conditional disabled attribute for "paymob-pixel".
		$row_html .= '<td data-label="Webhook URL" class="webhook_uRL" integration_id="'.$integration_id.'">' . ($webhook_url ? esc_html( $webhook_url ) : $webhook_button) . '</td>';  // Add Webhook URL column

		$row_html .= '<td data-label="Action"><a href="' . esc_url( $edit_url ) . '" class="button button-secondary" >' . __( 'Edit', 'paymob-for-woocommerce' ) . '</a> ';

		// Remove button with conditional disabled attribute based on is_manual.
		$remove_button_disabled = '0' === $gateway->is_manual ? 'disabled="disabled"' : '';
		$row_html .= '<button type="button" class="button remove-button button-primary" data-gateway-id="' . esc_attr( $gateway_id ) . '" ' . $remove_button_disabled . '>' . __( 'Remove', 'paymob-for-woocommerce' ) . '</button></td>';
		
		$row_html .= '</tr>';

		return $row_html;
	}

	public static function paymob_not_found_record_table() {
		$table_body  = '';
		$table_body .= '<tr><td colspan="8">' . __( 'No gateways found.', 'paymob-for-woocommerce' ) . '</td></tr>';
		return $table_body;
	}
	
}
