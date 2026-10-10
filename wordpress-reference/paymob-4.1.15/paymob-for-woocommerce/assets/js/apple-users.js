jQuery( 'body' ).on(
	'updated_checkout',
	function () {
		if ( ! window.ApplePaySession) {
			jQuery( 'li[class*="-apple-pay-"]' ).remove();
		}
	}
);