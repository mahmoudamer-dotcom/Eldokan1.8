<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table id="paymob_custom_gateways" class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th class="column-reorder"><?php esc_html_e('Re-Order', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Enable / Disable', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Payment Method', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Title', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Description', 'paymob-for-woocommerce'); ?></th>
            <th class="column-integration-id"><?php esc_html_e('Integration ID', 'paymob-for-woocommerce'); ?></th>
            <th class="column-logo"><?php esc_html_e('Logo', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Webhook URL', 'paymob-for-woocommerce'); ?></th>
            <th><?php esc_html_e('Action', 'paymob-for-woocommerce'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="8"><?php esc_html_e('Please wait while loading Payment methods ...', 'paymob-for-woocommerce'); ?></td>
        </tr>
    </tbody>
</table>
<p><a href="#" style="cursor:pointer;"
		id="reset-paymob-gateways"><?php esc_html_e( 'Click here', 'paymob-for-woocommerce' ); ?></a>
	<?php esc_html_e( 'to re-authenticate your Paymob configuration to get the new updated payment methods.', 'paymob-for-woocommerce' ); ?>
</p>
