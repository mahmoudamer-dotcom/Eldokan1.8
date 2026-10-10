/**
 * Classic checkout: when the shopper did NOT use widget Buy Now, ensure Bank Installments
 * is not left selected (WooCommerce otherwise picks the first gateway in the list).
 */
( function ( $ ) {
	'use strict';

	function isWidgetBuyNowFlow() {
		if ( typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout.preselect ) {
			return true;
		}

		return new URLSearchParams( window.location.search ).has( 'paymob_aw_preselect' );
	}

	function selectPixelIfBankWasAutoSelected() {
		if ( isWidgetBuyNowFlow() ) {
			return;
		}

		if ( document.querySelector( '.wc-block-checkout' ) ) {
			return;
		}

		var $pixel = $( '#payment_method_paymob-pixel' );
		if ( ! $pixel.length ) {
			return;
		}

		var $checked = $( 'input[name="payment_method"]:checked' );
		var selected = $checked.val() || '';

		if ( selected && selected.indexOf( 'bank-installments' ) === -1 ) {
			return;
		}

		if ( $pixel.is( ':checked' ) ) {
			return;
		}

		$( 'input[name="payment_method"]' ).prop( 'checked', false );
		$( '.payment_box' ).hide();
		$pixel.prop( 'checked', true ).trigger( 'change' );
		$pixel.closest( 'li' ).find( '.payment_box' ).show();
	}

	$( function () {
		selectPixelIfBankWasAutoSelected();
	} );

	$( document.body ).on( 'updated_checkout', selectPixelIfBankWasAutoSelected );
} )( jQuery );
