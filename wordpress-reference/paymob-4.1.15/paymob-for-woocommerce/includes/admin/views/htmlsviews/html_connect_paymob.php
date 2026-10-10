<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return '<div class="paymob-connect-div">
        <img style="width:10%; float: left;" src="' . plugins_url( PAYMOB_PLUGIN_NAME )  . '/assets/img/paymob.png" alt="Image" class="paymob-img">
        <div >
            Start Accepting Payments with Paymob Checkout offering 20+ Payment Methods including Card, Wallets,
            Bank Installments, Apple Pay, Google Pay, Instapay and a plethora of Buy Now Pay Later Payment Methods
            <div class="buttons-container" id="manual_setup">
                  <a class="button-action button button-primary" data-action="sm_enable_notice" data-key="migrations-required" id="connect_paymob">Connect your Paymob account</a>
                 
            <div>
             <a class="manual-setup-button">
              Manual Setup
            </a>
            </div>
        </div>
        </div>';
