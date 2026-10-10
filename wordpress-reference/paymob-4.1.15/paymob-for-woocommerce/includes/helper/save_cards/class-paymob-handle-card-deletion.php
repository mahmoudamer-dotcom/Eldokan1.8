<?php
class Paymob_Handle_Card_Deletion {

	public static function paymob_handle_card_deletions() {
		if ( Paymob::filterVar( 'delete_card_id' ) && is_user_logged_in() ) {
			$card_id = intval( Paymob::filterVar( 'delete_card_id' ) );
			$user_id = get_current_user_id();

			// Verify nonce for security
			if ( ! wp_verify_nonce( Paymob::filterVar( '_wpnonce' ), 'delete_card_' . $card_id ) ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'paymob_cards_token';

			// Ensure the card belongs to the logged-in user before deleting
			$wpdb->delete(
				$table_name,
				array(
					'id'      => $card_id,
					'user_id' => $user_id,
				),
				array( '%d', '%d' )
			);

			// Optionally add a message or redirect after deletion
			wc_add_notice( __( 'Card deleted successfully.', 'paymob-for-woocommerce' ), 'success' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'saved-cards' ) );
			exit;
		}
	}
}
