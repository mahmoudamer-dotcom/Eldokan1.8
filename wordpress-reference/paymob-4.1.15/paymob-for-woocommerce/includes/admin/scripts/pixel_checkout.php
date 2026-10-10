<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="paymob-elements"></div>
<script>
    window.hasEmptyFields = false;
  

    window.addEventListener('load', function () {           
         updateCheckoutData(); 
        });
        jQuery(document).ready(function ($) {
			$(document).on('change', 'input[name="payment_method"]', function () {
			if (this.value === 'paymob-pixel') {
				updateCheckoutData();
				console.log('updateCheckoutData() called on user change to paymob-pixel');
			} else if (typeof clearPaymobPixelCheckoutAdjustments === 'function') {
				clearPaymobPixelCheckoutAdjustments();
			}
		});
        // Function to toggle the visibility of the Place Order button
        function togglePlaceOrderButton() {
            const selectedPaymentMethod = $('input[name="payment_method"]:checked').val(); // Get selected payment method
            const isPreselectFlow = new URLSearchParams(window.location.search).has('paymob_aw_preselect')
                || (typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout.preselect);
            const targetGateway = (typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout.gatewayId)
                ? window.paymobAffordabilityCheckout.gatewayId
                : '';
            
            if (isPreselectFlow && selectedPaymentMethod === 'paymob-pixel') {
                $('#place_order').hide();
            } else if (isPreselectFlow) {
                $('#place_order').show().prop('disabled', false);
                document.body.classList.add('paymob-aw-show-place-order');
                return;
            }

            if (selectedPaymentMethod && selectedPaymentMethod.indexOf('bank-installments') !== -1) {
                $('#place_order').show().prop('disabled', false);
            } else if (isPreselectFlow && targetGateway && (targetGateway.indexOf('bank-installments') !== -1 || targetGateway === 'paymob')) {
                $('#place_order').show().prop('disabled', false);
            } else if (selectedPaymentMethod === 'paymob-pixel') {
                // Hide the Place Order button for 'paymob-pixel'
                $('#place_order').hide();
            } else {
                // Show the Place Order button for other payment methods
                $('#place_order').show();
            }
        }

        // Run on page load
        togglePlaceOrderButton();

        // Attach event listener to payment method radio buttons
        $(document).on('change', 'input[name="payment_method"]', togglePlaceOrderButton);

        // Reapply toggle logic after checkout updates
        $(document.body).on('updated_checkout', togglePlaceOrderButton);

        if (new URLSearchParams(window.location.search).has('paymob_aw_preselect')
            || (typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout.preselect)) {
            const preselectPlaceOrderGuard = window.setInterval(togglePlaceOrderButton, 250);
            window.setTimeout(function () {
                window.clearInterval(preselectPlaceOrderGuard);
            }, 120000);
        }
        $('form.checkout').on('submit', function (event) {
            // Loop through required checkout fields
            $('.woocommerce-billing-fields input, .woocommerce-billing-fields select').each(function () {
                if ($(this).prop('required') && $(this).val().trim() === '') {
                    window.hasEmptyFields = true;
                    $(this).css('border', '1px solid red'); // Highlight the empty field
                } else {
                    $(this).css('border', ''); // Remove the border for filled fields
                }
            });
        });
    });

    if (jQuery('#place_order').length && jQuery('input[name="payment_method"]:checked').val() === 'paymob-pixel' && window.hasEmptyFields === false) {
        jQuery('#place_order').on('click', function (event) { 
            event.preventDefault(); // Prevent default form submission
            const triggerPay = function () {
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                if (typeof startPaymobOtpOverlayGuard === 'function') {
                    startPaymobOtpOverlayGuard();
                }
                const payFromOutside = new Event('payFromOutside');
                window.dispatchEvent(payFromOutside);
                const updateIntentionData = new Event('updateIntentionData');
                window.dispatchEvent(updateIntentionData);
            };
            if ((window.paymobTotalsSignature || window.paymobDiscountApplied) && typeof pxl_object !== 'undefined') {
                jQuery.ajax({
                    url: pxl_object.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'paymob_sync_pixel_intention',
                        security: pxl_object.update_checkout_nonce,
                    },
                    complete: triggerPay,
                });
            } else {
                triggerPay();
            }
            return false; 
        });
    }
    jQuery('#place_order').on('click', function (event) {
        if(jQuery('button[type="submit"][name="woocommerce_checkout_place_order"]').length =1){
            jQuery('button[type="submit"][name="woocommerce_checkout_place_order"]').submit();
        }
        if(jQuery('input[name="woocommerce_checkout_place_order"]').length =1){
            jQuery('input[name="woocommerce_checkout_place_order"]').submit();
        }else{
            console.info('submit not triggered')
        }
    });



</script>
