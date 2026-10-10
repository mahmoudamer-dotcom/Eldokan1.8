<?php
class Paymob_Display_Save_Cards {

	public static function paymob_display_saved_cards() {
		// Get the current user ID
		$user_id = get_current_user_id();

		global $wpdb;
		// Fetch card tokens for the current user
		$cards = $wpdb->get_results( $wpdb->prepare( "SELECT id, masked_pan, card_subtype FROM {$wpdb->prefix}paymob_cards_token WHERE user_id = %d", $user_id ), OBJECT );

		if ( $cards ) {

			echo '<h4>' . esc_html( __( 'Saved Cards', 'paymob-for-woocommerce' ) ) . '</h4>';
			echo '<table class="shop_table shop_table_responsive">';
			echo '<thead><tr><th>' . esc_html( __( 'Card', 'paymob-for-woocommerce' ) ) . '</th><th>' . esc_html( __( 'Actions', 'paymob-for-woocommerce' ) ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $cards as $card ) {
				echo '<tr>';
				echo '<td>' . esc_html( $card->masked_pan ) . ' (' . esc_html( ucfirst( $card->card_subtype ) ) . ')</td>';
				echo '<td><a href="' . esc_url( wp_nonce_url( add_query_arg( 'delete_card_id', $card->id ), 'delete_card_' . $card->id ) ) . '" class="delete-card-icon"><img src="' . esc_url( plugins_url( PAYMOB_PLUGIN_NAME ) . '/assets/img/delete-icon.png' ) . '" alt="' . esc_attr__( 'Delete', 'paymob-for-woocommerce' ) . '" /></a></td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
		} else {
			echo '<p>' . esc_html( __( 'No saved cards.', 'paymob-for-woocommerce' ) ) . '</p>';
		}
	}
}
