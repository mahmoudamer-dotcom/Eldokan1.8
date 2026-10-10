jQuery(document).ready(function ($) {

    var tableBodyHtml = window.paymob_gateways_table_body || '';
    $('#paymob_custom_gateways tbody').html(tableBodyHtml);
    
    function showConfirmationModal(title, message, confirmCallback, cancelCallback) {
        $('#confirmation-modal-title').text(title);
        $('#confirmation-modal-message').text(message);
        $('#confirmation-modal').show();
        $('#confirmation-modal-confirm').off('click').on('click', function () {
            $('#confirmation-modal').hide();
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
        });
        $('#confirmation-modal-cancel').off('click').on('click', function () {
            $('#confirmation-modal').hide();
            if (typeof cancelCallback === 'function') {
                cancelCallback();
            }
        });
    }

    $('#paymob_custom_gateways tbody').on('click', '.remove-button', function () {
        var button = $(this);
        var gatewayId = button.data('gateway-id');
        var nonce = paymob_list.delete_nonce;

        showConfirmationModal(
            paymob_list.rg,
            paymob_list.ays,
            function () {
                $.ajax({
                    url: paymob_list.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'delete_gateway',
                        security: nonce,
                        gateway_id: gatewayId,
                    },
                    success: function (response) {
                        if (response.success) {
                            button.closest('tr').remove();
                        } else {
                            alert('Failed to delete gateway: ' + response.data.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert('AJAX error: ' + status + ' - ' + error);
                    }
                });
            }
        );
    });

    $('#paymob_custom_gateways tbody').on('change', '.enable-checkbox', function () {
        var checkbox = $(this);
        var action = checkbox.prop('checked') ? 'enable' : 'disable';
        var gatewayId = checkbox.data('gateway-id');
        var integrationId = checkbox.data('integration-id');
        var nonce = paymob_list.toggle_nonce;
       
        if(action === 'enable'){
            enable(checkbox,gatewayId,integrationId,nonce);
        }
        else
        {
            showConfirmationModal(
                action.charAt(0).toUpperCase() + action.slice(1) + paymob_list.gat,
                paymob_list.ay + action + paymob_list.tg,
                function () {
                    $.ajax({
                        url: paymob_list.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'toggle_gateway',
                            security: nonce,
                            gateway_id: gatewayId,
                            integration_id: integrationId,
                            enable: checkbox.prop('checked')
                        },
                        success: function (response) {
                            if (response.success) {
                                jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.msg + '</p></div>');
                            } else {
                                jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>' + response.data.msg + '</p></div>');
                                checkbox.prop('checked', false);
                            }
                            setTimeout(function () {
                                jQuery('.notice').fadeOut();
                            }, 5000);
                        },
                        error: function (xhr, status, error) {
                            alert('AJAX error: ' + status + ' - ' + error);
                        }
                    });
                },
                function () {
                    checkbox.prop('checked', !checkbox.prop('checked'));
                }
            );
        }

       
    });
    $('#paymob_custom_gateways tbody').on('click', '.webhook_uRL', function () {
        var integrationId = $(this).attr("integration_id");
        var nonce = paymob_list.toggle_nonce;
        $(".loader_paymob").show();
        // Make AJAX request
        $.ajax({
            url: paymob_list.ajax_url,
            type: 'POST',
            data: {
                action: 'webhook_url',
                _ajax_nonce: wc_admin_settings.nonce,
                integration_id: integrationId,
            },
            success: function (response) {
                if (response.success) {
                    // Log the transaction callbacks for debugging
                    var processed_callback = response.data.data.transaction_processed_callback;
                    var response_callback = response.data.data.transaction_response_callback;

                    // Populate the modal's input fields with the returned data
                    $('#processed_callback').val(processed_callback);
                    $('#response_callback').val(response_callback);
                    $('#integration_id').val(integrationId);
                    document.getElementById('integration_label').innerHTML += ' ' + integrationId;
                    // Open the modal
                    // $('#webhookconfirmationModal').show(); // Display the modal
                    openWebhookModal(processed_callback, response_callback, integrationId)
                } else {
                    // Show error message
                    $(".loader_paymob").hide();
                    jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error: ' + response.data.msg + '</p></div>');
                }

                // Auto fade out the notices after 5 seconds
                setTimeout(function () {
                    jQuery('.notice').fadeOut();
                }, 5000);
            },
            error: function (xhr, status, error) {
                $(".loader_paymob").hide();
                alert('AJAX error: ' + status + ' - ' + error);
            }
        });
           
        
    });

    // Function to open the modal and populate values
    function openWebhookModal(processedCallback, responseCallback, integrationId) {
        // Populate the current callback values
        document.getElementById('processed_callback_display').textContent = processedCallback || "No current callback set.";
        document.getElementById('response_callback_display').textContent = responseCallback || "No current callback set.";
        
        // Set the hidden integration_id
        document.getElementById('integration_id').value = integrationId;
        $(".loader_paymob").hide();
        // Show the modal with the 'show' class
        document.getElementById('webhookconfirmationModal').classList.add('show');
        jQuery('#webhookconfirmationModal').show() 
        jQuery('#webhookconfirmationModal').css('display','block');
    }
    
    // Function to handle form submission
    document.getElementById('modal_confirm_button').addEventListener('click', function() {
        const newCallback = document.getElementById('new_callback').value;
        const integrationId = document.getElementById('integration_id').value;
        $(".loader_paymob").show();

        if (newCallback) {
            document.getElementById('webhookconfirmationModal').classList.remove('show');

            // Send the new callback value to the server
            $.ajax({
                url: paymob_list.ajax_url, // Your API endpoint
                type: 'POST',
                data: {
                    action: 'save_webhook_callbacks',  // Action name, which you can handle in WordPress or your backend
                    _ajax_nonce: wc_admin_settings.nonce,  // Action to process
                    integration_id: integrationId,
                    new_callback: newCallback
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>Callbacks Updated successfully!</p></div>');
                        // Optionally close the modal after success
                    } else {
                        jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error: Cannot Update callbacks Url</p></div>');

                    }
                    $(".loader_paymob").hide();

                },
                error: function(xhr, status, error) {
                    alert('AJAX Error: ' + error);
                }
            });
        } else {
            alert('Please enter a new callback URL.');
        }
    });
    
    // Handling the close button click
    document.getElementById('modal_close_button').addEventListener('click', function() {
        document.getElementById('webhookconfirmationModal').classList.remove('show');
    });
    
    $('body').on('click', '.show-more', function () {
        var $this = $(this);
        var shortDescription = $this.siblings('.short-description');
        var fullDescription = $this.siblings('.full-description');

        if (fullDescription.is(':hidden')) {
            shortDescription.hide();
            fullDescription.show();
            $this.text('Show Less');
        } else {
            shortDescription.show();
            fullDescription.hide();
            $this.text('Show More');
        }
    });

    $("#paymob_custom_gateways tbody").sortable({
        items: "tr",
        dropOnEmpty: false,
        update: function (event, ui) {
            const order = $(this).sortable('toArray', { attribute: 'data-gateway-id' });
            // Send the order to the server via AJAX
            $.ajax({
                url: paymob_list.ajax_url,
                method: 'POST',
                data: {
                    action: 'save_paymob_gateway_order',
                    order: order,
                    security: paymob_list.save_gateway_order_nonce,
                },
                success: function (response) {
                    //alert('Order saved successfully!');
                },
                error: function () {
                    alert('Failed to save order.');
                }
            });
        }
    });
    $("#reset-paymob-gateways").click(function () {
        showConfirmationModal(
            paymob_list.rp,
            paymob_list.arp,
            function () {
                // Confirm callback: AJAX call to reset the gateways
                // alert('reset');return false;
                $.ajax({
                    url: paymob_list.ajax_url,
                    type: "POST",
                    data: {
                        action: "reset_paymob_gateways",
                        security: paymob_list.reset_paymob_gateways_nonce,
                    },
                    beforeSend: function () {
                        $(".loader_paymob").show();
                    },
                    success: function (response) {
                        
                        if(!response.success){
                            const data=JSON.parse(response.data);
                            alert(data.error);
                        }
                        location.reload();
                    },
                    complete: function () {
                        // $(".loader_paymob").hide();
                    },
                    error: function (xhr, status, error) {
                        alert('An error occurred while resetting the payment methods.');
                    }
                });
            },
            function () {
                // Cancel callback: No action needed, modal just closes
                //console.log("Reset canceled");
            }
        );
    });
    // Disable WooCommerce's and any other `beforeunload` handlers
    function disableBeforeUnload() {
        $(window).off('beforeunload');
        window.onbeforeunload = null;
    }

    // Run initially
    disableBeforeUnload();

    // Monitor changes and remove any added handlers every second
    setInterval(function () {
        disableBeforeUnload();
    }, 1000);

    function enable(checkbox,gatewayId,integrationId,nonce)
    {
        $.ajax({
            url: paymob_list.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_gateway',
                security: nonce,
                gateway_id: gatewayId,
                integration_id: integrationId,
                enable: checkbox.prop('checked')
            },
            success: function (response) {
                if (response.success) {
                    jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>' + response.data.msg + '</p></div>');
                } else {
                    jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>' + response.data.msg + '</p></div>');
                    checkbox.prop('checked', false);
                }
                setTimeout(function () {
                    jQuery('.notice').fadeOut();
                }, 5000);
            },
            error: function (xhr, status, error) {
                alert('AJAX error: ' + status + ' - ' + error);
            }
        });
    }

});