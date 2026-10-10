<?php
class Paymob_Saved_Cards_Tokens {
	public static function getUserTokens() {
		$tokens = array();
		if ( is_user_logged_in() ) {
			global $wpdb;
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;
			$results      = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}paymob_cards_token WHERE user_id = %d",
					$user_id
				),
				OBJECT
			);
			if ( $results ) {
				foreach ( $results as $value ) {
					$tokens[] = $value->token;
				}
			}
		}
		return $tokens;
	}
}
