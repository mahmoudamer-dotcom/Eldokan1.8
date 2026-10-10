<?php
/**
 * Confirmation modal for disabling Paymob gateways.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $gateways ) || ! is_array( $gateways ) ) {
	$gateways = array();
}
?>
<div id="confirmationModal">
	<h2><?php echo esc_html(__( 'Disable Paymob Gateway','paymob-for-woocommerce')); ?></h2>
	<p><?php echo esc_html(__( 'If you disable this gateway, all Paymob gateways will be disabled. Do you want to continue?','paymob-for-woocommerce')); ?></p>
	<ul>
		<?php
		foreach ( $gateways as $gateway ) {
			$options = get_option( 'woocommerce_' . $gateway->gateway_id . '_settings', array() );
			$enabled = isset( $options['enabled'] ) && 'yes' === $options['enabled'];
			if ( $enabled ) {
				$logo  = isset( $options['logo'] ) ? esc_url( $options['logo'] ) : '';
				$title = isset( $options['title'] ) ? esc_html( $options['title'] ) : 'Unknown Gateway';
				?>
				<li>
					<img src="<?php echo esc_url( $logo ); ?>" width="36" height="23" alt="<?php echo esc_attr( $title ); ?>">
					<?php echo esc_html( $title ); ?>
				</li>
				<?php
			}
		}
		?>
	</ul>
	<button id="confirmDisable"><?php echo esc_html(__( 'Disable','paymob-for-woocommerce')); ?></button>
	<button id="confirmCancel"><?php echo esc_html(__( 'Cancel','paymob-for-woocommerce')); ?></button>
</div>
<div id="overlay"></div>