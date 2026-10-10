<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$paymobOptions = get_option('woocommerce_paymob-main_settings');
$mode = isset($paymobOptions['mode']) ? $paymobOptions['mode'] : 'test';
$sliderMode = ($mode=='test')?'':'silderMode';
$html = '<div class="paymob-mainconf-div"><div class="paymob-reconnect-div">
    <img src="' . plugins_url( PAYMOB_PLUGIN_NAME )  . '/assets/img/paymob.png" alt="Image" class="paymob-img" style="width:10%; float: left;">';

// $html.= ' <div class="buttons-container" id="re_setup_setup">
//             <div class="mode-toggle-container switch-mode" style="max-width: 20%;">
//                 <label for="mode-toggle"></label>
//                 <label class="switch">
//                     <span class="slider round '.$sliderMode.'"></span>
//                 </label>
//                 <span id="mode-status">'.ucfirst($mode).'</span>
//             </div>
//             <div>
//             <a  id="disconnect_paymob" class="button-action button smaller-button disconnect-button connection_buttons" >
//                    Disconnect
//             </a>

//             <a class="button-action button manual-setup-button  button-primary connection_buttons" id="connect_paymob">Re-Connect</a>
//             </div> 
//             <div style="padding-top:20px; margin-left:8%">or 
//             <a class="manual-setup-button " style="cursor:pointer;">Manual Setup</a></div>';
// $html.= ' </div> </div>';

$html.= ' <div class="buttons-container" id="re_setup_setup">
            <div class="mode-toggle-container switch-mode" id="changemodemodal_confirm_button" style="max-width: 20%;">
                <label for="mode-toggle"></label>
                <label class="switch">
                    <span class="slider round '.$sliderMode.'"></span>
                </label>
                <span id="mode-status">'.ucfirst($mode).'</span>
            </div>
            <div>
            <a  id="disconnect_paymob" class="button-action button smaller-button disconnect-button connection_buttons" >
                   Disconnect
            </a>

            
            </div> 
            <div style="padding-top:20px; margin-left:10%">
            <a class="manual-setup-button " style="cursor:pointer;">Manual Setup</a></div>';
$html.= ' </div> </div>';

return $html;