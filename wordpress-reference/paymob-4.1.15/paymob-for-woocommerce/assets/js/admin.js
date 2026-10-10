jQuery( document ).ready(
	function () {
		jQuery( ".loader_paymob" ).fadeOut( 1500 );
		jQuery( 'textarea' ).removeClass( 'input-text wide-input ' );
		jQuery( 'input:text,textarea,select' )
			.filter( ':visible' )
			.filter( function () {
				var $field = jQuery( this );
				if ( $field.closest( '.paymob-aw-page' ).length ) {
					return false;
				}
				if ( $field.hasClass( 'paymob-aw-select' ) ) {
					return false;
				}
				if ( $field.closest( '.customization-settings-table' ).length ) {
					return false;
				}
				if ( $field.is( '#apple_pay_integration_id, #google_pay_integration_id, #bank_installment_integration_id, #cards_integration_id' ) ) {
					return false;
				}
				var fieldName = $field.attr( 'name' ) || '';
				if ( fieldName.indexOf( 'woocommerce_paymob_pixel_' ) === 0 ) {
					return false;
				}
				return true;
			} )
			.attr( 'required', 'required' )
			.each(
				function ( i, requiredField ) {
					var $field = jQuery( requiredField );
					if ( $field.closest( '.paymob-aw-page' ).length ) {
						return;
					}
					if ( jQuery( '#manualsetupconfirmationModal' ).is( ':visible' ) === false ) {
						$field.after( '<span class="red-star"> *</span>' );
					}
				}
			);
	}
);
jQuery( '#cpicon' ).click(
	function () {
		var copyText = document.getElementById( 'cburl' ).innerText;
		prompt( "Copy link, then click OK.", copyText );
	}
);

jQuery(document).ready(function($) {
	if ($('.paymob-connect-div').length) {
        // If it exists, hide the .woocommerce-save-button
        $(".woocommerce-save-button").hide(); 
    }
	// $('.switch-mode').on('click', function () {
	// 	// openChangeModeModal();
	// 	if (this.checked) {
    //         $('#mode-status').text('Live'); // When checked, set Live Mode
    //     } else {
    //         $('#mode-status').text('Test'); // When unchecked, set Test Mode
    //     }
	// });
});
jQuery(document).ready(function($) {
    // Remove pipe separator '|' from list items
    $('.subsubsub li').each(function() {
        var html = $(this).html().replace(/\s*\|\s*/g, ''); // Remove the '|' and spaces around it
        $(this).html(html);
    });
});

jQuery(document).ready(function ($) {
    // Target the multiselect field by its ID or class
    $('select[name="woocommerce_paymob_integration_id[]"]').select2({
        placeholder: 'Select Integration ID(s)',
        allowClear: true,
        width: '100%', // Full width
    });
});


jQuery(document).ready(function ($) {
    // Initialize Select2 for the Cards Integration ID
    $('#paymob_subscription_ds3_integration_ids').select2({
        placeholder: 'Select Integration ID',
        allowClear: true,
        width: '100%', // Ensure full width
    });
});


jQuery(document).ready(function ($) {
    // Initialize Select2 for the Cards Integration ID
    $('#paymob_subscription_moto_integration_id').select2({
        placeholder: 'Select Integration ID',
        allowClear: true,
        width: '100%', // Ensure full width
    });
});