<?php
class Paymob_Delete_Confirmation_Model {
	public static function paymob_add_delete_confirmation_modal() {
		if ( is_wc_endpoint_url( 'saved-cards' ) ) {
			include_once __DIR__ . '/../../admin/views/htmlsviews/html_save_card_confirmation_model.php';
			Paymob_Scripts::get_save_card_confirmation_model_script();
		}
	}
}
