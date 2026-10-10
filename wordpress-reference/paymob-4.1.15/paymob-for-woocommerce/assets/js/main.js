jQuery(document).ready(function ($) {
    $('#connect_paymob').on('click', function(e) {
        e.preventDefault(); // Prevent default button action

        // Show a loading message or change the button text (optional)
        var $button = $(this);
        $button.prop('disabled', true).text('Connecting...');

        // Make the AJAX request
        $.ajax({
            url: main.ajax_url, // WordPress AJAX URL
            method: 'POST',
            data: {
                action: 'connect_paymob_account', // Action hook for PHP handler
                security: main.connect_paymob_nonce, // Nonce for security
            },
            success: function(response) {
                console.log(response); // Debugging the response
                if (response.success) {
                    // Handle success (you can change button text, show a success message, etc.)
                    $button.text('Redirecting To Paymob');
                    window.location.href = response.data.url+'&redirect_url='+main.current_url; // Redirect to the Paymob URL
                } else {
                    window.location.href = response.data.url;
                    // $button.prop('disabled', false).text('Try Again');
                }
            },
            error: function(xhr, status, error) {
                // Handle error (e.g., show an alert)
                console.log(xhr.responseText); // Debugging AJAX error
                window.location.href = response.data.url;
            }
        });
    });
    if(main.popup === 'true'){
        if(mode == 'live'){
            $('#liveKeys').show();
            $('#testKeys').hide();
        }else{
            $('#liveKeys').hide();
            $('#testKeys').show();
        }
        document.getElementById('manualsetupconfirmationModal').classList.add('show');
        jQuery('#manualsetupconfirmationModal').show() 
        jQuery('#manualsetupconfirmationModal').css('display','block');
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const errorMsg = urlParams.get('error-msg');
    if (!errorMsg) {
        return;
    }

    // Build the notice with textContent so URL payloads cannot execute as HTML/JS (DOM XSS).
    var $notice = jQuery('<div class="notice notice-error is-dismissible"></div>');
    var $p = jQuery('<p></p>').text(errorMsg);
    $notice.append($p);
    jQuery('.wrap').prepend($notice);
});



