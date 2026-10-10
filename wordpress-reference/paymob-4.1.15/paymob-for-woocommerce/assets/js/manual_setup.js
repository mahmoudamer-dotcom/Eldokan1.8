jQuery.noConflict();
////////manual functionality /////////
jQuery(document).ready(function($) {
	$('#manual_setup').on('click', '.manual-setup-button', function () {
		if(mode == 'live'){
			$('#liveKeys').show();
	        $('#testKeys').hide();
    	}else{
    		$('#liveKeys').hide();
	        $('#testKeys').show();
    	}
		openWebhookModal();
	});
});
jQuery(document).ready(function($) {
	$('#re_setup_setup').on('click', '.manual-setup-button', function () {
		if(mode == 'live'){
			$('#liveKeys').show();
	        $('#testKeys').hide();
    	}else{
    		$('#liveKeys').hide();
	        $('#testKeys').show();
    	}
		openWebhookModal();
	});
});
// Function to open the modal and populate values
function openWebhookModal() {
   	document.getElementById('mode-toggle').checked ? jQuery('#mode-status2').html("Live") : jQuery('#mode-status2').html("Test");
	document.getElementById('manualsetupconfirmationModal').classList.add('show');
	jQuery('#manualsetupconfirmationModal').show() 
	jQuery('#manualsetupconfirmationModal').css('display','block');
}
    
// Function to handle form submission
document.getElementById('manualsetupmodal_confirm_button').addEventListener('click', function($) {
	
        // Gather input values
        const apiKey = document.getElementById('api_key').value;
        const testSecretKey = document.getElementById('test_secret_key').value;
        const liveSecretKey = document.getElementById('live_secret_key').value;
        const testPublicKey = document.getElementById('test_public_key').value;
        const livePublicKey = document.getElementById('live_public_key').value;
		const isTestMode = document.getElementById('mode-toggle').checked ? "live" : "test";
		if(isTestMode == 'test' && (testSecretKey.indexOf('sk_'+isTestMode) <0  || testPublicKey.indexOf('pk_'+isTestMode) <0)){
				jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:Invalid Public and Secret Keys for test mode</p></div>');
				// Optionally close the modal after success
				jQuery('#popupError').text('Error:Invalid Public and Secret Keys for test mode').css('color','red');
			
		}
		else if(isTestMode == 'live' && (liveSecretKey.indexOf('sk_'+isTestMode)  <0 ||livePublicKey.indexOf('pk_'+isTestMode)  <0)){
				jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:Invalid Public and Secret Keys for live mode</p></div>');
				jQuery('#popupError').text('Error:Invalid Public and Secret Keys for live mode').css('color','red');
		}else{
	        jQuery(".loader_paymob").show();
			jQuery.ajax({
				url: wc_admin_settings.ajax_url, // Your API endpoint
				type: 'POST',
				data: {
					action: 'manual_setup_save_keys',  // Action name, which you can handle in WordPress or your backend
					_ajax_nonce: wc_admin_settings.nonce,  // Action to process
					apiKey: apiKey,
					testSecretKey: testSecretKey,
					liveSecretKey:liveSecretKey,
					testPublicKey:testPublicKey,
					livePublicKey:livePublicKey,
					isTestMode:isTestMode

				},
				success: function(response) {
					
					if (response.success) {
						jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p>Configrations Saved successfully!</p></div>');
						// Optionally close the modal after success
						document.getElementById('manualsetupconfirmationModal').classList.remove('show');
						window.onbeforeunload = null; // Clear any "Leave this page" handlers.
						window.location.href = response.data.redirect_url;
					} else {
						jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:'+ response.data.message+'</p></div>');
						// Optionally close the modal after success
						jQuery('#popupError').text(response.data.message).css('color','red');

						// document.getElementById('manualsetupconfirmationModal').classList.remove('show');

					}
				        jQuery(".loader_paymob").hide();

				},
				error: function(xhr, status, error) {
					jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:'+error+ '</p></div>');
					// document.getElementById('manualsetupconfirmationModal').classList.remove('show');
					jQuery('#popupError').text(error).css('color','red');
				}
			});
			// Close Modal
			jQuery('#manualsetupmodal_close_button').on('click', function() {
				jQuery('#manualsetupconfirmationModal').hide();
			});
        }
    });



// Handling the close button click
document.getElementById('manualsetupmodal_close_button').addEventListener('click', function() {
	document.getElementById('manualsetupconfirmationModal').classList.remove('show');
});

jQuery(document).ready(function($) {
    // Handle the mode toggle
    $('#mode-toggle').on('change', function() {
        if (this.checked) {

            $('#mode-status2').text('Live'); // When checked, set Live Mode
			$('#liveKeys').show();
            $('#testKeys').hide();
        } else {
            $('#mode-status2').text('Test'); // When unchecked, set Test Mode
			$('#liveKeys').hide();
            $('#testKeys').show();
        }
    });
});

////////disconnect to paymob functionality /////////

jQuery(document).ready(function($) {
	$('#re_setup_setup').on('click', '.disconnect-button', function () {
		openDisconnectModal();
	});
});
// Function to open the modal and populate values
function openDisconnectModal() {
	
	document.getElementById('disconnectconfirmationModal').classList.add('show');
	jQuery('#disconnectconfirmationModal').show() 
	jQuery('#disconnectconfirmationModal').css('display','block');
}

// Handling the close button click
document.getElementById('disconnectmodal_close_button').addEventListener('click', function() {
	document.getElementById('disconnectconfirmationModal').classList.remove('show');
});
  
// Function to handle Disconnect 
document.getElementById('disconnectmodal_confirm_button').addEventListener('click', function() {
	jQuery.ajax({
		url: wc_admin_settings.ajax_url, // Your API endpoint
		type: 'POST',
		data: {
			action: 'disconnect_save_keys',  // Action name, which you can handle in WordPress or your backend
			_ajax_nonce: wc_admin_settings.nonce,  // Action to process
		},
		success: function(response) {
			
			if (response.success) {
				jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p> You Are Disconnect Sucessfully With Paymob Account</p></div>');
				// Optionally close the modal after success
				document.getElementById('disconnectconfirmationModal').classList.remove('show');
				window.onbeforeunload = null; // Clear any "Leave this page" handlers.
				window.location.href = response.data.redirect_url;
			} else {
				jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:'+ response.data.message+'</p></div>');
				// Optionally close the modal after success
				
				document.getElementById('disconnectconfirmationModal').classList.remove('show');

			}
		
		},
		error: function(xhr, status, error) {
			jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:'+error+ '</p></div>');
			document.getElementById('disconnectconfirmationModal').classList.remove('show');

		}
	});
	// Close Modal
	jQuery('#disconnectmodal_close_button').on('click', function() {
		jQuery('#disconnectconfirmationModal').hide();
	});
	
});

////////change mode functionality /////////

jQuery(document).ready(function($) {
	$('#re_setup_setup').on('click', '.switch-mode', function () {
		// openChangeModeModal();
		// if (this.checked) {
        //     $('#mode-status').text('Live'); // When checked, set Live Mode
        // } else {
        //     $('#mode-status').text('Test'); // When unchecked, set Test Mode
        // }
	});
});
// Function to open the modal and populate values
// function openChangeModeModal() {
	
// 	document.getElementById('changemodeconfirmationModal').classList.add('show');
// 	jQuery('#changemodeconfirmationModal').show() 
// 	jQuery('#changemodeconfirmationModal').css('display','block');
// }

// Handling the close button click
document.getElementById('changemodemodal_close_button').addEventListener('click', function() {
	document.getElementById('changemodeconfirmationModal').classList.remove('show');
});

// Function to handle Disconnect 
document.getElementById('changemodemodal_confirm_button').addEventListener('click', function() {
	jQuery.ajax({
		url: wc_admin_settings.ajax_url, // Your API endpoint
		type: 'POST',
		data: {
			action: 'change_mode_save',  // Action name, which you can handle in WordPress or your backend
			_ajax_nonce: wc_admin_settings.nonce,  // Action to process
		},
		success: function(response) {
			
			if (response.success) {
				jQuery('.wrap').prepend('<div class="notice notice-success is-dismissible"><p> You have changed the mode successfully!</p></div>');
				// Optionally close the modal after success
				document.getElementById('changemodeconfirmationModal').classList.remove('show');
				window.onbeforeunload = null; // Clear any "Leave this page" handlers.
				window.location.href = location.href;
			} else {
				// Optionally close the modal after success
				document.getElementById('manualsetupconfirmationModal').classList.add('show');
				var mode = response.data.mode;
				if(mode == 'live'){
		            jQuery('#liveKeys').show();
		            jQuery('#testKeys').hide();
		        }else{
		            jQuery('#liveKeys').hide();
		            jQuery('#testKeys').show();
		        }
				jQuery('#manualsetupconfirmationModal').show();
				jQuery('#manualsetupconfirmationModal').css('display','block');
				jQuery('#popupError').text(response.data.message).css('color','red');
				
			}
		
		},
		error: function(xhr, status, error) {
			if(error){
				jQuery('.wrap').prepend('<div class="notice notice-error is-dismissible"><p>Error:'+error+ '</p></div>');
				document.getElementById('changemodeconfirmationModal').classList.remove('show');
			}
		}
	});
	// Close Modal
	jQuery('#changemodemodal_close_button').on('click', function() {
		jQuery('#changemodeconfirmationModal').hide();
	});
	
});


