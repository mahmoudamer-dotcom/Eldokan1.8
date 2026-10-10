<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="confirmation-modal" style="display:none;">
	<div id="confirmation-modal-content">
		<h2 id="confirmation-modal-title"></h2>
		<p id="confirmation-modal-message"></p>

		<div class="modal-buttons">
		<button type="button"  id="confirmation-modal-confirm"><?php echo esc_html( __( 'Confirm', 'paymob-for-woocommerce' ) ); ?></button>
		<button type="button" id="confirmation-modal-cancel"><?php echo esc_html( __( 'Cancel', 'paymob-for-woocommerce' ) ); ?></button>
        </div>
		
	</div>
</div> 
<div class="loader_paymob"></div>
<?php
$paymobOptions = get_option( 'woocommerce_paymob-main_settings' );
$mode         = isset( $paymobOptions['mode'] ) ? $paymobOptions['mode'] : 'test';
$sliderMode   = ( 'test' === $mode ) ? '' : 'silderMode';
// Generate HTML row.
$tabs = include __DIR__ . '/../../paymob-admin-tabs.php';
$modeSwitcher = sprintf(
	'<div id="changemodemodal_confirm_button" class="mode-toggle-container switch-mode" style="max-width: 20%%;">
    <label for="mode-toggle"></label>
    <label class="switch">
        <span class="slider round %1$s"></span>
    </label>
    <span id="mode-status">%2$s</span>
</div>',
	esc_attr( $sliderMode ),
	esc_html( ucfirst( $mode ) )
);

echo wp_kses_post( $tabs . '<br/><br/>' . $modeSwitcher );
?>
