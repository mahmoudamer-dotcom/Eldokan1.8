jQuery(document).ready(function ($) {
    var $currentLink, $currentGatewayId, $currentRow, $currentToggle;
    function showConfirmationPopup() {
        $('#confirmationModal').show();
    }

    function hideConfirmationPopup() {
        $('#confirmationModal').hide();
    }

    // Event delegation for dynamically loaded content
    $('tr[data-gateway_id="paymob-main"] .wc-payment-gateway-method-toggle-enabled').click(function (event) {
        event.preventDefault(); // Prevent the default action
        $currentLink = $(this).closest('a');
        $currentRow = $currentLink.closest('tr');
        $currentToggle = $currentRow.find('.woocommerce-input-toggle');
        $currentGatewayId = $currentRow.data('gateway_id');
        var gatewaySection = $currentLink.attr('href');
        if (gatewaySection && gatewaySection.includes('section=paymob-main')) {
            if ($currentToggle.hasClass('woocommerce-input-toggle--enabled') && !$currentToggle.hasClass('woocommerce-input-toggle--disabled')) {
                showConfirmationPopup();
                return false;
            }
            if (wc_admin_settings.exist) {
                if ($currentToggle.hasClass('woocommerce-input-toggle--disabled')) {
                    // Redirect to the paymob_list_gateways section after enabling
                    window.location.href = wc_admin_settings.paymob_list_gateways;
                }
            }
        }
    });

    $('#confirmDisable').click(function (event) {
        event.preventDefault(); // Prevent the default action
        $('#confirmDisable').prop('disabled', true);
        $.ajax({
            url: wc_admin_settings.ajax_url,
            type: 'POST',
            data: {
                action: 'paymob_toggle_gateway',
                gateway_id: $currentGatewayId,
                _ajax_nonce: wc_admin_settings.nonce
            },
            success: function (response) {
                if (response.success) {
                    $currentToggle.removeClass('woocommerce-input-toggle--enabled').addClass('woocommerce-input-toggle--disabled');
                    hideConfirmationPopup();
                } else {
                    console.error('Error:', response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    });

    $('#confirmCancel').click(hideConfirmationPopup);
});