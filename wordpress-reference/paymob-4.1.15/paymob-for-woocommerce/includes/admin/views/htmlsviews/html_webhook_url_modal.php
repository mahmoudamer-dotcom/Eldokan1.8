<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="webhookconfirmationModal" class="webhook-modal" style="display:none;">
    <div class="webhook-modal-content">
        <h2>Webhook Callbacks</h2>
        <p class="modal-description">Review and update the callback URLs for transaction processing.</p>
        <!-- Callback URLs -->
        <div class="modal-callbacks">
            <div class="modal-callback">
                <label for="processed_callback">Current Processed Callback:</label>
                <p id="processed_callback_display">Loading...</p>
            </div>
            <div class="modal-callback">
                <label for="response_callback">Current Response Callback:</label>
                <p id="response_callback_display">Loading...</p>
            </div>
        </div>
        <!-- Input Fields -->
        <label for="new_callback" id="integration_label">Click "Confirm" if you wish to update the Webhook URL to the one provided below for Integration ID :</label>
        <input type="text" disabled id="new_callback" value="<?php echo esc_url( add_query_arg( array( 'wc-api' => 'paymob_callback' ), home_url() ) ); ?>">
        <input type="hidden" id="integration_id">
        
        <!-- Buttons -->
        <div class="modal-buttons">
            <button id="modal_confirm_button">Confirm</button>
            <button id="modal_close_button">Cancel</button>
        </div>
    </div>
</div>
