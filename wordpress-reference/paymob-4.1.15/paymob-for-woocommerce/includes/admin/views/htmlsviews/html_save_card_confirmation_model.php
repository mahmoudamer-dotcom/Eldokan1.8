<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="paymob-delete-modal" class="paymob-modal">
			<div class="paymob-modal-content">
				<span class="paymob-close">&times;</span>
				<p><?php echo esc_html( __( 'Are you sure you want to delete this card? This action cannot be undone.', 'paymob-for-woocommerce' ) ); ?></p>
				<button id="paymob-confirm-delete" class="button"><?php echo esc_html( __( 'Delete', 'paymob-for-woocommerce' ) ); ?></button>
				<button id="paymob-cancel-delete" class="button"><?php echo esc_html( __( 'Cancel', 'paymob-for-woocommerce' ) ); ?></button>
			</div>
		</div>
