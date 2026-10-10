<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// add  init paymob Dependencies

if ( ! class_exists( 'Paymob_Context' ) ) {
	include_once __DIR__ . '/../includes/helper/class-paymob-context.php';
}

if ( ! class_exists( 'PaymobAutoGenerate' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob-auto-generate.php';
}
require_once __DIR__ . '/../includes/helper/toggle-paymob-gateways.php';
require_once __DIR__ . '/../includes/helper/save-cards.php';
require_once __DIR__ . '/../includes/gateways/add-paymob-gateway.php';
require_once __DIR__ . '/../includes/helper/paymob-pixel.php';
require_once __DIR__ . '/../includes/helper/paymob-main.php';

if ( ! class_exists( 'Checkout_Blocks' ) ) {
	include_once __DIR__ . '/../includes/blocks/checkout-blocks.php';
}

if ( ! class_exists( 'Paymob_Error_Logs' ) ) {
	include_once __DIR__ . '/../includes/admin/paymob-error-logs/class-paymob-error-logs.php';
}

if ( ! class_exists( 'Paymob_Affordability_Widget' ) ) {
	include_once __DIR__ . '/../includes/helper/affordability_widget/class-paymob-affordability-widget.php';
}

if ( ! class_exists( 'Paymob' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob.php';
}

if ( ! class_exists( 'Paymob_WooCommerce' ) ) {
	include_once __DIR__ . '/paymob-for-woocommerce.php';
	new Paymob_WooCommerce( 'paymob-main' );
}

if ( ! class_exists( 'Paymob_Order' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob-order.php';
}

if ( ! class_exists( 'WC_Paymob_Loading' ) ) {
	include_once __DIR__ . '/class_wc_paymob_loading.php';
}
if ( ! class_exists( 'WC_Paymob_Tables' ) ) {
	include_once __DIR__ . '/class_wc_paymob_paymobTable.php';
}
if ( ! class_exists( 'WC_Paymob_HandelUpdate' ) ) {
	include_once __DIR__ . '/class_wc_paymob_handleUpdate.php';
}

if ( ! class_exists( 'WC_Paymob_GatewayData' ) ) {
	include_once __DIR__ . '/class_wc_paymob_gatewayData.php';
}

if ( ! class_exists( 'WC_Paymob_Install' ) ) {
	include_once __DIR__ . '/class_wc_paymob_Install.php';
}
if ( ! class_exists( 'WC_Paymob_UnInstall' ) ) {
	include_once __DIR__ . '/class_wc_paymob_uninstall.php';
}

if ( ! class_exists( 'WC_Paymob_Row_Meta' ) ) {
	include_once __DIR__ . '/class_wc_paymob_rowMeta.php';
}
if ( ! class_exists( 'WC_Paymob_RedirectFlag' ) ) {
	include_once __DIR__ . '/class_wc_paymob_set_redirect_flag_on_activation.php';
}
if ( ! class_exists( 'WC_Paymob_RedirectUrl' ) ) {
	include_once __DIR__ . '/class_wc_paymob_redirect_after_activation.php';
}
// add html admin views init Dependencies

if ( ! class_exists( 'MainHtmlInclude' ) ) {
	include_once __DIR__ . '/../includes/admin/views/includehtml.php';
}

if ( ! class_exists( 'Paymob_List_Gateways' ) ) {
	include_once __DIR__ . '/../includes/admin/paymob-list_gateway_table.php';
}
// add Script init Dependencies

if ( ! class_exists( 'Paymob_Scripts' ) ) {
	include_once __DIR__ . '/../includes/admin/scripts/paymob_admin_scripts.php';
}
// add Style init Dependencies

if ( ! class_exists( 'Paymob_Style' ) ) {
	include_once __DIR__ . '/../includes/admin/styles/paymob_admin_styles.php';
}

// add add_gateway init Dependencies

if ( ! class_exists( 'Paymob_Gateway_Settings' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-add-gateway-settings.php';
}

if ( ! class_exists( 'Paymob_Checkout_Section' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-add-checkout_section.php';
}

if ( ! class_exists( 'Paymob_Save_Gateway_Settings' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-save-gateway_settings.php';
}

if ( ! class_exists( 'Paymob_Verify_IntegrationID' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-verify-integrationID.php';
}

if ( ! class_exists( 'Paymob_Url_Exists' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-url-exists.php';
}

if ( ! class_exists( 'Paymob_Append_Gateway' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-append-gateway-to-paymob-order.php';
}

if ( ! class_exists( 'Paymob_List_Gateways_Settings' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-list-gateways-settings.php';
}

if ( ! class_exists( 'Paymob_Custom_Gateways_Table' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-create-custom-gateways-table.php';
}
if ( ! class_exists( 'Paymob_Reset_gateways' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-reset-gateways.php';
}
if ( ! class_exists( 'Paymob_Save_Gateway_Order' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-save-paymob-gateway-order.php';
}
if ( ! class_exists( 'Paymob_Apply_Gateway_Order' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-apply-paymob-gateway-order.php';
}

if ( ! class_exists( 'Paymob_Delete_Gateway' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-delete-gateway.php';
}

if ( ! class_exists( 'Paymob_Remove_Gateway_From_Order' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-remove-gateway-from-paymob-order.php';
}

if ( ! class_exists( 'Paymob_Toggle_Gateway' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-toggle-gateway.php';
}

if ( ! class_exists( 'Paymob_Register_Frameworks' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-register-frameworks.php';
}

if ( ! class_exists( 'Paymob_Gateways_HideCss' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-gateways-to-hide-css.php';
}

if ( ! class_exists( 'Paymob_List_Gateways_Style' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-list-gateways-styles.php';
}

if ( ! class_exists( 'Paymob_Admin_Scripts' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-enqueue-admin-scripts.php';
}

if ( ! class_exists( 'Paymob_Hide_Save_Button' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-hide-save-changes-button.php';
}
if ( ! class_exists( 'Paymob_Webhook_Url' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-webhook-url.php';
}

if ( ! class_exists( 'Paymob_Widget_Settings' ) ) {
	include_once __DIR__ . '/../includes/gateways/add_gateway/class-paymob-widget-section.php';
}
// add Save Cards init Dependencies

if ( ! class_exists( 'Paymob_Delete_Confirmation_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-add-delete-confirmation-modal.php';
}
if ( ! class_exists( 'Paymob_Save_Cards_Endpoints' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-add-saved-cards-endpoints.php';
}
if ( ! class_exists( 'Paymob_Save_Cards_Query_Vars' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-add-saved-cards-query-vars.php';
}
if ( ! class_exists( 'Paymob_Save_Cards_Tab' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-add-saved-cards-tab.php';
}
if ( ! class_exists( 'Paymob_Display_Save_Cards' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-display-saved-cards.php';
}
if ( ! class_exists( 'Paymob_Enqueue_Style' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-enqueue-saved-cards-styles.php';
}
if ( ! class_exists( 'Paymob_Handle_Card_Deletion' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-handle-card-deletion.php';
}
if ( ! class_exists( 'Paymob_Save_Cards_Title' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-saved-cards-title.php';
}
if ( ! class_exists( 'Paymob_Saved_Cards_Tokens' ) ) {
	include_once __DIR__ . '/../includes/helper/save_cards/class-paymob-saved-cards-tokens.php';
}

// add Toggle Gateway init Dependencies
if ( ! class_exists( 'Paymob_Custom_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-add-custom-modal-html.php';
}
if ( ! class_exists( 'Paymob_Check_IntergrationID' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-check-integrationID.php';
}
if ( ! class_exists( 'Paymob_Check_IntergrationMatch' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-check-intergation_match.php';
}
if ( ! class_exists( 'Paymob_Disable_Confirmation' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-disable-gateway-confirmation-script.php';
}
if ( ! class_exists( 'Paymob_Filter_currency' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-filter-on-currency.php';
}
if ( ! class_exists( 'Paymob_Handel_Toggle' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-handle-toggle-gateway.php';
}

// add Paymob Pixel init Dependencies
if ( ! class_exists( 'Paymob_Pixel_Settings' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-pixel-settings.php';
}
if ( ! class_exists( 'Paymob_Pixel_Style' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-pixel-styles.php';
}
if ( ! class_exists( 'Paymob_Pixel_Customization_Html' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-pixel-customization-html.php';
}
if ( ! class_exists( 'Paymob_Save_Pixel_Settings' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-save-pixel-settings.php';
}
if ( ! class_exists( 'Paymob_Update_Pixel_Data' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-update-pixel-data.php';
}
if ( ! class_exists( 'Paymob_Pixel_Checkout' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-pixel-checkout.php';
}
if ( ! class_exists( 'Paymob_Pixel_Update_Intention' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_pixel/class-paymob-pixel-update-Intention.php';
}
if ( ! class_exists( 'Paymob_Webhook_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-webhook_modal.php';
}

if ( ! class_exists( 'Paymob_Webhook_Update' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-webhook_update.php';
}
// add Paymob Pixel init Dependencies
if ( ! class_exists( 'Paymob_Main_Scripts' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-scripts.php';
}
if ( ! class_exists( 'Paymob_Main_Partner_Info' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-partner-info.php';
}
if ( ! class_exists( 'Paymob_Main_Connect_Account' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-connect-account.php';
}
if ( ! class_exists( 'Paymob_Manual_Setup_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-manual-setup-model.php';
}
if ( ! class_exists( 'Paymob_Manual_Setup_Save' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-manual-setup.php';
}

if ( ! class_exists( 'Paymob_Disconnect_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-disconnect-model.php';
}

if ( ! class_exists( 'Paymob_Disconnect_Save' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-disconnect-save.php';
}

if ( ! class_exists( 'Paymob_Change_Mode_Model' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-change-mode-model.php';
}
if ( ! class_exists( 'Paymob_Change_Mode_Save' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-change-mode-save.php';
}
if ( ! class_exists( 'Paymob_Unset_Old_Setting' ) ) {
	include_once __DIR__ . '/../includes/helper/paymob_main/class-paymob-unset-old-setting.php';
}

if ( ! class_exists( 'Paymob_Custom_Add_To_Cart' ) ) {
	include_once __DIR__ . '/../includes/helper/toggle_gateways/class-paymob-custom-add-to-cart.php';
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
