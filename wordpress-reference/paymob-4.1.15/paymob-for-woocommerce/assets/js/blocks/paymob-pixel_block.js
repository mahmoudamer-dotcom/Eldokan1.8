window.isValid =  true;
window.isCardValid = false;
window.scriptInitialized = false;
window.googleenabled =0;

// Trigger form submission
if (typeof pxl_object !== 'undefined')
{
    window.googleenabled = pxl_object.googleenabled;
   
}
if (typeof window.wc !== 'undefined' && typeof window.wp !== 'undefined' && typeof window.wc.wcSettings !== 'undefined' && typeof window.wc.wcBlocksRegistry !== 'undefined') {
   
    const settings = window.wc.wcSettings.getSetting('paymob-pixel_data', {});
    const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Paymob Pixel Payment', 'paymob-for-woocommerce');
    const Icon = () => {
        return settings.icon
            ? window.wp.element.createElement('img', {
                src: settings.icon,
                id: 'paymob-pixel-logo',
                style: {
                    maxWidth: '70px',
                    float: 'right',
                    paddingTop: '6px'
                }
            })
            : null;
    };
   
  let contentInitialized = false;
    const { useEffect } = window.wp.element;

    const Content = () => {
        useEffect(function () {
            if (!contentInitialized) {
                contentInitialized = true;
                loadScriptsAndInitializePaymob();
            }

            window.setTimeout(function () {
                tryInitializeBlocksPixel();
            }, 150);
        }, []);

        const selectedGateway = document.querySelector(
            'input[name="radio-control-wc-payment-method-options"]:checked'
        );
        if ( selectedGateway && selectedGateway.value === 'paymob-pixel') {
           
            const buttonSelectors = [
                '.wc-block-components-button',
                '.custom-checkout-button',
                '#theme-specific-button-id'
            ];
        
            buttonSelectors.forEach(selector => {
                if (jQuery(selector).length) {
                    updatePlaceOrderVisibility();
                }
            });
        }
        return window.wp.element.createElement('div', {
            id: 'paymob-elements',
            style: {
                maxWidth: '100%',
                width: '100%',
                boxSizing: 'border-box',
                overflowX: 'auto',
                overflowY: 'visible'
            }
        });
    };

    const LabelWithIcon = () => {
        return window.wp.element.createElement('span', { style: { width: '100%' } }, label, window.wp.element.createElement(Icon));
    };

    const Block_Gateway = {
        name: 'paymob-pixel',
        label: window.wp.element.createElement(LabelWithIcon),
        content: window.wp.element.createElement(Content),
        edit: window.wp.element.createElement(Content),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
             features:  [
                'products',
                'refunds',
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions',
            ],
        },
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

    // Append the dynamic CSS
    const css = `
         html[lang="en"] #paymob-pixel-logo {
             float: right !important;
         }
         html[lang="ar"] #paymob-pixel-logo {
             float: left !important;
         }
         .wc-block-checkout .wc-block-components-sidebar-layout .wc-block-components-main {
             flex: 1 1 0% !important;
             min-width: 0 !important;
             width: 0 !important;
         }
         .wc-block-checkout #paymob-elements {
             overflow-x: auto !important;
             overflow-y: visible !important;
         }
        `;
       
    const style = document.createElement('style');
    style.appendChild(document.createTextNode(css));
    document.head.appendChild(style);

    // Function to load Paymob scripts and initialize element
    function loadScriptsAndInitializePaymob() {
        isCheckoutFormValid();
        // const googleenabled =pxl_object.googleenabled;
        // loadScripts();
        const { select } = wp.data;
        const cartStore = select('wc/store/cart');
        const cartTotals = cartStore.getCartTotals();
        //const totalAmount = cartTotals ? cartTotals.total_price : null;
        const totalAmount = (parseInt(cartTotals.total_price, 10) /
            10 ** cartTotals.currency_minor_unit);

        const billingData = cartStore.getCustomerData().billingAddress;

        document.addEventListener("click", function(event) {
            // Check if the clicked element is a payment method radio button
            if (event.target.id.startsWith("radio-control-wc-payment-method-options-")) {
                const methodId = event.target.value || '';
                const ctx = getBlocksCheckoutContext();
                const billingData = ctx ? ctx.billingData : {};
                const totalAmount = ctx ? ctx.totalAmount : 0;
                // Hide the button if "pixel" payment method is selected
                if (event.target.id.includes("paymob-pixel")) {
                    releaseWidgetPreselectForPixelSelection();
                    // Prefer reuse — do not force a new intention on every radio click.
                    ajaxCall(billingData, totalAmount, false);
                    updatePlaceOrderVisibility();
                } else if (methodId.indexOf('bank-installments') !== -1) {
                    updatePlaceOrderVisibility();
                } else {
                    // Show the button for other payment methods
                    setPlaceOrderButtonsVisible(true);
                    updatePlaceOrderVisibility();
                }
            }
        });

        if (wp.data && typeof wp.data.subscribe === 'function') {
            let previousPaymentMethod = '';

            wp.data.subscribe(function () {
                try {
                    const activeMethod = wp.data.select('wc/store/payment').getActivePaymentMethod() || '';
                    if (activeMethod === previousPaymentMethod) {
                        return;
                    }
                    const leftPixel = isPixelPaymentMethod(previousPaymentMethod) && !isPixelPaymentMethod(activeMethod);
                    previousPaymentMethod = activeMethod;

                    if (isPixelPaymentMethod(activeMethod)) {
                        releaseWidgetPreselectForPixelSelection();
                        const ctx = getBlocksCheckoutContext();
                        // Skip while failure-reset owns remount (stops reload loop + "Already being processed").
                        if (window.paymobPixelFailureResetInProgress && !window.paymobPixelFailureRemountDone) {
                            return;
                        }
                        // Only init if Pixel is not already mounted — avoids 2nd/3rd intention.
                        if (ctx && !isPaymobPixelMounted()) {
                            ajaxCall(ctx.billingData, ctx.totalAmount, false);
                        }
                    } else if (leftPixel) {
                        // Leaving Pixel: clear Instant Refund / discount summary + session.
                        if (typeof clearPaymobPixelCheckoutAdjustments === 'function') {
                            clearPaymobPixelCheckoutAdjustments();
                        }
                    }

                    updatePlaceOrderVisibility();
                } catch (err) {
                    return;
                }
            });
        }
        
    }
    document.addEventListener('DOMContentLoaded', function () {
        const checkoutContainer = document.querySelector('.wc-block-checkout');

        if (checkoutContainer) {
            const observer = new MutationObserver(async() => {
                const placeOrderButton = document.querySelector('.wc-block-components-checkout-place-order-button');

                if (placeOrderButton) {
                    placeOrderButton.addEventListener('click', function (e) {
                        const selectedGateway = document.querySelector(
                            'input[name="radio-control-wc-payment-method-options"]:checked'
                        );
                        const selectedMethodId = selectedGateway ? selectedGateway.value : '';

                        // Only intercept Place Order for Paymob Pixel; other gateways (e.g. bank
                        // installments) must use the native WooCommerce Blocks checkout flow.
                        if (selectedMethodId !== 'paymob-pixel') {
                            return;
                        }

                        if (isCheckoutFormValid() !== true) {
                            return;
                        }

                        e.preventDefault();
                        console.log('Paymob Pixel selected.');

                        // Dim Place Order while Pixel payment is processing.
                        window.paymobPaymentProcessing = true;
                        placeOrderButton.disabled = true;
                        placeOrderButton.setAttribute('aria-disabled', 'true');
                        placeOrderButton.classList.add('is-disabled', 'paymob-processing');
                        placeOrderButton.style.opacity = '0.55';
                        placeOrderButton.style.pointerEvents = 'none';
                        // Do not show full-screen overlay here — it covers Paymob OTP / 3DS modals.
                        hideLoadingIndicator();

                        // Sync intention amount (discount / IR) before Pixel confirmation / pay.
                        const triggerPay = function () {
                            hideLoadingIndicator();
                            if (typeof startPaymobOtpOverlayGuard === 'function') {
                                startPaymobOtpOverlayGuard();
                            }
                            const payFromOutside = new Event('payFromOutside');
                            window.dispatchEvent(payFromOutside);
                            window.dispatchEvent(new Event('updateIntentionData'));
                        };
                        if (window.paymobTotalsSignature || window.paymobDiscountApplied) {
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
                    });
                    observer.disconnect();
                }
            });
            observer.observe(checkoutContainer, { childList: true, subtree: true });
        }
        // hide place order in loading page unless bank installments are pre-selected
        setTimeout(function checkButton() {
            if (shouldPreserveBankInstallmentSelection()) {
                document.body.classList.add('paymob-aw-show-place-order');
                hideLoadingIndicator();
                ensureBankInstallmentPreselection();
                updatePlaceOrderVisibility();
                scheduleBankInstallmentPreselection();
                startBlocksPlaceOrderGuard();
                bindBlocksPlaceOrderClickFallback();
                return;
            }

            if (document.querySelector('.wc-block-checkout')) {
                updatePlaceOrderVisibility();
                return;
            }

            const placeOrderBtn = document.querySelector('.wc-block-checkout__form .wp-block-button__link,.wc-block-checkout__form .wc-block-components-button, .wc-block-checkout__form button[type="submit"]');
            
            if (placeOrderBtn) {
                placeOrderBtn.style.display = 'none';
            } else {
                setTimeout(checkButton, 500); // Retry after 500ms
            }
        }, 1000); // Initial delay of 1 second


    });
}

function isCheckoutFormValid() {
    let isValid = true; // Assume valid, but check each field

    jQuery('.wc-block-components-form input[required], .wc-block-components-form select[required]').each(function () {
        const field = jQuery(this);
        if (field.val().trim() === '') {
            isValid = false;
            field.addClass('woocommerce-invalid'); // Highlight invalid field
            field.closest('.form-row').addClass('woocommerce-invalid-row'); // Highlight row
        } else {
            field.removeClass('woocommerce-invalid'); // Remove invalid highlight
            field.closest('.form-row').removeClass('woocommerce-invalid-row'); // Remove row highlight
        }
    });

    window.isValid = isValid;
    if (shouldPreserveBankInstallmentSelection() && !isPixelPaymentMethod(getSelectedPaymentMethodId())) {
        setPlaceOrderButtonsVisible(true);
    } else {
        updatePlaceOrderVisibility();
    }
    return isValid;
}


function updateCheckoutData(forcereload = false) {
    if(!forcereload)
        showLoadingMessage();
    var billingData = {
        first_name: jQuery('#billing_first_name').val(),
        last_name: jQuery('#billing_last_name').val(),
        email: jQuery('#billing_email').val(),
        phone: jQuery('#billing_phone').val(),
        address: jQuery('#billing_address_1').val(),
        city: jQuery('#billing_city').val(),
        postcode: jQuery('#billing_postcode').val(),
        country: jQuery('#billing_country').val(),
        state: jQuery('#billing_state').val(),
    };
    
    var totalAmount = jQuery('.order-total .amount').text().replace(/[^0-9.]/g, '');
    if(totalAmount == null){
        var totalAmount = jQuery('#order_review').find('.order-total .woocommerce-Price-amount').text().replace(/[^0-9.]/g, ''); // Extract total amount
    }
    var opts = window.paymobPixelNextUpdateOptions || {};
    window.paymobPixelNextUpdateOptions = null;
    ajaxCall(billingData, totalAmount, forcereload, opts);
}
// loadScripts();
function loadScripts() {
    // alert(window.googleenabled);
    const paymobScript = document.createElement('script');
    paymobScript.src = "https://cdn.jsdelivr.net/npm/paymob-pixel@latest/main.js";
    // paymobScript.src = "https://cdn.jsdelivr.net/npm/paymob-pixel@1.2.7/main.js";
    paymobScript.type = "module";
    paymobScript.async = true;
    document.head.appendChild(paymobScript);
   
    // const paymobMainCss = document.createElement("link");
    // paymobMainCss.href = "https://cdn.jsdelivr.net/npm/paymob-pixel@latest/main.css";
    // paymobMainCss.rel = "stylesheet";
    // document.head.appendChild(paymobMainCss);

    if (window.googleenabled == 1 ) {
        const googlePayScript = document.createElement('script');
        googlePayScript.src = "https://pay.google.com/gp/p/js/pay.js";
        document.head.appendChild(googlePayScript);
    }
    // const paymobStyleCss = document.createElement("link");
    // paymobStyleCss.href = "https://cdn.jsdelivr.net/npm/paymob-pixel@latest/styles.css";
    // paymobStyleCss.rel = "stylesheet";
    // document.head.appendChild(paymobStyleCss);

    
}
function initializePaymobElement(key, cs) {
    // alert(key+" --- "+ cs)
    if (!cs) {
        return;
    }
    // Same client secret already mounted — skip remount (prevents duplicate Paymob fetches).
    if (window.paymobActiveClientSecret === cs && isPaymobPixelMounted()) {
        hideLoadingIndicator();
        return;
    }
    window.paymobActiveClientSecret = cs;

    jQuery('.wc-block-store-notice').hide() ;
    var forcesavecard = false;
    var customArr = {};

    if (pxl_object.forcesavecard == 1) {
        forcesavecard = true;
    }

    var showsavecard = false;
    if (pxl_object.showsavecard == 1) {
        showsavecard = true;
    }

    var paymentMethods = [];
    if (pxl_object.cardsenabled == 1) {
        paymentMethods.push("card");
    } else if (pxl_object.bankinstallmentenabled == 1) {
        paymentMethods.push("card-installment");
    }
    if (pxl_object.googleenabled == 1) {
        paymentMethods.push("google-pay");

    }
    if (pxl_object.appleenabled == 1) {
        paymentMethods.push("apple-pay");

    }

    var customStyles = pxl_object.customize;
    var pixelContainerWidth = '100%';
    if (customStyles.font_family !== "" || customStyles.font_family !== null) {
        if (document.querySelector('.wc-block-checkout')) {
            pixelContainerWidth = '100%';
        } else if (customStyles.width_of_container !== '' && customStyles.width_of_container !== null) {
            pixelContainerWidth = customStyles.width_of_container + '%';
        }
        customArr = {
            Font_Family: customStyles.font_family,
            Font_Size_Label: customStyles.font_family,
            Font_Size_Input_Fields: customStyles.font_size_input_fields,
            Font_Size_Payment_Button: customStyles.font_size_payment_button,
            Font_Weight_Label: customStyles.font_weight_label,
            Font_Weight_Input_Fields: customStyles.font_weight_input_fields,
            Font_Weight_Payment_Button: customStyles.font_weight_payment_button,
            Color_Container: customStyles.color_container,
            Color_Border_Input_Fields: customStyles.color_border_input_fields,
            Color_Border_Payment_Button: customStyles.color_border_payment_button,
            Radius_Border: customStyles.radius_border,
            Color_Disabled: customStyles.color_disabled,
            Color_Error: customStyles.color_error,
            Color_Primary: customStyles.color_primary,
            Color_Input_Fields: customStyles.color_input_fields,
            Text_Color_For_Label: customStyles.text_color_for_label,
            Text_Color_For_Payment_Button: customStyles.text_color_for_payment_button,
            Text_Color_For_Input_Fields: customStyles.text_color_for_input_fields,
            Color_For_Text_Placeholder: customStyles.color_for_text_placeholder,
            Width_of_Container: pixelContainerWidth,
            Vertical_Padding: customStyles.vertical_padding,
            Vertical_Spacing_between_components: customStyles.vertical_spacing_between_components,
            Container_Padding: customStyles.container_padding
        };
    }
    
    hideLoadingIndicator();
    startPaymobOtpOverlayGuard();
    // Clear placeholder before Pixel mounts into #paymob-elements.
    jQuery('#paymob-elements').empty();
    new Pixel({
        publicKey: key,
        clientSecret: cs,
        paymentMethods: paymentMethods,
        elementId: 'paymob-elements', // The ID of the HTML element for rendering Paymob's element
        disablePay: true,
        showSaveCard: showsavecard,
        forceSaveCard: forcesavecard,
       
        beforePaymentComplete: async (paymentmethod) => {
            console.log('Before Payment Complete');
            console.log('Payment Method '+ paymentmethod);
            // OTP / 3DS must sit above any Woo overlay — hide before Paymob opens the challenge.
            hideLoadingIndicator();
            startPaymobOtpOverlayGuard();

            if(paymentmethod== 'google-pay' || paymentmethod== 'apple-pay')
            {
                if (jQuery('.wc-block-checkout').length) {
                    if(isCheckoutFormValid() === true){
                        console.log('Block checkout - Create order manually for '+ paymentmethod);
                        handleOrderCreation();
                        console.log('Order Created  successfully.');
                        // jQuery('.wc-block-checkout').submit();
                        return true ;
                    }else{
                        displayWooCommerceError("Please fill the Required Information to complete your payment.");
                        return false;
                    }
                } else {
                    // Trigger form submission
                    console.log('Classic Checkout - Create order manually for '+ paymentmethod);
                    // const wooform = jQuery('form.checkout');
                    // jQuery(wooform).append('<input type="hidden" id="pxl_submit" name="pxl_submit" value="pxl_submit">');
                  // First, check if Terms & Conditions checkbox is checked
                    if (jQuery('#terms').length) {
                        // Check if it's an input of type checkbox
                        if (jQuery('#terms').is('input[type="checkbox"]')) {
       
                            if (!jQuery('#terms').is(':checked')) {
                                displayWooCommerceError("Please read and accept the terms and conditions to proceed with your order.");
                                return false;
                            }
                        }
                    }

                    // Then, validate the form
                    if (validateClassicFrom() === false) {
                        return await new Promise((resolve) => {
                            console.log('Classic Checkout - Triggering order submission');
                            
                            // Trigger the WooCommerce place order button
                            jQuery('form.checkout button[name="woocommerce_checkout_place_order"]').trigger('click');

                            // Wait 5 seconds for WooCommerce to create the order
                            setTimeout(() => {
                                console.log('Classic Checkout - Resolve after delay');
                                resolve(true);
                            }, 5000);
                        });
                    } else {
                        displayWooCommerceError("Please fill the Required Information to complete your payment");
                        return false;
                    }


                }
            }
            else
            {
                try {
                    if (jQuery('.wc-block-checkout').length) {
                        // For block-based checkout
                        console.log('Block-based checkout detected for '+ paymentmethod);
                        // Ensure discounted intention amount is on Paymob before confirmation / pay.
                        await new Promise(function (resolve) {
                            jQuery.ajax({
                                url: pxl_object.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'paymob_sync_pixel_intention',
                                    security: pxl_object.update_checkout_nonce,
                                },
                                complete: function () {
                                    try {
                                        window.dispatchEvent(new Event('updateIntentionData'));
                                    } catch (e) {}
                                    resolve();
                                },
                            });
                        });
                        // Create Woo order with discounted total so order page matches confirmation.
                        if (typeof handleOrderCreation === 'function') {
                            handleOrderCreation();
                        }
                        await new Promise(res => setTimeout(() => res(''), 3000));
                        return true;
                    } else {
                        // Trigger form submission
                        console.log('Classic checkout detected for '+ paymentmethod);

                        const form = jQuery('form.checkout');
                        // Add a custom event listener to capture errors
                        return await new Promise((resolve) => {
                            form.on('checkout_error', function (e, errorMessages) {
                                console.log('Checkout Error:', errorMessages);
                                // Stop form submission
                                form.unblock({ message: null});

                                resolve(false);
                                hideLoadingIndicator();
                                return false;
                            });
                            // If the checkout completes successfully
                            form.on('checkout_place_order_success', function () {
                                console.log('Checkout successful.');
                                //showLoadingIndicator("Please wait while we direct you to Bank's OTP Page .");
                                form.unblock({ message: null});

                                resolve(true);
                                // window.dispatchEvent(new Event('updateIntentionData'));
                                // console.log('updateIntentionData');
 
                                // Trigger the form submission
                                form.submit();
                            });
                        }, 5000);
                    }
                   
                } catch (error) {
                    hideLoadingIndicator();
                    console.log('An unexpected error occurred:', error);
                    return false;
                }

            }

         
            hideLoadingIndicator();
        },
        afterPaymentComplete: async (response) => {
             stopPaymobOtpOverlayGuard();
             hideLoadingIndicator();
            console.info(response);
                
            // Fetch the order ID from the server
            const order = await jQuery.ajax({
                url: pxl_object.ajax_url,
                type: 'POST',
                async:false,
                data: {
                    action: 'get_order_id_from_session',
                    security: pxl_object.update_checkout_nonce,
                },
            });
            // Check if the response contains the order ID
            if (order && order.success === true && order.data && order.data.order_id) {
               console.log('Order ID is available', order);
               var merchant_order_id = order.data.order_id;
            } else {
                console.log('Order ID is not available or invalid:', order);
            }
            console.info(response);
            if(typeof response.data === 'undefined' && typeof response?.data?.data?.redirect_url !== 'undefined'){
                // in case of Oman Net after OTP valid/invalid
                    window.location.href =  response.data.data.redirect_url;// Update the browser's URL
          		return true;
            }else{

 	showLoadingIndicator("Please wait while we process your transaction.");
                // in case of cards / Apple / google
                
                try {
                    if(typeof response.res !== 'undefined'){
                        // Indicate that after-payment processing is complete
                        response.res.data.errmsg =response.res.data?.['data.message']
                        response.res.data.afterpayment = true;
                        response.res.data.merchant_order_id = merchant_order_id;
                        callbackAjaxCall(response.res.data, null);
                    }else{
                        // Indicate that after-payment processing is complete
                        response.data.afterpayment = true;
                        response.data.merchant_order_id = merchant_order_id;
                    }
                    console.log('After Payment Complete');
                    console.log('Response Data:', response);
                    console.log('Response Data:', response.data.error);
                    console.log('Merchant Order ID:', response.data.merchant_order_id);
             
                    console.log('Merchant Order ID:------', response.data.merchant_order_id);

                    // Simulate a delay (if necessary)
                    await new Promise((res) => setTimeout(res, 5000));
                    // If merchant_order_id is set, proceed with the callback
                    if (response.data.merchant_order_id  && (response.data['success'] == 'true' && response.data['pending'] == 'false') 
                        || (response.data['success'] == 'false' && response.data['pending'] == 'false')) {
                        console.log('before callbackAjaxCall function');
                        callbackAjaxCall(response.data, null);
                        return true;
                    } 
		if(typeof response.data.redirect_url !== 'undefined'){
	                        console.log('inside URL ', response.data.redirect_url);
		     // in case of Oman Net after OTP valid/invalid
	         		window.location.href =  response.data.redirect_url;// Update the browser's URL
			return true;
          		}
                    // Handle error messages if present
                    const errorMessage = response.data.error || response.data?.['data.message'];

                    if (errorMessage !== null && errorMessage !== 'undefined') {
                        console.log('inside errorMessage' + errorMessage);
                        response.data.errmsg = errorMessage;

                        // Bug 1: clear discounted session before checkout reload so retry base = cart.
                        if (typeof pxl_object !== 'undefined' && pxl_object && pxl_object.ajax_url) {
                            jQuery.post(pxl_object.ajax_url, {
                                action: 'paymob_clear_discount',
                                security: pxl_object.update_checkout_nonce,
                                invalidate_intention: 1,
                            });
                        }
                        window.paymobDiscountApplied = false;
                        window.paymobTotalsSignature = null;
                        window.paymobActiveClientSecret = null;

                        const redirectUrl = new URL(window.location);
            
                        // Remove existing gateway error parameter if present
                        if (redirectUrl.searchParams.has('gatewayerror')) {
                            redirectUrl.searchParams.delete('gatewayerror');
                            window.history.pushState({}, '', redirectUrl); // Update the browser's URL
                        }
                        console.log('before callbackAjaxCall function with gatewayerror');

                        // Add the new gateway error parameter and handle the error
                        redirectUrl.searchParams.set('gatewayerror', errorMessage);
                        displayWooCommerceError(errorMessage);
                        callbackAjaxCall(response.data, redirectUrl.toString());
            
                        return false; // Stop further processing
                    }else {
                        console.log('Merchant Order ID is null or undefined.');
                    }
                    hideLoadingIndicator();
                } catch (error) {
                    // Handle any unexpected errors
                    console.log('Error during afterPaymentComplete:', error);
                    if (typeof resetPaymobPixelAfterPaymentFailure === 'function') {
                        resetPaymobPixelAfterPaymentFailure();
                    }
                    hideLoadingIndicator();

                }
            }
        },
        
        onPaymentCancel: (response) => {
            stopPaymobOtpOverlayGuard();
            hideLoadingIndicator();
            console.log('Payment has been canceled');
            if (typeof resetPaymobPixelAfterPaymentFailure === 'function') {
                resetPaymobPixelAfterPaymentFailure();
            }
            response.data.afterpayment = true;
            callbackAjaxCall(response.data, null);
        }, 
        cardValidationChanged: (isValid) => {
            console.log(isValid);
            window.isCardValid = isValid === 'true' || isValid === true;
            updatePlaceOrderVisibility();
            constrainPaymobPixelLayout();
        },   
        customStyle: customArr,
    });
    schedulePaymobLayoutFix();
}

function constrainPaymobPixelLayout() {
    const container = document.getElementById('paymob-elements');
    if (!container) {
        return;
    }

    container.style.setProperty('max-width', '100%', 'important');
    container.style.setProperty('width', '100%', 'important');
    container.style.setProperty('overflow-x', 'auto', 'important');
    container.style.setProperty('overflow-y', 'visible', 'important');

    const accordion = container.closest('.wc-block-components-radio-control-accordion-content');
    if (accordion) {
        accordion.style.maxWidth = '100%';
        accordion.style.overflowX = 'auto';
    }

    Array.from(container.children).forEach(function (child) {
        child.style.maxWidth = '100%';
        child.style.boxSizing = 'border-box';

        if (container.clientWidth > 0 && child.scrollWidth > container.clientWidth) {
            child.style.width = '100%';
            child.style.overflowX = 'auto';
        }
    });
}

function schedulePaymobLayoutFix() {
    [100, 500, 1200, 2500].forEach(function (delay) {
        window.setTimeout(constrainPaymobPixelLayout, delay);
    });
}

function getSelectedPaymentMethodId() {
    let storeMethod = '';

    if (typeof wp !== 'undefined' && wp.data) {
        try {
            storeMethod = wp.data.select('wc/store/payment').getActivePaymentMethod() || '';
        } catch (err) {
            storeMethod = '';
        }
    }

    const blocksInput = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
    const domBlocksMethod = blocksInput && blocksInput.value ? blocksInput.value : '';

    const classicInput = document.querySelector('input[name="payment_method"]:checked');
    const classicMethod = classicInput && classicInput.value ? classicInput.value : '';

    if (isBankInstallmentPaymentMethod(storeMethod)) {
        return storeMethod;
    }
    if (isBankInstallmentPaymentMethod(domBlocksMethod)) {
        return domBlocksMethod;
    }
    if (isBankInstallmentPaymentMethod(classicMethod)) {
        return classicMethod;
    }

    return domBlocksMethod || storeMethod || classicMethod || '';
}

function isBankInstallmentPaymentMethod(methodId) {
    return typeof methodId === 'string' && methodId.indexOf('bank-installments') !== -1;
}

function isPixelPaymentMethod(methodId) {
    return methodId === 'paymob-pixel';
}

function shouldPreserveBankInstallmentSelection() {
    if (typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout.preselect) {
        return true;
    }

    if (new URLSearchParams(window.location.search).has('paymob_aw_preselect')) {
        return true;
    }

    return false;
}

function ensureBankInstallmentPreselection() {
    if (!shouldPreserveBankInstallmentSelection()) {
        return false;
    }

    if (typeof window.paymobAwAttemptBankPreselect === 'function') {
        const selected = !!window.paymobAwAttemptBankPreselect();
        if (selected) {
            updatePlaceOrderVisibility();
        }
        return selected;
    }

    const config = window.paymobAffordabilityCheckout || {};
    const gatewayId = config.gatewayId || '';

    if (gatewayId && expandBlocksPaymentMethod(gatewayId)) {
        setBlocksActivePaymentMethod(gatewayId);
        updatePlaceOrderVisibility();
        return true;
    }

    const bankInput = document.querySelector('input[name="radio-control-wc-payment-method-options"][value*="bank-installments"]');
    if (bankInput && bankInput.value) {
        expandBlocksPaymentMethod(bankInput.value);
        setBlocksActivePaymentMethod(bankInput.value);
        updatePlaceOrderVisibility();
        return true;
    }

    return false;
}

function scheduleBankInstallmentPreselection() {
    [500, 1200, 2500, 4000].forEach(function (delay) {
        window.setTimeout(function () {
            ensureBankInstallmentPreselection();
        }, delay);
    });
}

function getBlocksCheckoutContext() {
    if (typeof wp === 'undefined' || !wp.data) {
        return null;
    }

    try {
        const { select } = wp.data;
        const cartStore = select('wc/store/cart');
        if (!cartStore) {
            return null;
        }
        const cartTotals = cartStore.getCartTotals();
        const totalAmount = (parseInt(cartTotals.total_price, 10) /
            10 ** cartTotals.currency_minor_unit);

        return {
            billingData: cartStore.getCustomerData().billingAddress,
            totalAmount: totalAmount
        };
    } catch (err) {
        return null;
    }
}

function getStoreApiCartUrl() {
    if (window.wc && window.wc.wcSettings && window.wc.wcSettings.storeApiUrl) {
        const url = String(window.wc.wcSettings.storeApiUrl);
        if (url.indexOf('/cart') !== -1) {
            return url;
        }
        return url.replace(/\/$/, '') + '/cart';
    }

    const root = (window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : '/wp-json/';
    return root.replace(/\/$/, '') + '/wc/store/v1/cart';
}

function setBlocksActivePaymentMethod(methodId) {
    if (!methodId || typeof wp === 'undefined' || !wp.data) {
        return false;
    }

    try {
        const dispatch = wp.data.dispatch('wc/store/payment');
        if (dispatch && typeof dispatch.__internalSetActivePaymentMethod === 'function') {
            dispatch.__internalSetActivePaymentMethod(methodId, {});
            return true;
        }
    } catch (err) {
        return false;
    }

    return false;
}

function tryInitializeBlocksPixel() {
    const methodId = getSelectedPaymentMethodId();
    if (shouldPreserveBankInstallmentSelection() && !isPixelPaymentMethod(methodId)) {
        return;
    }
    // Failure reset owns the only remount after gatewayerror.
    if (window.paymobPixelFailureResetInProgress || window.paymobSkipScheduledActivation) {
        if (!window.paymobPixelFailureRemountDone) {
            return;
        }
    }
    if (!jQuery('#paymob-elements').length) {
        return;
    }
    if (isPaymobPixelMounted()) {
        return;
    }
    if (window.paymobPixelAjaxInFlight) {
        return;
    }

    const ctx = getBlocksCheckoutContext();
    if (!ctx) {
        return;
    }

    showLoadingMessage();
    // ajaxCall owns the in-flight lock (do not set it here).
    ajaxCall(ctx.billingData, ctx.totalAmount, false);
}

function isBlocksPaymentPanelOpen(methodId) {
    const content = document.querySelector('.wc-block-components-radio-control-accordion-content[data-payment-method="' + methodId + '"]');
    if (!content) {
        return false;
    }

    return content.classList.contains('is-open') || content.offsetHeight > 0;
}

function expandBlocksPaymentMethod(methodId) {
    const input = document.querySelector('input[name="radio-control-wc-payment-method-options"][value="' + methodId + '"]');
    if (!input) {
        return false;
    }

    setBlocksActivePaymentMethod(methodId);

    if (!input.checked) {
        input.click();
    } else if (!isBlocksPaymentPanelOpen(methodId)) {
        const label = document.querySelector('label[for="' + input.id + '"]');
        if (label) {
            label.click();
        } else {
            input.click();
        }
    }

    const content = document.querySelector('.wc-block-components-radio-control-accordion-content[data-payment-method="' + methodId + '"]');
    if (content) {
        content.classList.add('is-open');
        content.style.removeProperty('display');
    }

    return true;
}

function openBlocksPaymentAccordion(methodId) {
    return expandBlocksPaymentMethod(methodId);
}

function activateBlocksPixelCheckout() {
    const methodId = getSelectedPaymentMethodId();
    if (shouldPreserveBankInstallmentSelection() && !isPixelPaymentMethod(methodId)) {
        return false;
    }

    const paymentMethod = 'paymob-pixel';
    const paymentClassicInput = jQuery('#payment_method_' + paymentMethod);

    if (!document.querySelector('.wc-block-checkout')) {
        if (paymentClassicInput.length && !paymentClassicInput.is(':checked')) {
            paymentClassicInput.prop('checked', true).trigger('change');
        }
        return paymentClassicInput.length > 0;
    }

    if (!expandBlocksPaymentMethod(paymentMethod)) {
        return false;
    }

    window.setTimeout(function () {
        tryInitializeBlocksPixel();
    }, 400);

    return true;
}

function ensureBlocksPixelPanelOpen() {
    const methodId = getSelectedPaymentMethodId();
    if (shouldPreserveBankInstallmentSelection() && !isPixelPaymentMethod(methodId)) {
        return;
    }
    if (!document.querySelector('.wc-block-checkout')) {
        return;
    }

    const selected = getSelectedPaymentMethodId();
    if (selected && selected !== 'paymob-pixel') {
        return;
    }

    expandBlocksPaymentMethod('paymob-pixel');
    window.setTimeout(function () {
        tryInitializeBlocksPixel();
    }, 400);
}

function scheduleBlocksPixelActivation() {
    // One delayed activation only — previous [500,1200,2200] caused 3 intentions.
    if (window.paymobSkipScheduledActivation) {
        return;
    }
    window.setTimeout(function () {
        if (window.paymobSkipScheduledActivation || window.paymobActiveClientSecret) {
            return;
        }
        activateBlocksPixelCheckout();
    }, 600);
}

function isPaymobPixelMounted() {
    const container = jQuery('#paymob-elements');
    if (!container.length || container.children().length === 0) {
        return false;
    }
    const text = (container.text() || '').trim();
    if (text.indexOf('Loading payments') === 0) {
        return false;
    }
    return !!window.paymobActiveClientSecret;
}

function isWidgetPreselectGateway(methodId) {
    const config = window.paymobAffordabilityCheckout || {};
    return !!config.preselect && !!config.gatewayId && methodId === config.gatewayId;
}

function shouldSkipPixelCheckoutSideEffects() {
    const methodId = getSelectedPaymentMethodId();

    // Shopper explicitly chose Pixel — load it normally even during widget preselect.
    if (isPixelPaymentMethod(methodId)) {
        return false;
    }

    if (shouldPreserveBankInstallmentSelection()) {
        return true;
    }

    return isBankInstallmentPaymentMethod(methodId) || isWidgetPreselectGateway(methodId);
}

function releaseWidgetPreselectForPixelSelection() {
    if (typeof window.paymobAffordabilityCheckout === 'object' && window.paymobAffordabilityCheckout) {
        window.paymobAffordabilityCheckout.preselect = false;
    }

    stopBlocksPlaceOrderGuard();
    document.body.classList.remove('paymob-aw-show-place-order');
}

function enableBlocksPlaceOrderButton() {
    const selectors = [
        '.wc-block-components-checkout-place-order-button',
        '.wc-block-checkout__actions .wc-block-components-button',
        '.wc-block-checkout__form .wc-block-components-button'
    ];

    selectors.forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('disabled');
            button.removeAttribute('aria-disabled');
            button.classList.remove('wc-block-components-checkout-place-order-button--loading');
            button.style.pointerEvents = 'auto';
            button.style.cursor = 'pointer';
            button.style.display = 'flex';
            button.style.alignItems = 'center';
            button.style.justifyContent = 'center';
            button.style.width = '100%';
            button.style.textAlign = 'center';
        });
    });
}

function isBlocksPlaceOrderButtonClickable() {
    const selectors = [
        '.wc-block-components-checkout-place-order-button',
        '.wc-block-checkout__actions .wc-block-components-button',
        '.wc-block-checkout__form .wc-block-components-button'
    ];

    for (let i = 0; i < selectors.length; i++) {
        const element = document.querySelector(selectors[i]);
        if (!element) {
            continue;
        }

        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);

        if (
            rect.height > 0
            && rect.width > 0
            && style.display !== 'none'
            && style.visibility !== 'hidden'
            && parseFloat(style.opacity) > 0
            && !element.disabled
            && element.getAttribute('aria-disabled') !== 'true'
        ) {
            return true;
        }
    }

    return false;
}

function triggerBlocksPlaceOrderClick(button) {
    if (!button) {
        return false;
    }

    button.disabled = false;
    button.removeAttribute('disabled');
    button.removeAttribute('aria-disabled');
    button.classList.remove('wc-block-components-checkout-place-order-button--loading');

    const eventOptions = { bubbles: true, cancelable: true, view: window };
    button.dispatchEvent(new PointerEvent('pointerdown', eventOptions));
    button.dispatchEvent(new MouseEvent('mousedown', eventOptions));
    button.dispatchEvent(new MouseEvent('mouseup', eventOptions));
    button.dispatchEvent(new MouseEvent('click', eventOptions));
    return true;
}

function bindBlocksPlaceOrderClickFallback() {
    if (window.paymobAwBlocksPlaceOrderClickBound || !document.querySelector('.wc-block-checkout')) {
        return;
    }

    window.paymobAwBlocksPlaceOrderClickBound = true;

    document.addEventListener('click', function (event) {
        if (!shouldForceBlocksPlaceOrderVisible()) {
            return;
        }

        const button = event.target.closest('.wc-block-components-checkout-place-order-button, .wc-block-checkout__actions .wc-block-components-button, .wc-block-checkout__form .wc-block-components-button');
        if (!button) {
            return;
        }

        const methodId = getSelectedPaymentMethodId();
        if (!isBankInstallmentPaymentMethod(methodId) && !isWidgetPreselectGateway(methodId)) {
            return;
        }

        hideLoadingIndicator();

        if (!button.disabled && button.getAttribute('aria-disabled') !== 'true') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        triggerBlocksPlaceOrderClick(button);
    }, true);
}

function shouldForceBlocksPlaceOrderVisible() {
    if (!document.querySelector('.wc-block-checkout')) {
        return false;
    }

    const methodId = getSelectedPaymentMethodId();
    if (isPixelPaymentMethod(methodId)) {
        return false;
    }

    // Widget Buy Now + Pixel: keep Place Order visible while preselect is active,
    // even if Blocks still has paymob-pixel as the interim default payment method.
    if (shouldPreserveBankInstallmentSelection()) {
        return true;
    }

    const config = window.paymobAffordabilityCheckout || {};
    const targetGateway = config.gatewayId || '';

    if (isBankInstallmentPaymentMethod(methodId) || isWidgetPreselectGateway(methodId)) {
        return true;
    }

    return isBankInstallmentPaymentMethod(targetGateway) || isWidgetPreselectGateway(targetGateway);
}

function unhideBlocksCheckoutActions() {
    const containerSelectors = [
        '.wc-block-checkout__actions',
        '.wp-block-woocommerce-checkout-actions-block',
        '.wc-block-checkout__sidebar'
    ];

    containerSelectors.forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
            element.style.setProperty('display', 'block', 'important');
            element.style.setProperty('visibility', 'visible', 'important');
            element.style.setProperty('opacity', '1', 'important');
            element.removeAttribute('hidden');
            element.removeAttribute('aria-hidden');
        });
    });
}

function setPlaceOrderButtonsVisible(visible) {
    const forceVisible = shouldForceBlocksPlaceOrderVisible();

    if (!visible && forceVisible) {
        visible = true;
    }

    const placeOrderSelectors = [
        '.wc-block-checkout__actions .wc-block-components-checkout-place-order-button',
        '.wp-block-woocommerce-checkout-actions-block .wc-block-components-checkout-place-order-button',
        '.wc-block-checkout__form .wc-block-components-button',
        '.wc-block-components-checkout-place-order-button',
        '.wc-block-checkout__form .wp-block-button__link',
        '.wc-block-checkout__form button[type="submit"]',
        '#place_order'
    ];

    if (visible && document.querySelector('.wc-block-checkout')) {
        document.body.classList.add('paymob-aw-show-place-order');
        unhideBlocksCheckoutActions();
    } else if (!visible) {
        document.body.classList.remove('paymob-aw-show-place-order');
    }

    placeOrderSelectors.forEach(function (selector) {
        document.querySelectorAll(selector).forEach(function (element) {
            if (visible) {
                const isBlocksPlaceOrderButton = element.classList.contains('wc-block-components-checkout-place-order-button')
                    || element.classList.contains('wc-block-components-button');

                if (isBlocksPlaceOrderButton) {
                    element.style.setProperty('display', 'flex', 'important');
                    element.style.setProperty('align-items', 'center', 'important');
                    element.style.setProperty('justify-content', 'center', 'important');
                    element.style.setProperty('width', '100%', 'important');
                    element.style.setProperty('text-align', 'center', 'important');
                } else {
                    element.style.setProperty('display', 'block', 'important');
                }

                element.style.setProperty('visibility', 'visible', 'important');
                element.style.setProperty('opacity', '1', 'important');
                element.removeAttribute('hidden');
                element.removeAttribute('aria-hidden');
                element.disabled = false;
                element.removeAttribute('disabled');
                element.removeAttribute('aria-disabled');
                element.classList.remove('wc-block-components-checkout-place-order-button--loading');
                element.style.pointerEvents = 'auto';
                jQuery(element).show().prop('disabled', false);
            } else if (!forceVisible) {
                element.style.display = 'none';
                element.disabled = true;
                jQuery(element).hide().prop('disabled', true);
            }
        });
    });

    if (visible) {
        enableBlocksPlaceOrderButton();
        bindBlocksPlaceOrderClickFallback();
    }
}

function isBlocksPlaceOrderButtonVisible() {
    const selectors = [
        '.wc-block-components-checkout-place-order-button',
        '.wc-block-checkout__actions .wc-block-components-button',
        '.wc-block-checkout__form .wc-block-components-button'
    ];

    for (let i = 0; i < selectors.length; i++) {
        const element = document.querySelector(selectors[i]);
        if (!element) {
            continue;
        }

        const rect = element.getBoundingClientRect();
        const style = window.getComputedStyle(element);

        if (
            rect.height > 0
            && rect.width > 0
            && style.display !== 'none'
            && style.visibility !== 'hidden'
            && parseFloat(style.opacity) > 0
        ) {
            return true;
        }
    }

    return false;
}

var blocksPlaceOrderGuardTimer = null;
var blocksPlaceOrderGuardObserver = null;

function stopBlocksPlaceOrderGuard() {
    if (blocksPlaceOrderGuardTimer) {
        window.clearInterval(blocksPlaceOrderGuardTimer);
        blocksPlaceOrderGuardTimer = null;
    }

    if (blocksPlaceOrderGuardObserver) {
        blocksPlaceOrderGuardObserver.disconnect();
        blocksPlaceOrderGuardObserver = null;
    }
}

function startBlocksPlaceOrderGuard() {
    if (!document.querySelector('.wc-block-checkout') || !shouldPreserveBankInstallmentSelection()) {
        return;
    }

    function guard() {
        if (!shouldPreserveBankInstallmentSelection()) {
            stopBlocksPlaceOrderGuard();
            return;
        }

        if (shouldForceBlocksPlaceOrderVisible()) {
            setPlaceOrderButtonsVisible(true);
            enableBlocksPlaceOrderButton();
            hideLoadingIndicator();
        }

        if (isBlocksPlaceOrderButtonClickable()) {
            stopBlocksPlaceOrderGuard();
        }
    }

    guard();

    if (blocksPlaceOrderGuardTimer) {
        return;
    }

    blocksPlaceOrderGuardTimer = window.setInterval(guard, 250);

    const checkoutRoot = document.querySelector('.wc-block-checkout');
    if (checkoutRoot && window.MutationObserver) {
        blocksPlaceOrderGuardObserver = new MutationObserver(guard);
        blocksPlaceOrderGuardObserver.observe(checkoutRoot, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style', 'class', 'hidden', 'aria-hidden', 'disabled']
        });
    }

    window.setTimeout(stopBlocksPlaceOrderGuard, shouldPreserveBankInstallmentSelection() ? 120000 : 30000);
}

function updatePlaceOrderVisibility() {
    const methodId = getSelectedPaymentMethodId();

    if (isPixelPaymentMethod(methodId)) {
        if (window.isValid && window.isCardValid) {
            setPlaceOrderButtonsVisible(true);
        } else {
            setPlaceOrderButtonsVisible(false);
        }
        return;
    }

    if (isBankInstallmentPaymentMethod(methodId) || isWidgetPreselectGateway(methodId)) {
        hideLoadingIndicator();
        setPlaceOrderButtonsVisible(true);
        startBlocksPlaceOrderGuard();
        bindBlocksPlaceOrderClickFallback();
        return;
    }

    if (shouldPreserveBankInstallmentSelection() && document.querySelector('.wc-block-checkout')) {
        hideLoadingIndicator();
        setPlaceOrderButtonsVisible(true);
        startBlocksPlaceOrderGuard();
        bindBlocksPlaceOrderClickFallback();
        return;
    }

    if (shouldForceBlocksPlaceOrderVisible()) {
        setPlaceOrderButtonsVisible(true);
        startBlocksPlaceOrderGuard();
        return;
    }

    if (methodId && !isPixelPaymentMethod(methodId)) {
        setPlaceOrderButtonsVisible(!!window.isValid);
        return;
    }

    if (window.isValid && window.isCardValid) {
        setPlaceOrderButtonsVisible(true);
    } else if (!shouldPreserveBankInstallmentSelection()) {
        setPlaceOrderButtonsVisible(false);
    }
}

window.updatePlaceOrderVisibility = updatePlaceOrderVisibility;
window.setPlaceOrderButtonsVisible = setPlaceOrderButtonsVisible;
window.startBlocksPlaceOrderGuard = startBlocksPlaceOrderGuard;
window.enableBlocksPlaceOrderButton = enableBlocksPlaceOrderButton;
window.bindBlocksPlaceOrderClickFallback = bindBlocksPlaceOrderClickFallback;
window.hideLoadingIndicator = hideLoadingIndicator;
window.startPaymobOtpOverlayGuard = startPaymobOtpOverlayGuard;
window.stopPaymobOtpOverlayGuard = stopPaymobOtpOverlayGuard;
window.releaseWidgetPreselectForPixelSelection = releaseWidgetPreselectForPixelSelection;
window.shouldPreserveBankInstallmentSelection = shouldPreserveBankInstallmentSelection;
window.ensureBlocksPixelPanelOpen = ensureBlocksPixelPanelOpen;
window.activateBlocksPixelCheckout = activateBlocksPixelCheckout;
window.tryInitializeBlocksPixel = tryInitializeBlocksPixel;
window.expandBlocksPaymentMethod = expandBlocksPaymentMethod;
window.constrainPaymobPixelLayout = constrainPaymobPixelLayout;



function validateClassicFrom(){
window.hasEmptyFields = false;
jQuery('.woocommerce-billing-fields input[aria-required], .woocommerce-billing-fields select[aria-required]').each(function () {
  if (jQuery(this).val().trim() === '') {
      window.hasEmptyFields = true;
      jQuery(this).css('border', '1px solid red'); // Highlight the empty field
      return window.hasEmptyFields;
  }
});
// Check if #terms exists on the page
if (jQuery('#terms').length) {
    // Check if it's an input of type checkbox
    if (jQuery('#terms').is('input[type="checkbox"]')) {
        if (!jQuery('#terms').is(':checked')) {
            hasEmptyFields = true;
            jQuery('#terms').closest('label').css('color', 'red'); // Highlight terms label
        } else {
            jQuery('#terms').closest('label').css('color', ''); // Clear highlight
        }
    }
}

return window.hasEmptyFields;
}
function shouldInitializePaymobElement(forceReload) {
    if (forceReload) {
        return true;
    }

    const container = jQuery('#paymob-elements');
    if (!container.length || container.children().length === 0) {
        return true;
    }

    const text = container.text().trim();
    return text.indexOf('Loading payments') === 0;
}

function ajaxCall(billingData, totalAmount, forcereload = false, options = {}) {
    if (shouldSkipPixelCheckoutSideEffects()) {
        hideLoadingIndicator();
        return;
    }

    const opts = options || {};
    // After successful pay → thank-you, next checkout must POST a fresh intention.
    try {
        if (sessionStorage.getItem('paymob_pixel_force_new') === '1') {
            opts.forceNew = true;
            sessionStorage.removeItem('paymob_pixel_force_new');
        }
    } catch (e) {}
    const resetDiscount = !!opts.resetDiscount;
    const forceNew = !!opts.forceNew;

    // During gatewayerror recovery, only the failure remount (resetDiscount) may call update_pixel_data.
    if (window.paymobPixelFailureResetInProgress && !resetDiscount && !window.paymobPixelFailureRemountDone) {
        hideLoadingIndicator();
        return;
    }

    // Already mounted successfully — ignore extra remount requests (causes reload loop).
    if (!forceNew && !resetDiscount && window.paymobActiveClientSecret && isPaymobPixelMounted()) {
        hideLoadingIndicator();
        return;
    }

    // Serialize calls — parallel POSTs created 3 different client secrets.
    if (window.paymobPixelAjaxInFlight) {
        // Prefer keeping the first request; only replace pending with force/reset.
        if (forceNew || resetDiscount || !window.paymobPixelPendingReload) {
            window.paymobPixelPendingReload = {
                billingData: billingData,
                totalAmount: totalAmount,
                forcereload: forcereload,
                options: opts,
            };
        }
        return;
    }
    window.paymobPixelAjaxInFlight = true;

    console.log("billingData", billingData);
    console.log("totalAmount", totalAmount);

    const needsInit = shouldInitializePaymobElement(forcereload) || resetDiscount || forceNew || forcereload;
    if (!needsInit && isPaymobPixelMounted()) {
        window.paymobPixelAjaxInFlight = false;
        hideLoadingIndicator();
        return;
    }

    if (forceNew || resetDiscount || forcereload || !isPaymobPixelMounted()) {
        showLoadingMessage(true);
    }

    jQuery.ajax({
        url: pxl_object.ajax_url,
        type: 'POST',
        data: {
            action: 'update_pixel_data',
            security: pxl_object.update_checkout_nonce,
            billing_data: billingData,
            total_amount: totalAmount,
            reset_discount: resetDiscount ? 1 : 0,
            force_new: forceNew ? 1 : 0,
        },
        success: function (response) {
            console.log('Checkout data updated:', response);
            if (response.success && response.data && response.data.cs) {
                // Reused intention + already mounted: do not remount Pixel SDK.
                if (response.data.reused && window.paymobActiveClientSecret === response.data.cs && isPaymobPixelMounted()) {
                    hideLoadingIndicator();
                    return;
                }
                initializePaymobElement(pxl_object.key, response.data.cs);
                hideLoadingIndicator();
            } else {
                displayWooCommerceError(response.data);
                hideLoadingIndicator();
            }
        },
        error: function () {
            hideLoadingIndicator();
        },
        complete: function () {
            window.paymobPixelAjaxInFlight = false;
            if (window.paymobPixelPendingReload) {
                const pending = window.paymobPixelPendingReload;
                window.paymobPixelPendingReload = null;
                // Drop queued remounts once Pixel has a client secret (stops reload loop).
                if (window.paymobActiveClientSecret || isPaymobPixelMounted()) {
                    hideLoadingIndicator();
                    return;
                }
                ajaxCall(pending.billingData, pending.totalAmount, false, pending.options || {});
            }
        }
    });
}

/**
 * Bug 1: after failed payment redirect, clear discount session and remount ONCE from cart.
 * Avoid force_new spam — that caused "Already being processed" + many Pixel reloads.
 */
function resetPaymobPixelAfterPaymentFailure() {
    if (window.paymobPixelFailureAlreadyReset) {
        return;
    }
    window.paymobPixelFailureAlreadyReset = true;
    window.paymobPixelFailureResetInProgress = true;
    window.paymobSkipScheduledActivation = true;
    window.paymobPixelPendingReload = null;
    window.paymobDiscountApplied = false;
    window.paymobTotalsSignature = null;
    window.paymobActiveClientSecret = null;
    window.previousTotal = null;
    window.previousTotalBlock = null;

    // Remove gatewayerror from the URL so refresh/history does not re-trigger reset.
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('gatewayerror')) {
            url.searchParams.delete('gatewayerror');
            window.history.replaceState({}, '', url.pathname + url.search + url.hash);
        }
    } catch (err) {}

    document.querySelectorAll(
        '.paymob-discount-line, .paymob-instant-refund-line, .paymob-discount-row, .paymob-instant-refund-row'
    ).forEach(function (el) {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    });

    const remountOnce = function () {
        if (window.paymobPixelFailureRemountDone) {
            window.paymobPixelFailureResetInProgress = false;
            return;
        }
        window.paymobPixelFailureRemountDone = true;
        const ctx = (typeof getBlocksCheckoutContext === 'function') ? getBlocksCheckoutContext() : null;
        if (ctx) {
            // resetDiscount clears stale discount; do NOT force_new (avoids intention churn).
            ajaxCall(ctx.billingData, ctx.totalAmount, true, { resetDiscount: true });
            window.paymobPixelFailureResetInProgress = false;
            // Allow later user-driven activates; keep FailureAlreadyReset so we do not loop.
            window.paymobSkipScheduledActivation = false;
            return;
        }
        if (typeof updateCheckoutData === 'function') {
            window.paymobPixelNextUpdateOptions = { resetDiscount: true };
            updateCheckoutData(true);
        }
        window.paymobPixelFailureResetInProgress = false;
        window.paymobSkipScheduledActivation = false;
    };

    if (typeof pxl_object !== 'undefined' && pxl_object && pxl_object.ajax_url) {
        jQuery.post(pxl_object.ajax_url, {
            action: 'paymob_clear_discount',
            security: pxl_object.update_checkout_nonce,
            invalidate_intention: 1,
        }).always(function () {
            remountOnce();
            // Failsafe: never leave overlay stuck.
            window.setTimeout(function () {
                hideLoadingIndicator();
            }, 8000);
        });
        return;
    }
    remountOnce();
}

window.resetPaymobPixelAfterPaymentFailure = resetPaymobPixelAfterPaymentFailure;

function callbackAjaxCall(data, url = null) {
    console.info(' callbackAjaxCall ', data);
    console.info(' callbackAjaxCall url ', url);
    jQuery.ajax({
        url: pxl_object.callback,
        type: 'GET',
        async: false,
        data: data,
        success: function (response) {
            if (response.success) {
                console.log('callbackAjaxCall success')
                // Paid intention must not stay mounted for the next checkout / Place Order.
                window.paymobActiveClientSecret = null;
                window.paymobDiscountApplied = false;
                window.paymobTotalsSignature = null;
                window.paymobCreateOrderInFlight = false;
                try {
                    sessionStorage.setItem('paymob_pixel_force_new', '1');
                } catch (e) {}
                window.location.href = response.data.url;
            }
        }
    });
    if(url !== null){
        console.log('callbackAjaxCall url !=null')
        window.location.href = url;
    }
}
function showLoadingMessage(reload = false){
     if(jQuery("#paymob-elements").children().length == 0 || reload == true){
        jQuery("#paymob-elements").html(
            `Loading payments, Please wait..`
        );
    }
}
window.previousTotal = null;
jQuery(document).ready(function ($) {
   
    if (!shouldSkipPixelCheckoutSideEffects()) {
        showLoadingMessage();
    } else {
        hideLoadingIndicator();
    }

    bindBlocksPlaceOrderClickFallback();
    $(document).on('updated_checkout', function () {
        // Get the current total amount        
        var totalElement = jQuery('.order-total .amount').text().replace(/[^0-9.]/g, '');
        if(totalElement == null){
            var totalElement = jQuery('#order_review').find('.order-total .woocommerce-Price-amount').text(); // Extract total amount
        }

        // const totalElement = $('.order-total .woocommerce-Price-amount');
        if (totalElement.length) {

            const currentTotal = (totalElement.replace(/[^0-9.]/g, ''));
             
            if (window.previousTotal !== null && window.previousTotal !== currentTotal) {

              //showLoadingIndicator("Loading Checkout. Please wait.");
                console.log('Checkout total has changed.');
                console.log('Previous Total:', window.previousTotal);
                console.log('Current Total:', currentTotal);

                window.paymobDiscountApplied = false;
                window.paymobTotalsSignature = null;
                window.paymobActiveClientSecret = null;
                window.paymobPixelNextUpdateOptions = { resetDiscount: true };
                updateCheckoutData(true);
            }

            // Update the previous total
            window.previousTotal = currentTotal;
        }

    });
    
    const totalElementSelector = '.wc-block-components-totals-item__value'; // Adjust the selector based on your theme
    window.previousTotalBlock = null;
   
    setInterval(async() => {
        if (shouldSkipPixelCheckoutSideEffects()) {
            hideLoadingIndicator();
            return;
        }

        const totalBlockElement = $(totalElementSelector);
        
        if (totalBlockElement.length) {
            const { select } = wp.data;
            const cartStore = select('wc/store/cart');
            const cartTotals = cartStore.getCartTotals();
            const totalAmount = (parseInt(cartTotals.total_price, 10) /
            10 ** cartTotals.currency_minor_unit);
            const currentBlockTotal = totalAmount;
           
            if (window.previousTotalBlock !== null && window.previousTotalBlock !== currentBlockTotal) {
                console.log('Checkout total has changed.');
                console.log('Previous Total:', previousTotalBlock);
                console.log('Current Total:', currentBlockTotal);

                // Update the previous total
                window.previousTotalBlock = currentBlockTotal;

                if (window.paymobPixelFailureResetInProgress) {
                    return;
                }

                const billingData = cartStore.getCustomerData().billingAddress;

                    showLoadingMessage(true);
                    window.paymobDiscountApplied = false;
                    window.paymobTotalsSignature = null;
                    window.paymobActiveClientSecret = null;
                    ajaxCall(billingData, totalAmount, true, { resetDiscount: true });

            }

            if (window.previousTotalBlock === null) {
                window.previousTotalBlock = currentBlockTotal; // Initialize on the first check
            }
        }
    }, 200); // Check every 1000 milliseconds



    const hasGatewayError = (function () {
        try {
            return !!new URLSearchParams(window.location.search).get('gatewayerror');
        } catch (e) {
            return false;
        }
    })();

    // On gatewayerror, reset owns a single remount — skip scheduled activation to avoid reload loops.
    if (!shouldPreserveBankInstallmentSelection() && !hasGatewayError) {
        scheduleBlocksPixelActivation();
    }

    if (hasGatewayError && typeof resetPaymobPixelAfterPaymentFailure === 'function') {
        window.paymobSkipScheduledActivation = true;
        resetPaymobPixelAfterPaymentFailure();
    }
});


function showLoadingIndicator(message = "Processing, please wait...") {
    if (shouldSkipPixelCheckoutSideEffects()) {
        return;
    }

    // Never cover an active OTP / 3DS challenge.
    if (isPaymobOtpChallengeVisible()) {
        hideLoadingIndicator();
        return;
    }

    hideLoadingIndicator();

    const loadingContainer = document.createElement('div');
    loadingContainer.id = 'paymob-loading-indicator';
    loadingContainer.style.position = 'fixed';
    loadingContainer.style.top = '0';
    loadingContainer.style.left = '0';
    loadingContainer.style.width = '100%';
    loadingContainer.style.height = '100%';
    loadingContainer.style.backgroundColor = 'rgba(0, 0, 0, 0.7)'; // Semi-transparent dark background
    loadingContainer.style.display = 'flex';
    loadingContainer.style.flexDirection = 'column';
    loadingContainer.style.justifyContent = 'center';
    loadingContainer.style.alignItems = 'center';
    // Keep below Paymob Pixel OTP / 3DS modals (they typically use 10000+).
    loadingContainer.style.zIndex = '9990';
    loadingContainer.style.pointerEvents = 'none';

    loadingContainer.innerHTML = `
        <div style="
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            padding: 20px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        ">
            <div class="loading-spinner" style="
                width: 50px; 
                height: 50px; 
                border: 5px solid #f3f3f3; 
                border-top: 5px solid #007bff; 
                border-radius: 50%; 
                animation: spin 1s linear infinite;">
            </div>
            <p style="
                margin-top: 15px; 
                font-family: Arial, sans-serif; 
                font-size: 16px; 
                color: #333;
                text-align: center;">
                ${message}
            </p>
        </div>
    `;
    document.body.appendChild(loadingContainer);

    // Add spinner animation and dark overlay fade-in
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        #paymob-loading-indicator {
            animation: fadeIn 0.3s ease-out;
        }
    `;
    document.head.appendChild(style);
}

function hideLoadingIndicator() {
    const loadingContainer = document.getElementById('paymob-loading-indicator');
    if (loadingContainer) {
        loadingContainer.remove();
    }
}

/**
 * Detect Paymob Pixel OTP / 3DS UI so we never stack our overlay on top of it.
 *
 * @return {boolean}
 */
function isPaymobOtpChallengeVisible() {
    const otpPattern = /4-digit code|enter the code we've sent|enter the code|one-time password|verification code/i;
    const modalSelectors = [
        '[role="dialog"]',
        '[aria-modal="true"]',
        '[class*="modal"]',
        '[class*="Modal"]',
        '[id*="paymob"]',
        '[class*="paymob"]',
    ];

    for (let i = 0; i < modalSelectors.length; i++) {
        const nodes = document.querySelectorAll(modalSelectors[i]);
        for (let j = 0; j < nodes.length; j++) {
            const node = nodes[j];
            if (!node || node.id === 'paymob-loading-indicator') {
                continue;
            }
            const text = (node.textContent || '').trim();
            if (!text || !otpPattern.test(text)) {
                continue;
            }
            const rect = node.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                return true;
            }
        }
    }

    return false;
}

/**
 * While payment is in progress, hide our overlay whenever OTP / 3DS appears.
 */
function startPaymobOtpOverlayGuard() {
    if (window.paymobOtpOverlayGuardStarted) {
        return;
    }
    window.paymobOtpOverlayGuardStarted = true;

    const guard = function () {
        if (isPaymobOtpChallengeVisible()) {
            hideLoadingIndicator();
            document.body.classList.add('paymob-otp-active');
        }
    };

    guard();
    window.paymobOtpOverlayGuardInterval = window.setInterval(guard, 250);

    if (typeof MutationObserver !== 'undefined') {
        window.paymobOtpOverlayObserver = new MutationObserver(guard);
        window.paymobOtpOverlayObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            characterData: true,
        });
    }
}

function stopPaymobOtpOverlayGuard() {
    window.paymobOtpOverlayGuardStarted = false;
    document.body.classList.remove('paymob-otp-active');
    if (window.paymobOtpOverlayGuardInterval) {
        window.clearInterval(window.paymobOtpOverlayGuardInterval);
        window.paymobOtpOverlayGuardInterval = null;
    }
    if (window.paymobOtpOverlayObserver) {
        window.paymobOtpOverlayObserver.disconnect();
        window.paymobOtpOverlayObserver = null;
    }
}

/////////////////////////////////////////////////Ajax To Place Order/////
function handleOrderCreation() {
        if (window.paymobCreateOrderInFlight) {
            console.log('create_order already in flight — skip duplicate');
            return;
        }
        window.paymobCreateOrderInFlight = true;

        const { select } = wp.data;
        const cartStore = select('wc/store/cart');
        const billingData = cartStore.getCustomerData().billingAddress;
        jQuery.ajax({
            url: pxl_object.ajax_url,
            type: 'POST',
            data: {
                action: 'create_order',
                    security: pxl_object.update_checkout_nonce,
                },
            success: function (response) {
               
                if (response.success) {
                   console.log('done');
                    
                } else {
                    console.log('failed');
                   
                }

            },
            complete: function () {
                window.paymobCreateOrderInFlight = false;
            }
            
        });
        
    
}

jQuery(document).ready(function ($) {
    if (!window.scriptInitialized) {
        window.scriptInitialized = true;
        // Load scripts and initialize Paymob element
        loadScripts();
            // Handle changes in billing address inputs
        jQuery(document).on('input change', '.wc-block-components-form input[required], .wc-block-components-form select[required]', function () {
            isCheckoutFormValid();
        });

        // Initial validation on page load
        isCheckoutFormValid();
        // handleOrderCreation();
    }
        
});

// handel place order in paymob-subscription

function getSelectedPaymentMethod() {
    const selected = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked');
    if (selected) {
        const paymentMethodId = selected.id.replace('radio-control-wc-payment-method-options-', '');
        const placeOrderSelectors = [
            '.wc-block-checkout__form .wc-block-components-button',
            '.wc-block-components-checkout-place-order-button',
            '#place_order'
        ];
        if (paymentMethodId=='paymob-subscription') {

            placeOrderSelectors.forEach(selector => {
                jQuery(selector).show().prop('disabled', false);
            });
        }
    }
}

function waitForSelectedPaymentMethod(attempts = 20) {
    const interval = setInterval(() => {
        const selected = getSelectedPaymentMethod();
        if (selected || attempts <= 0) {
            clearInterval(interval);
        }
        attempts--;
    }, 300);
}

document.addEventListener('DOMContentLoaded', () => {
    waitForSelectedPaymentMethod();
});

window.addEventListener("message", function (event) {
    const type = event.data?.type;
    if (type !== "discountResponse" && type !== "cardData" && type !== "instantRefundToggleResponse") {
        return;
    }

    const discountData = event.data.response?.res?.data;
    if (!discountData || typeof discountData !== "object") {
        return;
    }

    const discountCents = parseInt(discountData.discounted_amount_cents, 10) || 0;
    const feeCents = parseInt(discountData.instant_refund_fees, 10) || 0;
    const originalCents = parseInt(discountData.original_amount_cents, 10) || 0;
    let finalCents = parseInt(discountData.discount_amount_cents, 10) || 0;
    const hasDiscount = discountCents > 0;

    // Live cart in cents — if Paymob "original" is already below cart, intention reused a
    // previous discounted final and is about to double-discount (e.g. 5→4.50→4.05).
    let cartCents = 0;
    try {
        if (typeof wp !== 'undefined' && wp.data) {
            const cartTotals = wp.data.select('wc/store/cart').getCartTotals();
            if (cartTotals && cartTotals.total_price) {
                cartCents = parseInt(cartTotals.total_price, 10) || 0;
            }
        }
    } catch (err) {}
    if (cartCents <= 0 && typeof getBlocksCheckoutContext === 'function') {
        const ctx = getBlocksCheckoutContext();
        if (ctx && ctx.totalAmount) {
            cartCents = Math.round(parseFloat(ctx.totalAmount) * 100);
        }
    }

    if (hasDiscount && cartCents > 0 && originalCents > 0 && originalCents < cartCents - 1) {
        console.warn('Paymob discount original below cart — resetting intention to cart to avoid double discount.', {
            originalCents: originalCents,
            cartCents: cartCents,
        });
        window.paymobDiscountApplied = false;
        window.paymobTotalsSignature = null;
        if (typeof clearPaymobPixelCheckoutAdjustments === 'function') {
            clearPaymobPixelCheckoutAdjustments();
        }
        const ctx = (typeof getBlocksCheckoutContext === 'function') ? getBlocksCheckoutContext() : null;
        if (ctx && typeof ajaxCall === 'function') {
            ajaxCall(ctx.billingData, ctx.totalAmount, true, { resetDiscount: true });
        }
        return;
    }

    // Paymob often returns instant_refund_fees as an *eligible* amount even when
    // the Instant Refund toggle is OFF. Only treat IR as applied when explicit
    // flags say so, or when the final amount clearly includes the fee.
    const expectedWithoutFee = Math.max(0, originalCents - discountCents);
    const expectedWithFee = expectedWithoutFee + feeCents;
    const finalIncludesFee = feeCents > 0
        && finalCents > 0
        && Math.abs(finalCents - expectedWithFee) <= 2
        && Math.abs(finalCents - expectedWithoutFee) > 2;
    const instantRefundEnabled = discountData.instant_refund === true
        || discountData.instant_refund === 'true'
        || discountData.instant_refund_applied === true
        || discountData.instant_refund_applied === 'true'
        || (type === 'instantRefundToggleResponse' && feeCents > 0 && finalIncludesFee)
        || finalIncludesFee;
    const appliedFeeCents = instantRefundEnabled ? feeCents : 0;

    // Discount-only: some payloads omit discount_amount_cents (final). Derive it.
    if (finalCents <= 0 && originalCents > 0 && discountCents > 0) {
        finalCents = Math.max(0, originalCents - discountCents + appliedFeeCents);
    }

    // Nothing useful to sync.
    if (!hasDiscount && !instantRefundEnabled && finalCents <= 0) {
        return;
    }

    // cardData while typing: only apply once for discount (same as before).
    // Instant Refund toggle must still be allowed to update after discount.
    if (type === "cardData" && !instantRefundEnabled) {
        if (window.paymobDiscountApplied) {
            console.log("Paymob discount already applied, skipping duplicate.");
            return;
        }
        if (!hasDiscount) {
            return;
        }
    }

    const original = originalCents / 100;
    const discountValue = discountCents / 100;
    const feeValue = appliedFeeCents / 100;
    const finalTotal = finalCents / 100;
    const signature = [discountCents, appliedFeeCents, finalCents, instantRefundEnabled ? 1 : 0].join(":");

    if (window.paymobTotalsSignature === signature) {
        return;
    }
    window.paymobTotalsSignature = signature;
    if (hasDiscount) {
        window.paymobDiscountApplied = true;
    }

    jQuery.ajax({
        url: pxl_object.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
            action: "paymob_apply_discount",
            security: pxl_object.update_checkout_nonce,
            discount: discountValue,
            original: original,
            final_total: finalTotal,
            instant_refund_fee: feeValue,
            instant_refund_enabled: instantRefundEnabled ? 1 : 0,
            // Integer cents avoid float rounding (e.g. 0.5 → 1) in PHP/session.
            discount_cents: discountCents,
            original_cents: originalCents,
            final_cents: finalCents,
            instant_refund_fee_cents: appliedFeeCents,
        },
        success: function (response) {
            if (!response.success) {
                console.error("Failed to apply discount / instant refund:", response);
                return;
            }

            console.log("Paymob totals applied:", response.data);
            const isBlocksCheckout = document.querySelector(".wc-block-checkout") !== null;
            const displayDiscount = (response.data && typeof response.data.discount !== 'undefined')
                ? parseFloat(response.data.discount)
                : discountValue;
            const displayFee = (response.data && typeof response.data.instant_refund_fee !== 'undefined')
                ? parseFloat(response.data.instant_refund_fee)
                : feeValue;
            const displayFinal = (response.data && typeof response.data.final !== 'undefined')
                ? parseFloat(response.data.final)
                : finalTotal;

            // Store discounted final in Woo session only — do NOT PUT onto Pixel intention
            // (that made Order amount = previous final and Paymob re-discounted it).
            try {
                const syncPayload = {
                    action: "paymob_sync_pixel_intention",
                    security: pxl_object.update_checkout_nonce,
                    session_only: 1,
                };
                if (response.data && response.data.final_cents > 0) {
                    syncPayload.final_cents = response.data.final_cents;
                } else if (finalCents > 0) {
                    syncPayload.final_cents = finalCents;
                }
                jQuery.ajax({
                    url: pxl_object.ajax_url,
                    type: "POST",
                    dataType: "json",
                    data: syncPayload,
                    complete: function () {
                        try {
                            window.dispatchEvent(new Event("updateIntentionData"));
                        } catch (err) {}
                    },
                });
            } catch (err) {}

            setTimeout(function () {
                if (isBlocksCheckout) {
                    const discountContainer = document.querySelector(
                        ".wp-block-woocommerce-checkout-order-summary-discount-block.wc-block-components-totals-wrapper"
                    );
                    const totalsAnchor = document.querySelector(
                        ".wc-block-components-totals-footer-item, .wp-block-woocommerce-checkout-order-summary-totals-block"
                    );

                    const upsertBlocksLine = function (className, label, amount, isNegative) {
                        let line = document.querySelector("." + className);
                        if (!(amount > 0)) {
                            if (line && line.parentNode) {
                                line.parentNode.removeChild(line);
                            }
                            return;
                        }
                        if (!line) {
                            line = document.createElement("div");
                            line.className = "wc-block-components-totals-item " + className;
                            if (discountContainer) {
                                discountContainer.appendChild(line);
                            } else if (totalsAnchor && totalsAnchor.parentNode) {
                                totalsAnchor.parentNode.insertBefore(line, totalsAnchor);
                            } else {
                                return;
                            }
                        }
                        line.innerHTML =
                            '<span class="wc-block-components-totals-item__label">' + label + "</span>" +
                            '<span class="wc-block-components-totals-item__value">' +
                            (isNegative ? "-" : "") + "EGP " + Number(amount).toFixed(2) +
                            "</span>";
                    };

                    upsertBlocksLine("paymob-discount-line", "Discount", displayDiscount, true);
                    upsertBlocksLine("paymob-instant-refund-line", "Instant Refund Fee (non-refundable)", displayFee, false);

                    const totalEl = document.querySelector(
                        ".wc-block-components-totals-footer-item .wc-block-components-totals-item__value, .wc-block-components-totals-footer-item-tax-value, .wc-block-components-totals-footer-item-value, .wc-block-components-totals-footer-item .wc-block-formatted-money-amount"
                    );
                    if (totalEl && displayFinal > 0) {
                        totalEl.textContent = "EGP " + Number(displayFinal).toFixed(2);
                    }
                } else {
                    const totalEl = document.querySelector(
                        "tr.order-total .woocommerce-Price-amount.amount bdi, tr.order-total .woocommerce-Price-amount.amount"
                    );
                    if (totalEl && displayFinal > 0) {
                        totalEl.innerHTML =
                            '<span class="woocommerce-Price-currencySymbol">EGP</span>' + Number(displayFinal).toFixed(2);
                    }

                    const subtotalRow = document.querySelector("tr.cart-subtotal");
                    if (subtotalRow && subtotalRow.parentNode) {
                        const upsertClassicRow = function (className, label, amount, isNegative) {
                            let row = document.querySelector("tr." + className);
                            if (!(amount > 0)) {
                                if (row && row.parentNode) {
                                    row.parentNode.removeChild(row);
                                }
                                return;
                            }
                            if (!row) {
                                row = document.createElement("tr");
                                row.className = className;
                                subtotalRow.parentNode.insertBefore(row, subtotalRow.nextSibling);
                            }
                            row.innerHTML =
                                "<th>" + label + "</th>" +
                                '<td data-title="' + label + '">' +
                                '<span class="woocommerce-Price-amount amount"><bdi>' +
                                '<span class="woocommerce-Price-currencySymbol">EGP</span>' +
                                (isNegative ? "-" : "") + Number(amount).toFixed(2) +
                                "</bdi></span></td>";
                        };

                        upsertClassicRow("paymob-discount-row", "Discount", displayDiscount, true);
                        upsertClassicRow("paymob-instant-refund-row", "Instant Refund Fee (non-refundable)", displayFee, false);
                    }
                }
            }, 500);
        },
        error: function (err) {
            console.error("AJAX error applying discount / instant refund:", err);
        },
    });
});

function clearPaymobPixelCheckoutAdjustments() {
    window.paymobDiscountApplied = false;
    window.paymobTotalsSignature = null;

    document.querySelectorAll(
        '.paymob-discount-line, .paymob-instant-refund-line, .paymob-discount-row, .paymob-instant-refund-row'
    ).forEach(function (el) {
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    });

    function restoreOrderSummaryTotal(amount) {
        // var total = parseFloat(amount);
        // if (!(total >= 0) || isNaN(total)) {
        //     return;
        // }
        // var formatted = 'EGP ' + total.toFixed(2);

        // // Blocks checkout Total row
        // document.querySelectorAll(
        //     '.wc-block-components-totals-footer-item .wc-block-components-totals-item__value, .wc-block-components-totals-footer-item .wc-block-formatted-money-amount, .wc-block-components-totals-footer-item-tax-value, .wc-block-components-totals-footer-item-value'
        // ).forEach(function (el) {
        //     el.textContent = formatted;
        // });

        // // Classic checkout Total row
        // document.querySelectorAll(
        //     'tr.order-total .woocommerce-Price-amount.amount bdi, tr.order-total .woocommerce-Price-amount.amount'
        // ).forEach(function (el) {
        //     el.innerHTML = '<span class="woocommerce-Price-currencySymbol">EGP</span>' + total.toFixed(2);
        // });
    }

    function refreshBlocksCartStore() {
        try {
            if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
                return;
            }
            var cartDispatch = wp.data.dispatch('wc/store/cart');
            if (cartDispatch && typeof cartDispatch.invalidateResolution === 'function') {
                cartDispatch.invalidateResolution('getCartData');
                cartDispatch.invalidateResolution('getCartTotals');
            }
            if (wp.data.resolveSelect) {
                var cartSelect = wp.data.resolveSelect('wc/store/cart');
                if (cartSelect && typeof cartSelect.getCartData === 'function') {
                    cartSelect.getCartData().then(function (cart) {
                        if (cart && cart.totals && typeof cart.totals.total_price !== 'undefined') {
                            var minor = parseInt(cart.totals.currency_minor_unit, 10) || 2;
                            var total = parseInt(cart.totals.total_price, 10) / Math.pow(10, minor);
                            restoreOrderSummaryTotal(total);
                        }
                    }).catch(function () {});
                }
            }
        } catch (err) {}
    }

    // Prefer cart store total immediately if Blocks is available.
    try {
        if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
            var cartTotals = wp.data.select('wc/store/cart').getCartTotals();
            if (cartTotals && typeof cartTotals.total_price !== 'undefined') {
                var minorUnit = parseInt(cartTotals.currency_minor_unit, 10) || 2;
                restoreOrderSummaryTotal(parseInt(cartTotals.total_price, 10) / Math.pow(10, minorUnit));
            }
        }
    } catch (err) {}

    if (typeof pxl_object === 'undefined' || !pxl_object || !pxl_object.ajax_url) {
        refreshBlocksCartStore();
        if (window.jQuery) {
            window.jQuery(document.body).trigger('update_checkout');
        }
        return;
    }

    jQuery.post(pxl_object.ajax_url, {
        action: 'paymob_clear_discount',
        security: pxl_object.update_checkout_nonce,
    }).done(function (response) {
        if (response && response.success && response.data && typeof response.data.cart_total !== 'undefined') {
            restoreOrderSummaryTotal(response.data.cart_total);
        }
        refreshBlocksCartStore();
        if (window.jQuery) {
            window.jQuery(document.body).trigger('update_checkout');
        }
    }).fail(function () {
        refreshBlocksCartStore();
        if (window.jQuery) {
            window.jQuery(document.body).trigger('update_checkout');
        }
    });
}